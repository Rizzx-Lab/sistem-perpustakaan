<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Cek Status Buku';
include '../../config/database.php';

$search = $_GET['search'] ?? '';
$buku_list = [];
$search_performed = false;

if (!empty($search)) {
    $search_performed = true;
    try {
        // Query dengan JOIN penerbit, rak, dan kategori - PERBAIKAN: gunakan stok_tersedia bukan stok
        $query = "
            SELECT b.*, 
                   pn.nama_penerbit,
                   r.kode_rak,
                   r.lokasi,
                   (SELECT GROUP_CONCAT(k.nama_kategori SEPARATOR ', ')
                    FROM buku_kategori bk
                    JOIN kategori k ON bk.id_kategori = k.id_kategori
                    WHERE bk.isbn = b.isbn) as kategori_list,
                   (SELECT COUNT(*) FROM peminjaman WHERE isbn = b.isbn AND status = 'dipinjam') as sedang_dipinjam,
                   (SELECT COUNT(*) FROM peminjaman WHERE isbn = b.isbn) as total_peminjaman,
                   b.stok_tersedia as stok_tersedia
            FROM buku b
            LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
            LEFT JOIN rak r ON b.id_rak = r.id_rak
            WHERE b.isbn LIKE ? OR b.judul LIKE ? OR b.pengarang LIKE ?
            ORDER BY b.judul ASC
        ";
        $searchParam = "%{$search}%";
        $stmt = $conn->prepare($query);
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
        $buku_list = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
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

.btn-primary-modern {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-color: #007bff;
    color: white;
}

.btn-primary-modern:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    border-color: #0056b3;
    color: white;
}

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk statistik */
.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Perbaikan untuk list group */
.list-group-item {
    border: none;
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.list-group-item:last-child {
    border-bottom: none;
}

/* Perbaikan untuk border-left */
.border-start {
    border-left: 2px solid #dee2e6 !important;
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
    }
    
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }
    
    .border-start {
        border-left: none !important;
        border-top: 2px solid #dee2e6;
        margin-top: 20px;
        padding-top: 20px;
    }
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= SITE_URL ?>petugas/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Cek Status</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="fas fa-search me-2 text-primary"></i>Cek Status Buku
            </h1>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control-modern" 
                               placeholder="Masukkan ISBN, Judul, atau Pengarang buku..."
                               value="<?= htmlspecialchars($search) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-modern w-100">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ($search_performed): ?>
        <?php if (!empty($buku_list)): ?>
            <?php foreach ($buku_list as $buku): ?>
                <div class="modern-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($buku['judul']) ?></h5>
                                        <p class="text-muted mb-0">
                                            <small><i class="fas fa-user me-1"></i><?= htmlspecialchars($buku['pengarang']) ?></small>
                                        </p>
                                    </div>
                                    <?php if (($buku['stok_tersedia'] ?? 0) > 0): ?>
                                        <span class="badge bg-success">Tersedia</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Tidak Tersedia</span>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">ISBN</small>
                                        <div class="fw-semibold"><?= htmlspecialchars($buku['isbn']) ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Penerbit</small>
                                        <div class="fw-semibold"><?= htmlspecialchars($buku['nama_penerbit'] ?? '-') ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Tahun Terbit</small>
                                        <div class="fw-semibold"><?= $buku['tahun_terbit'] ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Stok Tersedia</small>
                                        <div class="fw-semibold">
                                            <span class="badge bg-<?= ($buku['stok_tersedia'] ?? 0) > 0 ? 'primary' : 'secondary' ?>">
                                                <?= $buku['stok_tersedia'] ?? 0 ?> buku
                                            </span>
                                        </div>
                                    </div>
                                    <?php if (!empty($buku['kode_rak'])): ?>
                                        <div class="col-6">
                                            <small class="text-muted">Lokasi Rak</small>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($buku['kode_rak']) ?>
                                                <?php if (!empty($buku['lokasi'])): ?>
                                                    - <?= htmlspecialchars($buku['lokasi']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($buku['kategori_list'])): ?>
                                        <div class="col-6">
                                            <small class="text-muted">Kategori</small>
                                            <div class="fw-semibold">
                                                <small><?= htmlspecialchars(substr($buku['kategori_list'], 0, 30)) ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="stat-number text-warning"><?= $buku['sedang_dipinjam'] ?></div>
                                            <small class="stat-label">Sedang Dipinjam</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="stat-number text-info"><?= $buku['total_peminjaman'] ?></div>
                                            <small class="stat-label">Total Peminjaman</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Daftar Peminjam Aktif -->
                            <div class="col-md-4">
                                <?php if ($buku['sedang_dipinjam'] > 0): ?>
                                    <?php
                                    $queryPeminjam = "
                                        SELECT p.*, a.nama, a.nik, a.no_hp,
                                               DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
                                        FROM peminjaman p
                                        JOIN anggota a ON p.nik = a.nik
                                        WHERE p.isbn = ? AND p.status = 'dipinjam'
                                        ORDER BY p.tanggal_pinjam DESC
                                    ";
                                    $stmtPeminjam = $conn->prepare($queryPeminjam);
                                    $stmtPeminjam->execute([$buku['isbn']]);
                                    $peminjam = $stmtPeminjam->fetchAll();
                                    ?>
                                    <div class="border-start ps-3">
                                        <h6 class="fw-semibold mb-3">
                                            <i class="fas fa-users me-1 text-primary"></i>Sedang Dipinjam Oleh:
                                        </h6>
                                        <?php foreach ($peminjam as $p): ?>
                                            <div class="mb-3 p-2 bg-light rounded">
                                                <div class="fw-semibold"><?= htmlspecialchars($p['nama']) ?></div>
                                                <small class="text-muted d-block">NIK: <?= htmlspecialchars($p['nik']) ?></small>
                                                <small class="text-muted d-block">HP: <?= htmlspecialchars($p['no_hp']) ?></small>
                                                <small class="text-muted d-block">
                                                    Kembali: <?= formatTanggal($p['tanggal_kembali']) ?>
                                                </small>
                                                <?php if ($p['hari_terlambat'] > 0): ?>
                                                    <span class="badge bg-danger mt-1">
                                                        Terlambat <?= $p['hari_terlambat'] ?> hari
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success mt-1">Tepat Waktu</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4 bg-light rounded">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p class="text-muted mb-0">Tidak ada yang meminjam</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="modern-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Buku Tidak Ditemukan</h5>
                    <p class="text-muted mb-0">Tidak ada buku yang sesuai dengan pencarian "<?= htmlspecialchars($search) ?>"</p>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="modern-card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book fa-3x text-primary mb-3"></i>
                <h5>Cari Status Buku</h5>
                <p class="text-muted mb-0">Gunakan form pencarian di atas untuk mengecek status dan ketersediaan buku</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>