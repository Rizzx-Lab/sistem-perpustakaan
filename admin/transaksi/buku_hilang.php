<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Lapor Buku Hilang/Rusak';
include '../../config/database.php';

// Get denda settings
$stmt = $conn->query("SELECT * FROM pengaturan WHERE setting_key IN ('denda_hilang', 'denda_rusak_parah')");
$denda_settings = [];
while ($row = $stmt->fetch()) {
    $denda_settings[$row['setting_key']] = $row['setting_value'];
}
$denda_hilang = (int)($denda_settings['denda_hilang'] ?? 50000);
$denda_rusak_parah = (int)($denda_settings['denda_rusak_parah'] ?? 25000);

// Process lost/damaged book report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lapor_buku'])) {
    $id_peminjaman = (int)$_POST['id_peminjaman'];
    $status = sanitizeInput($_POST['status']);
    $alasan = sanitizeInput($_POST['alasan']);
    
    try {
        $conn->beginTransaction();
        
        // Get peminjaman details
        $stmt = $conn->prepare("
            SELECT p.*, a.nama, b.judul, b.isbn, b.stok_total
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
        
        // Calculate denda
        $denda_hilang_total = 0;
        if ($status === 'hilang') {
            $denda_hilang_total = $denda_hilang;
        } elseif ($status === 'rusak_parah') {
            $denda_hilang_total = $denda_rusak_parah;
        }
        
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
        
        // Update book stock (reduce total stock by 1)
        $new_stok_total = max(0, $peminjaman['stok_total'] - 1);
        $stmt = $conn->prepare("
            UPDATE buku 
            SET stok_total = ?, 
                stok_tersedia = GREATEST(0, stok_tersedia - 1),
                status = CASE WHEN ? <= 0 THEN 'tidak tersedia' ELSE status END
            WHERE isbn = ?
        ");
        $stmt->execute([$new_stok_total, $new_stok_total, $peminjaman['isbn']]);
        
        // Insert to pengembalian with fine
        $stmt = $conn->prepare("
            INSERT INTO pengembalian 
            (id_peminjaman, tanggal_pengembalian_aktual, kondisi_buku, denda, catatan, created_at) 
            VALUES (?, CURDATE(), ?, ?, ?, NOW())
        ");
        $kondisi = $status === 'rusak_parah' ? 'rusak_berat' : 'rusak_ringan';
        $catatan = "Buku $status - $alasan";
        $stmt->execute([
            $id_peminjaman,
            $kondisi,
            $denda_hilang_total,
            $catatan
        ]);
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'LAPOR_BUKU_HILANG',
            "Buku '{$peminjaman['judul']}' dilaporkan $status oleh {$peminjaman['nama']}. Denda: Rp " . number_format($denda_hilang_total, 0, ',', '.'),
            'buku_hilang',
            $id_hilang
        );
        
        setFlashMessage("Laporan buku $status berhasil disimpan! Denda: Rp " . number_format($denda_hilang_total, 0, ',', '.'), 'success');
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
    SELECT p.*, a.nama, b.judul, b.isbn,
           DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
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



<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="mb-4">
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
        <h1 class="h3 mb-2 title-container">
            <span class="logo-emoji">🚨</span>
            <span class="title-gradient">Lapor Buku Hilang/Rusak</span>
        </h1>
        <p class="text-muted mb-0">Laporkan buku yang hilang atau rusak parah</p>
    </div>

    <!-- Layout Grid: Info + Denda -->
    <div class="row g-3 mb-4">
        <!-- Left: Ketentuan Denda -->
        <div class="col-lg-6">
            <div class="modern-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Ketentuan Denda
                    </h5>
                    <ul class="mb-0">
                        <li class="mb-2">
                            <strong>Buku Hilang:</strong> 
                            <span class="badge bg-danger ms-2">Rp <?= number_format($denda_hilang, 0, ',', '.') ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Buku Rusak Parah:</strong> 
                            <span class="badge bg-warning text-dark ms-2">Rp <?= number_format($denda_rusak_parah, 0, ',', '.') ?></span>
                        </li>
                        <li>
                            <small class="text-muted">*Denda keterlambatan tetap berlaku jika ada</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right: Catatan Penting -->
        <div class="col-lg-6">
            <div class="modern-card h-100">
                <div class="card-body">
                    <h5 class="mb-3">
                        <i class="fas fa-info-circle text-warning me-2"></i>Catatan Penting
                    </h5>
                    <ul class="mb-0 small">
                        <li class="mb-2">Laporan akan mengurangi stok total buku</li>
                        <li class="mb-2">Anggota tidak dapat meminjam buku lain selama memiliki denda</li>
                        <li>Pastikan buku benar-benar hilang/rusak sebelum melapor</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label-modern">NIK Anggota</label>
                    <input type="text" name="nik" class="form-control-modern" 
                           placeholder="Cari NIK..." 
                           value="<?= htmlspecialchars($search_nik) ?>">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label-modern">Nama Anggota</label>
                    <input type="text" name="nama" class="form-control-modern" 
                           placeholder="Cari nama..." 
                           value="<?= htmlspecialchars($search_nama) ?>">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label-modern">ISBN Buku</label>
                    <input type="text" name="isbn" class="form-control-modern" 
                           placeholder="Cari ISBN..." 
                           value="<?= htmlspecialchars($search_isbn) ?>">
                </div>
                <div class="col-lg-3 col-md-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-modern btn-primary-modern flex-fill">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <a href="buku_hilang.php" class="btn btn-modern btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Pilih Peminjaman untuk Dilaporkan
                <?php if ($peminjaman_aktif): ?>
                    <span class="badge bg-info ms-2"><?= count($peminjaman_aktif) ?> peminjaman</span>
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if ($peminjaman_aktif): ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="20%">Anggota</th>
                                <th width="25%">Buku</th>
                                <th width="12%">Tgl Pinjam</th>
                                <th width="12%">Batas Kembali</th>
                                <th width="10%">Status</th>
                                <th width="13%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_aktif as $row): ?>
                                <?php $is_overdue = $row['hari_terlambat'] > 0; ?>
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
                                    </td>
                                    <td><?= formatTanggal($row['tanggal_pinjam']) ?></td>
                                    <td>
                                        <div class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatTanggal($row['tanggal_kembali']) ?>
                                        </div>
                                        <?php if ($is_overdue): ?>
                                            <small class="text-danger">+<?= $row['hari_terlambat'] ?> hari</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-clock me-1"></i>Terlambat
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-book-reader me-1"></i>Dipinjam
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-modern btn-danger-modern btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reportModal"
                                                data-peminjaman-id="<?= $row['id_peminjaman'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                                data-judul="<?= htmlspecialchars($row['judul']) ?>"
                                                data-isbn="<?= htmlspecialchars($row['isbn']) ?>"
                                                data-terlambat="<?= $is_overdue ? $row['hari_terlambat'] : 0 ?>">
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
                        <p class="text-muted mb-0">Coba ubah kriteria pencarian</p>
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
                                        <small class="text-muted">Denda: Rp <?= number_format($denda_hilang, 0, ',', '.') ?></small>
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
                                        <small class="text-muted">Denda: Rp <?= number_format($denda_rusak_parah, 0, ',', '.') ?></small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div class="mb-3">
                        <label class="form-label-modern">Alasan/Keterangan <span class="text-danger">*</span></label>
                        <textarea name="alasan" class="form-control-modern" rows="3" 
                                  placeholder="Jelaskan alasan buku hilang/rusak..." required></textarea>
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
                                <td>Denda Hilang/Rusak:</td>
                                <td id="denda_hilang_display" class="text-end">-</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>TOTAL DENDA:</strong></td>
                                <td id="total_denda" class="text-end fw-bold text-danger">-</td>
                            </tr>
                        </table>
                        <small class="text-muted">*Stok total buku akan dikurangi 1 unit</small>
                    </div>
                    
                    <!-- Confirmation -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_report" required>
                        <label class="form-check-label" for="confirm_report">
                            Saya menyatakan bahwa buku benar-benar <span id="status_label">hilang/rusak</span> dan siap menerima konsekuensi denda
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modern btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" name="lapor_buku" class="btn btn-modern btn-danger-modern">
                        <i class="fas fa-paper-plane me-2"></i>Kirim Laporan
                    </button>
                </div>
            </form>
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
        
        // Calculate late fine (assuming 1000 per day)
        const dendaKeterlambatan = terlambat * 1000;
        document.getElementById('denda_keterlambatan').textContent = 
            dendaKeterlambatan > 0 ? `Rp ${dendaKeterlambatan.toLocaleString('id-ID')}` : 'Rp 0';
        
        // Reset form
        document.getElementById('reportForm').reset();
        document.getElementById('status_label').textContent = 'hilang/rusak';
        document.getElementById('denda_hilang_display').textContent = '-';
        document.getElementById('total_denda').textContent = '-';
    });
}

// Update fine calculation when status changes
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        updateFineCalculation();
    });
});

function updateFineCalculation() {
    const status = document.querySelector('input[name="status"]:checked');
    const terlambat = parseInt(document.querySelector('[data-terlambat]')?.getAttribute('data-terlambat') || 0);
    const dendaKeterlambatan = terlambat * 1000;
    
    if (!status) {
        document.getElementById('denda_hilang_display').textContent = '-';
        document.getElementById('total_denda').textContent = '-';
        return;
    }
    
    // Get fine amounts from PHP variables (embedded in HTML)
    const dendaHilang = <?= $denda_hilang ?>;
    const dendaRusak = <?= $denda_rusak_parah ?>;
    
    let dendaHilangTotal = 0;
    if (status.value === 'hilang') {
        dendaHilangTotal = dendaHilang;
        document.getElementById('status_label').textContent = 'hilang';
    } else if (status.value === 'rusak_parah') {
        dendaHilangTotal = dendaRusak;
        document.getElementById('status_label').textContent = 'rusak parah';
    }
    
    document.getElementById('denda_hilang_display').textContent = 
        `Rp ${dendaHilangTotal.toLocaleString('id-ID')}`;
    
    const totalDenda = dendaKeterlambatan + dendaHilangTotal;
    document.getElementById('total_denda').textContent = 
        `Rp ${totalDenda.toLocaleString('id-ID')}`;
}
</script>

<?php include '../../includes/footer.php'; ?>