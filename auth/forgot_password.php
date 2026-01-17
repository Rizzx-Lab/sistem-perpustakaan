<?php
/**
 * Forgot Password - Request OTP
 * File: auth/forgot_password.php
 */

session_start();
require_once '../config/database.php';
require_once '../includes/email_functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        $stmt = $conn->prepare("SELECT id, nama, email FROM users WHERE email = :email AND status = 'aktif'");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $otp = generateOTP(OTP_LENGTH);
            
            if (saveOTP($conn, $email, $otp)) {
                if (sendOTPEmail($email, $user['nama'], $otp)) {
                    $_SESSION['reset_email'] = $email;
                    $success = "Kode OTP telah dikirim ke email Anda. Silakan cek inbox atau folder spam.";
                    
                    header("refresh:2;url=verify_otp.php");
                } else {
                    $error = "Gagal mengirim email. Silakan coba lagi.";
                }
            } else {
                $error = "Terjadi kesalahan. Silakan coba lagi.";
            }
        } else {
            $success = "Jika email terdaftar, kode OTP akan dikirim ke email Anda.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Perpustakaan Nusantara</title>
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
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
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
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #4A90E2;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #0d47a1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Lupa Password</h1>
            <p>Masukkan email Anda untuk menerima kode OTP</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <strong>Informasi:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li>Masukkan email yang terdaftar di akun Anda</li>
                    <li>Kode OTP akan dikirim ke email tersebut</li>
                    <li>Kode berlaku selama <?php echo OTP_EXPIRY_MINUTES; ?> menit</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Masukkan email Anda"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>
                
                <button type="submit" class="btn">Kirim Kode OTP</button>
            </form>
            
            <div class="back-link">
                <a href="login.php">Kembali ke Login</a>
            </div>
        </div>
    </div>
</body>
</html>