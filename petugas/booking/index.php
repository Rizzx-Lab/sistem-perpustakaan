<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require petugas role
requireRole(['petugas', 'admin']);

$page_title = 'Kelola Booking Buku';
include '../../config/database.php';

// ========== APPROVE BOOKING ==========
if (isset($_POST['approve_booking']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get booking details
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
            throw new Exception('Booking tidak ditemukan atau sudah diproses');
        }
        
        // Check if book is available
        if ($booking['stok_tersedia'] <= 0) {
            throw new Exception('Buku tidak tersedia saat ini');
        }
        
        // Check if booking is expired
        if ($booking['expired_at'] < date('Y-m-d')) {
            throw new Exception('Booking sudah expired');
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
            'APPROVE_BOOKING_PETUGAS',
            "Petugas approve booking #{$booking_id} -> Peminjaman #{$id_peminjaman}. Buku: '{$booking['judul']}' oleh {$booking['nama']}",
            'booking',
            $booking_id
        );
        
        setFlashMessage("Booking berhasil disetujui! ID Peminjaman: #{$id_peminjaman}", 'success');
        redirect($_SERVER['REQUEST_URI']);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
}

// ========== CANCEL BOOKING ==========
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $alasan = sanitizeInput($_POST['alasan'] ?? '');
    
    try {
        // Get booking info for log
        $stmt = $conn->prepare("
            SELECT b.*, a.nama, bk.judul 
            FROM booking b
            JOIN anggota a ON b.nik = a.nik
            JOIN buku bk ON b.isbn = bk.isbn
            WHERE b.id_booking = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            throw new Exception('Booking tidak ditemukan');
        }
        
        $stmt = $conn->prepare("UPDATE booking SET status = 'dibatalkan' WHERE id_booking = ?");
        $stmt->execute([$booking_id]);
        
        logActivity(
            'CANCEL_BOOKING_PETUGAS',
            "Petugas membatalkan booking #{$booking_id}. Buku: '{$booking['judul']}' oleh {$booking['nama']}. Alasan: " . ($alasan ?: 'Tidak disebutkan'),
            'booking',
            $booking_id
        );
        
        setFlashMessage("Booking berhasil dibatalkan", 'success');
        redirect($_SERVER['REQUEST_URI']);
        
    } catch (Exception $e) {
        setFlashMessage($e->getMessage(), 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
}

// ========== FILTER & SEARCH ==========
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($_GET['nik'])) {
    $whereClause .= " AND b.nik LIKE ?";
    $params[] = '%' . $_GET['nik'] . '%';
}

if (!empty($_GET['isbn'])) {
    $whereClause .= " AND b.isbn LIKE ?";
    $params[] = '%' . $_GET['isbn'] . '%';
}

if (!empty($_GET['status'])) {
    $whereClause .= " AND b.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['nama'])) {
    $whereClause .= " AND a.nama LIKE ?";
    $params[] = '%' . $_GET['nama'] . '%';
}

// Status expired (auto)
if (isset($_GET['show_expired']) && $_GET['show_expired'] == '1') {
    $whereClause .= " AND b.expired_at < CURDATE() AND b.status = 'menunggu'";
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "
    SELECT COUNT(*) 
    FROM booking b
    JOIN anggota a ON b.nik = a.nik
    JOIN buku bk ON b.isbn = bk.isbn
    $whereClause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get booking data
$query = "
    SELECT b.*, 
           a.nama, 
           bk.judul,
           bk.stok_tersedia,
           DATEDIFF(b.expired_at, CURDATE()) as days_until_expire
    FROM booking b
    JOIN anggota a ON b.nik = a.nik
    JOIN buku bk ON b.isbn = bk.isbn
    $whereClause
    ORDER BY 
        CASE 
            WHEN b.status = 'menunggu' AND b.expired_at >= CURDATE() THEN 1
            WHEN b.status = 'menunggu' AND b.expired_at < CURDATE() THEN 2
            WHEN b.status = 'dipinjam' THEN 3
            ELSE 4
        END,
        b.tanggal_booking DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM booking")->fetchColumn(),
    'menunggu' => $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'menunggu'")->fetchColumn(),
    'dipinjam' => $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'dipinjam'")->fetchColumn(),
    'dibatalkan' => $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'dibatalkan'")->fetchColumn(),
    'expired' => $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'menunggu' AND expired_at < CURDATE()")->fetchColumn(),
    'expiring_today' => $conn->query("SELECT COUNT(*) FROM booking WHERE status = 'menunggu' AND expired_at = CURDATE()")->fetchColumn()
];

include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Booking Buku</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📅</span>
                <span class="title-gradient">Kelola Booking Buku</span>
            </h1>
            <p class="text-muted mb-0">Kelola antrian pemesanan buku</p>
        </div>
        <div>
            <a href="?show_expired=1" class="btn btn-modern btn-warning">
                <i class="fas fa-clock me-2"></i>Tampilkan Expired
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

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-list text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total'] ?></div>
                <small class="stat-label">Total Booking</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['menunggu'] ?></div>
                <small class="stat-label">Menunggu</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['dipinjam'] ?></div>
                <small class="stat-label">Dipinjam</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-times-circle text-danger"></i>
                </div>
                <div class="stat-number text-danger"><?= $stats['dibatalkan'] ?></div>
                <small class="stat-label">Dibatalkan</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-hourglass-end text-secondary"></i>
                </div>
                <div class="stat-number text-secondary"><?= $stats['expired'] ?></div>
                <small class="stat-label">Expired</small>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['expiring_today'] ?></div>
                <small class="stat-label">Expire Hari Ini</small>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">NIK Anggota</label>
                    <input type="text" name="nik" class="form-control-modern" 
                           placeholder="Cari NIK..." 
                           value="<?= htmlspecialchars($_GET['nik'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Nama Anggota</label>
                    <input type="text" name="nama" class="form-control-modern" 
                           placeholder="Cari nama..." 
                           value="<?= htmlspecialchars($_GET['nama'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">ISBN Buku</label>
                    <input type="text" name="isbn" class="form-control-modern" 
                           placeholder="Cari ISBN..." 
                           value="<?= htmlspecialchars($_GET['isbn'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Status</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="menunggu" <?= ($_GET['status'] ?? '') === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="dipinjam" <?= ($_GET['status'] ?? '') === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="dibatalkan" <?= ($_GET['status'] ?? '') === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-modern flex-fill">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Booking Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>Daftar Booking
                    <small class="text-muted">(<?= $total_records ?> booking)</small>
                </h5>
            </div>
            <div>
                <?php if ($total_records > 0): ?>
                    <span class="badge bg-info">Halaman <?= $page ?> dari <?= $total_pages ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Anggota</th>
                            <th>Buku</th>
                            <th>Tanggal Booking</th>
                            <th>Expired</th>
                            <th>Status</th>
                            <th width="20%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings): ?>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                // Status badge
                                $status_badge = '';
                                $status_text = '';
                                $is_expired = ($booking['status'] == 'menunggu' && $booking['days_until_expire'] < 0);
                                
                                switch($booking['status']) {
                                    case 'menunggu':
                                        $status_badge = $is_expired ? 'danger' : 'warning';
                                        $status_text = $is_expired ? 'EXPIRED' : 'MENUNGGU';
                                        break;
                                    case 'dipinjam':
                                        $status_badge = 'success';
                                        $status_text = 'DIPINJAM';
                                        break;
                                    case 'dibatalkan':
                                        $status_badge = 'secondary';
                                        $status_text = 'DIBATALKAN';
                                        break;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= $booking['id_booking'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($booking['nama']) ?></div>
                                        <small class="text-muted">NIK: <?= htmlspecialchars($booking['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($booking['judul']) ?></div>
                                        <small class="text-muted">ISBN: <?= htmlspecialchars($booking['isbn']) ?></small>
                                        <br>
                                        <small class="text-muted">Stok tersedia: <?= $booking['stok_tersedia'] ?></small>
                                    </td>
                                    <td>
                                        <div><?= formatTanggal($booking['tanggal_booking']) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($booking['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($booking['expired_at']): ?>
                                            <div class="<?= $is_expired ? 'text-danger fw-bold' : '' ?>">
                                                <?= formatTanggal($booking['expired_at']) ?>
                                            </div>
                                            <?php if ($booking['days_until_expire'] >= 0): ?>
                                                <small class="<?= $booking['days_until_expire'] == 0 ? 'text-warning' : 'text-muted' ?>">
                                                    <?= $booking['days_until_expire'] == 0 ? 'Hari ini' : $booking['days_until_expire'] . ' hari lagi' ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-danger"><?= abs($booking['days_until_expire']) ?> hari lalu</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_badge ?>">
                                            <?= $status_text ?>
                                        </span>
                                        <?php if ($is_expired): ?>
                                            <br><small class="text-danger">(Auto cancel soon)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($booking['status'] == 'menunggu'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve booking ini?')">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id_booking'] ?>">
                                                    <button type="submit" name="approve_booking" class="btn btn-success" 
                                                            <?= $booking['stok_tersedia'] <= 0 ? 'disabled title="Buku tidak tersedia"' : '' ?>>
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                                        data-bs-target="#cancelModal" data-booking-id="<?= $booking['id_booking'] ?>">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php elseif ($booking['status'] == 'dipinjam'): ?>
                                                <a href="../transaksi/peminjaman.php?search=<?= $booking['nik'] ?>" 
                                                   class="btn btn-info" title="Lihat Peminjaman">
                                                    <i class="fas fa-external-link-alt"></i> Lihat
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">Tidak ada data booking</h6>
                                    <?php if (!empty($_GET)): ?>
                                        <p class="small">Coba ubah kriteria pencarian</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Menampilkan <?= min($limit, $total_records - $offset) ?> dari <?= $total_records ?> booking
                        </div>
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle me-2"></i>Batalkan Booking
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="booking_id" id="cancel_booking_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian!</strong> Apakah Anda yakin ingin membatalkan booking ini?
                    </div>
                    <div class="mb-3">
                        <label class="form-label-modern">Alasan Pembatalan (Opsional)</label>
                        <textarea name="alasan" class="form-control-modern" rows="3" 
                                  placeholder="Masukkan alasan pembatalan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" name="cancel_booking" class="btn btn-danger">
                        <i class="fas fa-check me-2"></i>Ya, Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Cancel modal setup
document.addEventListener('DOMContentLoaded', function() {
    var cancelModal = document.getElementById('cancelModal');
    cancelModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var bookingId = button.getAttribute('data-booking-id');
        document.getElementById('cancel_booking_id').value = bookingId;
    });
});
</script>

<?php include '../../includes/footer.php'; ?>