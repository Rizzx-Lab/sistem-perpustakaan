<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Edit Penerbit';
include '../../config/database.php';

// Get penerbit ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($id)) {
    setFlashMessage('ID penerbit tidak valid', 'error');
    redirect(SITE_URL . 'admin/penerbit/index.php');
}

// Get penerbit data
try {
    $stmt = $conn->prepare("SELECT * FROM penerbit WHERE id_penerbit = ?");
    $stmt->execute([$id]);
    $penerbit = $stmt->fetch();
    
    if (!$penerbit) {
        setFlashMessage('Penerbit tidak ditemukan', 'error');
        redirect(SITE_URL . 'admin/penerbit/index.php');
    }
    
    // Get jumlah buku dari penerbit ini
    $stmt = $conn->prepare("SELECT COUNT(*) FROM buku WHERE id_penerbit = ?");
    $stmt->execute([$id]);
    $jumlah_buku = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'admin/penerbit/index.php');
}

// Handle form submission
if (isset($_POST['update_penerbit'])) {
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

    // Check if penerbit name already exists (excluding current)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_penerbit FROM penerbit WHERE nama_penerbit = ? AND id_penerbit != ?");
            $stmt->execute([$nama_penerbit, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Nama penerbit sudah digunakan oleh penerbit lain';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $sql = "UPDATE penerbit SET nama_penerbit = ?, alamat = ?, telepon = ?, email = ? 
                    WHERE id_penerbit = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$nama_penerbit, $alamat, $telepon, $email, $id]);
            
            if ($result) {
                logActivity('UPDATE_PENERBIT', "Penerbit diupdate: {$nama_penerbit} (ID: {$id})", 'penerbit', $id);
                setFlashMessage('Penerbit berhasil diupdate!', 'success');
                redirect(SITE_URL . 'admin/penerbit/index.php');
            } else {
                throw new Exception('Gagal mengupdate data penerbit');
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
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.form-label-modern {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

/* Perbaikan untuk tabel di card */
.table-sm td, .table-sm th {
    padding: 8px 0;
    vertical-align: top;
}

.table-borderless td, .table-borderless th {
    border: none;
}

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk statistik */
.text-center .h5 {
    font-size: 1.5rem;
    font-weight: 600;
}

/* Perbaikan untuk avatar circle */
.avatar-circle-delete {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin: 0 auto;
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
    
    .avatar-circle-delete {
        width: 60px;
        height: 60px;
    }
    
    .avatar-circle-delete i {
        font-size: 1.5rem !important;
    }
}

/* Perbaikan untuk code tag */
code {
    padding: 2px 6px;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.9em;
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
                    <li class="breadcrumb-item active">Edit Penerbit</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-edit me-2 text-warning"></i>Edit Penerbit
            </h1>
            <p class="text-muted mb-0">ID: <code><?= $id ?></code></p>
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
                            </label>
                            <input type="text" 
                                   name="nama_penerbit" 
                                   id="nama_penerbit"
                                   class="form-control-modern" 
                                   placeholder="Masukkan nama penerbit"
                                   value="<?= htmlspecialchars($penerbit['nama_penerbit']) ?>"
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
                                      placeholder="Masukkan alamat penerbit"><?= htmlspecialchars($penerbit['alamat']) ?></textarea>
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
                                       value="<?= htmlspecialchars($penerbit['telepon']) ?>">
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
                                       value="<?= htmlspecialchars($penerbit['email']) ?>">
                                <div class="invalid-feedback">Format email tidak valid</div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="update_penerbit" class="btn btn-warning text-white btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Update Penerbit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Penerbit Statistics -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistik Penerbit
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle-delete mb-3" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="fas fa-building fa-2x"></i>
                        </div>
                        <h5><?= htmlspecialchars($penerbit['nama_penerbit']) ?></h5>
                        <small class="text-muted">ID: <code><?= $id ?></code></small>
                    </div>

                    <div class="row g-3 text-center mb-4">
                        <div class="col-12">
                            <div class="h5 mb-1 text-primary"><?= $jumlah_buku ?></div>
                            <small class="text-muted">Total Buku</small>
                        </div>
                    </div>

                    <?php if ($jumlah_buku > 0): ?>
                        <hr class="my-3">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Perhatian:</strong> 
                            Perubahan nama penerbit akan mempengaruhi <?= $jumlah_buku ?> buku.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current Data Info -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Data Saat Ini
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="fw-semibold" style="width: 40%">Alamat:</td>
                            <td class="text-end"><?= $penerbit['alamat'] ? htmlspecialchars($penerbit['alamat']) : '<em class="text-muted">Belum diisi</em>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Telepon:</td>
                            <td class="text-end"><?= $penerbit['telepon'] ? htmlspecialchars($penerbit['telepon']) : '<em class="text-muted">Belum diisi</em>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Email:</td>
                            <td class="text-end"><?= $penerbit['email'] ? htmlspecialchars($penerbit['email']) : '<em class="text-muted">Belum diisi</em>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Ditambahkan:</td>
                            <td class="text-end"><?= formatTanggal($penerbit['created_at']) ?></td>
                        </tr>
                    </table>
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
                       <?php if ($total_stok == 0): ?>
                         <!-- HAPUS POPUP KONFIRMASI DAN LANGSUNG REDIRECT -->
                            <a href="hapus.php?id=<?= $id ?>" 
                                class="btn btn-outline-danger btn-modern">
                             <i class="fas fa-trash me-2"></i>Hapus Rak
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-modern" disabled>
                                <i class="fas fa-ban me-2"></i>Tidak Dapat Dihapus
                            </button>
                            <small class="text-muted text-center">Penerbit memiliki <?= $jumlah_buku ?> buku</small>
                        <?php endif; ?>
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