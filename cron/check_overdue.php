<?php
/**
 * Cron Job untuk Cek Keterlambatan dan Kirim Notifikasi Email
 * File: cron/check_overdue.php
 * UPDATED for Railway compatibility
 * 
 * PERUBAHAN:
 * - REMOVED: Update denda di tabel peminjaman
 * - Denda akan dicatat saat pengembalian di tabel pengembalian
 * 
 * Cara Menjalankan:
 * 1. Manual via browser: https://your-app.railway.app/cron/check_overdue.php
 * 2. Via command line: php /path/to/cron/check_overdue.php
 * 3. Setup cron job di Railway (Advanced)
 */

// Set execution time and memory
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/functions.php';

// Log file
$log_file = __DIR__ . '/overdue_check.log';

/**
 * Write to log file
 */
function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message; // Also output to console
}

/**
 * Main execution
 */
try {
    writeLog("========================================");
    writeLog("Starting Overdue Check Process");
    writeLog("Environment: " . (getenv('RAILWAY_ENVIRONMENT') !== false ? 'PRODUCTION (Railway)' : 'DEVELOPMENT (Localhost)'));
    writeLog("========================================");
    
    $today = date('Y-m-d');
    $dendaPerHari = (int)getSetting('denda_per_hari', 1000);
    
    writeLog("Date: {$today}");
    writeLog("Denda per hari: Rp " . number_format($dendaPerHari, 0, ',', '.'));
    
    // Query untuk mendapatkan semua peminjaman yang terlambat
    $query = "
        SELECT 
            p.id_peminjaman,
            p.nik,
            p.isbn,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            a.nama as nama_anggota,
            a.no_hp,
            b.judul as judul_buku,
            b.pengarang,
            penerbit.nama_penerbit,
            u.id as user_id,
            u.email,
            u.username,
            DATEDIFF(:today, p.tanggal_kembali) as hari_terlambat
        FROM peminjaman p
        INNER JOIN anggota a ON p.nik = a.nik
        INNER JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit ON b.id_penerbit = penerbit.id_penerbit
        LEFT JOIN users u ON a.user_id = u.id
        WHERE p.status = 'dipinjam'
        AND p.tanggal_kembali < :today2
        ORDER BY p.tanggal_kembali ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        'today' => $today,
        'today2' => $today
    ]);
    
    $overdueLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalOverdue = count($overdueLoans);
    
    writeLog("Found {$totalOverdue} overdue loan(s)");
    writeLog("----------------------------------------");
    
    if ($totalOverdue === 0) {
        writeLog("No overdue loans found. Process completed successfully.");
        writeLog("========================================\n");
        exit(0);
    }
    
    $notificationsSent = 0;
    $notificationsFailed = 0;
    
    foreach ($overdueLoans as $loan) {
        $hariTerlambat = (int)$loan['hari_terlambat'];
        $dendaBaru = $hariTerlambat * $dendaPerHari;
        
        writeLog("\n--- Processing Loan ID: {$loan['id_peminjaman']} ---");
        writeLog("Member: {$loan['nama_anggota']} (NIK: {$loan['nik']})");
        writeLog("Book: {$loan['judul_buku']}");
        writeLog("Publisher: " . ($loan['nama_penerbit'] ?? '-'));
        writeLog("Due date: {$loan['tanggal_kembali']}");
        writeLog("Days overdue: {$hariTerlambat} day(s)");
        writeLog("Calculated fine: Rp " . number_format($dendaBaru, 0, ',', '.'));
        
        // REMOVED: Update denda di database peminjaman
        // Denda akan dicatat otomatis saat pengembalian di tabel pengembalian
        writeLog("⚠ Fine will be recorded upon return in 'pengembalian' table");
        
        // Kirim notifikasi email jika email tersedia
        if (!empty($loan['email']) && filter_var($loan['email'], FILTER_VALIDATE_EMAIL)) {
            writeLog("Sending email notification to: {$loan['email']}");
            
            try {
                $emailSent = sendOverdueNotification(
                    $loan['email'],
                    $loan['nama_anggota'],
                    $loan['judul_buku'],
                    date('d/m/Y', strtotime($loan['tanggal_kembali'])),
                    $hariTerlambat,
                    $dendaBaru
                );
                
                if ($emailSent) {
                    $notificationsSent++;
                    writeLog("✓ Email notification sent successfully");
                    
                    // Log ke tabel log_aktivitas
                    try {
                        $logStmt = $conn->prepare("
                            INSERT INTO log_aktivitas 
                            (user_id, username, nama, role, action, description, target_type, target_id, ip_address, user_agent, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $logStmt->execute([
                            $loan['user_id'],
                            $loan['username'] ?? 'system',
                            $loan['nama_anggota'],
                            'anggota',
                            'NOTIFIKASI_KETERLAMBATAN',
                            "Notifikasi keterlambatan terkirim untuk buku '{$loan['judul_buku']}' - {$hariTerlambat} hari terlambat - Denda: Rp " . number_format($dendaBaru, 0, ',', '.'),
                            'peminjaman',
                            $loan['id_peminjaman'],
                            'CRON_JOB',
                            'Auto Notification System'
                        ]);
                    } catch (Exception $e) {
                        writeLog("⚠ Failed to log activity: " . $e->getMessage());
                    }
                    
                } else {
                    $notificationsFailed++;
                    writeLog("✗ Failed to send email notification");
                }
                
            } catch (Exception $e) {
                $notificationsFailed++;
                writeLog("✗ Email error: " . $e->getMessage());
            }
            
        } else {
            writeLog("⚠ No valid email address found - skipping notification");
            writeLog("  Email in database: " . ($loan['email'] ?? 'NULL'));
        }
    }
    
    // Summary
    writeLog("\n========================================");
    writeLog("PROCESS SUMMARY");
    writeLog("========================================");
    writeLog("Total overdue loans: {$totalOverdue}");
    writeLog("Notifications sent: {$notificationsSent}");
    writeLog("Notifications failed: {$notificationsFailed}");
    writeLog("Note: Fines will be recorded upon return in 'pengembalian' table");
    writeLog("========================================");
    writeLog("Process completed successfully at " . date('Y-m-d H:i:s'));
    writeLog("========================================\n");
    
    // Return status (untuk testing via browser)
    if (php_sapi_name() !== 'cli') {
        echo "<!DOCTYPE html><html><head><title>Overdue Check Results</title>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
            .container { max-width: 900px; margin: 30px auto; }
            .header { background: white; padding: 30px; border-radius: 10px 10px 0 0; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
            .header h2 { color: #667eea; margin-bottom: 10px; }
            .header .badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: 600; margin-top: 10px; }
            .header .badge.production { background: #28a745; color: white; }
            .header .badge.dev { background: #17a2b8; color: white; }
            pre { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); overflow-x: auto; line-height: 1.6; }
            .success { color: #28a745; font-weight: 600; }
            .error { color: #dc3545; font-weight: 600; }
            .info { color: #17a2b8; font-weight: 600; }
            .btn { display: inline-block; margin-top: 20px; padding: 12px 25px; background: white; color: #667eea; text-decoration: none; border-radius: 5px; font-weight: 600; box-shadow: 0 3px 10px rgba(0,0,0,0.2); transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        </style></head><body><div class='container'>";
        
        $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
        
        echo "<div class='header'>";
        echo "<h2>✓ Overdue Check Completed</h2>";
        echo "<span class='badge " . ($isProduction ? 'production' : 'dev') . "'>" . ($isProduction ? 'PRODUCTION (Railway)' : 'DEVELOPMENT (Localhost)') . "</span>";
        echo "</div>";
        
        echo "<pre>";
        echo "<span class='info'>Date & Time:</span> " . date('Y-m-d H:i:s') . "\n";
        echo "<span class='info'>Environment:</span> " . ($isProduction ? 'Production (Railway)' : 'Development (Localhost)') . "\n\n";
        echo "<span class='info'>Total overdue loans:</span> {$totalOverdue}\n";
        echo "<span class='success'>Notifications sent:</span> {$notificationsSent}\n";
        if ($notificationsFailed > 0) {
            echo "<span class='error'>Notifications failed:</span> {$notificationsFailed}\n";
        }
        echo "\n<span class='info'>Note:</span> Fines will be recorded in 'pengembalian' table upon return\n";
        echo "\n<span class='info'>Check log file for details:</span> " . basename($log_file);
        echo "</pre>";
        echo "<a href='../admin/pengaturan/test_notification.php' class='btn'>← Back to Dashboard</a>";
        echo "</div></body></html>";
    }
    
    exit(0);
    
} catch (Exception $e) {
    $error_message = "FATAL ERROR: " . $e->getMessage();
    writeLog($error_message);
    writeLog("Stack trace: " . $e->getTraceAsString());
    writeLog("========================================\n");
    
    // Send error email to admin (optional)
    try {
        if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
            sendEmail(
                ADMIN_EMAIL,
                'System Administrator',
                'Overdue Check Cron Job Failed',
                "<h3>Cron Job Error</h3><p>The overdue check cron job failed with error:</p><pre>" . htmlspecialchars($error_message) . "</pre><p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>"
            );
        }
    } catch (Exception $e2) {
        writeLog("Failed to send error email: " . $e2->getMessage());
    }
    
    // Return error status (untuk testing via browser)
    if (php_sapi_name() !== 'cli') {
        echo "<!DOCTYPE html><html><head><title>Overdue Check Error</title>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; padding: 20px; }
            .container { max-width: 900px; margin: 30px auto; }
            .header { background: white; padding: 30px; border-radius: 10px 10px 0 0; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
            .header h2 { color: #dc3545; }
            pre { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); color: #dc3545; overflow-x: auto; line-height: 1.6; }
            .btn { display: inline-block; margin-top: 20px; padding: 12px 25px; background: white; color: #dc3545; text-decoration: none; border-radius: 5px; font-weight: 600; box-shadow: 0 3px 10px rgba(0,0,0,0.2); transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        </style></head><body><div class='container'>";
        echo "<div class='header'><h2>✗ Overdue Check Failed</h2></div>";
        echo "<pre>";
        echo "Error: " . htmlspecialchars($error_message) . "\n\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Check log file: " . basename($log_file);
        echo "</pre>";
        echo "<a href='../admin/pengaturan/test_notification.php' class='btn'>← Back to Dashboard</a>";
        echo "</div></body></html>";
    }
    
    exit(1);
}
?>