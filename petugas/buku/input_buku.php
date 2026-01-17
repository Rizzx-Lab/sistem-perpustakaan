<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Input Buku Baru';
include '../../config/database.php';

// Get data penerbit, rak, dan kategori
$penerbit_list = $conn->query("SELECT id_penerbit, nama_penerbit FROM penerbit ORDER BY nama_penerbit")->fetchAll();
$rak_list = $conn->query("SELECT id_rak, kode_rak, lokasi FROM rak ORDER BY kode_rak")->fetchAll();
$kategori_list = $conn->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori")->fetchAll();

// Handle form submission
if (isset($_POST['tambah_buku'])) {
    $isbn = sanitizeInput($_POST['isbn']);
    $judul = sanitizeInput($_POST['judul']);
    $pengarang = sanitizeInput($_POST['pengarang']);
    $id_penerbit = !empty($_POST['id_penerbit']) ? (int)$_POST['id_penerbit'] : null;
    $id_rak = !empty($_POST['id_rak']) ? (int)$_POST['id_rak'] : null;
    $tahun = (int)$_POST['tahun'];
    $stok = (int)$_POST['stok'];
    $kategori_ids = isset($_POST['kategori']) ? $_POST['kategori'] : [];

    $errors = [];

    // Validasi input
    if (empty($isbn)) {
        $errors[] = 'ISBN harus diisi';
    } elseif (!preg_match('/^978-[0-9]{3}-[0-9]{3,4}-[0-9]{1,2}-[0-9]$/', $isbn)) {
        $errors[] = 'Format ISBN tidak valid (harus 13 digit dengan format: 978-xxx-xxx-xxx-x)';
    }

    if (empty($judul)) {
        $errors[] = 'Judul buku harus diisi';
    }

    if (empty($pengarang)) {
        $errors[] = 'Nama pengarang harus diisi';
    }

    if ($tahun < 1900 || $tahun > date('Y')) {
        $errors[] = 'Tahun terbit tidak valid (1900 - ' . date('Y') . ')';
    }

    if ($stok < 1) {
        $errors[] = 'Stok minimal 1 buku';
    }

    // Validasi kategori (minimal 1)
    if (empty($kategori_ids)) {
        $errors[] = 'Pilih minimal 1 kategori';
    }

    // Check if ISBN already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT isbn FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            if ($stmt->fetch()) {
                $errors[] = 'ISBN sudah terdaftar di sistem';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // INSERT buku dengan stok_total dan stok_tersedia
            $sql = "INSERT INTO buku (isbn, judul, pengarang, id_penerbit, id_rak, tahun_terbit, stok_total, stok_tersedia, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'tersedia', NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$isbn, $judul, $pengarang, $id_penerbit, $id_rak, $tahun, $stok, $stok]);
            
            // Insert kategori
            if (!empty($kategori_ids)) {
                $stmt_kategori = $conn->prepare("INSERT INTO buku_kategori (isbn, id_kategori) VALUES (?, ?)");
                foreach ($kategori_ids as $id_kategori) {
                    $stmt_kategori->execute([$isbn, $id_kategori]);
                }
            }
            
            $conn->commit();
            
            if ($result) {
                // LOG ACTIVITY
                $penerbit_name = '';
                if ($id_penerbit) {
                    $stmt_p = $conn->prepare("SELECT nama_penerbit FROM penerbit WHERE id_penerbit = ?");
                    $stmt_p->execute([$id_penerbit]);
                    $penerbit_name = $stmt_p->fetchColumn();
                }
                
                // Get kategori names
                $kategori_names = [];
                if (!empty($kategori_ids)) {
                    $placeholders = str_repeat('?,', count($kategori_ids) - 1) . '?';
                    $stmt_k = $conn->prepare("SELECT nama_kategori FROM kategori WHERE id_kategori IN ($placeholders)");
                    $stmt_k->execute($kategori_ids);
                    $kategori_names = $stmt_k->fetchAll(PDO::FETCH_COLUMN);
                }
                
                logActivity(
                    'TAMBAH_BUKU_PETUGAS', 
                    "Petugas menambahkan buku: {$judul} oleh {$pengarang}" . 
                    ($penerbit_name ? " (Penerbit: {$penerbit_name})" : "") . 
                    " - Kategori: " . implode(', ', $kategori_names) . 
                    " - Stok Total: {$stok}, Stok Tersedia: {$stok}",
                    'buku',
                    $isbn
                );
                
                setFlashMessage('Buku berhasil ditambahkan!', 'success');
                redirect(SITE_URL . 'petugas/buku/input_buku.php');
            } else {
                throw new Exception('Gagal menyimpan data buku');
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }

    // Store errors in session for display
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'error');
    }
}

// Get recent books
$recent_books = $conn->query("
    SELECT b.*, pn.nama_penerbit, r.kode_rak
    FROM buku b
    LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
    LEFT JOIN rak r ON b.id_rak = r.id_rak
    ORDER BY b.created_at DESC 
    LIMIT 5
")->fetchAll();

include '../../includes/header.php';
?>

<style>
/* MODERN CARD FIXES - PERBAIKAN UNTUK TULISAN MEPET DAN KEPOTONG */
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

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk tombol */
.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Perbaikan untuk checkbox kategori */
.form-check {
    margin-bottom: 8px;
    padding-left: 1.75rem;
}

.form-check-input {
    margin-left: -1.75rem;
    margin-top: 0.3rem;
}

.form-check-label {
    cursor: pointer;
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

.form-text a {
    text-decoration: none;
    color: #007bff;
}

/* Perbaikan untuk list di guidelines */
ul.small {
    padding-left: 1.2rem;
    margin-bottom: 0;
}

ul.small li {
    margin-bottom: 4px;
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

/* Perbaikan untuk list group */
.list-group-flush .list-group-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e0e0e0;
    border-left: none;
    border-right: none;
}

.list-group-flush .list-group-item:last-child {
    border-bottom: none;
}

/* Perbaikan untuk flex items spacing */
.d-flex.justify-content-between.align-items-start {
    margin-bottom: 8px;
}

/* Perbaikan untuk text-center spacing */
.text-center.py-4 {
    padding: 20px 15px;
}

/* Perbaikan untuk h6 spacing */
h6.mb-1 {
    margin-bottom: 8px !important;
    line-height: 1.3;
}

/* Perbaikan untuk flex items dalam list group */
.flex-grow-1 {
    min-width: 0;
}

/* Perbaikan untuk icon spacing dalam text muted */
.text-muted .fas {
    margin-right: 4px;
    width: 16px;
}

/* FIX untuk select dropdown arrow */
select.form-control-modern {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px 12px;
    padding-right: 40px;
}

/* Additional spacing fixes */
.mb-4 {
    margin-bottom: 1.5rem !important;
}

.pt-3 {
    padding-top: 1rem !important;
}

/* Card border warning */
.border-warning {
    border-color: #ffc107 !important;
}

.bg-warning.bg-opacity-10 {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

/* Kategori checkbox grid */
.row.g-2 {
    margin-top: 5px;
}

/* Select all categories button */
#selectAllCategories {
    padding: 5px 10px;
    font-size: 0.85rem;
    margin-bottom: 10px;
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= SITE_URL ?>petugas/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?= SITE_URL ?>petugas/buku/index.php"><i class="fas fa-book"></i> Buku</a>
                    </li>
                    <li class="breadcrumb-item active">Input Buku</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-plus-circle me-2 text-success"></i>Input Buku Baru
            </h1>
        </div>
        <div>
            <a href="<?= SITE_URL ?>petugas/buku/index.php" class="btn btn-secondary">
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
                        <i class="fas fa-book me-2"></i>Informasi Buku
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="bookForm" novalidate>
                        <!-- ISBN & Judul -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label class="form-label-modern" for="isbn">
                                    ISBN <span class="text-danger">*</span>
                                    <span class="badge bg-secondary ms-2">Unik</span>
                                </label>
                                <input type="text" 
                                       name="isbn" 
                                       id="isbn"
                                       class="form-control-modern" 
                                       placeholder="978-xxx-xxx-xxx-x"
                                       value="<?= isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : '' ?>"
                                       maxlength="17"
                                       required>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Format: 978-xxx-xxx-xxx-x (13 digit)
                                </div>
                                <div class="invalid-feedback">ISBN tidak valid</div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label-modern" for="judul">
                                    Judul Buku <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       name="judul" 
                                       id="judul"
                                       class="form-control-modern" 
                                       placeholder="Masukkan judul buku"
                                       value="<?= isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : '' ?>"
                                       required>
                                <div class="invalid-feedback">Judul buku harus diisi</div>
                            </div>
                        </div>

                        <!-- Pengarang -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="pengarang">
                                Pengarang <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="pengarang" 
                                   id="pengarang"
                                   class="form-control-modern" 
                                   placeholder="Nama pengarang"
                                   value="<?= isset($_POST['pengarang']) ? htmlspecialchars($_POST['pengarang']) : '' ?>"
                                   required>
                            <div class="invalid-feedback">Nama pengarang harus diisi</div>
                        </div>

                        <!-- Penerbit & Rak -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="id_penerbit">
                                    Penerbit <small class="text-muted">(opsional)</small>
                                </label>
                                <select name="id_penerbit" id="id_penerbit" class="form-control-modern">
                                    <option value="">-- Pilih Penerbit --</option>
                                    <?php foreach ($penerbit_list as $penerbit): ?>
                                        <option value="<?= $penerbit['id_penerbit'] ?>" 
                                                <?= (isset($_POST['id_penerbit']) && $_POST['id_penerbit'] == $penerbit['id_penerbit']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($penerbit['nama_penerbit']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- TOMBOL TAMBAH PENERBIT DIHAPUS -->
                                <div class="form-text mt-2">
                                    Pilih penerbit dari daftar yang tersedia
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="id_rak">
                                    Lokasi Rak <small class="text-muted">(opsional)</small>
                                </label>
                                <select name="id_rak" id="id_rak" class="form-control-modern">
                                    <option value="">-- Pilih Rak --</option>
                                    <?php foreach ($rak_list as $rak): ?>
                                        <option value="<?= $rak['id_rak'] ?>" 
                                                <?= (isset($_POST['id_rak']) && $_POST['id_rak'] == $rak['id_rak']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rak['kode_rak']) ?> - <?= htmlspecialchars($rak['lokasi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- TOMBOL TAMBAH RAK DIHAPUS -->
                                <div class="form-text mt-2">
                                    Pilih lokasi rak untuk buku
                                </div>
                            </div>
                        </div>

                        <!-- Tahun & Stok -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="tahun">
                                    Tahun Terbit <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       name="tahun" 
                                       id="tahun"
                                       class="form-control-modern" 
                                       placeholder="<?= date('Y') ?>"
                                       min="1900" 
                                       max="<?= date('Y') ?>"
                                       value="<?= isset($_POST['tahun']) ? $_POST['tahun'] : date('Y') ?>"
                                       required>
                                <div class="invalid-feedback">Tahun terbit harus valid (1900-<?= date('Y') ?>)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="stok">
                                    Jumlah Stok <span class="text-danger">*</span>
                                    <small class="text-muted d-block">(Stok awal)</small>
                                </label>
                                <input type="number" 
                                       name="stok" 
                                       id="stok"
                                       class="form-control-modern" 
                                       placeholder="0"
                                       min="1"
                                       value="<?= isset($_POST['stok']) ? $_POST['stok'] : '1' ?>"
                                       required>
                                <div class="invalid-feedback">Stok tidak valid</div>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Stok tersedia akan sama dengan stok total saat pertama kali
                                </div>
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="mb-4">
                            <label class="form-label-modern">
                                Kategori Buku <span class="text-danger">*</span>
                                <small class="text-muted">(pilih minimal 1)</small>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="selectAllCategories">
                                <i class="fas fa-check-square me-1"></i>Pilih Semua
                            </button>
                            <div class="row g-2 mb-3">
                                <?php foreach ($kategori_list as $kategori): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="kategori[]" 
                                                   value="<?= $kategori['id_kategori'] ?>" 
                                                   id="kategori_<?= $kategori['id_kategori'] ?>"
                                                   <?= (isset($_POST['kategori']) && in_array($kategori['id_kategori'], $_POST['kategori'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="kategori_<?= $kategori['id_kategori'] ?>">
                                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- TOMBOL TAMBAH KATEGORI DIHAPUS -->
                            <div class="form-text">
                                Pilih kategori dari daftar yang tersedia
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="<?= SITE_URL ?>petugas/buku/index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="tambah_buku" class="btn btn-success-modern btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Simpan Buku
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
                        <strong class="d-block mb-2">Format ISBN:</strong>
                        <ul class="small text-muted mb-0">
                            <li>Harus 13 digit</li>
                            <li>Dimulai dengan 978</li>
                            <li>Gunakan tanda strip (-) untuk pemisah</li>
                            <li>Contoh: 978-602-1234-56-7</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Data Tambahan:</strong>
                        <ul class="small text-muted mb-0">
                            <li><strong>Penerbit:</strong> Pilih dari daftar yang tersedia</li>
                            <li><strong>Rak:</strong> Tentukan lokasi fisik buku</li>
                            <li><strong>Kategori:</strong> Pilih minimal 1 kategori</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong class="d-block mb-2">Tips Pengisian:</strong>
                        <ul class="small text-muted mb-0">
                            <li>Periksa kembali ISBN di cover buku</li>
                            <li>Pastikan nama pengarang lengkap</li>
                            <li>Tahun terbit sesuai dengan yang tercantum</li>
                            <li>Stok awal sesuai jumlah buku fisik</li>
                        </ul>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Sistem Stok Baru:</strong> 
                        <ul class="mt-2 mb-0">
                            <li><strong>Stok Total:</strong> Jumlah buku fisik di perpustakaan</li>
                            <li><strong>Stok Tersedia:</strong> Buku yang siap dipinjam</li>
                            <li>Saat tambah buku, kedua stok akan sama</li>
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
                        <a href="<?= SITE_URL ?>petugas/buku/index.php" class="btn btn-outline-primary btn-modern">
                            <i class="fas fa-list me-2"></i>Lihat Semua Buku
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-modern" onclick="resetForm()">
                            <i class="fas fa-redo me-2"></i>Reset Form
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Buku Terakhir Ditambahkan -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-clock me-2"></i>Buku Terakhir Ditambahkan
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_books)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_books as $book): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($book['judul']) ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($book['pengarang']) ?> 
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-building me-1"></i>
                                                <?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                ISBN: <?= htmlspecialchars($book['isbn']) ?>
                                            </small>
                                            <?php if (!empty($book['kode_rak'])): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-th me-1"></i>
                                                    Rak: <?= htmlspecialchars($book['kode_rak']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-primary"><?= $book['stok_total'] ?> stok</span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($book['created_at'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">Belum ada buku yang ditambahkan</p>
                        </div>
                    <?php endif; ?>
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
                            <li>Pastikan ISBN belum terdaftar sebelumnya</li>
                            <li>ISBN tidak dapat diubah setelah disimpan</li>
                            <li>Periksa semua data sebelum menyimpan</li>
                            <li>Stok minimal 1 untuk buku baru</li>
                            <li>Pilih minimal 1 kategori buku</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ISBN auto-formatting
document.getElementById('isbn').addEventListener('input', function() {
    let value = this.value.replace(/[^\d]/g, '');
    let formatted = '';
    
    if (value.length >= 3) {
        formatted += value.substring(0, 3) + '-';
        if (value.length >= 6) {
            formatted += value.substring(3, 6) + '-';
            if (value.length >= 10) {
                formatted += value.substring(6, 10) + '-';
                if (value.length >= 12) {
                    formatted += value.substring(10, 12) + '-';
                    if (value.length >= 13) {
                        formatted += value.substring(12, 13);
                    }
                } else if (value.length > 10) {
                    formatted += value.substring(10);
                }
            } else if (value.length > 6) {
                formatted += value.substring(6);
            }
        } else if (value.length > 3) {
            formatted += value.substring(3);
        }
    } else {
        formatted = value;
    }
    
    this.value = formatted.substring(0, 17);
});

// Select/Deselect all categories
document.getElementById('selectAllCategories').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('input[name="kategori[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
    
    this.innerHTML = allChecked ? 
        '<i class="fas fa-check-square me-1"></i>Pilih Semua' : 
        '<i class="fas fa-square me-1"></i>Hapus Semua';
});

// Form validation
document.getElementById('bookForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate ISBN
    const isbn = document.getElementById('isbn').value.replace(/[^\d]/g, '');
    const isbnField = document.getElementById('isbn');
    
    if (!isbn || isbn.length !== 13 || !isbn.startsWith('978')) {
        isbnField.classList.add('is-invalid');
        isbnField.nextElementSibling.nextElementSibling.textContent = 'ISBN harus 13 digit dan dimulai dengan 978';
        isValid = false;
    }
    
    // Validate required fields
    const requiredFields = ['judul', 'pengarang', 'tahun', 'stok'];
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            field.nextElementSibling.textContent = 'Field ini harus diisi';
            isValid = false;
        }
    });
    
    // Validate year
    const tahun = parseInt(document.getElementById('tahun').value);
    const currentYear = new Date().getFullYear();
    const tahunField = document.getElementById('tahun');
    
    if (tahun < 1900 || tahun > currentYear) {
        tahunField.classList.add('is-invalid');
        tahunField.nextElementSibling.textContent = `Tahun harus antara 1900 - ${currentYear}`;
        isValid = false;
    }
    
    // Validate stock
    const stok = parseInt(document.getElementById('stok').value);
    const stokField = document.getElementById('stok');
    
    if (stok < 1) {
        stokField.classList.add('is-invalid');
        stokField.nextElementSibling.textContent = 'Stok minimal 1 buku';
        isValid = false;
    }
    
    // Validate at least one category is selected
    const categoryCheckboxes = form.querySelectorAll('input[name="kategori[]"]:checked');
    if (categoryCheckboxes.length === 0) {
        alert('Pilih minimal 1 kategori buku');
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
        document.getElementById('bookForm').reset();
        document.getElementById('tahun').value = new Date().getFullYear();
        document.getElementById('stok').value = '1';
        
        // Uncheck all checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        
        // Clear validation
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Reset "Select All" button text
        document.getElementById('selectAllCategories').innerHTML = '<i class="fas fa-check-square me-1"></i>Pilih Semua';
        
        // Focus to first field
        document.getElementById('isbn').focus();
    }
}

// Set focus to ISBN field on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('isbn').focus();
});

// Real-time validation feedback
document.getElementById('isbn').addEventListener('blur', function() {
    const isbn = this.value.replace(/[^\d]/g, '');
    if (isbn && (isbn.length !== 13 || !isbn.startsWith('978'))) {
        this.classList.add('is-invalid');
        this.nextElementSibling.nextElementSibling.textContent = 'Format ISBN tidak valid';
    } else {
        this.classList.remove('is-invalid');
    }
});

// Prevent negative values in number inputs
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        if (this.value < 1) this.value = 1;
    });
});

// Real-time validation for categories
document.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'kategori[]') {
        const categoryCheckboxes = document.querySelectorAll('input[name="kategori[]"]:checked');
        if (categoryCheckboxes.length === 0) {
            // You can show a warning here if needed
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>