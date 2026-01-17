<?php
// Main index.php - Redirect berdasarkan role user - FIXED VERSION
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Redirect based on user role
    $role = $_SESSION['role'] ?? '';
    
    switch($role) {
        case 'admin':
            redirect(SITE_URL . 'admin/dashboard.php', 'Selamat datang kembali, ' . ($_SESSION['nama'] ?? 'Admin') . '!', 'success');
            break;
            
        case 'petugas':
            redirect(SITE_URL . 'petugas/dashboard.php', 'Selamat datang kembali, ' . ($_SESSION['nama'] ?? 'Petugas') . '!', 'success');
            break;
            
        case 'anggota':
            redirect(SITE_URL . 'anggota/dashboard.php', 'Selamat datang kembali, ' . ($_SESSION['nama'] ?? 'Anggota') . '!', 'success');
            break;
            
        default:
            // Invalid role, logout and redirect to login
            session_unset();
            session_destroy();
            session_start();
            redirect(SITE_URL . 'auth/login.php', 'Role tidak dikenali, silakan login kembali.', 'warning');
            break;
    }
} else {
    // Not logged in, redirect to login
    redirect(SITE_URL . 'auth/login.php', 'Silakan login untuk mengakses sistem.', 'info');
}
?>