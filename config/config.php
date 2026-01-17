<?php
// System Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/database.php';

// Include functions SEBELUM digunakan
require_once __DIR__ . '/../includes/functions.php';

// Timezone setting
date_default_timezone_set('Asia/Jakarta');

// System constants
if (!defined('SITE_NAME')) define('SITE_NAME', 'Perpustakaan Nusantara');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost:8080/');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@perpustakaan.com');

// Default settings
$default_settings = [
    'max_pinjam_hari' => 14,
    'denda_per_hari' => 1000,
    'max_buku_pinjam' => 3,
    'nama_perpustakaan' => 'Perpustakaan Nusantara',
    'alamat_perpustakaan' => 'Surabaya, East Java, ID',
    'jam_buka' => '08:00',
    'jam_tutup' => '16:00'
];

// Load system settings
try {
    $SISTEM = [
        'max_pinjam_hari' => (int)getSetting('max_pinjam_hari', 14),
        'denda_per_hari' => (int)getSetting('denda_per_hari', 1000),
        'max_buku_pinjam' => (int)getSetting('max_buku_pinjam', 3),
        'nama_perpustakaan' => getSetting('nama_perpustakaan', 'Perpustakaan Nusantara'),
        'alamat_perpustakaan' => getSetting('alamat_perpustakaan', 'Surabaya, East Java, ID'),
        'jam_buka' => getSetting('jam_buka', '08:00'),
        'jam_tutup' => getSetting('jam_tutup', '16:00')
    ];
} catch (Exception $e) {
    $SISTEM = $default_settings;
}

// Helper function untuk absolute URL - FIXED
function asset_url($path = '') {
    $path = ltrim($path, '/');
    return rtrim(SITE_URL, '/') . '/' . $path;
}

// Alias untuk base_url
function base_url($path = '') {
    return asset_url($path);
}
?>