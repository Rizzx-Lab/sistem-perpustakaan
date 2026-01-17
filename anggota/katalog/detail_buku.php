<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Detail Buku';
include '../../config/database.php';

// Get ISBN from URL
$isbn = $_GET['isbn'] ?? '';

if (empty($isbn)) {
    setFlashMessage('ISBN tidak valid', 'error');
    redirect(SITE_URL . 'anggota/katalog/');
}

try {
    // Get book details with JOIN to penerbit
    $stmt = $conn->prepare("
        SELECT b.*, 
               p.nama_penerbit as penerbit,
               (SELECT COUNT(*) FROM peminjaman WHERE isbn = b.isbn) as total_dipinjam,
               (SELECT COUNT(*) FROM peminjaman WHERE isbn = b.isbn AND status = 'dipinjam') as sedang_dipinjam,
               b.stok_tersedia as stok_tersedia
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        WHERE b.isbn = ?
    ");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch();
    
    if (!$book) {
        setFlashMessage('Buku tidak ditemukan', 'error');
        redirect(SITE_URL . 'anggota/katalog/');
    }
    
    // Check if user already borrowed this book
    $user_stmt = $conn->prepare("SELECT nik FROM anggota WHERE user_id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch();
    
    $already_borrowed = false;
    $already_booked = false;
    if ($user_data) {
        $nik = $user_data['nik'];
        
        // Check if borrowed
        $check_stmt = $conn->prepare("
            SELECT id_peminjaman FROM peminjaman 
            WHERE nik = ? AND isbn = ? AND status = 'dipinjam'
        ");
        $check_stmt->execute([$nik, $isbn]);
        $already_borrowed = $check_stmt->fetch() ? true : false;
        
        // Check if booked
        $booked_stmt = $conn->prepare("
            SELECT id_booking FROM booking 
            WHERE nik = ? AND isbn = ? AND status IN ('menunggu', 'dipinjam')
        ");
        $booked_stmt->execute([$nik, $isbn]);
        $already_booked = $booked_stmt->fetch() ? true : false;
    }
    
    // Get booking queue info
    $queue_stmt = $conn->prepare("
        SELECT COUNT(*) as queue_count,
               MIN(tanggal_booking) as oldest_booking,
               MAX(expired_at) as latest_expiry
        FROM booking 
        WHERE isbn = ? AND status = 'menunggu'
        AND expired_at >= CURDATE()
    ");
    $queue_stmt->execute([$isbn]);
    $queue_info = $queue_stmt->fetch();
    
    // Get user position in queue (if booked)
    $user_position = 0;
    if ($user_data && $already_booked) {
        $position_stmt = $conn->prepare("
            SELECT position FROM (
                SELECT id_booking, nik, isbn, tanggal_booking,
                       ROW_NUMBER() OVER (ORDER BY tanggal_booking) as position
                FROM booking 
                WHERE isbn = ? AND status = 'menunggu'
                AND expired_at >= CURDATE()
            ) as queue
            WHERE nik = ?
        ");
        $position_stmt->execute([$isbn, $nik]);
        $position_result = $position_stmt->fetch();
        $user_position = $position_result ? $position_result['position'] : 0;
    }
    
    // ========== ESTIMATION LOGIC ==========
    // Calculate estimated available date with hold system
    $estimated_available = null;
    $hold_info = null;
    
    // Get booking settings
    $settings_stmt = $conn->query("
        SELECT setting_key, setting_value FROM pengaturan 
        WHERE setting_key IN ('enable_booking', 'booking_expire_days', 'max_buku_pinjam', 'max_pinjam_hari')
    ");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $hold_days = (int)($settings['booking_expire_days'] ?? 2);
    
    if ($queue_info && $queue_info['queue_count'] > 0) {
        // Check if there are books currently borrowed
        $borrowed_stmt = $conn->prepare("
            SELECT tanggal_kembali, nik,
                   (SELECT nama FROM anggota a WHERE a.nik = p.nik) as nama_anggota
            FROM peminjaman p
            WHERE isbn = ? AND status = 'dipinjam'
            AND tanggal_kembali >= CURDATE()
            ORDER BY tanggal_kembali ASC
            LIMIT 3
        ");
        $borrowed_stmt->execute([$isbn]);
        $borrowed_books = $borrowed_stmt->fetchAll();
        
        // Check if book is currently on hold for first in queue
        $current_hold_stmt = $conn->prepare("
            SELECT b.*, a.nama,
                   DATE_ADD(b.expired_at, INTERVAL -{$hold_days} DAY) as hold_start_date
            FROM booking b
            JOIN anggota a ON b.nik = a.nik
            WHERE b.isbn = ? AND b.status = 'menunggu'
            AND b.expired_at >= CURDATE()
            ORDER BY b.tanggal_booking ASC
            LIMIT 1
        ");
        $current_hold_stmt->execute([$isbn]);
        $current_hold = $current_hold_stmt->fetch();
        
        if ($current_hold) {
            $hold_info = $current_hold;
            $hold_info['hold_start'] = $current_hold['hold_start_date'];
            $hold_info['hold_until'] = $current_hold['expired_at'];
        }
        
        // Calculate estimated date for user
        if ($book['stok_tersedia'] <= 0) {
            // No available stock, need to wait for returns
            if (!empty($borrowed_books)) {
                // Calculate based on user position
                if ($user_position > 0) {
                    // Each position needs one book to return
                    $books_needed = $user_position;
                    $available_returns = min(count($borrowed_books), $books_needed);
                    
                    if ($available_returns >= $books_needed) {
                        // Get the return date for the book at user's position
                        $needed_return = $borrowed_books[$books_needed - 1]['tanggal_kembali'];
                        
                        // Hold starts hold_days after return
                        $estimated_available = date('Y-m-d', strtotime($needed_return . " + {$hold_days} days"));
                    } else {
                        // Not enough returns, estimate based on last return + extra
                        $last_return = end($borrowed_books)['tanggal_kembali'];
                        $extra_books = $books_needed - $available_returns;
                        $extra_days = $extra_books * 14; // 14 days per book
                        $estimated_available = date('Y-m-d', strtotime($last_return . " + {$hold_days} + {$extra_days} days"));
                    }
                } elseif ($user_data && !$already_booked) {
                    // User not in queue yet, show estimate for new booking
                    $new_position = $queue_info['queue_count'] + 1;
                    $books_needed = $new_position;
                    $available_returns = min(count($borrowed_books), $books_needed);
                    
                    if ($available_returns >= $books_needed) {
                        $needed_return = $borrowed_books[$books_needed - 1]['tanggal_kembali'];
                        $estimated_available = date('Y-m-d', strtotime($needed_return . " + {$hold_days} days"));
                    }
                }
            }
        } elseif ($book['stok_tersedia'] > 0 && $user_position > 0) {
            // There is stock but user is in queue
            if ($user_position <= $book['stok_tersedia']) {
                // User will get a book from current stock
                $days_to_wait = ($user_position - 1) * 7; // 7 days per person
                $estimated_available = date('Y-m-d', strtotime("+{$days_to_wait} days"));
            }
        }
    }
    
    // Get similar books
    $similar_books = $conn->prepare("
        SELECT b.*, 
               p.nama_penerbit as penerbit,
               b.stok_tersedia as stok_tersedia
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        WHERE b.pengarang = ? AND b.isbn != ? 
        LIMIT 4
    ");
    $similar_books->execute([$book['pengarang'], $isbn]);
    $similar = $similar_books->fetchAll();
    
    // Check if user can book more
    $max_books = (int)($settings['max_buku_pinjam'] ?? 3);
    $max_pinjam_hari = (int)($settings['max_pinjam_hari'] ?? 14);
    if ($user_data) {
        $active_stmt = $conn->prepare("
            SELECT COUNT(*) FROM booking 
            WHERE nik = ? AND status IN ('menunggu', 'dipinjam')
        ");
        $active_stmt->execute([$nik]);
        $active_bookings = $active_stmt->fetchColumn();
        $can_book_more = $active_bookings < $max_books;
    } else {
        $can_book_more = false;
    }
    
    // Check if booking feature is enabled
    $booking_enabled = ($settings['enable_booking'] ?? '1') == '1';
    
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'anggota/katalog/');
}

include '../../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    background-attachment: fixed !important;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background */
body::before {
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
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.container {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
}

/* Breadcrumb */
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
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.7);
}

.breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.5);
}

/* Main Card */
.detail-card {
    background: white;
    border-radius: 25px;
    padding: 2rem;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease;
    overflow: hidden;
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

.detail-card h2 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1.5rem;
    font-size: 1.6rem;
    line-height: 1.3;
    word-wrap: break-word;
}

/* Book Icon Section */
.book-display {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
    height: 100%;
    min-height: 250px;
}

.book-display:hover {
    transform: translateY(-5px);
}

.book-display i {
    font-size: 4rem;
    color: white;
    margin-bottom: 1.5rem;
}

.status-badge {
    padding: 0.5rem 1.2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    line-height: 1;
}

.status-badge i {
    font-size: 0.85rem;
    position: static;
}

.status-badge.available {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.status-badge.limited {
    background: linear-gradient(135deg, #ffa62e, #ffd89b);
    color: #333;
}

.status-badge.unavailable {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
    color: white;
}

/* Book Info Table */
.info-table {
    margin-top: 1rem;
    width: 100%;
    table-layout: fixed;
}

.info-table tr {
    border-bottom: 1px solid #f0f0f0;
}

.info-table tr:last-child {
    border-bottom: none;
}

.info-table td {
    padding: 0.8rem 0;
    vertical-align: middle;
    word-wrap: break-word;
}

.info-table td:first-child {
    color: #666;
    font-weight: 500;
    width: 140px;
    white-space: nowrap;
}

.info-table td:last-child {
    color: #333;
    font-weight: 600;
    padding-left: 0.5rem;
}

.info-table i {
    width: 22px;
    text-align: center;
    color: #667eea;
    margin-right: 5px;
}

.stock-badge {
    padding: 0.4rem 1rem;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-block;
}

.stock-badge.available {
    background: linear-gradient(135deg, #11998e, #38ef7d);
}

.stock-badge.limited {
    background: linear-gradient(135deg, #ffa62e, #ffd89b);
    color: #333;
}

.stock-badge.empty {
    background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
}

/* Alert Messages */
.alert-custom {
    border-radius: 15px;
    padding: 1rem;
    border: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.alert-custom.info {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.alert-custom.warning {
    background: linear-gradient(135deg, #fa709a, #fee140);
    color: white;
}

.alert-custom.success {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

/* Buttons */
.btn-back {
    padding: 0.7rem 1.5rem;
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.btn-back:hover {
    background: #667eea;
    color: white;
    transform: scale(1.05);
}

.btn-info {
    padding: 0.7rem 1.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.btn-info:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 1rem;
}

.btn-borrow {
    padding: 0.8rem 1.5rem;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
}

.btn-borrow:hover {
    background: linear-gradient(135deg, #38ef7d, #11998e);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(56, 239, 125, 0.3);
}

.btn-booking {
    padding: 0.8rem 1.5rem;
    background: linear-gradient(135deg, #ff9a9e, #fad0c4);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
}

.btn-booking:hover {
    background: linear-gradient(135deg, #fad0c4, #ff9a9e);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(255, 154, 158, 0.3);
}

.btn-booking:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Hold Status Card */
.hold-status-card {
    background: linear-gradient(135deg, #ffd89b, #19547b);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 5px solid #ffa62e;
    box-shadow: 0 8px 25px rgba(255, 166, 46, 0.2);
}

.hold-status-card h6 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hold-status-card h6 i {
    color: #ffa62e;
}

.hold-info {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.hold-user {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hold-user i {
    color: #ffa62e;
}

.hold-dates {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.hold-date {
    flex: 1;
}

.hold-date-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.2rem;
}

.hold-date-value {
    font-weight: 600;
    color: #333;
}

/* Estimation Card */
.estimation-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 2px solid #667eea;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.1);
}

.estimation-card h6 {
    color: #667eea;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.estimation-date {
    text-align: center;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 10px;
    color: white;
    margin-bottom: 1rem;
}

.estimation-date .date {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.estimation-date .days {
    font-size: 0.9rem;
    opacity: 0.9;
}

.estimation-details {
    font-size: 0.9rem;
    color: #666;
}

.estimation-details ul {
    padding-left: 1.2rem;
    margin: 0;
}

.estimation-details li {
    margin-bottom: 0.3rem;
}

/* Queue Card */
.queue-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    border-left: 5px solid #6a11cb;
}

.queue-card h6 {
    color: #6a11cb;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.queue-info {
    background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.queue-count {
    font-size: 2rem;
    font-weight: 800;
    color: #6a11cb;
    text-align: center;
    margin-bottom: 0.5rem;
}

.queue-label {
    text-align: center;
    color: #555;
    font-size: 0.9rem;
    font-weight: 500;
}

.queue-details {
    color: #666;
    font-size: 0.85rem;
}

.queue-details p {
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.queue-details i {
    width: 20px;
    color: #6a11cb;
}

/* Already Booked Status */
.booking-already {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    font-weight: 500;
    margin-bottom: 1rem;
}

.booking-already i {
    margin-right: 0.5rem;
}

.position-badge {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 25px;
    font-size: 1.4rem;
    font-weight: 800;
    display: inline-block;
    margin: 0.5rem 0;
    box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
}

.position-label {
    font-size: 0.9rem;
    color: #666;
    margin-top: 0.5rem;
}

/* Statistics Card */
.stats-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    animation: fadeInUp 0.6s ease 0.2s both;
}

.stats-card h6 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1.5rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-card h6 i {
    color: #667eea;
}

.stat-item {
    text-align: center;
    padding: 0.8rem;
    border-radius: 15px;
    background: #f8f9fa;
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
}

.stat-item:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    transform: translateY(-3px);
}

.stat-item:hover .stat-number,
.stat-item:hover .stat-label {
    color: white;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.3rem;
}

.stat-label {
    font-size: 0.8rem;
    color: #666;
    font-weight: 500;
}

/* Status Indicators */
.status-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    padding: 0.5rem;
    border-radius: 8px;
    background: #f8f9fa;
}

.status-indicator.available {
    border-left: 4px solid #38ef7d;
}

.status-indicator.hold {
    border-left: 4px solid #ffa62e;
}

.status-indicator.queue {
    border-left: 4px solid #6a11cb;
}

.status-indicator .status-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    color: white;
}

.status-indicator.available .status-icon {
    background: #38ef7d;
}

.status-indicator.hold .status-icon {
    background: #ffa62e;
}

.status-indicator.queue .status-icon {
    background: #6a11cb;
}

.status-indicator .status-text {
    flex: 1;
}

.status-indicator .status-title {
    font-weight: 600;
    font-size: 0.9rem;
}

.status-indicator .status-desc {
    font-size: 0.8rem;
    color: #666;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    animation: fadeInUp 0.6s ease 0.3s both;
    margin-bottom: 1.5rem;
}

.info-card h6 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card h6 i {
    color: #667eea;
}

.info-section-title {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.info-card ol {
    padding-left: 1.2rem;
    margin-bottom: 1rem;
}

.info-card ol li {
    color: #666;
    margin-bottom: 0.5rem;
    line-height: 1.5;
    font-size: 0.9rem;
}

.contact-info p {
    color: #666;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.contact-info i {
    width: 22px;
    color: #667eea;
}

/* Similar Books Card */
.similar-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
    animation: fadeInUp 0.6s ease 0.4s both;
}

.similar-card h5 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.similar-card h5 i {
    color: #667eea;
}

.similar-book-item {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.3s ease;
    margin-bottom: 0.8rem;
}

.similar-book-item:hover {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    transform: translateX(5px);
}

.similar-book-item .book-icon {
    color: #667eea;
    font-size: 1.5rem;
    margin-right: 0.8rem;
}

.similar-book-item h6 {
    margin-bottom: 0.3rem;
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1.3;
}

.similar-book-item h6 a {
    color: #333;
    text-decoration: none;
    transition: color 0.3s ease;
    word-wrap: break-word;
}

.similar-book-item h6 a:hover {
    color: #667eea;
}

.similar-book-item .book-meta {
    color: #666;
    font-size: 0.8rem;
    margin-bottom: 0.3rem;
    line-height: 1.3;
}

.similar-stock-badge {
    padding: 0.2rem 0.6rem;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.similar-stock-badge.limited {
    background: linear-gradient(135deg, #ffa62e, #ffd89b);
    color: #333;
}

.similar-stock-badge.empty {
    background: #6c757d;
}

/* Responsive */
@media (max-width: 768px) {
    .container {
        padding: 0 1rem;
    }
    
    .detail-card {
        padding: 1.5rem;
        margin: 1rem 0;
    }
    
    .detail-card h2 {
        font-size: 1.4rem;
    }
    
    .book-display {
        min-height: 200px;
        margin-bottom: 1.5rem;
    }
    
    .info-table td:first-child {
        width: 120px;
    }
    
    .stats-card,
    .info-card {
        margin-top: 1.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-back, .btn-info {
        width: 100%;
        justify-content: center;
        margin-bottom: 0.5rem;
    }
    
    .hold-dates {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 576px) {
    .detail-card {
        padding: 1rem;
    }
    
    .detail-card h2 {
        font-size: 1.2rem;
    }
    
    .info-table td {
        padding: 0.6rem 0;
        font-size: 0.9rem;
    }
    
    .status-badge {
        padding: 0.4rem 1rem;
        font-size: 0.8rem;
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
                <a href="./"><i class="fas fa-book"></i> Katalog</a>
            </li>
            <li class="breadcrumb-item active">Detail Buku</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <!-- Book Details -->
            <div class="detail-card">
                <h2><?= htmlspecialchars($book['judul']) ?></h2>
                
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="book-display">
                            <i class="fas fa-book"></i>
                            <?php 
                            // Determine status badge
                            $effective_stock = $book['stok_tersedia'] - ($queue_info['queue_count'] ?? 0);
                            if ($effective_stock >= 1) {
                                $status_class = 'available';
                                $status_text = 'Tersedia';
                                $status_icon = 'fa-check';
                            } elseif ($book['stok_tersedia'] > 0) {
                                $status_class = 'limited';
                                $status_text = 'Terbatas';
                                $status_icon = 'fa-exclamation-triangle';
                            } else {
                                $status_class = 'unavailable';
                                $status_text = 'Habis';
                                $status_icon = 'fa-times';
                            }
                            ?>
                            <span class="status-badge <?= $status_class ?>">
                                <i class="fas <?= $status_icon ?>"></i><?= $status_text ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <table class="info-table">
                            <tr>
                                <td><i class="fas fa-barcode"></i> ISBN</td>
                                <td><?= htmlspecialchars($book['isbn']) ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-user"></i> Pengarang</td>
                                <td><?= htmlspecialchars($book['pengarang']) ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-building"></i> Penerbit</td>
                                <td><?= !empty($book['penerbit']) ? htmlspecialchars($book['penerbit']) : 'Tidak tersedia' ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-calendar"></i> Tahun Terbit</td>
                                <td><?= $book['tahun_terbit'] ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-box"></i> Stok Tersedia</td>
                                <td>
                                    <?php 
                                    if (($book['stok_tersedia'] ?? 0) >= 3) {
                                        $stock_class = 'available';
                                    } elseif (($book['stok_tersedia'] ?? 0) > 0) {
                                        $stock_class = 'limited';
                                    } else {
                                        $stock_class = 'empty';
                                    }
                                    ?>
                                    <span class="stock-badge <?= $stock_class ?>">
                                        <?= $book['stok_tersedia'] ?? 0 ?> buku
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-heart"></i> Total Dipinjam</td>
                                <td><?= $book['total_dipinjam'] ?> kali</td>
                            </tr>
                        </table>

                        <!-- Status Indicators -->
                        <div class="mt-4">
                            <div class="status-indicator available">
                                <div class="status-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="status-text">
                                    <div class="status-title">Stok Fisik Tersedia</div>
                                    <div class="status-desc"><?= $book['stok_tersedia'] ?? 0 ?> buku ada di rak</div>
                                </div>
                            </div>
                            
                            <?php if ($queue_info && $queue_info['queue_count'] > 0): ?>
                                <div class="status-indicator queue">
                                    <div class="status-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="status-text">
                                        <div class="status-title">Dalam Antrian Booking</div>
                                        <div class="status-desc"><?= $queue_info['queue_count'] ?> orang mengantri</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($hold_info): ?>
                                <div class="status-indicator hold">
                                    <div class="status-icon">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                    <div class="status-text">
                                        <div class="status-title">Sedang Ditahan</div>
                                        <div class="status-desc">Untuk: <?= htmlspecialchars($hold_info['nama']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <?php if ($already_borrowed): ?>
                                <div class="alert-custom info">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Anda sedang meminjam buku ini (jatuh tempo: <?= date('d/m/Y', strtotime("+{$max_pinjam_hari} days")) ?>)</span>
                                </div>
                            <?php elseif ($already_booked): ?>
                                <div class="booking-already">
                                    <i class="fas fa-check-circle"></i>
                                    Anda sudah melakukan booking untuk buku ini
                                    <?php if ($user_position > 0): ?>
                                        <div class="position-badge">
                                            Posisi ke-<?= $user_position ?> dalam antrian
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php if ($effective_stock >= 1 && !$hold_info): ?>
                                    <div class="alert-custom success">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Buku ini tersedia untuk dipinjam langsung.</span>
                                    </div>
                                <?php elseif ($book['stok_tersedia'] > 0): ?>
                                    <div class="alert-custom warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Stok terbatas karena ada antrian booking.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="alert-custom warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span>Stok buku ini habis. Silakan booking untuk mengantri.</span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <?php if (!$already_borrowed && !$already_booked && $booking_enabled): ?>
                            <div class="action-buttons">
                                <?php if ($effective_stock >= 1 && !$hold_info): ?>
                                    <button type="button" class="btn-borrow" onclick="showBorrowInfo()">
                                        <i class="fas fa-hand-holding me-1"></i> Pinjam Sekarang
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($can_book_more): ?>
                                    <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                                       class="btn-booking">
                                        <i class="fas fa-calendar-plus me-1"></i> Booking Buku
                                    </a>
                                <?php else: ?>
                                    <button class="btn-booking" disabled>
                                        <i class="fas fa-calendar-times me-1"></i> Booking Penuh
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="./" class="btn-back">
                                <i class="fas fa-arrow-left"></i>Kembali ke Katalog
                            </a>
                            <?php if (!$already_borrowed): ?>
                                <button type="button" class="btn-info" onclick="showContactInfo()">
                                    <i class="fas fa-info-circle"></i>Cara Meminjam
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hold Status Card -->
            <?php if ($hold_info): ?>
                <div class="hold-status-card">
                    <h6>
                        <i class="fas fa-user-clock"></i>
                        Status Hold Aktif
                    </h6>
                    
                    <div class="hold-info">
                        <div class="hold-user">
                            <i class="fas fa-user"></i>
                            Buku sedang ditahan untuk: <strong><?= htmlspecialchars($hold_info['nama']) ?></strong>
                        </div>
                        
                        <div class="hold-dates">
                            <div class="hold-date">
                                <div class="hold-date-label">Hold Mulai:</div>
                                <div class="hold-date-value"><?= date('d/m/Y', strtotime($hold_info['hold_start'])) ?></div>
                            </div>
                            <div class="hold-date">
                                <div class="hold-date-label">Hold Berakhir:</div>
                                <div class="hold-date-value"><?= date('d/m/Y', strtotime($hold_info['hold_until'])) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-muted mb-0 small">
                        <i class="fas fa-info-circle"></i>
                        Hold aktif selama <?= $hold_days ?> hari setelah buku tersedia. Buku tidak dapat dipinjam oleh orang lain selama dalam status hold.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Estimation Card -->
            <?php if ($estimated_available): ?>
                <div class="estimation-card">
                    <h6>
                        <i class="fas fa-calendar-alt"></i>
                        Estimasi Dapat Hold
                    </h6>
                    
                    <div class="estimation-date">
                        <div class="date"><?= date('d M Y', strtotime($estimated_available)) ?></div>
                        <div class="days">
                            <?php 
                            $days_diff = floor((strtotime($estimated_available) - time()) / (60 * 60 * 24));
                            if ($days_diff > 0) {
                                echo "{$days_diff} hari lagi";
                            } elseif ($days_diff == 0) {
                                echo "Hari ini";
                            } else {
                                echo "Segera";
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="estimation-details">
                        <p><strong>Sistem Hold:</strong></p>
                        <ul>
                            <li>Hold mulai <?= $hold_days ?> hari setelah buku tersedia</li>
                            <li>Berlaku selama <?= $hold_days ?> hari</li>
                            <li>Harus diambil sebelum hold berakhir</li>
                        </ul>
                        
                        <?php if ($user_position > 0): ?>
                            <p><strong>Posisi Anda:</strong> ke-<?= $user_position ?> dalam antrian</p>
                        <?php elseif ($queue_info && $queue_info['queue_count'] > 0): ?>
                            <p><strong>Jika booking sekarang:</strong> Posisi ke-<?= $queue_info['queue_count'] + 1 ?> dalam antrian</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Booking Queue Info -->
            <?php if ($queue_info && $queue_info['queue_count'] > 0): ?>
                <div class="queue-card">
                    <h6>
                        <i class="fas fa-users"></i>
                        Status Antrian Booking
                    </h6>
                    
                    <?php if ($user_position > 0): ?>
                        <div class="text-center mb-3">
                            <div class="position-badge">
                                Posisi ke-<?= $user_position ?>
                            </div>
                            <div class="position-label">Dalam antrian booking</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="queue-info">
                        <div class="queue-count">
                            <?= $queue_info['queue_count'] ?>
                        </div>
                        <div class="queue-label">
                            Orang dalam antrian booking
                        </div>
                    </div>
                    
                    <div class="queue-details">
                        <p>
                            <i class="fas fa-calendar-plus"></i>
                            Booking pertama: <?= date('d/m/Y', strtotime($queue_info['oldest_booking'])) ?>
                        </p>
                        <p>
                            <i class="fas fa-clock"></i>
                            Hold terbaru hingga: <?= date('d/m/Y', strtotime($queue_info['latest_expiry'])) ?>
                        </p>
                    </div>
                    
                    <?php if (!$already_booked && $can_book_more && $booking_enabled): ?>
                        <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                           class="btn-booking" style="width: 100%; margin-top: 1rem;">
                            <i class="fas fa-calendar-plus me-2"></i>Gabung Antrian Booking
                        </a>
                        <small class="text-muted d-block mt-2 text-center">
                            Hold berlaku <?= $hold_days ?> hari setelah giliran tiba<br>
                            Posisi dalam antrian: ke-<?= $queue_info['queue_count'] + 1 ?>
                        </small>
                    <?php endif; ?>
                </div>
            <?php elseif (!$already_booked && $booking_enabled && $can_book_more): ?>
                <!-- No queue yet, show booking button -->
                <div class="queue-card">
                    <h6>
                        <i class="fas fa-calendar-plus"></i>
                        Booking Buku Ini
                    </h6>
                    <p class="text-muted mb-3">Belum ada antrian booking untuk buku ini.</p>
                    <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                       class="btn-booking" style="width: 100%;">
                        <i class="fas fa-calendar-plus me-2"></i>Booking Sekarang
                    </a>
                    <small class="text-muted d-block mt-2 text-center">
                        Anda akan menjadi orang pertama dalam antrian<br>
                        Hold mulai <?= $hold_days ?> hari setelah buku tersedia
                    </small>
                </div>
            <?php endif; ?>

            <!-- Similar Books -->
            <?php if (!empty($similar)): ?>
                <div class="similar-card">
                    <h5>
                        <i class="fas fa-book-open"></i>
                        Buku Lain dari Pengarang yang Sama
                    </h5>
                    
                    <div class="row">
                        <?php foreach ($similar as $s): ?>
                            <div class="col-md-6 mb-2">
                                <div class="similar-book-item">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-book book-icon"></i>
                                        <div class="flex-grow-1">
                                            <h6>
                                                <a href="detail_buku.php?isbn=<?= urlencode($s['isbn']) ?>">
                                                    <?= htmlspecialchars($s['judul']) ?>
                                                </a>
                                            </h6>
                                            <div class="book-meta">
                                                <?= !empty($s['penerbit']) ? htmlspecialchars($s['penerbit']) : 'Tidak tersedia' ?> (<?= $s['tahun_terbit'] ?>)
                                            </div>
                                            <?php 
                                            if (($s['stok_tersedia'] ?? 0) >= 3) {
                                                $similar_stock_class = '';
                                            } elseif (($s['stok_tersedia'] ?? 0) > 0) {
                                                $similar_stock_class = 'limited';
                                            } else {
                                                $similar_stock_class = 'empty';
                                            }
                                            ?>
                                            <span class="similar-stock-badge <?= $similar_stock_class ?>">
                                                Stok: <?= $s['stok_tersedia'] ?? 0 ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="stats-card">
                <h6>
                    <i class="fas fa-chart-bar"></i>
                    Statistik Buku
                </h6>
                
                <div class="row g-2">
                    <div class="col-6">
                        <div class="stat-item">
                            <div class="stat-number"><?= $book['stok_tersedia'] ?? 0 ?></div>
                            <div class="stat-label">Stok Tersedia</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-item">
                            <div class="stat-number"><?= $book['sedang_dipinjam'] ?></div>
                            <div class="stat-label">Sedang Dipinjam</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="stat-item">
                            <div class="stat-number"><?= $book['total_dipinjam'] ?></div>
                            <div class="stat-label">Total Peminjaman</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="stat-item">
                            <div class="stat-number"><?= $queue_info['queue_count'] ?? 0 ?></div>
                            <div class="stat-label">Dalam Antrian Booking</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking & Contact Info -->
            <div class="info-card">
                <h6>
                    <i class="fas fa-info-circle"></i>
                    Informasi Booking & Peminjaman
                </h6>
                
                <div class="info-section-title">Sistem Hold:</div>
                <ol>
                    <li><strong>Hold mulai <?= $hold_days ?> hari setelah buku tersedia</strong></li>
                    <li><strong>Buku direservasi khusus untuk Anda</strong></li>
                    <li><strong>Berlaku selama <?= $hold_days ?> hari</strong></li>
                    <li><strong>Tidak bisa dipinjam orang lain selama hold</strong></li>
                </ol>

                <div class="info-section-title mt-3">Keuntungan Booking:</div>
                <ol>
                    <li><strong>Garansi dapat buku</strong> - Buku direservasi untuk Anda</li>
                    <li><strong>Prioritas antrian</strong> - Dilayani sesuai urutan booking</li>
                    <li><strong>Notifikasi</strong> - Dapat pemberitahuan ketika hold aktif</li>
                    <li><strong>Hold <?= $hold_days ?> hari</strong> setelah giliran Anda</li>
                </ol>

                <div class="info-section-title mt-3">Cara Meminjam Langsung:</div>
                <ol>
                    <li>Datang ke perpustakaan</li>
                    <li>Tunjukkan kartu anggota</li>
                    <li>Sebutkan ISBN: <strong><?= htmlspecialchars($book['isbn']) ?></strong></li>
                    <li>Pastikan tidak ada hold aktif</li>
                    <li>Maksimal <?= $max_pinjam_hari ?> hari peminjaman</li>
                </ol>

                <div class="info-section-title mt-3">Ketentuan Booking:</div>
                <ol>
                    <li>Maksimal <?= $max_books ?> booking aktif per anggota</li>
                    <li>Hold berlaku <?= $hold_days ?> hari setelah giliran</li>
                    <li>Jika tidak diambil, booking hangus</li>
                    <li>Status booking bisa dicek di menu Riwayat</li>
                </ol>

                <hr style="border-color: #e0e0e0; margin: 1rem 0;">

                <div class="info-section-title">Kontak Perpustakaan:</div>
                <div class="contact-info">
                    <p>
                        <i class="fas fa-phone"></i>
                        <span>Telepon: (031) 1234567</span>
                    </p>
                    <p>
                        <i class="fas fa-envelope"></i>
                        <span>Email: info@perpustakaan.com</span>
                    </p>
                    <p>
                        <i class="fas fa-clock"></i>
                        <span>Senin-Jumat: 08.00-16.00</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cara Meminjam -->
<div class="modal fade" id="borrowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-hand-holding me-2"></i>
                    Cara Meminjam Buku
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: white;"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-book-reader fa-3x" style="color: #667eea;"></i>
                </div>
                
                <h6 class="mb-3" style="color: #667eea; font-weight: 600;">Untuk meminjam buku "<strong><?= htmlspecialchars($book['judul']) ?></strong>":</h6>
                
                <?php if ($effective_stock >= 1 && !$hold_info): ?>
                    <div class="alert alert-success mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Bisa pinjam langsung!</strong> Stok tersedia dan tidak ada hold aktif.
                    </div>
                    
                    <ol class="mb-3">
                        <li>Datang ke perpustakaan sekarang juga</li>
                        <li>Tunjukkan kartu anggota</li>
                        <li>Sebutkan ISBN: <code><?= htmlspecialchars($book['isbn']) ?></code></li>
                        <li>Petugas akan memproses peminjaman</li>
                        <li>Masa pinjam: <strong><?= $max_pinjam_hari ?> hari</strong></li>
                    </ol>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Tidak bisa pinjam langsung</strong> karena:
                        <?php if ($hold_info): ?>
                            <br>• Buku sedang dalam status hold
                        <?php endif; ?>
                        <?php if ($effective_stock < 1): ?>
                            <br>• Stok sudah dipesan oleh antrian booking
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Saran:</strong> Lakukan booking untuk mengamankan posisi dalam antrian.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none;">
                    <i class="fas fa-check me-2"></i>Mengerti
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Booking Info -->
<div class="modal fade" id="bookingInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff9a9e, #fad0c4); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus me-2"></i>
                    Keuntungan Booking
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="color: white;"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="fas fa-calendar-check fa-3x" style="color: #ff9a9e;"></i>
                </div>
                
                <h6 class="mb-3" style="color: #ff6b6b; font-weight: 600;">Mengapa booking lebih baik?</h6>
                <ul class="mb-3">
                    <li><strong>Garansi antrian</strong> - Posisi Anda diamankan</li>
                    <li><strong>Hold <?= $hold_days ?> hari setelah tersedia</strong></li>
                    <li><strong>Notifikasi otomatis</strong> - Dapat pemberitahuan</li>
                    <li><strong>Prioritas</strong> - Dilayani sesuai urutan booking</li>
                </ul>

                <?php if ($queue_info && $queue_info['queue_count'] > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-users me-2"></i>
                        <strong>Antrian saat ini:</strong> <?= $queue_info['queue_count'] ?> orang<br>
                        <strong>Posisi Anda nanti:</strong> ke-<?= $queue_info['queue_count'] + 1 ?><br>
                        <strong>Hold mulai:</strong> <?= $hold_days ?> hari setelah giliran
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-crown me-2"></i>
                        <strong>Anda akan menjadi orang pertama</strong> dalam antrian booking!
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Perhatian:</strong> Hold akan hangus jika tidak diambil dalam <?= $hold_days ?> hari
                </div>
            </div>
            <div class="modal-footer">
                <a href="../booking/process.php?isbn=<?= urlencode($book['isbn']) ?>&title=<?= urlencode($book['judul']) ?>" 
                   class="btn" style="background: linear-gradient(135deg, #ff9a9e, #fad0c4); color: white; border: none;">
                    <i class="fas fa-calendar-plus me-2"></i>Booking Sekarang
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Nanti Saja
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showContactInfo() {
    var borrowModal = new bootstrap.Modal(document.getElementById('borrowModal'));
    borrowModal.show();
}

function showBorrowInfo() {
    // Tampilkan modal info booking
    var bookingModal = new bootstrap.Modal(document.getElementById('bookingInfoModal'));
    bookingModal.show();
}

// Jika stok terbatas, tampilkan saran booking
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($effective_stock < $book['stok_tersedia'] && !$already_booked && !$already_borrowed): ?>
        // Tampilkan toast suggestion
        setTimeout(function() {
            const toastEl = document.createElement('div');
            toastEl.innerHTML = `
                <div class="toast-container position-fixed bottom-0 end-0 p-3">
                    <div class="toast show" role="alert">
                        <div class="toast-header" style="background: linear-gradient(135deg, #ffa62e, #ffd89b); color: #333;">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong class="me-auto">Saran</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Stok buku ini sudah dipesan oleh <?= $queue_info['queue_count'] ?? 0 ?> orang. 
                            <strong>Booking sekarang</strong> untuk mengamankan posisi!
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(toastEl);
            
            // Auto remove after 10 seconds
            setTimeout(() => {
                toastEl.remove();
            }, 10000);
        }, 2000);
    <?php endif; ?>
});
</script>

<?php include '../../includes/footer.php'; ?>