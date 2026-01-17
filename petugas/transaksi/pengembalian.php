<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Pengembalian Buku';
include '../../config/database.php';

// Get denda per hari dari pengaturan
$stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_per_hari'");
$denda_per_hari = (int)$stmt->fetchColumn() ?: 1000;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $id_peminjaman = $_POST['id_peminjaman'];
        $tanggal_pengembalian = $_POST['tanggal_pengembalian'];
        $kondisi_buku = $_POST['kondisi_buku'] ?? 'baik';
        $catatan_manual = sanitizeInput($_POST['catatan'] ?? '');
        
        // Validasi tanggal
        if ($tanggal_pengembalian > date('Y-m-d')) {
            throw new Exception('Tanggal pengembalian tidak boleh di masa depan');
        }
        
        // Get peminjaman data dengan LOCK
        $stmt = $conn->prepare("
            SELECT p.*, b.judul, a.nama, b.isbn, b.stok_total, b.stok_tersedia
            FROM peminjaman p
            JOIN buku b ON p.isbn = b.isbn
            JOIN anggota a ON p.nik = a.nik
            WHERE p.id_peminjaman = ? AND p.status = 'dipinjam'
            FOR UPDATE
        ");
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            throw new Exception('Data peminjaman tidak ditemukan atau sudah dikembalikan');
        }
        
        // Check apakah sudah ada record pengembalian (proteksi double-submit)
        $stmt = $conn->prepare("SELECT id_pengembalian FROM pengembalian WHERE id_peminjaman = ?");
        $stmt->execute([$id_peminjaman]);
        if ($stmt->fetch()) {
            throw new Exception('Buku ini sudah pernah dikembalikan sebelumnya');
        }
        
        // Hitung denda
        $tanggal_kembali = $peminjaman['tanggal_kembali'];
        $denda = 0;
        $hari_terlambat = 0;
        $denda_tambahan = 0;
        
        if ($tanggal_pengembalian > $tanggal_kembali) {
            $date1 = new DateTime($tanggal_kembali);
            $date2 = new DateTime($tanggal_pengembalian);
            $hari_terlambat = $date2->diff($date1)->days;
            $denda = $hari_terlambat * $denda_per_hari;
        }
        
        // Denda tambahan untuk kondisi buku + Auto catatan
        $catatan_kondisi = '';
        if ($kondisi_buku === 'rusak_ringan') {
            $denda_tambahan = 5000;
            $catatan_kondisi = "Kondisi buku rusak ringan (denda " . formatRupiah(5000) . ")";
        } elseif ($kondisi_buku === 'rusak_berat') {
            $denda_tambahan = 25000;
            $catatan_kondisi = "Kondisi buku rusak berat (denda " . formatRupiah(25000) . ")";
        }
        
        // Gabungkan catatan auto + manual
        $catatan_final = trim($catatan_kondisi . ($catatan_manual ? "\n" . $catatan_manual : ''));
        
        $total_denda = $denda + $denda_tambahan;
        
        // Update peminjaman status
        $stmt = $conn->prepare("
            UPDATE peminjaman 
            SET status = 'dikembalikan'
            WHERE id_peminjaman = ?
        ");
        $stmt->execute([$id_peminjaman]);
        
        // INSERT ke tabel pengembalian
        $stmt = $conn->prepare("
            INSERT INTO pengembalian (id_peminjaman, tanggal_pengembalian_aktual, kondisi_buku, denda, catatan, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$id_peminjaman, $tanggal_pengembalian, $kondisi_buku, $total_denda, $catatan_final]);
        
        // Update stok buku - TAMBAH stok_tersedia
        $stmt = $conn->prepare("UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE isbn = ?");
        $stmt->execute([$peminjaman['isbn']]);
        
        // Update status buku jika stok_tersedia > 0
        $stmt = $conn->prepare("UPDATE buku SET status = 'tersedia' WHERE isbn = ? AND stok_tersedia > 0");
        $stmt->execute([$peminjaman['isbn']]);
        
        $conn->commit();
        
        // LOG ACTIVITY - PENGEMBALIAN
        $status_text = $hari_terlambat > 0 ? 
            " ({$hari_terlambat} hari terlambat - Denda: " . formatRupiah($denda) . ")" : 
            " (Tepat waktu)";
        
        if ($denda_tambahan > 0) {
            $status_text .= " + Denda kondisi: " . formatRupiah($denda_tambahan);
        }
        
        logActivity(
            'PENGEMBALIAN',
            "Pengembalian buku '{$peminjaman['judul']}' oleh {$peminjaman['nama']}{$status_text}. " .
            "Stok total: {$peminjaman['stok_total']}, stok tersedia setelah: " . ($peminjaman['stok_tersedia'] + 1),
            'peminjaman',
            $id_peminjaman
        );
        
        $success_message = 'Pengembalian berhasil dicatat';
        if ($total_denda > 0) {
            $success_message .= '. Total denda: ' . formatRupiah($total_denda);
        }
        setFlashMessage($success_message, 'success');
        redirect(SITE_URL . 'petugas/transaksi/pengembalian.php');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = 'Gagal memproses pengembalian: ' . $e->getMessage();
    }
}

// Get active peminjaman
try {
    $search = $_GET['search'] ?? '';
    $whereClause = "WHERE p.status = 'dipinjam'";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (a.nama LIKE ? OR a.nik LIKE ? OR b.judul LIKE ? OR b.isbn LIKE ?)";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    $query = "
        SELECT p.*, a.nama, a.nik, b.judul, b.pengarang, b.isbn,
               DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
               CASE 
                   WHEN CURDATE() > p.tanggal_kembali THEN (DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari})
                   ELSE 0 
               END as estimasi_denda
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        $whereClause
        ORDER BY p.tanggal_kembali ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $peminjaman_list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $peminjaman_list = [];
    $error_message = 'Error: ' . $e->getMessage();
}

// Get statistics
$stats = [
    'total_active' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn(),
    'overdue' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali < CURDATE()")->fetchColumn(),
    'due_soon' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetchColumn(),
    'total_returns_today' => $conn->query("SELECT COUNT(*) FROM pengembalian WHERE DATE(tanggal_pengembalian_aktual) = CURDATE()")->fetchColumn(),
];

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= SITE_URL ?>petugas/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Pengembalian</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">↩️</span>
                <span class="title-gradient">Pengembalian Buku</span>
            </h1>
            <p class="text-muted mb-0">Proses pengembalian buku perpustakaan</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?= $flash['type'] === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                <div><?= htmlspecialchars($flash['message']) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total_active'] ?></div>
                <small class="stat-label">Sedang Dipinjam</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
                <div class="stat-number text-danger"><?= $stats['overdue'] ?></div>
                <small class="stat-label">Terlambat</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['due_soon'] ?></div>
                <small class="stat-label">Jatuh Tempo Segera</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['total_returns_today'] ?></div>
                <small class="stat-label">Dikembalikan Hari Ini</small>
            </div>
        </div>
    </div>

    <!-- Quick Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="modern-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-calculator me-2"></i>Tarif Denda</h6>
                        <p class="mb-0 text-muted small">Per hari keterlambatan</p>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0"><?= formatRupiah($denda_per_hari) ?></div>
                        <small class="text-muted">/ hari</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control-modern" 
                           placeholder="Cari berdasarkan NIK, Nama Anggota, ISBN, atau Judul Buku..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-modern w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Peminjaman List -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Daftar Peminjaman Aktif
                <small class="text-muted">(<?= count($peminjaman_list) ?> buku)</small>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($peminjaman_list)): ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Anggota</th>
                                <th>Buku</th>
                                <th>Tgl Pinjam</th>
                                <th>Batas Kembali</th>
                                <th>Status</th>
                                <th>Est. Denda</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_list as $row): ?>
                                <tr>
                                    <td><?= $row['id_peminjaman'] ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['judul']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['pengarang']) ?></small>
                                    </td>
                                    <td><?= formatTanggal($row['tanggal_pinjam']) ?></td>
                                    <td>
                                        <div class="<?= $row['hari_terlambat'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatTanggal($row['tanggal_kembali']) ?>
                                        </div>
                                        <?php if ($row['hari_terlambat'] > 0): ?>
                                            <small class="text-danger">+<?= $row['hari_terlambat'] ?> hari terlambat</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['hari_terlambat'] > 0): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Terlambat
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Tepat Waktu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['estimasi_denda'] > 0): ?>
                                            <span class="text-danger fw-bold">
                                                <?= formatRupiah($row['estimasi_denda']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="prosesKembali(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars(addslashes($row['judul'])) ?>', '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars($row['nik']) ?>', '<?= htmlspecialchars($row['isbn']) ?>', '<?= $row['tanggal_pinjam'] ?>', '<?= $row['tanggal_kembali'] ?>', <?= $row['hari_terlambat'] ?>, <?= $row['estimasi_denda'] ?>)">
                                            <i class="fas fa-check me-1"></i>Kembalikan
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Tidak ada peminjaman aktif</p>
                    <?php if (!empty($search)): ?>
                        <a href="pengembalian.php" class="btn btn-primary-modern btn-sm">
                            <i class="fas fa-redo me-2"></i>Reset Pencarian
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Pengembalian -->
<div class="modal fade" id="modalKembali" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-undo me-2"></i>Konfirmasi Pengembalian
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKembalikan">
                <input type="hidden" name="id_peminjaman" id="modal_id_peminjaman">
                <div class="modal-body">
                    <div class="alert alert-info" id="return_info">
                        <div class="d-flex">
                            <i class="fas fa-info-circle me-3 mt-1 fs-4"></i>
                            <div>
                                <h6 class="mb-2">Informasi Peminjaman</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Anggota:</strong> <span id="modal_nama_anggota"></span></p>
                                        <p class="mb-1"><strong>NIK:</strong> <span id="modal_nik"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Buku:</strong> <span id="modal_judul_buku"></span></p>
                                        <p class="mb-1"><strong>ISBN:</strong> <span id="modal_isbn"></span></p>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Pinjam:</strong> <span id="modal_tgl_pinjam"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Batas Kembali:</strong> <span id="modal_batas_kembali"></span></p>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <p class="mb-0 text-danger" id="status_terlambat" style="display: none;">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Status:</strong> <span id="hari_terlambat_text"></span> hari terlambat
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">Tanggal Pengembalian <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_pengembalian" id="return_date" 
                                       class="form-control-modern" value="<?= date('Y-m-d') ?>" 
                                       max="<?= date('Y-m-d') ?>" required
                                       onchange="calculateFine()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">Kondisi Buku <span class="text-danger">*</span></label>
                                <select name="kondisi_buku" id="kondisi_buku" class="form-control-modern" required onchange="calculateFine()">
                                    <option value="baik">Baik (Tidak ada denda tambahan)</option>
                                    <option value="rusak_ringan">Rusak Ringan (Denda Rp 5.000)</option>
                                    <option value="rusak_berat">Rusak Berat (Denda Rp 25.000)</option>
                                </select>
                                <small class="text-muted">Catatan kondisi akan otomatis ditambahkan</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div id="fine_calculation" class="alert alert-warning" style="display: none;">
                                <h6 class="alert-heading mb-2"><i class="fas fa-calculator me-2"></i>Perhitungan Denda</h6>
                                <div id="fine_details"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" class="form-control-modern" rows="3" 
                                  placeholder="Tambahkan catatan tambahan tentang kondisi buku atau informasi lainnya..."></textarea>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Catatan kondisi buku akan otomatis ditambahkan. Anda bisa menambahkan informasi tambahan di sini.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-success-modern">
                        <i class="fas fa-check me-2"></i>Konfirmasi Pengembalian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables for fine calculation
let fineData = {
    dueDate: '',
    finePerDay: <?= $denda_per_hari ?>,
    estimatedFine: 0,
    hariTerlambat: 0
};

// Fungsi untuk membuka modal pengembalian
function prosesKembali(id, judul, nama, nik, isbn, tglPinjam, tglKembali, hariTerlambat, estimasiDenda) {
    // Set nilai form
    document.getElementById('modal_id_peminjaman').value = id;
    document.getElementById('modal_nama_anggota').textContent = nama;
    document.getElementById('modal_nik').textContent = nik;
    document.getElementById('modal_judul_buku').textContent = judul;
    document.getElementById('modal_isbn').textContent = isbn;
    document.getElementById('modal_tgl_pinjam').textContent = formatDate(tglPinjam);
    document.getElementById('modal_batas_kembali').textContent = formatDate(tglKembali);
    
    // Reset kondisi buku ke "baik"
    document.getElementById('kondisi_buku').value = 'baik';
    
    // Simpan data untuk perhitungan denda
    fineData.dueDate = tglKembali;
    fineData.estimatedFine = estimasiDenda;
    fineData.hariTerlambat = hariTerlambat;
    
    // Tampilkan status terlambat jika ada
    const statusTerlambat = document.getElementById('status_terlambat');
    const hariTerlambatText = document.getElementById('hari_terlambat_text');
    
    if (fineData.hariTerlambat > 0) {
        hariTerlambatText.textContent = fineData.hariTerlambat;
        statusTerlambat.style.display = 'block';
    } else {
        statusTerlambat.style.display = 'none';
    }
    
    // Hitung denda awal
    calculateFine();
    
    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('modalKembali'));
    modal.show();
}

// Fungsi untuk menghitung denda
function calculateFine() {
    const returnDate = document.getElementById('return_date').value;
    const condition = document.querySelector('select[name="kondisi_buku"]').value;
    
    if (!returnDate || !fineData.dueDate) return;
    
    const returnDateObj = new Date(returnDate);
    const dueDateObj = new Date(fineData.dueDate);
    
    // Hitung hari terlambat
    let lateDays = 0;
    if (returnDateObj > dueDateObj) {
        const diffTime = returnDateObj - dueDateObj;
        lateDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }
    
    // Hitung denda keterlambatan
    let lateFine = lateDays * fineData.finePerDay;
    
    // Hitung denda kondisi
    let conditionFine = 0;
    let conditionText = '';
    switch(condition) {
        case 'rusak_ringan':
            conditionFine = 5000;
            conditionText = 'Rusak Ringan (Rp 5.000)';
            break;
        case 'rusak_berat':
            conditionFine = 25000;
            conditionText = 'Rusak Berat (Rp 25.000)';
            break;
        default:
            conditionText = 'Baik (Tidak ada denda)';
    }
    
    const totalFine = lateFine + conditionFine;
    
    // Tampilkan perhitungan
    const fineDetails = document.getElementById('fine_details');
    const fineBox = document.getElementById('fine_calculation');
    
    if (totalFine > 0) {
        let html = '<div class="row">';
        
        if (lateFine > 0) {
            html += `
                <div class="col-md-6">
                    <p class="mb-1"><strong>Keterlambatan:</strong></p>
                    <p class="mb-1">${lateDays} hari × Rp ${fineData.finePerDay.toLocaleString('id-ID')}</p>
                    <p class="mb-1"><strong>Subtotal:</strong> Rp ${lateFine.toLocaleString('id-ID')}</p>
                </div>
            `;
        }
        
        if (conditionFine > 0) {
            html += `
                <div class="col-md-6">
                    <p class="mb-1"><strong>Kondisi Buku:</strong></p>
                    <p class="mb-1">${conditionText}</p>
                    <p class="mb-1"><strong>Subtotal:</strong> Rp ${conditionFine.toLocaleString('id-ID')}</p>
                </div>
            `;
        }
        
        html += `
            <div class="col-md-12 mt-3">
                <hr>
                <p class="mb-0">
                    <strong>Total Denda:</strong> 
                    <span class="fs-5 text-danger ms-2">Rp ${totalFine.toLocaleString('id-ID')}</span>
                </p>
            </div>
        </div>`;
        
        fineDetails.innerHTML = html;
        fineBox.style.display = 'block';
        fineBox.className = 'alert alert-warning';
    } else {
        fineDetails.innerHTML = '<p class="mb-0 text-success"><i class="fas fa-check-circle me-2"></i>Tidak ada denda yang perlu dibayar.</p>';
        fineBox.style.display = 'block';
        fineBox.className = 'alert alert-success';
    }
}

// Fungsi helper untuk format tanggal
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { day: '2-digit', month: 'long', year: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}

// Auto-hitung denda saat modal dibuka
document.getElementById('return_date')?.addEventListener('change', calculateFine);
document.querySelector('select[name="kondisi_buku"]')?.addEventListener('change', calculateFine);

// Set max date untuk input tanggal
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if (input.name !== 'tanggal_pinjam') {
            input.max = today;
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>