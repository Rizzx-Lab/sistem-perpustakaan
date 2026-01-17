<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Tambah Rak';
include '../../config/database.php';

// Handle form submission
if (isset($_POST['tambah_rak'])) {
    $kode_rak = sanitizeInput($_POST['kode_rak']);
    $lokasi = sanitizeInput($_POST['lokasi']);
    $kapasitas = !empty($_POST['kapasitas']) ? (int)$_POST['kapasitas'] : null;

    $errors = [];

    // Validasi input
    if (empty($kode_rak)) {
        $errors[] = 'Kode rak harus diisi';
    }

    if (empty($lokasi)) {
        $errors[] = 'Lokasi harus diisi';
    }

    if ($kapasitas !== null && $kapasitas < 0) {
        $errors[] = 'Kapasitas tidak boleh negatif';
    }

    // Check if kode rak already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_rak FROM rak WHERE kode_rak = ?");
            $stmt->execute([$kode_rak]);
            if ($stmt->fetch()) {
                $errors[] = 'Kode rak sudah terdaftar di sistem';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO rak (kode_rak, lokasi, kapasitas, created_at) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$kode_rak, $lokasi, $kapasitas]);
            
            if ($result) {
                $last_id = $conn->lastInsertId();
                logActivity('TAMBAH_RAK', "Menambahkan rak: {$kode_rak} di {$lokasi}", 'rak', $last_id);
                setFlashMessage('Rak berhasil ditambahkan!', 'success');
                redirect(SITE_URL . 'admin/rak/index.php');
            } else {
                throw new Exception('Gagal menyimpan data rak');
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }

    // Store errors in session for display
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'error');
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

/* Perbaikan untuk list di guidelines */
ul.small {
    padding-left: 1.2rem;
    margin-bottom: 0;
}

ul.small li {
    margin-bottom: 4px;
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
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../index.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php"><i class="fas fa-archive"></i> Rak</a>
                    </li>
                    <li class="breadcrumb-item active">Tambah Rak</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-plus-circle me-2 text-success"></i>Tambah Rak Baru
            </h1>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Form -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-archive me-2"></i>Informasi Rak
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="rakForm" novalidate>
                        <!-- Kode Rak -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="kode_rak">
                                Kode Rak <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-2">Unik</span>
                            </label>
                            <input type="text" 
                                   name="kode_rak" 
                                   id="kode_rak"
                                   class="form-control-modern" 
                                   placeholder="Contoh: A1, B2, C3"
                                   value="<?= isset($_POST['kode_rak']) ? htmlspecialchars($_POST['kode_rak']) : '' ?>"
                                   maxlength="20"
                                   required>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Kode unik untuk identifikasi rak (contoh: A1, B2)
                            </div>
                            <div class="invalid-feedback">Kode rak harus diisi</div>
                        </div>

                        <!-- Lokasi -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="lokasi">
                                Lokasi / Deskripsi <span class="text-danger">*</span>
                            </label>
                            <textarea name="lokasi" 
                                      id="lokasi"
                                      class="form-control-modern" 
                                      rows="3"
                                      placeholder="Contoh: Rak 1 - Sudut Utara Timur, Lantai 1"
                                      required><?= isset($_POST['lokasi']) ? htmlspecialchars($_POST['lokasi']) : '' ?></textarea>
                            <div class="invalid-feedback">Lokasi harus diisi</div>
                        </div>

                        <!-- Kapasitas -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="kapasitas">
                                Kapasitas Maksimal <small class="text-muted">(opsional)</small>
                                <span class="badge bg-info ms-2">Buku Fisik</span>
                            </label>
                            <input type="number" 
                                   name="kapasitas" 
                                   id="kapasitas"
                                   class="form-control-modern" 
                                   placeholder="Contoh: 50"
                                   min="1"
                                   value="<?= isset($_POST['kapasitas']) ? $_POST['kapasitas'] : '' ?>">
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Jumlah maksimal buku FISIK yang bisa disimpan di rak ini.
                                Kosongkan untuk unlimited.
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="tambah_rak" class="btn btn-success-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Simpan Rak
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Guidelines Card -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Panduan Input
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <strong class="d-block mb-2">Informasi Wajib:</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Kode Rak:</strong> Harus unik dan mudah diingat</li>
                            <li><strong>Lokasi:</strong> Deskripsi jelas untuk memudahkan pencarian</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Kapasitas Rak (BARU):</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Berdasarkan BUKU FISIK</strong>, bukan judul</li>
                            <li>1 rak = 50 buku fisik</li>
                            <li>Jika ada 2 judul buku dengan total 10 eksemplar, 
                                maka kapasitas terpakai = 10 buku</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Contoh Kode Rak yang Baik:</strong>
                        <ul class="small text-muted mb-0">
                            <li>A1, A2, A3 (untuk rak kategori A)</li>
                            <li>B1-01, B1-02 (untuk rak bagian B, lantai 1)</li>
                            <li>KOM-001, KOM-002 (untuk rak komputer)</li>
                        </ul>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tips:</strong> 
                        <ul class="mt-2 mb-0">
                            <li>Gunakan sistem penomoran yang konsisten</li>
                            <li>Lokasi sejelas mungkin untuk petugas baru</li>
                            <li>Kapasitas membantu menghindari overload rak</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-bolt me-2"></i>Aksi Cepat
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-outline-primary btn-modern">
                            <i class="fas fa-list me-2"></i>Lihat Semua Rak
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-modern" onclick="resetForm()">
                            <i class="fas fa-redo me-2"></i>Reset Form
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Important Notes -->
            <div class="modern-card border-warning">
                <div class="card-header-modern bg-warning bg-opacity-10 border-warning">
                    <h6 class="card-title-modern mb-0 text-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Catatan Penting
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small mb-0">
                        <ul class="mb-0">
                            <li>Pastikan kode rak belum terdaftar sebelumnya</li>
                            <li>Kode rak tidak dapat diubah setelah ada buku</li>
                            <li>Rak dapat dihapus jika tidak memiliki buku</li>
                            <li>Kapasitas dapat diubah kapan saja</li>
                            <li><strong>Kapasitas dihitung berdasarkan buku fisik, bukan judul!</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('rakForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate required fields
    const kodeRak = document.getElementById('kode_rak');
    const lokasi = document.getElementById('lokasi');
    
    if (!kodeRak.value.trim()) {
        kodeRak.classList.add('is-invalid');
        kodeRak.nextElementSibling.nextElementSibling.textContent = 'Kode rak harus diisi';
        isValid = false;
    }
    
    if (!lokasi.value.trim()) {
        lokasi.classList.add('is-invalid');
        lokasi.nextElementSibling.textContent = 'Lokasi harus diisi';
        isValid = false;
    }
    
    // Validate kapasitas
    const kapasitas = document.getElementById('kapasitas');
    if (kapasitas.value && parseInt(kapasitas.value) < 1) {
        kapasitas.classList.add('is-invalid');
        kapasitas.nextElementSibling.textContent = 'Kapasitas minimal 1 buku';
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }
});

// Reset form function
function resetForm() {
    if (confirm('Yakin ingin reset semua input? Data yang sudah diisi akan hilang.')) {
        document.getElementById('rakForm').reset();
        
        // Clear validation
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Focus to first field
        document.getElementById('kode_rak').focus();
    }
}

// Set focus to kode rak field on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('kode_rak').focus();
});

// Prevent negative values in kapasitas
document.getElementById('kapasitas').addEventListener('input', function() {
    if (this.value && this.value < 1) {
        this.value = 1;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>