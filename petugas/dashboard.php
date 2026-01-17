<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Cek login dan role
requireRole('petugas');

$page_title = 'Dashboard Petugas';
include '../config/database.php';

// Variabel untuk header
$current_user = getCurrentUser();
$is_petugas = true;
$is_admin = false;
$is_anggota = false;

try {
    // Statistik Umum
    $stats = [];
    
    // Total Buku
    $stmt = $conn->query("SELECT COUNT(*) FROM buku");
    $stats['total_buku'] = $stmt->fetchColumn();
    
    // Buku Tersedia - KOREKSI: kolom 'stok' tidak ada, gunakan 'stok_tersedia'
    $stmt = $conn->query("SELECT COUNT(*) FROM buku WHERE stok_tersedia > 0");
    $stats['buku_tersedia'] = $stmt->fetchColumn();
    
    // Total Anggota
    $stmt = $conn->query("SELECT COUNT(*) FROM anggota");
    $stats['total_anggota'] = $stmt->fetchColumn();
    
    // Buku Dipinjam (Aktif)
    $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'");
    $stats['dipinjam'] = $stmt->fetchColumn();
    
    // Buku Terlambat - JOIN dengan pengembalian
    $stmt = $conn->query("
        SELECT COUNT(*) FROM peminjaman p
        WHERE p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE()
    ");
    $stats['terlambat'] = $stmt->fetchColumn();
    
    // Total Denda Belum Lunas - dari tabel pengembalian
    $stmt = $conn->query("
        SELECT COALESCE(SUM(pg.denda), 0) FROM pengembalian pg
        WHERE pg.denda > 0
    ");
    $stats['denda_belum_lunas'] = $stmt->fetchColumn();
    
    // Transaksi Hari Ini
    $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE DATE(created_at) = CURDATE()");
    $stats['transaksi_hari_ini'] = $stmt->fetchColumn();
    
    // Peminjaman Hari Ini dengan JOIN penerbit
    $query = "
        SELECT p.*, a.nama, b.judul, b.pengarang, pn.nama_penerbit
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
        WHERE DATE(p.created_at) = CURDATE()
        ORDER BY p.created_at DESC
        LIMIT 5
    ";
    $stmt = $conn->query($query);
    $transaksi_hari_ini = $stmt->fetchAll();
    
    // Buku yang akan jatuh tempo (3 hari ke depan)
    $query = "
        SELECT p.*, a.nama, a.no_hp, b.judul, b.pengarang, pn.nama_penerbit,
               DATEDIFF(p.tanggal_kembali, CURDATE()) as hari_tersisa
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit pn ON b.id_penerbit = pn.id_penerbit
        WHERE p.status = 'dipinjam' 
        AND p.tanggal_kembali BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ORDER BY p.tanggal_kembali ASC
    ";
    $stmt = $conn->query($query);
    $jatuh_tempo = $stmt->fetchAll();
    
    // Peminjaman Terlambat dengan JOIN pengembalian untuk denda
    $query = "
        SELECT p.*, a.nama, a.no_hp, b.judul, pg.denda,
               DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE()
        ORDER BY p.tanggal_kembali ASC
        LIMIT 5
    ";
    $stmt = $conn->query($query);
    $terlambat_list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
    // Debug untuk mengetahui query yang bermasalah
    error_log("Dashboard Error: " . $e->getMessage());
}

// Fungsi formatRupiah jika belum ada di functions.php
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        if (empty($angka) || $angka == 0) {
            return 'Rp 0';
        }
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

include '../includes/header.php';

// Bulan dalam bahasa Indonesia
$bulan = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];

$hari = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];

$tanggal_formatted = strtr(date('l, d F Y'), array_merge($hari, $bulan));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petugas - Perpustakaan Nusantara</title>
    <style>
        body {
            background: #FFFDD0;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin: 20px auto;
            max-width: 1400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .welcome-text {
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
            border-color: var(--card-color);
        }

        .stat-card-blue { --card-color: #4F46E5; }
        .stat-card-green { --card-color: #10B981; }
        .stat-card-yellow { --card-color: #F59E0B; }
        .stat-card-red { --card-color: #EF4444; }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #6B7280;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .info-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: white;
        }

        .info-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            border: none;
            color: #6B7280;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 20px;
            background: #F9FAFB;
        }

        .table-modern tbody td {
            padding: 15px 20px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }

        .table-modern tbody tr:hover {
            background: #F9FAFB;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-primary { background: #4F46E5; color: white; }
        .badge-warning { background: #F59E0B; color: white; }
        .badge-danger { background: #EF4444; color: white; }
        .badge-success { background: #10B981; color: white; }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .opacity-90 { opacity: 0.9; }
        .opacity-85 { opacity: 0.85; }

        @media (max-width: 768px) {
            .dashboard-container { padding: 15px; margin: 10px; }
            .welcome-text { font-size: 1.5rem; }
            .stat-number { font-size: 2rem; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="welcome-text mb-2">
                    👋 Hai, <?= htmlspecialchars($_SESSION['nama']) ?>!
                </h1>
                <p class="mb-0 opacity-90" style="font-size: 1.1rem;">
                    Selamat datang di Dashboard Petugas
                </p>
            </div>
            <div class="text-end">
                <div style="font-size: 1.1rem; opacity: 0.95;">
                    <i class="fas fa-calendar-alt me-2"></i><?= $tanggal_formatted ?>
                </div>
                <div style="font-size: 0.95rem; opacity: 0.85;">
                    <i class="fas fa-clock me-2"></i><?= date('H:i') ?> WIB
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-card-blue">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4F46E5, #7C3AED);">📚</div>
                <div class="stat-number" style="color: #4F46E5;"><?= $stats['total_buku'] ?? 0 ?></div>
                <div class="stat-label">Total Buku</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-card-green">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">✅</div>
                <div class="stat-number" style="color: #10B981;"><?= $stats['buku_tersedia'] ?? 0 ?></div>
                <div class="stat-label">Buku Tersedia</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-card-yellow">
                <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">📖</div>
                <div class="stat-number" style="color: #F59E0B;"><?= $stats['dipinjam'] ?? 0 ?></div>
                <div class="stat-label">Sedang Dipinjam</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card stat-card-red">
                <div class="stat-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">⚠️</div>
                <div class="stat-number" style="color: #EF4444;"><?= $stats['terlambat'] ?? 0 ?></div>
                <div class="stat-label">Terlambat</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5 class="info-card-title">
                        <i class="fas fa-history me-2"></i>Transaksi Hari Ini
                        <span class="badge badge-primary ms-2"><?= $stats['transaksi_hari_ini'] ?? 0 ?></span>
                    </h5>
                </div>
                <div>
                    <?php if (!empty($transaksi_hari_ini)): ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_hari_ini as $row): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                                            <td>
                                                <small>
                                                    <?= htmlspecialchars(substr($row['judul'], 0, 40)) ?>
                                                    <?php if (!empty($row['nama_penerbit'])): ?>
                                                        <br><span class="text-muted"><?= htmlspecialchars($row['nama_penerbit']) ?></span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td><span class="badge badge-primary"><?= date('H:i', strtotime($row['created_at'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Belum ada transaksi hari ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="info-card">
                <div class="info-card-header">
                    <h5 class="info-card-title">
                        <i class="fas fa-clock me-2"></i>Akan Jatuh Tempo (3 Hari)
                    </h5>
                </div>
                <div>
                    <?php if (!empty($jatuh_tempo)): ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Sisa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jatuh_tempo as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                                <br><small class="text-muted">📱 <?= htmlspecialchars($row['no_hp'] ?? 'Tidak ada') ?></small>
                                            </td>
                                            <td><small><?= htmlspecialchars(substr($row['judul'], 0, 35)) ?></small></td>
                                            <td><span class="badge badge-warning"><?= $row['hari_tersisa'] ?> hari</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted">Tidak ada yang jatuh tempo</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($terlambat_list)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="info-card">
                    <div class="info-card-header">
                        <h5 class="info-card-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Peminjaman Terlambat
                            <span class="badge badge-danger ms-2"><?= $stats['terlambat'] ?? 0 ?></span>
                        </h5>
                    </div>
                    <div>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Batas Kembali</th>
                                        <th>Terlambat</th>
                                        <th>Denda</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($terlambat_list as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                                <br><small class="text-muted">📱 <?= htmlspecialchars($row['no_hp'] ?? 'Tidak ada') ?></small>
                                            </td>
                                            <td><?= htmlspecialchars(substr($row['judul'], 0, 40)) ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal_kembali'])) ?></td>
                                            <td><span class="badge badge-danger"><?= $row['hari_terlambat'] ?> hari</span></td>
                                            <td>
                                                <strong style="color: #EF4444;">
                                                    <?= formatRupiah($row['denda'] ?? ($row['hari_terlambat'] * 1000)) ?>
                                                </strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>