<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'admin':
            redirect(SITE_URL . 'admin/dashboard.php');
            break;
        case 'petugas':
            redirect(SITE_URL . 'petugas/dashboard.php');
            break;
        case 'anggota':
            redirect(SITE_URL . 'anggota/dashboard.php');
            break;
        default:
            redirect(SITE_URL);
    }
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi';
    } else {
        $login_result = loginUser($username, $password);
        
        if ($login_result['success']) {
            // MODIFIED: Redirect to welcome.php instead of direct dashboard
            $redirect_url = SITE_URL . 'auth/welcome.php';
            
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect_url = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
            }
            
            redirect($redirect_url);
        } else {
            $error_message = $login_result['message'];
        }
    }
}

$flash = getFlashMessage();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #FFFDD0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(30, 60, 114, 0.2);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #4A90E2 100%);
            background-size: 200% 200%;
            animation: gradientShift 8s ease infinite;
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .login-right {
            padding: 3rem;
        }
        
        .logo-section {
            margin-bottom: 2rem;
        }
        
        .school-logo {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 2rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            animation: logoFloat 3s ease-in-out infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            background: none;
            padding: 0;
            border: none;
        }

        .school-logo img {
            width: 125%;
            height: 125%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }
        
        @keyframes logoFloat {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            }
            50% {
                transform: translateY(-10px) rotate(2deg);
                box-shadow: 0 25px 45px rgba(0, 0, 0, 0.4);
            }
        }
        
        .logo-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 15px rgba(0,0,0,0.3);
            line-height: 1.2;
        }
        
        .logo-section .school-name {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-shadow: 0 1px 5px rgba(0,0,0,0.2);
        }
        
        .logo-section .system-name {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
            text-shadow: 0 1px 5px rgba(0,0,0,0.2);
        }
        
        .form-control-modern {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1e3c72 0%, #4A90E2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.4);
            color: white;
        }
        
        .alert-custom {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger-custom {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success-custom {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning-custom {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .forgot-password-link {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password-link a {
            color: #4A90E2;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .forgot-password-link a:hover {
            color: #1e3c72;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .login-left, .login-right {
                padding: 2rem;
            }
            
            .logo-section h1 {
                font-size: 2.2rem;
            }
            
            .school-logo {
                width: 150px;
                height: 150px;
                margin-bottom: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-left, .login-right {
                padding: 1.5rem;
            }
            
            .logo-section h1 {
                font-size: 1.8rem;
            }
            
            .school-logo {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="login-container row g-0">
                    <div class="col-lg-6 login-left">
                        <div class="logo-section">
                            <div class="school-logo">
                                <img src="https://files.catbox.moe/7ozwfm.png" 
                                alt="Logo SMKN 2 Surabaya" 
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div style="display: none; font-size: 4rem;">📚</div>
                            </div>     
                            <h1>Perpustakaan<br>Nusantara</h1>
                            <p class="school-name">SMKN 2 Surabaya</p>
                            <p class="system-name">Sistem Informasi Perpustakaan Digital</p>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 login-right">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-dark mb-2">Selamat Datang</h3>
                            <p class="text-muted">Silakan masuk ke akun Anda</p>
                        </div>
                        
                        <?php if ($flash): ?>
                            <div class="alert alert-<?= $flash['type'] ?>-custom alert-custom">
                                <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($flash['message']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger-custom alert-custom">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username</label>
                                <input type="text" 
                                       name="username" 
                                       id="username" 
                                       class="form-control form-control-modern" 
                                       placeholder="Masukkan username Anda"
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <input type="password" 
                                       name="password" 
                                       id="password" 
                                       class="form-control form-control-modern" 
                                       placeholder="Masukkan password Anda"
                                       required>
                            </div>
                            
                            <div class="forgot-password-link">
                                <a href="forgot_password.php">
                                    <i class="fas fa-key me-1"></i>Lupa Password?
                                </a>
                            </div>
                            
                            <button type="submit" name="login" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Masuk
                            </button>
                        </form>
                        
                        <div class="mt-4 p-3" style="background: rgba(74, 144, 226, 0.1); border-radius: 12px;">
                            <h6 class="fw-bold mb-2">Demo Akun:</h6>
                            <small class="text-muted d-block">
                                <strong>Admin:</strong> admin / admin123<br>
                                <strong>Petugas:</strong> petugas1 / admin123<br>
                                <strong>Anggota:</strong> Riddz / (password Anda)
                            </small>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="small text-muted">
                                Belum punya akun anggota? 
                                <a href="register.php" class="text-decoration-none fw-semibold" style="color: #4A90E2;">
                                    Daftar di sini
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('username').focus();
        
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>