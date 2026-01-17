<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Laporan Buku Hilang/Rusak';
include '../../config/database.php';

// Filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($date_from)) {
    $where_conditions[] = "bh.tanggal_laporan >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "bh.tanggal_laporan <= ?";
    $params[] = $date_to;
}

if (!empty($status)) {
    $where_conditions[] = "bh.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(a.nama LIKE ? OR b.judul LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get lost/damaged books report
$stmt = $conn->prepare("
    SELECT bh.*, 
           p.tanggal_pinjam, p.tanggal_kembali,
           a.nama, a.nik,
           b.judul, b.isbn, b.pengarang, b.tahun_terbit,
           p2.nama_penerbit,
           bh.denda_hilang as total_denda
    FROM buku_hilang bh
    JOIN peminjaman p ON bh.id_peminjaman = p.id_peminjaman
    JOIN anggota a ON p.nik = a.nik
    JOIN buku b ON p.isbn = b.isbn
    LEFT JOIN penerbit p2 ON b.id_penerbit = p2.id_penerbit
    WHERE $where_clause
    ORDER BY bh.tanggal_laporan DESC, bh.created_at DESC
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM buku_hilang")->fetchColumn(),
    'hilang' => $conn->query("SELECT COUNT(*) FROM buku_hilang WHERE status = 'hilang'")->fetchColumn(),
    'rusak_parah' => $conn->query("SELECT COUNT(*) FROM buku_hilang WHERE status = 'rusak_parah'")->fetchColumn(),
    'total_denda' => $conn->query("SELECT SUM(denda_hilang) FROM buku_hilang")->fetchColumn() ?: 0,
    'total_stok_berkurang' => $conn->query("SELECT COUNT(*) FROM buku_hilang")->fetchColumn()
];

// Monthly statistics
$monthly_query = "
    SELECT 
        DATE_FORMAT(tanggal_laporan, '%Y-%m') as bulan,
        COUNT(*) as jumlah,
        SUM(denda_hilang) as total_denda
    FROM buku_hilang
    WHERE tanggal_laporan >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_laporan, '%Y-%m')
    ORDER BY bulan DESC
";
$monthly_stats = $conn->query($monthly_query)->fetchAll();

include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php">Laporan</a>
                    </li>
                    <li class="breadcrumb-item active">Buku Hilang/Rusak</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📊</span>
                <span class="title-gradient">Laporan Buku Hilang/Rusak</span>
            </h1>
            <p class="text-muted mb-0">Laporan buku yang hilang atau rusak parah</p>
        </div>
        <div>
            <button type="button" class="btn btn-modern btn-primary" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                </div>
                <div class="stat-number text-danger"><?= $stats['total'] ?></div>
                <small class="stat-label">Total Laporan</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-question-circle text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['hilang'] ?></div>
                <small class="stat-label">Buku Hilang</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-ban text-secondary"></i>
                </div>
                <div class="stat-number text-secondary"><?= $stats['rusak_parah'] ?></div>
                <small class="stat-label">Rusak Parah</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-money-bill-wave text-success"></i>
                </div>
                <div class="stat-number text-success"><?= formatRupiah($stats['total_denda']) ?></div>
                <small class="stat-label">Total Denda</small>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">Tanggal Dari</label>
                    <input type="date" name="date_from" class="form-control-modern" 
                           value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-modern">Tanggal Sampai</label>
                    <input type="date" name="date_to" class="form-control-modern" 
                           value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Status</label>
                    <select name="status" class="form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="hilang" <?= $status === 'hilang' ? 'selected' : '' ?>>Hilang</option>
                        <option value="rusak_parah" <?= $status === 'rusak_parah' ? 'selected' : '' ?>>Rusak Parah</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern">Pencarian</label>
                    <input type="text" name="search" class="form-control-modern" 
                           placeholder="Nama/ISBN/Judul..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label-modern invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-modern flex-fill">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <a href="buku_hilang.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Statistics -->
    <?php if ($monthly_stats): ?>
        <div class="modern-card mb-4">
            <div class="card-header-modern">
                <h5 class="card-title-modern mb-0">
                    <i class="fas fa-chart-line me-2"></i>Statistik 6 Bulan Terakhir
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th>Jumlah Laporan</th>
                                <th>Hilang</th>
                                <th>Rusak Parah</th>
                                <th>Total Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // We need to get detailed monthly stats
                            $detailed_monthly = [];
                            foreach ($monthly_stats as $month) {
                                $stmt = $conn->prepare("
                                    SELECT 
                                        COUNT(*) as total,
                                        SUM(CASE WHEN status = 'hilang' THEN 1 ELSE 0 END) as hilang,
                                        SUM(CASE WHEN status = 'rusak_parah' THEN 1 ELSE 0 END) as rusak
                                    FROM buku_hilang
                                    WHERE DATE_FORMAT(tanggal_laporan, '%Y-%m') = ?
                                ");
                                $stmt->execute([$month['bulan']]);
                                $details = $stmt->fetch();
                                
                                $detailed_monthly[] = [
                                    'bulan' => $month['bulan'],
                                    'total' => $details['total'],
                                    'hilang' => $details['hilang'],
                                    'rusak' => $details['rusak'],
                                    'denda' => $month['total_denda']
                                ];
                            }
                            ?>
                            
                            <?php foreach ($detailed_monthly as $month): ?>
                                <tr>
                                    <td><strong><?= date('F Y', strtotime($month['bulan'] . '-01')) ?></strong></td>
                                    <td><?= $month['total'] ?></td>
                                    <td><?= $month['hilang'] ?></td>
                                    <td><?= $month['rusak'] ?></td>
                                    <td class="fw-bold text-danger"><?= formatRupiah($month['denda']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Report Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Data Laporan
                <small class="text-muted">(<?= count($reports) ?> laporan)</small>
            </h5>
            <div>
                <span class="badge bg-info">
                    Total Denda: <?= formatRupiah(array_sum(array_column($reports, 'total_denda'))) ?>
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($reports): ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0" id="reportTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Anggota</th>
                                <th>Buku</th>
                                <th>Tgl Pinjam</th>
                                <th>Tgl Kembali</th>
                                <th>Status</th>
                                <th>Alasan</th>
                                <th class="text-end">Denda</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">#<?= $report['id_hilang'] ?></span>
                                    </td>
                                    <td>
                                        <?= formatTanggal($report['tanggal_laporan']) ?>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($report['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($report['nama']) ?></div>
                                        <small class="text-muted">NIK: <?= htmlspecialchars($report['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= htmlspecialchars($report['judul']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($report['pengarang']) ?> (<?= $report['tahun_terbit'] ?>)
                                        </small>
                                        <br>
                                        <small class="text-muted">ISBN: <?= htmlspecialchars($report['isbn']) ?></small>
                                    </td>
                                    <td><?= formatTanggal($report['tanggal_pinjam']) ?></td>
                                    <td><?= formatTanggal($report['tanggal_kembali']) ?></td>
                                    <td>
                                        <?php if ($report['status'] == 'hilang'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-question-circle me-1"></i>HILANG
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-ban me-1"></i>RUSAK PARAH
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars(truncateText($report['alasan'] ?? '', 50)) ?></small>
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        <?= formatRupiah($report['total_denda']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <td colspan="8" class="text-end"><strong>TOTAL:</strong></td>
                                <td class="text-end fw-bold">
                                    <?= formatRupiah(array_sum(array_column($reports, 'total_denda'))) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h6 class="text-muted">Tidak ada laporan buku hilang/rusak</h6>
                    <?php if (!empty($date_from) || !empty($date_to) || !empty($status) || !empty($search)): ?>
                        <p class="text-muted mb-3">Coba ubah kriteria filter</p>
                    <?php else: ?>
                        <p class="text-muted mb-3">Selamat! Tidak ada buku yang hilang atau rusak parah</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // Create a temporary table for export
    const table = document.getElementById('reportTable');
    if (!table) {
        alert('Tidak ada data untuk diexport');
        return;
    }
    
    // Clone table
    const tableClone = table.cloneNode(true);
    
    // Remove action buttons if any
    const actionCols = tableClone.querySelectorAll('td:last-child, th:last-child');
    actionCols.forEach(col => col.remove());
    
    // Convert to CSV
    let csv = [];
    const rows = tableClone.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean data
            let data = cols[j].innerText;
            data = data.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s+)/gm, ' ');
            data = data.replace(/"/g, '""');
            
            // Wrap in quotes if contains comma
            if (data.indexOf(',') >= 0 || data.indexOf('"') >= 0) {
                data = '"' + data + '"';
            }
            
            row.push(data);
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV
    const csvString = csv.join('\n');
    const filename = 'laporan_buku_hilang_' + new Date().toISOString().slice(0,10) + '.csv';
    
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>