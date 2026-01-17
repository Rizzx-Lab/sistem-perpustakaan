<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'System Maintenance';
include '../../config/database.php';

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';
$confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';

if (empty($action)) {
    redirect('index.php', 'Action tidak valid', 'error');
}

// ========== PROSES KONFIRMASI (Tampilkan Info) ==========
if ($confirm !== 'yes') {
    
    // Siapkan data untuk konfirmasi
    $confirm_data = [];
    
    switch ($action) {
        case 'optimize':
            // Get table info
            try {
                $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $total_size = 0;
                
                foreach ($tables as $table) {
                    $result = $conn->query("
                        SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size 
                        FROM information_schema.tables 
                        WHERE table_schema = '" . DB_NAME . "' AND table_name = '$table'
                    ")->fetch();
                    $total_size += $result['size'];
                }
                
                $confirm_data = [
                    'title' => 'Optimize Database',
                    'icon' => 'fa-database',
                    'color' => 'primary',
                    'items' => [
                        'Total tabel' => count($tables) . ' tabel',
                        'Ukuran database' => $total_size . ' MB',
                        'Operasi' => 'OPTIMIZE, ANALYZE, CHECK',
                        'Estimasi waktu' => '~' . (count($tables) * 3) . ' detik'
                    ],
                    'warnings' => [
                        'Database akan di-lock sementara',
                        'Proses tidak bisa dibatalkan',
                        'Backup otomatis tidak dilakukan'
                    ]
                ];
            } catch (PDOException $e) {
                redirect('index.php', 'Error: ' . $e->getMessage(), 'error');
            }
            break;
            
        case 'clear_cache':
            // Scan cache files
            $cache_info = [];
            $total_size = 0;
            $total_files = 0;
            
            // Session files
            if (session_status() === PHP_SESSION_ACTIVE) {
                $session_path = session_save_path();
                if (!empty($session_path) && is_dir($session_path)) {
                    $session_files = glob($session_path . '/sess_*');
                    $count = 0;
                    $size = 0;
                    foreach ($session_files as $file) {
                        if (basename($file) !== 'sess_' . session_id() && filemtime($file) < time() - 86400) {
                            $size += filesize($file);
                            $count++;
                        }
                    }
                    if ($count > 0) {
                        $cache_info['Session files'] = "$count files (" . formatBytes($size) . ")";
                        $total_files += $count;
                        $total_size += $size;
                    }
                }
            }
            
            // Temp uploads
            $upload_path = __DIR__ . '/../../uploads/temp';
            if (is_dir($upload_path)) {
                $temp_files = glob($upload_path . '/*');
                $count = 0;
                $size = 0;
                foreach ($temp_files as $file) {
                    if (is_file($file) && filemtime($file) < time() - 3600) {
                        $size += filesize($file);
                        $count++;
                    }
                }
                if ($count > 0) {
                    $cache_info['Temporary files'] = "$count files (" . formatBytes($size) . ")";
                    $total_files += $count;
                    $total_size += $size;
                }
            }
            
            // OPcache
            if (function_exists('opcache_reset')) {
                $cache_info['PHP OPcache'] = 'Akan di-reset';
            }
            
            // App cache
            $cache_path = __DIR__ . '/../../cache';
            if (is_dir($cache_path)) {
                $cache_files = glob($cache_path . '/*');
                $count = 0;
                $size = 0;
                foreach ($cache_files as $file) {
                    if (is_file($file)) {
                        $size += filesize($file);
                        $count++;
                    }
                }
                if ($count > 0) {
                    $cache_info['Cache files'] = "$count files (" . formatBytes($size) . ")";
                    $total_files += $count;
                    $total_size += $size;
                }
            }
            
            if (empty($cache_info)) {
                $cache_info['Status'] = 'Tidak ada cache untuk dibersihkan';
            }
            
            $cache_info['TOTAL'] = "$total_files files (" . formatBytes($total_size) . ")";
            
            $confirm_data = [
                'title' => 'Clear Cache',
                'icon' => 'fa-broom',
                'color' => 'success',
                'items' => $cache_info,
                'warnings' => [
                    'Website mungkin sedikit lambat setelah clear cache',
                    'Session lama (>24 jam) akan dihapus',
                    'File temporary akan dihapus permanen'
                ]
            ];
            break;
            
        default:
            redirect('index.php', 'Invalid action', 'error');
            break;
    }
    
    include '../../includes/header.php';
    ?>
    
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Pengaturan</a></li>
                <li class="breadcrumb-item active">Maintenance</li>
            </ol>
        </nav>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="modern-card border-<?= $confirm_data['color'] ?>">
                    <div class="card-header bg-<?= $confirm_data['color'] ?> text-white">
                        <h5 class="mb-0">
                            <i class="fas <?= $confirm_data['icon'] ?> me-2"></i>
                            Konfirmasi <?= $confirm_data['title'] ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> Proses ini akan melakukan perubahan pada sistem.
                        </div>
                        
                        <h6 class="mb-3">Detail Operasi:</h6>
                        <table class="table table-sm table-borderless">
                            <?php foreach ($confirm_data['items'] as $label => $value): ?>
                            <tr>
                                <th width="40%" class="ps-3">
                                    <i class="fas fa-check-circle text-<?= $confirm_data['color'] ?> me-2"></i>
                                    <?= $label ?>
                                </th>
                                <td><strong><?= $value ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        
                        <?php if (!empty($confirm_data['warnings'])): ?>
                        <div class="alert alert-danger mt-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-skull-crossbones me-2"></i>Peringatan:
                            </h6>
                            <ul class="mb-0">
                                <?php foreach ($confirm_data['warnings'] as $warning): ?>
                                <li><?= $warning ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmCheckbox" required>
                                    <label class="form-check-label" for="confirmCheckbox">
                                        <strong>Saya memahami dan ingin melanjutkan proses ini</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="button" class="btn btn-<?= $confirm_data['color'] ?>" id="confirmButton" disabled onclick="startProcess()">
                                <i class="fas fa-play me-2"></i>Lanjutkan Proses
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Enable button when checkbox checked
    document.getElementById('confirmCheckbox').addEventListener('change', function() {
        document.getElementById('confirmButton').disabled = !this.checked;
    });
    
    function startProcess() {
        // Show loading overlay
        const loadingHtml = `
            <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
                 style="background: rgba(0,0,0,0.7); z-index: 9999;">
                <div class="text-center text-white">
                    <div class="spinner-border mb-3" style="width: 4rem; height: 4rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h4>Processing...</h4>
                    <p>Mohon tunggu, jangan tutup halaman ini</p>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', loadingHtml);
        
        // Redirect to process
        window.location.href = '?action=<?= $action ?>&confirm=yes';
    }
    </script>
    
    <?php
    include '../../includes/footer.php';
    exit;
}

// ========== PROSES EKSEKUSI (confirm=yes) ==========

$start_time = microtime(true);
$results = [];

try {
    switch ($action) {
        
        // ========== OPTIMIZE DATABASE ==========
        case 'optimize':
            $conn->beginTransaction();
            
            $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $total_tables = count($tables);
            $optimized_tables = 0;
            $size_before = 0;
            $size_after = 0;
            $errors = [];
            
            // Get size before
            foreach ($tables as $table) {
                $result = $conn->query("
                    SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = '" . DB_NAME . "' AND table_name = '$table'
                ")->fetch();
                $size_before += $result['size'];
            }
            
            // Optimize tables
            foreach ($tables as $table) {
                try {
                    $conn->exec("OPTIMIZE TABLE `$table`");
                    $conn->exec("ANALYZE TABLE `$table`");
                    $optimized_tables++;
                } catch (PDOException $e) {
                    $errors[] = $table;
                }
            }
            
            // Get size after
            foreach ($tables as $table) {
                $result = $conn->query("
                    SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = '" . DB_NAME . "' AND table_name = '$table'
                ")->fetch();
                $size_after += $result['size'];
            }
            
            $conn->commit();
            
            $duration = round(microtime(true) - $start_time, 2);
            $space_saved = round($size_before - $size_after, 2);
            
            $results = [
                'success' => true,
                'title' => 'Database Optimization Complete!',
                'icon' => 'fa-database',
                'color' => 'success',
                'stats' => [
                    'Tables optimized' => "$optimized_tables / $total_tables tables",
                    'Size before' => $size_before . ' MB',
                    'Size after' => $size_after . ' MB',
                    'Space saved' => ($space_saved > 0 ? $space_saved : 0) . ' MB',
                    'Duration' => $duration . ' seconds'
                ],
                'errors' => $errors
            ];
            
            logActivity('OPTIMIZE_DATABASE', "$optimized_tables/$total_tables tables optimized, saved $space_saved MB");
            break;
            
        // ========== CLEAR CACHE ==========
        case 'clear_cache':
            $cleared = [];
            $total_size = 0;
            $total_files = 0;
            
            // Clear sessions
            if (session_status() === PHP_SESSION_ACTIVE) {
                $session_path = session_save_path();
                if (!empty($session_path) && is_dir($session_path)) {
                    $session_files = glob($session_path . '/sess_*');
                    $count = 0;
                    $size = 0;
                    
                    foreach ($session_files as $file) {
                        if (basename($file) === 'sess_' . session_id()) continue;
                        if (filemtime($file) < time() - 86400) {
                            $size += filesize($file);
                            @unlink($file);
                            $count++;
                        }
                    }
                    
                    if ($count > 0) {
                        $cleared['Session files'] = "$count files";
                        $total_size += $size;
                        $total_files += $count;
                    }
                }
            }
            
            // Clear temp files
            $upload_path = __DIR__ . '/../../uploads/temp';
            if (is_dir($upload_path)) {
                $temp_files = glob($upload_path . '/*');
                $count = 0;
                $size = 0;
                
                foreach ($temp_files as $file) {
                    if (is_file($file) && filemtime($file) < time() - 3600) {
                        $size += filesize($file);
                        @unlink($file);
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    $cleared['Temp files'] = "$count files";
                    $total_size += $size;
                    $total_files += $count;
                }
            }
            
            // Clear OPcache
            if (function_exists('opcache_reset')) {
                if (opcache_reset()) {
                    $cleared['PHP OPcache'] = "Cleared";
                }
            }
            
            // Clear app cache
            $cache_path = __DIR__ . '/../../cache';
            if (is_dir($cache_path)) {
                $cache_files = glob($cache_path . '/*');
                $count = 0;
                $size = 0;
                
                foreach ($cache_files as $file) {
                    if (is_file($file)) {
                        $size += filesize($file);
                        @unlink($file);
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    $cleared['Cache files'] = "$count files";
                    $total_size += $size;
                    $total_files += $count;
                }
            }
            
            $duration = round(microtime(true) - $start_time, 2);
            
            $results = [
                'success' => true,
                'title' => 'Cache Cleared Successfully!',
                'icon' => 'fa-broom',
                'color' => 'success',
                'stats' => array_merge($cleared, [
                    'Total files deleted' => $total_files . ' files',
                    'Total space freed' => formatBytes($total_size),
                    'Duration' => $duration . ' seconds'
                ]),
                'errors' => []
            ];
            
            logActivity('CLEAR_CACHE', "$total_files files deleted, " . formatBytes($total_size) . " freed");
            break;
            
        default:
            throw new Exception('Invalid action');
            break;
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    $results = [
        'success' => false,
        'title' => 'Process Failed!',
        'icon' => 'fa-times-circle',
        'color' => 'danger',
        'error_message' => $e->getMessage()
    ];
    
    logActivity('MAINTENANCE_ERROR', "Action: $action, Error: " . $e->getMessage());
}

// Store results in session for modal display
$_SESSION['maintenance_result'] = $results;
redirect('index.php?show_result=1', '', '');

// Helper function
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}