<?php
// Logout Handler
require_once '../config/config.php';

// Simpan nama user
$user_name = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'User';

// Hapus semua session data
$_SESSION = array();

// Hapus session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Start session baru untuk flash message
session_start();

// Set flash message
setFlashMessage("Berhasil logout. Sampai jumpa, " . htmlspecialchars($user_name) . "!", 'success');

// Redirect ke login
header("Location: " . SITE_URL . "auth/login.php");
exit();
?>