<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Pastikan hanya petugas yang bisa mengakses
if ($_SESSION['role'] !== 'petugas') {
    header('Location: ' . SITE_URL);
    exit();
}

$page_title = 'Profil Petugas';
$user_id = $_SESSION['user_id'];

// Ambil data petugas dari database
try {
    $query = "
        SELECT u.*, a.nik, a.no_hp, a.alamat 
        FROM users u 
        LEFT JOIN anggota a ON u.id = a.user_id 
        WHERE u.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $petugas = $stmt->fetch();
    
    if (!$petugas) {
        setFlashMessage('Data petugas tidak ditemukan', 'error');
        header('Location: ../dashboard.php');
        exit();
    }
    
    // Get statistics
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_peminjaman 
        FROM peminjaman 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $statistik = $stmt->fetch();
    $total_peminjaman = $statistik['total_peminjaman'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_pengembalian 
        FROM pengembalian 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $statistik = $stmt->fetch();
    $total_pengembalian = $statistik['total_pengembalian'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as anggota_aktif 
        FROM users 
        WHERE role = 'anggota' AND status = 'aktif'
    ");
    $stmt->execute();
    $statistik = $stmt->fetch();
    $anggota_aktif = $statistik['anggota_aktif'] ?? 0;
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
    $petugas = null;
}

include '../../includes/header.php';
?>

<style>
body {
    background: #FFFDD0;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
}

/* Animated Background DIHAPUS - diganti background sederhana */
.container {
    position: relative;
    z-index: 1;
}

/* Page Header TETAP SAMA */
.page-header {
    background: white;
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
    background: linear-gradient(120deg, #4facfe, #00f2fe);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.btn-edit-header {
    padding: 0.8rem 2rem;
    background: #4facfe;
    color: black;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
}

.btn-edit-header:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4);
    color: white;
}

/* Profile Hero Card TETAP SAMA */
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
    background: linear-gradient(135deg, #4facfe, #00f2fe);
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
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
    box-shadow: 0 15px 40px rgba(79, 172, 254, 0.4);
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

.profile-role {
    color: #4facfe;
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
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.btn-primary-action:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(79, 172, 254, 0.4);
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

/* Statistics Cards TETAP SAMA */
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
    --stat-color-1: #4facfe;
    --stat-color-2: #00f2fe;
}

.stat-card-mini.green {
    --stat-color-1: #38ef7d;
    --stat-color-2: #11998e;
}

.stat-card-mini.orange {
    --stat-color-1: #f093fb;
    --stat-color-2: #f5576c;
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

/* Info Cards TETAP SAMA */
.info-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.info-card-header {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
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
    border-left: 3px solid #4facfe;
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

/* Alert TETAP SAMA */
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

/* Animations TETAP SAMA */
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

/* Responsive TETAP SAMA */
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
                    <li class="breadcrumb-item active">Profil Petugas</li>
                </ol>
            </nav>
            <h1>Profil Petugas</h1>
        </div>
        <a href="edit.php" class="btn-edit-header">
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
                $initial = strtoupper(substr($petugas['nama'], 0, 1));
                ?>
                <div class="avatar-circle">
                    <?= $initial ?>
                    <div class="status-badge <?= $petugas['status'] === 'aktif' ? 'aktif' : 'tidak-aktif' ?>"></div>
                </div>
            </div>
            
            <div class="text-center">
                <h2 class="profile-name"><?= htmlspecialchars($petugas['nama']) ?></h2>
                <p class="profile-role">
                    <i class="fas fa-user-shield"></i> <?= ucfirst($petugas['role']) ?>
                </p>
                <div class="mb-4">
                    <span class="badge-status <?= $petugas['status'] === 'aktif' ? 'badge-aktif' : 'badge-tidak-aktif' ?>">
                        <?= $petugas['status'] === 'aktif' ? 'Akun Aktif' : 'Akun Tidak Aktif' ?>
                    </span>
                </div>
                
                <div class="profile-actions">
                    <a href="edit.php" class="btn-action btn-primary-action">
                        <i class="fas fa-edit"></i> Edit Profil
                    </a>
                    <a href="../../auth/logout.php" class="btn-action btn-danger-action">
                        <i class="fas fa-sign-out-alt"></i> Logout
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
            <div class="stat-mini-label">Peminjaman (30 hari)</div>
        </div>
        
        <div class="stat-card-mini green">
            <div class="stat-mini-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-mini-value"><?= $total_pengembalian ?></div>
            <div class="stat-mini-label">Pengembalian (30 hari)</div>
        </div>
        
        <div class="stat-card-mini orange">
            <div class="stat-mini-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-mini-value"><?= $anggota_aktif ?></div>
            <div class="stat-mini-label">Anggota Aktif</div>
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
                    <div class="info-value"><?= htmlspecialchars($petugas['username']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($petugas['email']) ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Tanggal Bergabung</div>
                    <div class="info-value"><?= formatTanggal($petugas['created_at']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status Akun</div>
                    <div class="info-value">
                        <span class="badge-status <?= $petugas['status'] === 'aktif' ? 'badge-aktif' : 'badge-tidak-aktif' ?>">
                            <?= ucfirst($petugas['status']) ?>
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
                    <div class="info-value"><?= htmlspecialchars($petugas['nik'] ?? '-') ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Nama Lengkap</div>
                    <div class="info-value"><?= htmlspecialchars($petugas['nama']) ?></div>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">No. HP</div>
                    <div class="info-value"><?= htmlspecialchars($petugas['no_hp'] ?? '-') ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Alamat</div>
                    <div class="info-value"><?= htmlspecialchars($petugas['alamat'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>