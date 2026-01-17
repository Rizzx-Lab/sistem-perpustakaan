<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Laporan Harian';
include '../../config/database.php';

// Get date filter
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    // Statistik Hari Ini
    $stats = [];
    
    // Peminjaman hari ini
    $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE DATE(created_at) = ?");
    $stmt->execute([$tanggal]);
    $stats['peminjaman'] = $stmt->fetchColumn();
    
    // UPDATED: Pengembalian hari ini dari tabel pengembalian
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pengembalian WHERE DATE(tanggal_pengembalian_aktual) = ?");
    $stmt->execute([$tanggal]);
    $stats['pengembalian'] = $stmt->fetchColumn();
    
    // UPDATED: Total denda dikumpulkan dari tabel pengembalian
    $stmt = $conn->prepare("SELECT COALESCE(SUM(denda), 0) FROM pengembalian WHERE DATE(tanggal_pengembalian_aktual) = ?");
    $stmt->execute([$tanggal]);
    $stats['denda'] = $stmt->fetchColumn();
    
    // Total transaksi
    $stats['total_transaksi'] = $stats['peminjaman'] + $stats['pengembalian'];
    
    // Detail Peminjaman
    // UPDATED: JOIN dengan penerbit
    $query = "
        SELECT p.*, 
               a.nama, a.nik, 
               b.judul, b.pengarang,
               penerbit.nama_penerbit
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit ON b.id_penerbit = penerbit.id_penerbit
        WHERE DATE(p.created_at) = ?
        ORDER BY p.created_at DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal]);
    $peminjaman = $stmt->fetchAll();
    
    // UPDATED: Detail Pengembalian dari tabel pengembalian
    $query = "
        SELECT pg.*, 
               p.id_peminjaman,
               p.tanggal_pinjam,
               p.tanggal_kembali,
               a.nama, a.nik, 
               b.judul, b.pengarang,
               penerbit.nama_penerbit,
               DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali) as hari_terlambat
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit ON b.id_penerbit = penerbit.id_penerbit
        WHERE DATE(pg.tanggal_pengembalian_aktual) = ?
        ORDER BY pg.tanggal_pengembalian_aktual DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal]);
    $pengembalian = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = 'Error: ' . $e->getMessage();
    $stats = ['peminjaman' => 0, 'pengembalian' => 0, 'denda' => 0, 'total_transaksi' => 0];
    $peminjaman = [];
    $pengembalian = [];
}

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= asset_url('petugas/dashboard.php') ?>"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Harian</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📋</span>
                <span class="title-gradient">Laporan Transaksi Harian</span>
            </h1>
        </div>
        <button onclick="cetakLaporan()" class="btn btn-primary-modern">
            <i class="fas fa-print me-2"></i>Cetak Laporan
        </button>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Tanggal -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Pilih Tanggal</label>
                    <input type="date" name="tanggal" class="form-control-modern" 
                           value="<?= $tanggal ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-modern w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="harian.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon bg-primary mb-2">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total_transaksi'] ?></div>
                <small class="stat-label">Total Transaksi</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon bg-info mb-2">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number text-info"><?= $stats['peminjaman'] ?></div>
                <small class="stat-label">Peminjaman</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon bg-success mb-2">
                    <i class="fas fa-undo"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['pengembalian'] ?></div>
                <small class="stat-label">Pengembalian</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon bg-warning mb-2">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-number text-warning"><?= formatRupiah($stats['denda']) ?></div>
                <small class="stat-label">Total Denda</small>
            </div>
        </div>
    </div>

    <!-- Content Laporan -->
    <div id="printable-area">
        <!-- Header untuk Print -->
        <div class="print-header" style="display: none;">
            <div class="text-center mb-4">
                <h3>Laporan Transaksi Harian</h3>
                <p class="mb-0">Tanggal: <?= formatTanggal($tanggal) ?></p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Peminjaman -->
            <div class="col-lg-6">
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern mb-0">
                            <i class="fas fa-book me-2"></i>Peminjaman (<?= $stats['peminjaman'] ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($peminjaman)): ?>
                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Anggota</th>
                                            <th>Buku</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($peminjaman as $row): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars($row['nama']) ?></small>
                                                    <br><small class="text-muted"><?= $row['nik'] ?></small>
                                                </td>
                                                <td>
                                                    <small><?= htmlspecialchars($row['judul']) ?></small>
                                                    <?php if ($row['nama_penerbit']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($row['nama_penerbit']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= date('H:i', strtotime($row['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada peminjaman</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pengembalian -->
            <div class="col-lg-6">
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h5 class="card-title-modern mb-0">
                            <i class="fas fa-undo me-2"></i>Pengembalian (<?= $stats['pengembalian'] ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($pengembalian)): ?>
                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Anggota</th>
                                            <th>Buku</th>
                                            <th>Kondisi</th>
                                            <th>Denda</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($pengembalian as $row): ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars($row['nama']) ?></small>
                                                    <br><small class="text-muted"><?= $row['nik'] ?></small>
                                                </td>
                                                <td>
                                                    <small><?= htmlspecialchars($row['judul']) ?></small>
                                                    <?php if ($row['nama_penerbit']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($row['nama_penerbit']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($row['hari_terlambat'] > 0): ?>
                                                        <br><span class="badge bg-danger">+<?= $row['hari_terlambat'] ?> hari</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $kondisi_badges = [
                                                        'baik' => 'success',
                                                        'rusak_ringan' => 'warning',
                                                        'rusak_berat' => 'danger'
                                                    ];
                                                    $badge = $kondisi_badges[$row['kondisi_buku']] ?? 'secondary';
                                                    $kondisi_text = [
                                                        'baik' => 'Baik',
                                                        'rusak_ringan' => 'Rusak Ringan',
                                                        'rusak_berat' => 'Rusak Berat'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $badge ?>"><?= $kondisi_text[$row['kondisi_buku']] ?? '-' ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($row['denda'] > 0): ?>
                                                        <small class="text-danger fw-bold"><?= formatRupiah($row['denda']) ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada pengembalian</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    
    #printable-area, #printable-area * {
        visibility: visible;
    }
    
    #printable-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .print-header {
        display: block !important;
    }
    
    .modern-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
    }
    
    .no-print {
        display: none !important;
    }
}
</style>

<script>
function cetakLaporan() {
    window.print();
}
</script>

<?php include '../../includes/footer.php'; ?>