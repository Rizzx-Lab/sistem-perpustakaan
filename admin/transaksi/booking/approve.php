<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Approve Booking';
include '../../../config/database.php';

// Get booking ID
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    setFlashMessage('Booking ID tidak valid', 'error');
    redirect('index.php');
}

// Check if booking exists and is pending
try {
    $stmt = $conn->prepare("
        SELECT b.*, a.nama, bk.judul, bk.stok_tersedia, a.user_id, u.email
        FROM booking b
        JOIN anggota a ON b.nik = a.nik
        LEFT JOIN users u ON a.user_id = u.id
        JOIN buku bk ON b.isbn = bk.isbn
        WHERE b.id_booking = ? AND b.status = 'menunggu'
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        // Cek apakah booking sudah dipinjam tapi belum ada peminjaman
        $stmt = $conn->prepare("
            SELECT b.*, a.nama, bk.judul, bk.stok_tersedia 
            FROM booking b
            JOIN anggota a ON b.nik = a.nik
            JOIN buku bk ON b.isbn = bk.isbn
            WHERE b.id_booking = ? AND b.status = 'dipinjam'
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if ($booking) {
            setFlashMessage('Booking sudah berstatus DIPINJAM. Cek apakah sudah ada peminjaman terkait.', 'warning');
            redirect('detail.php?id=' . $booking_id);
        } else {
            setFlashMessage('Booking tidak ditemukan', 'error');
            redirect('index.php');
        }
    }

    if ($booking['stok_tersedia'] <= 0) {
        setFlashMessage('Tidak dapat approve: Buku tidak tersedia', 'error');
        redirect('detail.php?id=' . $booking_id);
    }

} catch (Exception $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('index.php');
}

// Process approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Calculate return date
        $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $max_days = (int)($stmt->fetchColumn() ?: 14);
        $tanggal_pinjam = date('Y-m-d');
        $tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . " +{$max_days} days"));
        
        // PERBAIKAN: Cek dulu apakah sudah ada peminjaman untuk booking ini
        $stmt = $conn->prepare("
            SELECT id_peminjaman 
            FROM peminjaman 
            WHERE nik = ? AND isbn = ? AND status = 'dipinjam'
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$booking['nik'], $booking['isbn']]);
        $existing_peminjaman = $stmt->fetch();
        
        if ($existing_peminjaman) {
            throw new Exception('Sudah ada peminjaman aktif untuk buku ini oleh anggota yang sama.');
        }
        
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
            'APPROVE_BOOKING',
            "Booking #{$booking_id} disetujui -> Peminjaman #{$id_peminjaman}. Buku: '{$booking['judul']}' oleh {$booking['nama']}",
            'booking',
            $booking_id
        );
        
        // Jika ada email anggota, kirim notifikasi
        if (!empty($booking['email'])) {
            // Anda bisa menambahkan fungsi kirim email di sini
            logActivity(
                'EMAIL_APPROVAL',
                "Notifikasi approval booking dikirim ke {$booking['email']}",
                'booking',
                $booking_id
            );
        }
        
        setFlashMessage("Booking berhasil disetujui! ID Peminjaman: #{$id_peminjaman}", 'success');
        redirect('index.php');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect('detail.php?id=' . $booking_id);
    }
}

include '../../../includes/header.php';
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
                            <li><strong>Durasi Pinjam:</strong> <?= $max_days ?? '14' ?> hari</li>
                            <li><strong>Tanggal Pinjam:</strong> <?= date('d/m/Y') ?></li>
                            <li><strong>Batas Kembali:</strong> <?= date('d/m/Y', strtotime('+' . ($max_days ?? 14) . ' days')) ?></li>
                        </ul>
                    </div>
                    
                    <?php 
                    // PERBAIKAN: Tampilkan warning jika booking sudah expired
                    if (!empty($booking['expired_at']) && strtotime($booking['expired_at']) < time()): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Booking ini sudah EXPIRED sejak 
                            <?= formatTanggal($booking['expired_at']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success-modern">
                                <i class="fas fa-check me-2"></i>Ya, Approve Booking
                            </button>
                            <a href="detail.php?id=<?= $booking_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const approveBtn = document.querySelector('button[type="submit"]');
    approveBtn.addEventListener('click', function(e) {
        const bookingId = <?= $booking_id ?>;
        const anggotaNama = "<?= addslashes($booking['nama']) ?>";
        const judulBuku = "<?= addslashes($booking['judul']) ?>";
        
        const confirmation = confirm(
            `Konfirmasi Approve Booking\n\n` +
            `ID Booking: #${bookingId}\n` +
            `Anggota: ${anggotaNama}\n` +
            `Buku: ${judulBuku}\n\n` +
            `Apakah Anda yakin ingin mengkonversi booking ini menjadi peminjaman?`
        );
        
        if (!confirmation) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>