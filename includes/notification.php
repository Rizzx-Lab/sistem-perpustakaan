<?php
/**
 * Notification System untuk Peminjaman Terlambat (PDO VERSION - UPDATED)
 * File: includes/notification.php
 * 
 * PERUBAHAN UTAMA:
 * - Denda dipindah ke tabel pengembalian
 * - Peminjaman tidak lagi menyimpan denda
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/email_functions.php';
require_once __DIR__ . '/functions.php';

/**
 * Cek dan kirim notifikasi untuk peminjaman yang terlambat
 * 
 * @param PDO $conn Database connection
 * @return int Jumlah notifikasi yang berhasil dikirim
 */
function checkAndSendOverdueNotifications($conn) {
    $today = date('Y-m-d');
    $dendaPerHari = (int)getSetting('denda_per_hari', 1000);
    
    // Query untuk mendapatkan peminjaman yang terlambat dengan email user
    // UPDATED: Tidak lagi SELECT denda dari peminjaman
    $query = "
        SELECT 
            p.id_peminjaman,
            p.nik,
            p.isbn,
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
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute([
            'today' => $today,
            'today2' => $today
        ]);
        
        $notificationsSent = 0;
        $emailsSent = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hariTerlambat = (int)$row['hari_terlambat'];
            $dendaBaru = $hariTerlambat * $dendaPerHari;
            
            // REMOVED: Update denda di peminjaman (tidak dibutuhkan lagi)
            // Denda akan dicatat saat pengembalian di tabel pengembalian
            
            // Cek apakah email valid dan belum pernah dikirim
            if (!empty($row['email']) && 
                filter_var($row['email'], FILTER_VALIDATE_EMAIL) &&
                !in_array($row['email'] . '-' . $row['id_peminjaman'], $emailsSent)) {
                
                // Kirim notifikasi email
                $emailSent = sendOverdueNotification(
                    $row['email'],
                    $row['nama_anggota'],
                    $row['judul_buku'],
                    date('d/m/Y', strtotime($row['tanggal_kembali'])),
                    $hariTerlambat,
                    $dendaBaru
                );
                
                if ($emailSent) {
                    $notificationsSent++;
                    $emailsSent[] = $row['email'] . '-' . $row['id_peminjaman'];
                    
                    // Log aktivitas ke database
                    try {
                        $logStmt = $conn->prepare("
                            INSERT INTO log_aktivitas 
                            (user_id, username, nama, role, action, description, target_type, target_id, ip_address, user_agent, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $description = sprintf(
                            "Notifikasi keterlambatan terkirim untuk buku '%s' - %d hari terlambat - Denda: Rp %s",
                            $row['judul_buku'],
                            $hariTerlambat,
                            number_format($dendaBaru, 0, ',', '.')
                        );
                        
                        $logStmt->execute([
                            $row['user_id'],
                            $row['username'] ?? 'system',
                            $row['nama_anggota'],
                            'anggota',
                            'NOTIFIKASI_KETERLAMBATAN',
                            $description,
                            'peminjaman',
                            $row['id_peminjaman'],
                            'CRON_JOB',
                            'Auto Notification System'
                        ]);
                        
                    } catch (PDOException $e) {
                        error_log("Failed to log notification activity: " . $e->getMessage());
                    }
                    
                    error_log("Overdue notification sent to: {$row['email']} - {$row['nama_anggota']} - Book: {$row['judul_buku']} - {$hariTerlambat} days late");
                } else {
                    error_log("Failed to send overdue notification to: {$row['email']} - {$row['nama_anggota']}");
                }
            } else {
                if (empty($row['email'])) {
                    error_log("No email found for member: {$row['nama_anggota']} (NIK: {$row['nik']})");
                }
            }
        }
        
        return $notificationsSent;
        
    } catch (PDOException $e) {
        error_log("Error in checkAndSendOverdueNotifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Kirim reminder untuk denda yang belum dibayar
 * UPDATED: Ambil denda dari tabel pengembalian
 * 
 * @param PDO $conn Database connection
 * @return int Jumlah reminder yang berhasil dikirim
 */
function sendFineReminders($conn) {
    // Query untuk mendapatkan anggota dengan denda yang belum dibayar
    // UPDATED: JOIN dengan tabel pengembalian
    $query = "
        SELECT 
            a.nik,
            a.nama as nama_anggota,
            u.id as user_id,
            u.email,
            u.username,
            SUM(pg.denda) as total_denda,
            COUNT(pg.id_pengembalian) as jumlah_peminjaman,
            GROUP_CONCAT(b.judul SEPARATOR ', ') as daftar_buku
        FROM pengembalian pg
        INNER JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        INNER JOIN anggota a ON p.nik = a.nik
        LEFT JOIN users u ON a.user_id = u.id
        INNER JOIN buku b ON p.isbn = b.isbn
        WHERE pg.denda > 0
        GROUP BY a.nik, a.nama, u.id, u.email, u.username
        HAVING total_denda > 0
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $remindersSent = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Cek apakah email valid
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                
                $emailSent = sendFineReminderEmail(
                    $row['email'],
                    $row['nama_anggota'],
                    $row['total_denda'],
                    $row['jumlah_peminjaman']
                );
                
                if ($emailSent) {
                    $remindersSent++;
                    
                    // Log aktivitas ke database
                    try {
                        $logStmt = $conn->prepare("
                            INSERT INTO log_aktivitas 
                            (user_id, username, nama, role, action, description, target_type, target_id, ip_address, user_agent, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $description = sprintf(
                            "Reminder pembayaran denda terkirim via email - Total: Rp %s untuk %d pengembalian",
                            number_format($row['total_denda'], 0, ',', '.'),
                            $row['jumlah_peminjaman']
                        );
                        
                        $logStmt->execute([
                            $row['user_id'],
                            $row['username'] ?? 'system',
                            $row['nama_anggota'],
                            'anggota',
                            'REMINDER_DENDA',
                            $description,
                            'denda',
                            $row['nik'],
                            'CRON_JOB',
                            'Fine Reminder System'
                        ]);
                        
                    } catch (PDOException $e) {
                        error_log("Failed to log reminder activity: " . $e->getMessage());
                    }
                    
                    error_log("Fine reminder sent to: {$row['email']} - Total: Rp " . number_format($row['total_denda'], 0, ',', '.'));
                } else {
                    error_log("Failed to send fine reminder to: {$row['email']}");
                }
            }
        }
        
        return $remindersSent;
        
    } catch (PDOException $e) {
        error_log("Error in sendFineReminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Kirim notifikasi sebelum jatuh tempo (reminder H-2 atau H-1)
 * 
 * @param PDO $conn Database connection
 * @param int $daysBefore Berapa hari sebelum jatuh tempo (default: 2)
 * @return int Jumlah reminder yang berhasil dikirim
 */
function sendDueDateReminders($conn, $daysBefore = 2) {
    $targetDate = date('Y-m-d', strtotime("+{$daysBefore} days"));
    
    $query = "
        SELECT 
            p.id_peminjaman,
            p.nik,
            p.isbn,
            p.tanggal_kembali,
            a.nama as nama_anggota,
            b.judul as judul_buku,
            penerbit.nama_penerbit,
            u.id as user_id,
            u.email,
            u.username
        FROM peminjaman p
        INNER JOIN anggota a ON p.nik = a.nik
        INNER JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit ON b.id_penerbit = penerbit.id_penerbit
        LEFT JOIN users u ON a.user_id = u.id
        WHERE p.status = 'dipinjam'
        AND p.tanggal_kembali = :target_date
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute(['target_date' => $targetDate]);
        
        $remindersSent = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                
                $subject = "Pengingat: Buku Akan Jatuh Tempo dalam {$daysBefore} Hari";
                
                $body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 5px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Pengingat Pengembalian Buku</h1>
                            <p>" . COMPANY_NAME . "</p>
                        </div>
                        <div class='content'>
                            <p>Yth. <strong>{$row['nama_anggota']}</strong>,</p>
                            
                            <div class='info-box'>
                                <p style='margin: 0;'><strong>Pengingat:</strong> Buku yang Anda pinjam akan jatuh tempo dalam <strong>{$daysBefore} hari</strong>.</p>
                            </div>
                            
                            <h3>Detail Peminjaman:</h3>
                            <table style='width: 100%;'>
                                <tr>
                                    <td style='padding: 5px 0;'><strong>Judul Buku:</strong></td>
                                    <td>{$row['judul_buku']}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0;'><strong>Penerbit:</strong></td>
                                    <td>" . ($row['nama_penerbit'] ?? '-') . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 5px 0;'><strong>Tanggal Kembali:</strong></td>
                                    <td>" . date('d/m/Y', strtotime($row['tanggal_kembali'])) . "</td>
                                </tr>
                            </table>
                            
                            <p style='margin-top: 20px;'><strong>Mohon kembalikan buku tepat waktu untuk menghindari denda keterlambatan.</strong></p>
                            
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
                
                $emailSent = sendEmail($row['email'], $row['nama_anggota'], $subject, $body);
                
                if ($emailSent) {
                    $remindersSent++;
                    
                    // Log aktivitas
                    try {
                        $logStmt = $conn->prepare("
                            INSERT INTO log_aktivitas 
                            (user_id, username, nama, role, action, description, target_type, target_id, ip_address, user_agent, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $description = "Reminder jatuh tempo terkirim untuk buku '{$row['judul_buku']}' - H-{$daysBefore}";
                        
                        $logStmt->execute([
                            $row['user_id'],
                            $row['username'] ?? 'system',
                            $row['nama_anggota'],
                            'anggota',
                            'REMINDER_JATUH_TEMPO',
                            $description,
                            'peminjaman',
                            $row['id_peminjaman'],
                            'CRON_JOB',
                            'Due Date Reminder System'
                        ]);
                        
                    } catch (PDOException $e) {
                        error_log("Failed to log due date reminder: " . $e->getMessage());
                    }
                }
            }
        }
        
        return $remindersSent;
        
    } catch (PDOException $e) {
        error_log("Error in sendDueDateReminders: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get statistik notifikasi (untuk dashboard admin)
 * 
 * @param PDO $conn Database connection
 * @param int $days Jumlah hari terakhir (default: 30)
 * @return array Statistik notifikasi
 */
function getNotificationStats($conn, $days = 30) {
    try {
        $stats = [];
        
        // Total notifikasi keterlambatan
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM log_aktivitas 
            WHERE action = 'NOTIFIKASI_KETERLAMBATAN' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $stats['total_overdue_notifications'] = $stmt->fetchColumn();
        
        // Total reminder denda
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM log_aktivitas 
            WHERE action = 'REMINDER_DENDA' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $stats['total_fine_reminders'] = $stmt->fetchColumn();
        
        // Total reminder jatuh tempo
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM log_aktivitas 
            WHERE action = 'REMINDER_JATUH_TEMPO' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $stats['total_due_reminders'] = $stmt->fetchColumn();
        
        // Notifikasi hari ini
        $stmt = $conn->query("
            SELECT COUNT(*) 
            FROM log_aktivitas 
            WHERE action IN ('NOTIFIKASI_KETERLAMBATAN', 'REMINDER_DENDA', 'REMINDER_JATUH_TEMPO')
            AND DATE(created_at) = CURDATE()
        ");
        $stats['notifications_today'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error in getNotificationStats: " . $e->getMessage());
        return [
            'total_overdue_notifications' => 0,
            'total_fine_reminders' => 0,
            'total_due_reminders' => 0,
            'notifications_today' => 0
        ];
    }
}
?>