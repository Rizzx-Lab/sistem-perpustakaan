<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Detail Booking';
include '../../../config/database.php';

// Get booking ID
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    setFlashMessage('Booking ID tidak valid', 'error');
    redirect('index.php');
}

try {
    // Get booking details
    $stmt = $conn->prepare("
        SELECT b.*, 
        a.nama, a.alamat, a.no_hp,
        bk.judul, bk.pengarang, bk.tahun_terbit, bk.stok_tersedia,
        p.nama_penerbit,
        r.kode_rak, r.lokasi as lokasi_rak,
        DATEDIFF(b.expired_at, CURDATE()) as days_until_expire
        FROM booking b
        JOIN anggota a ON b.nik = a.nik
        JOIN buku bk ON b.isbn = bk.isbn
        LEFT JOIN penerbit p ON bk.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON bk.id_rak = r.id_rak
        WHERE b.id_booking = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        setFlashMessage('Booking tidak ditemukan', 'error');
        redirect('index.php');
    }

    // Get book categories
    $stmt = $conn->prepare("
        SELECT k.nama_kategori 
        FROM kategori k
        JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
        WHERE bk.isbn = ?
    ");
    $stmt->execute([$booking['isbn']]);
    $kategori_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect('index.php');
}

// Handle Approve
if (isset($_POST['approve_booking'])) {
    try {
        $conn->beginTransaction();
        
        // Check if book is available
        if ($booking['stok_tersedia'] <= 0) {
            throw new Exception('Buku tidak tersedia saat ini');
        }
        
        // Calculate return date (max_pinjam_hari from settings)
        $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $max_days = (int)($stmt->fetchColumn() ?: 14);
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
            'APPROVE_BOOKING',
            "Booking #{$booking_id} disetujui -> Peminjaman #{$id_peminjaman}. Buku: '{$booking['judul']}' oleh {$booking['nama']}",
            'booking',
            $booking_id
        );
        
        setFlashMessage("Booking berhasil disetujui! ID Peminjaman: #{$id_peminjaman}", 'success');
        redirect('detail.php?id=' . $booking_id);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect('detail.php?id=' . $booking_id);
    }
}

// Handle Cancel
if (isset($_POST['cancel_booking'])) {
    try {
        $stmt = $conn->prepare("UPDATE booking SET status = 'dibatalkan', expired_at = NULL WHERE id_booking = ?");
        $stmt->execute([$booking_id]);
        
        logActivity(
            'CANCEL_BOOKING',
            "Booking #{$booking_id} dibatalkan oleh admin | Buku: '{$booking['judul']}' | Anggota: {$booking['nama']}",
            'booking',
            $booking_id
        );
        
        setFlashMessage("Booking #{$booking_id} berhasil dibatalkan", 'success');
        redirect('detail.php?id=' . $booking_id);
        
    } catch (Exception $e) {
        setFlashMessage("Gagal membatalkan booking: " . $e->getMessage(), 'error');
        redirect('detail.php?id=' . $booking_id);
    }
}

include '../../../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="../transaksi/peminjaman.php">Transaksi</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php">Booking Buku</a>
                    </li>
                    <li class="breadcrumb-item active">Detail Booking</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📋</span>
                <span class="title-gradient">Detail Booking #<?= $booking['id_booking'] ?></span>
            </h1>
            <p class="text-muted mb-0">Detail informasi booking buku</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-modern btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                <div><?= htmlspecialchars($flash['message']) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Booking Status Alert -->
    <?php
    $is_expired = ($booking['status'] == 'menunggu' && $booking['days_until_expire'] < 0);
    $status_class = '';
    $status_icon = '';
    $status_message = '';
    
    switch($booking['status']) {
        case 'menunggu':
            $status_class = $is_expired ? 'danger' : 'warning';
            $status_icon = $is_expired ? 'fa-hourglass-end' : 'fa-clock';
            $status_message = $is_expired ? 
                'Booking telah EXPIRED (Kadaluarsa ' . abs($booking['days_until_expire']) . ' hari yang lalu)' :
                'Booking dalam status MENUNGGU (Expires in ' . $booking['days_until_expire'] . ' hari)';
            break;
        case 'dipinjam':
            $status_class = 'success';
            $status_icon = 'fa-check-circle';
            $status_message = 'Booking telah diproses menjadi peminjaman';
            break;
        case 'dibatalkan':
            $status_class = 'secondary';
            $status_icon = 'fa-times-circle';
            $status_message = 'Booking telah dibatalkan';
            break;
    }
    ?>
    <div class="alert alert-<?= $status_class ?> border-0 shadow-sm mb-4">
        <div class="d-flex align-items-center">
            <i class="fas <?= $status_icon ?> fa-2x me-3"></i>
            <div>
                <h5 class="mb-1">Status: <?= strtoupper($booking['status']) ?></h5>
                <p class="mb-0"><?= $status_message ?></p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Booking Info -->
        <div class="col-md-6">
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Informasi Booking
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%"><strong>ID Booking</strong></td>
                            <td>: #<?= $booking['id_booking'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Booking</strong></td>
                            <td>: <?= formatTanggal($booking['tanggal_booking']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Expired</strong></td>
                            <td class="<?= $is_expired ? 'text-danger fw-bold' : '' ?>">
                                : <?= formatTanggal($booking['expired_at']) ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Waktu Dibuat</strong></td>
                            <td>: <?= date('d/m/Y H:i:s', strtotime($booking['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>
                                : <span class="badge bg-<?= $status_class ?>">
                                    <?= strtoupper($booking['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Anggota Info -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-user me-2"></i>Informasi Anggota
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%"><strong>Nama</strong></td>
                            <td>: <?= htmlspecialchars($booking['nama']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>NIK</strong></td>
                            <td>: <?= htmlspecialchars($booking['nik']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>No. HP</strong></td>
                            <td>: <?= $booking['no_hp'] ? htmlspecialchars($booking['no_hp']) : '-' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Alamat</strong></td>
                            <td>: <?= htmlspecialchars($booking['alamat']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Book Info -->
        <div class="col-md-6">
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-book me-2"></i>Informasi Buku
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%"><strong>Judul</strong></td>
                            <td>: <?= htmlspecialchars($booking['judul']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>ISBN</strong></td>
                            <td>: <code><?= htmlspecialchars($booking['isbn']) ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Pengarang</strong></td>
                            <td>: <?= htmlspecialchars($booking['pengarang']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Penerbit</strong></td>
                            <td>: <?= $booking['nama_penerbit'] ? htmlspecialchars($booking['nama_penerbit']) : '-' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tahun Terbit</strong></td>
                            <td>: <?= $booking['tahun_terbit'] ?></td>
                        </tr>
                        <tr>
                            <td><strong>Kategori</strong></td>
                            <td>: <?= $kategori_list ? implode(', ', $kategori_list) : '-' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Rak</strong></td>
                            <td>: <?= $booking['kode_rak'] ? htmlspecialchars($booking['kode_rak']) : '-' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Lokasi Rak</strong></td>
                            <td>: <?= $booking['lokasi_rak'] ? htmlspecialchars($booking['lokasi_rak']) : '-' ?></td>
                        </tr>
                        <tr>
                            <td><strong>Stok Tersedia</strong></td>
                            <td>
                                : <span class="badge bg-<?= $booking['stok_tersedia'] > 0 ? 'success' : 'danger' ?>">
                                    <?= $booking['stok_tersedia'] ?> buku
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
<?php include '../../../includes/footer.php'; ?>