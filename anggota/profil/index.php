<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Profil Saya';
$body_class = 'anggota-profil';
include '../../config/database.php';

// Get user profile
try {
    $query = "
        SELECT u.*, a.nik, a.nama, a.alamat, a.no_hp, a.created_at as tanggal_daftar
        FROM users u
        LEFT JOIN anggota a ON u.id = a.user_id
        WHERE u.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('Data pengguna tidak ditemukan', 'error');
        redirect(SITE_URL . 'anggota/dashboard.php');
    }
    
    // Get borrowing statistics
    $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ?");
    $stmt->execute([$user['nik']]);
    $total_peminjaman = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
    $stmt->execute([$user['nik']]);
    $sedang_dipinjam = $stmt->fetchColumn();
    
    // UPDATED: Ambil total denda dari tabel pengembalian
    // Hanya denda yang belum dibayar (jika ada field status pembayaran)
    // Karena tidak ada field status pembayaran denda, kita ambil semua denda
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(pg.denda), 0) 
        FROM pengembalian pg
        INNER JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        WHERE p.nik = ? 
        AND pg.denda > 0
    ");
    $stmt->execute([$user['nik']]);
    $total_denda = $stmt->fetchColumn() ?? 0;
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
    $user = null;
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

/* Animated Background */
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

/* Page Header */
.page-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff 0%, #ffd89b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
}

.breadcrumb-item a:hover {
    color: #ffd89b;
}

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.7);
}

.btn-edit-header {
    padding: 0.8rem 2rem;
    background: white;
    color: #667eea;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
}

.btn-edit-header:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(255, 255, 255, 0.5);
    color: #667eea;
}

/* Profile Hero Card */
.profile-hero {
    background: white;
    border-radius: 30px;
    padding: 3rem;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.6s ease;
}

.profile-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 150px;
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.profile-content {
    position: relative;
    z-index: 1;
}

.avatar-wrapper {
    text-align: center;
    margin-bottom: 2rem;
}

.avatar-circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    border: 6px solid white;
    position: relative;
}

.status-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 4px solid white;
}

.status-badge.aktif {
    background: #38ef7d;
}

.status-badge.tidak-aktif {
    background: #eb3349;
}

.profile-name {
    font-size: 2rem;
    font-weight: 800;
    color: #333;
    margin-bottom: 0.5rem;
}

.profile-nik {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
}

.profile-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-action {
    padding: 0.8rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary-action {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-primary-action:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-danger-action {
    background: white;
    color: #eb3349;
    border: 2px solid #eb3349;
}

.btn-danger-action:hover {
    background: #eb3349;
    color: white;
    transform: scale(1.05);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card-mini {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-mini::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color-1), var(--stat-color-2));
}

.stat-card-mini:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
}

.stat-card-mini.blue {
    --stat-color-1: #667eea;
    --stat-color-2: #764ba2;
}

.stat-card-mini.orange {
    --stat-color-1: #f093fb;
    --stat-color-2: #f5576c;
}

.stat-card-mini.red {
    --stat-color-1: #eb3349;
    --stat-color-2: #f45c43;
}

.stat-mini-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    margin-bottom: 1rem;
}

.stat-mini-icon i {
    color: white;
    font-size: 1.5rem;
}

.stat-mini-value {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.3rem;
}

.stat-mini-label {
    color: #666;
    font-size: 0.9rem;
    font-weight: 600;
}

/* Info Cards */
.info-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.info-card-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 1.5rem;
    color: white;
}

.info-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.3rem;
}

.info-card-body {
    padding: 2rem;
}

.info-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-item {
    position: relative;
    padding-left: 1rem;
    border-left: 3px solid #667eea;
}

.info-label {
    font-size: 0.85rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}

.badge-status {
    padding: 0.5rem 1.2rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-block;
}

.badge-aktif {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.badge-tidak-aktif {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

/* Alert */
.alert-custom {
    border-radius: 20px;
    padding: 1.5rem;
    border: none;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    animation: slideIn 0.5s ease;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, #f8d7da, #ffe0e3);
    border-left: 5px solid #eb3349;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Animations */
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

.profile-hero {
    animation: fadeInUp 0.6s ease 0.1s;
    animation-fill-mode: both;
}

.stats-grid {
    animation: fadeInUp 0.6s ease 0.3s;
    animation-fill-mode: both;
}

.info-card:nth-child(1) {
    animation: fadeInUp 0.6s ease 0.5s;
    animation-fill-mode: both;
}

.info-card:nth-child(2) {
    animation: fadeInUp 0.6s ease 0.6s;
    animation-fill-mode: both;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .profile-hero {
        padding: 2rem 1.5rem;
    }
    
    .profile-name {
        font-size: 1.5rem;
    }
    
    .profile-actions {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .info-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Profil Saya</li>
                </ol>
            </nav>
            <h1>
                Profil Saya
            </h1>
        </div>
        <a href="edit_profil.php" class="btn-edit-header">
            Edit Profil
        </a>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="alert-custom">
            <strong>Error!</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Hero Card -->
    <div class="profile-hero">
        <div class="profile-content">
            <div class="avatar-wrapper">
                <?php 
                $initial = strtoupper(substr($user['nama'], 0, 1));
                ?>
                <div class="avatar-circle">
                    <?= $initial ?>
                    <div class="status-badge <?= $user['status'] === 'aktif' ? 'aktif' : 'tidak-aktif' ?>"></div>
                </div>
            </div>
            
            <div class="text-center">
                <h2 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h2>
                <p class="profile-nik">
                    NIK: <?= htmlspecialchars($user['nik']) ?>
                </p>
                <div class="mb-4">
                    <span class="badge-status <?= $user['status'] === 'aktif' ? 'badge-aktif' : 'badge-tidak-aktif' ?>">
                        <?= $user['status'] === 'aktif' ? 'Akun Aktif' : 'Akun Tidak Aktif' ?>
                    </span>
                </div>
                
                <div class="profile-actions">
                    <a href="edit_profil.php" class="btn-action btn-primary-action">
                        Edit Profil
                    </a>
                    <a href="../../auth/logout.php" class="btn-action btn-danger-action">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card-mini blue">
            <div class="stat-mini-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-mini-value"><?= $total_peminjaman ?></div>
            <div class="stat-mini-label">Total Peminjaman</div>
        </div>
        
        <div class="stat-card-mini orange">
            <div class="stat-mini-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-mini-value"><?= $sedang_dipinjam ?></div>
            <div class="stat-mini-label">Sedang Dipinjam</div>
        </div>
        
        <div class="stat-card-mini red">
            <div class="stat-mini-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-mini-value" style="font-size: 1.3rem;"><?= formatRupiah($total_denda) ?></div>
            <div class="stat-mini-label">Total Denda</div>
        </div>
    </div>

    <!-- Account Information -->
    <div class="info-card">
        <div class="info-card-header">
            <h5>Informasi Akun</h5>
        </div>
        <div class="info-card-body">
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Tanggal Daftar</div>
                    <div class="info-value"><?= formatTanggal($user['tanggal_daftar']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status Akun</div>
                    <div class="info-value">
                        <span class="badge-status <?= $user['status'] === 'aktif' ? 'badge-aktif' : 'badge-tidak-aktif' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="info-card">
        <div class="info-card-header">
            <h5>Informasi Pribadi</h5>
        </div>
        <div class="info-card-body">
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">NIK</div>
                    <div class="info-value"><?= htmlspecialchars($user['nik']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Nama Lengkap</div>
                    <div class="info-value"><?= htmlspecialchars($user['nama']) ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">No. HP</div>
                    <div class="info-value"><?= htmlspecialchars($user['no_hp']) ?? '-' ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Alamat</div>
                    <div class="info-value"><?= htmlspecialchars($user['alamat']) ?? '-' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../../includes/footer.php'; ?>