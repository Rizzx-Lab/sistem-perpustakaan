<?php
/**
 * Database Configuration - Railway Compatible
 * File: config/database.php
 */

// Deteksi environment
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;

if ($isProduction) {
    // Railway Production Environment
    if (!defined('DB_HOST')) define('DB_HOST', getenv('MYSQLHOST'));
    if (!defined('DB_PORT')) define('DB_PORT', getenv('MYSQLPORT') ?: 3306);
    if (!defined('DB_USER')) define('DB_USER', getenv('MYSQLUSER'));
    if (!defined('DB_PASS')) define('DB_PASS', getenv('MYSQLPASSWORD'));
    if (!defined('DB_NAME')) define('DB_NAME', getenv('MYSQLDATABASE'));
} else {
    // Localhost Development Environment
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', ''); // Password MySQL localhost kamu
    if (!defined('DB_NAME')) define('DB_NAME', 'testing'); // Nama database localhost kamu
}

// Create PDO connection (singleton pattern)
if (!isset($conn)) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        
    } catch (PDOException $e) {
        // Log error
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Show user-friendly message
        if ($isProduction) {
            die("Database connection error. Please contact administrator.");
        } else {
            die("Database Error: " . $e->getMessage());
        }
    }
}

// Helper Functions
if (!function_exists('generateRandomPassword')) {
    function generateRandomPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $charsLength = strlen($chars);
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        return $password;
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('testConnection')) {
    function testConnection() {
        global $conn;
        try {
            $stmt = $conn->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            error_log("Connection test failed: " . $e->getMessage());
            return false;
        }
    }
}
?>