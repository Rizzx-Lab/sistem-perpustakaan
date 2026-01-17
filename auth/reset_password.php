<?php
/**
 * Reset Password
 * File: auth/reset_password.php
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['verified_otp']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Semua field harus diisi!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email");
        
        if ($stmt->execute(['password' => $hashed_password, 'email' => $email])) {
            unset($_SESSION['reset_email']);
            unset($_SESSION['verified_otp']);
            
            $success = "Password berhasil direset! Silakan login dengan password baru Anda.";
            
            header("refresh:3;url=login.php");
        } else {
            $error = "Gagal mereset password. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Perpustakaan Nusantara</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFFDD0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(30, 60, 114, 0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #4A90E2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            user-select: none;
            font-size: 20px;
        }
        
        .toggle-password:hover {
            color: #4A90E2;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3c72 0%, #4A90E2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74, 144, 226, 0.4);
        }
        
        .password-requirements {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0d47a1;
        }
        
        .password-requirements ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Password</h1>
            <p>Buat password baru untuk akun Anda</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <div class="password-requirements">
                    <strong>Persyaratan Password:</strong>
                    <ul>
                        <li>Minimal 6 karakter</li>
                        <li>Gunakan kombinasi huruf dan angka untuk keamanan</li>
                        <li>Hindari menggunakan password yang mudah ditebak</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password">Password Baru</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Masukkan password baru"
                                required
                                minlength="6"
                            >
                            <span class="toggle-password" onclick="togglePassword('password')">&#128065;</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                placeholder="Konfirmasi password baru"
                                required
                                minlength="6"
                            >
                            <span class="toggle-password" onclick="togglePassword('confirm_password')">&#128065;</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.innerHTML = '&#128584;';
            } else {
                field.type = 'password';
                toggle.innerHTML = '&#128065;';
            }
        }
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
            }
        });
    </script>
</body>
</html>