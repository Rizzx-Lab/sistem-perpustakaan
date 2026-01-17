<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);
$page_title = 'Laporan Kategori';
include '../../config/database.php';

// Get filter parameters
$filter = [
    'search' => $_GET['search'] ?? '',
    'min_buku' => $_GET['min_buku'] ?? 0,
    'sort_by' => $_GET['sort_by'] ?? 'jumlah_buku',
    'sort_order' => $_GET['sort_order'] ?? 'desc'
];

// Build query for categories with statistics
$where = [];
$params = [];

if (!empty($filter['search'])) {
    $where[] = "(k.nama_kategori LIKE ? OR k.deskripsi LIKE ?)";
    $search_term = "%{$filter['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get categories with detailed statistics
$query = "
    SELECT 
        k.id_kategori,
        k.nama_kategori,
        k.deskripsi,
        DATE(k.created_at) as tanggal_dibuat,
        COUNT(DISTINCT bk.isbn) as jumlah_buku,
        COUNT(DISTINCT bk2.isbn) as total_judul,
        COALESCE(SUM(b.stok_total), 0) as total_eksemplar,
        COALESCE(SUM(b.stok_tersedia), 0) as stok_tersedia,
        COALESCE(COUNT(DISTINCT p.id_peminjaman), 0) as total_pinjam,
        COALESCE(COUNT(DISTINCT CASE WHEN p.status = 'dipinjam' THEN p.id_peminjaman END), 0) as sedang_dipinjam,
        COALESCE(ROUND(AVG(b.tahun_terbit)), 0) as rata_rata_tahun,
        COALESCE(MIN(b.tahun_terbit), 0) as tahun_tertua,
        COALESCE(MAX(b.tahun_terbit), 0) as tahun_terbaru
    FROM kategori k
    LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
    LEFT JOIN buku b ON bk.isbn = b.isbn
    LEFT JOIN buku_kategori bk2 ON k.id_kategori = bk2.id_kategori
    LEFT JOIN peminjaman p ON b.isbn = p.isbn
    $where_clause
    GROUP BY k.id_kategori, k.nama_kategori, k.deskripsi, k.created_at
    HAVING jumlah_buku >= ?
    ORDER BY {$filter['sort_by']} {$filter['sort_order']}
";

$params[] = (int)$filter['min_buku'];
$stmt = $conn->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT k.id_kategori) 
    FROM kategori k
    LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
    $where_clause
    HAVING COUNT(DISTINCT bk.isbn) >= ?
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([...$params]);
$total_categories = $count_stmt->fetchColumn() ?? 0;

// Get top categories for borrowing
$top_categories_borrow = $conn->query("
    SELECT 
        k.nama_kategori,
        COUNT(p.id_peminjaman) as total_pinjam,
        ROUND(COUNT(p.id_peminjaman) * 100.0 / (SELECT COUNT(*) FROM peminjaman WHERE YEAR(tanggal_pinjam) = YEAR(CURDATE())), 1) as persentase
    FROM kategori k
    LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
    LEFT JOIN peminjaman p ON bk.isbn = p.isbn AND YEAR(p.tanggal_pinjam) = YEAR(CURDATE())
    GROUP BY k.id_kategori
    ORDER BY total_pinjam DESC
    LIMIT 5
")->fetchAll();

// Calculate statistics
$stats = [
    'total_kategori' => $total_categories,
    'total_buku' => array_sum(array_column($categories, 'jumlah_buku')),
    'total_eksemplar' => array_sum(array_column($categories, 'total_eksemplar')),
    'total_pinjam' => array_sum(array_column($categories, 'total_pinjam')),
    'rata_buku_per_kategori' => $total_categories > 0 ? round(array_sum(array_column($categories, 'jumlah_buku')) / $total_categories, 1) : 0,
    'kategori_tanpa_buku' => count(array_filter($categories, fn($c) => $c['jumlah_buku'] == 0))
];

// Prepare data for chart
$chart_labels = array_column($categories, 'nama_kategori');
$chart_data_books = array_column($categories, 'jumlah_buku');
$chart_data_borrow = array_column($categories, 'total_pinjam');

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

/* List group */
.list-group-item {
    border-left: none;
    border-right: none;
    padding: 12px 15px;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

/* Chart container */
canvas {
    max-height: 200px;
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
    
    .row.g-3, .row.g-4 {
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
    
    .col-md-3, .col-md-2, .col-md-4, .col-md-6, .col-md-8, .col-md-12 {
        margin-bottom: 0.5rem;
    }
    
    canvas {
        max-height: 150px;
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
    
    .d-flex.gap-2 .btn-modern {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .form-label-modern {
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
    
    .row.g-4 .col-md-6 {
        margin-bottom: 1rem;
    }
}

@media print {
    .btn-modern, .modern-card:not(.table-responsive) {
        display: none !important;
    }
    .table-modern {
        font-size: 8px;
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
                    <li class="breadcrumb-item active">Kategori</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">🏷️</span>
                <span class="title-gradient">Laporan Kategori</span>
            </h1>
            <p class="text-muted mb-0">Distribusi dan analisis buku berdasarkan kategori</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?type=kategori" class="btn btn-modern btn-success">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-modern btn-primary">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-tags fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary"><?= $stats['total_kategori'] ?></div>
                    <small class="stat-label">Total Kategori</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success"><?= $stats['total_buku'] ?></div>
                    <small class="stat-label">Judul Buku</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-copy fa-2x text-info"></i>
                    </div>
                    <div class="stat-number text-info"><?= $stats['total_eksemplar'] ?></div>
                    <small class="stat-label">Total Eksemplar</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning"><?= $stats['total_pinjam'] ?></div>
                    <small class="stat-label">Total Peminjaman</small>
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
                <div class="col-md-4">
                    <label class="form-label-modern">Cari Kategori</label>
                    <input type="text" 
                           name="search" 
                           class="form-control-modern" 
                           placeholder="Nama kategori atau deskripsi..." 
                           value="<?= htmlspecialchars($filter['search']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Minimal Jumlah Buku</label>
                    <select name="min_buku" class="form-control-modern">
                        <option value="0">Semua Kategori</option>
                        <option value="1" <?= $filter['min_buku'] == 1 ? 'selected' : '' ?>>≥ 1 buku</option>
                        <option value="5" <?= $filter['min_buku'] == 5 ? 'selected' : '' ?>>≥ 5 buku</option>
                        <option value="10" <?= $filter['min_buku'] == 10 ? 'selected' : '' ?>>≥ 10 buku</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Urutkan Berdasarkan</label>
                    <select name="sort_by" class="form-control-modern">
                        <option value="nama_kategori" <?= $filter['sort_by'] === 'nama_kategori' ? 'selected' : '' ?>>Nama Kategori</option>
                        <option value="jumlah_buku" <?= $filter['sort_by'] === 'jumlah_buku' ? 'selected' : '' ?>>Jumlah Buku</option>
                        <option value="total_pinjam" <?= $filter['sort_by'] === 'total_pinjam' ? 'selected' : '' ?>>Total Peminjaman</option>
                        <option value="total_eksemplar" <?= $filter['sort_by'] === 'total_eksemplar' ? 'selected' : '' ?>>Total Eksemplar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Urutan</label>
                    <select name="sort_order" class="form-control-modern">
                        <option value="desc" <?= $filter['sort_order'] === 'desc' ? 'selected' : '' ?>>Tertinggi ↓</option>
                        <option value="asc" <?= $filter['sort_order'] === 'asc' ? 'selected' : '' ?>>Terendah ↑</option>
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-modern btn-primary">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                    <a href="kategori.php" class="btn btn-modern btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Distribusi Buku per Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="booksChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-fire me-2"></i>Kategori Terpopuler (Tahun Ini)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_categories_borrow as $index => $category): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2">#<?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars($category['nama_kategori']) ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-primary"><?= $category['total_pinjam'] ?> pinjaman</div>
                                        <small class="text-muted"><?= $category['persentase'] ?>% dari total</small>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-primary" 
                                         style="width: <?= min($category['persentase'], 100) ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-list me-2"></i>Detail Kategori
                    <small class="text-muted">(<?= count($categories) ?> kategori ditemukan)</small>
                </h5>
            </div>
            <div class="text-muted">
                Dicetak: <?= date('d/m/Y H:i') ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada kategori ditemukan</h5>
                    <p class="text-muted">Coba ubah kriteria pencarian</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Kategori</th>
                                <th class="text-center">Deskripsi</th>
                                <th class="text-center">Jumlah Buku</th>
                                <th class="text-center">Total Eksemplar</th>
                                <th class="text-center">Stok Tersedia</th>
                                <th class="text-center">Total Pinjam</th>
                                <th class="text-center">Sedang Dipinjam</th>
                                <th class="text-center">Rata-rata Tahun</th>
                                <th class="text-center">Analisis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $index => $cat): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($cat['nama_kategori']) ?></strong>
                                        <br>
                                        <small class="text-muted">Dibuat: <?= date('d/m/Y', strtotime($cat['tanggal_dibuat'])) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars(substr($cat['deskripsi'] ?? '-', 0, 50)) ?>...</small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['jumlah_buku'] > 0): ?>
                                            <span class="badge bg-primary"><?= $cat['jumlah_buku'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['total_eksemplar'] > 0): ?>
                                            <span class="badge bg-info"><?= $cat['total_eksemplar'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['stok_tersedia'] > 0): ?>
                                            <span class="badge bg-success"><?= $cat['stok_tersedia'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= $cat['stok_tersedia'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['total_pinjam'] > 0): ?>
                                            <span class="badge bg-warning"><?= $cat['total_pinjam'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['sedang_dipinjam'] > 0): ?>
                                            <span class="badge bg-purple"><?= $cat['sedang_dipinjam'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?php if ($cat['rata_rata_tahun'] > 0): ?>
                                                <?= $cat['rata_rata_tahun'] ?>
                                                <br>
                                                <small><?= $cat['tahun_tertua'] ?> - <?= $cat['tahun_terbaru'] ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $availability = $cat['total_eksemplar'] > 0 ? ($cat['stok_tersedia'] / $cat['total_eksemplar']) * 100 : 0;
                                        $popularity = $cat['total_pinjam'] > 0 ? 'Populer' : 'Jarang';
                                        
                                        if ($cat['jumlah_buku'] == 0) {
                                            echo '<span class="badge bg-secondary">Kosong</span>';
                                        } elseif ($availability < 20) {
                                            echo '<span class="badge bg-danger">Stok Rendah</span>';
                                        } elseif ($cat['total_pinjam'] > 10) {
                                            echo '<span class="badge bg-success">Sangat Populer</span>';
                                        } else {
                                            echo '<span class="badge bg-info">Normal</span>';
                                        }
                                        ?>
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
    <?php if (!empty($categories)): ?>
        <div class="row g-3 mt-4">
            <div class="col-md-12">
                <div class="modern-card">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb me-2"></i>Analisis dan Rekomendasi</h6>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted">Rata-rata buku per kategori:</small>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?= min($stats['rata_buku_per_kategori'] * 10, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= $stats['rata_buku_per_kategori'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted">Kategori tanpa buku:</small>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?= min($stats['kategori_tanpa_buku'] * 20, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= $stats['kategori_tanpa_buku'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted">Tingkat sirkulasi:</small>
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $circulation_rate = $stats['total_eksemplar'] > 0 ? ($stats['total_pinjam'] / $stats['total_eksemplar']) * 100 : 0;
                                        $rate_class = $circulation_rate > 50 ? 'bg-success' : ($circulation_rate > 20 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar <?= $rate_class ?>" 
                                                 style="width: <?= min($circulation_rate, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= round($circulation_rate, 1) ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats['kategori_tanpa_buku'] > 0): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                                <p class="mb-0">Ada <?= $stats['kategori_tanpa_buku'] ?> kategori yang tidak memiliki buku.</p>
                                <small>Pertimbangkan untuk menghapus kategori kosong atau menambahkan buku.</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $low_stock_cats = array_filter($categories, fn($c) => $c['stok_tersedia'] < 3 && $c['stok_tersedia'] > 0);
                        if (count($low_stock_cats) > 0): 
                        ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-boxes me-2"></i>Stok Rendah!</h6>
                                <p class="mb-2"><?= count($low_stock_cats) ?> kategori memiliki stok tersedia rendah:</p>
                                <ul class="mb-0">
                                    <?php foreach ($low_stock_cats as $cat): ?>
                                        <li><strong><?= $cat['nama_kategori'] ?></strong>: Hanya <?= $cat['stok_tersedia'] ?> eksemplar tersedia</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $popular_cats = array_slice($categories, 0, 3);
                        if (!empty($popular_cats)): 
                        ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-chart-line me-2"></i>Kategori Populer</h6>
                                <p class="mb-2">Top 3 kategori berdasarkan peminjaman:</p>
                                <div class="row">
                                    <?php foreach ($popular_cats as $cat): ?>
                                        <div class="col-md-4">
                                            <div class="border p-2 rounded">
                                                <strong><?= $cat['nama_kategori'] ?></strong>
                                                <div class="small text-muted">
                                                    <?= $cat['total_pinjam'] ?> pinjaman • 
                                                    <?= round(($cat['total_pinjam'] / $stats['total_pinjam']) * 100, 1) ?>% dari total
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Books Distribution Chart
const booksCtx = document.getElementById('booksChart').getContext('2d');
new Chart(booksCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_slice($chart_labels, 0, 8)) ?>,
        datasets: [{
            label: 'Jumlah Buku',
            data: <?= json_encode(array_slice($chart_data_books, 0, 8)) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }, {
            label: 'Total Peminjaman',
            data: <?= json_encode(array_slice($chart_data_borrow, 0, 8)) ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.7)',
            borderColor: 'rgb(255, 99, 132)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Jumlah Buku'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Total Peminjaman'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>