<?php
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

$logged_in = isset($_SESSION['user_id']);

$is_admin = $logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$is_petugas = $logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'petugas';
$is_anggota = $logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'anggota';

$current_uri = $_SERVER['REQUEST_URI'];

// ===== 🆕 DYNAMIC DASHBOARD URL BERDASARKAN ROLE =====
$dashboard_url = asset_url(''); // Default ke landing page

if ($logged_in && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $dashboard_url = asset_url('admin/dashboard.php');
            break;
        case 'petugas':
            $dashboard_url = asset_url('petugas/dashboard.php');
            break;
        case 'anggota':
            $dashboard_url = asset_url('anggota/dashboard.php');
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Dashboard' ?> - <?= SITE_NAME ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link href="<?= asset_url('assets/css/style.css') ?>?v=<?= time() ?>" rel="stylesheet">
    
    
    <meta name="description" content="Sistem Informasi Perpustakaan Digital">
    <meta name="author" content="Perpustakaan Nusantara">
    
    <style>
        /* Role Badge */
        .role-badge {
            font-size: 0.6rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .role-badge-admin {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
            color: #000 !important;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }
        
        .role-badge-petugas {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
            color: white !important;
        }
        
        .role-badge-anggota {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
            color: white !important;
        }
        
        .dropdown-header {
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px 8px 0 0;
            margin: -0.5rem -0.5rem 0.5rem -0.5rem;
        }
        
        .dropdown-header .fw-bold {
            font-size: 0.9rem;
            color: #2d3748;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }
        
        .dropdown-header .role-display {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.2rem;
        }
        
        .user-menu-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .user-name {
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.65rem;
        }
        
        @media (max-width: 1199px) {
            .nav-text {
                display: none;
            }
            
            .user-name {
                display: none;
            }
        }
        
        @media (min-width: 1200px) {
            .nav-text {
                display: inline;
                margin-left: 0.4rem;
            }
        }
        
        /* MOBILE SIDEBAR - SHOW ON MOBILE ONLY */
        @media (max-width: 991px) {
            /* Hide default Bootstrap collapse */
            .navbar-collapse {
                display: none !important;
            }
            
            .nav-text {
                display: inline;
            }
            
            .user-name {
                display: inline;
            }
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
    </style>
</head>
<body class="global-bg-fix <?= $body_class ?? '' ?>">

    <nav class="modern-navbar navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-3 px-lg-4">
            <!-- ✅ FIXED: Dynamic redirect berdasarkan role -->
            <a class="navbar-brand" href="<?= $dashboard_url ?>">
                <i class="fas fa-book-open"></i>
                <span class="brand-text">Perpustakaan</span><span class="brand-highlight">Nusantara</span>
            </a>
            
            
            <!-- Mobile Hamburger (visible only on mobile) -->
            <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- Desktop Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if ($is_admin): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos($current_uri, '/admin/dashboard') !== false) ? 'active' : '' ?>" 
                                href="<?= asset_url('admin/dashboard.php') ?>">
                                <i class="fas fa-home"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-users"></i>
                                <span class="nav-text">Users</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('admin/users/anggota.php') ?>">
                                    <i class="fas fa-user me-2"></i>Anggota
                                </a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/users/petugas.php') ?>">
                                    <i class="fas fa-user-tie me-2"></i>Petugas
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"> 
                                <i class="fas fa-sync"></i>
                                <span class="nav-text">Kelola</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('admin/buku/index.php') ?>">
                                    <i class="fas fa-book" style="margin-right: 10px;"></i>Buku</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/penerbit/index.php') ?>">
                                    <i class="fas fa-user" style="margin-right: 10px;"></i>Penerbit</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/kategori/index.php') ?>">
                                    <i class="fas fa-tags" style="margin-right: 10px;"></i>Kategori</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/rak/index.php') ?>">
                                    <i class="fas fa-archive" style="margin-right: 10px;"></i>Rak</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"> 
                                <i class="fa-solid fa-money-bill-wave"></i>
                                <span class="nav-text">Transaksi</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('admin/transaksi/peminjaman.php') ?>">
                                    <i class="fas fa-hand-holding-heart" style="margin-right: 10px;"></i>Peminjaman</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/transaksi/pengembalian.php') ?>">
                                    <i class="fas fa-undo" style="margin-right: 10px;"></i>Pengembalian</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/transaksi/perpanjangan.php') ?>">
                                    <i class="fas fa-clock" style="margin-right: 10px;"></i>Perpanjangan</a>
                                </li>
                                <li><a class="dropdown-item" href="<?= asset_url('admin/transaksi/buku_hilang.php') ?>">
                                    <i class="fa-solid fa-book-skull" style="margin-right: 10px;"></i>Buku Hilang</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('admin/transaksi/booking/index.php') ?>">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span class="nav-text">Booking</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('admin/laporan/') ?>">
                                <i class="fas fa-chart-bar"></i>
                                <span class="nav-text">Laporan</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('admin/pengaturan/') ?>">
                                <i class="fas fa-cogs"></i>
                                <span class="nav-text">Setting</span>
                            </a>
                        </li>
                        

                    <?php elseif ($is_petugas): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('petugas/dashboard.php') ?>">
                                <i class="fas fa-home"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-exchange-alt"></i>
                                <span class="nav-text">Transaksi</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/transaksi/peminjaman.php') ?>">Peminjaman</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/transaksi/pengembalian.php') ?>">Pengembalian</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/transaksi/perpanjangan.php') ?>">Perpanjangan</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/transaksi/buku_hilang.php') ?>">Buku Hilang</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-book"></i>
                                <span class="nav-text">Buku</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/buku/cek_status.php') ?>">Cek Status</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/buku/input_buku.php') ?>">Input Buku</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('petugas/laporan/harian.php') ?>">
                                <i class="fas fa-chart-line"></i>
                                <span class="nav-text">Laporan</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= asset_url('petugas/booking/index.php') ?>">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span class="nav-text">Booking</span>
                            </a>
                        </li>
                        
                    <?php elseif ($is_anggota): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos($current_uri, '/anggota/dashboard') !== false) ? 'active' : '' ?>" 
                               href="<?= asset_url('anggota/dashboard.php') ?>">
                                <i class="fas fa-home"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($current_uri, '/anggota/katalog') !== false ? 'active' : '' ?>" 
                               href="<?= asset_url('anggota/katalog/') ?>">
                                <i class="fas fa-search"></i>
                                <span class="nav-text">Katalog</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= strpos($current_uri, '/anggota/riwayat') !== false ? 'active' : '' ?>" 
                               href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-history"></i>
                                <span class="nav-text">Riwayat</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= asset_url('anggota/riwayat/peminjaman.php') ?>">Peminjaman</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('anggota/riwayat/denda.php') ?>">Denda</a></li>
                                <li><a class="dropdown-item" href="<?= asset_url('anggota/booking/history.php') ?>">Booking</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($current_uri, '/anggota/booking') !== false ? 'active' : '' ?>" 
                               href="<?= asset_url('anggota/booking/index.php') ?>">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span class="nav-text">Booking</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($current_uri, '/anggota/profil') !== false ? 'active' : '' ?>" 
                               href="<?= asset_url('anggota/profil/') ?>">
                                <i class="fas fa-user"></i>
                                <span class="nav-text">Profil</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <?php if ($logged_in): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-menu-link" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <span class="user-name">
                                <?php 
                                $nama = $_SESSION['nama'] ?? 'User';
                                echo htmlspecialchars(strlen($nama) > 12 ? substr($nama, 0, 12) . '...' : $nama);
                                ?>
                            </span>
                            <?php
                            $role = $_SESSION['role'] ?? 'user';
                            $badge_class = '';
                            $badge_text = '';
                            
                            switch ($role) {
                                case 'admin':
                                    $badge_class = 'role-badge role-badge-admin';
                                    $badge_text = 'Admin';
                                    break;
                                case 'petugas':
                                    $badge_class = 'role-badge role-badge-petugas';
                                    $badge_text = 'Petugas';
                                    break;
                                case 'anggota':
                                    $badge_class = 'role-badge role-badge-anggota';
                                    $badge_text = 'Anggota';
                                    break;
                                default:
                                    $badge_class = 'role-badge';
                                    $badge_text = 'User';
                            }
                            ?>
                            <span class="badge <?= $badge_class ?>">
                                <?= $badge_text ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-header">
                                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></div>
                                    <small class="text-muted role-display">
                                        <?php
                                        $role = $_SESSION['role'] ?? 'user';
                                        $icon = '';
                                        $badge_bg = '';
                                        $role_label = '';
                                        
                                        switch ($role) {
                                            case 'admin':
                                                $icon = 'fa-user-shield';
                                                $badge_bg = 'bg-warning text-dark';
                                                $role_label = 'Administrator';
                                                break;
                                            case 'petugas':
                                                $icon = 'fa-user-tie';
                                                $badge_bg = 'bg-info';
                                                $role_label = 'Petugas';
                                                break;
                                            case 'anggota':
                                                $icon = 'fa-user';
                                                $badge_bg = 'bg-success';
                                                $role_label = 'Anggota';
                                                break;
                                            default:
                                                $icon = 'fa-user';
                                                $badge_bg = 'bg-secondary';
                                                $role_label = 'User';
                                        }
                                        ?>
                                        <i class="fas <?= $icon ?> me-1"></i>
                                        <span class="badge <?= $badge_bg ?>"><?= $role_label ?></span>
                                    </small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- LINK PROFIL BERDASARKAN ROLE -->
                            <?php if ($is_anggota): ?>
                                <li><a class="dropdown-item" href="<?= asset_url('anggota/profil/') ?>">
                                    <i class="fas fa-user me-2"></i>Profil Saya
                                </a></li>
                            <?php elseif ($is_admin): ?>
                                <!-- ADMIN: Link ke edit_user.php dengan ID sendiri -->
                                <li><a class="dropdown-item" href="<?= asset_url('admin/users/edit_user.php?id=' . $_SESSION['user_id']) ?>">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profil
                                </a></li>
                            <?php elseif ($is_petugas): ?>
                                <!-- PETUGAS: Untuk sementara ke dashboard, nanti bisa dibuat halaman profil khusus -->
                                <li><a class="dropdown-item" href="<?= asset_url('petugas/profil/index.php') ?>">
                                    <i class="fas fa-user me-2"></i>Profil
                                </a></li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= asset_url('auth/logout.php') ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
                <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= asset_url('auth/login.php') ?>">
                            <i class="fas fa-sign-in-alt"></i>
                            <span class="nav-text">Login</span>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-overlay" id="sidebarOverlay"></div>
        <div class="mobile-sidebar-content">
            <div class="mobile-sidebar-header">
                <div class="mobile-sidebar-title">
                    <i class="fas fa-book-open"></i>
                    <span>Menu</span>
                </div>
                <button class="mobile-sidebar-close" id="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mobile-sidebar-body">
                <?php if ($logged_in): ?>
                <div class="mobile-user-info">
                    <i class="fas fa-user-circle"></i>
                    <div class="mobile-user-details">
                        <div class="mobile-user-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></div>
                        <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                    </div>
                </div>
                <div class="mobile-menu-divider"></div>
                <?php endif; ?>
                
                <ul class="mobile-nav-menu">
                    <?php if ($is_admin): ?>
                        <li><a href="<?= asset_url('admin/dashboard.php') ?>" class="<?= (strpos($current_uri, '/admin/dashboard') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-home"></i>Dashboard
                        </a></li>
                        
                        <!-- Users Dropdown -->
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fas fa-users"></i>Users
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('admin/users/anggota.php') ?>"><i class="fas fa-user"></i>Anggota</a></li>
                                <li><a href="<?= asset_url('admin/users/petugas.php') ?>"><i class="fas fa-user-tie"></i>Petugas</a></li>
                            </ul>
                        </li>
                        
                        <!-- Kelola Dropdown - GANTI DARI "BUKU" -->
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fas fa-sync"></i>Kelola
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('admin/buku/index.php') ?>"><i class="fas fa-book"></i>Buku</a></li>
                                <li><a href="<?= asset_url('admin/penerbit/index.php') ?>"><i class="fas fa-user"></i>Penerbit</a></li>
                                <li><a href="<?= asset_url('admin/kategori/index.php') ?>"><i class="fas fa-tags"></i>Kategori</a></li>
                                <li><a href="<?= asset_url('admin/rak/index.php') ?>"><i class="fas fa-archive"></i>Rak</a></li>
                            </ul>
                        </li>
                        
                        <!-- Transaksi Dropdown Mobile -->
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fa-solid fa-money-bill-wave"></i>Transaksi
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('admin/transaksi/peminjaman.php') ?>"><i class="fas fa-hand-holding-heart"></i>Peminjaman</a></li>
                                <li><a href="<?= asset_url('admin/transaksi/pengembalian.php') ?>"><i class="fas fa-undo"></i>Pengembalian</a></li>
                                <li><a href="<?= asset_url('admin/transaksi/perpanjangan.php') ?>"><i class="fas fa-clock"></i>Perpanjangan</a></li>
                                <li><a href="<?= asset_url('admin/transaksi/buku_hilang.php') ?>"><i class="fa-solid fa-book-skull"></i>Buku Hilang</a></li>
                            </ul>
                        </li>
                        <li><a href="<?= asset_url('admin/transaksi/booking/index.php') ?>">
                            <i class="fa-solid fa-calendar-days"></i>Booking
                        </a></li>
                        <li><a href="<?= asset_url('admin/laporan/') ?>">
                            <i class="fas fa-chart-bar"></i>Laporan
                        </a></li>
                        <li><a href="<?= asset_url('admin/pengaturan/') ?>">
                            <i class="fas fa-cogs"></i>Setting
                        </a></li>
                        
                    <?php elseif ($is_petugas): ?>
                        <li><a href="<?= asset_url('petugas/dashboard.php') ?>">
                            <i class="fas fa-home"></i>Dashboard
                        </a></li>
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fas fa-exchange-alt"></i>Transaksi
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('petugas/transaksi/peminjaman.php') ?>">Peminjaman</a></li>
                                <li><a href="<?= asset_url('petugas/transaksi/pengembalian.php') ?>">Pengembalian</a></li>
                                <li><a href="<?= asset_url('petugas/transaksi/perpanjangan.php') ?>">Perpanjangan</a></li>
                            </ul>
                        </li>
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fas fa-book"></i>Buku
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('petugas/buku/cek_status.php') ?>">Cek Status</a></li>
                                <li><a href="<?= asset_url('petugas/buku/input_buku.php') ?>">Input Buku</a></li>
                            </ul>
                        </li>
                        <li><a href="<?= asset_url('petugas/laporan/harian.php') ?>">
                            <i class="fas fa-chart-line"></i>Laporan
                        </a></li>
                        
                    <?php elseif ($is_anggota): ?>
                        <li><a href="<?= asset_url('anggota/dashboard.php') ?>" class="<?= (strpos($current_uri, '/anggota/dashboard') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-home"></i>Dashboard
                        </a></li>
                        <li><a href="<?= asset_url('anggota/katalog/') ?>" class="<?= strpos($current_uri, '/anggota/katalog') !== false ? 'active' : '' ?>">
                            <i class="fas fa-search"></i>Katalog
                        </a></li>
                        <li class="mobile-nav-dropdown">
                            <a href="#" class="mobile-dropdown-toggle">
                                <i class="fas fa-history"></i>Riwayat
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </a>
                            <ul class="mobile-dropdown-menu">
                                <li><a href="<?= asset_url('anggota/riwayat/peminjaman.php') ?>">Peminjaman</a></li>
                                <li><a href="<?= asset_url('anggota/riwayat/denda.php') ?>">Denda</a></li>
                            </ul>
                        </li>
                        <li><a href="<?= asset_url('anggota/profil/') ?>" class="<?= strpos($current_uri, '/anggota/profil') !== false ? 'active' : '' ?>">
                            <i class="fas fa-user"></i>Profil
                        </a></li>
                    <?php endif; ?>
                    
                    <?php if ($logged_in): ?>
                    <div class="mobile-menu-divider"></div>
                    
                    <!-- LINK PROFIL DI MOBILE SIDEBAR -->
                    <?php if ($is_admin): ?>
                        <li><a href="<?= asset_url('admin/users/edit_user.php?id=' . $_SESSION['user_id']) ?>">
                            <i class="fas fa-user-edit"></i>Edit Profil
                        </a></li>
                    <?php elseif ($is_petugas): ?>
                        <li><a href="<?= asset_url('petugas/profil/index.php') ?>">
                            <i class="fas fa-user"></i>Profil
                        </a></li>
                    <?php endif; ?>
                    
                    <li><a href="<?= asset_url('auth/logout.php') ?>" class="text-danger">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a></li>
                    <?php else: ?>
                    <li><a href="<?= asset_url('auth/login.php') ?>">
                        <i class="fas fa-sign-in-alt"></i>Login
                    </a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- MAIN WRAPPER - CRITICAL FOR FOOTER FIX -->
    <div id="main-wrapper">
    
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>