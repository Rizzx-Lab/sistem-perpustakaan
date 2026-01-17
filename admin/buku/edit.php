<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Edit Buku';
include '../../config/database.php';

// Get data penerbit, rak, dan kategori
$penerbit_list = getPenerbitList();
$rak_list = getRakList();
$kategori_list = getKategoriList();

// Get book ISBN from URL
$isbn = isset($_GET['isbn']) ? sanitizeInput($_GET['isbn']) : '';

if (empty($isbn)) {
    setFlashMessage('ISBN tidak valid', 'error');
    redirect(SITE_URL . 'admin/buku/index.php');
}

// Get book data dengan JOIN
try {
    $stmt = $conn->prepare("
        SELECT b.*, p.nama_penerbit, r.kode_rak, r.lokasi 
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON b.id_rak = r.id_rak
        WHERE b.isbn = ?
    ");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch();
    
    if (!$book) {
        setFlashMessage('Buku tidak ditemukan', 'error');
        redirect(SITE_URL . 'admin/buku/index.php');
    }
    
    // Get kategori yang sudah dipilih
    $stmt = $conn->prepare("SELECT id_kategori FROM buku_kategori WHERE isbn = ?");
    $stmt->execute([$isbn]);
    $selected_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'admin/buku/index.php');
}

// Fungsi validasi kapasitas rak
function validateBookCapacity($id_rak, $current_stok_total, $new_stok_total, $book_isbn) {
    global $conn;
    
    if (!$id_rak) return ['valid' => true, 'message' => ''];
    
    try {
        // Get rak capacity
        $stmt = $conn->prepare("SELECT kapasitas FROM rak WHERE id_rak = ?");
        $stmt->execute([$id_rak]);
        $rak = $stmt->fetch();
        
        if (!$rak || !$rak['kapasitas']) {
            return ['valid' => true, 'message' => 'Rak tanpa kapasitas'];
        }
        
        // Get total stok di rak (excluding current book)
        $stmt = $conn->prepare("
            SELECT SUM(stok_total) 
            FROM buku 
            WHERE id_rak = ? AND isbn != ?
        ");
        $stmt->execute([$id_rak, $book_isbn]);
        $other_books_stok = $stmt->fetchColumn() ?: 0;
        
        // Calculate available space
        $current_space_used = $other_books_stok + $current_stok_total;
        $new_space_needed = $other_books_stok + $new_stok_total;
        
        if ($new_space_needed > $rak['kapasitas']) {
            $available = $rak['kapasitas'] - $other_books_stok;
            return [
                'valid' => false,
                'message' => "Rak hanya dapat menampung {$available} buku lagi. Anda mencoba menambahkan {$new_stok_total} buku."
            ];
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (Exception $e) {
        return ['valid' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Fungsi get rak capacity info untuk dropdown
function getRakCapacityInfo($id_rak) {
    global $conn;
    try {
        // Get rak capacity
        $stmt = $conn->prepare("SELECT kapasitas FROM rak WHERE id_rak = ?");
        $stmt->execute([$id_rak]);
        $rak = $stmt->fetch();
        
        if (!$rak || !$rak['kapasitas']) {
            return ['max' => null, 'used' => 0, 'free' => null];
        }
        
        // Get total stok di rak
        $stmt = $conn->prepare("SELECT SUM(stok_total) FROM buku WHERE id_rak = ?");
        $stmt->execute([$id_rak]);
        $used = $stmt->fetchColumn() ?: 0;
        
        return [
            'max' => $rak['kapasitas'],
            'used' => $used,
            'free' => $rak['kapasitas'] - $used
        ];
    } catch (Exception $e) {
        return ['max' => null, 'used' => 0, 'free' => null];
    }
}

// Handle form submission
if (isset($_POST['update_buku'])) {
    $judul = sanitizeInput($_POST['judul']);
    $pengarang = sanitizeInput($_POST['pengarang']);
    $id_penerbit = !empty($_POST['id_penerbit']) ? (int)$_POST['id_penerbit'] : null;
    $id_rak = !empty($_POST['id_rak']) ? (int)$_POST['id_rak'] : null;
    $tahun = (int)$_POST['tahun'];
    $stok_total = (int)$_POST['stok_total'];
    $stok_tersedia = (int)$_POST['stok_tersedia'];
    $status = sanitizeInput($_POST['status']);
    $kategori_ids = isset($_POST['kategori']) ? $_POST['kategori'] : [];

    $errors = [];

    // Validasi input
    if (empty($judul)) {
        $errors[] = 'Judul buku harus diisi';
    }

    if (empty($pengarang)) {
        $errors[] = 'Nama pengarang harus diisi';
    }

    if ($tahun < 1900 || $tahun > date('Y')) {
        $errors[] = 'Tahun terbit tidak valid (1900 - ' . date('Y') . ')';
    }

    if ($stok_total < 0) {
        $errors[] = 'Stok total tidak boleh negatif';
    }

    if ($stok_tersedia < 0) {
        $errors[] = 'Stok tersedia tidak boleh negatif';
    }

    if ($stok_tersedia > $stok_total) {
        $errors[] = 'Stok tersedia tidak boleh melebihi stok total';
    }

    $valid_status = ['tersedia', 'tidak tersedia'];
    if (!in_array($status, $valid_status)) {
        $errors[] = 'Status tidak valid';
    }

    // Check if there are enough books for current loans
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE isbn = ? AND status = 'dipinjam'");
            $stmt->execute([$isbn]);
            $current_loans = $stmt->fetchColumn();
            
            if ($stok_total < $current_loans) {
                $errors[] = "Stok total tidak boleh kurang dari jumlah buku yang sedang dipinjam ($current_loans buku)";
            }
            
            if ($stok_tersedia < 0) {
                $errors[] = "Stok tersedia tidak boleh negatif";
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi: ' . $e->getMessage();
        }
    }
    
    // Validasi kapasitas rak jika diubah
    if (empty($errors) && $id_rak) {
        $capacity_check = validateBookCapacity($id_rak, $book['stok_total'], $stok_total, $isbn);
        if (!$capacity_check['valid']) {
            $errors[] = $capacity_check['message'];
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Update buku
            $sql = "UPDATE buku SET judul = ?, pengarang = ?, id_penerbit = ?, id_rak = ?, 
                    tahun_terbit = ?, stok_total = ?, stok_tersedia = ?, status = ? 
                    WHERE isbn = ?";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$judul, $pengarang, $id_penerbit, $id_rak, $tahun, $stok_total, $stok_tersedia, $status, $isbn]);
            
            // Update kategori - delete old, insert new
            $stmt = $conn->prepare("DELETE FROM buku_kategori WHERE isbn = ?");
            $stmt->execute([$isbn]);
            
            if (!empty($kategori_ids)) {
                $stmt_kategori = $conn->prepare("INSERT INTO buku_kategori (isbn, id_kategori) VALUES (?, ?)");
                foreach ($kategori_ids as $id_kategori) {
                    $stmt_kategori->execute([$isbn, $id_kategori]);
                }
            }
            
            $conn->commit();
            
            if ($result) {
                logActivity('UPDATE_BUKU', "Buku diupdate: $judul (ISBN: $isbn) - Stok Total: $stok_total, Stok Tersedia: $stok_tersedia", 'buku', $isbn);
                setFlashMessage('Buku berhasil diupdate!', 'success');
                redirect(SITE_URL . 'admin/buku/index.php');
            } else {
                throw new Exception('Gagal mengupdate data buku');
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

// Get borrowing statistics for this book
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_peminjaman,
            SUM(CASE WHEN status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam,
            SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as sudah_dikembalikan
        FROM peminjaman 
        WHERE isbn = ?
    ");
    $stmt->execute([$isbn]);
    $book_stats = $stmt->fetch();
} catch (PDOException $e) {
    $book_stats = ['total_peminjaman' => 0, 'sedang_dipinjam' => 0, 'sudah_dikembalikan' => 0];
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

/* Perbaikan untuk checkbox kategori */
.form-check {
    margin-bottom: 8px;
    padding-left: 1.75rem;
}

.form-check-input {
    margin-left: -1.75rem;
    margin-top: 0.3rem;
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
}

/* Styling untuk option dengan informasi kapasitas */
.option-with-capacity {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}

.capacity-info {
    font-size: 0.85rem;
    color: #666;
    margin-left: 10px;
}

.capacity-good { color: #28a745; }
.capacity-warning { color: #ffc107; }
.capacity-danger { color: #dc3545; }
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
                        <a href="index.php"><i class="fas fa-book"></i> Buku</a>
                    </li>
                    <li class="breadcrumb-item active">Edit Buku</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-edit me-2 text-warning"></i>Edit Buku
            </h1>
            <p class="text-muted mb-0">ISBN: <code><?= htmlspecialchars($isbn) ?></code></p>
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
                        <i class="fas fa-book me-2"></i>Informasi Buku
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="bookForm" novalidate>
                        <!-- ISBN (readonly) -->
                        <div class="mb-4">
                            <label class="form-label-modern">
                                ISBN <span class="badge bg-secondary ms-2">Tidak dapat diubah</span>
                            </label>
                            <input type="text" 
                                   class="form-control-modern bg-light" 
                                   value="<?= htmlspecialchars($book['isbn']) ?>"
                                   readonly>
                            <div class="form-text mt-2">ISBN tidak dapat diubah setelah buku disimpan</div>
                        </div>

                        <!-- Judul -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="judul">
                                Judul Buku <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="judul" 
                                   id="judul"
                                   class="form-control-modern" 
                                   placeholder="Masukkan judul buku"
                                   value="<?= htmlspecialchars($book['judul']) ?>"
                                   required>
                            <div class="invalid-feedback">Judul buku harus diisi</div>
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
                                   value="<?= htmlspecialchars($book['pengarang']) ?>"
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
                                                <?= ($book['id_penerbit'] == $penerbit['id_penerbit']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($penerbit['nama_penerbit']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="id_rak">
                                    Lokasi Rak <small class="text-muted">(opsional)</small>
                                    <span class="badge bg-info ms-2">Kapasitas Buku Fisik</span>
                                </label>
                                <select name="id_rak" id="id_rak" class="form-control-modern">
                                    <option value="">-- Pilih Rak --</option>
                                    <?php foreach ($rak_list as $rak): 
                                        $capacity_info = getRakCapacityInfo($rak['id_rak']);
                                        $capacity_text = '';
                                        $capacity_class = '';
                                        
                                        if ($capacity_info['max'] !== null) {
                                            // Adjust capacity for current book if it's in this rak
                                            $adjusted_free = $capacity_info['free'];
                                            if ($book['id_rak'] == $rak['id_rak']) {
                                                $adjusted_free += $book['stok_total'];
                                            }
                                            
                                            if ($adjusted_free <= 0) {
                                                $capacity_text = ' (PENUH)';
                                                $capacity_class = 'capacity-danger';
                                            } elseif ($adjusted_free < 10) {
                                                $capacity_text = " (Sisa: {$adjusted_free} buku - Hampir Penuh)";
                                                $capacity_class = 'capacity-warning';
                                            } else {
                                                $capacity_text = " (Sisa: {$adjusted_free} buku)";
                                                $capacity_class = 'capacity-good';
                                            }
                                        } else {
                                            $capacity_text = ' (Kapasitas Unlimited)';
                                            $capacity_class = 'capacity-good';
                                        }
                                    ?>
                                        <option value="<?= $rak['id_rak'] ?>" 
                                                <?= ($book['id_rak'] == $rak['id_rak']) ? 'selected' : '' ?>
                                                data-capacity-free="<?= $capacity_info['free'] ?? 0 ?>"
                                                data-capacity-max="<?= $capacity_info['max'] ?? 0 ?>"
                                                data-current-book="<?= ($book['id_rak'] == $rak['id_rak']) ? 'true' : 'false' ?>">
                                            <?= htmlspecialchars($rak['kode_rak']) ?> - <?= htmlspecialchars($rak['lokasi']) ?>
                                            <span class="<?= $capacity_class ?>"><?= $capacity_text ?></span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="capacityWarning" class="alert alert-warning mt-2 d-none">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <span id="capacityMessage"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Tahun & Stok & Status -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label-modern" for="tahun">
                                    Tahun Terbit <span class="text-danger">*</span>
                                </label>
                                <input type="number" 
                                       name="tahun" 
                                       id="tahun"
                                       class="form-control-modern" 
                                       min="1900" 
                                       max="<?= date('Y') ?>"
                                       value="<?= $book['tahun_terbit'] ?>"
                                       required>
                                <div class="invalid-feedback">Tahun terbit harus valid (1900-<?= date('Y') ?>)</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern" for="stok_total">
                                    Stok Total <span class="text-danger">*</span>
                                    <small class="text-muted d-block">(Buku fisik)</small>
                                </label>
                                <input type="number" 
                                       name="stok_total" 
                                       id="stok_total"
                                       class="form-control-modern" 
                                       min="<?= $book_stats['sedang_dipinjam'] ?>"
                                       value="<?= $book['stok_total'] ?>"
                                       required>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Minimal: <?= $book_stats['sedang_dipinjam'] ?> 
                                    (<?= $book_stats['sedang_dipinjam'] ?> buku sedang dipinjam)
                                </div>
                                <div class="invalid-feedback">Stok total tidak valid</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern" for="stok_tersedia">
                                    Stok Tersedia <span class="text-danger">*</span>
                                    <small class="text-muted d-block">(buku yang siap dipinjam)</small>
                                </label>
                                <input type="number" 
                                       name="stok_tersedia" 
                                       id="stok_tersedia"
                                       class="form-control-modern" 
                                       min="0"
                                       max="<?= $book['stok_total'] ?>"
                                       value="<?= $book['stok_tersedia'] ?>"
                                       required>
                                <div class="invalid-feedback">Stok tersedia tidak valid</div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="status">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="status" class="form-control-modern" required>
                                <option value="tersedia" <?= $book['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                <option value="tidak tersedia" <?= $book['status'] === 'tidak tersedia' ? 'selected' : '' ?>>Tidak Tersedia</option>
                            </select>
                            <div class="invalid-feedback">Pilih status buku</div>
                        </div>

                        <!-- Kategori -->
                        <div class="mb-4">
                            <label class="form-label-modern">
                                Kategori Buku <small class="text-muted">(pilih minimal 1)</small>
                            </label>
                            <div class="row g-2">
                                <?php foreach ($kategori_list as $kategori): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="kategori[]" 
                                                   value="<?= $kategori['id_kategori'] ?>" 
                                                   id="kategori_<?= $kategori['id_kategori'] ?>"
                                                   <?= in_array($kategori['id_kategori'], $selected_categories) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="kategori_<?= $kategori['id_kategori'] ?>">
                                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="index.php" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="update_buku" class="btn btn-warning text-white btn-modern" id="submitBtn">
                                <i class="fas fa-save me-2"></i>Update Buku
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Book Statistics -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Statistik Buku
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="h5 mb-1 text-primary"><?= $book_stats['total_peminjaman'] ?></div>
                            <small class="text-muted">Total Pinjam</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-1 text-warning"><?= $book_stats['sedang_dipinjam'] ?></div>
                            <small class="text-muted">Sedang Dipinjam</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-1 text-success"><?= $book_stats['sudah_dikembalikan'] ?></div>
                            <small class="text-muted">Dikembalikan</small>
                        </div>
                    </div>

                    <?php if ($book_stats['sedang_dipinjam'] > 0): ?>
                        <hr class="my-3">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Perhatian:</strong> 
                            <ul class="mt-2 mb-0">
                                <li>Stok total minimal <?= $book_stats['sedang_dipinjam'] ?> (ada buku yang sedang dipinjam)</li>
                                <li>Stok tersedia maksimal <?= $book['stok_total'] - $book_stats['sedang_dipinjam'] ?></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Rak Capacity Info -->
                    <?php if ($book['id_rak']): 
                        $capacity_info = getRakCapacityInfo($book['id_rak']);
                        if ($capacity_info['max'] !== null): ?>
                        <hr class="my-3">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-archive me-2"></i>
                            <strong>Info Kapasitas Rak:</strong> 
                            <ul class="mt-2 mb-0">
                                <li>Kapasitas maksimal: <?= $capacity_info['max'] ?> buku</li>
                                <li>Terpakai: <?= $capacity_info['used'] ?> buku</li>
                                <li>Sisa: <?= $capacity_info['free'] ?> buku</li>
                                <?php if ($capacity_info['free'] < $book['stok_total']): ?>
                                    <li class="text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Perubahan stok total mungkin melebihi kapasitas rak
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; endif; ?>
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
                            <td class="fw-semibold" style="width: 40%">Penerbit:</td>
                            <td class="text-end"><?= $book['nama_penerbit'] ? htmlspecialchars($book['nama_penerbit']) : '<em class="text-muted">Belum ditentukan</em>' ?></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Rak:</td>
                            <td class="text-end">
                                <?= $book['kode_rak'] ? htmlspecialchars($book['kode_rak']) . ' - ' . htmlspecialchars($book['lokasi']) : '<em class="text-muted">Belum ditentukan</em>' ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Kategori:</td>
                            <td class="text-end">
                                <?php 
                                $kategori_names = [];
                                foreach ($kategori_list as $k) {
                                    if (in_array($k['id_kategori'], $selected_categories)) {
                                        $kategori_names[] = $k['nama_kategori'];
                                    }
                                }
                                echo !empty($kategori_names) ? '<span class="badge bg-light text-dark">' . implode('</span> <span class="badge bg-light text-dark">', $kategori_names) . '</span>' : '<em class="text-muted">Belum ditentukan</em>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Stok Total:</td>
                            <td class="text-end">
                                <span class="badge bg-secondary"><?= $book['stok_total'] ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Stok Tersedia:</td>
                            <td class="text-end">
                                <span class="badge bg-<?= $book['stok_tersedia'] > 0 ? 'success' : 'warning' ?>">
                                    <?= $book['stok_tersedia'] ?>
                                </span>
                            </td>
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
                            <i class="fas fa-list me-2"></i>Lihat Semua Buku
                        </a>
                        <a href="tambah.php" class="btn btn-outline-success btn-modern">
                            <i class="fas fa-plus me-2"></i>Tambah Buku Baru
                        </a>
                       <?php if ($book_stats['sedang_dipinjam'] == 0): ?>
                         <!-- HAPUS POPUP KONFIRMASI DAN LANGSUNG REDIRECT -->
                             <a href="hapus.php?isbn=<?= urlencode($isbn) ?>" 
                                class="btn btn-outline-danger btn-modern">
                                <i class="fas fa-trash me-2"></i>Hapus Buku
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-modern" disabled>
                                <i class="fas fa-ban me-2"></i>Tidak Dapat Dihapus
                            </button>
                            <small class="text-muted text-center">Buku sedang dipinjam oleh <?= $book_stats['sedang_dipinjam'] ?> anggota</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validasi kapasitas rak secara real-time
function validateRakCapacity() {
    const rakSelect = document.getElementById('id_rak');
    const selectedOption = rakSelect.options[rakSelect.selectedIndex];
    const stokInput = document.getElementById('stok_total');
    const capacityWarning = document.getElementById('capacityWarning');
    const capacityMessage = document.getElementById('capacityMessage');
    
    if (rakSelect.value && selectedOption.dataset.capacityFree !== undefined) {
        const capacityFree = parseInt(selectedOption.dataset.capacityFree);
        const isCurrentBook = selectedOption.dataset.currentBook === 'true';
        const stok = parseInt(stokInput.value) || 0;
        
        // Adjust capacity if this is the current book's rak
        let adjustedCapacity = capacityFree;
        if (isCurrentBook) {
            // Add current book's stock back to available capacity
            const currentStok = <?= $book['stok_total'] ?>;
            adjustedCapacity += currentStok;
        }
        
        // Rak dengan unlimited capacity
        if (selectedOption.dataset.capacityMax === '0') {
            capacityWarning.classList.add('d-none');
            return true;
        }
        
        // Rak dengan kapasitas terbatas
        if (stok > adjustedCapacity) {
            capacityWarning.classList.remove('d-none');
            capacityMessage.textContent = `Rak ini hanya memiliki ${adjustedCapacity} slot kosong. Anda mencoba menambahkan ${stok} buku.`;
            return false;
        } else {
            capacityWarning.classList.add('d-none');
            return true;
        }
    }
    
    capacityWarning.classList.add('d-none');
    return true;
}

// Form validation
document.getElementById('bookForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate required fields
    const requiredFields = ['judul', 'pengarang', 'tahun', 'stok_total', 'stok_tersedia', 'status'];
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
    
    // Validate stock total
    const stokTotal = parseInt(document.getElementById('stok_total').value);
    const minStok = parseInt(document.getElementById('stok_total').getAttribute('min')) || 0;
    const stokTotalField = document.getElementById('stok_total');
    
    if (stokTotal < minStok) {
        stokTotalField.classList.add('is-invalid');
        stokTotalField.nextElementSibling.nextElementSibling.textContent = `Stok total minimal ${minStok}`;
        isValid = false;
    }
    
    // Validate stock available
    const stokTersedia = parseInt(document.getElementById('stok_tersedia').value);
    const maxStokTersedia = parseInt(document.getElementById('stok_tersedia').getAttribute('max'));
    const stokTersediaField = document.getElementById('stok_tersedia');
    
    if (stokTersedia < 0) {
        stokTersediaField.classList.add('is-invalid');
        stokTersediaField.nextElementSibling.textContent = 'Stok tersedia tidak boleh negatif';
        isValid = false;
    }
    
    if (stokTersedia > stokTotal) {
        stokTersediaField.classList.add('is-invalid');
        stokTersediaField.nextElementSibling.textContent = 'Stok tersedia tidak boleh melebihi stok total';
        isValid = false;
    }
    
    // Validate rak capacity
    if (!validateRakCapacity()) {
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
        } else if (!capacityWarning.classList.contains('d-none')) {
            capacityWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// Event listeners untuk validasi kapasitas
document.getElementById('id_rak').addEventListener('change', validateRakCapacity);
document.getElementById('stok_total').addEventListener('input', validateRakCapacity);

// Real-time validation
document.getElementById('stok_total').addEventListener('input', function() {
    const minStok = parseInt(this.getAttribute('min'));
    const currentStok = parseInt(this.value);
    const stokTersediaField = document.getElementById('stok_tersedia');
    
    // Update max for stok_tersedia
    stokTersediaField.setAttribute('max', currentStok);
    
    if (currentStok < minStok) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
    
    // Validate stok_tersedia against new max
    const currentTersedia = parseInt(stokTersediaField.value);
    if (currentTersedia > currentStok) {
        stokTersediaField.classList.add('is-invalid');
    } else {
        stokTersediaField.classList.remove('is-invalid');
    }
    
    // Validate capacity
    validateRakCapacity();
});

document.getElementById('stok_tersedia').addEventListener('input', function() {
    const maxStok = parseInt(this.getAttribute('max'));
    const currentStok = parseInt(this.value);
    const stokTotal = parseInt(document.getElementById('stok_total').value);
    
    if (currentStok < 0) {
        this.classList.add('is-invalid');
    } else if (currentStok > stokTotal) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Prevent negative values in number inputs
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        const min = parseInt(this.getAttribute('min')) || 0;
        if (this.value < min) this.value = min;
    });
});

// Auto-calculate stok_tersedia based on stok_total
document.getElementById('stok_total').addEventListener('change', function() {
    const stokTotal = parseInt(this.value);
    const stokTersedia = parseInt(document.getElementById('stok_tersedia').value);
    const minStok = parseInt(this.getAttribute('min')) || 0;
    
    if (stokTersedia > stokTotal) {
        document.getElementById('stok_tersedia').value = Math.max(stokTotal - minStok, 0);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>