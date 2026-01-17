<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Booking Buku Baru';
$body_class = 'anggota-booking-process';
include '../../config/database.php';

// ========== GET NIK FROM DATABASE ==========
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nik FROM anggota WHERE user_id = ?");
$stmt->execute([$user_id]);
$anggota = $stmt->fetch();

if (!$anggota || !isset($anggota['nik'])) {
    setFlashMessage('Data anggota tidak ditemukan. Silakan hubungi admin.', 'danger');
    redirect('../dashboard.php');
}

$nik = $anggota['nik'];

// ========== GET USER INFO ==========
$stmt = $conn->prepare("SELECT nama FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$nama_anggota = $user['nama'] ?? 'Anggota';

// ========== CREATE NEW BOOKING ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_booking'])) {
    $isbn = trim($_POST['isbn']);
    $tanggal_booking = date('Y-m-d');
    
    try {
        // Validate inputs
        if (empty($isbn)) {
            throw new Exception('ISBN buku harus diisi');
        }
        
        // Check if book exists
        $stmt = $conn->prepare("
            SELECT judul, stok_total, stok_tersedia, status 
            FROM buku 
            WHERE isbn = ?
        ");
        $stmt->execute([$isbn]);
        $book = $stmt->fetch();
        
        if (!$book) {
            throw new Exception('Buku tidak ditemukan');
        }
        
        if ($book['status'] != 'tersedia') {
            throw new Exception('Buku tidak dapat dipinjam saat ini');
        }
        
        // Check if member already has active booking for this book
        $stmt = $conn->prepare("
            SELECT id_booking FROM booking 
            WHERE nik = ? AND isbn = ? AND status = 'menunggu'
            AND expired_at >= CURDATE()
        ");
        $stmt->execute([$nik, $isbn]);
        if ($stmt->fetch()) {
            throw new Exception('Anda sudah memiliki booking aktif untuk buku ini');
        }
        
        // Check if member has reached maximum active bookings
        $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'");
        $max_books = (int)($stmt->fetchColumn() ?: 3);
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM booking 
            WHERE nik = ? AND status = 'menunggu'
            AND expired_at >= CURDATE()
        ");
        $stmt->execute([$nik]);
        $active_bookings = $stmt->fetchColumn();
        
        if ($active_bookings >= $max_books) {
            throw new Exception("Anda sudah mencapai batas maksimal booking aktif ($max_books buku)");
        }
        
        // Get current queue for this book - PERBAIKAN: pastikan integer
        $stmt = $conn->prepare("
            SELECT COUNT(*) as queue_count 
            FROM booking 
            WHERE isbn = ? AND status = 'menunggu'
            AND expired_at >= CURDATE()
        ");
        $stmt->execute([$isbn]);
        $queue_info = $stmt->fetch();
        $current_queue = (int)($queue_info['queue_count'] ?? 0); // PERBAIKAN: cast ke integer
        $queue_position = $current_queue + 1;
        
        // Calculate estimation based on current situation
        $estimation = calculateBookEstimation($isbn, $queue_position, $book['stok_tersedia'], $current_queue);
        
        // Get booking settings
        $stmt = $conn->query("
            SELECT setting_key, setting_value FROM pengaturan 
            WHERE setting_key IN ('enable_booking', 'booking_expire_days')
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if ($settings['enable_booking'] != '1') {
            throw new Exception('Fitur booking sedang dinonaktifkan');
        }
        
        $expire_days = (int)($settings['booking_expire_days'] ?? 2);
        $expired_at = date('Y-m-d', strtotime("+{$expire_days} days"));
        
        // Create booking
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            INSERT INTO booking (nik, isbn, tanggal_booking, status, expired_at, created_at) 
            VALUES (?, ?, ?, 'menunggu', ?, NOW())
        ");
        $stmt->execute([$nik, $isbn, $tanggal_booking, $expired_at]);
        $booking_id = $conn->lastInsertId();
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'CREATE_BOOKING',
            "Booking baru dibuat: #{$booking_id} untuk buku '{$book['judul']}' - Posisi: {$queue_position}",
            'booking',
            $booking_id
        );
        
        // Prepare success message with estimation - VERSI LEBIH RAPI
        $success_message = "✅ Booking berhasil dibuat!|";
        $success_message .= "📚 Buku: {$book['judul']}|";
        $success_message .= "🔖 Kode Booking: #{$booking_id}|";
        $success_message .= "👥 Posisi Antrian: ke-{$queue_position}";

        if ($estimation['type'] == 'available') {
            $success_message .= "|⚡ Status: Buku tersedia, bisa langsung ambil!";
        } else {
            $success_message .= "|⏰ Estimasi: {$estimation['date']}";
            $success_message .= "|📝 Catatan: {$estimation['note']}";
        }

        $success_message .= "|⏳ Masa Berlaku: {$expire_days} hari (saat giliran Anda)";
        $success_message .= "|💡 Tip: Cek status di menu 'Booking Aktif Saya'";

        setFlashMessage($success_message, 'success');
        redirect('index.php');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'danger');
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Helper function to calculate estimation dengan sistem hold 2 hari setelah buku tersedia
function calculateBookEstimation($isbn, $queue_position, $current_stock, $current_queue) {
    global $conn;
    
    $estimation = array(
        'type' => 'queue',
        'date' => 'Belum dapat diprediksi',
        'note' => 'Menunggu buku dikembalikan'
    );
    
    // Get booking expire days setting
    $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'booking_expire_days'");
    $hold_days = (int)($stmt->fetchColumn() ?: 2);
    
    // PERBAIKAN: Pastikan semua parameter integer
    $current_queue = (int)$current_queue;
    $current_stock = (int)$current_stock;
    $queue_position = (int)$queue_position;
    
    // Calculate effective stock (stok yang benar-benar bisa langsung dipinjam)
    $effective_stock = $current_stock - $current_queue;
    
    if ($effective_stock >= 1 && $queue_position == 1) {
        // First in queue with available stock - bisa dapat hold langsung
        $estimation['type'] = 'available';
        $estimation['date'] = 'SEGERA';
        $estimation['note'] = 'Bisa langsung mendapatkan hold setelah booking';
    } elseif ($effective_stock >= $queue_position) {
        // Enough stock for this position - estimasi berdasarkan orang di depan
        $days_per_position = 7; // 7 days per person (maksimal masa pinjam)
        $days_to_wait = ($queue_position - 1) * $days_per_position;
        $est_date = date('d/m/Y', strtotime("+{$days_to_wait} days"));
        
        $estimation['type'] = 'queue';
        $estimation['date'] = $est_date;
        $estimation['note'] = "Estimasi " . ($queue_position-1) . " orang di depan (maks 7 hari/orang)";
    } else {
        // Not enough stock, need to wait for returns
        // Get books that will be returned soon
        $stmt = $conn->prepare("
            SELECT tanggal_kembali, 
                   DATEDIFF(tanggal_kembali, CURDATE()) as days_left
            FROM peminjaman 
            WHERE isbn = ? AND status = 'dipinjam'
            AND tanggal_kembali >= CURDATE()
            ORDER BY tanggal_kembali ASC
            LIMIT 5
        ");
        $stmt->execute([$isbn]);
        $returning_books = $stmt->fetchAll();
        
        if (!empty($returning_books)) {
            // Calculate how many books needed for this position
            $books_needed = $queue_position - $effective_stock;
            $available_returns = min(count($returning_books), $books_needed);
            
            if ($available_returns >= $books_needed) {
                // Ada cukup buku yang akan dikembalikan
                $last_return_date = $returning_books[$books_needed - 1]['tanggal_kembali'];
                
                // Hold mulai dihitung 2 hari SETELAH buku dikembalikan
                $hold_start_date = date('Y-m-d', strtotime($last_return_date . " + {$hold_days} days"));
                $est_date = date('d/m/Y', strtotime($hold_start_date));
                
                $estimation['type'] = 'wait_return';
                $estimation['date'] = $est_date;
                $estimation['note'] = "Setelah " . $books_needed . " buku dikembalikan";
                
                // Add details about return dates
                if ($books_needed == 1) {
                    $estimation['note'] .= " (tanggal pengembalian: " . date('d/m/Y', strtotime($last_return_date)) . ")";
                }
            } else {
                // Tidak cukup buku yang dipinjam untuk memenuhi antrian
                $earliest_return = $returning_books[0]['tanggal_kembali'];
                $waiting_books = $books_needed - $available_returns;
                $extra_days = $waiting_books * 14; // 14 days per extra book (2x masa pinjam)
                
                $hold_start_date = date('Y-m-d', strtotime($earliest_return . " + {$hold_days} + {$extra_days} days"));
                $est_date = date('d/m/Y', strtotime($hold_start_date));
                
                $estimation['type'] = 'wait_long';
                $estimation['date'] = $est_date;
                $estimation['note'] = "Menunggu " . $books_needed . " buku tersedia";
            }
        } else {
            // No books currently borrowed (semua hilang atau baru)
            // Check if there are any books at all
            $stmt = $conn->prepare("
                SELECT COUNT(*) as borrowed_count 
                FROM peminjaman 
                WHERE isbn = ? AND status = 'dipinjam'
            ");
            $stmt->execute([$isbn]);
            $borrowed_count = $stmt->fetchColumn();
            
            if ($borrowed_count > 0) {
                // Ada buku dipinjam tapi tidak ada tanggal kembali valid
                $estimation['type'] = 'uncertain';
                $estimation['date'] = 'Perlu Konfirmasi';
                $estimation['note'] = "Ada " . $borrowed_count . " buku dipinjam tanpa tanggal kembali pasti";
            } else {
                // Tidak ada buku yang dipinjam sama sekali
                $weeks_to_wait = ceil($queue_position / 2); // Assume 2 books available per week
                $hold_start_date = date('Y-m-d', strtotime("+{$weeks_to_wait} weeks + {$hold_days} days"));
                $est_date = date('d/m/Y', strtotime($hold_start_date));
                
                $estimation['type'] = 'unknown';
                $estimation['date'] = $est_date;
                $estimation['note'] = "Tergantung ketersediaan buku baru";
            }
        }
    }
    
    return $estimation;
}

// Get ALL books for search (including zero stock)
$search_query = "";
$books = array();
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query = "
        SELECT b.isbn, b.judul, b.pengarang, b.tahun_terbit, b.stok_tersedia,
               (SELECT COUNT(*) FROM booking bk WHERE bk.isbn = b.isbn AND bk.status = 'menunggu' AND bk.expired_at >= CURDATE()) as queue_count,
               (SELECT MIN(tanggal_kembali) FROM peminjaman p WHERE p.isbn = b.isbn AND p.status = 'dipinjam' AND p.tanggal_kembali >= CURDATE()) as next_return_date
        FROM buku b
        WHERE (b.isbn LIKE ? OR b.judul LIKE ? OR b.pengarang LIKE ?) 
        AND b.status = 'tersedia'
        ORDER BY b.judul
        LIMIT 20
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$search, $search, $search]);
    $books = $stmt->fetchAll();
    $search_query = $_GET['search'];
}

// Get current member's booking statistics
$active_bookings = 0;
$max_books = 3;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM booking 
        WHERE nik = ? AND status = 'menunggu'
        AND expired_at >= CURDATE()
    ");
    $stmt->execute([$nik]);
    $active_bookings = $stmt->fetchColumn();
    
    $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'");
    $result = $stmt->fetchColumn();
    if ($result) {
        $max_books = (int)$result;
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// Get booking settings
$settings = array(
    'enable_booking' => '1',
    'booking_expire_days' => '2',
    'max_booking_days' => '3'
);

try {
    $stmt = $conn->query("
        SELECT setting_key, setting_value FROM pengaturan 
        WHERE setting_key IN ('enable_booking', 'booking_expire_days', 'max_booking_days')
    ");
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = array_merge($settings, $results);
} catch (Exception $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

include '../../includes/header.php';
?>

<style>
/* CSS TETAP SAMA SEPERTI ASLINYA - TIDAK DIUBAH */
body.anggota-booking-process {
    background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

body.anggota-booking-process::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
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

.info-cards {
    margin-bottom: 2rem;
}

.info-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    padding: 1.5rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    height: 100%;
    text-align: center;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.info-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.9;
    color: white;
}

.info-content {
    color: white;
}

.info-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
}

.info-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.5rem;
}

.info-subtitle {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
}

.search-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.search-box {
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.search-box .input-group-text {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    color: white;
    padding: 1rem;
}

.search-box .form-control {
    border: none;
    padding: 1rem;
    font-size: 1rem;
}

.search-box .form-control:focus {
    box-shadow: none;
    border-color: #667eea;
}

.btn-search {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 1rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-search:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.books-list {
    max-height: 400px;
    overflow-y: auto;
    border-radius: 10px;
    border: 2px solid #e0e0e0;
}

.book-item {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    transition: all 0.3s ease;
    cursor: pointer;
}

.book-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.book-item:last-child {
    border-bottom: none;
}

.book-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.25rem;
}

.book-author {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.book-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.book-isbn {
    color: #888;
    font-size: 0.85rem;
    font-family: monospace;
}

.book-stock {
    color: #28a745;
    font-weight: 600;
}

.btn-select {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-select:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.form-section {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control-custom {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    width: 100%;
    transition: all 0.3s ease;
}

.form-control-custom:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.book-selected {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 2px solid #28a745;
}

.book-selected h6 {
    color: #155724;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.book-selected p {
    color: #0c5460;
    margin-bottom: 0.25rem;
}

.info-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 2px solid #2196f3;
}

.info-box h6 {
    color: #0d47a1;
    font-weight: 600;
    margin-bottom: 1rem;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    margin-bottom: 0.5rem;
    color: #1565c0;
}

.info-list li i {
    margin-right: 0.5rem;
    width: 20px;
}

.btn-submit {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1.1rem;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    border: 2px dashed rgba(255, 255, 255, 0.3);
}

.empty-state i {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
}

.empty-state h5 {
    color: white;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.7);
}

/* Tambahan untuk status stok */
.stock-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
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

.stock-queue {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: white;
}

/* Estimation styles */
.estimation-badge {
    background: linear-gradient(135deg, #ffd89b, #19547b);
    color: #333;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: 0.5rem;
}

.estimation-date {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 1rem;
    margin: 1rem 0;
    border-left: 4px solid #6a11cb;
}

.estimation-date h6 {
    color: #6a11cb;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.estimated-date {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
    text-align: center;
    margin: 0.5rem 0;
}

.estimation-note {
    font-size: 0.85rem;
    color: #666;
    text-align: center;
}

/* Warning for zero stock */
.zero-stock-warning {
    background: linear-gradient(135deg, #ffeaa7, #fab1a0);
    border-radius: 10px;
    padding: 1rem;
    margin: 1rem 0;
    border: 2px dashed #e17055;
}

.zero-stock-warning h6 {
    color: #d63031;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* Queue position display */
.queue-position-display {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    color: white;
    margin: 1rem 0;
}

.queue-position-number {
    font-size: 4rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.queue-position-label {
    font-size: 1.2rem;
    opacity: 0.9;
}

.queue-details {
    list-style: none;
    padding: 0;
    margin: 0;
}

.queue-details li {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
}

.queue-details li i {
    font-size: 1.2rem;
    margin-right: 1rem;
    margin-top: 0.25rem;
    color: #667eea;
}

.queue-details li div {
    flex: 1;
}

.queue-details li strong {
    color: #333;
}

.queue-details li small {
    color: #666;
    font-size: 0.85rem;
}

.queue-estimation {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    padding: 1.5rem;
    border: 2px solid #dee2e6;
}

.return-date-info {
    background: linear-gradient(135deg, #e0f7fa, #b2ebf2);
    border-radius: 10px;
    padding: 1rem;
    margin: 1rem 0;
    border-left: 4px solid #00bcd4;
}

.return-date-info h6 {
    color: #006064;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.return-date-info h6 i {
    color: #00bcd4;
}

.hold-explanation {
    background: linear-gradient(135deg, #fff3e0, #ffcc80);
    border-radius: 10px;
    padding: 1rem;
    margin: 1rem 0;
    border-left: 4px solid #ff9800;
}

.hold-explanation h6 {
    color: #e65100;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hold-explanation h6 i {
    color: #ff9800;
}

.hold-explanation ol {
    margin-bottom: 0;
    padding-left: 1.2rem;
}

.hold-explanation li {
    margin-bottom: 0.3rem;
    color: #555;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .header-section { padding: 1.5rem; }
    .info-card { margin-bottom: 1rem; }
    .books-list { max-height: 300px; }
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
                <i class="fas fa-plus"></i> Booking Baru
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-book-medical"></i> Booking Buku Baru</h1>
                <p>Pesan buku yang ingin Anda pinjam - Termasuk buku stok habis</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="index.php" class="btn btn-light px-4 py-2 rounded-pill">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?php echo $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                <div><?php echo $flash['message']; ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Info Cards -->
    <div class="row info-cards">
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Anggota</div>
                    <div class="info-value"><?php echo htmlspecialchars($nama_anggota); ?></div>
                    <div class="info-subtitle">NIK: <?php echo htmlspecialchars($nik); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Booking Aktif</div>
                    <div class="info-value"><?php echo $active_bookings; ?>/<?php echo $max_books; ?></div>
                    <div class="info-subtitle">Maksimal <?php echo $max_books; ?> buku</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="info-content">
                    <div class="info-title">Masa Hold</div>
                    <div class="info-value"><?php echo $settings['booking_expire_days']; ?> hari</div>
                    <div class="info-subtitle">Setelah giliran tiba</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Book Search -->
        <div class="col-lg-6 mb-4">
            <div class="search-section">
                <h5 class="mb-3">
                    <i class="fas fa-search me-2"></i>Cari Buku untuk Booking
                    <small class="text-muted">(Termasuk stok habis)</small>
                </h5>
                
                <form method="GET" class="mb-4">
                    <div class="search-box input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Cari buku (judul, pengarang, ISBN)..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search me-1"></i> Cari
                        </button>
                    </div>
                </form>
                
                <?php if ($search_query && empty($books)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h5>Tidak ditemukan</h5>
                        <p>Tidak ada buku yang cocok dengan "<?php echo htmlspecialchars($search_query); ?>"</p>
                    </div>
                <?php elseif (!empty($books)): ?>
                    <div class="books-list">
                        <?php foreach ($books as $book): 
                            // PERBAIKAN: Pastikan integer untuk queue_count
                            $queue_count = isset($book['queue_count']) ? (int)$book['queue_count'] : 0;
                            $stok_tersedia = isset($book['stok_tersedia']) ? (int)$book['stok_tersedia'] : 0;
                            $effective_stock = $stok_tersedia - $queue_count;
                            $next_return = $book['next_return_date'] ?? null;
                            
                            // Determine stock status
                            if ($stok_tersedia == 0) {
                                $stock_class = 'stock-unavailable';
                                $stock_text = 'Stok Habis';
                                $stock_icon = 'fa-times';
                            } elseif ($effective_stock >= 1) {
                                $stock_class = 'stock-available';
                                $stock_text = "Stok: " . $stok_tersedia;
                                $stock_icon = 'fa-check';
                            } else {
                                $stock_class = 'stock-queue';
                                $stock_text = "Antrian: " . $queue_count;
                                $stock_icon = 'fa-users';
                            }
                        ?>
                            <div class="book-item" onclick="selectBook(
                                '<?php echo htmlspecialchars($book['isbn']); ?>', 
                                '<?php echo htmlspecialchars($book['judul']); ?>', 
                                '<?php echo htmlspecialchars($book['pengarang']); ?>', 
                                '<?php echo $stok_tersedia; ?>', 
                                '<?php echo $queue_count; ?>',
                                '<?php echo $next_return ? date('Y-m-d', strtotime($next_return)) : ''; ?>'
                            )">
                                <div class="book-title"><?php echo htmlspecialchars($book['judul']); ?></div>
                                <div class="book-author"><?php echo htmlspecialchars($book['pengarang']); ?> (<?php echo $book['tahun_terbit']; ?>)</div>
                                <div class="book-meta">
                                    <div class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></div>
                                    <div class="book-stock">
                                        <span class="stock-status <?php echo $stock_class; ?>">
                                            <i class="fas <?php echo $stock_icon; ?>"></i>
                                            <?php echo $stock_text; ?>
                                        </span>
                                        <?php if ($queue_count > 0): ?>
                                            <span class="estimation-badge">
                                                <i class="fas fa-clock"></i>
                                                <?php echo $queue_count; ?> antri
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($next_return && $stok_tersedia == 0): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-calendar-day"></i> 
                                        Pengembalian: <?php echo date('d/m/Y', strtotime($next_return)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-end mt-2">
                                    <button type="button" class="btn-select" 
                                            onclick="selectBook(
                                                '<?php echo htmlspecialchars($book['isbn']); ?>', 
                                                '<?php echo htmlspecialchars($book['judul']); ?>', 
                                                '<?php echo htmlspecialchars($book['pengarang']); ?>', 
                                                '<?php echo $stok_tersedia; ?>', 
                                                '<?php echo $queue_count; ?>',
                                                '<?php echo $next_return ? date('Y-m-d', strtotime($next_return)) : ''; ?>'
                                            )">
                                        <i class="fas fa-plus me-1"></i> Pilih
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h5>Cari Buku untuk Booking</h5>
                        <p>Masukkan judul, pengarang, atau ISBN buku yang ingin Anda booking</p>
                        <p class="small text-muted">Termasuk buku yang stoknya habis</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Booking Form -->
        <div class="col-lg-6 mb-4">
            <div class="form-section">
                <h5 class="mb-4">
                    <i class="fas fa-calendar-plus me-2"></i>Form Booking Buku
                    <small class="text-muted" id="selectedBookInfo"></small>
                </h5>
                
                <form method="POST" id="bookingForm" onsubmit="return validateForm()">
                    <input type="hidden" id="isbnInput" name="isbn" value="">
                    <input type="hidden" id="nextReturnDate" value="">
                    
                    <!-- Book Selection Info -->
                    <div id="noBookSelected" class="text-center py-5">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Belum ada buku dipilih</h6>
                        <p class="small">Pilih buku dari daftar di samping untuk memulai booking</p>
                    </div>
                    
                    <div id="selectedBookContainer" style="display: none;">
                        <!-- Book Info -->
                        <div class="book-selected mb-3">
                            <h6 id="selectedBookTitle">Judul Buku</h6>
                            <p id="selectedBookAuthor">Pengarang</p>
                            <p><i class="fas fa-barcode"></i> ISBN: <span id="selectedBookISBN"></span></p>
                            <p><i class="fas fa-box"></i> Stok: <span id="selectedBookStock">0</span> buku</p>
                        </div>
                        
                        <!-- Queue Position -->
                        <div class="queue-position-display" id="queuePositionDisplay">
                            <div class="queue-position-number" id="queuePositionNumber">1</div>
                            <div class="queue-position-label" id="queuePositionLabel">Posisi dalam antrian</div>
                        </div>
                        
                        <!-- Return Date Info -->
                        <div class="return-date-info" id="returnDateInfo" style="display: none;">
                            <h6><i class="fas fa-calendar-day me-2"></i>Informasi Pengembalian</h6>
                            <p>Tanggal pengembalian terdekat: <strong id="nextReturnDateText">-</strong></p>
                            <p class="small mb-0">Hold akan dimulai <?php echo $settings['booking_expire_days']; ?> hari setelah tanggal ini</p>
                        </div>
                        
                        <!-- Estimation -->
                        <div class="estimation-date" id="estimationDate">
                            <h6><i class="fas fa-hourglass-half me-2"></i>Estimasi Dapat Hold</h6>
                            <div class="estimated-date" id="estimatedDateText">-</div>
                            <div class="estimation-note" id="estimationNoteText">Menunggu perhitungan...</div>
                        </div>
                        
                        <!-- Hold Explanation -->
                        <div class="hold-explanation">
                            <h6><i class="fas fa-info-circle me-2"></i>Sistem Hold Perpustakaan</h6>
                            <ol>
                                <li>Hold mulai dihitung <strong><?php echo $settings['booking_expire_days']; ?> hari setelah buku tersedia</strong></li>
                                <li>Buku dianggap "tersedia" saat dikembalikan ke perpustakaan</li>
                                <li>Anda punya <?php echo $settings['booking_expire_days']; ?> hari untuk mengambil buku setelah hold aktif</li>
                                <li>Jika tidak diambil dalam <?php echo $settings['booking_expire_days']; ?> hari, hold hangus dan antrian berlanjut</li>
                            </ol>
                        </div>
                        
                        <!-- Warning for zero stock -->
                        <div class="zero-stock-warning" id="zeroStockWarning" style="display: none;">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian</h6>
                            <p>Buku ini stok habis. Booking akan masuk antrian dan Anda akan mendapatkan hold saat:</p>
                            <ul class="mb-0">
                                <li>Buku dikembalikan oleh peminjam sebelumnya</li>
                                <li>Anda mencapai posisi pertama dalam antrian</li>
                                <li>Masa hold <?php echo $settings['booking_expire_days']; ?> hari aktif untuk Anda</li>
                            </ul>
                        </div>
                        
                        <!-- Booking Info -->
                        <div class="queue-estimation">
                            <h6><i class="fas fa-info-circle me-2"></i>Informasi Booking</h6>
                            <ul class="queue-details">
                                <li>
                                    <i class="fas fa-calendar-check"></i>
                                    <div>
                                        <strong>Tanggal booking:</strong> <?php echo date('d/m/Y'); ?><br>
                                        <small>Hold berlaku <?php echo $settings['booking_expire_days']; ?> hari saat giliran Anda</small>
                                    </div>
                                </li>
                                <li>
                                    <i class="fas fa-bell"></i>
                                    <div>
                                        <strong>Notifikasi otomatis</strong><br>
                                        <small>Anda akan mendapat pemberitahuan saat giliran tiba</small>
                                    </div>
                                </li>
                                <li>
                                    <i class="fas fa-redo"></i>
                                    <div>
                                        <strong>Jika tidak diambil:</strong><br>
                                        <small>Hold hangus dan antrian berlanjut ke orang berikutnya</small>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" name="create_booking" id="submitBtn" class="btn-submit" disabled>
                        <i class="fas fa-calendar-plus me-2"></i>Buat Booking Sekarang
                    </button>
                </form>
            </div>

            <!-- Guide -->
            <div class="queue-estimation mt-4">
                <h6><i class="fas fa-question-circle me-2"></i>Mengapa Booking Buku Stok Habis?</h6>
                <ul class="queue-details">
                    <li>
                        <i class="fas fa-users"></i>
                        <div>
                            <strong>Amankan Posisi</strong><br>
                            Masuk antrian sebelum orang lain
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Hold Setelah Tersedia</strong><br>
                            Buku direservasi <?php echo $settings['booking_expire_days']; ?> hari setelah dikembalikan
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-bell"></i>
                        <div>
                            <strong>Notifikasi Otomatis</strong><br>
                            Dapat pemberitahuan saat hold aktif untuk Anda
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-calendar-alt"></i>
                        <div>
                            <strong>Prioritas Antrian</strong><br>
                            Dilayani sesuai urutan booking (FIFO)
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
let selectedBookData = null;
const holdDays = <?php echo $settings['booking_expire_days']; ?>;

function selectBook(isbn, title, author, stock, queueCount, nextReturnDate) {
    // PERBAIKAN UTAMA: Convert semua parameter ke number dengan parseInt
    const stockNum = parseInt(stock) || 0;
    const queueCountNum = parseInt(queueCount) || 0; // FIXED BUG
    
    selectedBookData = {
        isbn: isbn,
        title: title,
        author: author,
        stock: stockNum,
        queueCount: queueCountNum, // Menggunakan nilai yang sudah di-convert
        nextReturnDate: nextReturnDate
    };
    
    // Update form
    document.getElementById('isbnInput').value = isbn;
    document.getElementById('selectedBookTitle').textContent = title;
    document.getElementById('selectedBookAuthor').textContent = author;
    document.getElementById('selectedBookISBN').textContent = isbn;
    document.getElementById('selectedBookStock').textContent = stockNum;
    document.getElementById('nextReturnDate').value = nextReturnDate;
    
    // PERBAIKAN: Hitung queue position dengan number yang benar
    const queuePosition = queueCountNum + 1;
    document.getElementById('queuePositionNumber').textContent = queuePosition;
    
    if (queuePosition === 1) {
        document.getElementById('queuePositionLabel').textContent = 'POSISI PERTAMA!';
    } else {
        document.getElementById('queuePositionLabel').textContent = 'Posisi dalam antrian (' + queueCountNum + ' orang di depan)';
    }
    
    // Calculate estimation dengan parameter yang sudah di-convert
    calculateEstimation(stockNum, queueCountNum, queuePosition, nextReturnDate);
    
    // Show/hide zero stock warning and return date info
    if (stockNum == 0) {
        document.getElementById('zeroStockWarning').style.display = 'block';
        if (nextReturnDate) {
            const returnDate = new Date(nextReturnDate);
            document.getElementById('nextReturnDateText').textContent = formatDate(returnDate);
            document.getElementById('returnDateInfo').style.display = 'block';
        } else {
            document.getElementById('returnDateInfo').style.display = 'none';
        }
    } else {
        document.getElementById('zeroStockWarning').style.display = 'none';
        document.getElementById('returnDateInfo').style.display = 'none';
    }
    
    // Switch view
    document.getElementById('noBookSelected').style.display = 'none';
    document.getElementById('selectedBookContainer').style.display = 'block';
    document.getElementById('submitBtn').disabled = false;
    
    // Scroll to form
    document.getElementById('bookingForm').scrollIntoView({ behavior: 'smooth' });
}

function calculateEstimation(stock, queueCount, queuePosition, nextReturnDate) {
    const effectiveStock = stock - queueCount;
    const estimationDate = document.getElementById('estimatedDateText');
    const estimationNote = document.getElementById('estimationNoteText');
    
    if (stock == 0) {
        // Zero stock - estimasi berdasarkan pengembalian
        if (nextReturnDate) {
            const returnDate = new Date(nextReturnDate);
            // Hold mulai 2 hari setelah buku dikembalikan
            const holdStartDate = new Date(returnDate);
            holdStartDate.setDate(holdStartDate.getDate() + holdDays);
            
            estimationDate.textContent = formatDate(holdStartDate);
            estimationNote.textContent = 'Hold mulai ' + formatDate(holdStartDate) + 
                ' (setelah pengembalian ' + formatDate(returnDate) + ')';
        } else {
            estimationDate.textContent = 'Belum Diketahui';
            estimationNote.textContent = 'Tidak ada informasi tanggal pengembalian';
        }
    } else if (effectiveStock >= 1 && queuePosition == 1) {
        // First in queue with available stock
        estimationDate.textContent = 'SEGERA';
        estimationNote.textContent = 'Bisa langsung mendapatkan hold setelah booking';
    } else if (effectiveStock >= queuePosition) {
        // Enough stock for this position
        const daysPerPerson = 7; // Maksimal masa pinjam
        const daysToWait = (queuePosition - 1) * daysPerPerson;
        const estDate = new Date();
        estDate.setDate(estDate.getDate() + daysToWait);
        
        estimationDate.textContent = formatDate(estDate);
        estimationNote.textContent = 'Estimasi ' + daysToWait + ' hari (' + queueCount + ' orang di depan)';
    } else {
        // Not enough stock, need to wait for returns
        const booksNeeded = queuePosition - effectiveStock;
        
        if (nextReturnDate) {
            const returnDate = new Date(nextReturnDate);
            // Setiap buku tambahan butuh waktu 14 hari (2x masa pinjam)
            const extraDays = (booksNeeded - 1) * 14;
            const holdStartDate = new Date(returnDate);
            holdStartDate.setDate(holdStartDate.getDate() + holdDays + extraDays);
            
            estimationDate.textContent = formatDate(holdStartDate);
            estimationNote.textContent = 'Menunggu ' + booksNeeded + ' buku dikembalikan';
        } else {
            estimationDate.textContent = 'Tergantung Pengembalian';
            estimationNote.textContent = 'Menunggu ' + booksNeeded + ' buku tersedia';
        }
    }
}

function formatDate(date) {
    return date.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function validateForm() {
    if (!selectedBookData) {
        alert('Silakan pilih buku terlebih dahulu');
        return false;
    }
    
    if (<?php echo $active_bookings; ?> >= <?php echo $max_books; ?>) {
        alert('Anda sudah mencapai batas maksimal booking aktif');
        return false;
    }
    
    const queuePosition = selectedBookData.queueCount + 1;
    const stock = selectedBookData.stock;
    
    let confirmMessage = 'Konfirmasi Booking:\n\n';
    confirmMessage += 'Buku: ' + selectedBookData.title + '\n';
    confirmMessage += 'Posisi dalam antrian: ke-' + queuePosition + '\n';
    confirmMessage += 'Stok saat ini: ' + stock + ' buku\n';
    
    if (stock == 0) {
        confirmMessage += '\n⚠️ BUKU STOK HABIS\n';
        if (selectedBookData.nextReturnDate) {
            const returnDate = new Date(selectedBookData.nextReturnDate);
            confirmMessage += 'Tanggal pengembalian: ' + returnDate.toLocaleDateString('id-ID') + '\n';
            confirmMessage += 'Hold mulai: ' + formatDate(new Date(returnDate.setDate(returnDate.getDate() + holdDays))) + '\n';
        }
        confirmMessage += 'Anda akan masuk antrian menunggu buku dikembalikan.\n';
    }
    
    confirmMessage += '\nMasa hold: ' + holdDays + ' hari setelah giliran Anda\n';
    confirmMessage += 'Booking akan hangus jika tidak diambil dalam masa hold.\n\n';
    confirmMessage += 'Lanjutkan booking?';
    
    return confirm(confirmMessage);
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include '../../includes/footer.php'; ?>