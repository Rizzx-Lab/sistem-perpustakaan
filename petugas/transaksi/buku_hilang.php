<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require petugas role
requireRole(['petugas', 'admin']);

$page_title = 'Lapor Buku Hilang/Rusak';
include '../../config/database.php';

// Get denda settings
$stmt = $conn->query("SELECT * FROM pengaturan WHERE setting_key IN ('denda_hilang', 'denda_rusak_parah', 'denda_per_hari')");
$denda_settings = [];
while ($row = $stmt->fetch()) {
    $denda_settings[$row['setting_key']] = $row['setting_value'];
}
$denda_hilang = (int)($denda_settings['denda_hilang'] ?? 50000);
$denda_rusak_parah = (int)($denda_settings['denda_rusak_parah'] ?? 25000);
$denda_per_hari = (int)($denda_settings['denda_per_hari'] ?? 1000);

// Process lost/damaged book report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lapor_buku'])) {
    $id_peminjaman = (int)$_POST['id_peminjaman'];
    $status = sanitizeInput($_POST['status']);
    $alasan = sanitizeInput($_POST['alasan']);
    
    try {
        $conn->beginTransaction();
        
        // Get peminjaman details
        $stmt = $conn->prepare("
            SELECT p.*, a.nama, b.judul, b.isbn, b.stok_total, b.stok_tersedia,
                   DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
            FROM peminjaman p
            JOIN anggota a ON p.nik = a.nik
            JOIN buku b ON p.isbn = b.isbn
            WHERE p.id_peminjaman = ? AND p.status = 'dipinjam'
        ");
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            throw new Exception('Peminjaman tidak ditemukan atau sudah dikembalikan');
        }
        
        // Calculate fines
        $denda_keterlambatan = max(0, $peminjaman['hari_terlambat']) * $denda_per_hari;
        $denda_hilang_total = 0;
        
        if ($status === 'hilang') {
            $denda_hilang_total = $denda_hilang;
        } elseif ($status === 'rusak_parah') {
            $denda_hilang_total = $denda_rusak_parah;
        }
        
        $total_denda = $denda_keterlambatan + $denda_hilang_total;
        
        // Insert to buku_hilang table
        $stmt = $conn->prepare("
            INSERT INTO buku_hilang 
            (id_peminjaman, status, denda_hilang, alasan, tanggal_laporan, created_at) 
            VALUES (?, ?, ?, ?, CURDATE(), NOW())
        ");
        $stmt->execute([
            $id_peminjaman,
            $status,
            $denda_hilang_total,
            $alasan
        ]);
        $id_hilang = $conn->lastInsertId();
        
        // Update peminjaman status
        $stmt = $conn->prepare("UPDATE peminjaman SET status = 'dikembalikan' WHERE id_peminjaman = ?");
        $stmt->execute([$id_peminjaman]);
        
        // Update book stock
        $new_stok_total = max(0, $peminjaman['stok_total'] - 1);
        $new_stok_tersedia = max(0, $peminjaman['stok_tersedia'] - 1);
        $stmt = $conn->prepare("
            UPDATE buku 
            SET stok_total = ?, 
                stok_tersedia = ?,
                status = CASE WHEN ? <= 0 THEN 'tidak tersedia' ELSE status END
            WHERE isbn = ?
        ");
        $stmt->execute([$new_stok_total, $new_stok_tersedia, $new_stok_total, $peminjaman['isbn']]);
        
        // Insert to pengembalian with fine
        $stmt = $conn->prepare("
            INSERT INTO pengembalian 
            (id_peminjaman, tanggal_pengembalian_aktual, kondisi_buku, denda, catatan, created_at) 
            VALUES (?, CURDATE(), ?, ?, ?, NOW())
        ");
        $kondisi = $status === 'rusak_parah' ? 'rusak_berat' : 'rusak_ringan';
        $catatan = "Buku $status - " . ($alasan ?: 'Tanpa keterangan');
        $stmt->execute([
            $id_peminjaman,
            $kondisi,
            $total_denda,
            $catatan
        ]);
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'LAPOR_BUKU_HILANG_PETUGAS',
            "Petugas melaporkan buku '{$peminjaman['judul']}' $status oleh {$peminjaman['nama']}. Denda: Rp " . number_format($total_denda, 0, ',', '.'),
            'buku_hilang',
            $id_hilang
        );
        
        setFlashMessage("Laporan buku $status berhasil disimpan! Total denda: Rp " . number_format($total_denda, 0, ',', '.') . " (Rp " . number_format($denda_keterlambatan, 0, ',', '.') . " keterlambatan + Rp " . number_format($denda_hilang_total, 0, ',', '.') . " $status)", 'success');
        redirect($_SERVER['REQUEST_URI']);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Search active borrowings
$search_nik = isset($_GET['nik']) ? sanitizeInput($_GET['nik']) : '';
$search_nama = isset($_GET['nama']) ? sanitizeInput($_GET['nama']) : '';
$search_isbn = isset($_GET['isbn']) ? sanitizeInput($_GET['isbn']) : '';

$where_conditions = ["p.status = 'dipinjam'"];
$params = [];

if (!empty($search_nik)) {
    $where_conditions[] = "p.nik LIKE ?";
    $params[] = "%$search_nik%";
}

if (!empty($search_nama)) {
    $where_conditions[] = "a.nama LIKE ?";
    $params[] = "%$search_nama%";
}

if (!empty($search_isbn)) {
    $where_conditions[] = "p.isbn LIKE ?";
    $params[] = "%$search_isbn%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get active borrowings
$stmt = $conn->prepare("
    SELECT p.*, a.nama, b.judul, b.isbn, b.stok_total,
           DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
           DATEDIFF(CURDATE(), p.tanggal_pinjam) as sudah_dipinjam
    FROM peminjaman p
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    $where_clause
    ORDER BY p.tanggal_kembali ASC
    LIMIT 50
");
$stmt->execute($params);
$peminjaman_aktif = $stmt->fetchAll();

include '../../includes/header.php';
?>

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
                        <a href="peminjaman.php">Transaksi</a>
                    </li>
                    <li class="breadcrumb-item active">Buku Hilang/Rusak</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">🚨</span>
                <span class="title-gradient">Lapor Buku Hilang/Rusak</span>
            </h1>
            <p class="text-muted mb-0">Laporkan buku yang hilang atau rusak parah</p>
        </div>
        <div>
            <button type="button" class="btn btn-modern btn-info" data-bs-toggle="modal" data-bs-target="#dendaModal">
                <i class="fas fa-info-circle me-2"></i>Info Denda
            </button>
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

    <!-- Search Form -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">NIK Anggota</label>
                    <input type="text" name="nik" class="form-control-modern" 
                           placeholder="Cari NIK..." 
                           value="<?= htmlspecialchars($search_nik) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Nama Anggota</label>
                    <input type="text" name="nama" class="form-control-modern" 
                           placeholder="Cari nama..." 
                           value="<?= htmlspecialchars($search_nama) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">ISBN Buku</label>
                    <input type="text" name="isbn" class="form-control-modern" 
                           placeholder="Cari ISBN..." 
                           value="<?= htmlspecialchars($search_isbn) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-modern flex-fill">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <a href="buku_hilang.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Borrowings Table -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>Peminjaman Aktif untuk Dilaporkan
                <small class="text-muted">(<?= count($peminjaman_aktif) ?> ditemukan)</small>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if ($peminjaman_aktif): ?>
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
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_aktif as $row): ?>
                                <?php
                                $is_overdue = $row['hari_terlambat'] > 0;
                                $denda_keterlambatan = $is_overdue ? $row['hari_terlambat'] * $denda_per_hari : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= $row['id_peminjaman'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                        <small class="text-muted">NIK: <?= htmlspecialchars($row['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($row['judul']) ?></div>
                                        <small class="text-muted">ISBN: <?= htmlspecialchars($row['isbn']) ?></small>
                                        <br>
                                        <small class="text-muted">Stok tersedia: <?= $row['stok_total'] ?> buku</small>
                                    </td>
                                    <td>
                                        <div><?= formatTanggal($row['tanggal_pinjam']) ?></div>
                                        <small class="text-muted"><?= $row['sudah_dipinjam'] ?> hari</small>
                                    </td>
                                    <td>
                                        <div class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatTanggal($row['tanggal_kembali']) ?>
                                        </div>
                                        <?php if ($is_overdue): ?>
                                            <small class="text-danger">+<?= $row['hari_terlambat'] ?> hari</small>
                                            <br>
                                            <small class="text-danger">Denda: Rp <?= number_format($denda_keterlambatan, 0, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-clock me-1"></i>Terlambat
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-book-reader me-1"></i>Dipinjam
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reportModal"
                                                data-peminjaman-id="<?= $row['id_peminjaman'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                                data-judul="<?= htmlspecialchars($row['judul']) ?>"
                                                data-isbn="<?= htmlspecialchars($row['isbn']) ?>"
                                                data-terlambat="<?= $row['hari_terlambat'] ?>"
                                                data-denda-keterlambatan="<?= $denda_keterlambatan ?>">
                                            <i class="fas fa-flag me-1"></i>Laporkan
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">Tidak ada peminjaman aktif</h6>
                    <?php if (!empty($search_nik) || !empty($search_nama) || !empty($search_isbn)): ?>
                        <p class="text-muted mb-3">Coba ubah kriteria pencarian</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Lapor Buku Hilang/Rusak
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="reportForm">
                <input type="hidden" name="id_peminjaman" id="input_peminjaman_id">
                
                <div class="modal-body">
                    <!-- Info Display -->
                    <div class="alert alert-info mb-3">
                        <h6><i class="fas fa-info-circle me-2"></i>Informasi Peminjaman</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Anggota:</strong> <span id="display_nama"></span><br>
                                <strong>Buku:</strong> <span id="display_judul"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>ISBN:</strong> <span id="display_isbn"></span><br>
                                <strong>Status:</strong> <span id="display_status"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Report Type -->
                    <div class="mb-3">
                        <label class="form-label-modern">Status Buku <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="status" 
                                           id="status_hilang" value="hilang" required>
                                    <label class="form-check-label" for="status_hilang">
                                        <i class="fas fa-question-circle text-danger me-1"></i>
                                        <strong>Hilang</strong>
                                        <br>
                                        <small class="text-muted">Buku tidak dapat ditemukan</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="status" 
                                           id="status_rusak" value="rusak_parah" required>
                                    <label class="form-check-label" for="status_rusak">
                                        <i class="fas fa-ban text-warning me-1"></i>
                                        <strong>Rusak Parah</strong>
                                        <br>
                                        <small class="text-muted">Buku rusak tidak dapat diperbaiki</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div class="mb-3">
                        <label class="form-label-modern">Alasan/Keterangan <span class="text-danger">*</span></label>
                        <textarea name="alasan" class="form-control-modern" rows="3" 
                                  placeholder="Jelaskan alasan buku hilang/rusak (wajib diisi)..." required></textarea>
                        <small class="text-muted">Contoh: Buku tidak ditemukan setelah pencarian, Buku terkena air hingga halaman sobek, dll.</small>
                    </div>
                    
                    <!-- Fine Summary -->
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-file-invoice-dollar me-2"></i>Ringkasan Denda</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td width="60%">Denda Keterlambatan:</td>
                                <td id="denda_keterlambatan" class="text-end">Rp 0</td>
                            </tr>
                            <tr>
                                <td>Denda Hilang:</td>
                                <td id="denda_hilang_display" class="text-end">Rp 0</td>
                            </tr>
                            <tr>
                                <td>Denda Rusak Parah:</td>
                                <td id="denda_rusak_display" class="text-end">Rp 0</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>TOTAL DENDA:</strong></td>
                                <td id="total_denda" class="text-end fw-bold text-danger">Rp 0</td>
                            </tr>
                        </table>
                        <small class="text-muted">*Stok total buku akan dikurangi 1 unit</small>
                    </div>
                    
                    <!-- Confirmation -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_report" required>
                        <label class="form-check-label" for="confirm_report">
                            Saya sebagai petugas menyatakan bahwa buku benar-benar <span id="status_label">hilang/rusak</span> dan siap mencatat denda
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" name="lapor_buku" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Simpan Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Denda Info Modal -->
<div class="modal fade" id="dendaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Informasi Denda
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Ketentuan Denda</h6>
                    <ul class="mb-0">
                        <li><strong>Denda Keterlambatan:</strong> Rp <?= number_format($denda_per_hari, 0, ',', '.') ?>/hari</li>
                        <li><strong>Buku Hilang:</strong> Rp <?= number_format($denda_hilang, 0, ',', '.') ?></li>
                        <li><strong>Buku Rusak Parah:</strong> Rp <?= number_format($denda_rusak_parah, 0, ',', '.') ?></li>
                        <li><strong>Total Denda = Denda Keterlambatan + Denda Hilang/Rusak</strong></li>
                    </ul>
                </div>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Konsekuensi</h6>
                    <ul class="mb-0 small">
                        <li>Stok total buku akan berkurang 1 unit</li>
                        <li>Anggota tidak dapat meminjam buku lain selama memiliki denda</li>
                        <li>Pastikan verifikasi dengan anggota sebelum melapor</li>
                        <li>Laporan tidak dapat dibatalkan setelah disimpan</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal setup
const reportModal = document.getElementById('reportModal');
if (reportModal) {
    reportModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        
        // Get data from button
        const peminjamanId = button.getAttribute('data-peminjaman-id');
        const nama = button.getAttribute('data-nama');
        const judul = button.getAttribute('data-judul');
        const isbn = button.getAttribute('data-isbn');
        const terlambat = parseInt(button.getAttribute('data-terlambat'));
        const dendaKeterlambatan = parseInt(button.getAttribute('data-denda-keterlambatan'));
        
        // Update modal content
        document.getElementById('input_peminjaman_id').value = peminjamanId;
        document.getElementById('display_nama').textContent = nama;
        document.getElementById('display_judul').textContent = judul;
        document.getElementById('display_isbn').textContent = isbn;
        
        // Update status display
        const statusDisplay = document.getElementById('display_status');
        if (terlambat > 0) {
            statusDisplay.innerHTML = `<span class="badge bg-danger">Terlambat ${terlambat} hari</span>`;
        } else {
            statusDisplay.innerHTML = `<span class="badge bg-success">Tepat waktu</span>`;
        }
        
        // Update late fine
        document.getElementById('denda_keterlambatan').textContent = 
            `Rp ${dendaKeterlambatan.toLocaleString('id-ID')}`;
        
        // Reset form
        document.getElementById('reportForm').reset();
        document.getElementById('status_label').textContent = 'hilang/rusak';
        updateFineCalculation(dendaKeterlambatan);
    });
}

// Update fine calculation when status changes
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const dendaKeterlambatan = parseInt(document.getElementById('denda_keterlambatan').textContent.replace(/[^0-9]/g, '') || 0);
        updateFineCalculation(dendaKeterlambatan);
    });
});

function updateFineCalculation(dendaKeterlambatan) {
    const status = document.querySelector('input[name="status"]:checked');
    
    // Get fine amounts from PHP variables
    const dendaHilang = <?= $denda_hilang ?>;
    const dendaRusak = <?= $denda_rusak_parah ?>;
    
    // Reset all displays
    document.getElementById('denda_hilang_display').textContent = 'Rp 0';
    document.getElementById('denda_rusak_display').textContent = 'Rp 0';
    document.getElementById('total_denda').textContent = 'Rp 0';
    
    if (!status) {
        return;
    }
    
    let dendaHilangTotal = 0;
    if (status.value === 'hilang') {
        dendaHilangTotal = dendaHilang;
        document.getElementById('status_label').textContent = 'hilang';
        document.getElementById('denda_hilang_display').textContent = 
            `Rp ${dendaHilangTotal.toLocaleString('id-ID')}`;
    } else if (status.value === 'rusak_parah') {
        dendaHilangTotal = dendaRusak;
        document.getElementById('status_label').textContent = 'rusak parah';
        document.getElementById('denda_rusak_display').textContent = 
            `Rp ${dendaHilangTotal.toLocaleString('id-ID')}`;
    }
    
    const totalDenda = dendaKeterlambatan + dendaHilangTotal;
    document.getElementById('total_denda').textContent = 
        `Rp ${totalDenda.toLocaleString('id-ID')}`;
}
</script>

<?php include '../../includes/footer.php'; ?>