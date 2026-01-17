<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('anggota');

include '../config/database.php';

// Get NIK untuk anggota yang login
$nik = getUserNIK();

if (!$nik) {
    die("Error: Data anggota tidak ditemukan. Silakan hubungi admin.");
}

// Get anggota statistics
try {
    $stats = getUserStats($nik);
    
    // Get borrowing status
    $borrowing_status = getMemberBorrowingStatus($nik);
    
    // Get anggota profile
    $stmt = $conn->prepare("SELECT * FROM anggota WHERE nik = ?");
    $stmt->execute([$nik]);
    $profile = $stmt->fetch();
    
    // Get recent borrowed books history dengan JOIN pengembalian untuk denda
    $stmt = $conn->prepare("
        SELECT b.judul, b.pengarang, p.tanggal_pinjam, p.tanggal_kembali, 
               p.status, pg.tanggal_pengembalian_aktual, pg.denda
        FROM peminjaman p
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.nik = ?
        ORDER BY p.tanggal_pinjam DESC
        LIMIT 5
    ");
    $stmt->execute([$nik]);
    $recent_borrowed = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error mengambil data: " . $e->getMessage();
    $stats = ['buku_dipinjam' => 0, 'total_riwayat' => 0, 'total_denda' => 0, 'buku_terlambat' => 0];
    $borrowing_status = ['borrowed_books' => [], 'can_borrow' => false, 'total_borrowed' => 0, 'overdue_count' => 0];
    $recent_borrowed = [];
    $profile = null;
}

$page_title = 'Dashboard Anggota';
$body_class = 'anggota-dashboard';

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body.anggota-dashboard {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    background-attachment: fixed !important;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
}

body.anggota-dashboard::before {
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

body.anggota-dashboard .container {
    position: relative;
    z-index: 1;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* Force remove all gaps */
body.anggota-dashboard .container > * {
    margin-top: 0 !important;
}

body.anggota-dashboard .container > *:first-child {
    margin-top: 1rem !important;
}

.welcome-hero {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 30px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 2rem 2.5rem;
    margin-bottom: 1rem !important;
    margin-top: 1rem !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

.welcome-hero h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff 0%, #ffd89b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
}

.welcome-hero .subtitle {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.95);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.welcome-hero .description {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
    margin-bottom: 0;
}

.datetime-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 1.5rem;
    color: white;
    text-align: center;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.datetime-card .date-text {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.datetime-card .time-text {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem 1.25rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    border: none;
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

.stat-card.blue { --stat-color-1: #667eea; --stat-color-2: #764ba2; }
.stat-card.green { --stat-color-1: #11998e; --stat-color-2: #38ef7d; }
.stat-card.red { --stat-color-1: #eb3349; --stat-color-2: #f45c43; }
.stat-card.yellow { --stat-color-1: #f093fb; --stat-color-2: #f5576c; }

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

.action-card {
    background: white;
    border-radius: 20px;
    padding: 1.75rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    transition: all 0.4s ease;
    height: 100%;
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    border-color: var(--action-color);
}

.action-card.primary { --action-color: #667eea; }
.action-card.success { --action-color: #38ef7d; }
.action-card.info { --action-color: #f093fb; }

.action-icon-circle {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--action-color), var(--action-color-2));
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.action-card:hover .action-icon-circle {
    transform: rotate(360deg) scale(1.1);
}

.action-icon-circle i {
    color: white;
    font-size: 2.2rem;
}

.action-card h6 {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
    color: #333;
}

.action-card p {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1.25rem;
}

.btn-action {
    background: linear-gradient(135deg, var(--action-color), var(--action-color-2));
    color: white;
    border: none;
    padding: 0.75rem 1.75rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-action:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    color: white;
}

.action-card.primary { --action-color: #667eea; --action-color-2: #764ba2; }
.action-card.success { --action-color: #11998e; --action-color-2: #38ef7d; }
.action-card.info { --action-color: #f093fb; --action-color-2: #f5576c; }

.info-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    margin-bottom: 0;
    height: 100%;
}

.info-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.25rem 1.5rem;
    color: white;
}

.info-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.2rem;
}

.book-item {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.book-item:hover {
    background: #f8f9ff;
    transform: translateX(5px);
}

.book-item:last-child {
    border-bottom: none;
}

.book-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 1.5rem;
    margin-right: 1rem;
    flex-shrink: 0;
}

.badge-custom {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
}

.empty-state {
    text-align: center;
    padding: 2.5rem 1.5rem;
}

.empty-state i {
    font-size: 3.5rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #999;
    font-size: 1rem;
    margin-bottom: 1.25rem;
}

.section-title {
    color: white;
    font-weight: 700;
    font-size: 1.6rem;
    margin-bottom: 1rem !important;
    margin-top: 0 !important;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.section-title i {
    margin-right: 0.5rem;
    color: #ffd89b;
}

/* Remove gaps between sections */
.row.g-4 {
    row-gap: 1rem !important;
    margin-bottom: 1rem !important;
}

.row.g-4.mb-4 {
    margin-bottom: 1rem !important;
}

@media (max-width: 768px) {
    .welcome-hero { 
        padding: 1.5rem 1.25rem;
        margin-bottom: 1.25rem;
    }
    .welcome-hero h1 { font-size: 2rem; }
    .stat-card { margin-bottom: 0; }
    .section-title { font-size: 1.4rem; }
    body.anggota-dashboard .container {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
}
</style>

<div class="container">
    <!-- Welcome Hero Section -->
    <div class="welcome-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1><i class="fas fa-book-reader"></i> Selamat Datang!</h1>
                <p class="subtitle">Halo, <?= htmlspecialchars($_SESSION['nama']) ?>! 👋</p>
                <p class="description">
                    <i class="fas fa-quote-left"></i> 
                    Jelajahi dunia pengetahuan melalui ribuan koleksi buku kami. 
                    Baca, pinjam, dan tingkatkan wawasanmu setiap hari!
                    <i class="fas fa-quote-right"></i>
                </p>
            </div>
            <div class="col-lg-4">
                <div class="datetime-card">
                    <div class="date-text">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?= formatTanggalIndonesia(date('Y-m-d')) ?>
                    </div>
                    <div class="time-text">
                        <i class="fas fa-clock"></i> 
                        <span id="current-time"><?= date('H:i') ?></span>
                    </div>
                    <div class="nik-text">WIB</div>
                    <?php if ($profile): ?>
                        <div class="nik-text mt-2">
                            <i class="fas fa-id-card me-1"></i>
                            NIK: <?= htmlspecialchars($profile['nik']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-number"><?= $stats['buku_dipinjam'] ?? 0 ?></div>
                <div class="stat-label">Sedang Dipinjam</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-number"><?= $stats['total_riwayat'] ?? 0 ?></div>
                <div class="stat-label">Total Riwayat</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?= $stats['buku_terlambat'] ?? 0 ?></div>
                <div class="stat-label">Terlambat</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card yellow">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-number" style="font-size: 1.6rem;">
                    <?= formatRupiah($stats['total_denda'] ?? 0) ?>
                </div>
                <div class="stat-label">Total Denda</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="section-title">
        <i class="fas fa-bolt"></i> Menu Cepat
    </h4>
    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="action-card primary">
                <div class="action-icon-circle">
                    <i class="fas fa-search"></i>
                </div>
                <h6>Cari Buku</h6>
                <p>Jelajahi koleksi buku perpustakaan kami</p>
                <a href="katalog/" class="btn btn-action">
                    <i class="fas fa-arrow-right me-2"></i>Katalog
                </a>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6">
            <div class="action-card success">
                <div class="action-icon-circle">
                    <i class="fas fa-history"></i>
                </div>
                <h6>Riwayat Peminjaman</h6>
                <p>Lihat semua riwayat peminjaman Anda</p>
                <a href="riwayat/peminjaman.php" class="btn btn-action">
                    <i class="fas fa-arrow-right me-2"></i>Lihat Riwayat
                </a>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6">
            <div class="action-card info">
                <div class="action-icon-circle">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h6>Profil Saya</h6>
                <p>Kelola profil dan informasi akun Anda</p>
                <a href="profil/" class="btn btn-action">
                    <i class="fas fa-arrow-right me-2"></i>Profil
                </a>
            </div>
        </div>
    </div>

    <!-- Info Cards -->
    <div class="row g-4">
        <!-- Currently Borrowed Books -->
        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5><i class="fas fa-book"></i> Buku yang Sedang Dipinjam</h5>
                </div>
                <div>
                    <?php if (!empty($borrowing_status['borrowed_books'])): ?>
                        <?php foreach ($borrowing_status['borrowed_books'] as $book): ?>
                            <div class="book-item d-flex align-items-center">
                                <div class="book-icon">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <div class="book-info flex-grow-1">
                                    <h6><?= htmlspecialchars($book['judul']) ?></h6>
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        Kembali: <?= formatTanggal($book['tanggal_kembali']) ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($book['status'] === 'overdue'): ?>
                                        <span class="badge badge-custom bg-danger">
                                            <i class="fas fa-exclamation-circle me-1"></i>Terlambat
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-custom bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Aktif
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>Tidak ada buku yang sedang dipinjam</p>
                            <a href="katalog/" class="btn btn-action" style="--action-color: #667eea; --action-color-2: #764ba2;">
                                <i class="fas fa-search me-2"></i>Cari Buku Sekarang
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent History -->
        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5><i class="fas fa-history"></i> Riwayat Terakhir</h5>
                </div>
                <div>
                    <?php if (!empty($recent_borrowed)): ?>
                        <?php foreach ($recent_borrowed as $history): ?>
                            <div class="book-item d-flex align-items-center">
                                <div class="book-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                                    <i class="fas fa-<?= $history['status'] === 'dikembalikan' ? 'check-circle' : 'clock' ?>"></i>
                                </div>
                                <div class="book-info flex-grow-1">
                                    <h6><?= htmlspecialchars(substr($history['judul'], 0, 35)) ?><?= strlen($history['judul']) > 35 ? '...' : '' ?></h6>
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= formatTanggal($history['tanggal_pinjam']) ?>
                                    </small>
                                    <?php if (!empty($history['denda'])): ?>
                                        <br><small style="color: #eb3349; font-weight: 600;">
                                            Denda: <?= formatRupiah($history['denda']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge badge-custom bg-<?= $history['status'] === 'dikembalikan' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($history['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>Belum ada riwayat peminjaman</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

setInterval(updateClock, 1000);
updateClock();

document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(50px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
});
</script>

<?php include '../includes/footer.php'; ?>