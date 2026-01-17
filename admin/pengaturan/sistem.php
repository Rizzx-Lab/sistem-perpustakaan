<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireRole(['admin']);

$page_title = 'Pengaturan Sistem';
include '../../config/database.php';

// Handle form submission
if (isset($_POST['update_settings'])) {
    $errors = [];
    $success_count = 0;
    
    try {
        $conn->beginTransaction();
        
        $settings = [
            'max_pinjam_hari' => (int)$_POST['max_pinjam_hari'],
            'denda_per_hari' => (int)$_POST['denda_per_hari'],
            'max_buku_pinjam' => (int)$_POST['max_buku_pinjam'],
            'nama_perpustakaan' => sanitizeInput($_POST['nama_perpustakaan']),
            'alamat_perpustakaan' => sanitizeInput($_POST['alamat_perpustakaan']),
            'jam_buka' => sanitizeInput($_POST['jam_buka']),
            'jam_tutup' => sanitizeInput($_POST['jam_tutup']),
            'email_perpustakaan' => sanitizeInput($_POST['email_perpustakaan']),
            'telepon_perpustakaan' => sanitizeInput($_POST['telepon_perpustakaan'])
        ];
        
        // Validasi
        if ($settings['max_pinjam_hari'] < 1 || $settings['max_pinjam_hari'] > 365) {
            $errors[] = 'Maksimal hari peminjaman harus antara 1-365 hari';
        }
        
        if ($settings['denda_per_hari'] < 0) {
            $errors[] = 'Denda per hari tidak boleh negatif';
        }
        
        if ($settings['max_buku_pinjam'] < 1 || $settings['max_buku_pinjam'] > 10) {
            $errors[] = 'Maksimal buku yang dipinjam harus antara 1-10 buku';
        }
        
        if (empty($settings['nama_perpustakaan'])) {
            $errors[] = 'Nama perpustakaan harus diisi';
        }
        
        if (!empty($settings['email_perpustakaan']) && !filter_var($settings['email_perpustakaan'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email perpustakaan tidak valid';
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['jam_buka'])) {
            $errors[] = 'Format jam buka tidak valid (HH:MM)';
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['jam_tutup'])) {
            $errors[] = 'Format jam tutup tidak valid (HH:MM)';
        }
        
        if (empty($errors)) {
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO pengaturan (setting_key, setting_value, updated_at) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                
                if ($stmt->execute([$key, $value])) {
                    $success_count++;
                }
            }
            
            $conn->commit();
            logActivity('UPDATE_SETTINGS', "Pengaturan sistem diupdate ({$success_count} setting)");
            setFlashMessage('Pengaturan berhasil disimpan!', 'success');
            redirect($_SERVER['REQUEST_URI']);
        } else {
            $conn->rollback();
            setFlashMessage(implode('<br>', $errors), 'error');
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        setFlashMessage('Error database: ' . $e->getMessage(), 'error');
    }
}

// Get current settings
try {
    $stmt = $conn->query("SELECT setting_key, setting_value, description, updated_at FROM pengaturan ORDER BY setting_key");
    $current_settings = [];
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row;
    }
} catch (PDOException $e) {
    $error_message = "Error mengambil pengaturan: " . $e->getMessage();
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
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
}

.table-modern td {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
}

.table-modern tr:hover td {
    background-color: #f8f9fa;
}

.table-modern tr:last-child td {
    border-bottom: none;
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
    }
    
    .input-group .btn-outline-secondary {
        padding: 8px 12px;
    }
    
    .table-modern th,
    .table-modern td {
        padding: 8px 10px;
        font-size: 0.9rem;
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
                    <li class="breadcrumb-item active">Sistem</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">⚙️</span>
                <span class="title-gradient">Pengaturan Sistem</span>
            </h1>
        </div>
        <div>
            <a href="./" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST" id="settingsForm">
        <div class="row">
            <div class="col-lg-8">
                <!-- Loan Settings -->
                <div class="modern-card mb-4">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern mb-0">
                            <i class="fas fa-book-reader me-2"></i>Pengaturan Peminjaman
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label-modern" for="max_pinjam_hari">
                                    Maksimal Hari Peminjaman <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           name="max_pinjam_hari" 
                                           id="max_pinjam_hari"
                                           class="form-control-modern" 
                                           min="1" 
                                           max="365"
                                           value="<?= htmlspecialchars($current_settings['max_pinjam_hari']['setting_value'] ?? '14') ?>"
                                           required>
                                    <span class="input-group-text">hari</span>
                                </div>
                                <div class="form-text">Lama maksimal peminjaman buku (1-365 hari)</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern" for="max_buku_pinjam">
                                    Maksimal Buku per Anggota <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           name="max_buku_pinjam" 
                                           id="max_buku_pinjam"
                                           class="form-control-modern" 
                                           min="1" 
                                           max="10"
                                           value="<?= htmlspecialchars($current_settings['max_buku_pinjam']['setting_value'] ?? '3') ?>"
                                           required>
                                    <span class="input-group-text">buku</span>
                                </div>
                                <div class="form-text">Jumlah maksimal buku yang bisa dipinjam (1-10 buku)</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern" for="denda_per_hari">
                                    Denda per Hari <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" 
                                           name="denda_per_hari" 
                                           id="denda_per_hari"
                                           class="form-control-modern" 
                                           min="0"
                                           value="<?= htmlspecialchars($current_settings['denda_per_hari']['setting_value'] ?? '1000') ?>"
                                           required>
                                </div>
                                <div class="form-text">Denda keterlambatan per hari (Rupiah)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Library Info -->
                <div class="modern-card mb-4">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern mb-0">
                            <i class="fas fa-building me-2"></i>Informasi Perpustakaan
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label-modern" for="nama_perpustakaan">
                                Nama Perpustakaan <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="nama_perpustakaan" 
                                   id="nama_perpustakaan"
                                   class="form-control-modern" 
                                   placeholder="Nama perpustakaan"
                                   value="<?= htmlspecialchars($current_settings['nama_perpustakaan']['setting_value'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-modern" for="alamat_perpustakaan">
                                Alamat Perpustakaan
                            </label>
                            <textarea name="alamat_perpustakaan" 
                                      id="alamat_perpustakaan"
                                      class="form-control-modern" 
                                      rows="3"
                                      placeholder="Alamat lengkap perpustakaan"><?= htmlspecialchars($current_settings['alamat_perpustakaan']['setting_value'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="email_perpustakaan">
                                    Email Perpustakaan
                                </label>
                                <input type="email" 
                                       name="email_perpustakaan" 
                                       id="email_perpustakaan"
                                       class="form-control-modern" 
                                       placeholder="email@perpustakaan.com"
                                       value="<?= htmlspecialchars($current_settings['email_perpustakaan']['setting_value'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="telepon_perpustakaan">
                                    Telepon Perpustakaan
                                </label>
                                <input type="text" 
                                       name="telepon_perpustakaan" 
                                       id="telepon_perpustakaan"
                                       class="form-control-modern" 
                                       placeholder="031-1234567"
                                       value="<?= htmlspecialchars($current_settings['telepon_perpustakaan']['setting_value'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="jam_buka">
                                    Jam Buka <span class="text-danger">*</span>
                                </label>
                                <input type="time" 
                                       name="jam_buka" 
                                       id="jam_buka"
                                       class="form-control-modern" 
                                       value="<?= htmlspecialchars($current_settings['jam_buka']['setting_value'] ?? '08:00') ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="jam_tutup">
                                    Jam Tutup <span class="text-danger">*</span>
                                </label>
                                <input type="time" 
                                       name="jam_tutup" 
                                       id="jam_tutup"
                                       class="form-control-modern" 
                                       value="<?= htmlspecialchars($current_settings['jam_tutup']['setting_value'] ?? '16:00') ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="modern-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-modern btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <button type="submit" name="update_settings" class="btn btn-modern btn-success-modern">
                                <i class="fas fa-save me-2"></i>Simpan Pengaturan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Current Settings Info -->
                <div class="modern-card mb-4">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern mb-0">
                            <i class="fas fa-info-circle me-2"></i>Info Pengaturan
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($current_settings)): ?>
                            <div class="small text-muted mb-3">
                                <strong>Terakhir diupdate:</strong><br>
                                <?= isset($current_settings['max_pinjam_hari']['updated_at']) ? 
                                    date('d/m/Y H:i', strtotime($current_settings['max_pinjam_hari']['updated_at'])) : 
                                    'Belum pernah diupdate' ?>
                            </div>
                            
                            <h6 class="text-primary mb-2">Pengaturan Aktif:</h6>
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="fas fa-calendar-day text-warning me-2"></i>
                                    <strong>Maks. Hari:</strong> <?= $current_settings['max_pinjam_hari']['setting_value'] ?? '14' ?> hari
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-books text-info me-2"></i>
                                    <strong>Maks. Buku:</strong> <?= $current_settings['max_buku_pinjam']['setting_value'] ?? '3' ?> buku
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-money-bill text-danger me-2"></i>
                                    <strong>Denda:</strong> Rp <?= number_format($current_settings['denda_per_hari']['setting_value'] ?? 1000) ?>/hari
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-clock text-success me-2"></i>
                                    <strong>Jam Operasional:</strong><br>
                                    <?= $current_settings['jam_buka']['setting_value'] ?? '08:00' ?> - 
                                    <?= $current_settings['jam_tutup']['setting_value'] ?? '16:00' ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <p>Tidak ada pengaturan ditemukan</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="modern-card mb-4">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern mb-0">
                            <i class="fas fa-lightbulb me-2"></i>Panduan Pengaturan
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <h6 class="text-primary">Tips Pengaturan:</h6>
                            <ul class="text-muted">
                                <li class="mb-2">Maksimal hari peminjaman yang terlalu lama dapat mengurangi sirkulasi buku</li>
                                <li class="mb-2">Denda yang wajar membantu kedisiplinan pengembalian</li>
                                <li class="mb-2">Batasi jumlah buku per anggota sesuai kapasitas perpustakaan</li>
                                <li class="mb-2">Jam operasional harus realistis dan konsisten</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern mb-0">
                            <i class="fas fa-server me-2"></i>Status Sistem
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db_status = $conn->query("SELECT 1")->fetchColumn() ? 'Terhubung' : 'Error';
                            $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
                            $total_books = $conn->query("SELECT COUNT(*) FROM buku")->fetchColumn();
                            $total_members = $conn->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
                            $active_loans = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn();
                        } catch (Exception $e) {
                            $db_status = 'Error';
                            $total_users = $total_books = $total_members = $active_loans = 0;
                        }
                        ?>
                        
                        <div class="row g-3 text-center small">
                            <div class="col-6">
                                <div class="text-<?= $db_status === 'Terhubung' ? 'success' : 'danger' ?> fw-bold">
                                    <?= $db_status ?>
                                </div>
                                <div class="text-muted">Database</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold"><?= $total_users ?></div>
                                <div class="text-muted">Total Users</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold"><?= $total_books ?></div>
                                <div class="text-muted">Total Buku</div>
                            </div>
                            <div class="col-6">
                                <div class="fw-bold"><?= $total_members ?></div>
                                <div class="text-muted">Anggota</div>
                            </div>
                            <div class="col-12">
                                <div class="fw-bold text-warning"><?= $active_loans ?></div>
                                <div class="text-muted">Peminjaman Aktif</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Form validation
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate max loan days
    const maxDays = parseInt(document.getElementById('max_pinjam_hari').value);
    if (maxDays < 1 || maxDays > 365) {
        alert('Maksimal hari peminjaman harus antara 1-365 hari');
        isValid = false;
    }
    
    // Validate max books
    const maxBooks = parseInt(document.getElementById('max_buku_pinjam').value);
    if (maxBooks < 1 || maxBooks > 10) {
        alert('Maksimal buku yang dipinjam harus antara 1-10 buku');
        isValid = false;
    }
    
    // Validate fine
    const fine = parseInt(document.getElementById('denda_per_hari').value);
    if (fine < 0) {
        alert('Denda per hari tidak boleh negatif');
        isValid = false;
    }
    
    // Validate library name
    const libraryName = document.getElementById('nama_perpustakaan').value.trim();
    if (!libraryName) {
        alert('Nama perpustakaan harus diisi');
        isValid = false;
    }
    
    // Validate email format
    const email = document.getElementById('email_perpustakaan').value.trim();
    if (email && !isValidEmail(email)) {
        alert('Format email perpustakaan tidak valid');
        isValid = false;
    }
    
    // Validate time format
    const jamBuka = document.getElementById('jam_buka').value;
    const jamTutup = document.getElementById('jam_tutup').value;
    
    if (!jamBuka || !jamTutup) {
        alert('Jam buka dan tutup harus diisi');
        isValid = false;
    } else if (jamBuka >= jamTutup) {
        alert('Jam tutup harus lebih dari jam buka');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Reset form to original values
function resetForm() {
    if (confirm('Yakin ingin reset semua perubahan? Data yang belum disimpan akan hilang.')) {
        document.getElementById('settingsForm').reset();
    }
}

// Real-time validation feedback
document.getElementById('max_pinjam_hari').addEventListener('input', function() {
    const value = parseInt(this.value);
    const feedback = this.parentElement.nextElementSibling;
    
    if (value < 1 || value > 365) {
        this.style.borderColor = '#dc3545';
        feedback.style.color = '#dc3545';
        feedback.textContent = 'Harus antara 1-365 hari';
    } else {
        this.style.borderColor = '';
        feedback.style.color = '';
        feedback.textContent = 'Lama maksimal peminjaman buku (1-365 hari)';
    }
});

document.getElementById('max_buku_pinjam').addEventListener('input', function() {
    const value = parseInt(this.value);
    const feedback = this.parentElement.nextElementSibling;
    
    if (value < 1 || value > 10) {
        this.style.borderColor = '#dc3545';
        feedback.style.color = '#dc3545';
        feedback.textContent = 'Harus antara 1-10 buku';
    } else {
        this.style.borderColor = '';
        feedback.style.color = '';
        feedback.textContent = 'Jumlah maksimal buku yang bisa dipinjam (1-10 buku)';
    }
});

document.getElementById('denda_per_hari').addEventListener('input', function() {
    const value = parseInt(this.value);
    const feedback = this.parentElement.nextElementSibling;
    
    if (value < 0) {
        this.style.borderColor = '#dc3545';
        feedback.style.color = '#dc3545';
        feedback.textContent = 'Tidak boleh negatif';
    } else {
        this.style.borderColor = '';
        feedback.style.color = '';
        feedback.textContent = 'Denda keterlambatan per hari (Rupiah)';
    }
});

document.getElementById('email_perpustakaan').addEventListener('blur', function() {
    const email = this.value.trim();
    
    if (email && !isValidEmail(email)) {
        this.style.borderColor = '#dc3545';
    } else {
        this.style.borderColor = '';
    }
});

// Format phone number input
document.getElementById('telepon_perpustakaan').addEventListener('input', function() {
    this.value = this.value.replace(/[^\d\-\s]/g, '');
});

// Show confirmation before leaving if form is dirty
let formChanged = false;
document.querySelectorAll('#settingsForm input, #settingsForm textarea').forEach(field => {
    field.addEventListener('input', () => { formChanged = true; });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Clear form changed flag on successful submit
document.getElementById('settingsForm').addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php include '../../includes/footer.php'; ?>