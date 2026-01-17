<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

$page_title = 'Laporan Denda';
include '../../config/database.php';

// Get denda per hari dari pengaturan
$denda_per_hari = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_per_hari'")->fetchColumn() ?? 1000;

// Get filter parameters
$filter = [
    'periode' => $_GET['periode'] ?? 'bulan_ini', // bulan_ini, tahun_ini, custom
    'tgl_dari' => $_GET['tgl_dari'] ?? date('Y-m-01'),
    'tgl_sampai' => $_GET['tgl_sampai'] ?? date('Y-m-d'),
    'status' => $_GET['status'] ?? '', // lunas, belum_lunas, semua
    'anggota' => $_GET['anggota'] ?? '',
    'jenis_denda' => $_GET['jenis_denda'] ?? '', // terlambat, rusak, semua
    'min_jumlah' => $_GET['min_jumlah'] ?? 0,
    'sort_by' => $_GET['sort_by'] ?? 'denda',
    'sort_order' => $_GET['sort_order'] ?? 'desc'
];

// Adjust dates based on periode
if ($filter['periode'] === 'bulan_ini') {
    $filter['tgl_dari'] = date('Y-m-01');
    $filter['tgl_sampai'] = date('Y-m-d');
} elseif ($filter['periode'] === 'tahun_ini') {
    $filter['tgl_dari'] = date('Y-01-01');
    $filter['tgl_sampai'] = date('Y-m-d');
} elseif ($filter['periode'] === 'kemarin') {
    $filter['tgl_dari'] = date('Y-m-d', strtotime('-1 day'));
    $filter['tgl_sampai'] = date('Y-m-d', strtotime('-1 day'));
} elseif ($filter['periode'] === 'minggu_ini') {
    $filter['tgl_dari'] = date('Y-m-d', strtotime('monday this week'));
    $filter['tgl_sampai'] = date('Y-m-d');
}

// Build query for denda
$where = [];
$params = [];

// Filter by date
$where[] = "DATE(pg.created_at) BETWEEN ? AND ?";
$params[] = $filter['tgl_dari'];
$params[] = $filter['tgl_sampai'];

// Filter by status
if ($filter['status'] === 'lunas') {
    $where[] = "pg.denda > 0";
} elseif ($filter['status'] === 'belum_lunas') {
    // Untuk denda yang belum lunas, kita perlu hitung dari peminjaman yang telat
    // Ini lebih kompleks, kita akan handle di aplikasi logic
}

// Filter by anggota
if (!empty($filter['anggota'])) {
    $where[] = "(a.nama LIKE ? OR a.nik LIKE ?)";
    $search_term = "%{$filter['anggota']}%";
    $params[] = $search_term;
    $params[] = $search_term;
}

// Filter by jenis denda
if ($filter['jenis_denda'] === 'terlambat') {
    $where[] = "pg.kondisi_buku = 'baik'";
} elseif ($filter['jenis_denda'] === 'rusak') {
    $where[] = "pg.kondisi_buku IN ('rusak_ringan', 'rusak_berat')";
}

// Filter by minimum amount
if ($filter['min_jumlah'] > 0) {
    $where[] = "pg.denda >= ?";
    $params[] = $filter['min_jumlah'];
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get denda yang sudah dibayar (dari tabel pengembalian)
$query_paid = "
    SELECT 
        pg.id_pengembalian,
        p.id_peminjaman,
        a.nik,
        a.nama as nama_anggota,
        b.judul,
        b.isbn,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        pg.tanggal_pengembalian_aktual,
        pg.kondisi_buku,
        pg.denda,
        DATE(pg.created_at) as tanggal_denda,
        DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali) as hari_terlambat,
        CASE 
            WHEN pg.kondisi_buku = 'baik' THEN pg.denda
            ELSE 0
        END as denda_terlambat,
        CASE 
            WHEN pg.kondisi_buku IN ('rusak_ringan', 'rusak_berat') THEN pg.denda
            ELSE 0
        END as denda_rusak
    FROM pengembalian pg
    JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    $where_clause
    ORDER BY {$filter['sort_by']} {$filter['sort_order']}
";

$stmt_paid = $conn->prepare($query_paid);
$stmt_paid->execute($params);
$denda_dibayar = $stmt_paid->fetchAll();

// Get denda yang belum dibayar (peminjaman telat belum dikembalikan)
$query_unpaid = "
    SELECT 
        p.id_peminjaman,
        a.nik,
        a.nama as nama_anggota,
        b.judul,
        b.isbn,
        p.tanggal_pinjam,
        p.tanggal_kembali,
        CURDATE() as tanggal_sekarang,
        DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
        DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari} as denda_tertunggak,
        'belum_lunas' as status
    FROM peminjaman p
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    WHERE p.status = 'dipinjam'
    AND p.tanggal_kembali < CURDATE()
    AND NOT EXISTS (
        SELECT 1 FROM pengembalian pg 
        WHERE pg.id_peminjaman = p.id_peminjaman
    )
    ORDER BY hari_terlambat DESC
";

$denda_belum_dibayar = $conn->query($query_unpaid)->fetchAll();

// Combine all fines
$all_denda = array_merge($denda_dibayar, $denda_belum_dibayar);

// Get total count
$total_paid = count($denda_dibayar);
$total_unpaid = count($denda_belum_dibayar);
$total_all = $total_paid + $total_unpaid;

// Calculate statistics
$stats = [
    'total_denda' => array_sum(array_column($denda_dibayar, 'denda')) + array_sum(array_column($denda_belum_dibayar, 'denda_tertunggak')),
    'denda_dibayar' => array_sum(array_column($denda_dibayar, 'denda')),
    'denda_belum_dibayar' => array_sum(array_column($denda_belum_dibayar, 'denda_tertunggak')),
    'denda_terlambat' => array_sum(array_column($denda_dibayar, 'denda_terlambat')),
    'denda_rusak' => array_sum(array_column($denda_dibayar, 'denda_rusak')),
    'total_transaksi' => $total_paid,
    'rata_denda' => $total_paid > 0 ? round(array_sum(array_column($denda_dibayar, 'denda')) / $total_paid, 2) : 0,
    'denda_tertinggi' => $total_paid > 0 ? max(array_column($denda_dibayar, 'denda')) : 0
];

// Get top members with fines
$top_members = $conn->query("
    SELECT 
        a.nik,
        a.nama,
        COUNT(pg.id_pengembalian) as total_denda,
        SUM(pg.denda) as total_nominal
    FROM anggota a
    LEFT JOIN peminjaman p ON a.nik = p.nik
    LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman AND pg.denda > 0
    GROUP BY a.nik
    HAVING total_nominal > 0
    ORDER BY total_nominal DESC
    LIMIT 5
")->fetchAll();

// Monthly trend
$monthly_trend = $conn->query("
    SELECT 
        DATE_FORMAT(pg.created_at, '%Y-%m') as bulan,
        DATE_FORMAT(pg.created_at, '%b %Y') as bulan_nama,
        COUNT(*) as jumlah_transaksi,
        SUM(pg.denda) as total_denda,
        AVG(pg.denda) as rata_denda
    FROM pengembalian pg
    WHERE pg.denda > 0
    AND pg.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY bulan
    ORDER BY bulan ASC
")->fetchAll();

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

/* Color variations */
.text-teal { color: #20c997; }
.text-orange { color: #fd7e14; }
.text-pink { color: #d63384; }
.text-indigo { color: #6610f2; }

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

/* Card footer */
.card-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #e0e0e0;
    padding: 12px 20px;
    border-radius: 0 0 12px 12px;
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
        font-size: 7px;
    }
    .container-fluid {
        padding: 0 !important;
    }
}

/* Background colors */
.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1) !important;
}
.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1) !important;
}
.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1) !important;
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
                    <li class="breadcrumb-item active">Denda</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">💰</span>
                <span class="title-gradient">Laporan Denda</span>
            </h1>
            <p class="text-muted mb-0">Manajemen dan analisis denda perpustakaan</p>
        </div>
        <div class="d-flex gap-2">
            <a href="export.php?type=denda" class="btn btn-modern btn-success">
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
                        <i class="fas fa-money-bill-wave fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary"><?= formatRupiah($stats['total_denda']) ?></div>
                    <small class="stat-label">Total Denda (Semua)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success"><?= formatRupiah($stats['denda_dibayar']) ?></div>
                    <small class="stat-label">Denda Dibayar</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-clock fa-2x text-danger"></i>
                    </div>
                    <div class="stat-number text-danger"><?= formatRupiah($stats['denda_belum_dibayar']) ?></div>
                    <small class="stat-label">Denda Belum Dibayar</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-up fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning"><?= formatRupiah($stats['denda_tertinggi']) ?></div>
                    <small class="stat-label">Denda Tertinggi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-receipt fa-2x text-info"></i>
                    </div>
                    <div class="stat-number text-info"><?= $total_paid ?></div>
                    <small class="stat-label">Transaksi Denda</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half fa-2x text-purple"></i>
                    </div>
                    <div class="stat-number text-purple"><?= $total_unpaid ?></div>
                    <small class="stat-label">Denda Tertunggak</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times fa-2x text-teal"></i>
                    </div>
                    <div class="stat-number text-teal"><?= formatRupiah($stats['denda_terlambat']) ?></div>
                    <small class="stat-label">Denda Keterlambatan</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book-medical fa-2x text-orange"></i>
                    </div>
                    <div class="stat-number text-orange"><?= formatRupiah($stats['denda_rusak']) ?></div>
                    <small class="stat-label">Denda Kerusakan</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-calculator fa-2x text-pink"></i>
                    </div>
                    <div class="stat-number text-pink"><?= formatRupiah($stats['rata_denda']) ?></div>
                    <small class="stat-label">Rata-rata per Transaksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-tag fa-2x text-indigo"></i>
                    </div>
                    <div class="stat-number text-indigo"><?= $denda_per_hari ?>/hari</div>
                    <small class="stat-label">Tarif Denda</small>
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
                    <label class="form-label-modern">Periode</label>
                    <select name="periode" class="form-control-modern" onchange="this.form.submit()">
                        <option value="bulan_ini" <?= $filter['periode'] === 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini</option>
                        <option value="tahun_ini" <?= $filter['periode'] === 'tahun_ini' ? 'selected' : '' ?>>Tahun Ini</option>
                        <option value="minggu_ini" <?= $filter['periode'] === 'minggu_ini' ? 'selected' : '' ?>>Minggu Ini</option>
                        <option value="kemarin" <?= $filter['periode'] === 'kemarin' ? 'selected' : '' ?>>Kemarin</option>
                        <option value="custom" <?= $filter['periode'] === 'custom' ? 'selected' : '' ?>>Custom</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Tanggal Dari</label>
                    <input type="date" 
                           name="tgl_dari" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filter['tgl_dari']) ?>"
                           <?= $filter['periode'] !== 'custom' ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Tanggal Sampai</label>
                    <input type="date" 
                           name="tgl_sampai" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filter['tgl_sampai']) ?>"
                           <?= $filter['periode'] !== 'custom' ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Cari Anggota</label>
                    <input type="text" 
                           name="anggota" 
                           class="form-control-modern" 
                           placeholder="Nama atau NIK anggota..." 
                           value="<?= htmlspecialchars($filter['anggota']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Jenis Denda</label>
                    <select name="jenis_denda" class="form-control-modern">
                        <option value="">Semua Jenis</option>
                        <option value="terlambat" <?= $filter['jenis_denda'] === 'terlambat' ? 'selected' : '' ?>>Keterlambatan</option>
                        <option value="rusak" <?= $filter['jenis_denda'] === 'rusak' ? 'selected' : '' ?>>Kerusakan Buku</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Status Denda</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="lunas" <?= $filter['status'] === 'lunas' ? 'selected' : '' ?>>Sudah Dibayar</option>
                        <option value="belum_lunas" <?= $filter['status'] === 'belum_lunas' ? 'selected' : '' ?>>Belum Dibayar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Minimal Jumlah</label>
                    <input type="number" 
                           name="min_jumlah" 
                           class="form-control-modern" 
                           placeholder="Rp 0" 
                           min="0" 
                           step="1000"
                           value="<?= htmlspecialchars($filter['min_jumlah']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Urutkan Berdasarkan</label>
                    <select name="sort_by" class="form-control-modern">
                        <option value="denda" <?= $filter['sort_by'] === 'denda' ? 'selected' : '' ?>>Jumlah Denda</option>
                        <option value="tanggal_denda" <?= $filter['sort_by'] === 'tanggal_denda' ? 'selected' : '' ?>>Tanggal</option>
                        <option value="nama_anggota" <?= $filter['sort_by'] === 'nama_anggota' ? 'selected' : '' ?>>Nama Anggota</option>
                    </select>
                </div>
                <div class="col-md-1">
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
                    <a href="denda.php" class="btn btn-modern btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Charts and Analysis -->
    <div class="row g-4 mb-4">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Monthly Trend -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-chart-line me-2"></i>Tren Denda 6 Bulan Terakhir
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Top Members with Fines -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Top 5 Anggota dengan Denda
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_members as $index => $member): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-danger me-2">#<?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars($member['nama']) ?></strong>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-danger fw-bold"><?= formatRupiah($member['total_nominal']) ?></div>
                                        <small class="text-muted"><?= $member['total_denda'] ?> transaksi</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_members)): ?>
                            <div class="list-group-item border-0 text-center py-4">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada anggota dengan denda</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Denda Tables -->
    <div class="row g-4">
        <!-- Denda Dibayar -->
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern bg-success text-white">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-check-circle me-2"></i>Denda Sudah Dibayar
                        <small class="opacity-75">(<?= $total_paid ?> transaksi)</small>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($denda_dibayar)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Tidak ada denda dibayar dalam periode ini</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th class="text-center">Hari Telat</th>
                                        <th class="text-center">Denda</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($denda_dibayar as $denda): ?>
                                        <tr>
                                            <td>
                                                <small><?= date('d/m/Y', strtotime($denda['tanggal_denda'])) ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($denda['nama_anggota']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= $denda['nik'] ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars(substr($denda['judul'], 0, 20)) ?>...</small>
                                                <br>
                                                <small class="text-muted">ISBN: <?= $denda['isbn'] ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($denda['hari_terlambat'] > 0): ?>
                                                    <span class="badge bg-warning"><?= $denda['hari_terlambat'] ?> hari</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Tepat waktu</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?= formatRupiah($denda['denda']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                // FIXED: Logika status yang benar
                                                $kondisi = $denda['kondisi_buku'];
                                                $hari_terlambat = intval($denda['hari_terlambat']);
                                                
                                                if ($hari_terlambat > 0) {
                                                    // Ada keterlambatan
                                                    if ($kondisi === 'rusak_ringan') {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Terlambat + Rusak Ringan</span>';
                                                    } elseif ($kondisi === 'rusak_berat') {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Terlambat + Rusak Berat</span>';
                                                    } else {
                                                        // Hanya terlambat
                                                        echo '<span class="badge bg-info"><i class="fas fa-clock me-1"></i>Terlambat ' . $hari_terlambat . ' hari</span>';
                                                    }
                                                } else {
                                                    // Tepat waktu, cek kondisi buku
                                                    if ($kondisi === 'rusak_ringan') {
                                                        echo '<span class="badge bg-warning"><i class="fas fa-tools me-1"></i>Rusak Ringan</span>';
                                                    } elseif ($kondisi === 'rusak_berat') {
                                                        echo '<span class="badge bg-danger"><i class="fas fa-tools me-1"></i>Rusak Berat</span>';
                                                    } else {
                                                        // Sempurna
                                                        echo '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Tepat Waktu</span>';
                                                    }
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
                <?php if ($total_paid > 0): ?>
                    <div class="card-footer bg-success-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-success">Total: <?= formatRupiah($stats['denda_dibayar']) ?></small>
                            <small class="text-success">Rata-rata: <?= formatRupiah($stats['rata_denda']) ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Denda Belum Dibayar -->
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern bg-danger text-white">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-clock me-2"></i>Denda Belum Dibayar (Tertunggak)
                        <small class="opacity-75">(<?= $total_unpaid ?> transaksi)</small>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($denda_belum_dibayar)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">Tidak ada denda tertunggak</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th class="text-center">Batas Kembali</th>
                                        <th class="text-center">Hari Telat</th>
                                        <th class="text-center">Denda Tertunggak</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($denda_belum_dibayar as $denda): ?>
                                        <tr>
                                            <td>
                                                <small><?= htmlspecialchars($denda['nama_anggota']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= $denda['nik'] ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars(substr($denda['judul'], 0, 20)) ?>...</small>
                                            </td>
                                            <td class="text-center">
                                                <small><?= date('d/m/Y', strtotime($denda['tanggal_kembali'])) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning"><?= $denda['hari_terlambat'] ?> hari</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger"><?= formatRupiah($denda['denda_tertunggak']) ?></span>
                                                <br>
                                                <small class="text-muted">+<?= $denda_per_hari ?>/hari</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-dark"><i class="fas fa-exclamation-circle me-1"></i>Belum Dikembalikan</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($total_unpaid > 0): ?>
                    <div class="card-footer bg-danger-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-danger">Total Tertunggak: <?= formatRupiah($stats['denda_belum_dibayar']) ?></small>
                            <small class="text-danger">Bertambah <?= formatRupiah($denda_per_hari) ?>/hari</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="modern-card mt-4">
        <div class="card-header-modern bg-primary text-white">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-chart-pie me-2"></i>Ringkasan dan Analisis Denda
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-percentage me-2"></i>Distribusi Jenis Denda</h6>
                    <div class="mt-3">
                        <div class="mb-3">
                            <small class="text-muted">Denda Keterlambatan:</small>
                            <div class="progress">
                                <?php
                                $late_percent = $stats['denda_dibayar'] > 0 ? ($stats['denda_terlambat'] / $stats['denda_dibayar']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-info" style="width: <?= $late_percent ?>%">
                                    <?= formatRupiah($stats['denda_terlambat']) ?> (<?= round($late_percent, 1) ?>%)
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Denda Kerusakan Buku:</small>
                            <div class="progress">
                                <?php
                                $damage_percent = $stats['denda_dibayar'] > 0 ? ($stats['denda_rusak'] / $stats['denda_dibayar']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-warning" style="width: <?= $damage_percent ?>%">
                                    <?= formatRupiah($stats['denda_rusak']) ?> (<?= round($damage_percent, 1) ?>%)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-lightbulb me-2"></i>Rekomendasi</h6>
                    <div class="mt-3">
                        <?php if ($total_unpaid > 0): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian: Denda Tertunggak</h6>
                                <p class="mb-2">Ada <?= $total_unpaid ?> denda yang belum dibayar dengan total <?= formatRupiah($stats['denda_belum_dibayar']) ?>.</p>
                                <small>Segera lakukan penagihan kepada anggota terkait.</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['denda_rusak'] > 0): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-book-medical me-2"></i>Perawatan Koleksi</h6>
                                <p class="mb-2">Total denda kerusakan buku: <?= formatRupiah($stats['denda_rusak']) ?>.</p>
                                <small>Pertimbangkan untuk meningkatkan sosialisasi perawatan buku.</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-chart-line me-2"></i>Analisis Performa</h6>
                            <p class="mb-2">Rata-rata denda per transaksi: <?= formatRupiah($stats['rata_denda']) ?>.</p>
                            <small>Target: <strong><?= formatRupiah($stats['total_denda'] * 0.9) ?></strong> (90% dari total) harus terkumpul dalam 30 hari.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_trend, 'bulan_nama')) ?>,
        datasets: [
            {
                label: 'Total Denda',
                data: <?= json_encode(array_column($monthly_trend, 'total_denda')) ?>,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Jumlah Transaksi',
                data: <?= json_encode(array_column($monthly_trend, 'jumlah_transaksi')) ?>,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Total Denda (Rp)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Jumlah Transaksi'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>