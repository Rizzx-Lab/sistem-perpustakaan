<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Tambah Kategori';
include '../../config/database.php';

// Handle form submission
if (isset($_POST['tambah_kategori'])) {
    $nama_kategori = sanitizeInput($_POST['nama_kategori']);
    $deskripsi = sanitizeInput($_POST['deskripsi']);

    $errors = [];

    // Validasi input
    if (empty($nama_kategori)) {
        $errors[] = 'Nama kategori harus diisi';
    }

    // Check if kategori already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ?");
            $stmt->execute([$nama_kategori]);
            if ($stmt->fetch()) {
                $errors[] = 'Nama kategori sudah terdaftar di sistem';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO kategori (nama_kategori, deskripsi, created_at) 
                    VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$nama_kategori, $deskripsi]);
            
            if ($result) {
                $last_id = $conn->lastInsertId();
                logActivity('TAMBAH_KATEGORI', "Menambahkan kategori: {$nama_kategori}", 'kategori', $last_id);
                setFlashMessage('Kategori berhasil ditambahkan!', 'success');
                redirect(SITE_URL . 'admin/kategori/index.php');
            } else {
                throw new Exception('Gagal menyimpan data kategori');
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
                        <a href="index.php"><i class="fas fa-tags"></i> Kategori</a>
                    </li>
                    <li class="breadcrumb-item active">Tambah Kategori</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-plus-circle me-2 text-success"></i>Tambah Kategori Baru
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
                        <i class="fas fa-tag me-2"></i>Informasi Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="kategoriForm" novalidate>
                        <!-- Nama Kategori -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="nama_kategori">
                                Nama Kategori <span class="text-danger">*</span>
                                <span class="badge bg-secondary ms-2">Unik</span>
                            </label>
                            <input type="text" 
                                   name="nama_kategori" 
                                   id="nama_kategori"
                                   class="form-control-modern" 
                                   placeholder="Contoh: Programming & Algorithm, Data Science, Web Development"
                                   value="<?= isset($_POST['nama_kategori']) ? htmlspecialchars($_POST['nama_kategori']) : '' ?>"
                                   maxlength="50"
                                   required>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Nama kategori harus unik dan deskriptif (maksimal 50 karakter)
                            </div>
                            <div class="invalid-feedback">Nama kategori harus diisi</div>
                        </div>

                        <!-- Deskripsi -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="deskripsi">
                                Deskripsi <small class="text-muted">(opsional)</small>
                            </label>
                            <textarea name="deskripsi" 
                                      id="deskripsi"
                                      class="form-control-modern" 
                                      rows="4"
                                      placeholder="Deskripsi singkat tentang kategori ini..."><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Deskripsi akan membantu pengguna memahami kategori ini
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="tambah_kategori" class="btn btn-success-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Simpan Kategori
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
                            <li><strong>Nama Kategori:</strong> Harus unik dan jelas</li>
                            <li>Maksimal 50 karakter</li>
                            <li>Contoh: Programming, Data Science, Web Development</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Informasi Tambahan:</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Deskripsi:</strong> Menjelaskan ruang lingkup kategori</li>
                            <li>Bisa berisi contoh buku atau topik yang termasuk</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Contoh Kategori yang Baik:</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Programming & Algorithm:</strong> Buku tentang pemrograman dasar hingga lanjutan</li>
                            <li><strong>Data Science:</strong> Machine learning, analisis data, statistik</li>
                            <li><strong>Web Development:</strong> Framework, frontend, backend development</li>
                        </ul>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tips:</strong> 
                        <ul class="mt-2 mb-0">
                            <li>Buat kategori yang spesifik tapi tidak terlalu sempit</li>
                            <li>Pertimbangkan kebutuhan pengguna saat membuat kategori</li>
                            <li>Satu buku bisa memiliki lebih dari satu kategori</li>
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
                            <i class="fas fa-list me-2"></i>Lihat Semua Kategori
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
                            <li>Pastikan nama kategori belum terdaftar sebelumnya</li>
                            <li>Nama kategori tidak dapat diubah setelah ada buku</li>
                            <li>Kategori dapat dihapus jika tidak memiliki buku</li>
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
document.getElementById('kategoriForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate required fields
    const namaKategori = document.getElementById('nama_kategori');
    if (!namaKategori.value.trim()) {
        namaKategori.classList.add('is-invalid');
        namaKategori.nextElementSibling.nextElementSibling.textContent = 'Nama kategori harus diisi';
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
        document.getElementById('kategoriForm').reset();
        
        // Clear validation
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Focus to first field
        document.getElementById('nama_kategori').focus();
    }
}

// Set focus to nama kategori field on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('nama_kategori').focus();
});
</script>

<?php include '../../includes/footer.php'; ?>