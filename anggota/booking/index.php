<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require anggota role
requireRole('anggota');

$page_title = 'Booking Aktif Saya';
$body_class = 'anggota-booking';
include '../../config/database.php';

// ========== GET NIK FROM DATABASE ==========
$user_id = $_SESSION['user_id'];

// Get anggota NIK from database
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

// ========== CANCEL BOOKING ==========
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    try {
        // Verify booking belongs to current member
        $stmt = $conn->prepare("SELECT id_booking, status FROM booking WHERE id_booking = ? AND nik = ?");
        $stmt->execute([$booking_id, $nik]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            throw new Exception('Booking tidak ditemukan atau tidak memiliki akses');
        }
        
        if ($booking['status'] != 'menunggu') {
            throw new Exception('Booking tidak dapat dibatalkan (status: ' . $booking['status'] . ')');
        }
        
        // Update to 'dibatalkan'
        $stmt = $conn->prepare("UPDATE booking SET status = 'dibatalkan' WHERE id_booking = ? AND nik = ?");
        $stmt->execute([$booking_id, $nik]);
        
        // Log activity
        logActivity(
            'CANCEL_BOOKING',
            "Booking #{$booking_id} dibatalkan oleh anggota",
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

// ========== GET ACTIVE BOOKINGS ==========
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Total count
$count_query = "
    SELECT COUNT(*) 
    FROM booking b
    JOIN buku bk ON b.isbn = bk.isbn
    WHERE b.nik = ? 
    AND b.status IN ('menunggu', 'dipinjam')
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([$nik]);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get booking data
$query = "
    SELECT b.*, 
           bk.judul,
           bk.pengarang,
           bk.stok_tersedia,
           DATEDIFF(b.expired_at, CURDATE()) as days_until_expire
    FROM booking b
    JOIN buku bk ON b.isbn = bk.isbn
    WHERE b.nik = ? 
    AND b.status IN ('menunggu', 'dipinjam')
    ORDER BY b.tanggal_booking DESC, b.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute([$nik]);
$bookings = $stmt->fetchAll();

// ========== GET STATISTICS ==========
$stats = [
    'menunggu' => 0,
    'dipinjam' => 0,
    'expiring_today' => 0,
    'total_active' => 0
];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'menunggu'");
    $stmt->execute([$nik]);
    $stats['menunggu'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'dipinjam'");
    $stmt->execute([$nik]);
    $stats['dipinjam'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status = 'menunggu' AND expired_at = CURDATE()");
    $stmt->execute([$nik]);
    $stats['expiring_today'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE nik = ? AND status IN ('menunggu', 'dipinjam')");
    $stmt->execute([$nik]);
    $stats['total_active'] = $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

include '../../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body.anggota-booking {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    background-attachment: fixed !important;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
}

body.anggota-booking::before {
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

/* Alert */
.alert {
    border-radius: 15px;
    border: none;
    backdrop-filter: blur(10px);
    animation: slideDown 0.5s ease;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
}

.alert-success {
    background: rgba(17, 153, 142, 0.9);
    color: white;
}

.alert-danger {
    background: rgba(235, 51, 73, 0.9);
    color: white;
}

.alert .btn-close {
    filter: brightness(0) invert(1);
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

/* Statistics Cards */
.stats-cards {
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    height: 100%;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color-1), var(--stat-color-2));
}

.stat-card:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.stat-card.warning { --stat-color-1: #f093fb; --stat-color-2: #f5576c; }
.stat-card.success { --stat-color-1: #11998e; --stat-color-2: #38ef7d; }
.stat-card.danger { --stat-color-1: #eb3349; --stat-color-2: #f45c43; }
.stat-card.primary { --stat-color-1: #667eea; --stat-color-2: #764ba2; }

.stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.stat-icon i {
    color: white;
    font-size: 1.8rem;
}

.stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
}

/* Booking Cards */
.booking-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.4s ease;
    margin-bottom: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.booking-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.booking-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.5rem;
    color: white;
}

.booking-id {
    font-size: 0.9rem;
    font-weight: 700;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.booking-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.booking-author {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 500;
}

.booking-body {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.booking-meta {
    margin-bottom: 1.5rem;
    flex-grow: 1;
}

.meta-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.meta-item:last-child {
    border-bottom: none;
}

.meta-label {
    font-weight: 600;
    color: #666;
    font-size: 0.9rem;
}

.meta-value {
    font-weight: 600;
    color: #333;
    font-size: 0.9rem;
}

.text-danger {
    color: #eb3349 !important;
}

.text-warning {
    color: #f5576c !important;
}

.booking-status {
    text-align: center;
    margin-bottom: 1rem;
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

.status-borrowed {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.status-expired {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

.booking-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-cancel, .btn-view {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-block;
}

.btn-cancel {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

.btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(235, 51, 73, 0.4);
}

.btn-view {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 25px;
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.empty-state i {
    font-size: 5rem;
    color: #ddd;
    margin-bottom: 1.5rem;
}

.empty-state h4 {
    color: #333;
    font-weight: 700;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.empty-state .btn {
    padding: 0.85rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    margin: 0.25rem;
}

.empty-state .btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
}

.empty-state .btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.empty-state .btn-light:hover {
    transform: translateY(-3px);
}

.empty-state .d-flex {
    justify-content: center;
    flex-wrap: wrap;
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
    color: white;
}

.page-item.disabled .page-link {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
    border-color: rgba(255, 255, 255, 0.2);
}

@media (max-width: 768px) {
    .header-section {
        padding: 1.5rem;
    }
    
    .header-section h1 {
        font-size: 1.8rem;
    }
    
    .booking-card {
        margin-bottom: 1rem;
    }
    
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .pagination-container {
        padding: 1rem;
    }
    
    .pagination-container .d-flex {
        flex-direction: column;
        gap: 1rem;
    }
    
    .pagination-container .text-white {
        text-align: center;
    }
}

@media (max-width: 576px) {
    .header-section {
        padding: 1.25rem;
    }
    
    .header-section h1 {
        font-size: 1.5rem;
    }
    
    .header-section p {
        font-size: 0.95rem;
    }
    
    .stat-number {
        font-size: 1.8rem;
    }
    
    .stat-label {
        font-size: 0.75rem;
    }
    
    .booking-title {
        font-size: 1.05rem;
    }
    
    .empty-state {
        padding: 3rem 1.5rem;
    }
    
    .empty-state i {
        font-size: 4rem;
    }
}
/* Alert - SUPER RAPI VERSION */
.alert {
    border-radius: 20px;
    border: none;
    backdrop-filter: blur(10px);
    animation: slideDown 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    padding: 1.75rem 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, 
        rgba(255, 255, 255, 0.8) 0%, 
        rgba(255, 255, 255, 0.4) 50%, 
        rgba(255, 255, 255, 0.8) 100%);
}

.alert-success {
    background: linear-gradient(135deg, 
        rgba(16, 185, 129, 0.95) 0%, 
        rgba(5, 150, 105, 0.95) 100%);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, 
        rgba(239, 68, 68, 0.95) 0%, 
        rgba(220, 38, 38, 0.95) 100%);
    color: white;
}

.alert-icon {
    font-size: 2rem;
    opacity: 0.9;
    animation: bounceIn 0.6s ease;
}

@keyframes bounceIn {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 0.9; }
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3);
    letter-spacing: 0.3px;
}

.alert-details {
    display: grid;
    gap: 0.75rem;
    margin-top: 1rem;
}

.alert-item {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(5px);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-size: 0.95rem;
    line-height: 1.6;
    border-left: 3px solid rgba(255, 255, 255, 0.5);
    transition: all 0.3s ease;
    animation: slideInRight 0.5s ease forwards;
    opacity: 0;
}

.alert-item:nth-child(1) { animation-delay: 0.1s; }
.alert-item:nth-child(2) { animation-delay: 0.15s; }
.alert-item:nth-child(3) { animation-delay: 0.2s; }
.alert-item:nth-child(4) { animation-delay: 0.25s; }
.alert-item:nth-child(5) { animation-delay: 0.3s; }
.alert-item:nth-child(6) { animation-delay: 0.35s; }
.alert-item:nth-child(7) { animation-delay: 0.4s; }

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert-item:hover {
    background: rgba(255, 255, 255, 0.25);
    border-left-width: 4px;
    transform: translateX(5px);
}

.btn-close-white {
    filter: brightness(0) invert(1);
    opacity: 0.7;
    transition: all 0.3s ease;
    width: 1.5rem;
    height: 1.5rem;
}

.btn-close-white:hover {
    opacity: 1;
    transform: rotate(90deg) scale(1.1);
}

/* Responsive */
@media (max-width: 768px) {
    .alert {
        padding: 1.5rem;
        border-radius: 15px;
    }
    
    .alert-icon {
        font-size: 1.5rem;
    }
    
    .alert-title {
        font-size: 1.1rem;
    }
    
    .alert-item {
        font-size: 0.9rem;
        padding: 0.65rem 0.85rem;
    }
}

/* Dark background enhancement */
body.anggota-booking .alert {
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
}

/* Custom Modal - Glassmorphism Theme */
.custom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.custom-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.custom-modal-container {
    position: relative;
    z-index: 10000;
    animation: modalSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.8) translateY(-50px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.custom-modal-content {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    width: 450px;
    max-width: 90vw;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.5);
    overflow: hidden;
}

.custom-modal-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2.5rem 2rem 1.5rem;
    text-align: center;
}

.custom-modal-icon i {
    font-size: 4rem;
    color: white;
    animation: iconPulse 2s infinite;
    filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.custom-modal-header {
    padding: 2rem 2rem 0;
    text-align: center;
}

.custom-modal-header h3 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0;
}

.custom-modal-body {
    padding: 1.5rem 2rem 2rem;
    text-align: center;
}

.custom-modal-body p {
    font-size: 1.1rem;
    color: #4b5563;
    line-height: 1.6;
    margin: 0;
}

.custom-modal-footer {
    padding: 1.5rem 2rem 2rem;
    display: flex;
    gap: 1rem;
}

.custom-btn {
    flex: 1;
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 15px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.custom-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.custom-btn-cancel {
    background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
    color: #374151;
}

.custom-btn-confirm {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

@media (max-width: 576px) {
    .custom-modal-content { width: 95vw; }
    .custom-modal-icon i { font-size: 3rem; }
    .custom-modal-footer { flex-direction: column; }
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
                <i class="fas fa-calendar-alt"></i> Booking Aktif
            </li>
        </ol>
    </nav>

<!-- Custom Confirmation Modal -->
<div id="customConfirmModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-overlay"></div>
    <div class="custom-modal-container">
        <div class="custom-modal-content">
            <div class="custom-modal-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="custom-modal-header">
                <h3 id="customModalTitle">Batalkan Booking</h3>
            </div>
            <div class="custom-modal-body">
                <p id="customModalMessage">Apakah Anda yakin ingin membatalkan booking ini?</p>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="custom-btn custom-btn-cancel" id="customModalCancel">
                    <i class="fas fa-times me-2"></i>Batal
                </button>
                <button type="button" class="custom-btn custom-btn-confirm" id="customModalConfirm">
                    <i class="fas fa-check me-2"></i>Ya, Batalkan
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show mb-4">
        <div class="d-flex align-items-start">
            <div class="alert-icon me-3">
                <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
            </div>
            <div class="flex-grow-1 alert-content">
                <?php 
                $messages = explode('|', $flash['message']);
                if (count($messages) > 1) {
                    // Title
                    echo '<div class="alert-title">' . htmlspecialchars($messages[0]) . '</div>';
                    
                    // Details
                    echo '<div class="alert-details">';
                    for ($i = 1; $i < count($messages); $i++) {
                        $msg = trim($messages[$i]);
                        if ($msg) {
                            echo '<div class="alert-item">' . htmlspecialchars($msg) . '</div>';
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<div class="alert-title">' . htmlspecialchars($flash['message']) . '</div>';
                }
                ?>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

    <!-- Header Section -->
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-calendar-check"></i> Booking Aktif Saya</h1>
                <p>Kelola pemesanan buku Anda yang sedang aktif</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="process.php" class="btn btn-light px-4 py-2 rounded-pill">
                    <i class="fas fa-plus me-2"></i>Booking Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row stats-cards g-3">
        <div class="col-md-3 col-6">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?= $stats['menunggu'] ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?= $stats['dipinjam'] ?></div>
                <div class="stat-label">Dipinjam</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?= $stats['expiring_today'] ?></div>
                <div class="stat-label">Expire Hari Ini</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-number"><?= $stats['total_active'] ?></div>
                <div class="stat-label">Total Aktif</div>
            </div>
        </div>
    </div>

    <!-- Booking List -->
    <?php if ($total_records > 0): ?>
        <div class="row g-3">
            <?php foreach ($bookings as $booking): ?>
                <?php
                $is_expired = ($booking['status'] == 'menunggu' && $booking['days_until_expire'] < 0);
                $status_class = $is_expired ? 'status-expired' : 
                                ($booking['status'] == 'menunggu' ? 'status-waiting' : 'status-borrowed');
                $status_text = $is_expired ? 'EXPIRED' : 
                              ($booking['status'] == 'menunggu' ? 'MENUNGGU' : 'DIPINJAM');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-id">#<?= $booking['id_booking'] ?></div>
                            <div class="booking-title"><?= htmlspecialchars(substr($booking['judul'], 0, 40)) ?><?= strlen($booking['judul']) > 40 ? '...' : '' ?></div>
                            <div class="booking-author"><?= htmlspecialchars($booking['pengarang']) ?></div>
                        </div>
                        
                        <div class="booking-body">
                            <div class="booking-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Tanggal Booking:</span>
                                    <span class="meta-value"><?= formatTanggal($booking['tanggal_booking']) ?></span>
                                </div>
                                <?php if ($booking['status'] == 'menunggu'): ?>
                                    <div class="meta-item">
                                        <span class="meta-label">Expired:</span>
                                        <span class="meta-value <?= $is_expired ? 'text-danger' : '' ?>">
                                            <?= formatTanggal($booking['expired_at']) ?>
                                        </span>
                                    </div>
                                    <div class="meta-item">
                                        <span class="meta-label">Sisa Waktu:</span>
                                        <span class="meta-value <?= $is_expired ? 'text-danger' : 'text-warning' ?>">
                                            <?php if ($is_expired): ?>
                                                <?= abs($booking['days_until_expire']) ?> hari lalu
                                            <?php elseif ($booking['days_until_expire'] == 0): ?>
                                                Hari ini
                                            <?php else: ?>
                                                <?= $booking['days_until_expire'] ?> hari
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="meta-item">
                                    <span class="meta-label">Stok Tersedia:</span>
                                    <span class="meta-value"><?= $booking['stok_tersedia'] ?></span>
                                </div>
                            </div>
                            
                            <div class="booking-status">
                                <span class="status-badge <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </div>
                            
                            <div class="booking-actions">
                                <?php if ($booking['status'] == 'menunggu'): ?>
                                    <form method="POST" action="" class="w-100" onsubmit="return showCustomConfirm(this);">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id_booking'] ?>">
                                        <input type="hidden" name="cancel_booking" value="1">
                                        <button type="submit" class="btn-cancel">
                                            <i class="fas fa-times me-1"></i> Batalkan
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="../riwayat/peminjaman.php" class="btn-view">
                                        <i class="fas fa-external-link-alt me-1"></i> Lihat Peminjaman
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="text-white mb-2 mb-md-0">
                        Menampilkan <?= min($limit, $total_records - $offset) ?> dari <?= $total_records ?> booking
                    </div>
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">
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
                                    <a class="page-link" href="?page=<?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">
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
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h4>Tidak ada booking aktif</h4>
            <p>Anda belum memiliki booking yang aktif saat ini</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="process.php" class="btn btn-primary px-4">
                    <i class="fas fa-plus me-1"></i> Booking Buku Baru
                </a>
                <a href="history.php" class="btn btn-light px-4">
                    <i class="fas fa-history me-1"></i> Lihat Riwayat
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>

// Custom Modal System - FIXED
let currentFormToSubmit = null;

function showCustomConfirm(formElement) {
    if (event) event.preventDefault(); // Stop form submission
    
    currentFormToSubmit = formElement; // Save form reference
    
    // Debug - bisa dihapus nanti
    console.log('Form saved:', formElement);
    console.log('Booking ID:', formElement.querySelector('[name="booking_id"]')?.value);
    
    // Show modal
    document.getElementById('customConfirmModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    return false; // Important!
}

function hideCustomConfirm() {
    document.getElementById('customConfirmModal').style.display = 'none';
    document.body.style.overflow = '';
    currentFormToSubmit = null;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Confirm button - SUBMIT FORM
    const confirmBtn = document.getElementById('customModalConfirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            console.log('Confirm clicked'); // Debug
            
            if (currentFormToSubmit) {
                console.log('Submitting form...'); // Debug
                
                // Remove onsubmit to prevent recursion
                currentFormToSubmit.onsubmit = null;
                
                // Submit the form
                currentFormToSubmit.submit();
            } else {
                console.error('No form found!'); // Debug
                alert('Error: Form tidak ditemukan');
            }
            
            hideCustomConfirm();
        });
    }
    
    // Cancel button
    const cancelBtn = document.getElementById('customModalCancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            console.log('Cancel clicked'); // Debug
            hideCustomConfirm();
        });
    }
    
    // Overlay click to close
    const overlay = document.querySelector('.custom-modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            hideCustomConfirm();
        });
    }
    
    // ESC key to close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('customConfirmModal')?.style.display === 'flex') {
            hideCustomConfirm();
        }
    });
});

// Animation for booking cards
document.addEventListener('DOMContentLoaded', function() {
    const bookingCards = document.querySelectorAll('.booking-card');
    bookingCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animate stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Auto dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert, .notification-wrapper');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-50px)';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>


<?php include '../../includes/footer.php'; ?>