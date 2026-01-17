<?php
/**
 * Email SMTP Configuration - Railway Compatible
 * File: config/email.php
 */

// Deteksi environment
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

if ($isProduction) {
    // Railway Production - dari Environment Variables
    define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
    define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
    define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
    define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
    define('EMAIL_FROM', getenv('EMAIL_FROM'));
    define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'Perpustakaan Nusantara');
} else {
    // Localhost Development - hardcoded
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USERNAME', 'muhammadfarizsetiawan1604@gmail.com');
    define('SMTP_PASSWORD', 'qlxr irev zgli dnkb'); // App Password
    define('SMTP_SECURE', 'tls');
    define('EMAIL_FROM', 'muhammadfarizsetiawan1604@gmail.com');
    define('EMAIL_FROM_NAME', 'Perpustakaan [DEV]');
}

// OTP Settings
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 15);

// Company Information
define('COMPANY_NAME', 'Perpustakaan Nusantara');
define('COMPANY_ADDRESS', 'Surabaya, East Java, ID');
define('COMPANY_PHONE', '031-1234567');
define('COMPANY_EMAIL', 'info@perpustakaan.com');
define('ADMIN_EMAIL', 'admin@perpustakaan.com');
?>