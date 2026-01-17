<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Tambah Penerbit';
include '../../config/database.php';

// Handle form submission
if (isset($_POST['tambah_penerbit'])) {
    $nama_penerbit = sanitizeInput($_POST['nama_penerbit']);
    $alamat = sanitizeInput($_POST['alamat']);
    $telepon = sanitizeInput($_POST['telepon']);
    $email = sanitizeInput($_POST['email']);

    $errors = [];

    // Validasi input
    if (empty($nama_penerbit)) {
        $errors[] = 'Nama penerbit harus diisi';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    // Check if penerbit already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_penerbit FROM penerbit WHERE nama_penerbit = ?");
            $stmt->execute([$nama_penerbit]);
            if ($stmt->fetch()) {
                $errors[] = 'Nama penerbit sudah terdaftar di sistem';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO penerbit (nama_penerbit, alamat, telepon, email, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$nama_penerbit, $alamat, $telepon, $email]);
            
            if ($result) {
                $last_id = $conn->lastInsertId();
                logActivity('TAMBAH_PENERBIT', "Menambahkan penerbit: {$nama_penerbit}", 'penerbit', $last_id);
                setFlashMessage('Penerbit berhasil ditambahkan!', 'success');
                redirect(SITE_URL . 'admin/penerbit/index.php');
            } else {
                throw new Exception('Gagal menyimpan data penerbit');
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
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php"><i class="fas fa-building"></i> Penerbit</a>
                    </li>
                    <li class="breadcrumb-item active">Tambah Penerbit</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-plus-circle me-2 text-success"></i>Tambah Penerbit Baru
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
                        <i class="fas fa-building me-2"></i>Informasi Penerbit
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="penerbitForm" novalidate>
                        <!-- Nama Penerbit -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="nama_penerbit">
                                Nama Penerbit <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-2">Unik</span>
                            </label>
                            <input type="text" 
                                   name="nama_penerbit" 
                                   id="nama_penerbit"
                                   class="form-control-modern" 
                                   placeholder="Masukkan nama penerbit"
                                   value="<?= isset($_POST['nama_penerbit']) ? htmlspecialchars($_POST['nama_penerbit']) : '' ?>"
                                   required>
                            <div class="invalid-feedback">Nama penerbit harus diisi</div>
                        </div>

                        <!-- Alamat -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="alamat">
                                Alamat <small class="text-muted">(opsional)</small>
                            </label>
                            <textarea name="alamat" 
                                      id="alamat"
                                      class="form-control-modern" 
                                      rows="3"
                                      placeholder="Masukkan alamat penerbit"><?= isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : '' ?></textarea>
                        </div>

                        <!-- Telepon & Email -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="telepon">
                                    Telepon <small class="text-muted">(opsional)</small>
                                </label>
                                <input type="text" 
                                       name="telepon" 
                                       id="telepon"
                                       class="form-control-modern" 
                                       placeholder="Contoh: 021-1234567"
                                       value="<?= isset($_POST['telepon']) ? htmlspecialchars($_POST['telepon']) : '' ?>">
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Format: 021-1234567 atau 081234567890
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="email">
                                    Email <small class="text-muted">(opsional)</small>
                                </label>
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       class="form-control-modern" 
                                       placeholder="penerbit@example.com"
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                <div class="invalid-feedback">Format email tidak valid</div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="tambah_penerbit" class="btn btn-success-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Simpan Penerbit
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
                            <li><strong>Nama Penerbit:</strong> Harus unik dan tidak boleh kosong</li>
                            <li>Pastikan nama sesuai dengan yang tercantum di buku</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Informasi Tambahan:</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Alamat:</strong> Dapat digunakan untuk pengiriman</li>
                            <li><strong>Telepon:</strong> Format bebas (dengan atau tanpa kode area)</li>
                            <li><strong>Email:</strong> Harus valid jika diisi</li>
                        </ul>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tips:</strong> 
                        <ul class="mt-2 mb-0">
                            <li>Periksa kembali nama penerbit agar konsisten</li>
                            <li>Data kontak dapat diisi nanti jika tidak tersedia</li>
                            <li>Penerbit dapat digunakan oleh banyak buku</li>
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
                            <i class="fas fa-list me-2"></i>Lihat Semua Penerbit
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
                            <li>Pastikan nama penerbit belum terdaftar</li>
                            <li>Nama penerbit tidak dapat diubah setelah ada buku</li>
                            <li>Penerbit dapat dihapus jika tidak memiliki buku</li>
                            <li>Periksa semua data sebelum menyimpan</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('penerbitForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate required fields
    const namaPenerbit = document.getElementById('nama_penerbit');
    if (!namaPenerbit.value.trim()) {
        namaPenerbit.classList.add('is-invalid');
        namaPenerbit.nextElementSibling.textContent = 'Nama penerbit harus diisi';
        isValid = false;
    }
    
    // Validate email if filled
    const email = document.getElementById('email');
    if (email.value.trim() && !isValidEmail(email.value)) {
        email.classList.add('is-invalid');
        email.nextElementSibling.textContent = 'Format email tidak valid';
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

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Reset form function
function resetForm() {
    if (confirm('Yakin ingin reset semua input? Data yang sudah diisi akan hilang.')) {
        document.getElementById('penerbitForm').reset();
        
        // Clear validation
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Focus to first field
        document.getElementById('nama_penerbit').focus();
    }
}

// Set focus to nama penerbit field on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('nama_penerbit').focus();
});

// Real-time email validation
document.getElementById('email').addEventListener('blur', function() {
    if (this.value.trim() && !isValidEmail(this.value)) {
        this.classList.add('is-invalid');
        this.nextElementSibling.textContent = 'Format email tidak valid';
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>