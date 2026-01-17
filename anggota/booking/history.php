<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Riwayat Booking';
$body_class = 'anggota-booking-history';
include '../../config/database.php';

// Get NIK from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nik FROM anggota WHERE user_id = ?");
$stmt->execute([$user_id]);
$anggota = $stmt->fetch();
$nik = $anggota['nik'] ?? null;

if (!$nik) {
    setFlashMessage('Data anggota tidak ditemukan', 'danger');
    redirect('../dashboard.php');
}

// ========== FILTER PARAMETERS ==========
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ========== BUILD QUERY ==========
$where = "WHERE b.nik = ?";
$params = [$nik];

if (!empty($search)) {
    $where .= " AND (bk.judul LIKE ? OR bk.pengarang LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    $where .= " AND b.status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $where .= " AND b.tanggal_booking >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where .= " AND b.tanggal_booking <= ?";
    $params[] = $date_to;
}

// ========== PAGINATION ==========
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Count total
$count_query = "SELECT COUNT(*) FROM booking b JOIN buku bk ON b.isbn = bk.isbn $where";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get booking history
$query = "
    SELECT b.*, 
           bk.judul,
           bk.pengarang,
           bk.tahun_terbit,
           DATEDIFF(b.expired_at, b.tanggal_booking) as booking_duration
    FROM booking b
    JOIN buku bk ON b.isbn = bk.isbn
    $where
    ORDER BY b.tanggal_booking DESC, b.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// ========== STATISTICS ==========
$stats = [
    'total' => 0,
    'menunggu' => 0,
    'dipinjam' => 0,
    'dibatalkan' => 0,
    'expired' => 0
];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ?");
    $stmt->execute([$nik]);
    $stats['total'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'menunggu'");
    $stmt->execute([$nik]);
    $stats['menunggu'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'dipinjam'");
    $stmt->execute([$nik]);
    $stats['dipinjam'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'dibatalkan'");
    $stmt->execute([$nik]);
    $stats['dibatalkan'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'menunggu' AND expired_at < CURDATE()");
    $stmt->execute([$nik]);
    $stats['expired'] = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

include '../../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body.anggota-booking-history {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    background-attachment: fixed !important;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

body.anggota-booking-history::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.05) 0%, transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 40%);
    animation: float 20s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(5deg); }
}

.container {
    position: relative;
    z-index: 1;
    padding-top: 2rem;
    padding-bottom: 2rem;
}

/* Breadcrumb */
.breadcrumb {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1rem 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 1.5rem;
}

.breadcrumb-item a {
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.breadcrumb-item a:hover {
    color: #ffd89b;
}

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.9);
}

.breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.6);
}

/* Header Section */
.header-section {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

.header-section h1 {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff 0%, #ffd89b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.header-section p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin-bottom: 0;
}

.btn-light {
    background: white;
    color: #667eea;
    border: none;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.btn-light:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    background: #f8f9fa;
    color: #667eea;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-item {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: pointer;
}

.stat-item:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
}

/* Filter Box */
.filter-box {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.filter-box .form-control,
.filter-box .form-select {
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    padding: 0.75rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.filter-box .form-control:focus,
.filter-box .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-filter {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 0.75rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

/* History Cards */
.history-item {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    transition: all 0.4s ease;
}

.history-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.history-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1rem 1.5rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.booking-id {
    font-weight: 700;
    font-size: 1rem;
}

.booking-date {
    font-size: 0.9rem;
    opacity: 0.9;
}

.book-info {
    padding: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
}

.book-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
}

.book-author {
    font-size: 0.95rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.book-isbn {
    font-size: 0.85rem;
    color: #999;
    font-weight: 500;
}

.history-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}

.detail-item {
    text-align: center;
}

.detail-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #999;
    margin-bottom: 0.5rem;
    letter-spacing: 0.5px;
}

.detail-value {
    font-size: 1rem;
    font-weight: 700;
    color: #333;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-waiting {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.status-dipinjam {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.status-dibatalkan {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

.status-expired {
    background: linear-gradient(135deg, #434343, #000000);
    color: white;
}

/* Empty State */
.empty-history {
    background: white;
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.empty-history i {
    font-size: 5rem;
    color: #ddd;
    margin-bottom: 1.5rem;
}

.empty-history h4 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1rem;
}

.empty-history p {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.empty-history .btn {
    padding: 0.85rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Pagination */
.pagination-container {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    margin-top: 2rem;
}

.pagination {
    margin: 0;
}

.page-link {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-weight: 600;
    margin: 0 0.25rem;
    border-radius: 10px;
    padding: 0.5rem 0.85rem;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: white;
    color: #667eea;
    border-color: white;
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: transparent;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .header-section {
        padding: 1.5rem;
    }
    
    .header-section h1 {
        font-size: 1.8rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-box .row {
        row-gap: 0.75rem;
    }
    
    .history-details {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="index.php">Booking</a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-history"></i> Riwayat
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-history"></i> Riwayat Booking</h1>
                <p>Lihat semua booking yang pernah Anda lakukan</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="index.php" class="btn btn-light px-4 py-2 rounded-pill">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Aktif
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Booking</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $stats['menunggu'] ?></div>
            <div class="stat-label">Menunggu</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $stats['dipinjam'] ?></div>
            <div class="stat-label">Dipinjam</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $stats['dibatalkan'] ?></div>
            <div class="stat-label">Dibatalkan</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $stats['expired'] ?></div>
            <div class="stat-label">Expired</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">
                <?= $stats['total'] > 0 ? round(($stats['dipinjam'] / $stats['total']) * 100) : 0 ?>%
            </div>
            <div class="stat-label">Success Rate</div>
        </div>
    </div>

    <!-- Filter Box -->
    <div class="filter-box">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" 
                       placeholder="Cari judul, pengarang, ISBN..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="menunggu" <?= $status == 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="dipinjam" <?= $status == 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                    <option value="dibatalkan" <?= $status == 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" 
                       placeholder="Dari Tanggal"
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" 
                       placeholder="Sampai Tanggal"
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-filter w-100">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- History List -->
    <?php if (!empty($bookings)): ?>
        <?php foreach ($bookings as $booking): ?>
            <?php
            $is_expired = ($booking['status'] == 'menunggu' && strtotime($booking['expired_at']) < time());
            $status_class = '';
            switch($booking['status']) {
                case 'menunggu': 
                    $status_class = $is_expired ? 'status-expired' : 'status-waiting';
                    $status_text = $is_expired ? 'EXPIRED' : 'MENUNGGU';
                    break;
                case 'dipinjam': 
                    $status_class = 'status-dipinjam'; 
                    $status_text = 'DIPINJAM';
                    break;
                case 'dibatalkan': 
                    $status_class = 'status-dibatalkan'; 
                    $status_text = 'DIBATALKAN';
                    break;
            }
            ?>
            <div class="history-item">
                <div class="history-header">
                    <div class="booking-id">#<?= $booking['id_booking'] ?></div>
                    <div class="booking-date">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?= formatTanggal($booking['tanggal_booking']) ?>
                    </div>
                </div>
                
                <div class="book-info">
                    <div class="book-title"><?= htmlspecialchars($booking['judul']) ?></div>
                    <div class="book-author">
                        <i class="fas fa-user-edit me-1"></i>
                        <?= htmlspecialchars($booking['pengarang']) ?>
                    </div>
                    <div class="book-isbn">
                        <i class="fas fa-barcode me-1"></i>
                        ISBN: <?= htmlspecialchars($booking['isbn']) ?>
                    </div>
                </div>
                
                <div class="history-details">
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <span class="status-badge <?= $status_class ?>">
                            <?= $status_text ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Expired</div>
                        <div class="detail-value">
                            <?= $booking['expired_at'] ? formatTanggal($booking['expired_at']) : '-' ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Durasi</div>
                        <div class="detail-value">
                            <?= $booking['booking_duration'] ? $booking['booking_duration'] . ' hari' : '-' ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tahun Terbit</div>
                        <div class="detail-value"><?= $booking['tahun_terbit'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="text-white mb-2 mb-md-0">
                        Menampilkan <?= min($limit, $total_records - $offset) ?> dari <?= $total_records ?> booking
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php 
                            $query_params = $_GET;
                            unset($query_params['page']);
                            $base_url = '?' . http_build_query($query_params) . ($query_params ? '&' : '');
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= $base_url ?>page=<?= $page - 1 ?>">
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
                                    <a class="page-link" href="<?= $base_url ?>page=<?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= $base_url ?>page=<?= $page + 1 ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-history">
            <i class="fas fa-history"></i>
            <h4>Tidak ada riwayat booking</h4>
            <p>
                <?php if (!empty($search) || !empty($status) || !empty($date_from) || !empty($date_to)): ?>
                    Tidak ditemukan booking dengan kriteria tersebut
                <?php else: ?>
                    Anda belum pernah melakukan booking
                <?php endif; ?>
            </p>
            <?php if (!empty($search) || !empty($status) || !empty($date_from) || !empty($date_to)): ?>
                <a href="history.php" class="btn btn-primary px-4">
                    <i class="fas fa-redo me-1"></i> Reset Filter
                </a>
            <?php else: ?>
                <a href="process.php" class="btn btn-primary px-4">
                    <i class="fas fa-plus me-1"></i> Booking Buku Baru
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Animation for history items
document.addEventListener('DOMContentLoaded', function() {
    const historyItems = document.querySelectorAll('.history-item');
    historyItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(30px)';
        setTimeout(() => {
            item.style.transition = 'all 0.6s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animate stat items
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 80);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>