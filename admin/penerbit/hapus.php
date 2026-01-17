<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Hapus Penerbit';
include '../../config/database.php';

// Handle konfirmasi hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Check if penerbit exists
        $stmt = $conn->prepare("SELECT * FROM penerbit WHERE id_penerbit = ?");
        $stmt->execute([$id]);
        $penerbit = $stmt->fetch();
        
        if (!$penerbit) {
            throw new Exception('Penerbit tidak ditemukan!');
        }
        
        // Check if penerbit has books
        $stmt = $conn->prepare("SELECT COUNT(*) FROM buku WHERE id_penerbit = ?");
        $stmt->execute([$id]);
        $book_count = $stmt->fetchColumn();
        
        if ($book_count > 0) {
            throw new Exception("Tidak dapat menghapus penerbit yang memiliki {$book_count} buku!");
        }
        
        // Delete penerbit
        $stmt = $conn->prepare("DELETE FROM penerbit WHERE id_penerbit = ?");
        $stmt->execute([$id]);
        
        // Log activity
        logActivity('HAPUS_PENERBIT', "Penerbit dihapus: {$penerbit['nama_penerbit']} (ID: {$id})", 'penerbit', $id);
        
        redirect('index.php', "Penerbit '{$penerbit['nama_penerbit']}' berhasil dihapus!", 'success');
        
    } catch (Exception $e) {
        redirect('index.php', $e->getMessage(), 'error');
    }
}

// Get ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('index.php', 'ID penerbit tidak valid!', 'error');
}

$id = (int)$_GET['id'];

try {
    // Get penerbit data
    $stmt = $conn->prepare("SELECT * FROM penerbit WHERE id_penerbit = ?");
    $stmt->execute([$id]);
    $penerbit = $stmt->fetch();
    
    if (!$penerbit) {
        redirect('index.php', 'Penerbit tidak ditemukan!', 'error');
    }
    
    // Check if penerbit has books
    $stmt = $conn->prepare("SELECT COUNT(*) as jumlah_buku FROM buku WHERE id_penerbit = ?");
    $stmt->execute([$id]);
    $book_info = $stmt->fetch();
    
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
    max-width: 800px;
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
    background: #28a745;
    color: white;
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
    display: inline-block;
}

.btn-cancel:hover {
    background: #6c757d;
    color: white;
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
                <li class="breadcrumb-item"><a href="index.php">Kelola Penerbit</a></li>
                <li class="breadcrumb-item active">Hapus Penerbit</li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="page-header-delete">
            <h1>
                <i class="fas fa-exclamation-triangle me-2"></i>
                Konfirmasi Hapus Penerbit
            </h1>
            <p>Periksa informasi dengan teliti sebelum menghapus data</p>
        </div>

        <?php if ($book_info['jumlah_buku'] > 0): ?>
            <!-- Error: Penerbit has books -->
            <div class="error-card">
                <div class="error-card-header">
                    <h5><i class="fas fa-times-circle me-2"></i>Tidak Dapat Menghapus Penerbit</h5>
                </div>
                <div class="detail-card-body">
                    <div class="error-alert">
                        <strong><i class="fas fa-exclamation-circle me-2"></i>Penerbit ini memiliki <?= $book_info['jumlah_buku'] ?> buku!</strong>
                        <br>
                        Penerbit tidak dapat dihapus sampai semua buku dipindahkan ke penerbit lain.
                    </div>

                    <!-- Penerbit Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="avatar-info">
                            <h4><?= htmlspecialchars($penerbit['nama_penerbit']) ?></h4>
                            <p style="margin-top: 0.3rem; font-size: 0.85rem; color: #6c757d;">
                                <i class="fas fa-hashtag me-1"></i>ID: <?= $id ?>
                            </p>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Solusi:</strong>
                        <ul class="mt-2 mb-0">
                            <li>Pindahkan semua buku ke penerbit lain terlebih dahulu</li>
                            <li>Atau ubah buku-buku tersebut menjadi "Tanpa Penerbit"</li>
                        </ul>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a href="index.php" class="btn-cancel">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                        <a href="edit.php?id=<?= $id ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Penerbit
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
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

            <!-- Confirmation Form -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h5><i class="fas fa-building me-2"></i>Detail Penerbit yang Akan Dihapus</h5>
                </div>
                <div class="detail-card-body">
                    <!-- Avatar and Basic Info -->
                    <div class="avatar-section">
                        <div class="avatar-circle-delete">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="avatar-info">
                            <h4><?= htmlspecialchars($penerbit['nama_penerbit']) ?></h4>
                            <span class="role-badge-delete">PENERBIT</span>
                            <p style="margin-top: 0.5rem; font-size: 0.85rem; color: #6c757d;">
                                <i class="fas fa-hashtag me-1"></i>ID: <?= $id ?>
                            </p>
                        </div>
                    </div>

                    <!-- Detailed Info -->
                    <table class="info-table">
                        <tr>
                            <th><i class="fas fa-hashtag me-2"></i>ID</th>
                            <td><code class="info-value"><?= $id ?></code></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-building me-2"></i>Nama</th>
                            <td><span class="info-value"><?= htmlspecialchars($penerbit['nama_penerbit']) ?></span></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-map-marker-alt me-2"></i>Alamat</th>
                            <td><?= $penerbit['alamat'] ? htmlspecialchars($penerbit['alamat']) : '<em class="text-muted">-</em>' ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-phone me-2"></i>Telepon</th>
                            <td><?= $penerbit['telepon'] ? htmlspecialchars($penerbit['telepon']) : '<em class="text-muted">-</em>' ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-envelope me-2"></i>Email</th>
                            <td><?= $penerbit['email'] ? htmlspecialchars($penerbit['email']) : '<em class="text-muted">-</em>' ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar me-2"></i>Ditambahkan</th>
                            <td><?= formatTanggal($penerbit['created_at']) ?></td>
                        </tr>
                    </table>

                    <!-- Statistics -->
                    <div style="background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 1rem 1.5rem; border-radius: 10px; margin-top: 1.5rem; margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle me-2" style="color: #0c5460;"></i>
                        <strong style="color: #0c5460;">Status Penerbit:</strong>
                        <p style="color: #0c5460; margin: 0.5rem 0 0 0; margin-bottom: 0;">
                            Penerbit ini <strong>tidak memiliki buku</strong> dan dapat dihapus dengan aman.
                        </p>
                    </div>

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
                        <input type="hidden" name="id" value="<?= $id ?>">
                        
                        <div class="d-flex gap-3 justify-content-between">
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="confirm_delete" class="btn-delete" id="deleteButton" disabled>
                                <i class="fas fa-trash me-2"></i>Hapus Penerbit Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Final Warning -->
            <div class="final-warning">
                <h6><i class="fas fa-skull-crossbones me-2"></i>Peringatan Terakhir!</h6>
                <p class="mb-2">Dengan menghapus penerbit ini, Anda akan kehilangan:</p>
                <ul>
                    <li>Data penerbit secara permanen</li>
                    <li>Informasi kontak dan alamat</li>
                    <li>Akses untuk memilih penerbit ini di buku baru</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Enable delete button only when checkbox is checked
document.getElementById('confirmCheckbox')?.addEventListener('change', function() {
    const deleteButton = document.getElementById('deleteButton');
    if (deleteButton) {
        deleteButton.disabled = !this.checked;
    }
});

// Confirm before submit
document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
    const namaPenerbit = '<?= htmlspecialchars($penerbit['nama_penerbit'], ENT_QUOTES) ?>';
    
    if (!confirm(`Apakah Anda BENAR-BENAR YAKIN ingin menghapus penerbit "${namaPenerbit}"?\n\nTindakan ini TIDAK DAPAT DIBATALKAN!`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>