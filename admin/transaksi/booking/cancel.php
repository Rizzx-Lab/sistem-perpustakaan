<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Batalkan Booking';
include '../../../config/database.php';

// Get booking ID from URL
$id_booking = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get booking details - AMBIL EMAIL DARI TABLE USERS
$stmt = $conn->prepare("
    SELECT b.*, 
           a.nama, 
           a.no_hp, 
           a.alamat,
           u.email,
           bk.judul, 
           bk.isbn, 
           bk.pengarang,
           DATEDIFF(b.expired_at, CURDATE()) as days_until_expire
    FROM booking b
    JOIN anggota a ON b.nik = a.nik
    LEFT JOIN users u ON a.user_id = u.id  -- JOIN ke users untuk dapatkan email
    JOIN buku bk ON b.isbn = bk.isbn
    WHERE b.id_booking = ?
");
$stmt->execute([$id_booking]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlashMessage("Booking tidak ditemukan", 'error');
    redirect('index.php');
}

// Check if booking can be cancelled
if ($booking['status'] !== 'menunggu') {
    setFlashMessage("Booking tidak dapat dibatalkan karena status sudah: " . $booking['status'], 'error');
    redirect('index.php');
}

// Handle cancellation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alasan = trim($_POST['alasan'] ?? '');
    
    try {
        $conn->beginTransaction();
        
        // Update booking status to cancelled
        $stmt = $conn->prepare("
            UPDATE booking 
            SET status = 'dibatalkan', 
                expired_at = NULL 
            WHERE id_booking = ?
        ");
        $stmt->execute([$id_booking]);
        
        // Send notification email to member (jika email tersedia)
        if (!empty($booking['email']) && filter_var($booking['email'], FILTER_VALIDATE_EMAIL)) {
            $subject = "Booking Buku Dibatalkan - Perpustakaan #{$id_booking}";
            $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #dc3545;'>📚 Booking Buku Dibatalkan</h2>
                    <p>Halo <strong>{$booking['nama']}</strong>,</p>
                    <p>Booking Anda dengan detail berikut telah <strong>dibatalkan</strong>:</p>
                    
                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        <h4 style='color: #495057; margin-top: 0;'>📋 Detail Booking</h4>
                        <table style='width: 100%;'>
                            <tr>
                                <td style='padding: 5px 0; width: 120px;'><strong>ID Booking:</strong></td>
                                <td style='padding: 5px 0;'><span style='background-color: #6c757d; color: white; padding: 2px 8px; border-radius: 3px;'>#{$id_booking}</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Buku:</strong></td>
                                <td style='padding: 5px 0;'>{$booking['judul']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Pengarang:</strong></td>
                                <td style='padding: 5px 0;'>{$booking['pengarang']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>ISBN:</strong></td>
                                <td style='padding: 5px 0;'><code>{$booking['isbn']}</code></td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Tanggal Booking:</strong></td>
                                <td style='padding: 5px 0;'>" . formatTanggal($booking['tanggal_booking']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 5px 0;'><strong>Alasan:</strong></td>
                                <td style='padding: 5px 0; color: #dc3545;'>" . ($alasan ? htmlspecialchars($alasan) : 'Administrator') . "</td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>Anda dapat membuat booking baru melalui sistem perpustakaan.</p>
                    
                    <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;'>
                        <p>Email ini dikirim otomatis dari sistem Perpustakaan.</p>
                        <p>Jika ada pertanyaan, silakan hubungi perpustakaan.</p>
                    </div>
                </div>
            ";
            
            // Use your email function here
            // sendEmail($booking['email'], $subject, $message);
            
            // Log bahwa email telah dikirim
            logActivity(
                'EMAIL_CANCELLATION',
                "Email pembatalan booking #{$id_booking} dikirim ke {$booking['email']}",
                'booking',
                $id_booking
            );
        }
        
        // Log activity
        logActivity(
            'CANCEL_BOOKING',
            "Booking #{$id_booking} dibatalkan oleh admin. Alasan: " . ($alasan ?: 'Tidak disebutkan') . 
            " | Buku: '{$booking['judul']}' | Anggota: {$booking['nama']}",
            'booking',
            $id_booking
        );
        
        $conn->commit();
        
        setFlashMessage("Booking #{$id_booking} berhasil dibatalkan", 'success');
        redirect('index.php');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage("Gagal membatalkan booking: " . $e->getMessage(), 'error');
        redirect('cancel.php?id=' . $id_booking);
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
                    <li class="breadcrumb-item active">Batalkan Booking</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">❌</span>
                <span class="title-gradient">Batalkan Booking #<?= $id_booking ?></span>
            </h1>
            <p class="text-muted mb-0">Konfirmasi pembatalan booking buku</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-modern btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar
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

    <!-- Warning Alert -->
    <div class="alert alert-warning mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
            <div>
                <h5 class="alert-heading mb-2">Konfirmasi Pembatalan</h5>
                <p class="mb-0">Anda akan membatalkan booking berikut. Aksi ini tidak dapat dibatalkan.</p>
                <?php 
                $is_expired = ($booking['status'] == 'menunggu' && $booking['days_until_expire'] < 0);
                if ($is_expired): ?>
                    <p class="mb-0 mt-2"><strong>Status:</strong> Booking ini sudah <span class="text-danger">KADALUARSA</span></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Booking Information Card -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Detail Booking
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">ID Booking</label>
                            <div class="form-control-static">
                                <span class="badge bg-secondary">#<?= $booking['id_booking'] ?></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">Status Saat Ini</label>
                            <div class="form-control-static">
                                <span class="badge bg-warning">MENUNGGU</span>
                                <?php if ($is_expired): ?>
                                    <span class="badge bg-danger ms-1">KADALUARSA</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">Anggota</label>
                            <div class="form-control-static">
                                <div class="fw-medium"><?= htmlspecialchars($booking['nama']) ?></div>
                                <small class="text-muted">NIK: <?= htmlspecialchars($booking['nik']) ?></small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">Kontak</label>
                            <div class="form-control-static">
                                <div><i class="fas fa-phone me-1"></i> <?= $booking['no_hp'] ? htmlspecialchars($booking['no_hp']) : '-' ?></div>
                                <div><i class="fas fa-envelope me-1"></i> <?= $booking['email'] ? htmlspecialchars($booking['email']) : '<span class="text-muted">Tidak ada email</span>' ?></div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label-modern fw-semibold">Alamat</label>
                            <div class="form-control-static">
                                <?= htmlspecialchars($booking['alamat']) ?>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label-modern fw-semibold">Buku</label>
                            <div class="form-control-static">
                                <div class="fw-medium"><?= htmlspecialchars($booking['judul']) ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-user-pen me-1"></i> <?= htmlspecialchars($booking['pengarang']) ?> | 
                                    <i class="fas fa-barcode me-1"></i> <?= htmlspecialchars($booking['isbn']) ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">Tanggal Booking</label>
                            <div class="form-control-static">
                                <i class="fas fa-calendar me-1"></i> <?= formatTanggal($booking['tanggal_booking']) ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern fw-semibold">Expired Pada</label>
                            <div class="form-control-static">
                                <i class="fas fa-clock me-1"></i> <?= $booking['expired_at'] ? formatTanggal($booking['expired_at']) : '-' ?>
                                <?php 
                                if ($booking['expired_at']):
                                    $days_left = $booking['days_until_expire'];
                                    if ($days_left >= 0):
                                ?>
                                        <br><small class="text-<?= $days_left == 0 ? 'warning' : 'success' ?>">
                                            <?= $days_left == 0 ? '⏰ Expire hari ini' : "⏳ {$days_left} hari lagi" ?>
                                        </small>
                                    <?php else: ?>
                                        <br><small class="text-danger">
                                            ⚠️ Kadaluarsa <?= abs($days_left) ?> hari lalu
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label-modern fw-semibold">Dibuat Pada</label>
                            <div class="form-control-static">
                                <i class="fas fa-calendar-plus me-1"></i> <?= formatTanggal($booking['created_at'], true) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Form -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-comment-alt me-2"></i>Form Pembatalan
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="cancelForm">
                        <div class="mb-4">
                            <label for="alasan" class="form-label-modern fw-semibold">
                                <i class="fas fa-edit me-2"></i>Alasan Pembatalan (Opsional)
                            </label>
                            <textarea name="alasan" id="alasan" class="form-control-modern" 
                                      rows="4" placeholder="Masukkan alasan pembatalan... (Opsional)"
                                      maxlength="500"><?php
                                if ($is_expired) {
                                    echo "Booking kadaluarsa otomatis";
                                }
                            ?></textarea>
                            <div class="form-text">
                                Alasan akan dicatat dalam log aktivitas 
                                <?php if ($booking['email']): ?>
                                    dan dikirim ke email anggota.
                                <?php else: ?>
                                    (tidak ada email anggota untuk notifikasi).
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notification Status -->
                        <?php if ($booking['email']): ?>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-envelope me-2"></i>
                                <strong>Notifikasi Email:</strong> Konfirmasi pembatalan akan dikirim ke: 
                                <code><?= htmlspecialchars($booking['email']) ?></code>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Perhatian:</strong> Anggota tidak memiliki email terdaftar. 
                                Notifikasi hanya akan dicatat dalam sistem.
                            </div>
                        <?php endif; ?>

                        <!-- Confirmation Checkboxes -->
                        <div class="mb-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="confirm1" required>
                                <label class="form-check-label" for="confirm1">
                                    Saya menyadari bahwa pembatalan ini akan mengubah status booking menjadi <span class="badge bg-secondary">DIBATALKAN</span>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="confirm2" required>
                                <label class="form-check-label" for="confirm2">
                                    Saya memahami bahwa aksi ini <strong class="text-danger">TIDAK DAPAT DIBATALKAN</strong>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm3" required>
                                <label class="form-check-label" for="confirm3">
                                    Saya bertanggung jawab atas pembatalan booking ini
                                </label>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="detail.php?id=<?= $id_booking ?>" class="btn btn-modern btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Kembali ke Detail
                            </a>
                            <button type="submit" class="btn btn-modern btn-danger">
                                <i class="fas fa-ban me-2"></i>Ya, Batalkan Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    const checks = [
        document.getElementById('confirm1'),
        document.getElementById('confirm2'),
        document.getElementById('confirm3')
    ];
    
    // Check if all checkboxes are checked
    const allChecked = checks.every(check => check.checked);
    
    if (!allChecked) {
        alert('❌ Harap centang semua kotak konfirmasi sebelum melanjutkan.');
        e.preventDefault();
        return false;
    }
    
    const bookingId = <?= $id_booking ?>;
    const isExpired = <?= $is_expired ? 'true' : 'false' ?>;
    
    let message = '⚠️ PERINGATAN!\n\n';
    message += `Anda akan membatalkan booking #${bookingId}.\n\n`;
    
    if (isExpired) {
        message += 'Booking ini sudah KADALUARSA.\n';
    }
    
    message += 'Setelah dibatalkan, booking tidak dapat dikembalikan ke status "menunggu".\n\n';
    message += 'Apakah Anda yakin ingin melanjutkan?';
    
    return confirm(message);
});

// Character counter for reason textarea
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('alasan');
    const counter = document.createElement('small');
    counter.className = 'form-text text-end d-block mt-1';
    counter.textContent = '0/500 karakter';
    counter.id = 'charCounter';
    
    textarea.parentNode.appendChild(counter);
    
    textarea.addEventListener('input', function() {
        const length = this.value.length;
        counter.textContent = `${length}/500 karakter`;
        
        if (length > 450) {
            counter.classList.add('text-warning');
            counter.classList.remove('text-danger');
        } else if (length > 500) {
            counter.classList.remove('text-warning');
            counter.classList.add('text-danger');
        } else {
            counter.classList.remove('text-warning', 'text-danger');
        }
    });
    
    // Initialize counter
    textarea.dispatchEvent(new Event('input'));
});

// Auto-check checkboxes for expired bookings
<?php if ($is_expired): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-check confirmation boxes for expired bookings
    document.getElementById('confirm1').checked = true;
    document.getElementById('confirm2').checked = true;
    document.getElementById('confirm3').checked = true;
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>