<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Katalog Buku';
$body_class = 'anggota-katalog';
include '../../config/database.php';

// Get user NIK
$nik = getUserNIK();

if (!$nik) {
    setFlashMessage('Data anggota tidak ditemukan. Silakan hubungi admin.', 'danger');
    redirect('dashboard.php');
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$tahun = $_GET['tahun'] ?? '';
$status = $_GET['status'] ?? '';

// Build filters array
$filters = [];
if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($kategori)) {
    $filters['kategori'] = $kategori;
}
if (!empty($tahun)) {
    $filters['tahun'] = $tahun;
}
if (!empty($status)) {
    $filters['status'] = $status;
}

try {
    // Search books dengan stok_tersedia
    $books = searchBooksWithStock($search, $filters);
    
    // Get categories for filter dropdown
    $kategori_list = getKategoriList();
    
    // Get unique years for filter dropdown
    $stmt = $conn->query("SELECT DISTINCT tahun_terbit FROM buku ORDER BY tahun_terbit DESC");
    $tahun_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user's active bookings
    $user_bookings_stmt = $conn->prepare("
        SELECT isbn FROM booking 
        WHERE nik = ? AND status IN ('menunggu', 'dipinjam')
    ");
    $user_bookings_stmt->execute([$nik]);
    $user_active_bookings = $user_bookings_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get booking queue counts for all books
    $queue_stmt = $conn->query("
        SELECT isbn, COUNT(*) as queue_count 
        FROM booking 
        WHERE status = 'menunggu' 
        AND expired_at >= CURDATE()
        GROUP BY isbn
    ");
    $booking_queues = $queue_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get next return dates for books
    $return_stmt = $conn->query("
        SELECT p.isbn, MIN(p.tanggal_kembali) as next_return
        FROM peminjaman p
        WHERE p.status = 'dipinjam'
        AND p.tanggal_kembali >= CURDATE()
        GROUP BY p.isbn
    ");
    $next_returns = $return_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get max books allowed
    $max_books_stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'");
    $max_books = (int)$max_books_stmt->fetchColumn() ?: 3;
    
    // Count user's active bookings
    $user_active_count = count($user_active_bookings);
    
    // Get enable_booking setting
    $enable_booking_stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'enable_booking'");
    $enable_booking = $enable_booking_stmt->fetchColumn();
    $booking_enabled = ($enable_booking == '1');
    
    // Get booking expire days
    $expire_stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'booking_expire_days'");
    $booking_expire_days = (int)$expire_stmt->fetchColumn() ?: 2;
    
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $books = [];
    $kategori_list = [];
    $tahun_list = [];
    $user_active_bookings = [];
    $booking_queues = [];
    $next_returns = [];
    $max_books = 3;
    $user_active_count = 0;
    $booking_enabled = true;
    $booking_expire_days = 2;
}

include '../../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
    animation: float 20s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.container {
    position: relative;
    z-index: 1;
}

.breadcrumb {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
}

.breadcrumb-item a:hover {
    color: #ffd89b;
}

.header-section {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-section h1 {
    color: white;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.header-section p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
}

.search-box {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.search-box .input-group {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.search-box .input-group-text {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
}

.search-box .form-control {
    border: none;
    padding: 1rem;
    font-size: 1rem;
}

.search-box .form-control:focus {
    box-shadow: none;
}

.filter-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.filter-section .form-select {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.filter-section .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
}

.filter-section .btn-outline-primary {
    border: 2px solid #667eea;
    color: #667eea;
    font-weight: 600;
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
}

.filter-section .btn-outline-primary:hover {
    background: #667eea;
    color: white;
}

.results-info {
    color: white;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.book-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    height: 100%;
    margin-bottom: 2rem;
}

.book-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
}

.book-card .book-cover {
    height: 200px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.book-card .book-cover::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent 40%, rgba(255, 255, 255, 0.1) 50%, transparent 60%);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.book-card .book-cover i {
    font-size: 4rem;
    color: white;
    z-index: 1;
}

.book-card .book-details {
    padding: 1.5rem;
}

.book-card .book-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #333;
    line-height: 1.4;
    height: 3.5em;
    overflow: hidden;
}

.book-card .book-author {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
    min-height: 2em;
}

.book-card .book-meta {
    color: #888;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.book-card .book-meta i {
    margin-right: 0.3rem;
}

.book-card .kategori-tags {
    margin-bottom: 1rem;
    min-height: 2em;
}

.book-card .kategori-tag {
    display: inline-block;
    background: #e0e7ff;
    color: #667eea;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 0.3rem;
    margin-bottom: 0.3rem;
}

/* Stock info styles */
.stock-info {
    margin-bottom: 1rem;
    text-align: center;
    min-height: 3em;
}

.stock-badge {
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.95rem;
    display: inline-block;
    text-align: center;
    width: 100%;
}

.stock-available {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.stock-limited {
    background: linear-gradient(135deg, #ffa62e, #ffd89b);
    color: #333;
}

.stock-unavailable {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
}

.stock-reserved {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
}

/* Return Date Info */
.return-date {
    font-size: 0.8rem;
    color: #666;
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
}

.return-date i {
    color: #ff6b6b;
}

/* Queue Badge */
.queue-info {
    margin-top: 0.5rem;
}

.queue-badge {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    margin-right: 0.5rem;
    margin-bottom: 0.3rem;
}

.hold-badge {
    background: linear-gradient(135deg, #ffa62e, #ffd89b);
    color: #333;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    margin-right: 0.5rem;
    margin-bottom: 0.3rem;
}

/* Booking Button Styles */
.booking-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-detail {
    width: 100%;
    padding: 0.8rem;
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-detail:hover {
    background: #667eea;
    color: white;
}

.btn-booking {
    width: 100%;
    padding: 0.8rem;
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-booking:hover {
    background: linear-gradient(135deg, #2575fc, #6a11cb);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 117, 252, 0.3);
}

.btn-booking:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-borrow {
    width: 100%;
    padding: 0.8rem;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-borrow:hover {
    background: linear-gradient(135deg, #38ef7d, #11998e);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(56, 239, 125, 0.3);
}

.already-booked {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    padding: 0.8rem;
    border-radius: 10px;
    color: white;
    font-size: 0.9rem;
    text-align: center;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.already-booked i {
    margin-right: 0.5rem;
}

.booking-disabled {
    background: linear-gradient(135deg, #6c757d, #adb5bd);
    padding: 0.8rem;
    border-radius: 10px;
    color: white;
    font-size: 0.9rem;
    text-align: center;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.position-indicator {
    font-size: 0.8rem;
    color: #666;
    text-align: center;
    margin-top: 0.5rem;
}

.hold-indicator {
    font-size: 0.75rem;
    color: #ff6b6b;
    text-align: center;
    margin-top: 0.3rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
}

.hold-indicator i {
    font-size: 0.7rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 2px dashed rgba(255, 255, 255, 0.3);
}

.empty-state i {
    font-size: 4rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1.5rem;
}

.empty-state h4 {
    color: white;
    font-weight: 600;
    margin-bottom: 1rem;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .header-section { padding: 1.5rem; }
    .book-card { margin-bottom: 1.5rem; }
    .book-title { height: auto; }
    .book-author { min-height: auto; }
}
</style>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="breadcrumb-item active">
                <i class="fas fa-book"></i> Katalog
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-book-open"></i> Katalog Buku</h1>
                <p>Jelajahi koleksi buku perpustakaan kami yang lengkap</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-inline-block px-3 py-2 rounded" style="background: rgba(255, 255, 255, 0.2);">
                    <small class="text-white">
                        <i class="fas fa-info-circle me-1"></i>
                        Booking: <strong><?= $user_active_count ?></strong>/<strong><?= $max_books ?></strong> aktif
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-box">
        <form method="GET" action="" class="mb-0">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" 
                       class="form-control" 
                       name="search" 
                       placeholder="Cari buku berdasarkan judul, pengarang, ISBN, atau penerbit..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary px-4" type="submit">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
            </div>
        </form>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= $kat['id_kategori'] ?>" 
                                <?= ($kategori == $kat['id_kategori']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="tahun">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($tahun_list as $year): ?>
                            <option value="<?= $year ?>" 
                                <?= ($tahun == $year) ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">Semua Status</option>
                        <option value="tersedia" <?= ($status == 'tersedia') ? 'selected' : '' ?>>Bisa Dipinjam</option>
                        <option value="antrian" <?= ($status == 'antrian') ? 'selected' : '' ?>>Dalam Antrian</option>
                        <option value="habis" <?= ($status == 'habis') ? 'selected' : '' ?>>Stok Habis</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Results Info -->
    <div class="results-info">
        <i class="fas fa-books me-2"></i>
        Menampilkan <strong><?= count($books) ?></strong> buku
        <?php if (!empty($search)): ?>
            untuk "<strong><?= htmlspecialchars($search) ?></strong>"
        <?php endif; ?>
    </div>

    <!-- Books Grid -->
    <div class="row">
        <?php if (!empty($books)): ?>
            <?php foreach ($books as $book): 
                $stok_tersedia = $book['stok_tersedia'] ?? $book['stok'];
                $queue_count = $booking_queues[$book['isbn']] ?? 0;
                $already_booked = in_array($book['isbn'], $user_active_bookings);
                $can_book_more = $user_active_count < $max_books;
                $next_return = $next_returns[$book['isbn']] ?? null;
                
                // Hitung stok efektif (setelah dipotong antrian)
                $effective_stock = $stok_tersedia - $queue_count;
                
                // Check if book has hold (first in queue)
                $hold_stmt = $conn->prepare("
                    SELECT b.*, a.nama,
                           DATE_ADD(b.expired_at, INTERVAL -? DAY) as hold_start_date
                    FROM booking b
                    JOIN anggota a ON b.nik = a.nik
                    WHERE b.isbn = ? AND b.status = 'menunggu'
                    AND b.expired_at >= CURDATE()
                    ORDER BY b.tanggal_booking ASC
                    LIMIT 1
                ");
                $hold_stmt->execute([$booking_expire_days, $book['isbn']]);
                $has_hold = $hold_stmt->fetch();
                
                // Tentukan status stok
                if ($effective_stock >= 1 && !$has_hold) {
                    // Bisa pinjam langsung
                    $stock_class = 'stock-available';
                    $stock_text = "Tersedia: {$stok_tersedia} buku";
                    $stock_icon = 'fa-check';
                    $can_borrow = true;
                } elseif ($effective_stock >= 1 && $has_hold) {
                    // Ada stok tapi ada hold
                    $stock_class = 'stock-reserved';
                    $stock_text = "Hold aktif";
                    $stock_icon = 'fa-user-clock';
                    $can_borrow = false;
                } elseif ($stok_tersedia > 0) {
                    // Ada stok tapi sudah dipesan antrian
                    $stock_class = 'stock-limited';
                    $stock_text = "{$queue_count} dalam antrian";
                    $stock_icon = 'fa-users';
                    $can_borrow = false;
                } else {
                    // Stok habis
                    $stock_class = 'stock-unavailable';
                    $stock_text = "Stok Habis";
                    $stock_icon = 'fa-times';
                    $can_borrow = false;
                }
            ?>
                <div class="col-lg-4 col-md-6">
                    <div class="book-card">
                        <div class="book-cover">
                            <i class="fas fa-book"></i>
                        </div>
                        
                        <div class="book-details">
                            <h5 class="book-title" title="<?= htmlspecialchars($book['judul']) ?>">
                                <?= htmlspecialchars(truncateText($book['judul'], 50)) ?>
                            </h5>
                            
                            <div class="book-author">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($book['pengarang']) ?>
                            </div>
                            
                            <div class="book-meta">
                                <div>
                                    <i class="fas fa-building"></i> 
                                    <?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?>
                                </div>
                                <div>
                                    <i class="fas fa-calendar"></i> <?= $book['tahun_terbit'] ?>
                                </div>
                                <div>
                                    <i class="fas fa-barcode"></i> <?= htmlspecialchars($book['isbn']) ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($book['kategori_list'])): ?>
                                <div class="kategori-tags">
                                    <?php 
                                    $kategori_array = explode(', ', $book['kategori_list']);
                                    foreach (array_slice($kategori_array, 0, 3) as $kat):
                                    ?>
                                        <span class="kategori-tag"><?= htmlspecialchars($kat) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($kategori_array) > 3): ?>
                                        <span class="kategori-tag">+<?= count($kategori_array) - 3 ?> lagi</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Stok dan Status Booking -->
                            <div class="stock-info">
                                <div class="stock-badge <?= $stock_class ?>">
                                    <i class="fas <?= $stock_icon ?> me-1"></i>
                                    <?= $stock_text ?>
                                </div>
                                
                                <?php if ($next_return && $stok_tersedia == 0): ?>
                                    <div class="return-date">
                                        <i class="fas fa-calendar-day"></i>
                                        Pengembalian: <?= date('d/m', strtotime($next_return)) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="queue-info">
                                    <?php if ($queue_count > 0): ?>
                                        <span class="queue-badge">
                                            <i class="fas fa-users me-1"></i>
                                            <?= $queue_count ?> antrian
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_hold): ?>
                                        <span class="hold-badge">
                                            <i class="fas fa-clock me-1"></i>
                                            Hold aktif
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Informasi Hold -->
                            <?php if ($has_hold): ?>
                                <div class="hold-indicator">
                                    <i class="fas fa-info-circle"></i>
                                    Hold <?= $booking_expire_days ?> hari setelah tersedia
                                </div>
                            <?php endif; ?>
                            
                            <!-- Tombol Aksi -->
                            <div class="booking-actions mt-3">
                                <?php if ($already_booked): ?>
                                    <div class="already-booked">
                                        <i class="fas fa-check-circle"></i>
                                        Sudah Anda booking
                                    </div>
                                <?php elseif (!$booking_enabled): ?>
                                    <div class="booking-disabled">
                                        <i class="fas fa-ban"></i>
                                        Fitur booking dinonaktifkan
                                    </div>
                                <?php else: ?>
                                    <?php if ($can_borrow): ?>
                                        <!-- Jika bisa pinjam langsung -->
                                        <div class="d-flex gap-2">
                                            <a href="detail_buku.php?isbn=<?= urlencode($book['isbn']) ?>" 
                                               class="btn-borrow flex-grow-1">
                                                <i class="fas fa-hand-holding me-1"></i> Pinjam
                                            </a>
                                            <?php if ($can_book_more): ?>
                                                <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                                                   class="btn-booking" style="width: auto; padding: 0.8rem 1rem;">
                                                    <i class="fas fa-calendar-plus me-1"></i> Booking
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Jika tidak bisa pinjam langsung -->
                                        <?php if ($can_book_more): ?>
                                            <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                                               class="btn-booking">
                                                <i class="fas fa-calendar-plus me-1"></i> Booking Sekarang
                                            </a>
                                            
                                            <?php if ($queue_count > 0): ?>
                                                <div class="position-indicator">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Posisi Anda: ke-<?= $queue_count + 1 ?> dalam antrian
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="hold-indicator">
                                                <i class="fas fa-clock me-1"></i>
                                                Hold <?= $booking_expire_days ?> hari setelah giliran
                                            </div>
                                        <?php else: ?>
                                            <button class="btn-booking" disabled title="Anda sudah mencapai batas maksimal booking">
                                                <i class="fas fa-calendar-times me-1"></i> Booking Penuh
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Tombol detail selalu ada -->
                                <a href="detail_buku.php?isbn=<?= urlencode($book['isbn']) ?>" 
                                   class="btn btn-detail">
                                    <i class="fas fa-info-circle me-1"></i> Detail Buku
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <h4>Tidak ada buku ditemukan</h4>
                    <p><?php if (!empty($search)): ?>
                        Tidak ditemukan buku untuk "<strong><?= htmlspecialchars($search) ?></strong>"
                    <?php else: ?>
                        Belum ada buku dalam koleksi perpustakaan
                    <?php endif; ?></p>
                    <?php if (!empty($search) || !empty($kategori) || !empty($tahun) || !empty($status)): ?>
                        <a href="index.php" class="btn btn-primary px-4">
                            <i class="fas fa-undo me-1"></i> Reset Pencarian
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookCards = document.querySelectorAll('.book-card');
    bookCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Show booking tip for limited stock books
    setTimeout(() => {
        const limitedCards = document.querySelectorAll('.stock-limited, .stock-reserved, .stock-unavailable');
        limitedCards.forEach((card, index) => {
            setTimeout(() => {
                const title = card.closest('.book-card').querySelector('.book-title').textContent;
                const stockText = card.querySelector('.stock-badge').textContent;
                
                // Only show tip for books with queue or no stock
                if (stockText.includes('antrian') || stockText.includes('Habis') || stockText.includes('Hold')) {
                    const toastEl = document.createElement('div');
                    toastEl.innerHTML = `
                        <div class="toast-container position-fixed bottom-0 end-0 p-3">
                            <div class="toast" role="alert">
                                <div class="toast-header" style="background: linear-gradient(135deg, #ffa62e, #ffd89b); color: #333;">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    <strong class="me-auto">Tips Booking</strong>
                                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                                </div>
                                <div class="toast-body">
                                    "<strong>${title}</strong>" memiliki antrian. Booking sekarang untuk amankan posisi!
                                    <br><small>Hold <?= $booking_expire_days ?> hari setelah giliran</small>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toastEl);
                    const toast = new bootstrap.Toast(toastEl.querySelector('.toast'));
                    
                    // Show toast with delay based on index
                    setTimeout(() => {
                        toast.show();
                        // Auto remove after 5 seconds
                        setTimeout(() => {
                            toastEl.remove();
                        }, 5000);
                    }, 1000 + (index * 300));
                }
            }, 1000);
        });
    }, 2000);
});
</script>

<?php include '../../includes/footer.php'; ?>