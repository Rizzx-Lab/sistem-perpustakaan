<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

$page_title = 'Laporan Penerbit';
include '../../config/database.php';

// Get filter parameters
$filter = [
    'search' => $_GET['search'] ?? '',
    'min_buku' => $_GET['min_buku'] ?? 0,
    'sort_by' => $_GET['sort_by'] ?? 'jumlah_buku',
    'sort_order' => $_GET['sort_order'] ?? 'desc',
    'tahun_dari' => $_GET['tahun_dari'] ?? '',
    'tahun_sampai' => $_GET['tahun_sampai'] ?? ''
];

// Build query for publishers with statistics
$where = [];
$params = [];

if (!empty($filter['search'])) {
    $where[] = "(p.nama_penerbit LIKE ? OR p.alamat LIKE ? OR p.email LIKE ?)";
    $search_term = "%{$filter['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filter['tahun_dari']) && !empty($filter['tahun_sampai'])) {
    $where[] = "b.tahun_terbit BETWEEN ? AND ?";
    $params[] = $filter['tahun_dari'];
    $params[] = $filter['tahun_sampai'];
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get publishers with detailed statistics
$query = "
    SELECT 
        pn.id_penerbit,
        pn.nama_penerbit,
        pn.alamat,
        pn.telepon,
        pn.email,
        DATE(pn.created_at) as tanggal_ditambahkan,
        COUNT(DISTINCT b.isbn) as jumlah_buku,
        COALESCE(SUM(b.stok_total), 0) as total_eksemplar,
        COALESCE(SUM(b.stok_tersedia), 0) as stok_tersedia,
        COALESCE(COUNT(DISTINCT p.id_peminjaman), 0) as total_pinjam,
        COALESCE(COUNT(DISTINCT CASE WHEN p.status = 'dipinjam' THEN p.id_peminjaman END), 0) as sedang_dipinjam,
        COALESCE(ROUND(AVG(b.tahun_terbit)), 0) as rata_rata_tahun,
        COALESCE(MIN(b.tahun_terbit), 0) as tahun_tertua,
        COALESCE(MAX(b.tahun_terbit), 0) as tahun_terbaru,
        COALESCE(COUNT(DISTINCT bk.id_kategori), 0) as jumlah_kategori,
        GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') as kategori_terbanyak
    FROM penerbit pn
    LEFT JOIN buku b ON pn.id_penerbit = b.id_penerbit
    LEFT JOIN peminjaman p ON b.isbn = p.isbn
    LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
    LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
    $where_clause
    GROUP BY pn.id_penerbit, pn.nama_penerbit, pn.alamat, pn.telepon, pn.email, pn.created_at
    HAVING jumlah_buku >= ?
    ORDER BY {$filter['sort_by']} {$filter['sort_order']}
";

$params[] = (int)$filter['min_buku'];
$stmt = $conn->prepare($query);
$stmt->execute($params);
$publishers = $stmt->fetchAll();

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT pn.id_penerbit) 
    FROM penerbit pn
    LEFT JOIN buku b ON pn.id_penerbit = b.id_penerbit
    $where_clause
    HAVING COUNT(DISTINCT b.isbn) >= ?
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute([...$params]);
$total_publishers = $count_stmt->fetchColumn() ?? 0;

// Get top publishers for borrowing
$top_publishers_borrow = $conn->query("
    SELECT 
        pn.nama_penerbit,
        COUNT(p.id_peminjaman) as total_pinjam,
        ROUND(COUNT(p.id_peminjaman) * 100.0 / (SELECT COUNT(*) FROM peminjaman WHERE YEAR(tanggal_pinjam) = YEAR(CURDATE())), 1) as persentase
    FROM penerbit pn
    LEFT JOIN buku b ON pn.id_penerbit = b.id_penerbit
    LEFT JOIN peminjaman p ON b.isbn = p.isbn AND YEAR(p.tanggal_pinjam) = YEAR(CURDATE())
    GROUP BY pn.id_penerbit
    HAVING COUNT(p.id_peminjaman) > 0
    ORDER BY total_pinjam DESC
    LIMIT 5
")->fetchAll();

// Calculate statistics
$stats = [
    'total_penerbit' => $total_publishers,
    'total_buku' => array_sum(array_column($publishers, 'jumlah_buku')),
    'total_eksemplar' => array_sum(array_column($publishers, 'total_eksemplar')),
    'total_pinjam' => array_sum(array_column($publishers, 'total_pinjam')),
    'rata_buku_per_penerbit' => $total_publishers > 0 ? round(array_sum(array_column($publishers, 'jumlah_buku')) / $total_publishers, 1) : 0,
    'penerbit_tanpa_buku' => count(array_filter($publishers, fn($p) => $p['jumlah_buku'] == 0))
];

// Prepare data for chart
$chart_labels = array_column($publishers, 'nama_penerbit');
$chart_data_books = array_column($publishers, 'jumlah_buku');
$chart_data_borrow = array_column($publishers, 'total_pinjam');

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

/* Perbaikan untuk stat cards */
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Perbaikan untuk table */
.table-modern {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.table-modern td {
    padding: 12px 15px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.table-modern tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Perbaikan untuk form controls */
.form-control, .form-select {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

/* Perbaikan untuk tombol */
.btn {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
    border-color: #1e7e34;
    color: white;
}

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.85em;
}

/* Perbaikan untuk progress bar */
.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
}

/* Perbaikan untuk list group */
.list-group-item {
    padding: 12px 20px;
    border-left: 0;
    border-right: 0;
}

.list-group-item:first-child {
    border-top: 0;
}

.list-group-item:last-child {
    border-bottom: 0;
}

/* Perbaikan untuk alert */
.alert {
    border-radius: 8px;
    padding: 15px;
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

/* Perbaikan untuk title gradient */
.title-gradient {
    background: linear-gradient(45deg, #28a745, #20c997);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logo-emoji {
    font-size: 1.5rem;
    margin-right: 10px;
}

/* Perbaikan untuk spacing */
.mb-4 {
    margin-bottom: 1.5rem !important;
}

.g-3 {
    --bs-gutter-x: 1rem;
    --bs-gutter-y: 1rem;
}

.g-4 {
    --bs-gutter-x: 1.5rem;
    --bs-gutter-y: 1.5rem;
}

/* Perbaikan untuk text yang kepotong */
td small {
    word-break: break-word;
    overflow-wrap: break-word;
    line-height: 1.4;
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
    
    .table-modern th,
    .table-modern td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .btn {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .form-control, .form-select {
        padding: 8px 12px;
    }
}

@media (max-width: 576px) {
    .d-flex.justify-content-between.align-items-center {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 15px;
    }
    
    .d-flex.gap-2 {
        flex-wrap: wrap;
        gap: 10px !important;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .table-modern th,
    .table-modern td {
        padding: 6px 8px;
        font-size: 0.8rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">🏢</span>
                <span class="title-gradient">Laporan Penerbit</span>
            </h1>
            <p class="text-muted mb-0">Analisis koleksi buku berdasarkan penerbit</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?type=penerbit" class="btn btn-success">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Cetak
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-number text-primary"><?= $stats['total_penerbit'] ?></div>
                <small class="stat-label">Total Penerbit</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-number text-success"><?= $stats['total_buku'] ?></div>
                <small class="stat-label">Judul Buku</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-number text-info"><?= $stats['total_eksemplar'] ?></div>
                <small class="stat-label">Total Eksemplar</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-number text-warning"><?= $stats['total_pinjam'] ?></div>
                <small class="stat-label">Total Peminjaman</small>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-filter me-2"></i>Filter Data
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cari Penerbit</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Nama penerbit, alamat, atau email..." 
                           value="<?= htmlspecialchars($filter['search']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Minimal Jumlah Buku</label>
                    <select name="min_buku" class="form-select">
                        <option value="0">Semua Penerbit</option>
                        <option value="1" <?= $filter['min_buku'] == 1 ? 'selected' : '' ?>>≥ 1 buku</option>
                        <option value="5" <?= $filter['min_buku'] == 5 ? 'selected' : '' ?>>≥ 5 buku</option>
                        <option value="10" <?= $filter['min_buku'] == 10 ? 'selected' : '' ?>>≥ 10 buku</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun Terbit Dari</label>
                    <input type="number" name="tahun_dari" class="form-control" 
                           placeholder="2000" min="1900" max="<?= date('Y') ?>"
                           value="<?= htmlspecialchars($filter['tahun_dari']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tahun Terbit Sampai</label>
                    <input type="number" name="tahun_sampai" class="form-control" 
                           placeholder="<?= date('Y') ?>" min="1900" max="<?= date('Y') ?>"
                           value="<?= htmlspecialchars($filter['tahun_sampai']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Urutkan Berdasarkan</label>
                    <select name="sort_by" class="form-select">
                        <option value="nama_penerbit" <?= $filter['sort_by'] === 'nama_penerbit' ? 'selected' : '' ?>>Nama Penerbit</option>
                        <option value="jumlah_buku" <?= $filter['sort_by'] === 'jumlah_buku' ? 'selected' : '' ?>>Jumlah Buku</option>
                        <option value="total_pinjam" <?= $filter['sort_by'] === 'total_pinjam' ? 'selected' : '' ?>>Total Peminjaman</option>
                        <option value="rata_rata_tahun" <?= $filter['sort_by'] === 'rata_rata_tahun' ? 'selected' : '' ?>>Rata-rata Tahun</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Urutan</label>
                    <select name="sort_order" class="form-select">
                        <option value="desc" <?= $filter['sort_order'] === 'desc' ? 'selected' : '' ?>>Tertinggi ↓</option>
                        <option value="asc" <?= $filter['sort_order'] === 'asc' ? 'selected' : '' ?>>Terendah ↑</option>
                    </select>
                </div>
                <div class="col-md-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                    <a href="penerbit.php" class="btn btn-outline-secondary">
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
                        <i class="fas fa-chart-bar me-2"></i>Koleksi Buku per Penerbit
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="publishersChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-fire me-2"></i>Penerbit Terpopuler (Tahun Ini)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_publishers_borrow as $index => $publisher): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2">#<?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars($publisher['nama_penerbit']) ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-primary"><?= $publisher['total_pinjam'] ?> pinjaman</div>
                                        <small class="text-muted"><?= $publisher['persentase'] ?>% dari total</small>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-primary" 
                                         style="width: <?= min($publisher['persentase'], 100) ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_publishers_borrow)): ?>
                            <div class="list-group-item border-0 text-center py-4">
                                <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada data peminjaman tahun ini</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Publishers Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-list me-2"></i>Detail Penerbit
                    <small class="text-muted">(<?= count($publishers) ?> penerbit ditemukan)</small>
                </h5>
            </div>
            <div class="text-muted">
                Dicetak: <?= date('d/m/Y H:i') ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($publishers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada penerbit ditemukan</h5>
                    <p class="text-muted">Coba ubah kriteria pencarian</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Penerbit</th>
                                <th class="text-center">Kontak</th>
                                <th class="text-center">Jumlah Buku</th>
                                <th class="text-center">Total Eksemplar</th>
                                <th class="text-center">Stok Tersedia</th>
                                <th class="text-center">Total Pinjam</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Rata-rata Tahun</th>
                                <th class="text-center">Analisis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($publishers as $index => $pub): ?>
                                <tr>
                                    <td class="fw-bold"><?= $index + 1 ?></td>
                                    <td>
                                        <strong class="d-block mb-1"><?= htmlspecialchars($pub['nama_penerbit']) ?></strong>
                                        <small class="text-muted d-block">
                                            Ditambahkan: <?= date('d/m/Y', strtotime($pub['tanggal_ditambahkan'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="mb-1">
                                            <small class="d-block">
                                                <i class="fas fa-phone text-muted me-1"></i>
                                                <?= htmlspecialchars($pub['telepon'] ?? '-') ?>
                                            </small>
                                        </div>
                                        <div class="mb-1">
                                            <small class="d-block">
                                                <i class="fas fa-envelope text-muted me-1"></i>
                                                <?= htmlspecialchars($pub['email'] ?? '-') ?>
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                <?= htmlspecialchars(substr($pub['alamat'] ?? '-', 0, 40)) ?>...
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pub['jumlah_buku'] > 0): ?>
                                            <span class="badge bg-primary"><?= $pub['jumlah_buku'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pub['total_eksemplar'] > 0): ?>
                                            <span class="badge bg-info"><?= $pub['total_eksemplar'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pub['stok_tersedia'] > 0): ?>
                                            <span class="badge bg-success"><?= $pub['stok_tersedia'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= $pub['stok_tersedia'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($pub['total_pinjam'] > 0): ?>
                                            <span class="badge bg-warning"><?= $pub['total_pinjam'] ?></span>
                                            <br>
                                            <small class="text-muted">(<?= $pub['sedang_dipinjam'] ?> dipinjam)</small>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted d-block">
                                            <?php if ($pub['jumlah_kategori'] > 0): ?>
                                                <span class="badge bg-dark"><?= $pub['jumlah_kategori'] ?> kat</span>
                                                <br>
                                                <small><?= htmlspecialchars(substr($pub['kategori_terbanyak'] ?? '-', 0, 30)) ?>...</small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted d-block">
                                            <?php if ($pub['rata_rata_tahun'] > 0): ?>
                                                <?= $pub['rata_rata_tahun'] ?>
                                                <br>
                                                <small><?= $pub['tahun_tertua'] ?> - <?= $pub['tahun_terbaru'] ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $availability = $pub['total_eksemplar'] > 0 ? ($pub['stok_tersedia'] / $pub['total_eksemplar']) * 100 : 0;
                                        
                                        if ($pub['jumlah_buku'] == 0) {
                                            echo '<span class="badge bg-secondary">Tanpa Buku</span>';
                                        } elseif ($availability < 20) {
                                            echo '<span class="badge bg-danger">Stok Rendah</span>';
                                        } elseif ($pub['total_pinjam'] > $pub['jumlah_buku'] * 3) {
                                            echo '<span class="badge bg-success">Sangat Populer</span>';
                                        } elseif ($pub['rata_rata_tahun'] > date('Y') - 5) {
                                            echo '<span class="badge bg-info">Koleksi Baru</span>';
                                        } else {
                                            echo '<span class="badge bg-light text-dark">Normal</span>';
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
    <?php if (!empty($publishers)): ?>
        <div class="row g-3 mt-4">
            <div class="col-md-12">
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern mb-0">
                            <i class="fas fa-lightbulb me-2"></i>Analisis dan Rekomendasi
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">Rata-rata buku per penerbit:</small>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-info" 
                                                 style="width: <?= min($stats['rata_buku_per_penerbit'] * 10, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= $stats['rata_buku_per_penerbit'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">Penerbit tanpa buku:</small>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?= min($stats['penerbit_tanpa_buku'] * 20, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= $stats['penerbit_tanpa_buku'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">Koleksi modern (≤ 5 tahun):</small>
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $modern_books = array_filter($publishers, fn($p) => $p['rata_rata_tahun'] >= date('Y') - 5);
                                        $modern_rate = count($modern_books) / count($publishers) * 100;
                                        $modern_class = $modern_rate > 50 ? 'bg-success' : ($modern_rate > 20 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress flex-grow-1" style="height: 20px;">
                                            <div class="progress-bar <?= $modern_class ?>" 
                                                 style="width: <?= min($modern_rate, 100) ?>%">
                                            </div>
                                        </div>
                                        <span class="ms-2 fw-bold"><?= round($modern_rate, 1) ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats['penerbit_tanpa_buku'] > 0): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                                <p class="mb-2">Ada <?= $stats['penerbit_tanpa_buku'] ?> penerbit yang tidak memiliki buku.</p>
                                <small class="d-block">Pertimbangkan untuk menghapus penerbit kosong atau menghubungi mereka untuk koleksi baru.</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $low_stock_pubs = array_filter($publishers, fn($p) => $p['stok_tersedia'] < 5 && $p['stok_tersedia'] > 0);
                        if (count($low_stock_pubs) > 0): 
                        ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-boxes me-2"></i>Stok Rendah!</h6>
                                <p class="mb-2"><?= count($low_stock_pubs) ?> penerbit memiliki stok tersedia rendah:</p>
                                <ul class="mb-0">
                                    <?php foreach ($low_stock_pubs as $pub): ?>
                                        <li><strong><?= $pub['nama_penerbit'] ?></strong>: Hanya <?= $pub['stok_tersedia'] ?> eksemplar tersedia</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $top_publishers = array_slice($publishers, 0, 3);
                        if (!empty($top_publishers)): 
                        ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-trophy me-2"></i>Penerbit Unggulan</h6>
                                <p class="mb-2">Top 3 penerbit berdasarkan koleksi:</p>
                                <div class="row g-2">
                                    <?php foreach ($top_publishers as $pub): ?>
                                        <div class="col-md-4">
                                            <div class="border p-2 rounded bg-light">
                                                <strong class="d-block"><?= $pub['nama_penerbit'] ?></strong>
                                                <div class="small mt-1">
                                                    <span class="text-primary d-inline-block me-2"><?= $pub['jumlah_buku'] ?> judul</span>
                                                    <span class="text-success d-inline-block me-2"><?= $pub['total_eksemplar'] ?> eksemplar</span>
                                                    <span class="text-warning d-inline-block"><?= $pub['total_pinjam'] ?> pinjaman</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        $old_publishers = array_filter($publishers, fn($p) => $p['rata_rata_tahun'] < date('Y') - 10 && $p['rata_rata_tahun'] > 0);
                        if (count($old_publishers) > 0): 
                        ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-history me-2"></i>Koleksi Tua</h6>
                                <p class="mb-2"><?= count($old_publishers) ?> penerbit memiliki koleksi rata-rata >10 tahun:</p>
                                <ul class="mb-0">
                                    <?php foreach (array_slice($old_publishers, 0, 5) as $pub): ?>
                                        <li><strong><?= $pub['nama_penerbit'] ?></strong>: Rata-rata <?= $pub['rata_rata_tahun'] ?> (<?= date('Y') - $pub['rata_rata_tahun'] ?> tahun lalu)</li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="mt-2 d-block">Pertimbangkan untuk update koleksi atau diskon buku lama.</small>
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
// Publishers Chart
const publishersCtx = document.getElementById('publishersChart').getContext('2d');
new Chart(publishersCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_slice($chart_labels, 0, 8)) ?>,
        datasets: [{
            label: 'Jumlah Buku',
            data: <?= json_encode(array_slice($chart_data_books, 0, 8)) ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.7)',
            borderColor: 'rgb(153, 102, 255)',
            borderWidth: 1
        }, {
            label: 'Total Peminjaman',
            data: <?= json_encode(array_slice($chart_data_borrow, 0, 8)) ?>,
            backgroundColor: 'rgba(255, 159, 64, 0.7)',
            borderColor: 'rgb(255, 159, 64)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
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
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    padding: 15,
                    boxWidth: 12
                }
            }
        }
    }
});
</script>

<style>
@media print {
    .btn, .modern-card:not(.table-responsive), .filter-section, .chart-section, .analysis-section {
        display: none !important;
    }
    .table-modern {
        font-size: 8px !important;
    }
    .container-fluid {
        padding: 0 !important;
    }
    .modern-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>