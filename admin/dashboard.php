<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole(['admin']);

include '../config/database.php';

try {
    $stats = getDashboardStats();
    
    // Recent peminjaman dengan JOIN penerbit
    $recent_peminjaman = $conn->query("
        SELECT p.id_peminjaman, a.nama, b.judul, pn.nama_penerbit, p.tanggal_pinjam, p.status 
        FROM peminjaman p 
        JOIN anggota a ON p.nik = a.nik 
        JOIN buku b ON p.isbn = b.isbn 
        LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
        ORDER BY p.id_peminjaman DESC 
        LIMIT 8
    ")->fetchAll();
    
    // Overdue books dengan JOIN pengembalian
    $overdue_books = $conn->query("
        SELECT a.nama, b.judul, p.tanggal_kembali,
        DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        WHERE p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE()
        ORDER BY hari_terlambat DESC
        LIMIT 5
    ")->fetchAll();
    
    // Due soon
    $due_soon = $conn->query("
        SELECT a.nama, b.judul, p.tanggal_kembali,
        DATEDIFF(p.tanggal_kembali, CURDATE()) as hari_sisa
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        WHERE p.status = 'dipinjam' 
        AND p.tanggal_kembali BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ORDER BY p.tanggal_kembali ASC
        LIMIT 5
    ")->fetchAll();
    
    // Popular books dengan JOIN penerbit dan kategori
    $popular_books = $conn->query("
        SELECT b.judul, b.pengarang, pn.nama_penerbit, COUNT(p.id_peminjaman) as total_pinjam
        FROM buku b
        LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
        LEFT JOIN peminjaman p ON b.isbn = p.isbn 
            AND p.tanggal_pinjam >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY b.isbn
        ORDER BY total_pinjam DESC
        LIMIT 5
    ")->fetchAll();
    
    // Get total denda dari tabel pengembalian
    $total_denda = $conn->query("
        SELECT SUM(denda) as total FROM pengembalian WHERE denda > 0
    ")->fetchColumn() ?? 0;
    
    $stats['total_denda'] = $total_denda;
    
    // Get total pinjam hari ini - FIX ERROR DI SINI
    $pinjam_hari_ini = $conn->query("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE DATE(tanggal_pinjam) = CURDATE()
    ")->fetchColumn() ?? 0;
    
    $stats['pinjam_hari_ini'] = $pinjam_hari_ini;
    
    // Get anggota aktif untuk stats
    $anggota_aktif = $conn->query("
    SELECT COUNT(*) as total FROM anggota
")->fetchColumn() ?? 0;
    
    $stats['anggota_aktif'] = $anggota_aktif;
    
} catch (PDOException $e) {
    $error_message = "Error mengambil data: " . $e->getMessage();
    // Inisialisasi semua stats dengan default value untuk menghindari error
    $stats = [
        'total_buku' => 0, 
        'buku_tersedia' => 0, 
        'total_anggota' => 0, 
        'anggota_aktif' => 0,
        'buku_dipinjam' => 0, 
        'buku_terlambat' => 0, 
        'total_denda' => 0,
        'pinjam_hari_ini' => 0
    ];
    $recent_peminjaman = [];
    $overdue_books = [];
    $due_soon = [];
    $popular_books = [];
}

$page_title = 'Admin Dashboard';
$body_class = 'admin-dashboard';

include '../includes/header.php';

// Greeting based on time
$hour = date('H');
if ($hour < 12) {
    $greeting = "Selamat Pagi";
    $greeting_icon = "☀️";
} elseif ($hour < 15) {
    $greeting = "Selamat Siang";
    $greeting_icon = "🌤️";
} elseif ($hour < 18) {
    $greeting = "Selamat Sore";
    $greeting_icon = "🌅";
} else {
    $greeting = "Selamat Malam";
    $greeting_icon = "🌙";
}

// Format tanggal Indonesia
function formatTanggalIndonesiaDashboard($date) {
    $hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $bulan = array(
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    );
    
    $day = date('l', strtotime($date));
    $month = date('F', strtotime($date));
    $dayNum = date('d', strtotime($date));
    $year = date('Y', strtotime($date));
    
    $hari_indonesia = $hari[date('w', strtotime($date))];
    $bulan_indonesia = $bulan[$month] ?? $month;
    
    return "$hari_indonesia, $dayNum $bulan_indonesia $year";
}

// Library status message
function getLibraryStatusMessageDashboard() {
    return [
        'message' => 'Perpustakaan Buka',
        'status' => 'open'
    ];
}
?>

<style>
/* ==================== */
/* DASHBOARD STYLES MODERN */
/* ==================== */

:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f46b45 0%, #eea849 100%);
    --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --purple-gradient: linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    --orange-gradient: linear-gradient(135deg, #FF9800 0%, #FF5722 100%);
}

/* Welcome Section */
.welcome-section-modern {
    background: var(--primary-gradient);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.welcome-section-modern::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-section-modern::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
}

.greeting-text-modern {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 2;
}

.greeting-icon-modern {
    font-size: 2.2rem;
    margin-right: 0.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.welcome-user-name-modern {
    font-size: 1.4rem;
    font-weight: 600;
    color: #FFD700;
    margin-bottom: 0.75rem;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.welcome-message-modern {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 1.5rem;
    position: relative;
    z-index: 2;
    max-width: 600px;
}

.welcome-message-modern strong {
    color: #FFD700;
    font-weight: 700;
}

.role-badge-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.time-widget-modern {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 2;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.time-widget-modern .time-display {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: 1px;
}

.time-widget-modern .date-display {
    font-size: 1rem;
    opacity: 0.95;
    margin-bottom: 0.75rem;
}

.status-badge-modern {
    background: rgba(255, 255, 255, 0.25);
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Stats Cards Modern - GRID 6 KOLOM SEJAJAR */
.stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(6, 1fr); /* 6 KOLOM SEJAJAR */
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .stats-grid-modern {
        grid-template-columns: repeat(3, 1fr); /* 3 KOLOM DI TABLET */
    }
}

@media (max-width: 768px) {
    .stats-grid-modern {
        grid-template-columns: repeat(2, 1fr); /* 2 KOLOM DI MOBILE */
    }
}

@media (max-width: 480px) {
    .stats-grid-modern {
        grid-template-columns: 1fr; /* 1 KOLOM DI HP KECIL */
    }
}

.stats-card-modern {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #eef2ff;
    position: relative;
    overflow: hidden;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    cursor: pointer;
}

.stats-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--card-gradient);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stats-card-modern:hover::before {
    opacity: 1;
}

.stats-card-modern:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.stats-card-modern i {
    font-size: 2.2rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.stats-card-modern h2 {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    color: #2c3e50;
    position: relative;
    z-index: 1;
    line-height: 1.2;
}

.stats-card-modern p {
    font-size: 0.8rem;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 0;
    position: relative;
    z-index: 1;
}

.stats-card-modern .stats-sub {
    font-size: 0.75rem;
    color: #adb5bd;
    margin-top: 0.5rem;
    font-weight: 500;
}

/* Card Color Variants - SEMUA STATS DI BARIS PERTAMA */
.stats-card-modern:nth-child(1) { --card-gradient: var(--primary-gradient); }
.stats-card-modern:nth-child(2) { --card-gradient: var(--success-gradient); }
.stats-card-modern:nth-child(3) { --card-gradient: var(--warning-gradient); }
.stats-card-modern:nth-child(4) { --card-gradient: var(--danger-gradient); }
.stats-card-modern:nth-child(5) { --card-gradient: var(--info-gradient); }
.stats-card-modern:nth-child(6) { --card-gradient: var(--orange-gradient); } /* WARNA ORANGE UNTUK DENDA */

/* Special style untuk Total Denda agar teks tidak terlalu kecil */
.stats-card-modern:last-child h2 {
    font-size: 1.5rem; /* Sedikit lebih kecil untuk muat angka besar */
    word-break: break-word;
}

/* Quick Actions Modern */
.quick-actions-section {
    margin-bottom: 2rem;
}

.section-title-modern {
    font-size: 1.4rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-title-modern i {
    font-size: 1.3rem;
    color: #667eea;
}

.actions-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.action-card-modern {
    background: white;
    border-radius: 18px;
    padding: 1.75rem;
    text-align: center;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.06);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.action-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: var(--action-color);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.action-card-modern:hover::before {
    opacity: 1;
}

.action-card-modern:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    border-color: var(--action-color);
}

.action-icon-wrapper-modern {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    font-size: 1.8rem;
    color: white;
    background: var(--action-color);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    transition: all 0.4s ease;
}

.action-card-modern:hover .action-icon-wrapper-modern {
    transform: rotate(15deg) scale(1.1);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2);
}

.action-card-modern h6 {
    font-weight: 800;
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
    color: #2c3e50;
}

.action-card-modern p {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
    line-height: 1.5;
    flex-grow: 1;
}

.action-btn-modern {
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    font-size: 0.9rem;
    background: var(--action-color);
    color: white;
    min-width: 150px;
}

.action-btn-modern:hover {
    background: white;
    color: var(--action-color);
    border-color: var(--action-color);
    transform: translateY(-2px);
}

/* Action Color Variants */
.action-card-modern:nth-child(1) { --action-color: #667eea; }
.action-card-modern:nth-child(2) { --action-color: #11998e; }
.action-card-modern:nth-child(3) { --action-color: #f46b45; }
.action-card-modern:nth-child(4) { --action-color: #8e2de2; }



.users-btn-modern {
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    font-size: 0.85rem;
    flex: 1;
    min-width: 120px;
}

.users-btn-anggota {
    background: #11998e;
    color: white;
}

.users-btn-anggota:hover {
    background: white;
    color: #11998e;
    border-color: #11998e;
}

.users-btn-petugas {
    background: #36D1DC;
    color: white;
}

.users-btn-petugas:hover {
    background: white;
    color: #36D1DC;
    border-color: #36D1DC;
}

/* Main Content Grid - TINGGI FIX */
.content-grid-modern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
    height: auto;
    min-height: 500px;
}

/* Tablet - 1 Kolom */
@media (max-width: 992px) {
    .content-grid-modern {
        grid-template-columns: 1fr;
        height: auto;
        min-height: auto;
        gap: 1.5rem;
    }
}

/* Mobile - 1 Kolom dengan spacing lebih kecil */
@media (max-width: 768px) {
    .content-grid-modern {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
}

/* Card Modern - Responsive Height */
.card-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid #f0f2f5;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 550px;
    min-height: 400px;
}

/* Mobile - Card height adjustment */
@media (max-width: 768px) {
    .card-modern {
        max-height: none; /* Hapus limit di mobile */
        min-height: 350px; /* Kurangi min-height */
        border-radius: 16px; /* Radius lebih kecil */
    }
}

@media (max-width: 576px) {
    .card-modern {
        min-height: 300px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
}

/* Card Header - Responsive */
.card-header-modern {
    background: var(--primary-gradient);
    color: white;
    padding: 1.25rem 1.75rem;
    border: none;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
    z-index: 10;
}

@media (max-width: 768px) {
    .card-header-modern {
        padding: 1rem 1.25rem;
    }
    
    .card-header-modern h6 {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .card-header-modern {
        padding: 0.85rem 1rem;
    }
    
    .card-header-modern h6 {
        font-size: 0.9rem;
    }
}

.card-header-modern::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.card-header-modern h6 {
    margin: 0;
    font-weight: 800;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    z-index: 1;
}

/* Card Body - Responsive Scroll */
.card-body-scrollable {
    padding: 0;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
    max-height: 450px;
}

@media (max-width: 768px) {
    .card-body-scrollable {
        max-height: 400px;
    }
}

@media (max-width: 576px) {
    .card-body-scrollable {
        max-height: 350px;
    }
}
/* Activity Items */
.activity-item-modern {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f5f5f5;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .activity-item-modern {
        padding: 0.85rem 1.25rem;
        gap: 0.85rem;
    }
}

@media (max-width: 576px) {
    .activity-item-modern {
        padding: 0.75rem 1rem;
        gap: 0.75rem;
    }
}

.activity-item-modern:hover {
    background: #f8faff;
    transform: translateX(5px);
}

.activity-item-modern:last-child {
    border-bottom: none;
}

.activity-icon-modern {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
    .activity-icon-modern {
        width: 38px;
        height: 38px;
        font-size: 1rem;
        border-radius: 10px;
    }
}

@media (max-width: 576px) {
    .activity-icon-modern {
        width: 34px;
        height: 34px;
        font-size: 0.9rem;
        border-radius: 8px;
    }
}

.activity-icon-modern.success {
    background: var(--success-gradient);
    color: white;
}

.activity-icon-modern.warning {
    background: var(--warning-gradient);
    color: white;
}

.activity-content-modern {
    flex: 1;
    min-width: 0;
}

.activity-content-modern .name {
    font-weight: 700;
    color: #2c3e50;
    font-size: 0.95rem;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.activity-content-modern .book {
    color: #6c757d;
    font-size: 0.85rem;
    margin-bottom: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.activity-content-modern .publisher {
    color: #adb5bd;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

@media (max-width: 768px) {
    .activity-content-modern .name {
        font-size: 0.9rem;
    }
    
    .activity-content-modern .book {
        font-size: 0.8rem;
    }
    
    .activity-content-modern .publisher {
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .activity-content-modern .name {
        font-size: 0.85rem;
    }
    
    .activity-content-modern .book {
        font-size: 0.75rem;
    }
    
    .activity-content-modern .publisher {
        font-size: 0.7rem;
    }
}


.activity-badge-modern {
    padding: 0.4rem 0.9rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.8rem;
    white-space: nowrap;
}
@media (max-width: 768px) {
    .activity-badge-modern {
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
        border-radius: 6px;
    }
}
@media (max-width: 576px) {
    .activity-badge-modern {
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
        border-radius: 5px;
    }
}

.empty-state-modern {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d;
}

@media (max-width: 576px) {
    .empty-state-modern {
        padding: 1.5rem 0.75rem;
    }
    
    .empty-state-modern i {
        font-size: 1.5rem !important;
    }
    
    .empty-state-modern h6 {
        font-size: 0.9rem;
    }
    
    .empty-state-modern p {
        font-size: 0.8rem;
    }
}

.activity-badge-modern.warning {
    background: #f46b45;
    color: white;
}

.activity-badge-modern.success {
    background: #11998e;
    color: white;
}

/* ==================== */
/* PERBAIKAN KHUSUS: SCROLL UNTUK NOTIFIKASI SAJA */
/* ==================== */


/* Notifications Container - Responsive */
.notifications-container {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: 500px;
    gap: 0;
    overflow: hidden;
}

@media (max-width: 768px) {
    .notifications-container {
        padding: 1rem;
        max-height: 450px;
    }
}

@media (max-width: 576px) {
    .notifications-container {
        padding: 0.75rem;
        max-height: 400px;
    }
}

/* Notifications Alerts - Responsive */
.notifications-alerts-container {
    flex: 0 0 auto;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
    max-height: 200px;
    margin-bottom: 1rem;
    padding-right: 10px;
    scroll-behavior: smooth;
}

@media (max-width: 768px) {
    .notifications-alerts-container {
        max-height: 180px;
        padding-right: 8px;
    }
}

@media (max-width: 576px) {
    .notifications-alerts-container {
        max-height: 150px;
        padding-right: 6px;
        margin-bottom: 0.75rem;
    }
}

/* Custom Scrollbar - LEBIH CANTIK */
.notifications-alerts-container::-webkit-scrollbar {
    width: 10px;
}

.notifications-alerts-container::-webkit-scrollbar-track {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    margin: 6px 0;
}

.notifications-alerts-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #cbd5e0 0%, #a0aec0 100%);
    border-radius: 10px;
    border: 2px solid #f8f9fa;
    transition: all 0.3s ease;
}

.notifications-alerts-container::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #edf2f7;
    transform: scale(1.1);
}

.notifications-alerts-container::-webkit-scrollbar-thumb:active {
    background: linear-gradient(135deg, #5568d3 0%, #6542a1 100%);
}

/* Firefox Scrollbar */
.notifications-alerts-container {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f8f9fa;
}

/* Scroll Shadow Indicators - MENUNJUKKAN ADA KONTEN LEBIH */
.notifications-alerts-container {
    background:
        linear-gradient(white 30%, transparent) center top,
        linear-gradient(transparent, white 70%) center bottom,
        radial-gradient(farthest-side at 50% 0, rgba(102, 126, 234, 0.15), transparent) center top,
        radial-gradient(farthest-side at 50% 100%, rgba(102, 126, 234, 0.15), transparent) center bottom;
    background-repeat: no-repeat;
    background-size: 100% 40px, 100% 40px, 100% 14px, 100% 14px;
    background-attachment: local, local, scroll, scroll;
}

/* Alert Cards Modern */
.alert-card-modern {
    border-radius: 12px;
    border: none;
    padding: 0.85rem;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .alert-card-modern {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        gap: 0.6rem;
        border-radius: 10px;
    }
    
    .alert-card-modern i {
        font-size: 1.5rem;
    }
    
    .alert-card-modern .alert-title {
        font-size: 0.9rem;
    }
    
    .alert-card-modern .alert-details {
        font-size: 0.85rem;
    }
}

@media (max-width: 576px) {
    .alert-card-modern {
        padding: 0.65rem;
        margin-bottom: 0.4rem;
        gap: 0.5rem;
        border-radius: 8px;
    }
    
    .alert-card-modern i {
        font-size: 1.3rem;
    }
    
    .alert-card-modern .alert-title {
        font-size: 0.85rem;
    }
    
    .alert-card-modern .alert-details {
        font-size: 0.8rem;
    }
}


.alert-card-modern:last-child {
    margin-bottom: 0.5rem; /* SPACING TERAKHIR */
}

.alert-card-modern:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    z-index: 10;
}

.alert-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 6px;
    height: 100%;
    background: currentColor;
    opacity: 0.3;
}

.alert-card-modern i {
    font-size: 1.8rem;
    flex-shrink: 0;
}

.alert-card-modern .alert-content {
    flex: 1;
}

.alert-card-modern .alert-title {
    font-weight: 800;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.alert-card-modern .alert-details {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Alert Color Variants */
.alert-danger-modern {
    background: linear-gradient(135deg, #ffeaea 0%, #fff0f0 100%);
    color: #ff6b6b;
    border-left: 4px solid #ff6b6b;
}

.alert-warning-modern {
    background: linear-gradient(135deg, #fff5e6 0%, #fffaf0 100%);
    color: #f46b45;
    border-left: 4px solid #f46b45;
}

.alert-info-modern {
    background: linear-gradient(135deg, #e6f7ff 0%, #f0f9ff 100%);
    color: #4facfe;
    border-left: 4px solid #4facfe;
}

.alert-success-modern {
    background: linear-gradient(135deg, #e6fff5 0%, #f0fff8 100%);
    color: #11998e;
    border-left: 4px solid #11998e;
}

/* ==================== */
/* SCROLL UNTUK BUKU POPULER BULAN INI */
/* ==================== */

/* CARI BAGIAN .popular-books-section DI DASHBOARD.PHP (SEKITAR BARIS 400-500) */
/* GANTI DENGAN CSS INI: */

.popular-books-section {
    margin-top: 0;
    padding-top: 0;
    border-top: 2px dashed #eaeaea;
    flex: 1 1 auto;
    min-height: 0;
    max-height: 250px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 10px;
    scroll-behavior: smooth;
    position: relative;
}

@media (max-width: 768px) {
    .popular-books-section {
        max-height: 220px;
        padding-right: 8px;
    }
}

@media (max-width: 576px) {
    .popular-books-section {
        max-height: 200px;
        padding-right: 6px;
    }
}
/* Custom Scrollbar untuk Popular Books */
.popular-books-section::-webkit-scrollbar {
    width: 10px;
}

.popular-books-section::-webkit-scrollbar-track {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    margin: 8px 0;
}

.popular-books-section::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    border: 2px solid #f8f9fa;
    transition: all 0.3s ease;
}

.popular-books-section::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6542a1 100%);
    transform: scaleX(1.2);
}

/* Firefox Scrollbar */
.popular-books-section {
    scrollbar-width: thin;
    scrollbar-color: #667eea #f8f9fa;
}

/* Title tetap di atas saat scroll */
.popular-books-title {
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
    position: sticky;
    top: 0;
    background: white;
    padding: 1rem 0.5rem;
    z-index: 100;
    border-bottom: 2px solid #eef2ff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .popular-books-title {
        font-size: 1rem;
        padding: 0.85rem 0.4rem;
        gap: 0.4rem;
    }
    
    .popular-books-title i {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .popular-books-title {
        font-size: 0.9rem;
        padding: 0.75rem 0.3rem;
        gap: 0.35rem;
    }
    
    .popular-books-title i {
        font-size: 0.9rem;
    }
}

/* Shadow lebih jelas saat scroll */
.popular-books-section.scrolling .popular-books-title {
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    border-bottom-color: #667eea;
}

/* Popular Book Items */
.popular-book-item-modern {
    background: #f8faff;
    border-radius: 10px;
    padding: 0.65rem;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    border: 1px solid #eef2ff;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .popular-book-item-modern {
        padding: 0.6rem;
        margin-bottom: 0.45rem;
        gap: 0.6rem;
        border-radius: 8px;
    }
}

@media (max-width: 576px) {
    .popular-book-item-modern {
        padding: 0.5rem;
        margin-bottom: 0.4rem;
        gap: 0.5rem;
        border-radius: 6px;
    }
}

.popular-book-item-modern:last-child {
    margin-bottom: 0.5rem; /* Spacing untuk scrollbar */
}

.popular-book-item-modern:hover {
    background: white;
    transform: translateX(5px) scale(1.02);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    border-color: #667eea;
    z-index: 5;
}

/* Rank Badge */
.popular-rank-modern {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
}

@media (max-width: 768px) {
    .popular-rank-modern {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
        border-radius: 8px;
    }
}

@media (max-width: 576px) {
    .popular-rank-modern {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
        border-radius: 6px;
    }
}

.popular-content-modern {
    flex: 1;
    min-width: 0;
}

.popular-content-modern .title {
    font-weight: 700;
    color: #2c3e50;
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.popular-content-modern .meta {
    color: #6c757d;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .popular-content-modern .title {
        font-size: 0.9rem;
    }
    
    .popular-content-modern .meta {
        font-size: 0.8rem;
        gap: 0.6rem;
    }
}

@media (max-width: 576px) {
    .popular-content-modern .title {
        font-size: 0.85rem;
    }
    
    .popular-content-modern .meta {
        font-size: 0.75rem;
        gap: 0.5rem;
    }
    
    .meta-item i {
        font-size: 0.75rem;
    }
}


.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Shadow Gradient Indicators */
.popular-books-section::before {
    content: '';
    position: sticky;
    top: 60px;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to bottom, white, transparent);
    pointer-events: none;
    z-index: 5;
    display: block;
}

.popular-books-section::after {
    content: '';
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to top, white, transparent);
    pointer-events: none;
    z-index: 5;
    display: block;
}

/* Animation untuk book items */
.popular-book-item-modern {
    animation: slideInBook 0.5s ease-out backwards;
}

.popular-book-item-modern:nth-child(1) { animation-delay: 0.1s; }
.popular-book-item-modern:nth-child(2) { animation-delay: 0.15s; }
.popular-book-item-modern:nth-child(3) { animation-delay: 0.2s; }
.popular-book-item-modern:nth-child(4) { animation-delay: 0.25s; }
.popular-book-item-modern:nth-child(5) { animation-delay: 0.3s; }
.popular-book-item-modern:nth-child(6) { animation-delay: 0.35s; }

@keyframes slideInBook {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .popular-books-section {
        max-height: 320px;
        padding-right: 8px;
    }
    
    .popular-books-section::-webkit-scrollbar {
        width: 8px;
    }
}

@media (max-width: 576px) {
    .notifications-alerts-container {
        max-height: 250px;
    }
    
    .alert-card-modern {
        padding: 1rem;
    }
}

/* Animasi untuk alert cards */
.alert-card-modern {
    animation: fadeInAlert 0.5s ease-out backwards;
}

.alert-card-modern:nth-child(1) { animation-delay: 0.1s; }
.alert-card-modern:nth-child(2) { animation-delay: 0.2s; }
.alert-card-modern:nth-child(3) { animation-delay: 0.3s; }
.alert-card-modern:nth-child(4) { animation-delay: 0.4s; }
.alert-card-modern:nth-child(5) { animation-delay: 0.5s; }

@keyframes fadeInAlert {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Scroll hint indicator - OPSIONAL */
.scroll-hint {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    color: #667eea;
    font-size: 1.5rem;
    animation: bounceDown 1.5s ease-in-out infinite;
    pointer-events: none;
    opacity: 0.6;
}

@keyframes bounceDown {
    0%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    50% {
        transform: translateX(-50%) translateY(5px);
    }
}

/* ==================== */
/* FOOTER FIX KHUSUS DASHBOARD */
/* ==================== */

/* Pastikan html dan body full height */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

/* Body dashboard sebagai flex container */
body.admin-dashboard {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Container utama mengambil sisa space */
body.admin-dashboard .container {
    flex: 1 0 auto;
    padding-bottom: 1rem;
}

/* Footer tetap di bawah */
body.admin-dashboard footer {
    flex-shrink: 0;
    margin-top: auto;
}


/* Batasi tinggi card body */
.card-body-scrollable {
    max-height: 350px;
}

/* Custom Scrollbar - LEBIH HALUS */
.card-body-scrollable::-webkit-scrollbar,
.notifications-alerts-container::-webkit-scrollbar {
    width: 8px;
}

.card-body-scrollable::-webkit-scrollbar-track,
.notifications-alerts-container::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 10px;
    margin: 4px;
}

.card-body-scrollable::-webkit-scrollbar-thumb,
.notifications-alerts-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
    border: 2px solid #f8f9fa;
}

.card-body-scrollable::-webkit-scrollbar-thumb:hover,
.notifications-alerts-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Hapus scrollbar dari Firefox */
.card-body-scrollable,
.notifications-alerts-container {
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f8f9fa;
}

/* Tidak ada scrollbar jika konten pendek */
.no-scroll {
    overflow: hidden !important;
}

/* Error message styling */
.error-message {
    background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    border-left: 5px solid #dc3545;
    font-weight: 600;
}

/* ==================== */
/* RESPONSIVE MOBILE FIX - ANTI KETUMPUK */
/* ==================== */

/* RESET GRID UNTUK MOBILE */
@media (max-width: 768px) {
    /* Container utama */
    .container {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    
    /* Content Grid - 1 KOLOM PENUH */
    .content-grid-modern {
        display: flex !important;
        flex-direction: column !important;
        gap: 1.5rem !important;
        grid-template-columns: 1fr !important;
        height: auto !important;
        min-height: auto !important;
    }
    
    /* Card Modern - Full Width */
    .card-modern {
        width: 100% !important;
        max-height: none !important;
        min-height: 350px !important;
        margin-bottom: 0 !important;
    }
    
    /* Card Header - Lebih Compact */
    .card-header-modern {
        padding: 1rem 1.25rem !important;
    }
    
    .card-header-modern h6 {
        font-size: 0.95rem !important;
    }
    
    /* Card Body - Auto Height */
    .card-body-scrollable {
        max-height: 400px !important;
        overflow-y: auto !important;
    }
    
    /* Notifications Container */
    .notifications-container {
        padding: 1rem !important;
        max-height: none !important;
        overflow: visible !important;
    }
    
    /* Alerts Container */
    .notifications-alerts-container {
        max-height: 180px !important;
        margin-bottom: 1rem !important;
    }
    
    /* Alert Cards */
    .alert-card-modern {
        padding: 0.75rem !important;
        margin-bottom: 0.5rem !important;
        gap: 0.6rem !important;
    }
    
    .alert-card-modern i {
        font-size: 1.4rem !important;
    }
    
    .alert-card-modern .alert-title {
        font-size: 0.85rem !important;
    }
    
    .alert-card-modern .alert-details {
        font-size: 0.8rem !important;
    }
    
    /* Popular Books Section */
    .popular-books-section {
        max-height: 200px !important;
    }
    
    .popular-books-title {
        font-size: 0.95rem !important;
        padding: 0.75rem 0.5rem !important;
    }
    
    .popular-book-item-modern {
        padding: 0.6rem !important;
        margin-bottom: 0.45rem !important;
    }
    
    .popular-rank-modern {
        width: 30px !important;
        height: 30px !important;
        font-size: 0.8rem !important;
    }
    
    .popular-content-modern .title {
        font-size: 0.85rem !important;
    }
    
    .popular-content-modern .meta {
        font-size: 0.75rem !important;
    }
    
    /* Activity Items */
    .activity-item-modern {
        padding: 0.75rem 1rem !important;
        gap: 0.75rem !important;
    }
    
    .activity-icon-modern {
        width: 36px !important;
        height: 36px !important;
        font-size: 1rem !important;
    }
    
    .activity-content-modern .name {
        font-size: 0.85rem !important;
    }
    
    .activity-content-modern .book {
        font-size: 0.75rem !important;
    }
    
    .activity-content-modern .publisher {
        font-size: 0.7rem !important;
    }
    
    .activity-badge-modern {
        padding: 0.3rem 0.6rem !important;
        font-size: 0.7rem !important;
    }
}

/* EXTRA SMALL MOBILE (< 576px) */
@media (max-width: 576px) {
    .container {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
    
    .content-grid-modern {
        gap: 1rem !important;
    }
    
    .card-modern {
        min-height: 300px !important;
        border-radius: 12px !important;
    }
    
    .card-header-modern {
        padding: 0.85rem 1rem !important;
    }
    
    .card-header-modern h6 {
        font-size: 0.9rem !important;
    }
    
    .card-body-scrollable {
        max-height: 350px !important;
    }
    
    .notifications-container {
        padding: 0.75rem !important;
    }
    
    .notifications-alerts-container {
        max-height: 150px !important;
    }
    
    .alert-card-modern {
        padding: 0.65rem !important;
        margin-bottom: 0.4rem !important;
    }
    
    .alert-card-modern i {
        font-size: 1.2rem !important;
    }
    
    .alert-card-modern .alert-title {
        font-size: 0.8rem !important;
    }
    
    .alert-card-modern .alert-details {
        font-size: 0.75rem !important;
    }
    
    .popular-books-section {
        max-height: 180px !important;
    }
    
    .popular-books-title {
        font-size: 0.9rem !important;
        padding: 0.65rem 0.4rem !important;
    }
    
    .popular-book-item-modern {
        padding: 0.5rem !important;
        margin-bottom: 0.4rem !important;
    }
    
    .popular-rank-modern {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.75rem !important;
    }
    
    .popular-content-modern .title {
        font-size: 0.8rem !important;
    }
    
    .popular-content-modern .meta {
        font-size: 0.7rem !important;
    }
    
    .activity-item-modern {
        padding: 0.65rem 0.85rem !important;
    }
    
    .activity-icon-modern {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.9rem !important;
    }
}

/* PASTIKAN TIDAK ADA OVERFLOW DI MOBILE */
@media (max-width: 768px) {
    body.admin-dashboard {
        overflow-x: hidden !important;
    }
    
    .welcome-section-modern {
        margin-bottom: 1.5rem !important;
        padding: 1.5rem !important;
    }
    
    .stats-grid-modern {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
    }
    
    .quick-actions-section {
        margin-bottom: 1.5rem !important;
    }
    
    .actions-grid-modern {
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
}

/* ==================== */
/* DROPDOWN MODERN STYLES */
/* ==================== */

/* Dropdown Toggle Button - SAMA DENGAN BUTTON LAIN */
.dropdown-toggle-modern {
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    font-size: 0.9rem;
    background: var(--action-color);
    color: white;
    min-width: 150px;
    cursor: pointer;
    position: relative;
}

.dropdown-toggle-modern:hover {
    background: white;
    color: var(--action-color);
    border-color: var(--action-color);
    transform: translateY(-2px);
}

.dropdown-toggle-modern:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.dropdown-toggle-modern .fa-chevron-down,
.dropdown-toggle-modern .fa-chevron-up {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.dropdown-toggle-modern[aria-expanded="true"] .fa-chevron-down {
    transform: rotate(180deg);
}

.dropdown-toggle-modern[aria-expanded="true"] .fa-chevron-up {
    transform: rotate(180deg);
}

/* ==================== */
/* DROPDOWN BORDER RADIUS - 100% FIX */
/* CARI DAN GANTI BAGIAN .dropdown-menu-modern DI DASHBOARD.PHP */
/* MULAI DARI BARIS ~1680 SAMPAI ~1850 */
/* ==================== */

/* Dropdown Menu - BORDER RADIUS PERFECT */
.dropdown-menu-modern {
    /* STEP 1: Background solid dulu, TANPA gradient */
    background: white !important;
    border: none;
    
    /* STEP 2: Border radius SEMUA sisi */
    border-radius: 16px !important;
    
    /* STEP 3: Overflow hidden untuk clip content */
    overflow: hidden !important;
    
    /* STEP 4: Box shadow di luar */
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    
    /* Padding & Margin */
    padding: 0.75rem;
    margin-bottom: 0.5rem !important;
    margin-top: 0 !important;
    
    /* Size */
    min-width: 260px;
    max-width: 280px;
    max-height: 260px !important;
    
    /* Position */
    position: absolute !important;
    bottom: 100% !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    top: auto !important;
    z-index: 9999 !important;
    
    /* Scroll */
    overflow-y: auto !important;
    overflow-x: hidden !important;
    overscroll-behavior: contain;
    scroll-behavior: smooth;
    
    animation: dropdownSlideUp 0.3s ease-out;
}

/* STEP 5: Wrapper untuk scroll indicators - TANPA ganggu border */
.dropdown-menu-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 30px;
    background: linear-gradient(to bottom, white, transparent);
    pointer-events: none;
    z-index: 10;
    border-radius: 16px 16px 0 0; /* RADIUS ATAS SAJA */
}

/* Container */
.dropup {
    position: relative !important;
}

/* Fallback dropdown */
.dropup.force-dropdown .dropdown-menu-modern {
    bottom: auto !important;
    top: 100% !important;
    margin-bottom: 0 !important;
    margin-top: 0.5rem !important;
}

/* Custom Scrollbar - SLIM & CANTIK */
.dropdown-menu-modern::-webkit-scrollbar {
    width: 5px;
}

.dropdown-menu-modern::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
    margin: 16px 2px; /* MARGIN DARI UJUNG */
}

.dropdown-menu-modern::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.dropdown-menu-modern::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6542a1 100%);
    transform: scaleX(1.5);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
}

/* Firefox */
.dropdown-menu-modern {
    scrollbar-width: thin;
    scrollbar-color: #667eea transparent;
}

/* Dropdown Items */
.dropdown-item-modern {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.75rem;
    margin: 0 0.25rem; /* MARGIN HORIZONTAL KECIL */
    border-radius: 10px;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #2c3e50;
    cursor: pointer;
    position: relative;
}

/* Item pertama - spacing atas */
.dropdown-item-modern:first-child {
    margin-top: 0.25rem;
}

/* Item terakhir - spacing bawah */
.dropdown-item-modern:last-child {
    margin-bottom: 0.25rem;
}

.dropdown-item-modern:hover {
    background: linear-gradient(135deg, #f8faff 0%, #eef2ff 100%);
    transform: translateX(5px);
    color: #667eea;
}

.dropdown-item-modern i {
    font-size: 1.15rem;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8faff;
    border-radius: 8px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.dropdown-item-modern:hover i {
    transform: rotate(5deg) scale(1.1);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.dropdown-item-modern div {
    flex: 1;
    min-width: 0;
}

.dropdown-item-modern strong {
    display: block;
    font-size: 0.86rem;
    font-weight: 700;
    margin-bottom: 0.1rem;
    color: #2c3e50;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-item-modern small {
    display: block;
    font-size: 0.7rem;
    color: #6c757d;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-item-modern:hover strong {
    color: #667eea;
}

/* Divider */
.dropdown-menu-modern .dropdown-divider {
    margin: 0.25rem 0.25rem;
    border-top: 1px solid #eef2ff;
}

/* Animasi */
@keyframes dropdownSlideUp {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

/* Scroll Indicator - Panah bawah */
.dropdown-menu-modern::after {
    content: '⌄';
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 26px;
    background: linear-gradient(to top, white 70%, transparent);
    color: #667eea;
    font-size: 1.3rem;
    font-weight: bold;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 10;
    border-radius: 0 0 16px 16px; /* RADIUS BAWAH SAJA */
}

/* Show arrow when scrollable */
.dropdown-menu-modern[data-scrollable="true"]:not(.at-bottom)::after {
    opacity: 0.7;
    animation: pulseDown 1.5s ease-in-out infinite;
}

/* Hide at bottom */
.dropdown-menu-modern.at-bottom::after {
    opacity: 0 !important;
}

@keyframes pulseDown {
    0%, 100% {
        transform: translateY(0);
        opacity: 0.5;
    }
    50% {
        transform: translateY(4px);
        opacity: 0.9;
    }
}

/* Shadow based on scroll position */
.dropdown-menu-modern.at-top {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.dropdown-menu-modern.at-bottom {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.dropdown-menu-modern.is-scrolling {
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

/* Responsive */
@media (max-width: 768px) {
    .dropdown-menu-modern {
        min-width: 240px;
        max-width: 260px;
        max-height: 240px !important;
        margin-bottom: 0.4rem !important;
        border-radius: 14px !important;
    }
    
    .dropdown-menu-modern::before {
        border-radius: 14px 14px 0 0;
    }
    
    .dropdown-menu-modern::after {
        border-radius: 0 0 14px 14px;
    }
    
    .dropdown-item-modern {
        padding: 0.55rem 0.7rem;
        gap: 0.6rem;
    }
    
    .dropdown-item-modern i {
        width: 28px;
        height: 28px;
        font-size: 1.05rem;
    }
    
    .dropdown-item-modern strong {
        font-size: 0.82rem;
    }
    
    .dropdown-item-modern small {
        font-size: 0.68rem;
    }
}

@media (max-width: 576px) {
    .dropdown-menu-modern {
        min-width: 220px;
        max-width: 240px;
        max-height: 220px !important;
        margin-bottom: 0.35rem !important;
        border-radius: 12px !important;
    }
    
    .dropdown-menu-modern::before {
        border-radius: 12px 12px 0 0;
    }
    
    .dropdown-menu-modern::after {
        border-radius: 0 0 12px 12px;
    }
    
    .dropdown-item-modern {
        padding: 0.5rem 0.65rem;
        gap: 0.55rem;
    }
    
    .dropdown-item-modern i {
        width: 26px;
        height: 26px;
        font-size: 1rem;
    }
}

/* Viewport protection */
@media (max-height: 700px) {
    .dropdown-menu-modern {
        max-height: 220px !important;
    }
}

@media (max-height: 600px) {
    .dropdown-menu-modern {
        max-height: 180px !important;
    }
}

@media (max-height: 500px) {
    .dropdown-menu-modern {
        max-height: 150px !important;
    }
}

/* Ensure tidak keluar viewport */
.dropdown-menu-modern {
    max-width: min(280px, 90vw);
}

/* Override Bootstrap */
.dropup .dropdown-menu {
    bottom: 100% !important;
    top: auto !important;
    margin-top: 0 !important;
}

/* CRITICAL: Remove any conflicting styles */
.dropdown-menu-modern,
.dropdown-menu-modern * {
    box-sizing: border-box;
}

/* CRITICAL: Clip everything inside */
.dropdown-menu-modern > li,
.dropdown-menu-modern > ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

/* CRITICAL: No background images that can overflow */
.dropdown-menu-modern {
    background-image: none !important;
    background-size: auto !important;
    background-attachment: scroll !important;
}
</style>

<div class="container py-4">
    <!-- Welcome Section -->
    <div class="welcome-section-modern fade-in-up">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="welcome-content">
                    <div class="greeting-text-modern">
                        <span class="greeting-icon-modern"><?= $greeting_icon ?></span>
                        <?= $greeting ?>, Admin!
                    </div>
                    <div class="welcome-user-name-modern">
                        <?= htmlspecialchars($_SESSION['nama']) ?>
                    </div>
                    <p class="welcome-message-modern">
                        <i class="fas fa-quote-left me-2"></i>
                        Selamat datang di dashboard admin perpustakaan. 
                        <?= ($stats['pinjam_hari_ini'] ?? 0) > 0 ? "Ada <strong>{$stats['pinjam_hari_ini']} peminjaman</strong> hari ini." : "Belum ada peminjaman hari ini." ?>
                        <i class="fas fa-quote-right ms-2"></i>
                    </p>
                    <div class="role-badge-modern">
                        <i class="fas fa-shield-alt"></i>
                        <span>Administrator Sistem</span>
                        <span class="ms-2">•</span>
                        <span class="ms-2"><?= date('d M Y') ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="time-widget-modern">
                    <div class="time-display">
                        <i class="fas fa-clock me-2"></i>
                        <span id="current-time"><?= date('H:i:s') ?></span>
                    </div>
                    <div class="date-display">
                        <?= formatTanggalIndonesiaDashboard(date('Y-m-d')) ?>
                    </div>
                    <div class="status-badge-modern">
                        <i class="fas fa-check-circle me-1"></i>
                        <?php
                        $library_status = getLibraryStatusMessageDashboard();
                        echo htmlspecialchars($library_status['message']);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error Database:</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards - 6 KOLOM SEJAJAR -->
    <div class="stats-grid-modern">
        <!-- TOTAL BUKU -->
        <div class="stats-card-modern fade-in-up stagger-delay-1">
            <i class="fas fa-book" style="color: #667eea;"></i>
            <h2><?= number_format($stats['total_buku'] ?? 0, 0, ',', '.') ?></h2>
            <p>Total Buku</p>
            <div class="stats-sub">
                <i class="fas fa-check-circle text-success me-1"></i>
                <?= $stats['buku_tersedia'] ?? 0 ?> tersedia
            </div>
        </div>
        
        <!-- SEDANG DIPINJAM -->
        <div class="stats-card-modern fade-in-up stagger-delay-2">
            <i class="fas fa-hand-holding-heart" style="color: #11998e;"></i>
            <h2><?= number_format($stats['buku_dipinjam'] ?? 0, 0, ',', '.') ?></h2>
            <p>Sedang Dipinjam</p>
            <div class="stats-sub">
                <i class="fas fa-clock text-warning me-1"></i>
                <?= count($due_soon) ?> hampir jatuh tempo
            </div>
        </div>
        
        <!-- TERLAMBAT -->
        <div class="stats-card-modern fade-in-up stagger-delay-3">
            <i class="fas fa-exclamation-triangle" style="color: #f46b45;"></i>
            <h2><?= number_format($stats['buku_terlambat'] ?? 0, 0, ',', '.') ?></h2>
            <p>Terlambat</p>
            <div class="stats-sub">
                <i class="fas fa-calendar-times text-danger me-1"></i>
                <?= count($overdue_books) ?> terlambat aktif
            </div>
        </div>
        
        <!-- TOTAL ANGGOTA -->
        <div class="stats-card-modern fade-in-up stagger-delay-4">
            <i class="fas fa-users" style="color: #36b9cc;"></i>
            <h2><?= number_format($stats['total_anggota'] ?? 0, 0, ',', '.') ?></h2>
            <p>Total Anggota</p>
            <div class="stats-sub">
                <i class="fas fa-user-plus text-info me-1"></i>
                <?= $stats['anggota_aktif'] ?? 0 ?> aktif
            </div>
        </div>
        
        <!-- PEMINJAMAN HARI INI -->
        <div class="stats-card-modern fade-in-up stagger-delay-5">
            <i class="fas fa-exchange-alt" style="color: #8e2de2;"></i>
            <h2><?= number_format($stats['pinjam_hari_ini'] ?? 0, 0, ',', '.') ?></h2>
            <p>Peminjaman Hari Ini</p>
            <div class="stats-sub">
                <i class="fas fa-calendar-day me-1" style="color: #8e2de2;"></i>
                <?= date('d M Y') ?>
            </div>
        </div>
        
        <!-- TOTAL DENDA - SEKARANG SEJAJAR DENGAN LAINNYA -->
        <div class="stats-card-modern fade-in-up stagger-delay-6">
            <i class="fas fa-money-bill-wave" style="color: #FF9800;"></i>
            <h2><?= formatRupiah($stats['total_denda'] ?? 0) ?></h2>
            <p>Total Denda</p>
            <div class="stats-sub">
                <i class="fas fa-coins me-1" style="color: #FF9800;"></i>
                <?= ($stats['total_denda'] ?? 0) > 0 ? 'Terkumpul' : 'Tidak ada denda' ?>
            </div>
        </div>
    </div>

    
<!-- Quick Actions Section -->
<div class="quick-actions-section">
    <div class="section-title-modern fade-in-up">
        <i class="fas fa-bolt"></i>
        AKSI CEPAT
    </div>
    
    <div class="actions-grid-modern">
        <!-- KELOLA - DENGAN DROPDOWN -->
        <div class="action-card-modern fade-in-up">
            <div class="action-icon-wrapper-modern">
                <i class="fas fa-sync"></i>
            </div>
            <h6>Kelola</h6>
            <p>Mengelola data buku, penerbit, kategori, dan rak</p>
            
            <!-- Dropdown Toggle Button -->
            <div class="dropup">
                <button class="action-btn-modern dropdown-toggle-modern" type="button" id="dropdownKelola" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <i class="fas fa-th-large me-2"></i>Pilih Menu
                    <i class="fas fa-chevron-up ms-2"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="dropdownKelola">
                <li>
                    <a class="dropdown-item-modern" href="buku/index.php">
                        <i class="fas fa-book text-primary"></i>
                        <div>
                            <strong>Kelola Buku</strong>
                            <small>Tambah, edit, hapus buku</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item-modern" href="penerbit/index.php">
                        <i class="fas fa-building text-success"></i>
                        <div>
                            <strong>Penerbit</strong>
                            <small>Manajemen penerbit</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item-modern" href="kategori/index.php">
                        <i class="fas fa-tags text-warning"></i>
                        <div>
                            <strong>Kategori</strong>
                            <small>Kelola kategori buku</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item-modern" href="rak/index.php">
                        <i class="fas fa-layer-group text-info"></i>
                        <div>
                            <strong>Rak Buku</strong>
                            <small>Manajemen rak perpustakaan</small>
                        </div>
                    </a>
                </li>
            </ul>
            </div>
        </div>

        <!-- KELOLA USERS - DENGAN DROPDOWN -->
        <div class="action-card-modern fade-in-up">
            <div class="action-icon-wrapper-modern">
                <i class="fas fa-users-cog"></i>
            </div>
            <h6>Kelola Users</h6>
            <p>Manajemen data anggota dan petugas perpustakaan</p>
            
            <!-- Dropdown Toggle Button -->
            <div class="dropup">
                <button class="action-btn-modern dropdown-toggle-modern" type="button" id="dropdownUsers" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    <i class="fas fa-users me-2"></i>Pilih User
                    <i class="fas fa-chevron-up ms-2"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="dropdownUsers">
                <li>
                    <a class="dropdown-item-modern" href="users/anggota.php">
                        <i class="fas fa-user text-success"></i>
                        <div>
                            <strong>Anggota</strong>
                            <small>Kelola data anggota perpustakaan</small>
                        </div>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item-modern" href="users/petugas.php">
                        <i class="fas fa-user-tie text-info"></i>
                        <div>
                            <strong>Petugas</strong>
                            <small>Kelola data petugas/admin</small>
                        </div>
                    </a>
                </li>
            </ul>
            </div>
        </div>

        <!-- TRANSAKSI - SEKARANG DENGAN DROPDOWN -->
<div class="action-card-modern fade-in-up">
    <div class="action-icon-wrapper-modern">
        <i class="fas fa-exchange-alt"></i>
    </div>
    <h6>Transaksi</h6>
    <p>Kelola peminjaman, pengembalian, dan perpanjangan buku</p>
    
    <!-- Dropdown Toggle Button -->
    <div class="dropup">
        <button class="action-btn-modern dropdown-toggle-modern" type="button" id="dropdownTransaksi" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
            <i class="fas fa-exchange-alt me-2"></i>Pilih Transaksi
            <i class="fas fa-chevron-up ms-2"></i>
        </button>
        
        <!-- Dropdown Menu -->
        <ul class="dropdown-menu dropdown-menu-modern" aria-labelledby="dropdownTransaksi">
            <li>
                <a class="dropdown-item-modern" href="transaksi/peminjaman.php">
                    <i class="fas fa-hand-holding-heart text-primary"></i>
                    <div>
                        <strong>Peminjaman</strong>
                        <small>Proses peminjaman buku baru</small>
                    </div>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item-modern" href="transaksi/pengembalian.php">
                    <i class="fas fa-undo text-success"></i>
                    <div>
                        <strong>Pengembalian</strong>
                        <small>Proses pengembalian buku</small>
                    </div>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item-modern" href="transaksi/perpanjangan.php">
                    <i class="fas fa-clock text-warning"></i>
                    <div>
                        <strong>Perpanjangan</strong>
                        <small>Perpanjang masa peminjaman</small>
                    </div>
                </a>
            </li>
        </ul>
    </div>
</div>

        <!-- LAPORAN - TETAP SAMA -->
        <div class="action-card-modern fade-in-up">
            <div class="action-icon-wrapper-modern">
                <i class="fas fa-chart-bar"></i>
            </div>
            <h6>Laporan & Analisis</h6>
            <p>Statistik lengkap dan laporan perpustakaan</p>
            <a href="laporan/" class="action-btn-modern">
                <i class="fas fa-chart-line me-2"></i>Lihat Laporan
            </a>
        </div>
    </div>
</div>

    <!-- Main Content Grid - DENGAN TINGGI FIX -->
    <div class="content-grid-modern">
        <!-- Recent Activity -->
        <div class="fade-in-up">
            <div class="card-modern">
                <div class="card-header-modern">
                    <h6>
                        <i class="fas fa-history"></i>
                        AKTIVITAS TERBARU
                    </h6>
                </div>
                <div class="card-body-scrollable" id="activity-scroll">
                    <?php if (!empty($recent_peminjaman)): ?>
                        <?php foreach ($recent_peminjaman as $pinjam): ?>
                            <div class="activity-item-modern">
                                <div class="activity-icon-modern <?= $pinjam['status'] === 'dipinjam' ? 'warning' : 'success' ?>">
                                    <i class="fas fa-<?= $pinjam['status'] === 'dipinjam' ? 'arrow-right' : 'arrow-left' ?>"></i>
                                </div>
                                <div class="activity-content-modern">
                                    <div class="name"><?= htmlspecialchars($pinjam['nama']) ?></div>
                                    <div class="book"><?= htmlspecialchars(substr($pinjam['judul'], 0, 45)) ?><?= strlen($pinjam['judul']) > 45 ? '...' : '' ?></div>
                                    <?php if (!empty($pinjam['nama_penerbit'])): ?>
                                        <div class="publisher">
                                            <i class="fas fa-building me-1"></i><?= htmlspecialchars($pinjam['nama_penerbit']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="activity-badge-modern <?= $pinjam['status'] === 'dipinjam' ? 'warning' : 'success' ?>">
                                    <?= $pinjam['status'] === 'dipinjam' ? 'PINJAM' : 'KEMBALI' ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-modern">
                            <i class="fas fa-inbox fa-2x"></i>
                            <h6>Belum ada aktivitas hari ini</h6>
                            <p class="small mt-2">Semua transaksi akan muncul di sini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications & Popular Books -->
        <div class="fade-in-up">
            <div class="card-modern">
                <div class="card-header-modern">
                    <h6>
                        <i class="fas fa-bell"></i>
                        PERINGATAN & NOTIFIKASI
                    </h6>
                </div>
                <div class="notifications-container">
                    <!-- ALERTS CONTAINER DENGAN SCROLL KHUSUS -->
                    <div class="notifications-alerts-container">
                        <!-- Overdue Books Alert -->
                        <?php if (!empty($overdue_books)): ?>
                            <div class="alert-card-modern alert-danger-modern">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="alert-content">
                                    <div class="alert-title">
                                        <i class="fas fa-book me-1"></i>
                                        <?= count($overdue_books) ?> Buku Terlambat!
                                    </div>
                                    <div class="alert-details">
                                        <?php foreach (array_slice($overdue_books, 0, 3) as $overdue): ?>
                                            <div class="mb-1">
                                                • <strong><?= htmlspecialchars($overdue['nama']) ?></strong> - 
                                                <span class="badge bg-danger"><?= $overdue['hari_terlambat'] ?> hari terlambat</span>
                                                <small class="text-muted ms-2"><?= htmlspecialchars(substr($overdue['judul'], 0, 25)) ?>...</small>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($overdue_books) > 3): ?>
                                            <div class="mt-2 text-muted small">
                                                <i class="fas fa-ellipsis-h me-1"></i>
                                                dan <?= count($overdue_books) - 3 ?> lainnya...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Due Soon Alert -->
                        <?php if (!empty($due_soon)): ?>
                            <div class="alert-card-modern alert-warning-modern">
                                <i class="fas fa-clock"></i>
                                <div class="alert-content">
                                    <div class="alert-title">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?= count($due_soon) ?> Buku Akan Jatuh Tempo
                                    </div>
                                    <div class="alert-details">
                                        <?php foreach (array_slice($due_soon, 0, 2) as $due): ?>
                                            <div class="mb-1">
                                                • <strong><?= htmlspecialchars($due['nama']) ?></strong> - 
                                                <span class="badge bg-warning text-dark"><?= $due['hari_sisa'] ?> hari lagi</span>
                                                <small class="text-muted ms-2"><?= date('d M', strtotime($due['tanggal_kembali'])) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Total Denda Alert -->
                        <?php if (($stats['total_denda'] ?? 0) > 0): ?>
                            <div class="alert-card-modern alert-info-modern">
                                <i class="fas fa-money-bill-wave"></i>
                                <div class="alert-content">
                                    <div class="alert-title">
                                        <i class="fas fa-coins me-1"></i>
                                        Total Denda Terkumpul
                                    </div>
                                    <div class="alert-details">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <strong style="font-size: 1.2rem;"><?= formatRupiah($stats['total_denda']) ?></strong>
                                            <span class="badge bg-info"><?= count($overdue_books ?? []) ?> pelanggar</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- All Good Alert -->
                        <?php if (empty($overdue_books) && empty($due_soon) && ($stats['total_denda'] ?? 0) == 0): ?>
                            <div class="alert-card-modern alert-success-modern">
                                <i class="fas fa-check-circle"></i>
                                <div class="alert-content">
                                    <div class="alert-title">
                                        <i class="fas fa-thumbs-up me-1"></i>
                                        Semua Sistem Berjalan Normal
                                    </div>
                                    <div class="alert-details">
                                        Tidak ada buku terlambat dan tidak ada denda yang perlu ditagih.
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Popular Books Section - TANPA SCROLL -->
                    <?php if (!empty($popular_books)): ?>
                        <div class="popular-books-section">
                            <!-- TITLE STICKY -->
                            <div class="popular-books-title">
                                <i class="fas fa-fire text-danger"></i>
                                BUKU POPULER BULAN INI
                            </div>
                            
                            <!-- WRAPPER UNTUK BOOK ITEMS -->
                            <div class="popular-books-list">
                                <?php foreach (array_slice($popular_books, 0, 5) as $idx => $popular): ?>
                                    <div class="popular-book-item-modern">
                                        <div class="popular-rank-modern">
                                            #<?= $idx + 1 ?>
                                        </div>
                                        <div class="popular-content-modern">
                                            <div class="title"><?= htmlspecialchars($popular['judul']) ?></div>
                                            <div class="meta">
                                                <span class="meta-item">
                                                    <i class="fas fa-bookmark text-primary"></i>
                                                    <span><?= $popular['total_pinjam'] ?>x dipinjam</span>
                                                </span>
                                                <?php if (!empty($popular['nama_penerbit'])): ?>
                                                    <span class="meta-item">
                                                        <i class="fas fa-building text-secondary"></i>
                                                        <span><?= htmlspecialchars($popular['nama_penerbit']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($popular['pengarang'])): ?>
                                                    <span class="meta-item">
                                                        <i class="fas fa-user-pen text-success"></i>
                                                        <span><?= htmlspecialchars($popular['pengarang']) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time clock update
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Initialize clock
setInterval(updateClock, 1000);
updateClock();

// Dashboard animations and interactions
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to stats cards
    const statCards = document.querySelectorAll('.stats-card-modern');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    
    
    // Auto-hide scrollbar jika tidak perlu
    const activityScroll = document.getElementById('activity-scroll');
    if (activityScroll) {
        // Cek apakah konten lebih pendek dari container
        const checkScrollbar = () => {
            if (activityScroll.scrollHeight <= activityScroll.clientHeight) {
                activityScroll.classList.add('no-scroll');
            } else {
                activityScroll.classList.remove('no-scroll');
            }
        };
        
        checkScrollbar();
        window.addEventListener('resize', checkScrollbar);
    }
    
    // Smooth scroll untuk activity items
    const activityItems = document.querySelectorAll('.activity-item-modern');
    activityItems.forEach(item => {
        item.addEventListener('click', function() {
            // Highlight item yang diklik
            activityItems.forEach(i => i.style.background = '');
            this.style.background = '#e8f4ff';
            setTimeout(() => {
                this.style.background = '';
            }, 1000);
        });
    });
    
    // Format Rupiah untuk stats card jika belum di-format
    const dendaElements = document.querySelectorAll('.stats-card-modern:last-child h2');
    dendaElements.forEach(el => {
        const text = el.textContent;
        if (text.includes('Rp') && !text.includes('.')) {
            // Format angka jika belum diformat
            const amount = text.replace('Rp', '').replace(/\s/g, '');
            if (!isNaN(amount)) {
                el.textContent = 'Rp' + parseInt(amount).toLocaleString('id-ID');
            }
        }
    });
});

// CARI BAGIAN <script> DI DASHBOARD.PHP (PALING BAWAH, SEKITAR BARIS 700-800)
// TAMBAHKAN KODE INI SETELAH FUNGSI updateClock() ATAU DI DALAM document.addEventListener('DOMContentLoaded')

// ==================== 
// NOTIFICATION SCROLL ENHANCEMENT
// ====================

// Fungsi untuk enhance notification scroll
function enhanceNotificationScroll() {
    const notificationContainer = document.querySelector('.notifications-alerts-container');
    
    if (!notificationContainer) {
        console.log('❌ Notification container not found');
        return;
    }
    
    console.log('✅ Notification scroll enhancement loaded');
    
    // Check if scrollable
    function checkScrollable() {
        const isScrollable = notificationContainer.scrollHeight > notificationContainer.clientHeight;
        
        if (isScrollable) {
            notificationContainer.setAttribute('data-scrollable', 'true');
            console.log('📜 Notifications are scrollable');
        } else {
            notificationContainer.removeAttribute('data-scrollable');
            console.log('📄 Notifications fit in container');
        }
        
        return isScrollable;
    }
    
    // Check scroll position (top/middle/bottom)
    function checkScrollPosition() {
        const scrollTop = notificationContainer.scrollTop;
        const scrollHeight = notificationContainer.scrollHeight;
        const clientHeight = notificationContainer.clientHeight;
        
        // At top
        if (scrollTop <= 5) {
            notificationContainer.classList.add('at-top');
            notificationContainer.classList.remove('at-bottom', 'scrolling');
        } 
        // At bottom (with 5px tolerance)
        else if (scrollTop + clientHeight >= scrollHeight - 5) {
            notificationContainer.classList.add('at-bottom');
            notificationContainer.classList.remove('at-top', 'scrolling');
        } 
        // Middle (scrolling)
        else {
            notificationContainer.classList.add('scrolling');
            notificationContainer.classList.remove('at-top', 'at-bottom');
        }
    }
    
    // Add click effects to alert cards
    const alertCards = notificationContainer.querySelectorAll('.alert-card-modern');
    
    alertCards.forEach((card, index) => {
        card.addEventListener('click', function(e) {
            // Prevent if clicking on links inside
            if (e.target.tagName === 'A') return;
            
            // Highlight effect
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
            
            // Log click
            const alertType = this.classList.contains('alert-danger-modern') ? 'DANGER' :
                            this.classList.contains('alert-warning-modern') ? 'WARNING' :
                            this.classList.contains('alert-info-modern') ? 'INFO' : 'SUCCESS';
            
            console.log(`🔔 Alert clicked: ${alertType} (${index + 1}/${alertCards.length})`);
            
            // Optional: scroll to this card smoothly if not fully visible
            const rect = this.getBoundingClientRect();
            const containerRect = notificationContainer.getBoundingClientRect();
            
            if (rect.bottom > containerRect.bottom || rect.top < containerRect.top) {
                this.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            }
        });
        
        // Hover effect enhancement
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
        });
    });
    
    // Add scroll event listener
    notificationContainer.addEventListener('scroll', function() {
        checkScrollPosition();
        
        // Add subtle pulsing to scrollbar while scrolling
        this.style.scrollbarColor = '#667eea #f8f9fa';
        
        clearTimeout(this.scrollTimeout);
        this.scrollTimeout = setTimeout(() => {
            this.style.scrollbarColor = '#cbd5e0 #f8f9fa';
        }, 500);
    });
    
    // Keyboard navigation
    notificationContainer.setAttribute('tabindex', '0');
    
    notificationContainer.addEventListener('keydown', function(e) {
        const scrollAmount = 80;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.scrollBy({ top: scrollAmount, behavior: 'smooth' });
                console.log('⬇️ Scroll down');
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
                console.log('⬆️ Scroll up');
                break;
                
            case 'Home':
                e.preventDefault();
                this.scrollTo({ top: 0, behavior: 'smooth' });
                console.log('🏠 Scroll to top');
                break;
                
            case 'End':
                e.preventDefault();
                this.scrollTo({ top: this.scrollHeight, behavior: 'smooth' });
                console.log('🔚 Scroll to bottom');
                break;
                
            case 'PageDown':
                e.preventDefault();
                this.scrollBy({ top: this.clientHeight * 0.8, behavior: 'smooth' });
                break;
                
            case 'PageUp':
                e.preventDefault();
                this.scrollBy({ top: -this.clientHeight * 0.8, behavior: 'smooth' });
                break;
        }
    });
    
    // Enhanced mouse wheel scrolling
    let wheelTimeout;
    notificationContainer.addEventListener('wheel', function(e) {
        // Clear existing timeout
        clearTimeout(wheelTimeout);
        
        // Add scrolling class for visual feedback
        this.classList.add('is-scrolling');
        
        // Remove class after scrolling stops
        wheelTimeout = setTimeout(() => {
            this.classList.remove('is-scrolling');
        }, 150);
    }, { passive: true });
    
    // Auto-scroll hint animation (shows users content is scrollable)
    function showScrollHint() {
        if (checkScrollable() && notificationContainer.scrollTop === 0) {
            console.log('💡 Showing scroll hint...');
            
            // Gentle bounce animation
            notificationContainer.scrollTo({ top: 40, behavior: 'smooth' });
            
            setTimeout(() => {
                notificationContainer.scrollTo({ top: 0, behavior: 'smooth' });
            }, 800);
        }
    }
    
    // Show hint after page load (only once)
    let hintShown = sessionStorage.getItem('scrollHintShown');
    if (!hintShown && checkScrollable()) {
        setTimeout(() => {
            showScrollHint();
            sessionStorage.setItem('scrollHintShown', 'true');
        }, 2500); // After 2.5 seconds
    }
    
    // Intersection Observer for fade-in animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, {
        root: notificationContainer,
        threshold: 0.1,
        rootMargin: '20px'
    });
    
    alertCards.forEach(card => {
        observer.observe(card);
    });
    
    // Initial checks
    checkScrollable();
    checkScrollPosition();
    
    // Recheck on window resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            checkScrollable();
            checkScrollPosition();
            console.log('📐 Window resized, rechecking scroll');
        }, 250);
    });
    
    // Summary log
    console.log(`📊 Total alerts: ${alertCards.length}`);
    console.log(`📏 Container height: ${notificationContainer.clientHeight}px`);
    console.log(`📜 Content height: ${notificationContainer.scrollHeight}px`);
    console.log(`🎯 Scrollable: ${checkScrollable() ? 'YES' : 'NO'}`);


}

// Call the enhancement function when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceNotificationScroll);
} else {
    enhanceNotificationScroll();
}

// ==================== 
// DROPDOWN MOUSE WHEEL SCROLL - ENABLE SCROLL MOUSE
// GANTI JAVASCRIPT DROPDOWN DI DASHBOARD.PHP
// ====================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dropdown with mouse scroll initializing...');
    
    const dropdownButtons = document.querySelectorAll('.dropdown-toggle-modern');
    
    dropdownButtons.forEach((button) => {
        const dropdownId = button.getAttribute('id');
        const dropdownMenu = document.querySelector(`[aria-labelledby="${dropdownId}"]`);
        const dropupContainer = button.closest('.dropup');
        
        if (!dropdownMenu || !dropupContainer) {
            console.warn(`⚠️ Missing elements for: ${dropdownId}`);
            return;
        }
        
        console.log(`✅ Found dropdown: ${dropdownId}`);
        
        // Function to check viewport cutoff
        function checkViewportCutoff() {
            const buttonRect = button.getBoundingClientRect();
            const dropdownHeight = dropdownMenu.offsetHeight || 260;
            const spaceAbove = buttonRect.top;
            
            console.log(`📊 [${dropdownId}] Space above: ${Math.round(spaceAbove)}px, needed: ${dropdownHeight}px`);
            
            if (spaceAbove < dropdownHeight + 20) {
                console.log(`⚠️ [${dropdownId}] Not enough space! Forcing dropdown`);
                dropupContainer.classList.add('force-dropdown');
                return false;
            } else {
                dropupContainer.classList.remove('force-dropdown');
                return true;
            }
        }
        
        // Function to update scroll position
        function updateScrollPosition() {
            const scrollTop = dropdownMenu.scrollTop;
            const scrollHeight = dropdownMenu.scrollHeight;
            const clientHeight = dropdownMenu.clientHeight;
            const isScrollable = scrollHeight > clientHeight;
            
            if (isScrollable) {
                dropdownMenu.setAttribute('data-scrollable', 'true');
            } else {
                dropdownMenu.removeAttribute('data-scrollable');
            }
            
            dropdownMenu.classList.remove('at-top', 'at-bottom', 'is-scrolling');
            
            if (scrollTop <= 5) {
                dropdownMenu.classList.add('at-top');
            } else if (scrollTop + clientHeight >= scrollHeight - 5) {
                dropdownMenu.classList.add('at-bottom');
            } else {
                dropdownMenu.classList.add('is-scrolling');
            }
        }
        
        // Event: Before dropdown opens
        button.addEventListener('show.bs.dropdown', function(e) {
            console.log(`🎯 [${dropdownId}] Opening...`);
            this.style.boxShadow = '0 8px 20px rgba(102, 126, 234, 0.3)';
            
            setTimeout(() => checkViewportCutoff(), 10);
            
            if (dropdownMenu) {
                dropdownMenu.scrollTop = 0;
                console.log(`📜 [${dropdownId}] Reset scroll to top`);
            }
        });
        
        // Event: After dropdown opened
        button.addEventListener('shown.bs.dropdown', function() {
            console.log(`🎯 [${dropdownId}] Fully opened`);
            
            if (!dropdownMenu) return;
            
            setTimeout(() => {
                dropdownMenu.scrollTop = 0;
                updateScrollPosition();
                checkViewportCutoff();
            }, 50);
            
            // Highlight first item
            const firstItem = dropdownMenu.querySelector('.dropdown-item-modern');
            if (firstItem) {
                firstItem.style.background = 'linear-gradient(135deg, #f8faff 0%, #eef2ff 100%)';
                firstItem.style.transform = 'translateX(5px)';
                
                setTimeout(() => {
                    firstItem.style.background = '';
                    firstItem.style.transform = '';
                }, 1200);
                
                const itemName = firstItem.querySelector('strong')?.textContent || 'Unknown';
                console.log(`💡 [${dropdownId}] Highlighted: ${itemName}`);
            }
            
            const allItems = dropdownMenu.querySelectorAll('.dropdown-item-modern');
            console.log(`📋 [${dropdownId}] Items: ${allItems.length}`);
        });
        
        // Event: Dropdown closing
        button.addEventListener('hide.bs.dropdown', function() {
            console.log(`🎯 [${dropdownId}] Closing...`);
            this.style.boxShadow = '';
        });
        
        // ==================== 
        // PENTING: ENABLE MOUSE WHEEL SCROLL
        // ====================
        
        dropdownMenu.addEventListener('wheel', function(e) {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            const delta = e.deltaY;
            
            // Check if at boundaries
            const atTop = scrollTop === 0;
            const atBottom = scrollTop + clientHeight >= scrollHeight;
            
            // Prevent page scroll when:
            // - Scrolling down and not at bottom
            // - Scrolling up and not at top
            if ((delta > 0 && !atBottom) || (delta < 0 && !atTop)) {
                e.preventDefault();
                e.stopPropagation();
                
                // Manual scroll dengan smooth animation
                this.scrollTop += delta;
                
                console.log(`🖱️ Mouse scroll: ${delta > 0 ? 'down ⬇️' : 'up ⬆️'}`);
            }
            
            updateScrollPosition();
        }, { passive: false }); // PENTING: passive: false biar preventDefault works
        
        // ==================== 
        // SCROLL EVENT LISTENER
        // ====================
        
        dropdownMenu.addEventListener('scroll', function() {
            updateScrollPosition();
            
            // Visual feedback
            this.style.scrollbarColor = '#667eea #f8f9fa';
            
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = setTimeout(() => {
                this.style.scrollbarColor = '#cbd5e0 #f8f9fa';
            }, 500);
        });
        
        // Keyboard navigation
        dropdownMenu.setAttribute('tabindex', '0');
        
        dropdownMenu.addEventListener('keydown', function(e) {
            const scrollAmount = 60;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.scrollBy({ top: scrollAmount, behavior: 'smooth' });
                    console.log('⬇️ Keyboard: Arrow down');
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
                    console.log('⬆️ Keyboard: Arrow up');
                    break;
                case 'Home':
                    e.preventDefault();
                    this.scrollTo({ top: 0, behavior: 'smooth' });
                    console.log('🏠 Keyboard: Home');
                    break;
                case 'End':
                    e.preventDefault();
                    this.scrollTo({ top: this.scrollHeight, behavior: 'smooth' });
                    console.log('🔚 Keyboard: End');
                    break;
                case 'PageDown':
                    e.preventDefault();
                    this.scrollBy({ top: this.clientHeight * 0.8, behavior: 'smooth' });
                    break;
                case 'PageUp':
                    e.preventDefault();
                    this.scrollBy({ top: -this.clientHeight * 0.8, behavior: 'smooth' });
                    break;
            }
        });
        
        // Hover effects on button
        button.addEventListener('mouseenter', function() {
            if (!this.classList.contains('show')) {
                this.style.transform = 'translateY(-2px)';
            }
        });
        
        button.addEventListener('mouseleave', function() {
            if (!this.classList.contains('show')) {
                this.style.transform = '';
            }
        });
        
        // Re-check on window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (dropdownMenu.classList.contains('show')) {
                    checkViewportCutoff();
                }
            }, 250);
        });
    });
    
    // ==================== 
    // RIPPLE EFFECT ON ITEMS
    // ====================
    
    const dropdownItems = document.querySelectorAll('.dropdown-item-modern');
    
    dropdownItems.forEach((item, index) => {
        item.addEventListener('click', function(e) {
            // Create ripple
            const ripple = document.createElement('span');
            ripple.style.position = 'absolute';
            ripple.style.borderRadius = '50%';
            ripple.style.background = 'rgba(102, 126, 234, 0.3)';
            ripple.style.width = '20px';
            ripple.style.height = '20px';
            ripple.style.left = e.offsetX + 'px';
            ripple.style.top = e.offsetY + 'px';
            ripple.style.transform = 'translate(-50%, -50%) scale(0)';
            ripple.style.animation = 'ripple 0.6s ease-out';
            ripple.style.pointerEvents = 'none';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
            
            const menuName = this.querySelector('strong')?.textContent;
            console.log(`📋 Clicked: ${menuName} (${index + 1}/${dropdownItems.length})`);
        });
        
        // Keyboard support
        item.setAttribute('tabindex', '0');
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    console.log(`✅ Enhanced ${dropdownButtons.length} dropdowns with mouse scroll support`);
    console.log(`📋 Total ${dropdownItems.length} menu items`);

    // ==================== 
    // DROPDOWN TRANSAKSI - SAMA DENGAN DROPDOWN LAIN
    // ====================
    
    const dropdownTransaksi = document.querySelector('#dropdownTransaksi');
    const dropdownTransaksiMenu = document.querySelector('[aria-labelledby="dropdownTransaksi"]');
    const dropupTransaksiContainer = dropdownTransaksi?.closest('.dropup');
    
    if (dropdownTransaksi && dropdownTransaksiMenu && dropupTransaksiContainer) {
        console.log('✅ Found dropdown: Transaksi');
        
        // Function to check viewport cutoff
        function checkTransaksiViewport() {
            const buttonRect = dropdownTransaksi.getBoundingClientRect();
            const dropdownHeight = dropdownTransaksiMenu.offsetHeight || 260;
            const spaceAbove = buttonRect.top;
            
            console.log(`📊 [Transaksi] Space above: ${Math.round(spaceAbove)}px, needed: ${dropdownHeight}px`);
            
            if (spaceAbove < dropdownHeight + 20) {
                console.log(`⚠️ [Transaksi] Not enough space! Forcing dropdown`);
                dropupTransaksiContainer.classList.add('force-dropdown');
                return false;
            } else {
                dropupTransaksiContainer.classList.remove('force-dropdown');
                return true;
            }
        }
        
        // Function to update scroll position
        function updateTransaksiScrollPosition() {
            const scrollTop = dropdownTransaksiMenu.scrollTop;
            const scrollHeight = dropdownTransaksiMenu.scrollHeight;
            const clientHeight = dropdownTransaksiMenu.clientHeight;
            const isScrollable = scrollHeight > clientHeight;
            
            if (isScrollable) {
                dropdownTransaksiMenu.setAttribute('data-scrollable', 'true');
            } else {
                dropdownTransaksiMenu.removeAttribute('data-scrollable');
            }
            
            dropdownTransaksiMenu.classList.remove('at-top', 'at-bottom', 'is-scrolling');
            
            if (scrollTop <= 5) {
                dropdownTransaksiMenu.classList.add('at-top');
            } else if (scrollTop + clientHeight >= scrollHeight - 5) {
                dropdownTransaksiMenu.classList.add('at-bottom');
            } else {
                dropdownTransaksiMenu.classList.add('is-scrolling');
            }
        }
        
        // Event: Before dropdown opens
        dropdownTransaksi.addEventListener('show.bs.dropdown', function(e) {
            console.log(`🎯 [Transaksi] Opening...`);
            this.style.boxShadow = '0 8px 20px rgba(244, 107, 69, 0.3)'; // Orange shadow untuk transaksi
            
            setTimeout(() => checkTransaksiViewport(), 10);
            
            if (dropdownTransaksiMenu) {
                dropdownTransaksiMenu.scrollTop = 0;
                console.log(`📜 [Transaksi] Reset scroll to top`);
            }
        });
        
        // Event: After dropdown opened
        dropdownTransaksi.addEventListener('shown.bs.dropdown', function() {
            console.log(`🎯 [Transaksi] Fully opened`);
            
            if (!dropdownTransaksiMenu) return;
            
            setTimeout(() => {
                dropdownTransaksiMenu.scrollTop = 0;
                updateTransaksiScrollPosition();
                checkTransaksiViewport();
            }, 50);
            
            // Highlight first item (Peminjaman)
            const firstItem = dropdownTransaksiMenu.querySelector('.dropdown-item-modern');
            if (firstItem) {
                firstItem.style.background = 'linear-gradient(135deg, #f8faff 0%, #eef2ff 100%)';
                firstItem.style.transform = 'translateX(5px)';
                
                setTimeout(() => {
                    firstItem.style.background = '';
                    firstItem.style.transform = '';
                }, 1200);
                
                console.log(`💡 [Transaksi] Highlighted: Peminjaman`);
            }
            
            const allItems = dropdownTransaksiMenu.querySelectorAll('.dropdown-item-modern');
            console.log(`📋 [Transaksi] Items: ${allItems.length}`);
        });
        
        // Event: Dropdown closing
        dropdownTransaksi.addEventListener('hide.bs.dropdown', function() {
            console.log(`🎯 [Transaksi] Closing...`);
            this.style.boxShadow = '';
        });
        
        // Mouse wheel scroll
        dropdownTransaksiMenu.addEventListener('wheel', function(e) {
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            const delta = e.deltaY;
            
            const atTop = scrollTop === 0;
            const atBottom = scrollTop + clientHeight >= scrollHeight;
            
            if ((delta > 0 && !atBottom) || (delta < 0 && !atTop)) {
                e.preventDefault();
                e.stopPropagation();
                this.scrollTop += delta;
                console.log(`🖱️ Transaksi scroll: ${delta > 0 ? 'down ⬇️' : 'up ⬆️'}`);
            }
            
            updateTransaksiScrollPosition();
        }, { passive: false });
        
        // Scroll event listener
        dropdownTransaksiMenu.addEventListener('scroll', function() {
            updateTransaksiScrollPosition();
            
            this.style.scrollbarColor = '#f46b45 #f8f9fa'; // Orange scrollbar
            
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = setTimeout(() => {
                this.style.scrollbarColor = '#cbd5e0 #f8f9fa';
            }, 500);
        });
        
        // Keyboard navigation
        dropdownTransaksiMenu.setAttribute('tabindex', '0');
        
        dropdownTransaksiMenu.addEventListener('keydown', function(e) {
            const scrollAmount = 60;
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.scrollBy({ top: scrollAmount, behavior: 'smooth' });
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.scrollBy({ top: -scrollAmount, behavior: 'smooth' });
                    break;
                case 'Home':
                    e.preventDefault();
                    this.scrollTo({ top: 0, behavior: 'smooth' });
                    break;
                case 'End':
                    e.preventDefault();
                    this.scrollTo({ top: this.scrollHeight, behavior: 'smooth' });
                    break;
            }
        });
        
        // Hover effects
        dropdownTransaksi.addEventListener('mouseenter', function() {
            if (!this.classList.contains('show')) {
                this.style.transform = 'translateY(-2px)';
            }
        });
        
        dropdownTransaksi.addEventListener('mouseleave', function() {
            if (!this.classList.contains('show')) {
                this.style.transform = '';
            }
        });
        
        // Re-check on window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (dropdownTransaksiMenu.classList.contains('show')) {
                    checkTransaksiViewport();
                }
            }, 250);
        });
        
        console.log('✅ Dropdown Transaksi initialized successfully');
    }

});

// CSS untuk ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: translate(-50%, -50%) scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>