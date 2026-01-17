<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Backup Database';
include '../../config/database.php';

// Handle backup request
if (isset($_POST['create_backup'])) {
    $backup_type = $_POST['backup_type'] ?? 'structure_data';
    
    try {
        $backup_content = createDatabaseBackup($backup_type);
        
        if ($backup_content) {
            $filename = 'perpustakaan_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filesize = strlen($backup_content);
            
            // ===== LOG ACTIVITY - BACKUP DATABASE =====
            logActivity(
                'BACKUP_DATABASE',
                "Backup database dibuat: {$filename} (Type: {$backup_type}, Size: " . formatBytes($filesize) . ")",
                'backup',
                $filename
            );
            
            // Force download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . $filesize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            echo $backup_content;
            exit;
        } else {
            setFlashMessage('Gagal membuat backup database', 'error');
        }
    } catch (Exception $e) {
        setFlashMessage('Error: ' . $e->getMessage(), 'error');
    }
}

// Get database info
try {
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $table_name = $row[0];
        
        // Get row count
        $count_stmt = $conn->query("SELECT COUNT(*) FROM `{$table_name}`");
        $row_count = $count_stmt->fetchColumn();
        
        // Get table size
        $size_stmt = $conn->query("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
            FROM information_schema.tables 
            WHERE table_schema = '" . DB_NAME . "' 
            AND table_name = '{$table_name}'
        ");
        $size_info = $size_stmt->fetch();
        
        $tables[] = [
            'name' => $table_name,
            'rows' => $row_count,
            'size' => $size_info['size_mb'] ?? 0
        ];
    }
} catch (PDOException $e) {
    $error_message = "Error mengambil info database: " . $e->getMessage();
}

// Function to create database backup
function createDatabaseBackup($backup_type = 'structure_data') {
    global $conn;
    
    $backup_content = '';
    $backup_content .= "-- ==========================================\n";
    $backup_content .= "-- DATABASE BACKUP PERPUSTAKAAN\n";
    $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup_content .= "-- Backup Type: " . $backup_type . "\n";
    $backup_content .= "-- ==========================================\n\n";
    
    $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $backup_content .= "SET AUTOCOMMIT = 0;\n";
    $backup_content .= "START TRANSACTION;\n";
    $backup_content .= "SET time_zone = \"+00:00\";\n\n";
    
    try {
        // Get all tables
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            if ($backup_type === 'structure_only' || $backup_type === 'structure_data') {
                // Export table structure
                $create_stmt = $conn->query("SHOW CREATE TABLE `{$table_name}`");
                $create_table = $create_stmt->fetch();
                
                $backup_content .= "-- --------------------------------------------------------\n";
                $backup_content .= "-- Table structure for table `{$table_name}`\n";
                $backup_content .= "-- --------------------------------------------------------\n\n";
                $backup_content .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
                $backup_content .= $create_table['Create Table'] . ";\n\n";
            }
            
            if ($backup_type === 'data_only' || $backup_type === 'structure_data') {
                // Export table data
                $data_stmt = $conn->query("SELECT * FROM `{$table_name}`");
                $row_count = $data_stmt->rowCount();
                
                if ($row_count > 0) {
                    $backup_content .= "-- --------------------------------------------------------\n";
                    $backup_content .= "-- Dumping data for table `{$table_name}`\n";
                    $backup_content .= "-- --------------------------------------------------------\n\n";
                    
                    $backup_content .= "INSERT INTO `{$table_name}` VALUES\n";
                    
                    $values = [];
                    while ($row = $data_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $row_values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $row_values[] = 'NULL';
                            } else {
                                $row_values[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $values[] = '(' . implode(', ', $row_values) . ')';
                    }
                    
                    $backup_content .= implode(",\n", $values) . ";\n\n";
                }
            }
        }
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $backup_content .= "COMMIT;\n";
        
        return $backup_content;
        
    } catch (Exception $e) {
        throw new Exception("Error creating backup: " . $e->getMessage());
    }
}

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
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
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

/* Perbaikan untuk table modern */
.table-modern {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    margin: 0;
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.table-modern td {
    padding: 10px 15px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
}

.table-modern tr:hover td {
    background-color: #f8f9fa;
}

.table-modern tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.card-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #e0e0e0;
    padding: 15px 20px;
}

/* Progress bar untuk modal */
.progress {
    height: 6px;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

/* Modal styling */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

.modal-body {
    padding: 30px;
}

/* Code styling */
code {
    background-color: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    display: inline-block;
    word-break: break-all;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
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
    
    .table-modern th,
    .table-modern td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }
    
    .badge {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .modal-body {
        padding: 20px;
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
                    <li class="breadcrumb-item active">Backup</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-database me-2 text-info"></i>Backup Database
            </h1>
        </div>
        <div>
            <a href="sistem.php" class="btn btn-modern btn-outline-primary">
                <i class="fas fa-cogs me-2"></i>Pengaturan
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Backup Form -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-download me-2"></i>Buat Backup Database
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="backupForm">
                        <div class="mb-4">
                            <label class="form-label-modern">Pilih Tipe Backup:</label>
                            
                            <div class="mt-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="backup_type" id="structure_data" value="structure_data" checked>
                                    <label class="form-check-label" for="structure_data">
                                        <strong>Struktur + Data (Lengkap)</strong>
                                        <div class="small text-muted">Backup lengkap termasuk struktur tabel dan semua data</div>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="backup_type" id="structure_only" value="structure_only">
                                    <label class="form-check-label" for="structure_only">
                                        <strong>Struktur Saja</strong>
                                        <div class="small text-muted">Hanya struktur tabel tanpa data</div>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="backup_type" id="data_only" value="data_only">
                                    <label class="form-check-label" for="data_only">
                                        <strong>Data Saja</strong>
                                        <div class="small text-muted">Hanya data tanpa struktur tabel</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Catatan Penting:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Backup akan didownload sebagai file SQL</li>
                                <li>Simpan file backup di tempat yang aman</li>
                                <li>Lakukan backup secara berkala untuk keamanan data</li>
                                <li>Backup lengkap direkomendasikan untuk restore penuh</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div class="text-muted small">
                                <i class="fas fa-info-circle me-2"></i>
                                Backup akan dibuat dengan format: perpustakaan_backup_YYYY-MM-DD_HH-mm-ss.sql
                            </div>
                            <button type="submit" name="create_backup" class="btn btn-modern btn-success-modern" onclick="showLoading()">
                                <i class="fas fa-download me-2"></i>Download Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Database Tables Info -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-table me-2"></i>Informasi Tabel Database
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($tables)): ?>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Tabel</th>
                                        <th class="text-end">Jumlah Baris</th>
                                        <th class="text-end">Ukuran (MB)</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tables as $table): ?>
                                        <tr>
                                            <td>
                                                <code><?= htmlspecialchars($table['name']) ?></code>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge bg-primary"><?= number_format($table['rows']) ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge bg-info"><?= $table['size'] ?> MB</span>
                                            </td>
                                            <td class="small text-muted">
                                                <?php
                                                $descriptions = [
                                                    'anggota' => 'Data anggota perpustakaan',
                                                    'buku' => 'Koleksi buku perpustakaan',
                                                    'peminjaman' => 'Riwayat peminjaman buku',
                                                    'users' => 'Data pengguna sistem',
                                                    'pengaturan' => 'Konfigurasi sistem',
                                                    'log_aktivitas' => 'Log aktivitas pengguna'
                                                ];
                                                echo $descriptions[$table['name']] ?? 'Tabel sistem';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-footer bg-light">
                            <div class="row text-center small text-muted">
                                <div class="col-md-4">
                                    <strong><?= count($tables) ?></strong> Tabel
                                </div>
                                <div class="col-md-4">
                                    <strong><?= number_format(array_sum(array_column($tables, 'rows'))) ?></strong> Total Baris
                                </div>
                                <div class="col-md-4">
                                    <strong><?= number_format(array_sum(array_column($tables, 'size')), 2) ?></strong> MB Total
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Tidak ada tabel ditemukan</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Backup Guidelines -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Panduan Backup
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <h6 class="text-primary mb-2">Tipe Backup:</h6>
                        <ul class="text-muted mb-3">
                            <li class="mb-1"><strong>Lengkap:</strong> Untuk restore penuh sistem</li>
                            <li class="mb-1"><strong>Struktur:</strong> Untuk membuat database kosong</li>
                            <li class="mb-1"><strong>Data:</strong> Untuk migrasi data saja</li>
                        </ul>
                        
                        <h6 class="text-primary mb-2">Rekomendasi:</h6>
                        <ul class="text-muted mb-0">
                            <li class="mb-1">Backup harian untuk data penting</li>
                            <li class="mb-1">Backup mingguan untuk arsip</li>
                            <li class="mb-1">Simpan di multiple lokasi</li>
                            <li class="mb-1">Test restore secara berkala</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informasi Sistem
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $db_version = $conn->query("SELECT VERSION() as version")->fetch()['version'];
                        $db_size = $conn->query("
                            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS db_size 
                            FROM information_schema.tables 
                            WHERE table_schema = '" . DB_NAME . "'
                        ")->fetch()['db_size'];
                    } catch (Exception $e) {
                        $db_version = 'Unknown';
                        $db_size = 'Unknown';
                    }
                    ?>
                    
                    <div class="row small">
                        <div class="col-5"><strong>Database:</strong></div>
                        <div class="col-7"><?= DB_NAME ?></div>
                    </div>
                    <div class="row small">
                        <div class="col-5"><strong>MySQL Ver:</strong></div>
                        <div class="col-7"><?= explode('-', $db_version)[0] ?></div>
                    </div>
                    <div class="row small">
                        <div class="col-5"><strong>Size:</strong></div>
                        <div class="col-7"><?= $db_size ?> MB</div>
                    </div>
                    <div class="row small">
                        <div class="col-5"><strong>Server:</strong></div>
                        <div class="col-7"><?= DB_HOST ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Membuat Backup Database</h5>
                <p class="text-muted mb-0">Mohon tunggu, backup sedang diproses...</p>
                <div class="progress mt-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showLoading() {
    const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
    modal.show();
    
    setTimeout(() => {
        modal.hide();
    }, 3000);
}

// Form validation
document.getElementById('backupForm').addEventListener('submit', function(e) {
    const backupType = document.querySelector('input[name="backup_type"]:checked');
    
    if (!backupType) {
        e.preventDefault();
        alert('Silakan pilih tipe backup terlebih dahulu!');
        return;
    }
    
    const typeText = backupType.nextElementSibling.querySelector('strong').textContent;
    const confirm = window.confirm(`Yakin ingin membuat backup "${typeText}"?\n\nFile backup akan didownload ke komputer Anda.`);
    
    if (!confirm) {
        e.preventDefault();
        return;
    }
    
    showLoading();
});

// Add tooltip for radio buttons
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.form-check-label').forEach(label => {
            label.classList.remove('text-primary', 'fw-bold');
        });
        
        if (this.checked) {
            this.nextElementSibling.classList.add('text-primary', 'fw-bold');
        }
    });
});

// Initialize first option as selected
document.getElementById('structure_data').nextElementSibling.classList.add('text-primary', 'fw-bold');
</script>

<?php include '../../includes/footer.php'; ?>