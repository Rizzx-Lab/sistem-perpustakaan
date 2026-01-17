<?php
/**
 * Verify OTP
 * File: auth/verify_otp.php
 */

session_start();
require_once '../config/database.php';
require_once '../includes/email_functions.php';

// Cek apakah ada session reset_email
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];
    
    if (empty($otp)) {
        $error = "Kode OTP harus diisi!";
    } elseif (strlen($otp) != OTP_LENGTH) {
        $error = "Kode OTP harus 6 digit!";
    } else {
        // Verifikasi OTP
        if (verifyOTP($conn, $email, $otp)) {
            // OTP valid, mark sebagai used
            markOTPAsUsed($conn, $email, $otp);
            
            // Simpan OTP di session untuk validasi di halaman reset password
            $_SESSION['verified_otp'] = $otp;
            
            // Redirect ke halaman reset password
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Kode OTP tidak valid atau sudah kedaluwarsa!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - Perpustakaan Nusantara</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .email-sent {
            background: #e7f3ff;
            border: 2px dashed #2196F3;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .email-sent p {
            margin: 5px 0;
            color: #0d47a1;
        }
        
        .email-sent strong {
            color: #1976d2;
            font-size: 16px;
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
        
        .otp-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .resend-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .resend-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verifikasi OTP</h1>
            <p>Masukkan kode yang telah dikirim ke email Anda</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="email-sent">
                <p>Kode OTP telah dikirim ke:</p>
                <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    Periksa folder inbox atau spam Anda
                </p>
            </div>
            
            <div class="info-box">
                <strong>Penting:</strong>
                <ul style="margin: 10px 0 0 20px; padding: 0;">
                    <li>Kode OTP terdiri dari 6 digit angka</li>
                    <li>Berlaku selama <?php echo OTP_EXPIRY_MINUTES; ?> menit</li>
                    <li>Jangan bagikan kode ini ke siapapun</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="otp">Kode OTP</label>
                    <input 
                        type="text" 
                        id="otp" 
                        name="otp" 
                        class="otp-input"
                        placeholder="000000"
                        maxlength="<?php echo OTP_LENGTH; ?>"
                        pattern="[0-9]{<?php echo OTP_LENGTH; ?>}"
                        required
                        autocomplete="off"
                    >
                </div>
                
                <button type="submit" class="btn">Verifikasi OTP</button>
            </form>
            
            <div class="resend-link">
                Tidak menerima kode? 
                <a href="forgot_password.php">Kirim ulang</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto focus pada input OTP
        document.getElementById('otp').focus();
        
        // Hanya izinkan angka
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>