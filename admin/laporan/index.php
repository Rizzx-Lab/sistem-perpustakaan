<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

$page_title = 'Dashboard Laporan';
include '../../config/database.php';

// Get current year and month
$current_year = date('Y');
$current_month = date('m');

// Get statistics data
try {
    // Overall statistics
    $overall = [
        'total_buku' => $conn->query("SELECT COUNT(*) FROM buku")->fetchColumn(),
        'buku_tersedia' => $conn->query("SELECT SUM(stok_tersedia) FROM buku")->fetchColumn(),
        'total_anggota' => $conn->query("SELECT COUNT(*) FROM anggota")->fetchColumn(),
        'anggota_aktif' => $conn->query("SELECT COUNT(DISTINCT nik) FROM peminjaman WHERE YEAR(tanggal_pinjam) = '$current_year'")->fetchColumn(),
        'total_peminjaman' => $conn->query("SELECT COUNT(*) FROM peminjaman")->fetchColumn(),
        'peminjaman_bulan_ini' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE YEAR(tanggal_pinjam) = '$current_year' AND MONTH(tanggal_pinjam) = '$current_month'")->fetchColumn(),
        'total_denda' => $conn->query("SELECT SUM(denda) FROM pengembalian WHERE denda > 0")->fetchColumn() ?? 0,
        'denda_bulan_ini' => $conn->query("SELECT SUM(denda) FROM pengembalian WHERE denda > 0 AND YEAR(created_at) = '$current_year' AND MONTH(created_at) = '$current_month'")->fetchColumn() ?? 0
    ];

    // Monthly statistics (last 6 months)
    $monthly_stats = $conn->query("
        SELECT 
            DATE_FORMAT(p.tanggal_pinjam, '%Y-%m') as bulan,
            DATE_FORMAT(p.tanggal_pinjam, '%b %Y') as bulan_nama,
            COUNT(*) as total_pinjam,
            SUM(CASE WHEN p.status = 'dikembalikan' THEN 1 ELSE 0 END) as total_kembali,
            SUM(COALESCE(pg.denda, 0)) as total_denda
        FROM peminjaman p
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.tanggal_pinjam >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY bulan
        ORDER BY bulan ASC
    ")->fetchAll();

    // Top 5 buku terpopuler
    $popular_books = $conn->query("
        SELECT b.judul, b.pengarang, COUNT(p.id_peminjaman) as total_pinjam
        FROM buku b
        LEFT JOIN peminjaman p ON b.isbn = p.isbn
        GROUP BY b.isbn
        ORDER BY total_pinjam DESC
        LIMIT 5
    ")->fetchAll();

    // Top 5 anggota teraktif
    $active_members = $conn->query("
        SELECT a.nama, COUNT(p.id_peminjaman) as total_pinjam
        FROM anggota a
        LEFT JOIN peminjaman p ON a.nik = p.nik
        GROUP BY a.nik
        ORDER BY total_pinjam DESC
        LIMIT 5
    ")->fetchAll();

    // Denda terbesar bulan ini
    $top_fines = $conn->query("
        SELECT a.nama, pg.denda, pg.created_at
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        JOIN anggota a ON p.nik = a.nik
        WHERE pg.denda > 0 
        AND YEAR(pg.created_at) = '$current_year'
        AND MONTH(pg.created_at) = '$current_month'
        ORDER BY pg.denda DESC
        LIMIT 5
    ")->fetchAll();

    // Kategori dengan buku terbanyak
    $top_categories = $conn->query("
        SELECT k.nama_kategori, COUNT(bk.isbn) as jumlah_buku
        FROM kategori k
        LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
        GROUP BY k.id_kategori
        ORDER BY jumlah_buku DESC
        LIMIT 5
    ")->fetchAll();

    // Penerbit dengan koleksi terbanyak
    $top_publishers = $conn->query("
        SELECT pn.nama_penerbit, COUNT(b.isbn) as jumlah_buku
        FROM penerbit pn
        LEFT JOIN buku b ON pn.id_penerbit = b.id_penerbit
        GROUP BY pn.id_penerbit
        ORDER BY jumlah_buku DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<style>
/* Layout Styling untuk Dashboard Laporan */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
    --warning-gradient: linear-gradient(135deg, #f46b45 0%, #eea849 100%);
    --purple-gradient: linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
}

/* Modern Card */
.modern-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.modern-card:hover {
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
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
    margin: 0;
}

/* Title Gradient */
.title-gradient {
    background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.logo-emoji {
    margin-right: 10px;
    font-size: 1.5rem;
}

/* Stats Cards */
.stats-card {
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.icon-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 15px;
}

.bg-primary-light { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
.bg-success-light { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
.bg-info-light { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
.bg-danger-light { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
.bg-warning-light { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
.bg-purple-light { background-color: rgba(111, 66, 193, 0.1); color: #6f42c1; }

/* TOMBOL STYLING - SAMA DENGAN SISTEM.PHP */
.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* Primary (Biru) */
.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: 1px solid #5a67d8;
}

.btn-primary-modern:hover {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    color: white;
    border-color: #4c51bf;
}

/* Success (Hijau) */
.btn-success-modern {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    color: white;
}

.btn-success-modern:hover {
    background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
    border-color: #1e7e34;
    color: white;
}

/* Info (Biru Terang) */
.btn-info-modern {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border-color: #36D1DC;
    color: white;
}

.btn-info-modern:hover {
    background: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%);
    border-color: #4facfe;
    color: white;
}

/* Danger (Merah) */
.btn-danger-modern {
    background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
    border-color: #ff6b6b;
    color: white;
}

.btn-danger-modern:hover {
    background: linear-gradient(135deg, #dc3545 0%, #ff8e8e 100%);
    border-color: #dc3545;
    color: white;
}

/* Warning (Oranye) */
.btn-warning-modern {
    background: linear-gradient(135deg, #f46b45 0%, #eea849 100%);
    border-color: #f46b45;
    color: white;
}

.btn-warning-modern:hover {
    background: linear-gradient(135deg, #eea849 0%, #f46b45 100%);
    border-color: #eea849;
    color: white;
}

/* Purple (Ungu) */
.btn-purple-modern {
    background: linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%);
    border-color: #8e2de2;
    color: white;
}

.btn-purple-modern:hover {
    background: linear-gradient(135deg, #4a00e0 0%, #8e2de2 100%);
    border-color: #4a00e0;
    color: white;
}

/* Tombol Small */
.btn-sm-modern {
    padding: 8px 16px;
    font-size: 0.875rem;
    border-radius: 6px;
}

/* Tombol Outline */
.btn-outline-primary-modern {
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-outline-primary-modern:hover {
    background: #667eea;
    color: white;
}

.btn-outline-success-modern {
    background: transparent;
    color: #28a745;
    border: 2px solid #28a745;
}

.btn-outline-success-modern:hover {
    background: #28a745;
    color: white;
}

.btn-outline-info-modern {
    background: transparent;
    color: #4facfe;
    border: 2px solid #4facfe;
}

.btn-outline-info-modern:hover {
    background: #4facfe;
    color: white;
}

.btn-outline-danger-modern {
    background: transparent;
    color: #ff6b6b;
    border: 2px solid #ff6b6b;
}

.btn-outline-danger-modern:hover {
    background: #ff6b6b;
    color: white;
}

/* Badge Modern */
.badge-modern {
    border-radius: 6px;
    padding: 5px 10px;
    font-weight: 500;
}

/* List Group Items */
.list-group-item {
    border: none;
    border-bottom: 1px solid #f0f0f0;
    padding: 12px 15px;
}

.list-group-item:last-child {
    border-bottom: none;
}

/* Responsive */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .modern-card {
        margin-bottom: 15px;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .card-header-modern {
        padding: 12px 15px;
    }
    
    .btn-modern {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .btn-sm-modern {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    
    .icon-circle {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2 title-container">
                <span class="logo-emoji">📊</span>
                <span class="title-gradient">Dashboard Laporan</span>
            </h1>
            <p class="text-muted mb-0">Statistik dan analisis perpustakaan</p>
        </div>
        <div class="text-end">
            <small class="text-muted">Periode: <?= date('F Y') ?></small>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Layout Baru: ATAS Tabel Statistik, KANAN Info Samping -->
    <div class="row g-4">
        <!-- Left Column: Statistik dan Tabel -->
        <div class="col-lg-8">
            <!-- Chart Row -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-chart-line me-2"></i>Trend Peminjaman 6 Bulan Terakhir
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="modern-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Buku</h6>
                                <h3 class="mb-0 text-primary"><?= number_format($overall['total_buku']) ?></h3>
                                <small class="text-muted"><?= number_format($overall['buku_tersedia']) ?> tersedia</small>
                            </div>
                            <div class="icon-circle bg-primary-light">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="buku.php" class="btn btn-modern btn-sm-modern btn-outline-primary-modern w-100">
                                <i class="fas fa-chart-bar me-1"></i> Laporan Buku
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Anggota</h6>
                                <h3 class="mb-0 text-success"><?= number_format($overall['total_anggota']) ?></h3>
                                <small class="text-muted"><?= number_format($overall['anggota_aktif']) ?> aktif tahun ini</small>
                            </div>
                            <div class="icon-circle bg-success-light">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="anggota.php" class="btn btn-modern btn-sm-modern btn-outline-success-modern w-100">
                                <i class="fas fa-user-chart me-1"></i> Laporan Anggota
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Peminjaman</h6>
                                <h3 class="mb-0 text-info"><?= number_format($overall['total_peminjaman']) ?></h3>
                                <small class="text-muted"><?= number_format($overall['peminjaman_bulan_ini']) ?> bulan ini</small>
                            </div>
                            <div class="icon-circle bg-info-light">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="export.php?type=peminjaman" class="btn btn-modern btn-sm-modern btn-outline-info-modern w-100">
                                <i class="fas fa-download me-1"></i> Export Data
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="modern-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Denda</h6>
                                <h3 class="mb-0 text-danger"><?= formatRupiah($overall['total_denda']) ?></h3>
                                <small class="text-muted"><?= formatRupiah($overall['denda_bulan_ini']) ?> bulan ini</small>
                            </div>
                            <div class="icon-circle bg-danger-light">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="denda.php" class="btn btn-modern btn-sm-modern btn-outline-danger-modern w-100">
                                <i class="fas fa-file-invoice-dollar me-1"></i> Laporan Denda
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links Row - TAMBAHKAN CARD BUKU HILANG -->
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="modern-card p-3 text-center">
                        <div class="icon-circle bg-warning-light mb-3 mx-auto">
                            <i class="fas fa-tags text-warning"></i>
                        </div>
                        <h5 class="mb-2">Laporan Kategori</h5>
                        <p class="text-muted small mb-3">Distribusi buku berdasarkan kategori</p>
                        <a href="kategori.php" class="btn btn-modern btn-warning-modern w-100">
                            <i class="fas fa-eye me-1"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="modern-card p-3 text-center">
                        <div class="icon-circle bg-purple-light mb-3 mx-auto">
                            <i class="fas fa-building text-purple"></i>
                        </div>
                        <h5 class="mb-2">Laporan Penerbit</h5>
                        <p class="text-muted small mb-3">Koleksi buku per penerbit</p>
                        <a href="penerbit.php" class="btn btn-modern btn-purple-modern w-100">
                            <i class="fas fa-eye me-1"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
                
                <!-- 🆕 CARD BARU: LAPORAN BUKU HILANG -->
                <div class="col-md-4">
                    <div class="modern-card p-3 text-center">
                        <div class="icon-circle bg-danger-light mb-3 mx-auto">
                            <i class="fa-solid fa-book-skull text-danger"></i>
                        </div>
                        <h5 class="mb-2">Laporan Buku Hilang</h5>
                        <p class="text-muted small mb-3">Buku hilang atau rusak parah</p>
                        <a href="buku_hilang.php" class="btn btn-modern btn-danger-modern w-100">
                            <i class="fas fa-eye me-1"></i> Lihat Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Buku Terpopuler, Anggota Teraktif, Denda -->
        <div class="col-lg-4">
            <!-- Buku Terpopuler -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-fire me-2 text-danger"></i>Buku Terpopuler
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($popular_books as $index => $book): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge badge-modern bg-primary me-2">#<?= $index + 1 ?></span>
                                            <strong><?= htmlspecialchars(substr($book['judul'], 0, 25)) ?></strong>
                                        </div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($book['pengarang']) ?></small>
                                    </div>
                                    <span class="badge badge-modern bg-primary ms-2"><?= $book['total_pinjam'] ?>x</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($popular_books)): ?>
                            <div class="list-group-item border-0 text-center py-4">
                                <i class="fas fa-book fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada data buku</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Anggota Teraktif -->
            <div class="modern-card mb-4">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-user-check me-2 text-success"></i>Anggota Teraktif
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($active_members as $index => $member): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge badge-modern bg-success me-2">#<?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars(substr($member['nama'], 0, 20)) ?></strong>
                                    </div>
                                    <span class="badge badge-modern bg-success"><?= $member['total_pinjam'] ?>x</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($active_members)): ?>
                            <div class="list-group-item border-0 text-center py-4">
                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada data anggota</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Denda Tertinggi -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Denda Tertinggi Bulan Ini
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_fines as $index => $fine): ?>
                            <div class="list-group-item border-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong class="d-block"><?= htmlspecialchars($fine['nama']) ?></strong>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($fine['created_at'])) ?></small>
                                    </div>
                                    <span class="badge badge-modern bg-danger ms-2"><?= formatRupiah($fine['denda']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_fines)): ?>
                            <div class="list-group-item border-0 text-center py-4">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada denda bulan ini</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Top Kategori dan Penerbit -->
    <div class="row g-4 mt-3">
        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-tags me-2 text-warning"></i>Top Kategori
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_categories as $index => $category): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge badge-modern bg-warning me-2"><?= $index + 1 ?></span>
                                        <?= htmlspecialchars($category['nama_kategori']) ?>
                                    </div>
                                    <span class="badge badge-modern bg-light text-dark"><?= $category['jumlah_buku'] ?> buku</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_categories)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-tags fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada data kategori</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-building me-2 text-purple"></i>Top Penerbit
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_publishers as $index => $publisher): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge badge-modern bg-purple me-2"><?= $index + 1 ?></span>
                                        <?= htmlspecialchars(substr($publisher['nama_penerbit'], 0, 25)) ?>
                                    </div>
                                    <span class="badge badge-modern bg-light text-dark"><?= $publisher['jumlah_buku'] ?> buku</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_publishers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-building fa-2x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Belum ada data penerbit</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_stats, 'bulan_nama')) ?>,
        datasets: [
            {
                label: 'Peminjaman',
                data: <?= json_encode(array_column($monthly_stats, 'total_pinjam')) ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            },
            {
                label: 'Pengembalian',
                data: <?= json_encode(array_column($monthly_stats, 'total_kembali')) ?>,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            },
            {
                label: 'Denda (Ribuan)',
                data: <?= json_encode(array_map(function($d) { return $d['total_denda'] / 1000; }, $monthly_stats)) ?>,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
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
                    text: 'Jumlah Transaksi'
                },
                grid: {
                    drawBorder: false
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Denda (x1000)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            },
            x: {
                grid: {
                    drawBorder: false
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        }
    }
});

// Add hover effects to buttons
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.btn-modern');
    buttons.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
            this.style.boxShadow = '0 8px 20px rgba(0, 0, 0, 0.15)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>