<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'admin':
            redirect(SITE_URL . 'admin/');
            break;
        case 'petugas':
            redirect(SITE_URL . 'petugas/');
            break;
        case 'anggota':
            redirect(SITE_URL . 'anggota/');
            break;
        default:
            redirect(SITE_URL);
    }
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    
    if (empty($username) || empty($password) || empty($nama) || empty($email) || empty($nik) || empty($alamat)) {
        $error_message = 'Semua field wajib harus diisi';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Konfirmasi password tidak cocok';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid';
    } elseif (!validateNIK($nik)) {
        $error_message = 'Format NIK tidak valid (harus 16 digit)';
    } else {
        $user_data = [
            'username' => $username,
            'password' => $password,
            'nama' => $nama,
            'email' => $email
        ];
        
        $register_result = registerUser($user_data);
        
        if ($register_result['success']) {
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $stmt = $conn->prepare("INSERT INTO anggota (nik, nama, no_hp, alamat, user_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nik, $nama, $no_hp, $alamat, $user['id']]);
                }
                
                $success_message = 'Registrasi berhasil! Anda dapat login dengan akun yang telah dibuat.';
            } catch (Exception $e) {
                $error_message = 'Registrasi berhasil tetapi terjadi kesalahan pada data anggota. Silakan hubungi admin.';
            }
        } else {
            $error_message = $register_result['message'];
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
    <title>Daftar Anggota - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            background: #FFFDD0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(30, 60, 114, 0.2);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .register-left {
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
        
        .register-right {
            padding: 2rem;
        }
        
        .logo-section {
            margin-bottom: 2rem;
        }
        
        .logo-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
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
        
        .btn-register {
            background: linear-gradient(135deg, #1e3c72 0%, #4A90E2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-register:hover {
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
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 2rem;
            }
            .register-right {
                padding: 1.5rem;
            }
            .logo-section h1 {
                font-size: 2rem;
            }
            .school-logo {
                width: 150px;
                height: 150px;
                margin-bottom: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .school-logo {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="register-container row g-0">
                    <div class="col-lg-5 register-left">
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
                    
                    <div class="col-lg-7 register-right">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-dark mb-2">Daftar Sebagai Anggota</h3>
                            <p class="text-muted">Lengkapi form untuk membuat akun perpustakaan</p>
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
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success-custom alert-custom">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$success_message): ?>
                        <form method="POST" action="" id="registerForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nama" class="form-label fw-semibold">Nama Lengkap *</label>
                                    <input type="text" 
                                           name="nama" 
                                           id="nama" 
                                           class="form-control form-control-modern" 
                                           placeholder="Masukkan nama lengkap"
                                           value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="nik" class="form-label fw-semibold">NIK *</label>
                                    <input type="text" 
                                           name="nik" 
                                           id="nik" 
                                           class="form-control form-control-modern" 
                                           placeholder="16 digit NIK"
                                           value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>"
                                           maxlength="16"
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="username" class="form-label fw-semibold">Username *</label>
                                    <input type="text" 
                                           name="username" 
                                           id="username" 
                                           class="form-control form-control-modern" 
                                           placeholder="Username untuk login"
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label fw-semibold">Email *</label>
                                    <input type="email" 
                                           name="email" 
                                           id="email" 
                                           class="form-control form-control-modern" 
                                           placeholder="alamat@email.com"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="password" class="form-label fw-semibold">Password *</label>
                                    <input type="password" 
                                           name="password" 
                                           id="password" 
                                           class="form-control form-control-modern" 
                                           placeholder="Minimal 6 karakter"
                                           minlength="6"
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label fw-semibold">Konfirmasi Password *</label>
                                    <input type="password" 
                                           name="confirm_password" 
                                           id="confirm_password" 
                                           class="form-control form-control-modern" 
                                           placeholder="Ulangi password"
                                           minlength="6"
                                           required>
                                </div>
                                
                                <div class="col-12">
                                    <label for="no_hp" class="form-label fw-semibold">No. Handphone</label>
                                    <input type="text" 
                                           name="no_hp" 
                                           id="no_hp" 
                                           class="form-control form-control-modern" 
                                           placeholder="08xxxxxxxxxx"
                                           value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label for="alamat" class="form-label fw-semibold">Alamat Lengkap *</label>
                                    <textarea name="alamat" 
                                              id="alamat" 
                                              class="form-control form-control-modern" 
                                              placeholder="Masukkan alamat lengkap"
                                              rows="3"
                                              required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agree" required>
                                    <label class="form-check-label small" for="agree">
                                        Saya setuju dengan <a href="#" class="text-decoration-none">syarat dan ketentuan</a> yang berlaku
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" name="register" class="btn btn-register mt-4">
                                <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <p class="small text-muted">
                                Sudah punya akun? 
                                <a href="login.php" class="text-decoration-none fw-semibold" style="color: #4A90E2;">
                                    Masuk di sini
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
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const nik = document.getElementById('nik').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return;
            }
            
            if (nik.length !== 16 || !/^\d+$/.test(nik)) {
                e.preventDefault();
                alert('NIK harus berisi 16 digit angka!');
                return;
            }
        });
        
        document.getElementById('nama').focus();
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });
        
        document.getElementById('nik').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 16);
        });
        
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