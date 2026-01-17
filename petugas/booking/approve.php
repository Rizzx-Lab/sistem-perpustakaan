<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require petugas role
requireRole(['petugas', 'admin']);

$page_title = 'Approve Booking';
include '../../config/database.php';

// Get booking ID
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    setFlashMessage('Booking ID tidak valid', 'error');
    redirect('index.php');
}

// Check if booking exists and is pending
try {
    $stmt = $conn->prepare("
        SELECT b.*, a.nama, bk.judul, bk.stok_tersedia
        FROM booking b
        JOIN anggota a ON b.nik = a.nik
        JOIN buku bk ON b.isbn = bk.isbn
        WHERE b.id_booking = ? AND b.status = 'menunggu'
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlashMessage('Booking tidak ditemukan atau sudah diproses', 'error');
        redirect('index.php');
    }

    if ($booking['stok_tersedia'] <= 0) {
        setFlashMessage('Tidak dapat approve: Buku tidak tersedia', 'error');
        redirect('index.php');
    }

    // Check if booking is expired
    if ($booking['expired_at'] < date('Y-m-d')) {
        setFlashMessage('Tidak dapat approve: Booking sudah expired', 'error');
        redirect('index.php');
    }

} catch (Exception $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('index.php');
}

// Get max pinjam hari
$stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
$max_days = (int)($stmt->fetchColumn() ?: 14);

// Process approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Calculate return date
        $tanggal_pinjam = date('Y-m-d');
        $tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . " +{$max_days} days"));
        
        // Create peminjaman
        $stmt = $conn->prepare("
            INSERT INTO peminjaman (nik, isbn, tanggal_pinjam, tanggal_kembali, status, created_at) 
            VALUES (?, ?, ?, ?, 'dipinjam', NOW())
        ");
        $stmt->execute([
            $booking['nik'],
            $booking['isbn'],
            $tanggal_pinjam,
            $tanggal_kembali
        ]);
        $id_peminjaman = $conn->lastInsertId();
        
        // Update buku stok
        $stmt = $conn->prepare("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE isbn = ?");
        $stmt->execute([$booking['isbn']]);
        
        // Update booking status
        $stmt = $conn->prepare("UPDATE booking SET status = 'dipinjam', expired_at = NULL WHERE id_booking = ?");
        $stmt->execute([$booking_id]);
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'APPROVE_BOOKING_PETUGAS',
            "Petugas approve booking #{$booking_id} -> Peminjaman #{$id_peminjaman}. Buku: '{$booking['judul']}' oleh {$booking['nama']}",
            'booking',
            $booking_id
        );
        
        setFlashMessage("Booking berhasil disetujui! ID Peminjaman: #{$id_peminjaman}", 'success');
        redirect('index.php');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect('index.php');
    }
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern text-center">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-check-circle me-2"></i>Approve Booking
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                        <h5>Approve Booking #<?= $booking_id ?>?</h5>
                        <p class="text-muted">
                            Booking akan dikonversi menjadi peminjaman reguler
                        </p>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Detail Booking</h6>
                        <ul class="mb-0">
                            <li><strong>Anggota:</strong> <?= htmlspecialchars($booking['nama']) ?></li>
                            <li><strong>Buku:</strong> <?= htmlspecialchars($booking['judul']) ?></li>
                            <li><strong>Stok Tersedia:</strong> <?= $booking['stok_tersedia'] ?> buku</li>
                            <li><strong>Durasi Pinjam:</strong> <?= $max_days ?> hari</li>
                            <li><strong>Booking dibuat:</strong> <?= formatTanggal($booking['tanggal_booking']) ?></li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Setelah di-approve:</h6>
                        <ul class="mb-0 small">
                            <li>Booking akan berubah status menjadi "Dipinjam"</li>
                            <li>Stok buku akan dikurangi 1</li>
                            <li>Peminjaman akan tercatat dengan durasi <?= $max_days ?> hari</li>
                            <li>Anggota akan mendapat notifikasi (jika email terkonfigurasi)</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success-modern">
                                <i class="fas fa-check me-2"></i>Ya, Approve Booking
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>