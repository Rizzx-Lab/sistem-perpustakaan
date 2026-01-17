<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Edit Rak';
include '../../config/database.php';

// Get rak ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($id)) {
    setFlashMessage('ID rak tidak valid', 'error');
    redirect(SITE_URL . 'admin/rak/index.php');
}

// Get rak data
try {
    $stmt = $conn->prepare("SELECT * FROM rak WHERE id_rak = ?");
    $stmt->execute([$id]);
    $rak = $stmt->fetch();
    
    if (!$rak) {
        setFlashMessage('Rak tidak ditemukan', 'error');
        redirect(SITE_URL . 'admin/rak/index.php');
    }
    
    // Get total stok fisik di rak ini (BERUBAH: jumlah buku fisik)
    $stmt = $conn->prepare("SELECT SUM(stok_total) FROM buku WHERE id_rak = ?");
    $stmt->execute([$id]);
    $total_stok = $stmt->fetchColumn() ?: 0;
    
    // Get jumlah judul buku di rak (untuk informasi saja)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM buku WHERE id_rak = ?");
    $stmt->execute([$id]);
    $jumlah_judul = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'admin/rak/index.php');
}

// Handle form submission
if (isset($_POST['update_rak'])) {
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

    // Check kapasitas jika ada buku fisik
    if ($kapasitas !== null && $kapasitas < $total_stok) {
        $errors[] = "Kapasitas tidak boleh kurang dari total buku fisik yang ada ($total_stok buku)";
    }

    // Check if kode rak already exists (excluding current)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id_rak FROM rak WHERE kode_rak = ? AND id_rak != ?");
            $stmt->execute([$kode_rak, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Kode rak sudah digunakan oleh rak lain';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $sql = "UPDATE rak SET kode_rak = ?, lokasi = ?, kapasitas = ? 
                    WHERE id_rak = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$kode_rak, $lokasi, $kapasitas, $id]);
            
            if ($result) {
                logActivity('UPDATE_RAK', "Rak diupdate: {$kode_rak} (ID: {$id})", 'rak', $id);
                setFlashMessage('Rak berhasil diupdate!', 'success');
                redirect(SITE_URL . 'admin/rak/index.php');
            } else {
                throw new Exception('Gagal mengupdate data rak');
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
    background: linear-gradient(135deg, #667eea, #764ba2);
}

/* Perbaikan untuk progress bar */
.progress {
    border-radius: 6px;
    height: 10px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 6px;
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
    
    .text-center .h5 {
        font-size: 1.3rem;
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
                        <a href="../index.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php"><i class="fas fa-archive"></i> Rak</a>
                    </li>
                    <li class="breadcrumb-item active">Edit Rak</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-edit me-2 text-warning"></i>Edit Rak
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
                        <i class="fas fa-archive me-2"></i>Informasi Rak
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="rakForm" novalidate>
                        <!-- Kode Rak -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="kode_rak">
                                Kode Rak <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="kode_rak" 
                                   id="kode_rak"
                                   class="form-control-modern" 
                                   placeholder="Contoh: A1, B2, C3"
                                   value="<?= htmlspecialchars($rak['kode_rak']) ?>"
                                   maxlength="20"
                                   required>
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
                                      required><?= htmlspecialchars($rak['lokasi']) ?></textarea>
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
                                   min="<?= $total_stok ?>"
                                   value="<?= $rak['kapasitas'] ?>">
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Minimal: <?= $total_stok ?> 
                                (<?= $total_stok ?> buku fisik sedang disimpan di rak ini)
                                <br>
                                <small class="text-muted">
                                    <?= $jumlah_judul ?> judul buku
                                </small>
                            </div>
                            <div class="invalid-feedback">Kapasitas tidak valid</div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="update_rak" class="btn btn-warning text-white btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Update Rak
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Rak Statistics -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistik Rak
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle-delete mb-3">
                            <i class="fas fa-archive fa-2x"></i>
                        </div>
                        <h5><?= htmlspecialchars($rak['kode_rak']) ?></h5>
                        <small class="text-muted">ID: <code><?= $id ?></code></small>
                    </div>

                    <div class="row g-3 text-center mb-4">
                        <div class="col-6">
                            <div class="h5 mb-1 text-primary"><?= $jumlah_judul ?></div>
                            <small class="text-muted">Jumlah Judul</small>
                        </div>
                        <div class="col-6">
                            <div class="h5 mb-1 text-success"><?= $total_stok ?></div>
                            <small class="text-muted">Buku Fisik</small>
                        </div>
                    </div>

                    <?php if ($rak['kapasitas']): ?>
                        <hr class="my-3">
                        <div class="mb-4">
                            <small class="text-muted d-block mb-2">Kapasitas Terpakai</small>
                            <div class="progress">
                                <?php 
                                $persentase = $total_stok > 0 ? round(($total_stok / $rak['kapasitas']) * 100) : 0;
                                $progress_color = $persentase >= 90 ? 'danger' : ($persentase >= 70 ? 'warning' : 'success');
                                ?>
                                <div class="progress-bar bg-<?= $progress_color ?>" 
                                     role="progressbar" 
                                     style="width: <?= min($persentase, 100) ?>%"
                                     aria-valuenow="<?= $persentase ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small><?= $persentase ?>%</small>
                                <small><?= $total_stok ?> / <?= $rak['kapasitas'] ?> buku</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($total_stok > 0): ?>
                        <hr class="my-3">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Perhatian:</strong> 
                            Perubahan kode rak akan mempengaruhi <?= $jumlah_judul ?> judul buku 
                            (<?= $total_stok ?> buku fisik).
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
                            <td class="fw-semibold" style="width: 40%">Kapasitas:</td>
                            <td class="text-end"><?= $rak['kapasitas'] ? $rak['kapasitas'] . ' buku fisik' : '<em class="text-muted">Unlimited</em>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Jumlah Judul:</td>
                            <td class="text-end">
                                <span class="badge bg-<?= $jumlah_judul > 0 ? 'info' : 'secondary' ?>">
                                    <?= $jumlah_judul ?> judul
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Buku Fisik:</td>
                            <td class="text-end">
                                <span class="badge bg-secondary"><?= $total_stok ?> buku</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Ditambahkan:</td>
                            <td class="text-end"><?= formatTanggal($rak['created_at']) ?></td>
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
                            <i class="fas fa-list me-2"></i>Lihat Semua Rak
                        </a>
                            <?php if ($total_stok == 0): ?>
                        <!-- HAPUS POPUP KONFIRMASI DAN LANGSUNG REDIRECT -->
                            <a   a href="hapus.php?id=<?= $id ?>" 
                                class="btn btn-outline-danger btn-modern">
                                <i class="fas fa-trash me-2"></i>Hapus Rak
                            </a>
                         <?php else: ?>
                            <button class="btn btn-outline-secondary btn-modern" disabled>
                                <i class="fas fa-ban me-2"></i>Tidak Dapat Dihapus
                            </button>
                            <small class="text-muted text-center">Rak memiliki <?= $total_stok ?> buku fisik</small>
                        <?php endif; ?>
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
        kodeRak.nextElementSibling.textContent = 'Kode rak harus diisi';
        isValid = false;
    }
    
    if (!lokasi.value.trim()) {
        lokasi.classList.add('is-invalid');
        lokasi.nextElementSibling.textContent = 'Lokasi harus diisi';
        isValid = false;
    }
    
    // Validate kapasitas
    const kapasitas = document.getElementById('kapasitas');
    const minKapasitas = parseInt(kapasitas.getAttribute('min')) || 0;
    
    if (kapasitas.value && parseInt(kapasitas.value) < minKapasitas) {
        kapasitas.classList.add('is-invalid');
        kapasitas.nextElementSibling.textContent = `Kapasitas minimal ${minKapasitas} buku`;
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

// Real-time validation for kapasitas
document.getElementById('kapasitas').addEventListener('input', function() {
    const minKapasitas = parseInt(this.getAttribute('min')) || 0;
    if (this.value && this.value < minKapasitas) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Prevent negative values
document.getElementById('kapasitas').addEventListener('input', function() {
    if (this.value && this.value < 0) {
        this.value = 0;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>