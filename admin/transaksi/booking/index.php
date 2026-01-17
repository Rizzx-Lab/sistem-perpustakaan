<?php
require_once '../../../config/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Kelola Booking Buku';
include '../../../config/database.php';

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
    ORDER BY b.tanggal_booking DESC, b.created_at DESC
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
                        <a href="../peminjaman.php">Transaksi</a>
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
                            <th width="25%">Aksi</th>
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
                                            <br><small class="text-danger">(Kadaluarsa)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($booking['status'] == 'menunggu'): ?>
                                                <!-- Approve Button - Link to approve.php -->
                                                <?php if ($booking['stok_tersedia'] > 0): ?>
                                                    <a href="approve.php?id=<?= $booking['id_booking'] ?>" 
                                                       class="btn btn-success"
                                                       onclick="return confirm('Approve booking #<?= $booking['id_booking'] ?>?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary" disabled
                                                            title="Tidak dapat approve: Stok buku habis">
                                                        <i class="fas fa-times-circle"></i> Stok Habis
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Cancel Button - Link to cancel.php -->
                                                <a href="cancel.php?id=<?= $booking['id_booking'] ?>" 
                                                   class="btn btn-danger">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                                
                                            <?php elseif ($booking['status'] == 'dipinjam'): ?>
                                                <!-- View related peminjaman -->
                                                <?php
                                                // PERBAIKAN: Cari peminjaman yang terkait dengan booking ini
                                                // Gunakan tanggal booking sebagai referensi untuk mencari peminjaman yang sesuai
                                                $stmt2 = $conn->prepare("
                                                    SELECT id_peminjaman, tanggal_pinjam 
                                                    FROM peminjaman 
                                                    WHERE nik = ? AND isbn = ? AND status = 'dipinjam'
                                                    ORDER BY created_at DESC LIMIT 1
                                                ");
                                                $stmt2->execute([$booking['nik'], $booking['isbn']]);
                                                $peminjaman = $stmt2->fetch();
                                                ?>
                                                <?php if ($peminjaman): ?>
                                                    <a href="../peminjaman.php?search=<?= $peminjaman['id_peminjaman'] ?>" 
                                                       class="btn btn-info" title="Lihat Detail Peminjaman">
                                                        <i class="fas fa-external-link-alt"></i> Peminjaman #<?= $peminjaman['id_peminjaman'] ?>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-warning" 
                                                            onclick="showBookingInfo(<?= $booking['id_booking'] ?>, '<?= htmlspecialchars(addslashes($booking['judul'])) ?>', '<?= htmlspecialchars(addslashes($booking['nama'])) ?>')"
                                                            title="Belum ada peminjaman terkait">
                                                        <i class="fas fa-info-circle"></i> Info
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- View Details Button -->
                                            <a href="detail.php?id=<?= $booking['id_booking'] ?>" 
                                               class="btn btn-secondary">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
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
                                    <?php else: ?>
                                        <p class="small">
                                            <a href="add_test_booking.php" class="btn btn-sm btn-primary mt-2">
                                                <i class="fas fa-plus me-1"></i>Tambah Data Test
                                            </a>
                                        </p>
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

<!-- Modal Info Booking -->
<div class="modal fade" id="bookingInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Informasi Booking
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Status Booking</h6>
                    <p class="mb-2" id="bookingInfoText"></p>
                    <p class="mb-0 small">
                        <strong>Solusi:</strong> Lakukan "Approve" pada booking ini untuk mengkonversinya menjadi peminjaman reguler.
                    </p>
                </div>
                <div class="text-center">
                    <a href="#" id="approveBookingLink" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve Sekarang
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Add confirmation for cancel links
document.addEventListener('DOMContentLoaded', function() {
    const cancelLinks = document.querySelectorAll('a[href*="cancel.php"]');
    cancelLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const bookingId = this.getAttribute('href').split('id=')[1];
            if (!confirm(`Batalkan booking #${bookingId}?`)) {
                e.preventDefault();
            }
        });
    });
});

// Fungsi untuk menampilkan info booking
function showBookingInfo(bookingId, judulBuku, namaAnggota) {
    document.getElementById('bookingInfoText').textContent = 
        `Booking #${bookingId} untuk buku "${judulBuku}" oleh "${namaAnggota}" sudah berstatus "DIPINJAM" tetapi belum ada peminjaman terkait di sistem.`;
    
    // Set link approve
    document.getElementById('approveBookingLink').href = `approve.php?id=${bookingId}`;
    
    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('bookingInfoModal'));
    modal.show();
}

// Alert untuk booking expired
document.addEventListener('DOMContentLoaded', function() {
    const expiredRows = document.querySelectorAll('tr');
    expiredRows.forEach(row => {
        const expiredBadge = row.querySelector('.text-danger.fw-bold');
        if (expiredBadge) {
            const bookingId = row.querySelector('.badge.bg-secondary')?.textContent?.replace('#', '') || '';
            if (bookingId) {
                const approveBtn = row.querySelector('a.btn-success');
                if (approveBtn) {
                    approveBtn.classList.remove('btn-success');
                    approveBtn.classList.add('btn-warning');
                    approveBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Expired';
                    approveBtn.title = 'Booking sudah expired';
                    approveBtn.removeAttribute('href');
                    approveBtn.style.cursor = 'not-allowed';
                    approveBtn.onclick = function(e) {
                        e.preventDefault();
                        alert('Booking ini sudah expired dan tidak dapat diapprove. Silakan batalkan atau buat booking baru.');
                    };
                }
            }
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>