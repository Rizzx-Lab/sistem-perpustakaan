<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Hapus Buku';
include '../../config/database.php';

// Handle konfirmasi hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $isbn = sanitizeInput($_POST['isbn']);
    
    try {
        // Check if book exists
        $stmt = $conn->prepare("SELECT * FROM buku WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $book = $stmt->fetch();
        
        if (!$book) {
            throw new Exception('Buku tidak ditemukan!');
        }
        
        // Check if book is currently borrowed
        $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE isbn = ? AND status = 'dipinjam'");
        $stmt->execute([$isbn]);
        $borrowed_count = $stmt->fetchColumn();
        
        if ($borrowed_count > 0) {
            throw new Exception("Tidak dapat menghapus buku yang sedang dipinjam! Terdapat {$borrowed_count} peminjaman aktif.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete kategori associations first
        $stmt = $conn->prepare("DELETE FROM buku_kategori WHERE isbn = ?");
        $stmt->execute([$isbn]);
        
        // Delete pengembalian records (must delete before peminjaman due to FK)
        $stmt = $conn->prepare("
            DELETE pengembalian FROM pengembalian
            INNER JOIN peminjaman ON pengembalian.id_peminjaman = peminjaman.id_peminjaman
            WHERE peminjaman.isbn = ?
        ");
        $stmt->execute([$isbn]);
        
        // Delete borrowing history
        $stmt = $conn->prepare("DELETE FROM peminjaman WHERE isbn = ?");
        $stmt->execute([$isbn]);
        
        // Delete the book
        $stmt = $conn->prepare("DELETE FROM buku WHERE isbn = ?");
        $stmt->execute([$isbn]);
        
        $conn->commit();
        
        // Log activity
        logActivity('HAPUS_BUKU', "Buku dihapus: {$book['judul']} (ISBN: {$isbn})", 'buku', $isbn);
        
        redirect('index.php', "Buku '{$book['judul']}' berhasil dihapus!", 'success');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        redirect('index.php', $e->getMessage(), 'error');
    }
}

// Get ISBN from URL
if (!isset($_GET['isbn']) || empty($_GET['isbn'])) {
    redirect('index.php', 'ISBN tidak valid!', 'error');
}

$isbn = sanitizeInput($_GET['isbn']);

try {
    // Get book data
    $stmt = $conn->prepare("SELECT * FROM buku WHERE isbn = ?");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch();
    
    if (!$book) {
        redirect('index.php', 'Buku tidak ditemukan!', 'error');
    }
    
    // Check if book is currently borrowed
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_dipinjam,
               COUNT(CASE WHEN status = 'dipinjam' THEN 1 END) as sedang_dipinjam
        FROM peminjaman 
        WHERE isbn = ?
    ");
    $stmt->execute([$isbn]);
    $peminjaman_info = $stmt->fetch();
    
    // Get active borrowers if any
    $active_borrowers = [];
    if ($peminjaman_info['sedang_dipinjam'] > 0) {
        $stmt = $conn->prepare("
            SELECT p.*, a.nama, a.no_hp
            FROM peminjaman p
            JOIN anggota a ON p.nik = a.nik
            WHERE p.isbn = ? AND p.status = 'dipinjam'
            ORDER BY p.tanggal_pinjam DESC
        ");
        $stmt->execute([$isbn]);
        $active_borrowers = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    redirect('index.php', 'Error: ' . $e->getMessage(), 'error');
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
    border-radius: 12px;
    padding: 1.2rem 1.5rem;
    color: white;
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
    margin-bottom: 1.5rem;
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
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.page-header-delete p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

/* Warning Alert */
.warning-card {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-left: 4px solid #ffc107;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 3px 10px rgba(255, 193, 7, 0.15);
    animation: fadeIn 0.6s ease 0.2s both;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.warning-card h5 {
    color: #856404;
    font-weight: 700;
    margin-bottom: 0.3rem;
    font-size: 1rem;
}

.warning-card p {
    color: #856404;
    margin: 0;
    font-size: 0.9rem;
}

/* Detail Card */
.detail-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 1.5rem;
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
    padding: 1rem 1.2rem;
    color: white;
}

.detail-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
}

.detail-card-body {
    padding: 1.5rem;
}

/* Avatar Section */
.avatar-section {
    padding: 1.2rem;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 10px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.avatar-circle-delete {
    width: 80px;
    height: 80px;
    min-width: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.25);
}

.avatar-info {
    flex: 1;
}

.role-badge-delete {
    padding: 0.3rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-block;
}

.avatar-section h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
}

.avatar-section p {
    font-size: 0.9rem;
    margin: 0;
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
    padding: 0.7rem;
    font-weight: 600;
    color: #495057;
    width: 140px;
    text-align: left;
    font-size: 0.9rem;
}

.info-table td {
    padding: 0.7rem;
    color: #333;
    font-size: 0.9rem;
}

.info-value {
    font-weight: 600;
    font-size: 1rem;
}

/* Confirmation Box */
.confirmation-box {
    background: linear-gradient(135deg, #fff5f5, #ffe0e0);
    border: 2px solid #dc3545;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.form-check-input:checked {
    background-color: #dc3545;
    border-color: #dc3545;
}

/* Buttons */
.btn-delete {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    padding: 0.7rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    border: none;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.btn-delete:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
    color: white;
}

.btn-delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-cancel {
    background: white;
    color: #6c757d;
    padding: 0.7rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    border: 2px solid #6c757d;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-cancel:hover {
    background: #6c757d;
    color: white;
}

/* Loans Table */
.loans-table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1rem;
    border: 1px solid #e0e0e0;
}

.loans-table table {
    width: 100%;
    margin: 0;
    font-size: 0.9rem;
}

.loans-table thead {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.loans-table th {
    padding: 0.7rem;
    font-weight: 600;
    color: #495057;
    border: none;
    font-size: 0.9rem;
}

.loans-table td {
    padding: 0.7rem;
    border-bottom: 1px solid #f0f0f0;
}

.loans-table tbody tr:hover {
    background: #f8f9fa;
}

/* Error State */
.error-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    animation: fadeInUp 0.6s ease 0.3s both;
}

.error-card-header {
    background: linear-gradient(135deg, #dc3545, #c82333);
    padding: 1rem 1.2rem;
    color: white;
}

.error-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.1rem;
}

.error-alert {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 0.9rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

/* Final Warning */
.final-warning {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    border-radius: 10px;
    padding: 1.2rem;
    margin-top: 1rem;
    font-size: 0.9rem;
}

.final-warning h6 {
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.final-warning p {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.final-warning ul {
    margin-bottom: 0;
    padding-left: 1.5rem;
}

/* Breadcrumb */
.breadcrumb {
    background: white;
    padding: 0.8rem 1.2rem;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
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
                <li class="breadcrumb-item"><a href="index.php">Kelola Buku</a></li>
                <li class="breadcrumb-item active">Hapus Buku</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header-delete">
            <h1>
                <i class="fas fa-exclamation-triangle me-2"></i>
                Konfirmasi Hapus Buku
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

        <?php if ($peminjaman_info['sedang_dipinjam'] > 0): ?>
            <!-- Error: Book is borrowed -->
            <div class="error-card">
                <div class="error-card-header">
                    <h5><i class="fas fa-times-circle me-2"></i>Tidak Dapat Menghapus Buku</h5>
                </div>
                <div class="detail-card-body">
                    <div class="error-alert">
                        <strong><i class="fas fa-exclamation-circle me-2"></i>Buku ini sedang dipinjam oleh <?= $peminjaman_info['sedang_dipinjam'] ?> orang!</strong>
                        <br>
                        Buku tidak dapat dihapus sampai semua peminjaman dikembalikan.
                    </div>

                    <!-- Book Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="avatar-info">
                            <h4><?= htmlspecialchars($book['judul']) ?></h4>
                            <p style="margin-top: 0.3rem; font-size: 0.85rem; color: #6c757d;">
                                <i class="fas fa-barcode me-1"></i><?= htmlspecialchars($book['isbn']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Active Loans -->
                    <h6 class="mb-3"><i class="fas fa-list me-2"></i>Peminjam Aktif:</h6>
                    <div class="loans-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Peminjam</th>
                                    <th>No. HP</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Harus Kembali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($active_borrowers as $borrower): 
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($borrower['nama']) ?></strong></td>
                                        <td><?= htmlspecialchars($borrower['no_hp']) ?></td>
                                        <td><?= formatTanggal($borrower['tanggal_pinjam']) ?></td>
                                        <td><?= formatTanggal($borrower['tanggal_kembali']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a href="index.php" class="btn-cancel">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Confirmation Form -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h5><i class="fas fa-book me-2"></i>Detail Buku yang Akan Dihapus</h5>
                </div>
                <div class="detail-card-body">
                    <!-- Avatar and Basic Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="avatar-info">
                            <h4><?= htmlspecialchars($book['judul']) ?></h4>
                            <span class="role-badge-delete bg-info">BUKU</span>
                            <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #6c757d;">
                                <i class="fas fa-barcode me-1"></i><?= htmlspecialchars($book['isbn']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Detailed Info -->
                    <table class="info-table">
                        <tr>
                            <th><i class="fas fa-barcode me-2"></i>ISBN</th>
                            <td><code class="info-value"><?= htmlspecialchars($book['isbn']) ?></code></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-book me-2"></i>Judul</th>
                            <td><span class="info-value"><?= htmlspecialchars($book['judul']) ?></span></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-pen-fancy me-2"></i>Pengarang</th>
                            <td><?= htmlspecialchars($book['pengarang']) ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-building me-2"></i>Penerbit</th>
                            <td>
                                <?php 
                                if ($book['id_penerbit']) {
                                    $stmt = $conn->prepare("SELECT nama_penerbit FROM penerbit WHERE id_penerbit = ?");
                                    $stmt->execute([$book['id_penerbit']]);
                                    $penerbit = $stmt->fetchColumn();
                                    echo htmlspecialchars($penerbit ?: '-');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar me-2"></i>Tahun Terbit</th>
                            <td><?= $book['tahun_terbit'] ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-cubes me-2"></i>Stok Total</th>
                            <td>
                                <span class="badge bg-primary" style="font-size: 1rem;">
                                    <?= $book['stok_total'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-box-open me-2"></i>Stok Tersedia</th>
                            <td>
                                <span class="badge bg-<?= $book['stok_tersedia'] > 0 ? 'success' : 'secondary' ?>" style="font-size: 1rem;">
                                    <?= $book['stok_tersedia'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-check-circle me-2"></i>Status</th>
                            <td>
                                <span class="badge bg-<?= $book['status'] == 'tersedia' ? 'success' : 'secondary' ?>" style="font-size: 1rem;">
                                    <?= strtoupper($book['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>

                    <!-- Statistics -->
                    <?php if ($peminjaman_info['total_dipinjam'] > 0): ?>
                        <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 1rem 1.5rem; border-radius: 10px; margin-top: 1.5rem; margin-bottom: 1.5rem;">
                            <i class="fas fa-info-circle me-2" style="color: #0c5460;"></i>
                            <strong style="color: #0c5460;">Riwayat Peminjaman:</strong>
                            <p style="color: #0c5460; margin: 0.5rem 0 0 0; margin-bottom: 0;">
                                Buku ini pernah dipinjam <strong><?= $peminjaman_info['total_dipinjam'] ?></strong> kali.
                                <br>
                                <small>Semua riwayat akan ikut terhapus.</small>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Confirmation Checkbox -->
                    <div class="confirmation-box">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmCheckbox" style="width: 20px; height: 20px;">
                            <label class="form-check-label ms-2" for="confirmCheckbox" style="font-weight: 600; font-size: 1.05rem;">
                                Saya memahami bahwa data yang dihapus tidak dapat dikembalikan
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="isbn" value="<?= htmlspecialchars($book['isbn']) ?>">
                        
                        <div class="d-flex gap-3 justify-content-between">
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="confirm_delete" class="btn-delete" id="deleteButton" disabled>
                                <i class="fas fa-trash me-2"></i>Hapus Buku Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Final Warning -->
            <div class="final-warning">
                <h6><i class="fas fa-skull-crossbones me-2"></i>Peringatan Terakhir!</h6>
                <p class="mb-2">Dengan menghapus buku ini, Anda akan kehilangan:</p>
                <ul>
                    <li>Data buku secara permanen</li>
                    <li>Semua riwayat peminjaman (<?= $peminjaman_info['total_dipinjam'] ?> record)</li>
                    <li>Statistik dan laporan terkait buku ini</li>
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
    const judul = '<?= htmlspecialchars($book['judul'], ENT_QUOTES) ?>';
    
    if (!confirm(`Apakah Anda BENAR-BENAR YAKIN ingin menghapus buku "${judul}"?\n\nTindakan ini TIDAK DAPAT DIBATALKAN!`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>