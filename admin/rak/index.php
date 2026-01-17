<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Kelola Rak';
include '../../config/database.php';

// ========== Search & Filter ==========
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'kode_rak';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(kode_rak LIKE ? OR lokasi LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns
$valid_sorts = ['kode_rak', 'lokasi', 'kapasitas', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'kode_rak';
$sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : 'ASC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM rak $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_rak = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rak / $limit);

    // Get rak data dengan PERHITUNGAN BARU
    $query = "
        SELECT r.*, 
               COUNT(DISTINCT b.isbn) as jumlah_judul,
               COALESCE(SUM(b.stok_total), 0) as total_stok
        FROM rak r
        LEFT JOIN buku b ON r.id_rak = b.id_rak
        $where_clause
        GROUP BY r.id_rak
        ORDER BY $sort_by $sort_order
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rak_list = $stmt->fetchAll();

    // Get statistics - DIPERBAIKI
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) FROM rak")->fetchColumn(),
        'terisi' => $conn->query("SELECT COUNT(DISTINCT id_rak) FROM buku WHERE id_rak IS NOT NULL")->fetchColumn(),
        'kosong' => $conn->query("SELECT COUNT(*) FROM rak WHERE id_rak NOT IN (SELECT DISTINCT id_rak FROM buku WHERE id_rak IS NOT NULL)")->fetchColumn(),
        'kapasitas_total' => $conn->query("SELECT SUM(kapasitas) FROM rak WHERE kapasitas IS NOT NULL")->fetchColumn() ?: 0,
        'kapasitas_terpakai' => $conn->query("SELECT COALESCE(SUM(stok_total), 0) FROM buku WHERE id_rak IS NOT NULL")->fetchColumn() ?: 0
    ];
    
    // Hitung sisa kapasitas
    $stats['kapasitas_sisa'] = $stats['kapasitas_total'] - $stats['kapasitas_terpakai'];
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 title-container">
                <span class="logo-emoji">📚</span>
                <span class="title-gradient">Kelola Rak Buku</span>
            </h1>
            <p class="text-muted mb-0">Kelola lokasi penyimpanan buku di perpustakaan</p>
        </div>
        <div>
            <a href="tambah.php" class="btn btn-modern btn-primary-modern">
                <i class="fas fa-plus me-2"></i>Tambah Rak
            </a>
        </div>
    </div>

    <!-- Statistics Cards - DIPERBAIKI -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-archive text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total'] ?? 0 ?></div>
                <small class="stat-label">Total Rak</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['terisi'] ?? 0 ?></div>
                <small class="stat-label">Rak Terisi</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-box-open text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['kosong'] ?? 0 ?></div>
                <small class="stat-label">Rak Kosong</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-weight-hanging text-info"></i>
                </div>
                <div class="stat-number text-info"><?= $stats['kapasitas_terpakai'] ?? 0 ?> / <?= $stats['kapasitas_total'] ?? 0 ?></div>
                <small class="stat-label">Kapasitas Terpakai (Buku Fisik)</small>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control form-control-modern" 
                           placeholder="Cari kode rak atau lokasi..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-modern btn-primary-modern w-100">
                        <i class="fas fa-search me-1"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rak Table - DIPERBAIKI -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Daftar Rak
                <?php if ($total_rak > 0): ?>
                    <small class="text-muted">(<?= $total_rak ?> rak)</small>
                <?php endif; ?>
            </h5>
            <div class="d-flex gap-2">
                <select name="sort" class="form-select form-select-sm" onchange="updateSort(this)">
                    <option value="kode_rak" <?= $sort_by === 'kode_rak' ? 'selected' : '' ?>>Kode (A-Z)</option>
                    <option value="lokasi" <?= $sort_by === 'lokasi' ? 'selected' : '' ?>>Lokasi</option>
                    <option value="kapasitas" <?= $sort_by === 'kapasitas' ? 'selected' : '' ?>>Kapasitas</option>
                    <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Terbaru</option>
                </select>
                <a href="tambah.php" class="btn btn-sm btn-success">
                    <i class="fas fa-plus me-1"></i>Tambah
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger m-3"><?= htmlspecialchars($error_message) ?></div>
            <?php elseif (empty($rak_list)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada rak ditemukan</h5>
                    <?php if (!empty($search)): ?>
                        <p class="text-muted mb-3">Coba ubah kata kunci pencarian</p>
                        <a href="index.php" class="btn btn-secondary">Reset Pencarian</a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Belum ada rak di sistem</p>
                        <a href="tambah.php" class="btn btn-primary">Tambah Rak Pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Rak</th>
                                <th>Lokasi</th>
                                <th>Kapasitas</th>
                                <th>Jumlah Judul</th>
                                <th>Buku Fisik</th>
                                <th>Persentase</th>
                                <th>Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = $offset + 1;
                            foreach ($rak_list as $rak): 
                                // Hitung persentase kapasitas terpakai - DIPERBAIKI
                                $persentase = ($rak['kapasitas'] > 0 && $rak['total_stok'] > 0) ? 
                                    round(($rak['total_stok'] / $rak['kapasitas']) * 100) : 0;
                                $progress_color = $persentase >= 90 ? 'danger' : ($persentase >= 70 ? 'warning' : 'success');
                                $status_warna = '';
                                $status_text = '';
                                
                                // Tentukan status rak berdasarkan kapasitas
                                if ($rak['kapasitas'] > 0) {
                                    if ($rak['total_stok'] == 0) {
                                        $status_warna = 'bg-success';
                                        $status_text = 'Kosong';
                                    } elseif ($rak['total_stok'] >= $rak['kapasitas']) {
                                        $status_warna = 'bg-danger';
                                        $status_text = 'Penuh';
                                    } elseif ($persentase >= 70) {
                                        $status_warna = 'bg-warning';
                                        $status_text = 'Hampir Penuh';
                                    } else {
                                        $status_warna = 'bg-info';
                                        $status_text = 'Tersedia';
                                    }
                                } else {
                                    $status_warna = 'bg-secondary';
                                    $status_text = 'Unlimited';
                                }
                            ?>
                                <tr>
                                    <td><strong><?= $no++ ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($rak['kode_rak']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($rak['lokasi']) ?></div>
                                    </td>
                                    <td>
                                        <?= $rak['kapasitas'] ? $rak['kapasitas'] . ' buku' : '<em class="text-muted">Unlimited</em>' ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $rak['jumlah_judul'] > 0 ? 'info' : 'secondary' ?>">
                                            <?= $rak['jumlah_judul'] ?> judul
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($rak['total_stok']): ?>
                                            <span class="badge bg-secondary">
                                                <?= $rak['total_stok'] ?> buku
                                            </span>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($rak['kapasitas'] > 0): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                    <div class="progress-bar bg-<?= $progress_color ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= min($persentase, 100) ?>%">
                                                    </div>
                                                </div>
                                                <small><?= $persentase ?>%</small>
                                            </div>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_warna ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit.php?id=<?= $rak['id_rak'] ?>" 
                                               class="btn btn-outline-primary" title="Edit Rak">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($rak['total_stok'] == 0): ?>
                                                <a href="hapus.php?id=<?= $rak['id_rak'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   title="Hapus Rak">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled 
                                                        title="Tidak dapat dihapus karena memiliki <?= $rak['total_stok'] ?> buku fisik">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-light">
                        <nav aria-label="Rak pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center text-muted small mt-2">
                            Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_rak ?> total rak)
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Info Box - DIPERBAIKI -->
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Informasi Sistem Rak Baru:</strong> 
        <ul class="mb-0 mt-2">
            <li>Kapasitas dihitung berdasarkan <strong>buku fisik (eksemplar)</strong>, bukan judul buku</li>
            <li>1 rak dengan kapasitas 50 = dapat menampung 50 buku fisik</li>
            <li>Jika ada 2 judul buku dengan total 10 eksemplar, maka kapasitas terpakai = 10 buku</li>
            <li>Rak yang memiliki buku fisik tidak dapat dihapus</li>
        </ul>
    </div>
</div>

<script>
// Update sort
function updateSort(select) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', select.value);
    window.location.href = url.toString();
}
</script>

<?php include '../../includes/footer.php'; ?>