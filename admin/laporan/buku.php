<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

$page_title = 'Laporan Buku';
include '../../config/database.php';

// Get filter parameters
$filter = [
    'search' => $_GET['search'] ?? '',
    'kategori' => $_GET['kategori'] ?? '',
    'penerbit' => $_GET['penerbit'] ?? '',
    'tahun' => $_GET['tahun'] ?? '',
    'status' => $_GET['status'] ?? '',
    'sort_by' => $_GET['sort_by'] ?? 'judul',
    'sort_order' => $_GET['sort_order'] ?? 'asc',
    'limit' => $_GET['limit'] ?? 50
];

// Build query
$where = [];
$params = [];

if (!empty($filter['search'])) {
    $where[] = "(b.judul LIKE ? OR b.pengarang LIKE ? OR b.isbn LIKE ?)";
    $search_term = "%{$filter['search']}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filter['kategori'])) {
    $where[] = "bk.id_kategori = ?";
    $params[] = $filter['kategori'];
}

if (!empty($filter['penerbit'])) {
    $where[] = "b.id_penerbit = ?";
    $params[] = $filter['penerbit'];
}

if (!empty($filter['tahun'])) {
    $where[] = "b.tahun_terbit = ?";
    $params[] = $filter['tahun'];
}

if (!empty($filter['status'])) {
    if ($filter['status'] === 'tersedia') {
        $where[] = "b.stok_tersedia > 0";
    } elseif ($filter['status'] === 'habis') {
        $where[] = "b.stok_tersedia = 0";
    }
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Validate sort order
$valid_orders = ['asc', 'desc'];
$filter['sort_order'] = in_array(strtolower($filter['sort_order']), $valid_orders) ? $filter['sort_order'] : 'asc';

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT b.isbn) as total
    FROM buku b
    LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
    LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
    LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_books = $count_stmt->fetchColumn();

// Get books data with statistics
$query = "
    SELECT 
        b.isbn,
        b.judul,
        b.pengarang,
        p.nama_penerbit,
        b.tahun_terbit,
        b.stok_total,
        b.stok_tersedia,
        b.status,
        GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') as kategori,
        COUNT(DISTINCT pm.id_peminjaman) as total_pinjam,
        SUM(CASE WHEN pm.status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam
    FROM buku b
    LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
    LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
    LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
    LEFT JOIN peminjaman pm ON b.isbn = pm.isbn
    $where_clause
    GROUP BY b.isbn
    ORDER BY {$filter['sort_by']} {$filter['sort_order']}
    LIMIT ?
";

$params[] = (int)$filter['limit'];
$stmt = $conn->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get filter options
$categories = $conn->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori")->fetchAll();
$publishers = $conn->query("SELECT id_penerbit, nama_penerbit FROM penerbit ORDER BY nama_penerbit")->fetchAll();
$years = $conn->query("SELECT DISTINCT tahun_terbit FROM buku ORDER BY tahun_terbit DESC")->fetchAll();

// Calculate statistics
$stats = [
    'total_buku' => $total_books,
    'total_stok' => array_sum(array_column($books, 'stok_total')),
    'stok_tersedia' => array_sum(array_column($books, 'stok_tersedia')),
    'buku_tersedia' => count(array_filter($books, fn($b) => $b['stok_tersedia'] > 0)),
    'total_pinjam' => array_sum(array_column($books, 'total_pinjam')),
    'sedang_dipinjam' => array_sum(array_column($books, 'sedang_dipinjam'))
];

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
    
    .col-md-2, .col-md-3 {
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
    
    .d-flex.gap-2 .btn-modern {
        width: 100%;
        margin-bottom: 0.25rem;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .col-md-3, .col-md-2, .col-md-1, .col-md-7 {
        margin-bottom: 0.5rem;
    }
    
    .form-label-modern {
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
    }
}

@media print {
    .btn-modern, .modern-card:not(.table-responsive) {
        display: none !important;
    }
    .table-modern {
        font-size: 10px;
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
                    <li class="breadcrumb-item active">Buku</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">📚</span>
                <span class="title-gradient">Laporan Buku</span>
            </h1>
            <p class="text-muted mb-0">Inventaris dan statistik koleksi buku</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?type=buku" class="btn btn-modern btn-success">
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
                        <i class="fas fa-book fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary"><?= $stats['total_buku'] ?></div>
                    <small class="stat-label">Judul Buku</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-copy fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success"><?= $stats['total_stok'] ?></div>
                    <small class="stat-label">Total Eksemplar</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle fa-2x text-info"></i>
                    </div>
                    <div class="stat-number text-info"><?= $stats['stok_tersedia'] ?></div>
                    <small class="stat-label">Tersedia</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book-open fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning"><?= $stats['buku_tersedia'] ?></div>
                    <small class="stat-label">Judul Tersedia</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt fa-2x text-purple"></i>
                    </div>
                    <div class="stat-number text-purple"><?= $stats['total_pinjam'] ?></div>
                    <small class="stat-label">Total Dipinjam</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-clock fa-2x text-danger"></i>
                    </div>
                    <div class="stat-number text-danger"><?= $stats['sedang_dipinjam'] ?></div>
                    <small class="stat-label">Sedang Dipinjam</small>
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
                    <label class="form-label-modern">Cari Buku</label>
                    <input type="text" 
                           name="search" 
                           class="form-control-modern" 
                           placeholder="Judul, pengarang, ISBN..." 
                           value="<?= htmlspecialchars($filter['search']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Kategori</label>
                    <select name="kategori" class="form-control-modern">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id_kategori'] ?>" 
                                <?= $filter['kategori'] == $cat['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Penerbit</label>
                    <select name="penerbit" class="form-control-modern">
                        <option value="">Semua Penerbit</option>
                        <?php foreach ($publishers as $pub): ?>
                            <option value="<?= $pub['id_penerbit'] ?>" 
                                <?= $filter['penerbit'] == $pub['id_penerbit'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pub['nama_penerbit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Tahun Terbit</label>
                    <select name="tahun" class="form-control-modern">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year['tahun_terbit'] ?>" 
                                <?= $filter['tahun'] == $year['tahun_terbit'] ? 'selected' : '' ?>>
                                <?= $year['tahun_terbit'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Status Stok</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="tersedia" <?= $filter['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="habis" <?= $filter['status'] === 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label-modern">Tampilkan</label>
                    <select name="limit" class="form-control-modern">
                        <option value="20" <?= $filter['limit'] == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $filter['limit'] == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $filter['limit'] == 100 ? 'selected' : '' ?>>100</option>
                        <option value="500" <?= $filter['limit'] == 500 ? 'selected' : '' ?>>500</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Urutkan Berdasarkan</label>
                    <select name="sort_by" class="form-control-modern">
                        <option value="judul" <?= $filter['sort_by'] === 'judul' ? 'selected' : '' ?>>Judul</option>
                        <option value="pengarang" <?= $filter['sort_by'] === 'pengarang' ? 'selected' : '' ?>>Pengarang</option>
                        <option value="tahun_terbit" <?= $filter['sort_by'] === 'tahun_terbit' ? 'selected' : '' ?>>Tahun Terbit</option>
                        <option value="stok_tersedia" <?= $filter['sort_by'] === 'stok_tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                        <option value="total_pinjam" <?= $filter['sort_by'] === 'total_pinjam' ? 'selected' : '' ?>>Total Dipinjam</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Urutan</label>
                    <select name="sort_order" class="form-control-modern">
                        <option value="asc" <?= $filter['sort_order'] === 'asc' ? 'selected' : '' ?>>A → Z</option>
                        <option value="desc" <?= $filter['sort_order'] === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    </select>
                </div>
                <div class="col-md-7 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-modern btn-primary">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                    <a href="buku.php" class="btn btn-modern btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-list me-2"></i>Daftar Buku
                    <small class="text-muted">(<?= $total_books ?> judul ditemukan)</small>
                </h5>
            </div>
            <div class="text-muted">
                Dicetak: <?= date('d/m/Y H:i') ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada buku ditemukan</h5>
                    <p class="text-muted">Coba ubah kriteria pencarian</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>ISBN</th>
                                <th>Judul Buku</th>
                                <th>Pengarang</th>
                                <th>Penerbit</th>
                                <th>Kategori</th>
                                <th>Tahun</th>
                                <th class="text-center">Stok</th>
                                <th class="text-center">Tersedia</th>
                                <th class="text-center">Dipinjam</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($book['isbn']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($book['judul']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($book['pengarang']) ?></td>
                                    <td><?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?></td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($book['kategori'] ?? '-') ?></small>
                                    </td>
                                    <td><?= $book['tahun_terbit'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $book['stok_total'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($book['stok_tersedia'] > 0): ?>
                                            <span class="badge bg-success"><?= $book['stok_tersedia'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?= $book['stok_tersedia'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($book['total_pinjam'] > 0): ?>
                                            <span class="badge bg-info" title="Total dipinjam: <?= $book['total_pinjam'] ?>">
                                                <?= $book['sedang_dipinjam'] ?> / <?= $book['total_pinjam'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($book['stok_tersedia'] > 0): ?>
                                            <span class="badge bg-success">Tersedia</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Habis</span>
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
    <?php if (!empty($books)): ?>
        <div class="row g-3 mt-4">
            <div class="col-md-6">
                <div class="modern-card">
                    <div class="card-body">
                        <h6><i class="fas fa-chart-bar me-2"></i>Analisis Stok</h6>
                        <div class="mt-3">
                            <?php
                            $low_stock = array_filter($books, fn($b) => $b['stok_tersedia'] <= 2 && $b['stok_tersedia'] > 0);
                            $out_of_stock = array_filter($books, fn($b) => $b['stok_tersedia'] == 0);
                            ?>
                            <div class="mb-3">
                                <small class="text-muted">Stok Rendah (≤ 2):</small>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" 
                                         style="width: <?= count($low_stock) / $total_books * 100 ?>%">
                                        <?= count($low_stock) ?> buku
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Stok Habis:</small>
                                <div class="progress">
                                    <div class="progress-bar bg-danger" 
                                         style="width: <?= count($out_of_stock) / $total_books * 100 ?>%">
                                        <?= count($out_of_stock) ?> buku
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
                            <?php if (count($low_stock) > 0): ?>
                                <div class="alert alert-warning mb-3">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                                    <p class="mb-2"><?= count($low_stock) ?> buku memiliki stok rendah (≤ 2 eksemplar).</p>
                                    <small>Pertimbangkan untuk menambah stok.</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (count($out_of_stock) > 0): ?>
                                <div class="alert alert-danger mb-3">
                                    <h6><i class="fas fa-ban me-2"></i>Stok Habis!</h6>
                                    <p class="mb-2"><?= count($out_of_stock) ?> buku stoknya habis.</p>
                                    <small>Segera lakukan restocking.</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $never_borrowed = array_filter($books, fn($b) => $b['total_pinjam'] == 0);
                            if (count($never_borrowed) > 0): 
                            ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Analisis Popularitas</h6>
                                    <p class="mb-2"><?= count($never_borrowed) ?> buku belum pernah dipinjam.</p>
                                    <small>Pertimbangkan untuk melakukan promosi atau review koleksi.</small>
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