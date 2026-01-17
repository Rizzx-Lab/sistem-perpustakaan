<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Peminjaman Buku';
include '../../config/database.php';

// Get denda per hari dari pengaturan
$stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_per_hari'");
$denda_per_hari = (int)$stmt->fetchColumn() ?: 1000;

// Proteksi double-submit menggunakan token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlashMessage('Invalid security token. Please try again.', 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
    // Regenerate token setelah digunakan
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== Tambah Peminjaman ==========
if (isset($_POST['tambah_peminjaman'])) {
    $nik = sanitizeInput($_POST['nik']);
    $isbn = sanitizeInput($_POST['isbn']);
    $tanggal_pinjam = sanitizeInput($_POST['tanggal_pinjam']);
    
    try {
        $conn->beginTransaction();
        
        // Validasi input
        if (empty($nik) || empty($isbn) || empty($tanggal_pinjam)) {
            throw new Exception('Semua field harus diisi');
        }
        
        // Validasi format NIK (16 digit)
        if (!preg_match('/^\d{16}$/', $nik)) {
            throw new Exception('NIK harus 16 digit angka');
        }
        
        // Validasi tanggal tidak di masa depan
        if ($tanggal_pinjam > date('Y-m-d')) {
            throw new Exception('Tanggal pinjam tidak boleh di masa depan');
        }
        
        // Get max loan days dari setting
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $stmt->execute();
        $max_days = (int)($stmt->fetchColumn() ?: 14);
        
        $tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . " +{$max_days} days"));
        
        // Check apakah anggota exists
        $stmt = $conn->prepare("SELECT nama FROM anggota WHERE nik = ?");
        $stmt->execute([$nik]);
        $anggota = $stmt->fetch();
        
        if (!$anggota) {
            throw new Exception('Anggota dengan NIK tersebut tidak ditemukan');
        }
        
        // Check apakah buku exists dan stok tersedia dengan LOCKING
        $stmt = $conn->prepare("SELECT judul, stok_total, stok_tersedia FROM buku WHERE isbn = ? FOR UPDATE");
        $stmt->execute([$isbn]);
        $buku = $stmt->fetch();
        
        if (!$buku) {
            throw new Exception('Buku dengan ISBN tersebut tidak ditemukan');
        }
        
        // Validasi stok tersedia - gunakan stok_tersedia bukan stok
        $current_stock = (int)$buku['stok_tersedia'];
        if ($current_stock < 1) {
            throw new Exception('Stok buku tidak tersedia');
        }
        
        // Check apakah anggota sudah pinjam buku ini (belum dikembalikan)
        $stmt = $conn->prepare("
            SELECT id_peminjaman 
            FROM peminjaman 
            WHERE nik = ? AND isbn = ? AND status = 'dipinjam'
        ");
        $stmt->execute([$nik, $isbn]);
        if ($stmt->fetch()) {
            throw new Exception('Anggota sudah meminjam buku ini. Harap kembalikan terlebih dahulu.');
        }
        
        // Check apakah anggota punya denda belum lunas
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM pengembalian pg
            JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
            WHERE p.nik = ? AND pg.denda > 0
        ");
        $stmt->execute([$nik]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Anggota memiliki denda yang belum lunas. Harap lunasi terlebih dahulu.');
        }
        
        // Check maksimal peminjaman per anggota
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'");
        $stmt->execute();
        $max_books = (int)($stmt->fetchColumn() ?: 3);
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
        $stmt->execute([$nik]);
        $current_loans = $stmt->fetchColumn();
        
        if ($current_loans >= $max_books) {
            throw new Exception("Anggota sudah mencapai batas maksimal peminjaman ({$max_books} buku)");
        }
        
        // INSERT peminjaman
        $stmt = $conn->prepare("
            INSERT INTO peminjaman (nik, isbn, tanggal_pinjam, tanggal_kembali, status, created_at) 
            VALUES (?, ?, ?, ?, 'dipinjam', NOW())
        ");
        $stmt->execute([$nik, $isbn, $tanggal_pinjam, $tanggal_kembali]);
        $id_peminjaman = $conn->lastInsertId();
        
        // Update stok_tersedia (bukan stok)
        $stmt = $conn->prepare("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE isbn = ? AND stok_tersedia > 0");
        $stmt->execute([$isbn]);
        
        // Cek apakah stok berhasil diupdate
        $rows_affected = $stmt->rowCount();
        if ($rows_affected === 0) {
            throw new Exception('Gagal mengurangi stok. Stok mungkin sudah habis saat proses.');
        }
        
        // Verifikasi stok akhir tidak minus
        $stmt = $conn->prepare("SELECT stok_tersedia FROM buku WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $final_stock = $stmt->fetchColumn();
        
        if ($final_stock < 0) {
            throw new Exception('ERROR: Stok menjadi negatif. Rollback transaction.');
        }
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'PEMINJAMAN',
            "Peminjaman buku '{$buku['judul']}' oleh {$anggota['nama']} (NIK: {$nik}). Stok awal: {$current_stock}, stok akhir: {$final_stock}. Kembali: " . formatTanggal($tanggal_kembali),
            'peminjaman',
            $id_peminjaman
        );
        
        setFlashMessage("Peminjaman berhasil ditambahkan! Batas kembali: " . formatTanggal($tanggal_kembali) . " | Stok tersisa: {$final_stock}", 'success');
        redirect($_SERVER['REQUEST_URI']);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect($_SERVER['REQUEST_URI']);
    }
}

// ========== Filter dan Pencarian ==========
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($_GET['nik'])) {
    $whereClause .= " AND p.nik LIKE ?";
    $params[] = '%' . $_GET['nik'] . '%';
}

if (!empty($_GET['isbn'])) {
    $whereClause .= " AND p.isbn LIKE ?";
    $params[] = '%' . $_GET['isbn'] . '%';
}

if (!empty($_GET['status'])) {
    $whereClause .= " AND p.status = ?";
    $params[] = $_GET['status'];
}

// Get all available books for dropdown
try {
    $stmt_books = $conn->query("
        SELECT b.isbn, b.judul, b.pengarang, b.tahun_terbit, b.stok_total, b.stok_tersedia,
               p.nama_penerbit,
               r.kode_rak
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON b.id_rak = r.id_rak
        WHERE b.stok_tersedia > 0 AND b.status = 'tersedia'
        ORDER BY b.judul ASC
    ");
    $available_books = $stmt_books->fetchAll();
} catch (PDOException $e) {
    $available_books = [];
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "
    SELECT COUNT(*) 
    FROM peminjaman p 
    JOIN anggota a ON p.nik = a.nik 
    JOIN buku b ON p.isbn = b.isbn 
    $whereClause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get peminjaman data dengan LEFT JOIN ke pengembalian - UPDATED dengan catatan
$query = "
    SELECT p.*, 
           a.nama, 
           b.judul,
           b.stok_tersedia as stok_sekarang,
           pg.denda as denda_dibayar,
           pg.tanggal_pengembalian_aktual,
           pg.kondisi_buku,
           pg.catatan,
           CASE 
               WHEN p.status = 'dipinjam' AND CURDATE() > p.tanggal_kembali 
               THEN DATEDIFF(CURDATE(), p.tanggal_kembali)
               WHEN p.status = 'dikembalikan' AND pg.tanggal_pengembalian_aktual > p.tanggal_kembali
               THEN DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali)
               ELSE 0
           END as hari_terlambat,
           CASE 
               WHEN p.status = 'dipinjam' AND CURDATE() > p.tanggal_kembali 
               THEN DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari}
               WHEN p.status = 'dikembalikan'
               THEN COALESCE(pg.denda, 0)
               ELSE 0
           END as denda_aktual
    FROM peminjaman p
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    $whereClause
    ORDER BY p.id_peminjaman DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$peminjaman = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM peminjaman")->fetchColumn(),
    'dipinjam' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn(),
    'dikembalikan' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dikembalikan'")->fetchColumn(),
    'terlambat' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali < CURDATE()")->fetchColumn(),
    'total_buku' => $conn->query("SELECT COUNT(*) FROM buku")->fetchColumn(),
    'buku_tersedia' => $conn->query("SELECT SUM(stok_tersedia) FROM buku")->fetchColumn()
];

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
                    <li class="breadcrumb-item active">Peminjaman</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📚</span>
                <span class="title-gradient">Kelola Peminjaman</span>
            </h1>
            <p class="text-muted mb-0">Manage peminjaman buku perpustakaan</p>
        </div>
        <div>
            <button type="button" class="btn btn-modern btn-success-modern" data-bs-toggle="modal" data-bs-target="#tambahPeminjamanModal">
                <i class="fas fa-plus me-2"></i>Tambah Peminjaman
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

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total'] ?></div>
                <small class="stat-label">Total Peminjaman</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-hand-holding-heart text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['dipinjam'] ?></div>
                <small class="stat-label">Sedang Dipinjam</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['dikembalikan'] ?></div>
                <small class="stat-label">Dikembalikan</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
                <div class="stat-number text-danger"><?= $stats['terlambat'] ?></div>
                <small class="stat-label">Terlambat</small>
            </div>
        </div>
    </div>

    <!-- Quick Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="modern-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-box-open me-2"></i>Inventaris Buku</h6>
                        <p class="mb-0 text-muted small"><?= $stats['total_buku'] ?> judul buku</p>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0"><?= $stats['buku_tersedia'] ?></div>
                        <small class="text-muted">Stok tersedia</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="modern-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Pengaturan Sistem</h6>
                        <p class="mb-0 text-muted small">Maks. <?= $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'")->fetchColumn() ?> buku/anggota</p>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0"><?= $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'")->fetchColumn() ?> hari</div>
                        <small class="text-muted">Durasi pinjam</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">NIK Anggota</label>
                    <input type="text" name="nik" class="form-control-modern" 
                           placeholder="Cari NIK..." 
                           value="<?= htmlspecialchars($_GET['nik'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">ISBN Buku</label>
                    <input type="text" name="isbn" class="form-control-modern" 
                           placeholder="Cari ISBN..." 
                           value="<?= htmlspecialchars($_GET['isbn'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Status</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="dipinjam" <?= ($_GET['status'] ?? '') === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="dikembalikan" <?= ($_GET['status'] ?? '') === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-modern flex-fill">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <a href="peminjaman.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Peminjaman Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-list me-2"></i>Data Peminjaman
                    <small class="text-muted">(<?= $total_records ?> record)</small>
                </h5>
            </div>
            <div>
                <?php if ($total_records > 0): ?>
                    <span class="badge bg-info">Halaman <?= $page ?> dari <?= $total_pages ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Anggota</th>
                            <th>Buku</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                            <th>Denda</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($peminjaman): ?>
                            <?php foreach ($peminjaman as $row): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= $row['id_peminjaman'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                        <small class="text-muted">NIK: <?= htmlspecialchars($row['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-start">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars(mb_substr($row['judul'], 0, 25)) ?><?= mb_strlen($row['judul']) > 25 ? '...' : '' ?></div>
                                                <small class="text-muted">ISBN: <?= htmlspecialchars($row['isbn']) ?></small>
                                                <?php if ($row['status'] === 'dipinjam'): ?>
                                                    <br><small class="text-muted">Stok tersedia: <?= $row['stok_sekarang'] ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= formatTanggal($row['tanggal_pinjam']) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="<?= $row['status'] === 'dipinjam' && $row['hari_terlambat'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatTanggal($row['tanggal_kembali']) ?>
                                        </div>
                                        <?php if ($row['status'] === 'dipinjam' && $row['hari_terlambat'] > 0): ?>
                                            <small class="text-danger">+<?= $row['hari_terlambat'] ?> hari terlambat</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'dipinjam'): ?>
                                            <?php if ($row['hari_terlambat'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-clock me-1"></i>Terlambat
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-book-reader me-1"></i>Dipinjam
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Dikembalikan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $denda_tampil = $row['denda_aktual'];
                                        if ($denda_tampil > 0): 
                                        ?>
                                            <span class="text-danger fw-bold"><?= formatRupiah($denda_tampil) ?></span>
                                            <?php if ($row['status'] === 'dipinjam'): ?>
                                                <br><small class="text-muted" style="font-size: 0.75rem;">*terus berjalan</small>
                                            <?php endif; ?>
                                            <?php if ($row['kondisi_buku'] && $row['kondisi_buku'] !== 'baik'): ?>
                                                <br><small class="text-muted"><?= ucfirst(str_replace('_', ' ', $row['kondisi_buku'])) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($row['status'] === 'dipinjam'): ?>
                                                <a href="pengembalian.php?id=<?= $row['id_peminjaman'] ?>" class="btn btn-success" title="Kembalikan Buku">
                                                    <i class="fas fa-undo"></i>
                                                </a>
                                                <button type="button" class="btn btn-info" 
                                                        onclick='showDetailModal(<?= json_encode($row) ?>)'
                                                        title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-info" 
                                                        onclick='showDetailModal(<?= json_encode($row) ?>)'
                                                        title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">Tidak ada data peminjaman</h6>
                                    <?php if (!empty($_GET)): ?>
                                        <p class="small">Coba reset filter atau tambah peminjaman baru</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Menampilkan <?= min($limit, $total_records - $offset) ?> dari <?= $total_records ?> data
                        </div>
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Peminjaman -->
<div class="modal fade" id="tambahPeminjamanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Tambah Peminjaman Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formTambahPeminjaman">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">NIK Anggota <span class="text-danger">*</span></label>
                                <input type="text" name="nik" class="form-control-modern" 
                                       placeholder="Masukkan NIK 16 digit" 
                                       pattern="\d{16}"
                                       maxlength="16" required
                                       oninput="checkMember()">
                                <div id="memberInfo" class="mt-1 small"></div>
                                <small class="text-muted">Contoh: 1234567890123456</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">ISBN Buku <span class="text-danger">*</span></label>
                                <select name="isbn" class="form-control-modern" required onchange="showBookInfo()">
                                    <option value="">-- Pilih Buku --</option>
                                    <?php foreach ($available_books as $book): ?>
                                        <option value="<?= htmlspecialchars($book['isbn']) ?>" 
                                                data-judul="<?= htmlspecialchars($book['judul']) ?>"
                                                data-pengarang="<?= htmlspecialchars($book['pengarang']) ?>"
                                                data-penerbit="<?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?>"
                                                data-rak="<?= htmlspecialchars($book['kode_rak'] ?? '-') ?>"
                                                data-tahun="<?= htmlspecialchars($book['tahun_terbit']) ?>"
                                                data-stok-total="<?= htmlspecialchars($book['stok_total']) ?>"
                                                data-stok-tersedia="<?= htmlspecialchars($book['stok_tersedia']) ?>">
                                            <?= htmlspecialchars($book['judul']) ?> (<?= htmlspecialchars($book['pengarang']) ?>)
                                            - Tersedia: <?= htmlspecialchars($book['stok_tersedia']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih buku dari daftar yang tersedia</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Info Buku Terpilih -->
                    <div id="bookInfo" class="alert alert-secondary d-none mb-3">
                        <h6 class="mb-2"><i class="fas fa-book me-2"></i>Detail Buku</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td width="100"><strong>ISBN</strong></td>
                                <td>: <span id="infoISBN">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Judul</strong></td>
                                <td>: <span id="infoJudul">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Pengarang</strong></td>
                                <td>: <span id="infoPengarang">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Penerbit</strong></td>
                                <td>: <span id="infoPenerbit">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Rak</strong></td>
                                <td>: <span id="infoRak">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Tahun</strong></td>
                                <td>: <span id="infoTahun">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Stok Total</strong></td>
                                <td>: <span id="infoStokTotal" class="badge bg-secondary">-</span></td>
                            </tr>
                            <tr>
                                <td><strong>Stok Tersedia</strong></td>
                                <td>: <span id="infoStokTersedia" class="badge bg-success">-</span></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">Tanggal Pinjam <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_pinjam" class="form-control-modern" 
                                       value="<?= date('Y-m-d') ?>" 
                                       max="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-modern">Batas Kembali</label>
                                <input type="text" class="form-control-modern bg-light" 
                                       value="<?= date('d/m/Y', strtotime('+14 days')) ?>" 
                                       readonly>
                                <small class="text-muted">Otomatis 14 hari setelah pinjam</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <i class="fas fa-info-circle me-3 mt-1"></i>
                            <div>
                                <strong>Ketentuan Peminjaman:</strong>
                                <ul class="mb-0 small">
                                    <li>Maksimal meminjam <strong>3 buku</strong> dalam waktu bersamaan</li>
                                    <li>Batas waktu peminjaman <strong>14 hari</strong></li>
                                    <li>Denda keterlambatan <strong>Rp 1.000/hari</strong></li>
                                    <li>Tidak boleh ada denda yang belum lunas</li>
                                    <li>Anggota tidak boleh meminjam buku yang sama 2 kali</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div id="validationResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" name="tambah_peminjaman" class="btn btn-success-modern" id="submitBtn">
                        <i class="fas fa-save me-2"></i>Simpan Peminjaman
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Peminjaman - UPDATED dengan Catatan -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>Detail Peminjaman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-user me-2"></i>Informasi Anggota</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="40%"><strong>Nama</strong></td>
                                <td>: <span id="detail_nama"></span></td>
                            </tr>
                            <tr>
                                <td><strong>NIK</strong></td>
                                <td>: <span id="detail_nik"></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-book me-2"></i>Informasi Buku</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="40%"><strong>Judul</strong></td>
                                <td>: <span id="detail_judul"></span></td>
                            </tr>
                            <tr>
                                <td><strong>ISBN</strong></td>
                                <td>: <span id="detail_isbn"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-calendar-alt me-2"></i>Jadwal Peminjaman</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="50%"><strong>Tanggal Pinjam</strong></td>
                                <td>: <span id="detail_pinjam"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Batas Kembali</strong></td>
                                <td>: <span id="detail_batas"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Tgl Pengembalian</strong></td>
                                <td>: <span id="detail_kembali"></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-file-invoice-dollar me-2"></i>Informasi Denda</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="50%"><strong>Kondisi Buku</strong></td>
                                <td>: <span id="detail_kondisi"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Total Denda</strong></td>
                                <td>: <span id="detail_denda"></span></td>
                            </tr>
                            <tr>
                                <td><strong>Status</strong></td>
                                <td>: <span id="detail_status"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Catatan Section -->
                <div class="mt-4" id="detail_notes"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-modern" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi untuk check member real-time
function checkMember() {
    const nik = document.querySelector('input[name="nik"]').value;
    const memberInfo = document.getElementById('memberInfo');
    
    if (nik.length === 16) {
        fetch(`../../api/check_member.php?nik=${nik}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    memberInfo.innerHTML = `<span class="text-success">
                        <i class="fas fa-check-circle me-1"></i>Anggota ditemukan: ${data.nama}
                    </span>`;
                    memberInfo.className = 'mt-1 small text-success';
                } else {
                    memberInfo.innerHTML = `<span class="text-danger">
                        <i class="fas fa-times-circle me-1"></i>Anggota tidak ditemukan
                    </span>`;
                    memberInfo.className = 'mt-1 small text-danger';
                }
            })
            .catch(error => {
                memberInfo.innerHTML = '';
            });
    } else {
        memberInfo.innerHTML = '';
    }
}

// Fungsi untuk menampilkan info buku
function showBookInfo() {
    const select = document.querySelector('select[name="isbn"]');
    const selectedOption = select.options[select.selectedIndex];
    const bookInfo = document.getElementById('bookInfo');
    
    if (select.value) {
        document.getElementById('infoISBN').textContent = select.value;
        document.getElementById('infoJudul').textContent = selectedOption.getAttribute('data-judul');
        document.getElementById('infoPengarang').textContent = selectedOption.getAttribute('data-pengarang');
        document.getElementById('infoPenerbit').textContent = selectedOption.getAttribute('data-penerbit');
        document.getElementById('infoRak').textContent = selectedOption.getAttribute('data-rak');
        document.getElementById('infoTahun').textContent = selectedOption.getAttribute('data-tahun');
        document.getElementById('infoStokTotal').textContent = selectedOption.getAttribute('data-stok-total') + ' buku';
        document.getElementById('infoStokTersedia').textContent = selectedOption.getAttribute('data-stok-tersedia') + ' buku';
        
        bookInfo.classList.remove('d-none');
    } else {
        bookInfo.classList.add('d-none');
    }
}

// Helper function untuk escape HTML (mencegah XSS)
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Fungsi untuk menampilkan detail peminjaman - UPDATED dengan catatan
function showDetailModal(data) {
    // Set nilai detail
    document.getElementById('detail_nama').textContent = data.nama;
    document.getElementById('detail_nik').textContent = data.nik;
    document.getElementById('detail_judul').textContent = data.judul;
    document.getElementById('detail_isbn').textContent = data.isbn;
    
    // Format tanggal
    const tglPinjam = new Date(data.tanggal_pinjam).toLocaleDateString('id-ID');
    const tglKembali = new Date(data.tanggal_kembali).toLocaleDateString('id-ID');
    
    document.getElementById('detail_pinjam').textContent = tglPinjam;
    document.getElementById('detail_batas').textContent = tglKembali;
    
    // Tanggal pengembalian aktual
    const tglKembaliAktual = data.tanggal_pengembalian_aktual 
        ? new Date(data.tanggal_pengembalian_aktual).toLocaleDateString('id-ID')
        : '-';
    document.getElementById('detail_kembali').textContent = tglKembaliAktual;
    
    // Kondisi buku
    const kondisiBuku = data.kondisi_buku || '-';
    let kondisiHTML = '';
    switch(kondisiBuku) {
        case 'baik':
            kondisiHTML = '<span class="badge bg-success">Baik</span>';
            break;
        case 'rusak_ringan':
            kondisiHTML = '<span class="badge bg-warning">Rusak Ringan</span>';
            break;
        case 'rusak_berat':
            kondisiHTML = '<span class="badge bg-danger">Rusak Berat</span>';
            break;
        default:
            kondisiHTML = kondisiBuku;
    }
    document.getElementById('detail_kondisi').innerHTML = kondisiHTML;
    
    // Denda - gunakan denda_aktual yang sudah dihitung
    const denda = data.denda_aktual && data.denda_aktual > 0 
        ? `<span class="text-danger fw-bold">Rp ${parseInt(data.denda_aktual).toLocaleString('id-ID')}</span>` 
        : '<span class="text-muted">Tidak ada denda</span>';
    document.getElementById('detail_denda').innerHTML = denda;
    
    // Status
    let statusHTML = '';
    if (data.status === 'dipinjam') {
        if (data.hari_terlambat > 0) {
            statusHTML = `<span class="badge bg-danger">
                <i class="fas fa-exclamation-triangle me-1"></i>Terlambat (${data.hari_terlambat} hari)
            </span>`;
        } else {
            statusHTML = `<span class="badge bg-warning">
                <i class="fas fa-book-reader me-1"></i>Dipinjam
            </span>`;
        }
    } else {
        statusHTML = `<span class="badge bg-success">
            <i class="fas fa-check-circle me-1"></i>Dikembalikan
        </span>`;
    }
    document.getElementById('detail_status').innerHTML = statusHTML;
    
    // ===== BAGIAN BARU: Tampilkan catatan =====
    const detailNotesDiv = document.getElementById('detail_notes');
    let notesHTML = '';
    
    if (data.catatan) {
        notesHTML = `
            <div class="alert alert-info border-start border-4 border-info">
                <h6 class="mb-2">
                    <i class="fas fa-sticky-note me-2"></i>Catatan Pengembalian
                </h6>
                <p class="mb-0">${escapeHtml(data.catatan)}</p>
            </div>
        `;
    } else if (data.status === 'dikembalikan') {
        // Jika sudah dikembalikan tapi tidak ada catatan
        notesHTML = `
            <div class="alert alert-light border-start border-4 border-secondary">
                <h6 class="mb-2">
                    <i class="fas fa-sticky-note me-2"></i>Catatan Pengembalian
                </h6>
                <p class="mb-0 text-muted"><em>Tidak ada catatan</em></p>
            </div>
        `;
    } else if (data.status === 'dipinjam') {
        // Jika masih dipinjam, tampilkan info bahwa catatan akan ada saat pengembalian
        notesHTML = `
            <div class="alert alert-secondary border-start border-4 border-secondary">
                <h6 class="mb-2">
                    <i class="fas fa-sticky-note me-2"></i>Catatan Pengembalian
                </h6>
                <p class="mb-0 text-muted"><em>Catatan akan tersimpan saat buku dikembalikan</em></p>
            </div>
        `;
    }
    
    detailNotesDiv.innerHTML = notesHTML;
    
    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
}

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