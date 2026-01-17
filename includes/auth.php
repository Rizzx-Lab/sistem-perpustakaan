<?php
// Complete Authentication and Authorization System - WITH SUPER ADMIN HIERARCHY
require_once __DIR__ . '/../config/config.php';

// Check if user has required role
if (!function_exists('hasRole')) {
    function hasRole($required_roles) {
        if (!isLoggedIn()) {
            return false;
        }
        
        if (is_string($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($_SESSION['role'], $required_roles);
    }
}

// Check if user is super admin (administrator sistem utama)
if (!function_exists('is_super_admin')) {
    function is_super_admin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
    }
}

// Check if user is any admin (super_admin atau admin biasa)
if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin');
    }
}

// Check if user can change roles (hanya super admin)
if (!function_exists('can_change_role')) {
    function can_change_role() {
        return is_super_admin();
    }
}

// Require super admin access
if (!function_exists('require_super_admin')) {
    function require_super_admin() {
        if (!is_super_admin()) {
            setFlashMessage('Akses ditolak! Hanya Administrator Sistem Utama yang dapat mengakses fitur ini.', 'error');
            redirect(SITE_URL . 'admin/dashboard.php');
            exit();
        }
    }
}

// Require login - redirect to login if not authenticated
if (!function_exists('requireLogin')) {
    function requireLogin($redirect_url = '/auth/login.php') {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            redirect(SITE_URL . ltrim($redirect_url, '/'), 'Silakan login terlebih dahulu.', 'warning');
        }
    }
}

// Require specific role - redirect if insufficient permission
if (!function_exists('requireRole')) {
    function requireRole($required_roles, $redirect_url = null) {
        // Pastikan user login dulu
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            redirect(SITE_URL . 'auth/login.php', 'Silakan login terlebih dahulu.', 'warning');
            exit();
        }
        
        // Check role
        if (!hasRole($required_roles)) {
            // Redirect sesuai role user
            switch($_SESSION['role']) {
                case 'super_admin':
                case 'admin':
                    if (!$redirect_url) $redirect_url = SITE_URL . 'admin/dashboard.php';
                    break;
                case 'petugas':
                    if (!$redirect_url) $redirect_url = SITE_URL . 'petugas/dashboard.php';
                    break;
                case 'anggota':
                    if (!$redirect_url) $redirect_url = SITE_URL . 'anggota/dashboard.php';
                    break;
                default:
                    if (!$redirect_url) $redirect_url = SITE_URL . 'auth/login.php';
            }
            
            redirect($redirect_url, 'Anda tidak memiliki akses untuk halaman ini.', 'error');
            exit();
        }
    }
}

// Check role for different access levels
if (!function_exists('checkRole')) {
    function checkRole($role) {
        requireRole($role);
    }
}

// Login function
if (!function_exists('loginUser')) {
    function loginUser($username, $password) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("SELECT id, username, password, nama, email, role, status FROM users WHERE username = ? AND status = 'aktif'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Log login activity
                try {
                    logActivity('LOGIN', "User {$user['username']} logged in");
                } catch (Exception $e) {
                    // Silent fail untuk logging
                }
                
                return [
                    'success' => true,
                    'role' => $user['role'],
                    'nama' => $user['nama']
                ];
            }
            
            return ['success' => false, 'message' => 'Username atau password salah'];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}

// Logout function
if (!function_exists('logoutUser')) {
    function logoutUser() {
        if (isLoggedIn()) {
            try {
                logActivity('LOGOUT', "User {$_SESSION['username']} logged out");
            } catch (Exception $e) {
                // Silent fail untuk logging
            }
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Start new session for flash messages
        session_start();
        
        redirect(SITE_URL . 'auth/login.php', 'Anda telah logout.', 'success');
    }
}

// Register new user (for anggota registration)
if (!function_exists('registerUser')) {
    function registerUser($data) {
        global $conn;
        
        try {
            // Validate input
            $required_fields = ['username', 'password', 'nama', 'email'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field {$field} harus diisi"];
                }
            }
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username sudah digunakan'];
            }
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email sudah digunakan'];
            }
            
            // Insert new user
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, nama, email, role, status) 
                VALUES (?, ?, ?, ?, 'anggota', 'aktif')
            ");
            
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $result = $stmt->execute([
                $data['username'],
                $hashed_password,
                $data['nama'],
                $data['email']
            ]);
            
            if ($result) {
                logActivity('REGISTER', "New user registered: {$data['username']}");
                return ['success' => true, 'message' => 'Registrasi berhasil'];
            }
            
            return ['success' => false, 'message' => 'Gagal mendaftar'];
            
        } catch (Exception $e) {
            error_log("Register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}

// Activity logging
if (!function_exists('logActivity')) {
    function logActivity($action, $description = '') {
        global $conn;
        
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'guest';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            
            // Log to PHP error log
            $log_message = "ACTIVITY LOG - User: {$username}, Action: {$action}, Description: {$description}, IP: {$ip_address}";
            error_log($log_message);
            
        } catch (Exception $e) {
            // Silent fail for logging
            error_log("Log activity error: " . $e->getMessage());
        }
    }
}

// Session timeout check
if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout($timeout_minutes = 120) {
        if (isLoggedIn() && isset($_SESSION['login_time'])) {
            $elapsed = time() - $_SESSION['login_time'];
            
            if ($elapsed > ($timeout_minutes * 60)) {
                logoutUser();
            }
            
            // Update login time on activity
            $_SESSION['login_time'] = time();
        }
    }
}

// Get user permissions based on role
if (!function_exists('getUserPermissions')) {
    function getUserPermissions($role) {
        $permissions = [
            'super_admin' => [
                'buku' => ['create', 'read', 'update', 'delete'],
                'anggota' => ['create', 'read', 'update', 'delete'],
                'petugas' => ['create', 'read', 'update', 'delete'],
                'admin' => ['create', 'read', 'update', 'delete'],
                'peminjaman' => ['create', 'read', 'update', 'delete'],
                'laporan' => ['read', 'export'],
                'pengaturan' => ['read', 'update'],
                'role_management' => ['update'] // HANYA SUPER ADMIN
            ],
            'admin' => [
                'buku' => ['create', 'read', 'update', 'delete'],
                'anggota' => ['create', 'read', 'update', 'delete'],
                'petugas' => ['create', 'read', 'update', 'delete'],
                'peminjaman' => ['create', 'read', 'update', 'delete'],
                'laporan' => ['read', 'export'],
                'pengaturan' => ['read', 'update']
                // TIDAK ADA role_management - tidak bisa ubah role
            ],
            'petugas' => [
                'buku' => ['create', 'read', 'update'],
                'anggota' => ['read'],
                'peminjaman' => ['create', 'read', 'update'],
                'laporan' => ['read', 'export']
            ],
            'anggota' => [
                'buku' => ['read'],
                'peminjaman' => ['read'], // only own data
                'profil' => ['read', 'update']
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
}

// Check if user can perform action on resource
if (!function_exists('canAccess')) {
    function canAccess($resource, $action) {
        if (!isLoggedIn()) {
            return false;
        }
        
        $permissions = getUserPermissions($_SESSION['role']);
        
        return isset($permissions[$resource]) && in_array($action, $permissions[$resource]);
    }
}

// Create user account for existing anggota
if (!function_exists('linkAnggotaToUser')) {
    function linkAnggotaToUser($nik, $username, $password, $email) {
        global $conn;
        
        try {
            // Get anggota data
            $stmt = $conn->prepare("SELECT * FROM anggota WHERE nik = ?");
            $stmt->execute([$nik]);
            $anggota = $stmt->fetch();
            
            if (!$anggota) {
                return ['success' => false, 'message' => 'Data anggota tidak ditemukan'];
            }
            
            // Create user account
            $userData = [
                'username' => $username,
                'password' => $password,
                'nama' => $anggota['nama'],
                'email' => $email
            ];
            
            $result = registerUser($userData);
            
            if ($result['success']) {
                // Link anggota to user
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $stmt = $conn->prepare("UPDATE anggota SET user_id = ? WHERE nik = ?");
                    $stmt->execute([$user['id'], $nik]);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Link anggota error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}

// Update profile function
if (!function_exists('updateProfile')) {
    function updateProfile($data) {
        global $conn;
        
        try {
            if (!isLoggedIn()) {
                return ['success' => false, 'message' => 'Silakan login terlebih dahulu'];
            }
            
            $user_id = $_SESSION['user_id'];
            
            // Validate email
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Format email tidak valid'];
            }
            
            // Check if email already exists (except current user)
            if (!empty($data['email'])) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $user_id]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'message' => 'Email sudah digunakan oleh user lain'];
                }
            }
            
            // Update user data
            $stmt = $conn->prepare("UPDATE users SET nama = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$data['nama'], $data['email'], $user_id]);
            
            if ($result) {
                // Update session
                $_SESSION['nama'] = $data['nama'];
                $_SESSION['email'] = $data['email'];
                
                logActivity('PROFILE_UPDATE', "Profile updated");
                return ['success' => true, 'message' => 'Profil berhasil diupdate'];
            }
            
            return ['success' => false, 'message' => 'Gagal mengupdate profil'];
            
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}

// Change password function
if (!function_exists('changePassword')) {
    function changePassword($current_password, $new_password, $confirm_password) {
        global $conn;
        
        try {
            if (!isLoggedIn()) {
                return ['success' => false, 'message' => 'Silakan login terlebih dahulu'];
            }
            
            // Validate passwords
            if (strlen($new_password) < 6) {
                return ['success' => false, 'message' => 'Password baru minimal 6 karakter'];
            }
            
            if ($new_password !== $confirm_password) {
                return ['success' => false, 'message' => 'Konfirmasi password tidak cocok'];
            }
            
            // Get current user
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                return ['success' => false, 'message' => 'Password saat ini salah'];
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$hashed_password, $user_id]);
            
            if ($result) {
                logActivity('PASSWORD_CHANGE', "Password changed");
                return ['success' => true, 'message' => 'Password berhasil diubah'];
            }
            
            return ['success' => false, 'message' => 'Gagal mengubah password'];
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
        }
    }
}

// Auto logout jika session timeout
checkSessionTimeout();
?>