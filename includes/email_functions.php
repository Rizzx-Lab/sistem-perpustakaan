<?php
/**
 * Email Functions menggunakan PHPMailer dengan PDO
 * File: includes/email_functions.php
 * UPDATED for Railway compatibility
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email.php';

/**
 * Fungsi untuk mengirim email
 */
function sendEmail($to, $toName, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Disable debug di production
        $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
        $mail->SMTPDebug = $isProduction ? 0 : 0; // 0=off, 2=verbose
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Kirim OTP untuk reset password
 */
function sendOTPEmail($email, $nama, $otp) {
    $subject = "Kode OTP Reset Password - " . COMPANY_NAME;
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .otp-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 8px; }
            .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Reset Password</h1>
                <p>Perpustakaan Nusantara</p>
            </div>
            <div class='content'>
                <p>Halo <strong>{$nama}</strong>,</p>
                <p>Anda menerima email ini karena ada permintaan untuk mereset password akun Anda.</p>
                
                <div class='otp-box'>
                    <p style='margin: 0; font-size: 14px; color: #666;'>Kode OTP Anda:</p>
                    <div class='otp-code'>{$otp}</div>
                    <p style='margin: 10px 0 0 0; font-size: 12px; color: #999;'>Berlaku selama " . OTP_EXPIRY_MINUTES . " menit</p>
                </div>
                
                <div class='info-box'>
                    <strong>Penting:</strong>
                    <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                        <li>Jangan bagikan kode OTP ini kepada siapapun</li>
                        <li>Kode akan kedaluwarsa dalam " . OTP_EXPIRY_MINUTES . " menit</li>
                        <li>Jika Anda tidak melakukan permintaan ini, abaikan email ini</li>
                    </ul>
                </div>
                
                <p>Terima kasih,<br><strong>Tim " . COMPANY_NAME . "</strong></p>
            </div>
            <div class='footer'>
                <p>" . COMPANY_NAME . "<br>
                " . COMPANY_ADDRESS . "<br>
                Email: " . COMPANY_EMAIL . " | Telepon: " . COMPANY_PHONE . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $nama, $subject, $body);
}

/**
 * Kirim notifikasi peminjaman terlambat
 */
function sendOverdueNotification($email, $nama, $judulBuku, $tanggalKembali, $hariTerlambat, $denda) {
    $subject = "Pemberitahuan Keterlambatan Pengembalian Buku";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .book-info { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; border: 1px solid #ddd; }
            .denda { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 15px 0; text-align: center; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Keterlambatan Pengembalian</h1>
                <p>Perpustakaan Nusantara</p>
            </div>
            <div class='content'>
                <p>Yth. <strong>{$nama}</strong>,</p>
                
                <div class='warning-box'>
                    <strong>Pemberitahuan Penting!</strong>
                    <p style='margin: 10px 0 0 0;'>Buku yang Anda pinjam telah melewati batas waktu pengembalian.</p>
                </div>
                
                <div class='book-info'>
                    <h3 style='margin-top: 0; color: #667eea;'>Detail Peminjaman:</h3>
                    <table style='width: 100%;'>
                        <tr>
                            <td style='padding: 5px 0;'><strong>Judul Buku:</strong></td>
                            <td>{$judulBuku}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0;'><strong>Tanggal Kembali:</strong></td>
                            <td>{$tanggalKembali}</td>
                        </tr>
                        <tr>
                            <td style='padding: 5px 0;'><strong>Keterlambatan:</strong></td>
                            <td><span style='color: #dc3545; font-weight: bold;'>{$hariTerlambat} hari</span></td>
                        </tr>
                    </table>
                </div>
                
                <div class='denda'>
                    <h3 style='margin-top: 0;'>Total Denda</h3>
                    <div style='font-size: 32px; font-weight: bold;'>Rp " . number_format($denda, 0, ',', '.') . "</div>
                    <p style='margin: 10px 0 0 0; font-size: 14px;'>(Rp 1.000 per hari)</p>
                </div>
                
                <p><strong>Mohon segera mengembalikan buku dan melunasi denda di perpustakaan.</strong></p>
                
                <p style='margin-top: 20px;'>Jam Operasional:<br>
                <strong>Senin - Jumat: 08:00 - 16:00 WIB</strong></p>
                
                <p>Terima kasih atas perhatian Anda.<br><strong>Tim " . COMPANY_NAME . "</strong></p>
            </div>
            <div class='footer'>
                <p>" . COMPANY_NAME . "<br>
                " . COMPANY_ADDRESS . "<br>
                Email: " . COMPANY_EMAIL . " | Telepon: " . COMPANY_PHONE . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $nama, $subject, $body);
}

/**
 * Kirim reminder denda yang belum dibayar
 */
function sendFineReminderEmail($email, $nama, $totalDenda, $jumlahBuku) {
    $subject = "Pengingat Pembayaran Denda Perpustakaan";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .reminder-box { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .total-box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border: 2px solid #ffc107; text-align: center; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Pengingat Pembayaran</h1>
                <p>Perpustakaan Nusantara</p>
            </div>
            <div class='content'>
                <p>Yth. <strong>{$nama}</strong>,</p>
                
                <div class='reminder-box'>
                    <p style='margin: 0;'>Anda memiliki denda keterlambatan yang belum dibayarkan untuk <strong>{$jumlahBuku}</strong> peminjaman buku.</p>
                </div>
                
                <div class='total-box'>
                    <h3 style='margin-top: 0; color: #ffc107;'>Total Denda yang Belum Dibayar</h3>
                    <div style='font-size: 36px; font-weight: bold; color: #dc3545;'>Rp " . number_format($totalDenda, 0, ',', '.') . "</div>
                </div>
                
                <p><strong>Mohon untuk segera melakukan pembayaran di perpustakaan.</strong></p>
                
                <p>Informasi Pembayaran:<br>
                Lokasi: " . COMPANY_ADDRESS . "<br>
                Jam Buka: 08:00 - 16:00 WIB (Senin - Jumat)<br>
                Telepon: " . COMPANY_PHONE . "</p>
                
                <p style='margin-top: 20px; font-size: 14px; color: #666;'>
                <em>Pembayaran denda dapat dilakukan di bagian pelayanan perpustakaan. Pastikan membawa kartu anggota Anda.</em>
                </p>
                
                <p>Terima kasih atas kerjasamanya.<br><strong>Tim " . COMPANY_NAME . "</strong></p>
            </div>
            <div class='footer'>
                <p>" . COMPANY_NAME . "<br>
                " . COMPANY_ADDRESS . "<br>
                Email: " . COMPANY_EMAIL . " | Telepon: " . COMPANY_PHONE . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $nama, $subject, $body);
}

/**
 * Generate OTP code
 */
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Simpan OTP ke database (PDO VERSION)
 */
function saveOTP($conn, $email, $otp) {
    $expiryTime = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    $stmt = $conn->prepare("INSERT INTO password_resets (email, otp_code, expires_at) VALUES (:email, :otp, :expires)");
    
    return $stmt->execute([
        'email' => $email,
        'otp' => $otp,
        'expires' => $expiryTime
    ]);
}

/**
 * Verifikasi OTP (PDO VERSION)
 */
function verifyOTP($conn, $email, $otp) {
    $currentTime = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = :email AND otp_code = :otp AND expires_at > :current_time AND is_used = 0");
    $stmt->execute([
        'email' => $email,
        'otp' => $otp,
        'current_time' => $currentTime
    ]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Mark OTP sebagai sudah digunakan (PDO VERSION)
 */
function markOTPAsUsed($conn, $email, $otp) {
    $stmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE email = :email AND otp_code = :otp");
    
    return $stmt->execute([
        'email' => $email,
        'otp' => $otp
    ]);
}
?>