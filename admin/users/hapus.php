<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Hapus User';
include '../../config/database.php';

// Handle konfirmasi hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $type = sanitizeInput($_POST['type']);
    $redirect_page = $type === 'anggota' ? 'anggota.php' : 'petugas.php';
    
    try {
        // Hapus Petugas
        if ($type === 'petugas') {
            $user_id = (int)$_POST['user_id'];
            
            // Get petugas data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'petugas'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Data petugas tidak ditemukan!');
            }
            
            // Check if current user is trying to delete themselves
            if ($user_id === $_SESSION['user_id']) {
                throw new Exception('Anda tidak dapat menghapus akun Anda sendiri!');
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'petugas'");
            $stmt->execute([$user_id]);
            
            logActivity('HAPUS_PETUGAS', "Petugas {$user['nama']} (ID: {$user_id}) dihapus");
            redirect($redirect_page, "Petugas '{$user['nama']}' berhasil dihapus!", 'success');
        }
        // Hapus Anggota
        else if ($type === 'anggota') {
            $nik = sanitizeInput($_POST['nik']);
            
            // Get anggota data
            $stmt = $conn->prepare("SELECT a.*, u.id as user_id FROM anggota a LEFT JOIN users u ON a.user_id = u.id WHERE a.nik = ?");
            $stmt->execute([$nik]);
            $anggota = $stmt->fetch();
            
            if (!$anggota) {
                throw new Exception('Data anggota tidak ditemukan!');
            }
            
            // Check for active loans
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
            $stmt->execute([$nik]);
            $active_loans = $stmt->fetchColumn();
            
            if ($active_loans > 0) {
                throw new Exception("Tidak dapat menghapus anggota yang masih memiliki {$active_loans} pinjaman aktif!");
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            // Delete user account if exists
            if ($anggota['user_id']) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$anggota['user_id']]);
            }
            
            // Delete borrowing history
            $stmt = $conn->prepare("DELETE FROM peminjaman WHERE nik = ?");
            $stmt->execute([$nik]);
            
            // Delete anggota
            $stmt = $conn->prepare("DELETE FROM anggota WHERE nik = ?");
            $stmt->execute([$nik]);
            
            $conn->commit();
            
            logActivity('HAPUS_ANGGOTA', "Anggota {$anggota['nama']} (NIK: {$nik}) dihapus");
            redirect($redirect_page, "Anggota '{$anggota['nama']}' berhasil dihapus!", 'success');
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        redirect($redirect_page, $e->getMessage(), 'error');
    }
}

// Get parameters from URL
$type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$redirect_page = $type === 'anggota' ? 'anggota.php' : 'petugas.php';

// Validate type
if (!in_array($type, ['petugas', 'anggota'])) {
    redirect('petugas.php', 'Tipe user tidak valid!', 'error');
}

try {
    if ($type === 'petugas') {
        // Get petugas data
        $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (empty($user_id)) {
            redirect($redirect_page, 'ID petugas tidak valid!', 'error');
        }
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'petugas'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            redirect($redirect_page, 'Data petugas tidak ditemukan!', 'error');
        }
        
        // Check if trying to delete self
        if ($user_id === $_SESSION['user_id']) {
            redirect($redirect_page, 'Anda tidak dapat menghapus akun Anda sendiri!', 'error');
        }
        
    } else {
        // Get anggota data
        $nik = isset($_GET['nik']) ? sanitizeInput($_GET['nik']) : '';
        
        if (empty($nik)) {
            redirect($redirect_page, 'NIK tidak valid!', 'error');
        }
        
        $stmt = $conn->prepare("
            SELECT a.*, u.id as user_id, u.username, u.email
            FROM anggota a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.nik = ?
        ");
        $stmt->execute([$nik]);
        $user = $stmt->fetch();
        
        if (!$user) {
            redirect($redirect_page, 'Data anggota tidak ditemukan!', 'error');
        }
        
        // Check for active loans
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_pinjam,
                   COUNT(CASE WHEN status = 'dipinjam' THEN 1 END) as pinjam_aktif
            FROM peminjaman 
            WHERE nik = ?
        ");
        $stmt->execute([$nik]);
        $loan_info = $stmt->fetch();
        
        // Get active loan details if any
        $active_loans = [];
        if ($loan_info['pinjam_aktif'] > 0) {
            $stmt = $conn->prepare("
                SELECT p.*, b.judul
                FROM peminjaman p
                JOIN buku b ON p.isbn = b.isbn
                WHERE p.nik = ? AND p.status = 'dipinjam'
                ORDER BY p.tanggal_pinjam DESC
            ");
            $stmt->execute([$nik]);
            $active_loans = $stmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    redirect($redirect_page, 'Error: ' . $e->getMessage(), 'error');
}

$body_class = 'admin-dashboard';
include '../../includes/header.php';
?>

<style>
body.admin-dashboard {
    background: #FFFDD0;
    min-height: 100vh;
}

.delete-container {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
.page-header-delete {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);
    margin-bottom: 2rem;
    animation: slideDown 0.6s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-header-delete h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.page-header-delete p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

/* Warning Alert */
.warning-card {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-left: 5px solid #ffc107;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 20px rgba(255, 193, 7, 0.2);
    animation: fadeIn 0.6s ease 0.2s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.warning-card h5 {
    color: #856404;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.warning-card p {
    color: #856404;
    margin: 0;
}

/* Detail Card */
.detail-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease 0.3s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.detail-card-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    padding: 1.5rem;
    color: white;
}

.detail-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.3rem;
}

.detail-card-body {
    padding: 2rem;
}

/* Avatar Section */
.avatar-section {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    margin-bottom: 1.5rem;
}

.avatar-circle-delete {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 4rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.role-badge-delete {
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-block;
}

/* Info Table */
.info-table {
    width: 100%;
}

.info-table tr {
    border-bottom: 1px solid #f0f0f0;
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table th {
    padding: 1rem;
    font-weight: 600;
    color: #495057;
    width: 180px;
    text-align: left;
}

.info-table td {
    padding: 1rem;
    color: #333;
}

.info-value {
    font-weight: 600;
    font-size: 1.1rem;
}

/* Confirmation Box */
.confirmation-box {
    background: linear-gradient(135deg, #fff5f5, #ffe0e0);
    border: 2px solid #dc3545;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.form-check-input:checked {
    background-color: #dc3545;
    border-color: #dc3545;
}

/* Buttons */
.btn-delete {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    padding: 1rem 2.5rem;
    border-radius: 50px;
    font-weight: 600;
    border: none;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.btn-delete:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(220, 53, 69, 0.4);
    color: white;
}

.btn-delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-cancel {
    background: white;
    color: #6c757d;
    padding: 1rem 2.5rem;
    border-radius: 50px;
    font-weight: 600;
    border: 2px solid #6c757d;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-cancel:hover {
    background: #6c757d;
    color: white;
}

/* Active Loans Table */
.loans-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.loans-table table {
    width: 100%;
    margin: 0;
}

.loans-table thead {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.loans-table th {
    padding: 1rem;
    font-weight: 600;
    color: #495057;
    border: none;
}

.loans-table td {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.loans-table tbody tr:hover {
    background: #f8f9fa;
}

/* Error State */
.error-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    animation: fadeInUp 0.6s ease 0.3s both;
}

.error-card-header {
    background: linear-gradient(135deg, #dc3545, #c82333);
    padding: 1.5rem;
    color: white;
}

.error-alert {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

/* Final Warning */
.final-warning {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.final-warning ul {
    margin-bottom: 0;
}

/* Breadcrumb */
.breadcrumb {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.breadcrumb-item a {
    color: #1e3c72;
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-item a:hover {
    color: #4A90E2;
}

.breadcrumb-item.active {
    color: #6c757d;
}
</style>

<div class="container py-4">
    <div class="delete-container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= $redirect_page ?>">Kelola <?= ucfirst($type) ?></a></li>
                <li class="breadcrumb-item active">Hapus <?= ucfirst($type) ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header-delete">
            <h1>
                <i class="fas fa-exclamation-triangle me-2"></i>
                Konfirmasi Hapus <?= ucfirst($type) ?>
            </h1>
            <p>Periksa informasi dengan teliti sebelum menghapus data</p>
        </div>

        <!-- Warning Alert -->
        <div class="warning-card">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-3x text-warning me-3"></i>
                <div>
                    <h5>Perhatian!</h5>
                    <p>Data yang dihapus tidak dapat dikembalikan. Pastikan Anda yakin sebelum melanjutkan.</p>
                </div>
            </div>
        </div>

        <?php if ($type === 'anggota' && $loan_info['pinjam_aktif'] > 0): ?>
            <!-- Error: Anggota has active loans -->
            <div class="error-card">
                <div class="error-card-header">
                    <h5><i class="fas fa-times-circle me-2"></i>Tidak Dapat Menghapus Anggota</h5>
                </div>
                <div class="detail-card-body">
                    <div class="error-alert">
                        <strong><i class="fas fa-exclamation-circle me-2"></i>Anggota ini masih memiliki <?= $loan_info['pinjam_aktif'] ?> pinjaman aktif!</strong>
                        <br>
                        Anggota tidak dapat dihapus sampai semua buku dikembalikan.
                    </div>

                    <!-- Member Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4><?= htmlspecialchars($user['nama']) ?></h4>
                        <p class="text-muted mb-0">NIK: <code><?= htmlspecialchars($user['nik']) ?></code></p>
                    </div>

                    <!-- Active Loans -->
                    <h6 class="mb-3"><i class="fas fa-list me-2"></i>Pinjaman Aktif:</h6>
                    <div class="loans-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Buku</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Harus Kembali</th>
                                    <th>Status Denda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($active_loans as $loan): 
                                    $hari_terlambat = hitungHariTerlambat($loan['tanggal_kembali']);
                                    $denda = hitungDenda($hari_terlambat);
                                    $denda_display = ($hari_terlambat > 0) ? formatRupiah($denda) : '<span class="text-success">Tidak ada denda</span>';
                                    $is_late = $hari_terlambat > 0;
                                ?>
                                    <tr style="<?= $is_late ? 'background: #fff0f0;' : '' ?>">
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($loan['judul']) ?></strong></td>
                                        <td><?= formatTanggal($loan['tanggal_pinjam']) ?></td>
                                        <td><?= formatTanggal($loan['tanggal_kembali']) ?></td>
                                        <td><?= $denda_display ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a href="<?= $redirect_page ?>" class="btn-cancel">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                        <a href="../transaksi/peminjaman.php?nik=<?= urlencode($user['nik']) ?>" class="btn" style="background: linear-gradient(135deg, #4A90E2, #357ABD); color: white; padding: 1rem 2.5rem; border-radius: 50px; font-weight: 600; text-decoration: none;">
                            <i class="fas fa-list me-2"></i>Lihat Peminjaman
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Confirmation Form -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h5><i class="fas fa-user me-2"></i>Detail <?= ucfirst($type) ?> yang Akan Dihapus</h5>
                </div>
                <div class="detail-card-body">
                    <!-- Avatar and Basic Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-<?= $type === 'petugas' ? 'user-tie' : 'user' ?>"></i>
                        </div>
                        <h4><?= htmlspecialchars($user['nama']) ?></h4>
                        <span class="role-badge-delete bg-<?= $type === 'petugas' ? 'info' : 'primary' ?>">
                            <?= strtoupper($type) ?>
                        </span>
                    </div>

                    <!-- Detailed Info -->
                    <table class="info-table">
                        <?php if ($type === 'petugas'): ?>
                            <tr>
                                <th><i class="fas fa-hashtag me-2"></i>ID User</th>
                                <td><code class="info-value"><?= $user['id'] ?></code></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-user me-2"></i>Username</th>
                                <td><code class="info-value"><?= htmlspecialchars($user['username']) ?></code></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-id-card me-2"></i>Nama Lengkap</th>
                                <td><span class="info-value"><?= htmlspecialchars($user['nama']) ?></span></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-envelope me-2"></i>Email</th>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-shield-alt me-2"></i>Role</th>
                                <td><span class="badge bg-info"><?= strtoupper($user['role']) ?></span></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                                <td><span class="badge bg-<?= $user['status'] === 'aktif' ? 'success' : 'secondary' ?>"><?= strtoupper($user['status']) ?></span></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-calendar me-2"></i>Terdaftar Sejak</th>
                                <td><?= formatTanggal($user['created_at']) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th><i class="fas fa-id-card me-2"></i>NIK</th>
                                <td><code class="info-value"><?= htmlspecialchars($user['nik']) ?></code></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-user me-2"></i>Nama Lengkap</th>
                                <td><span class="info-value"><?= htmlspecialchars($user['nama']) ?></span></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-phone me-2"></i>No. HP</th>
                                <td><?= htmlspecialchars($user['no_hp'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-map-marker-alt me-2"></i>Alamat</th>
                                <td><?= htmlspecialchars($user['alamat']) ?></td>
                            </tr>
                            <?php if ($user['user_id']): ?>
                            <tr>
                                <th><i class="fas fa-user-circle me-2"></i>Username</th>
                                <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                            </tr>
                            <tr>
                                <th><i class="fas fa-envelope me-2"></i>Email</th>
                                <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><i class="fas fa-calendar me-2"></i>Terdaftar Sejak</th>
                                <td><?= formatTanggal($user['created_at']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <!-- Statistics for Anggota -->
                    <?php if ($type === 'anggota' && $loan_info['total_pinjam'] > 0): ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Riwayat Peminjaman:</strong> Anggota ini pernah meminjam <strong><?= $loan_info['total_pinjam'] ?></strong> kali.
                            Semua riwayat akan ikut terhapus.
                        </div>
                    <?php endif; ?>

                    <!-- Confirmation Checkbox -->
                    <div class="confirmation-box mt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmCheckbox" style="width: 20px; height: 20px;">
                            <label class="form-check-label ms-2" for="confirmCheckbox" style="font-weight: 600; font-size: 1.05rem;">
                                Saya memahami bahwa data yang dihapus tidak dapat dikembalikan
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                        <?php if ($type === 'petugas'): ?>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <?php else: ?>
                            <input type="hidden" name="nik" value="<?= htmlspecialchars($user['nik']) ?>">
                        <?php endif; ?>
                        
                        <div class="d-flex gap-3 justify-content-between mt-4">
                            <a href="<?= $redirect_page ?>" class="btn-cancel">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="confirm_delete" class="btn-delete" id="deleteButton" disabled>
                                <i class="fas fa-trash me-2"></i>Hapus <?= ucfirst($type) ?> Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Final Warning -->
            <div class="final-warning">
                <h6><i class="fas fa-skull-crossbones me-2"></i>Peringatan Terakhir!</h6>
                <p class="mb-2">Dengan menghapus <?= $type ?> ini, Anda akan kehilangan:</p>
                <ul>
                    <li>Data <?= $type ?> secara permanen</li>
                    <?php if ($type === 'anggota'): ?>
                        <li>Akun user terkait (jika ada)</li>
                        <li>Semua riwayat peminjaman (<?= $loan_info['total_pinjam'] ?> record)</li>
                    <?php else: ?>
                        <li>Akun login petugas</li>
                        <li>Riwayat aktivitas petugas</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
document.getElementById('confirmCheckbox')?.addEventListener('change', function() {
    document.getElementById('deleteButton').disabled = !this.checked;
});

// Confirm before submit
document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
    const type = '<?= $type ?>';
    const nama = '<?= htmlspecialchars($user['nama'], ENT_QUOTES) ?>';
    
    if (!confirm(`Apakah Anda BENAR-BENAR YAKIN ingin menghapus ${type} "${nama}"?\n\nTindakan ini TIDAK DAPAT DIBATALKAN!`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>