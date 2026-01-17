<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Perpanjangan Peminjaman';
include '../../config/database.php';

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

// ========== Perpanjangan Peminjaman ==========
if (isset($_POST['perpanjang_peminjaman'])) {
    $id_peminjaman = (int)$_POST['id_peminjaman'];
    $tanggal_kembali_baru = sanitizeInput($_POST['tanggal_kembali_baru']);
    $alasan_perpanjangan = sanitizeInput($_POST['alasan_perpanjangan'] ?? '');
    
    try {
        $conn->beginTransaction();
        
        // Validasi tanggal
        if ($tanggal_kembali_baru <= date('Y-m-d')) {
            throw new Exception('Tanggal perpanjangan harus setelah hari ini');
        }
        
        // Get peminjaman data
        $stmt = $conn->prepare("
            SELECT p.*, b.judul, a.nama, a.nik
            FROM peminjaman p
            JOIN buku b ON p.isbn = b.isbn
            JOIN anggota a ON p.nik = a.nik
            WHERE p.id_peminjaman = ? AND p.status = 'dipinjam'
        ");
        $stmt->execute([$id_peminjaman]);
        $pinjam = $stmt->fetch();
        
        if (!$pinjam) {
            throw new Exception('Data peminjaman tidak ditemukan atau sudah dikembalikan');
        }
        
        // Validasi: tanggal perpanjangan harus setelah tanggal kembali lama
        if ($tanggal_kembali_baru <= $pinjam['tanggal_kembali']) {
            throw new Exception('Tanggal perpanjangan harus setelah tanggal kembali sebelumnya');
        }
        
        // Get max loan days dari setting
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $stmt->execute();
        $max_days = (int)($stmt->fetchColumn() ?: 14);
        
        // Hitung lama perpanjangan
        $date1 = new DateTime($pinjam['tanggal_kembali']);
        $date2 = new DateTime($tanggal_kembali_baru);
        $days_extended = $date2->diff($date1)->days;
        
        // Validasi: tidak boleh perpanjang lebih dari max days
        if ($days_extended > $max_days) {
            throw new Exception("Perpanjangan maksimal {$max_days} hari");
        }
        
        // Update tanggal kembali
        $stmt = $conn->prepare("
            UPDATE peminjaman 
            SET tanggal_kembali = ? 
            WHERE id_peminjaman = ?
        ");
        $stmt->execute([$tanggal_kembali_baru, $id_peminjaman]);
        
        $conn->commit();
        
        // Log activity
        logActivity(
            'PERPANJANGAN',
            "Perpanjangan peminjaman #{$id_peminjaman} buku '{$pinjam['judul']}' oleh {$pinjam['nama']} (NIK: {$pinjam['nik']}). " .
            "Dari: " . formatTanggal($pinjam['tanggal_kembali']) . " ke: " . formatTanggal($tanggal_kembali_baru) . " ({$days_extended} hari)",
            'peminjaman',
            $id_peminjaman
        );
        
        setFlashMessage("Perpanjangan berhasil! Buku diperpanjang sampai " . formatTanggal($tanggal_kembali_baru) . " (+{$days_extended} hari)", 'success');
        redirect('perpanjangan.php');
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        setFlashMessage($e->getMessage(), 'error');
        redirect('perpanjangan.php');
    }
}

// ========== Filter dan Pencarian ==========
$whereClause = "WHERE p.status = 'dipinjam'";
$params = [];

if (!empty($_GET['search'])) {
    $whereClause .= " AND (a.nama LIKE ? OR a.nik LIKE ? OR b.judul LIKE ? OR b.isbn LIKE ?)";
    $searchParam = "%{$_GET['search']}%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Get max loan days dari setting
$max_days = (int)$conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'")->fetchColumn() ?: 14;

// Get active peminjaman yang bisa diperpanjang
$query = "
    SELECT p.*, a.nama, a.nik, b.judul, b.pengarang, b.isbn,
           DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
           CASE 
               WHEN CURDATE() > p.tanggal_kembali THEN (DATEDIFF(CURDATE(), p.tanggal_kembali) * 1000)
               ELSE 0 
           END as estimasi_denda,
           CASE 
               WHEN DATEDIFF(p.tanggal_kembali, CURDATE()) <= 3 THEN 'segera_jatuh_tempo'
               ELSE 'normal' 
           END as status_jatuh_tempo
    FROM peminjaman p
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    $whereClause
    ORDER BY p.tanggal_kembali ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$peminjaman_list = $stmt->fetchAll();

// Filter peminjaman yang bisa diperpanjang (tidak terlambat)
$can_extend = array_filter($peminjaman_list, function($row) {
    return $row['hari_terlambat'] <= 0;
});

$cannot_extend = array_filter($peminjaman_list, function($row) {
    return $row['hari_terlambat'] > 0;
});

// Get statistics
$stats = [
    'total_active' => count($peminjaman_list),
    'can_extend' => count($can_extend),
    'cannot_extend' => count($cannot_extend),
    'due_soon' => count(array_filter($peminjaman_list, function($row) {
        return $row['status_jatuh_tempo'] === 'segera_jatuh_tempo' && $row['hari_terlambat'] <= 0;
    })),
    'max_extension_days' => $max_days
];

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
    position: relative;
}

/* FIX untuk card header yang mepet */
.card-header-modern {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px !important;
    border-radius: 12px 12px 0 0 !important;
}

/* FIX untuk card body yang mepet */
.card-body {
    padding: 20px !important;
}

/* FIX untuk card title yang terlalu mepet */
.card-title-modern {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0;
    padding: 2px 0;
}

/* FIX untuk form controls */
.form-control-modern {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
    font-size: 0.95rem;
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
    font-size: 0.95rem;
}

/* FIX untuk tombol */
.btn-primary-modern {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-color: #007bff;
    color: white;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
}

.btn-primary-modern:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    border-color: #0056b3;
    color: white;
}

/* FIX untuk alert */
.alert {
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
    border: 1px solid transparent;
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

/* FIX untuk stat cards */
.stat-icon {
    font-size: 2rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 5px 0;
}

.stat-label {
    color: #6c757d;
    font-size: 0.85rem;
}

/* FIX untuk table modern */
.table-modern {
    margin-bottom: 0;
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #e0e0e0;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
}

.table-modern td {
    padding: 12px 15px;
    vertical-align: middle;
    border-top: 1px solid #e0e0e0;
}

.table-modern tbody tr:hover {
    background-color: #f8f9fa;
}

/* FIX untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.8rem;
}

/* FIX untuk modal */
.modal-content.modern-card {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
}

/* FIX untuk tabs modern */
.modern-tabs .nav-link {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    border-radius: 8px 8px 0 0;
    padding: 12px 20px;
    margin-right: 5px;
    color: #6c757d;
    font-weight: 500;
}

.modern-tabs .nav-link.active {
    background-color: #fff;
    border-bottom: 3px solid #007bff;
    color: #007bff;
    font-weight: 600;
}

/* FIX untuk search bar */
.input-group .form-control-modern {
    border-radius: 8px 0 0 8px;
}

.input-group .btn {
    border-radius: 0 8px 8px 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .modern-card {
        margin-bottom: 15px;
    }
    
    .card-body {
        padding: 15px !important;
    }
    
    .card-header-modern {
        padding: 12px 15px !important;
    }
    
    .form-control-modern {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .btn-modern {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .table-modern th,
    .table-modern td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-icon {
        font-size: 1.8rem;
    }
}

/* FIX untuk text yang terlalu mepet */
.text-muted {
    margin-top: 5px;
    display: block;
}

.small {
    font-size: 0.85rem;
}

/* FIX untuk row dan col spacing */
.row.g-3 {
    margin-bottom: 15px;
}

.row.g-3 > * {
    padding: 5px;
}

/* FIX untuk breadcrumb */
.breadcrumb {
    background: transparent;
    padding: 8px 0;
    margin-bottom: 10px;
}

.breadcrumb-item a {
    text-decoration: none;
    color: #6c757d;
    font-size: 0.9rem;
}

.breadcrumb-item.active {
    color: #495057;
    font-weight: 500;
}

/* FIX untuk title container */
.title-container {
    margin-bottom: 10px;
}

.title-gradient {
    background: linear-gradient(135deg, #007bff 0%, #00bfff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logo-emoji {
    font-size: 1.5rem;
    margin-right: 10px;
}

/* FIX untuk d-flex spacing */
.d-flex.justify-content-between {
    padding: 5px 0;
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
                    </li>
                    <li class="breadcrumb-item active">Perpanjangan</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">⏳</span>
                <span class="title-gradient">Perpanjangan Peminjaman</span>
            </h1>
            <p class="text-muted mb-0">Kelola perpanjangan waktu peminjaman buku</p>
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
                <div class="stat-number text-primary"><?= $stats['total_active'] ?></div>
                <small class="stat-label">Sedang Dipinjam</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['can_extend'] ?></div>
                <small class="stat-label">Dapat Diperpanjang</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-times-circle text-danger"></i>
                </div>
                <div class="stat-number text-danger"><?= $stats['cannot_extend'] ?></div>
                <small class="stat-label">Tidak Bisa Diperpanjang</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['due_soon'] ?></div>
                <small class="stat-label">Segera Jatuh Tempo</small>
            </div>
        </div>
    </div>

    <!-- Quick Info -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="modern-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-info-circle me-2"></i>Ketentuan Perpanjangan</h6>
                        <p class="mb-0 text-muted small">
                            • Peminjaman hanya bisa diperpanjang jika <strong>belum terlambat</strong><br>
                            • Maksimal perpanjangan <strong><?= $stats['max_extension_days'] ?> hari</strong><br>
                            • Hanya boleh diperpanjang <strong>sekali</strong> per peminjaman<br>
                            • Perpanjangan tidak bisa dilakukan jika ada peminjaman lain yang menunggu
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0"><?= $stats['max_extension_days'] ?> hari</div>
                        <small class="text-muted">Maks. perpanjangan</small>
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
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-modern w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs untuk peminjaman yang bisa/tidak bisa diperpanjang -->
    <ul class="nav nav-tabs modern-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="can-extend-tab" data-bs-toggle="tab" data-bs-target="#can-extend" type="button" role="tab">
                <i class="fas fa-check-circle me-2"></i>Dapat Diperpanjang
                <span class="badge bg-success ms-2"><?= count($can_extend) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cannot-extend-tab" data-bs-toggle="tab" data-bs-target="#cannot-extend" type="button" role="tab">
                <i class="fas fa-times-circle me-2"></i>Tidak Bisa Diperpanjang
                <span class="badge bg-danger ms-2"><?= count($cannot_extend) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <!-- Tab: Dapat Diperpanjang -->
        <div class="tab-pane fade show active" id="can-extend" role="tabpanel">
            <?php if (!empty($can_extend)): ?>
                <div class="row g-4">
                    <?php foreach ($can_extend as $row): ?>
                        <div class="col-md-6">
                            <div class="modern-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="card-title mb-1"><?= htmlspecialchars($row['judul']) ?></h6>
                                            <p class="text-muted small mb-1"><?= htmlspecialchars($row['pengarang']) ?></p>
                                            <p class="text-muted small mb-0">ISBN: <?= htmlspecialchars($row['isbn']) ?></p>
                                        </div>
                                        <span class="badge <?= $row['status_jatuh_tempo'] === 'segera_jatuh_tempo' ? 'bg-warning' : 'bg-success' ?>">
                                            <?= $row['status_jatuh_tempo'] === 'segera_jatuh_tempo' ? 'Segera Jatuh Tempo' : 'Tepat Waktu' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Anggota</small>
                                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($row['nama']) ?></p>
                                            <small class="text-muted">NIK: <?= htmlspecialchars($row['nik']) ?></small>
                                        </div>
                                        <div class="col-6 text-end">
                                            <small class="text-muted">Batas Kembali</small>
                                            <p class="mb-0 fw-semibold"><?= formatTanggal($row['tanggal_kembali']) ?></p>
                                            <small class="text-muted">
                                                <?php 
                                                $days_left = (strtotime($row['tanggal_kembali']) - strtotime(date('Y-m-d'))) / (60*60*24);
                                                echo $days_left > 0 ? $days_left . ' hari lagi' : 'Hari ini';
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-primary-modern w-100" 
                                            onclick="openPerpanjangModal(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars($row['judul']) ?>', '<?= htmlspecialchars($row['nama']) ?>', '<?= $row['tanggal_kembali'] ?>')">
                                        <i class="fas fa-calendar-plus me-2"></i>Perpanjang Peminjaman
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Tidak ada peminjaman yang dapat diperpanjang</p>
                    <?php if (!empty($_GET['search'])): ?>
                        <a href="perpanjangan.php" class="btn btn-primary-modern btn-sm">
                            <i class="fas fa-redo me-2"></i>Reset Pencarian
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Tidak Bisa Diperpanjang -->
        <div class="tab-pane fade" id="cannot-extend" role="tabpanel">
            <?php if (!empty($cannot_extend)): ?>
                <div class="modern-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Tgl Pinjam</th>
                                        <th>Batas Kembali</th>
                                        <th>Terlambat</th>
                                        <th>Est. Denda</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cannot_extend as $row): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                                <small class="text-muted">NIK: <?= htmlspecialchars($row['nik']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-medium"><?= htmlspecialchars(mb_substr($row['judul'], 0, 25)) ?><?= mb_strlen($row['judul']) > 25 ? '...' : '' ?></div>
                                                <small class="text-muted"><?= htmlspecialchars(mb_substr($row['pengarang'], 0, 20)) ?><?= mb_strlen($row['pengarang']) > 20 ? '...' : '' ?></small>
                                            </td>
                                            <td><?= formatTanggal($row['tanggal_pinjam']) ?></td>
                                            <td>
                                                <div class="text-danger fw-bold"><?= formatTanggal($row['tanggal_kembali']) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?= $row['hari_terlambat'] ?> hari
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-danger fw-bold">
                                                    <?= formatRupiah($row['estimasi_denda']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="pengembalian.php?id=<?= $row['id_peminjaman'] ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-undo me-1"></i>Kembalikan
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-success">Tidak ada peminjaman yang terlambat</p>
                    <p class="text-muted small">Semua anggota mengembalikan buku tepat waktu</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Perpanjangan -->
<div class="modal fade" id="modalPerpanjang" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modern-card">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-plus me-2"></i>Perpanjang Peminjaman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPerpanjang">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id_peminjaman" id="modal_id_peminjaman">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <div><strong>Buku:</strong> <span id="modal_judul_buku"></span></div>
                        <div><strong>Peminjam:</strong> <span id="modal_nama_anggota"></span></div>
                        <div><strong>Batas Kembali Lama:</strong> <span id="modal_batas_lama"></span></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Tanggal Kembali Baru <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_kembali_baru" id="tanggal_kembali_baru" 
                               class="form-control-modern" required
                               onchange="calculateExtension()">
                        <div class="form-text">
                            Maksimal perpanjangan <?= $max_days ?> hari dari tanggal kembali lama
                        </div>
                    </div>
                    
                    <div id="extension_info" class="alert alert-warning" style="display: none;">
                        <div><strong>Lama Perpanjangan:</strong> <span id="hari_perpanjangan">0</span> hari</div>
                        <div><strong>Batas Maksimal:</strong> <?= $max_days ?> hari</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label-modern">Alasan Perpanjangan <small class="text-muted">(opsional)</small></label>
                        <textarea name="alasan_perpanjangan" class="form-control-modern" rows="3" 
                                  placeholder="Masukkan alasan perpanjangan (misalnya: butuh waktu lebih untuk studi, dll)..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Perhatian:</strong> Perpanjangan hanya bisa dilakukan sekali per peminjaman.
                            Setelah diperpanjang, tidak dapat diperpanjang lagi.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="perpanjang_peminjaman" class="btn btn-primary-modern">
                        <i class="fas fa-save me-2"></i>Simpan Perpanjangan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let extensionData = {
    oldDueDate: '',
    maxDays: <?= $max_days ?>
};

// Fungsi untuk membuka modal perpanjangan
function openPerpanjangModal(id, judul, nama, tanggalKembali) {
    // Set nilai form
    document.getElementById('modal_id_peminjaman').value = id;
    document.getElementById('modal_judul_buku').textContent = judul;
    document.getElementById('modal_nama_anggota').textContent = nama;
    document.getElementById('modal_batas_lama').textContent = formatDate(tanggalKembali);
    
    // Simpan data
    extensionData.oldDueDate = tanggalKembali;
    
    // Set min date (besok) dan max date (max_days dari oldDueDate)
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const maxDate = new Date(tanggalKembali);
    maxDate.setDate(maxDate.getDate() + extensionData.maxDays);
    
    const dateInput = document.getElementById('tanggal_kembali_baru');
    dateInput.min = tomorrow.toISOString().split('T')[0];
    dateInput.max = maxDate.toISOString().split('T')[0];
    
    // Reset value
    dateInput.value = '';
    
    // Sembunyikan info perpanjangan
    document.getElementById('extension_info').style.display = 'none';
    
    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('modalPerpanjang'));
    modal.show();
}

// Fungsi untuk menghitung lama perpanjangan
function calculateExtension() {
    const newDate = document.getElementById('tanggal_kembali_baru').value;
    
    if (!newDate || !extensionData.oldDueDate) return;
    
    const oldDate = new Date(extensionData.oldDueDate);
    const newDateObj = new Date(newDate);
    
    // Hitung selisih hari
    const diffTime = newDateObj - oldDate;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    // Tampilkan info
    if (diffDays > 0) {
        document.getElementById('hari_perpanjangan').textContent = diffDays;
        
        // Validasi: tidak boleh lebih dari max days
        if (diffDays > extensionData.maxDays) {
            document.getElementById('extension_info').className = 'alert alert-danger';
            document.getElementById('extension_info').innerHTML = `
                <div><strong>Error:</strong> Perpanjangan ${diffDays} hari melebihi batas maksimal ${extensionData.maxDays} hari</div>
                <div><strong>Saran:</strong> Pilih tanggal sebelum ${formatDate(addDays(extensionData.oldDueDate, extensionData.maxDays))}</div>
            `;
        } else {
            document.getElementById('extension_info').className = 'alert alert-warning';
            document.getElementById('extension_info').innerHTML = `
                <div><strong>Lama Perpanjangan:</strong> ${diffDays} hari</div>
                <div><strong>Batas Maksimal:</strong> ${extensionData.maxDays} hari</div>
                <div><strong>Batas Baru:</strong> ${formatDate(newDate)}</div>
            `;
        }
        
        document.getElementById('extension_info').style.display = 'block';
    } else {
        document.getElementById('extension_info').style.display = 'none';
    }
}

// Fungsi helper untuk format tanggal
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

// Fungsi helper untuk tambah hari
function addDays(dateString, days) {
    const date = new Date(dateString);
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];
}

// Event listener untuk input tanggal
document.getElementById('tanggal_kembali_baru')?.addEventListener('change', calculateExtension);
</script>

<?php include '../../includes/footer.php'; ?>