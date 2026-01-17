<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

$page_title = 'Laporan Anggota';
include '../../config/database.php';

// Get filter parameters
$filter = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '', // aktif, tidak_aktif, denda
    'sort_by' => $_GET['sort_by'] ?? 'total_pinjam',
    'sort_order' => $_GET['sort_order'] ?? 'desc',
    'limit' => $_GET['limit'] ?? 50,
    'tgl_daftar_dari' => $_GET['tgl_daftar_dari'] ?? '',
    'tgl_daftar_sampai' => $_GET['tgl_daftar_sampai'] ?? ''
];

// Build query
$where = [];
$params = [];

if (!empty($filter['search'])) {
    $where[] = "(a.nama LIKE ? OR a.nik LIKE ? OR a.no_hp LIKE ?)";
    $search_term = "%{$filter['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filter['tgl_daftar_dari']) && !empty($filter['tgl_daftar_sampai'])) {
    $where[] = "DATE(a.created_at) BETWEEN ? AND ?";
    $params[] = $filter['tgl_daftar_dari'];
    $params[] = $filter['tgl_daftar_sampai'];
} elseif (!empty($filter['tgl_daftar_dari'])) {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $filter['tgl_daftar_dari'];
} elseif (!empty($filter['tgl_daftar_sampai'])) {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $filter['tgl_daftar_sampai'];
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get members with statistics
$query = "
    SELECT 
        a.nik,
        a.nama,
        a.no_hp,
        a.alamat,
        DATE(a.created_at) as tanggal_daftar,
        DATEDIFF(CURDATE(), a.created_at) as hari_bergabung,
        COALESCE(COUNT(DISTINCT p.id_peminjaman), 0) as total_pinjam,
        COALESCE(SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END), 0) as sedang_dipinjam,
        COALESCE(SUM(CASE WHEN p.status = 'dikembalikan' THEN 1 ELSE 0 END), 0) as sudah_kembali,
        COALESCE(SUM(pg.denda), 0) as total_denda,
        COALESCE(SUM(CASE WHEN pg.denda > 0 THEN 1 ELSE 0 END), 0) as total_denda_transaksi,
        MAX(p.tanggal_pinjam) as terakhir_pinjam
    FROM anggota a
    LEFT JOIN peminjaman p ON a.nik = p.nik
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
    $where_clause
    GROUP BY a.nik, a.nama, a.no_hp, a.alamat, a.created_at
    ORDER BY {$filter['sort_by']} {$filter['sort_order']}
    LIMIT ?
";

$params[] = (int)$filter['limit'];
$stmt = $conn->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get total count
$count_query = "SELECT COUNT(*) FROM anggota a $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute(array_slice($params, 0, -1));
$total_members = $count_stmt->fetchColumn();

// Calculate statistics
$stats = [
    'total_anggota' => $total_members,
    'total_pinjam' => array_sum(array_column($members, 'total_pinjam')),
    'sedang_dipinjam' => array_sum(array_column($members, 'sedang_dipinjam')),
    'total_denda' => array_sum(array_column($members, 'total_denda')),
    'anggota_denda' => count(array_filter($members, fn($m) => $m['total_denda'] > 0)),
    'anggota_aktif' => count(array_filter($members, fn($m) => $m['total_pinjam'] > 0)),
    'anggota_baru' => count(array_filter($members, fn($m) => $m['hari_bergabung'] <= 30))
];

// Apply additional filters
if ($filter['status'] === 'aktif') {
    $members = array_filter($members, fn($m) => $m['total_pinjam'] > 0);
} elseif ($filter['status'] === 'tidak_aktif') {
    $members = array_filter($members, fn($m) => $m['total_pinjam'] == 0);
} elseif ($filter['status'] === 'denda') {
    $members = array_filter($members, fn($m) => $m['total_denda'] > 0);
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
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-label-modern {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
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

/* Perbaikan untuk form-text */
.form-text {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6c757d;
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

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk table modern */
.table-modern {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    margin: 0;
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.table-modern td {
    padding: 10px 15px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
}

.table-modern tr:hover td {
    background-color: #f8f9fa;
}

.table-modern tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

/* Perbaikan untuk title gradient */
.title-gradient {
    background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logo-emoji {
    margin-right: 10px;
    font-size: 1.5rem;
}

/* Statistics cards */
.modern-card.text-center {
    padding: 15px;
}

.stat-icon {
    margin-bottom: 8px;
}

.stat-icon i {
    font-size: 1.8rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.text-purple {
    color: #6f42c1 !important;
}

/* Progress bar */
.progress {
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
    font-size: 0.75rem;
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
        font-size: 0.9rem;
    }
    
    .row.g-3 {
        --bs-gutter-y: 1rem;
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
    
    .badge {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-icon i {
        font-size: 1.5rem;
    }
    
    .col-md-2 {
        margin-bottom: 0.5rem;
    }
}

@media (max-width: 576px) {
    .container-fluid {
        padding-left: 12px;
        padding-right: 12px;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .d-flex.gap-2 .btn {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .col-md-3, .col-md-2, .col-md-1, .col-md-7 {
        margin-bottom: 0.5rem;
    }
    
    .form-label {
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
}

@media print {
    .btn, .modern-card:not(.table-responsive) {
        display: none !important;
    }
    .table-modern {
        font-size: 9px;
    }
    .container-fluid {
        padding: 0 !important;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="./"><i class="fas fa-chart-bar"></i> Laporan</a>
                    </li>
                    <li class="breadcrumb-item active">Anggota</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">👥</span>
                <span class="title-gradient">Laporan Anggota</span>
            </h1>
            <p class="text-muted mb-0">Statistik dan aktivitas anggota perpustakaan</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?type=anggota" class="btn btn-modern btn-success">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-modern btn-primary">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary"><?= $stats['total_anggota'] ?></div>
                    <small class="stat-label">Total Anggota</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-user-check fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success"><?= $stats['anggota_aktif'] ?></div>
                    <small class="stat-label">Anggota Aktif</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book fa-2x text-info"></i>
                    </div>
                    <div class="stat-number text-info"><?= $stats['total_pinjam'] ?></div>
                    <small class="stat-label">Total Peminjaman</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book-reader fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning"><?= $stats['sedang_dipinjam'] ?></div>
                    <small class="stat-label">Sedang Dipinjam</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                    </div>
                    <div class="stat-number text-danger"><?= formatRupiah($stats['total_denda']) ?></div>
                    <small class="stat-label">Total Denda</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus fa-2x text-purple"></i>
                    </div>
                    <div class="stat-number text-purple"><?= $stats['anggota_baru'] ?></div>
                    <small class="stat-label">Anggota Baru (30 hari)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-filter me-2"></i>Filter & Pencarian
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">Cari Anggota</label>
                    <input type="text" 
                           name="search" 
                           class="form-control-modern" 
                           placeholder="Nama, NIK, No. HP..." 
                           value="<?= htmlspecialchars($filter['search']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Status</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $filter['status'] === 'aktif' ? 'selected' : '' ?>>Aktif (pernah pinjam)</option>
                        <option value="tidak_aktif" <?= $filter['status'] === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                        <option value="denda" <?= $filter['status'] === 'denda' ? 'selected' : '' ?>>Punya Denda</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Tanggal Daftar Dari</label>
                    <input type="date" 
                           name="tgl_daftar_dari" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filter['tgl_daftar_dari']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Tanggal Daftar Sampai</label>
                    <input type="date" 
                           name="tgl_daftar_sampai" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filter['tgl_daftar_sampai']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Urutkan Berdasarkan</label>
                    <select name="sort_by" class="form-control-modern">
                        <option value="nama" <?= $filter['sort_by'] === 'nama' ? 'selected' : '' ?>>Nama</option>
                        <option value="total_pinjam" <?= $filter['sort_by'] === 'total_pinjam' ? 'selected' : '' ?>>Total Pinjam</option>
                        <option value="total_denda" <?= $filter['sort_by'] === 'total_denda' ? 'selected' : '' ?>>Total Denda</option>
                        <option value="created_at" <?= $filter['sort_by'] === 'created_at' ? 'selected' : '' ?>>Tanggal Daftar</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label-modern">Urutan</label>
                    <select name="sort_order" class="form-control-modern">
                        <option value="desc" <?= $filter['sort_order'] === 'desc' ? 'selected' : '' ?>>↓ Tertinggi</option>
                        <option value="asc" <?= $filter['sort_order'] === 'asc' ? 'selected' : '' ?>>↑ Terendah</option>
                    </select>
                </div>
                <div class="col-md-7 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-modern btn-primary">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                    <a href="anggota.php" class="btn btn-modern btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-list me-2"></i>Daftar Anggota
                    <small class="text-muted">(<?= count($members) ?> anggota ditemukan)</small>
                </h5>
            </div>
            <div class="text-muted">
                Dicetak: <?= date('d/m/Y H:i') ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada anggota ditemukan</h5>
                    <p class="text-muted">Coba ubah kriteria pencarian</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>NIK</th>
                                <th>Nama Anggota</th>
                                <th>Kontak</th>
                                <th class="text-center">Tanggal Daftar</th>
                                <th class="text-center">Total Pinjam</th>
                                <th class="text-center">Sedang Pinjam</th>
                                <th class="text-center">Terakhir Pinjam</th>
                                <th class="text-center">Total Denda</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($member['nik']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($member['nama']) ?></strong>
                                        <br>
                                        <small class="text-muted">Bergabung <?= $member['hari_bergabung'] ?> hari lalu</small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($member['no_hp'] ?? '-') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars(substr($member['alamat'], 0, 30)) ?>...</small>
                                    </td>
                                    <td class="text-center">
                                        <?= date('d/m/Y', strtotime($member['tanggal_daftar'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($member['total_pinjam'] > 0): ?>
                                            <span class="badge bg-success"><?= $member['total_pinjam'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($member['sedang_dipinjam'] > 0): ?>
                                            <span class="badge bg-warning"><?= $member['sedang_dipinjam'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($member['terakhir_pinjam']): ?>
                                            <small><?= date('d/m/Y', strtotime($member['terakhir_pinjam'])) ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Belum pernah</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($member['total_denda'] > 0): ?>
                                            <span class="badge bg-danger" title="Dari <?= $member['total_denda_transaksi'] ?> transaksi">
                                                <?= formatRupiah($member['total_denda']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($member['total_pinjam'] == 0): ?>
                                            <span class="badge bg-secondary">Tidak Aktif</span>
                                        <?php elseif ($member['hari_bergabung'] <= 30): ?>
                                            <span class="badge bg-info">Baru</span>
                                        <?php elseif ($member['total_denda'] > 0): ?>
                                            <span class="badge bg-danger">Punya Denda</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Analysis Section -->
    <?php if (!empty($members)): ?>
        <div class="row g-3 mt-4">
            <div class="col-md-6">
                <div class="modern-card">
                    <div class="card-body">
                        <h6><i class="fas fa-chart-pie me-2"></i>Distribusi Aktivitas</h6>
                        <div class="mt-3">
                            <?php
                            $active_count = count(array_filter($members, fn($m) => $m['total_pinjam'] > 0));
                            $inactive_count = count(array_filter($members, fn($m) => $m['total_pinjam'] == 0));
                            $fine_count = count(array_filter($members, fn($m) => $m['total_denda'] > 0));
                            ?>
                            <div class="mb-3">
                                <small class="text-muted">Anggota Aktif:</small>
                                <div class="progress">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= $active_count / count($members) * 100 ?>%">
                                        <?= $active_count ?> anggota (<?= round($active_count / count($members) * 100) ?>%)
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Anggota Tidak Aktif:</small>
                                <div class="progress">
                                    <div class="progress-bar bg-secondary" 
                                         style="width: <?= $inactive_count / count($members) * 100 ?>%">
                                        <?= $inactive_count ?> anggota (<?= round($inactive_count / count($members) * 100) ?>%)
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Anggota dengan Denda:</small>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" 
                                         style="width: <?= $fine_count / count($members) * 100 ?>%">
                                        <?= $fine_count ?> anggota (<?= round($fine_count / count($members) * 100) ?>%)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="modern-card">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb me-2"></i>Rekomendasi</h6>
                        <div class="mt-3">
                            <?php 
                            $inactive_members = array_filter($members, fn($m) => $m['total_pinjam'] == 0);
                            if (count($inactive_members) > 0): 
                            ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="fas fa-user-clock me-2"></i>Anggota Tidak Aktif</h6>
                                    <p class="mb-2"><?= count($inactive_members) ?> anggota belum pernah meminjam buku.</p>
                                    <small>Pertimbangkan untuk mengirim reminder atau promo.</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $members_with_fines = array_filter($members, fn($m) => $m['total_denda'] > 0);
                            if (count($members_with_fines) > 0): 
                            ?>
                                <div class="alert alert-danger mb-3">
                                    <h6><i class="fas fa-money-bill-wave me-2"></i>Penagihan Denda</h6>
                                    <p class="mb-2"><?= count($members_with_fines) ?> anggota memiliki denda belum lunas.</p>
                                    <small>Total denda tertunggak: <?= formatRupiah(array_sum(array_column($members_with_fines, 'total_denda'))) ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($stats['anggota_baru'] > 0): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-user-plus me-2"></i>Anggota Baru</h6>
                                    <p class="mb-2"><?= $stats['anggota_baru'] ?> anggota baru bergabung dalam 30 hari terakhir.</p>
                                    <small>Sambut mereka dengan welcome package atau promo khusus.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>