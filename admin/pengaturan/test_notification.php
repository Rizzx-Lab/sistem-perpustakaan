<?php
/**
 * Testing Manual Notifikasi Keterlambatan
 * File: admin/pengaturan/test_notification.php
 */

require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/notification.php';

requireRole(['admin']);

$page_title = 'Test Notifikasi Email';

// Handle form submission
$test_result = null;
if (isset($_POST['run_test'])) {
    $test_type = $_POST['test_type'] ?? 'overdue';
    
    try {
        if ($test_type === 'overdue') {
            $count = checkAndSendOverdueNotifications($conn);
            $test_result = [
                'success' => true,
                'message' => "Berhasil mengirim {$count} notifikasi keterlambatan",
                'count' => $count
            ];
            logActivity('TEST_NOTIFIKASI', "Manual test notifikasi keterlambatan - {$count} email terkirim");
            
        } elseif ($test_type === 'fine_reminder') {
            $count = sendFineReminders($conn);
            $test_result = [
                'success' => true,
                'message' => "Berhasil mengirim {$count} reminder denda",
                'count' => $count
            ];
            logActivity('TEST_NOTIFIKASI', "Manual test reminder denda - {$count} email terkirim");
            
        } elseif ($test_type === 'due_reminder') {
            $daysBefore = (int)($_POST['days_before'] ?? 2);
            $count = sendDueDateReminders($conn, $daysBefore);
            $test_result = [
                'success' => true,
                'message' => "Berhasil mengirim {$count} reminder jatuh tempo (H-{$daysBefore})",
                'count' => $count
            ];
            logActivity('TEST_NOTIFIKASI', "Manual test reminder jatuh tempo - {$count} email terkirim");
        }
        
    } catch (Exception $e) {
        $test_result = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'count' => 0
        ];
    }
}

// Get statistics
$stats = getNotificationStats($conn, 30);

// Get recent overdue loans
// FIX: Changed p.dendd to p.denda
$overdueQuery = "
    SELECT 
        p.id_peminjaman,
        p.nik,
        p.tanggal_kembali,
        a.nama as nama_anggota,
        b.judul as judul_buku,
        u.email,
        DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
        pg.denda
    FROM peminjaman p
    INNER JOIN anggota a ON p.nik = a.nik
    INNER JOIN buku b ON p.isbn = b.isbn
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    WHERE p.status = 'dipinjam'
    AND p.tanggal_kembali < CURDATE()
    ORDER BY p.tanggal_kembali ASC
    LIMIT 10
";

$overdueStmt = $conn->query($overdueQuery);
$overdueLoans = $overdueStmt->fetchAll();

include '../../includes/header.php';
?>

<style>
/* Styling untuk memperbaiki card yang mepet */
.modern-card {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
    background: #fff;
}

.card-header-modern {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.card-body {
    padding: 20px;
}

.card-title-modern {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
}

/* Perbaikan untuk form controls */
.form-control-modern {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-label-modern {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

/* Perbaikan untuk tombol sukses */
.btn-success-modern {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    color: white;
}

.btn-success-modern:hover {
    background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
    border-color: #1e7e34;
    color: white;
}

/* Perbaikan untuk tombol */
.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Perbaikan untuk alert */
.alert {
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
}

.alert ul {
    margin-bottom: 0;
    padding-left: 1.2rem;
}

.alert li {
    margin-bottom: 4px;
}

.alert li:last-child {
    margin-bottom: 0;
}

/* Perbaikan untuk quick actions */
.d-grid .btn {
    border-radius: 8px;
    padding: 10px;
    text-align: left;
}

/* Perbaikan untuk form-text */
.form-text {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Perbaikan untuk invalid feedback */
.invalid-feedback {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
}

/* Perbaikan untuk breadcrumb */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 10px;
}

.breadcrumb-item a {
    text-decoration: none;
    color: #6c757d;
}

.breadcrumb-item.active {
    color: #495057;
    font-weight: 500;
}

/* Perbaikan untuk input group */
.input-group .btn-outline-secondary {
    border-color: #ced4da;
    border-radius: 0 8px 8px 0;
}

.input-group .form-control-modern {
    border-radius: 8px 0 0 8px;
}

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk table */
.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.table th {
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
}

.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e0e0e0;
}

/* Perbaikan untuk title gradient */
.title-gradient {
    background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logo-emoji {
    margin-right: 10px;
    font-size: 1.5rem;
}

/* Statistics cards */
.modern-card.text-center .card-body {
    padding: 20px;
}

.modern-card.text-center i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.modern-card.text-center h3 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.modern-card.text-center small {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Form check styling */
.form-check-input {
    width: 18px;
    height: 18px;
    margin-top: 0.25rem;
    margin-right: 10px;
}

.form-check-label {
    display: flex;
    flex-direction: column;
}

.form-check-label strong {
    margin-bottom: 4px;
}

/* Code block styling */
pre {
    background-color: #343a40;
    color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    font-size: 12px;
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

code {
    font-family: 'Courier New', monospace;
}

/* Log entries */
.border-bottom {
    border-bottom: 1px solid #e9ecef !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modern-card {
        margin-bottom: 15px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .card-header-modern {
        padding: 12px 15px;
    }
    
    .form-control-modern {
        padding: 8px 12px;
    }
    
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }
    
    .btn-modern {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .input-group .btn-outline-secondary {
        padding: 8px 12px;
    }
    
    .table th,
    .table td {
        padding: 8px 10px;
        font-size: 0.9rem;
    }
    
    .badge {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .modern-card.text-center h3 {
        font-size: 1.5rem;
    }
    
    .modern-card.text-center i {
        font-size: 1.5rem;
    }
    
    .row.g-3 > [class*="col-"] {
        margin-bottom: 1rem;
    }
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="./"><i class="fas fa-cogs"></i> Pengaturan</a>
                    </li>
                    <li class="breadcrumb-item active">Test Notifikasi</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📧</span>
                <span class="title-gradient">Test Notifikasi Email</span>
            </h1>
        </div>
        <div>
            <a href="./" class="btn btn-modern btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <?php if ($test_result): ?>
        <div class="alert alert-<?= $test_result['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
            <i class="fas fa-<?= $test_result['success'] ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($test_result['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Statistics -->
        <div class="col-lg-8">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="modern-card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h3 class="mb-0"><?= count($overdueLoans) ?></h3>
                            <small class="text-muted">Peminjaman Terlambat</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="modern-card text-center">
                        <div class="card-body">
                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                            <h3 class="mb-0"><?= $stats['total_overdue_notifications'] ?></h3>
                            <small class="text-muted">Notif Keterlambatan (30d)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="modern-card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill fa-2x text-info mb-2"></i>
                            <h3 class="mb-0"><?= $stats['total_fine_reminders'] ?></h3>
                            <small class="text-muted">Reminder Denda (30d)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="modern-card text-center">
                        <div class="card-body">
                            <i class="fas fa-bell fa-2x text-success mb-2"></i>
                            <h3 class="mb-0"><?= $stats['notifications_today'] ?></h3>
                            <small class="text-muted">Notifikasi Hari Ini</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Form -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-flask me-2"></i>Test Manual Notifikasi
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="testForm">
                        <div class="mb-3">
                            <label class="form-label-modern">Pilih Jenis Test</label>
                            <select name="test_type" class="form-control-modern" id="testType" required>
                                <option value="overdue">Notifikasi Keterlambatan</option>
                                <option value="fine_reminder">Reminder Denda</option>
                                <option value="due_reminder">Reminder Jatuh Tempo</option>
                            </select>
                            <div class="form-text">
                                Pilih jenis notifikasi yang ingin dikirim
                            </div>
                        </div>

                        <div class="mb-3" id="daysBeforeSection" style="display: none;">
                            <label class="form-label-modern">Hari Sebelum Jatuh Tempo</label>
                            <input type="number" name="days_before" class="form-control-modern" value="2" min="1" max="7">
                            <div class="form-text">
                                Kirim reminder berapa hari sebelum jatuh tempo (1-7 hari)
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Catatan:</strong> 
                            <ul class="mb-0 mt-2">
                                <li><strong>Notifikasi Keterlambatan:</strong> Mengirim email ke semua anggota yang terlambat mengembalikan buku</li>
                                <li><strong>Reminder Denda:</strong> Mengirim email ke anggota yang memiliki denda belum dibayar</li>
                                <li><strong>Reminder Jatuh Tempo:</strong> Mengirim email pengingat sebelum tanggal jatuh tempo</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-modern btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-redo me-2"></i>Reset
                            </button>
                            <button type="submit" name="run_test" class="btn btn-modern btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Kirim Notifikasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Overdue Loans List -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-list me-2"></i>Peminjaman Terlambat Saat Ini
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($overdueLoans) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Tgl Kembali</th>
                                        <th>Terlambat</th>
                                        <th>Denda</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdueLoans as $loan): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($loan['nama_anggota']) ?></strong>
                                                <br><small class="text-muted">NIK: <?= htmlspecialchars($loan['nik']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($loan['judul_buku']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($loan['tanggal_kembali'])) ?></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?= $loan['hari_terlambat'] ?> hari
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                // Tampilkan denda jika ada
                                                if ($loan['denda'] && $loan['denda'] > 0): 
                                                ?>
                                                    <strong class="text-danger">
                                                        <?= formatRupiah($loan['denda']) ?>
                                                    </strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($loan['email']): ?>
                                                    <i class="fas fa-check-circle text-success me-1"></i>
                                                    <small><?= htmlspecialchars($loan['email']) ?></small>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle text-danger me-1"></i>
                                                    <small class="text-muted">Tidak ada</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p class="mb-0">Tidak ada peminjaman yang terlambat saat ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Info Box -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informasi
                    </h6>
                </div>
                <div class="card-body">
                    <h6 class="text-primary">Cara Kerja Sistem:</h6>
                    <ol class="small">
                        <li class="mb-2">Sistem mengecek peminjaman yang melewati tanggal jatuh tempo</li>
                        <li class="mb-2">Menghitung denda berdasarkan keterlambatan (Rp 1.000/hari)</li>
                        <li class="mb-2">Mengirim email notifikasi ke anggota yang memiliki email terdaftar</li>
                        <li class="mb-2">Mencatat aktivitas pengiriman ke log sistem</li>
                    </ol>

                    <hr>

                    <h6 class="text-primary">Automasi dengan Cron Job:</h6>
                    <div class="bg-light p-3 rounded small">
                        <p class="mb-2"><strong>File:</strong> <code>cron/check_overdue.php</code></p>
                        <p class="mb-2"><strong>Setup Crontab (Linux):</strong></p>
                        <pre class="bg-dark text-white p-2 rounded mb-0" style="font-size: 11px;">0 9 * * * php /path/to/cron/check_overdue.php</pre>
                        <p class="mb-0 mt-2 text-muted">Akan berjalan setiap hari jam 09:00</p>
                    </div>
                </div>
            </div>

            <!-- Email Config Check -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-cog me-2"></i>Konfigurasi Email
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>SMTP Host:</span>
                            <strong><?= SMTP_HOST ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>SMTP Port:</span>
                            <strong><?= SMTP_PORT ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>From Email:</span>
                            <strong><?= EMAIL_FROM ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>From Name:</span>
                            <strong><?= EMAIL_FROM_NAME ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Status:</span>
                            <span class="badge bg-success">Configured</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Logs -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-history me-2"></i>Log Terakhir
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $logQuery = "
                        SELECT action, description, created_at 
                        FROM log_aktivitas 
                        WHERE action IN ('NOTIFIKASI_KETERLAMBATAN', 'REMINDER_DENDA', 'REMINDER_JATUH_TEMPO', 'TEST_NOTIFIKASI')
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ";
                    $logStmt = $conn->query($logQuery);
                    $recentLogs = $logStmt->fetchAll();
                    ?>

                    <?php if (count($recentLogs) > 0): ?>
                        <div class="small">
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="text-primary"><?= htmlspecialchars($log['action']) ?></strong>
                                            <p class="mb-0 text-muted"><?= htmlspecialchars($log['description']) ?></p>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="log.php" class="btn btn-modern btn-sm btn-outline-primary w-100 mt-2">
                            <i class="fas fa-list me-2"></i>Lihat Semua Log
                        </a>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">Belum ada log notifikasi</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide days before field based on test type
document.getElementById('testType').addEventListener('change', function() {
    const daysSection = document.getElementById('daysBeforeSection');
    if (this.value === 'due_reminder') {
        daysSection.style.display = 'block';
    } else {
        daysSection.style.display = 'none';
    }
});

// Confirm before sending
document.getElementById('testForm').addEventListener('submit', function(e) {
    const testType = document.getElementById('testType');
    const selectedText = testType.options[testType.selectedIndex].text;
    
    if (!confirm(`Yakin ingin mengirim ${selectedText}?\n\nEmail akan dikirim ke semua anggota yang memenuhi kriteria.`)) {
        e.preventDefault();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>