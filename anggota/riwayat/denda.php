<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Riwayat Denda';
include '../../config/database.php';

// Get user's NIK
try {
    $stmt = $conn->prepare("SELECT nik FROM anggota WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        setFlashMessage('Data anggota tidak ditemukan', 'error');
        redirect(SITE_URL . 'anggota/dashboard.php');
    }
    
    $nik = $user_data['nik'];
    
    // Get denda per hari setting
    $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_per_hari'");
    $denda_per_hari = (int)$stmt->fetchColumn() ?: 1000;
    
    // Get denda buku hilang setting
    $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_hilang'");
    $denda_hilang = (int)$stmt->fetchColumn() ?: 50000;
    
    // Get denda rusak parah setting
    $stmt = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_rusak_parah'");
    $denda_rusak_parah = (int)$stmt->fetchColumn() ?: 25000;
    
    // Filter
    $status_filter = $_GET['status'] ?? '';
    
    // QUERY TERPADU: Denda Keterlambatan + Denda Buku Hilang/Rusak
    $query_denda_keterlambatan = "
        SELECT 
            p.id_peminjaman,
            'keterlambatan' as jenis_denda,
            b.judul,
            b.pengarang,
            b.isbn,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            pg.tanggal_pengembalian_aktual,
            pg.denda as denda_dibayar,
            pg.kondisi_buku,
            CASE 
                WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                THEN DATEDIFF(CURDATE(), p.tanggal_kembali)
                WHEN p.status = 'dikembalikan' AND pg.tanggal_pengembalian_aktual > p.tanggal_kembali
                THEN DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali)
                ELSE 0
            END as hari_terlambat,
            CASE 
                WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                THEN DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari}
                WHEN p.status = 'dikembalikan'
                THEN COALESCE(pg.denda, 0)
                ELSE 0
            END as denda_aktual,
            CASE 
                WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                THEN 'belum_lunas'
                WHEN p.status = 'dikembalikan' AND pg.denda > 0
                THEN 'lunas'
                ELSE 'no_denda'
            END as status_denda,
            NULL as tanggal_laporan,
            NULL as status_buku,
            NULL as alasan
        FROM peminjaman p
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.nik = ?
        HAVING denda_aktual > 0
    ";
    
    $query_denda_hilang = "
        SELECT 
            bh.id_hilang as id_peminjaman,
            'buku_hilang' as jenis_denda,
            b.judul,
            b.pengarang,
            b.isbn,
            p.tanggal_pinjam,
            p.tanggal_kembali,
            p.status,
            NULL as tanggal_pengembalian_aktual,
            bh.denda_hilang as denda_dibayar,
            NULL as kondisi_buku,
            0 as hari_terlambat,
            bh.denda_hilang as denda_aktual,
            'lunas' as status_denda,
            bh.tanggal_laporan,
            bh.status as status_buku,
            bh.alasan
        FROM buku_hilang bh
        JOIN peminjaman p ON bh.id_peminjaman = p.id_peminjaman
        JOIN buku b ON p.isbn = b.isbn
        WHERE p.nik = ?
    ";
    
    // Gabungkan kedua query
    $query = "($query_denda_keterlambatan) UNION ALL ($query_denda_hilang)";
    
    // Tambahkan parameter
    $params = [$nik, $nik];
    
    // Filter berdasarkan status pembayaran
    if ($status_filter === 'lunas') {
        $query .= " WHERE status_denda = 'lunas'";
    } elseif ($status_filter === 'belum_lunas') {
        $query .= " WHERE status_denda = 'belum_lunas'";
    }
    
    $query .= " ORDER BY 
        CASE 
            WHEN status_denda = 'belum_lunas' THEN 0 
            ELSE 1 
        END,
        COALESCE(tanggal_laporan, tanggal_pinjam) DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $denda_list = $stmt->fetchAll();
    
    // STATISTIK TERPADU
    // Total denda keterlambatan
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE 
                WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                THEN DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari}
                WHEN p.status = 'dikembalikan'
                THEN COALESCE(pg.denda, 0)
                ELSE 0
            END) as total_denda_keterlambatan
        FROM peminjaman p
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.nik = ?
    ");
    $stmt->execute([$nik]);
    $total_denda_keterlambatan = $stmt->fetchColumn() ?: 0;
    
    // Total denda buku hilang/rusak
    $stmt = $conn->prepare("
        SELECT SUM(bh.denda_hilang) as total_denda_hilang
        FROM buku_hilang bh
        JOIN peminjaman p ON bh.id_peminjaman = p.id_peminjaman
        WHERE p.nik = ?
    ");
    $stmt->execute([$nik]);
    $total_denda_hilang = $stmt->fetchColumn() ?: 0;
    
    // Denda belum lunas (keterlambatan saja)
    $stmt = $conn->prepare("
        SELECT 
            SUM(DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari}) as total_belum_lunas
        FROM peminjaman p
        WHERE p.nik = ? AND p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE()
    ");
    $stmt->execute([$nik]);
    $total_belum_lunas = $stmt->fetchColumn() ?: 0;
    
    // Total keseluruhan
    $total_denda = $total_denda_keterlambatan + $total_denda_hilang;
    $total_lunas = $total_denda - $total_belum_lunas;
    $total_transaksi = count($denda_list);
    
    $stats = [
        'total_denda' => $total_denda,
        'belum_lunas' => $total_belum_lunas,
        'lunas' => $total_lunas,
        'transaksi' => $total_transaksi,
        'denda_hilang' => $total_denda_hilang
    ];
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
    $denda_list = [];
    $stats = [
        'total_denda' => 0, 
        'belum_lunas' => 0, 
        'lunas' => 0, 
        'transaksi' => 0,
        'denda_hilang' => 0
    ];
}

include '../../includes/header.php';
?>

<style>
/* ================================
   GANTI SELURUH SECTION <style> DI denda.php
   DENGAN CSS INI (dari @import sampai akhir)
   ================================ */

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

/* ===== FIX BACKGROUND - SMOOTH GRADIENT ===== */
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    background-attachment: fixed !important;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.05) 0%, transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 40%);
    animation: float 20s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.container {
    position: relative;
    z-index: 1;
}

.page-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff 0%, #ffd89b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
}

.breadcrumb-item a:hover {
    color: #ffd89b;
}

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.7);
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color-1), var(--stat-color-2));
}

.stat-card:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.stat-card.blue { --stat-color-1: #667eea; --stat-color-2: #764ba2; }
.stat-card.purple { --stat-color-1: #f093fb; --stat-color-2: #f5576c; }
.stat-card.red { --stat-color-1: #eb3349; --stat-color-2: #f45c43; }
.stat-card.green { --stat-color-1: #11998e; --stat-color-2: #38ef7d; }

.stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.stat-icon i { color: white; font-size: 1.8rem; }

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label {
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
}

.filter-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.filter-select {
    padding: 0.8rem 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
    width: 100%;
}

.filter-select:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 3px 15px rgba(102, 126, 234, 0.15);
}

.btn-filter {
    padding: 0.8rem 2rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-filter:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-reset {
    padding: 0.8rem 2rem;
    background: white;
    color: #ff6b6b;
    border: 2px solid #ff6b6b;
    border-radius: 15px;
    font-weight: 600;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-reset:hover {
    background: #ff6b6b;
    color: white;
    transform: scale(1.05);
}

.table-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.table-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.5rem;
    color: white;
}

.table-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.3rem;
}

/* ===== FIX TABLE - SIMETRIS & RAPI ===== */
.table-modern {
    width: 100%;
    margin: 0;
}

.table-modern thead {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.table-modern thead th {
    padding: 1rem 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    color: #495057;
    border: none;
    text-align: center;
    vertical-align: middle;
}

.table-modern tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
    color: #333;
    text-align: center;
}

/* Kolom Buku - align left */
.table-modern tbody td:nth-child(2) {
    text-align: left;
}

.table-modern tbody tr {
    transition: all 0.3s ease;
}

.table-modern tbody tr:hover {
    background: #f8f9ff;
    transform: scale(1.01);
}

.book-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.2rem;
}

.book-author {
    font-size: 0.85rem;
    color: #666;
}

.badge-modern {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-block;
}

.badge-warning-modern {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.badge-success-modern {
    background: linear-gradient(135deg, #11998e, #38ef7d);
    color: white;
}

.badge-danger-modern {
    background: linear-gradient(135deg, #eb3349, #f45c43);
    color: white;
}

.badge-info-modern {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.badge-late {
    background: #ff6b6b;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.3rem;
    display: inline-block;
}

.denda-text {
    color: #eb3349;
    font-weight: 700;
    font-size: 0.95rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #11998e, #38ef7d);
    box-shadow: 0 15px 40px rgba(17, 153, 142, 0.3);
}

.empty-state-icon i { color: white; font-size: 4rem; }

.empty-state h6 {
    font-weight: 700;
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.empty-btn {
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    margin-top: 1rem;
}

.empty-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    color: white;
}

/* Animations */
.stat-card {
    animation: fadeInUp 0.6s ease;
    animation-fill-mode: both;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filter-card {
    animation: fadeInUp 0.6s ease 0.5s;
    animation-fill-mode: both;
}

.table-card {
    animation: fadeInUp 0.6s ease 0.6s;
    animation-fill-mode: both;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .table-responsive {
        border-radius: 0;
    }
    
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .table-modern thead th,
    .table-modern tbody td {
        padding: 0.75rem 0.5rem;
        font-size: 0.85rem;
    }
}

/* Custom styles for buku hilang */
.badge-hilang {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    color: white;
}

.btn-info-small {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.alert-buku-hilang {
    background: rgba(255, 193, 7, 0.15);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 15px;
}
</style>

<div class="container py-4">
    <div class="page-header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                </li>
                <li class="breadcrumb-item active">Riwayat Denda</li>
            </ol>
        </nav>
        <h1>
            <i class="fas fa-money-bill-wave"></i> Riwayat Denda
        </h1>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Error!</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-number"><?= $stats['transaksi'] ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card purple">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-number" style="font-size: 1.5rem;"><?= formatRupiah($stats['total_denda']) ?></div>
                <div class="stat-label">Total Denda</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-number" style="font-size: 1.5rem;"><?= formatRupiah($stats['belum_lunas']) ?></div>
                <div class="stat-label">Belum Lunas</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number" style="font-size: 1.5rem;"><?= formatRupiah($stats['lunas']) ?></div>
                <div class="stat-label">Sudah Lunas</div>
            </div>
        </div>
    </div>

    <?php if ($stats['denda_hilang'] > 0): ?>
    <div class="alert alert-warning alert-buku-hilang mb-4">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
            <div>
                <h6 class="fw-bold mb-1">Denda Buku Hilang/Rusak</h6>
                <p class="mb-0">
                    Anda memiliki denda buku hilang/rusak sebesar 
                    <span class="fw-bold text-danger"><?= formatRupiah($stats['denda_hilang']) ?></span>.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter Status</label>
                <select name="status" class="filter-select">
                    <option value="">Semua Status</option>
                    <option value="belum_lunas" <?= $status_filter === 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                    <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : '' ?>>Sudah Lunas</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
            </div>
            <?php if (!empty($status_filter)): ?>
                <div class="col-md-3">
                    <a href="denda.php" class="btn-reset">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-card">
        <div class="table-card-header">
            <h5><i class="fas fa-list-alt me-2"></i>Daftar Denda</h5>
        </div>
        <div>
            <?php if (!empty($denda_list)): ?>
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Buku</th>
                                <th>Jenis Denda</th>
                                <th>Tgl Pinjam</th>
                                <th>Batas Kembali</th>
                                <th>Tgl Dikembalikan</th>
                                <th>Terlambat</th>
                                <th>Status</th>
                                <th>Denda</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($denda_list as $row): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="book-title"><?= htmlspecialchars($row['judul']) ?></div>
                                        <div class="book-author">
                                            <i class="fas fa-user me-1"></i>
                                            <?= htmlspecialchars($row['pengarang']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['jenis_denda'] === 'buku_hilang'): ?>
                                            <span class="badge-modern badge-hilang">
                                                <?php if ($row['status_buku'] === 'hilang'): ?>
                                                    <i class="fas fa-book-dead me-1"></i> Hilang
                                                <?php else: ?>
                                                    <i class="fas fa-book-medical me-1"></i> Rusak Parah
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-warning-modern">
                                                <i class="fas fa-clock me-1"></i> Keterlambatan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatTanggal($row['tanggal_pinjam']) ?></td>
                                    <td>
                                        <?= formatTanggal($row['tanggal_kembali']) ?>
                                        <?php if ($row['jenis_denda'] === 'keterlambatan' && $row['status_denda'] === 'belum_lunas' && $row['hari_terlambat'] > 0): ?>
                                            <br><span class="badge-late">
                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                +<?= $row['hari_terlambat'] ?> hari
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['tanggal_pengembalian_aktual'])): ?>
                                            <?= formatTanggal($row['tanggal_pengembalian_aktual']) ?>
                                        <?php elseif (!empty($row['tanggal_laporan'])): ?>
                                            <span class="text-muted" style="font-size: 0.85rem;">
                                                Dilaporkan: <?= formatTanggal($row['tanggal_laporan']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['jenis_denda'] === 'keterlambatan' && $row['hari_terlambat'] > 0): ?>
                                            <span class="badge-late">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= $row['hari_terlambat'] ?> hari
                                            </span>
                                        <?php elseif ($row['jenis_denda'] === 'buku_hilang'): ?>
                                            <span class="badge-modern badge-danger-modern">
                                                <?php if ($row['status_buku'] === 'hilang'): ?>
                                                    <i class="fas fa-times-circle me-1"></i> HILANG
                                                <?php else: ?>
                                                    <i class="fas fa-band-aid me-1"></i> RUSAK PARAH
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status_denda'] === 'belum_lunas'): ?>
                                            <span class="badge-modern badge-danger-modern">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Belum Lunas
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-success-modern">
                                                <i class="fas fa-check-circle me-1"></i> Lunas
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="denda-text">
                                            <i class="fas fa-money-bill-wave me-1"></i>
                                            <?= formatRupiah($row['denda_aktual']) ?>
                                        </span>
                                        <?php if ($row['status_denda'] === 'belum_lunas'): ?>
                                            <br><small class="text-muted" style="font-size: 0.75rem;">*terus berjalan</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['jenis_denda'] === 'buku_hilang' && !empty($row['alasan'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-info btn-info-small" 
                                                    data-bs-toggle="popover" 
                                                    data-bs-placement="left"
                                                    data-bs-title="Alasan <?= $row['status_buku'] === 'hilang' ? 'Hilang' : 'Rusak' ?>"
                                                    data-bs-content="<?= htmlspecialchars($row['alasan']) ?>">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        <?php elseif (!empty($row['kondisi_buku']) && $row['kondisi_buku'] !== 'baik'): ?>
                                            <span class="badge-modern badge-info-modern">
                                                <i class="fas fa-file-alt me-1"></i>
                                                <?= ucfirst(str_replace('_', ' ', $row['kondisi_buku'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h6>Tidak Ada Denda</h6>
                    <p class="text-muted">Anda tidak memiliki riwayat denda keterlambatan atau buku hilang. Pertahankan!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelector('select[name="status"]')?.addEventListener('change', function() {
    this.form.submit();
});

// Inisialisasi popover untuk alasan buku hilang
document.addEventListener('DOMContentLoaded', function() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<?php include '../../includes/footer.php'; ?>