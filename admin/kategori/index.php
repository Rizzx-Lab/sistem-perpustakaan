<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Kelola Kategori';
include '../../config/database.php';

// ========== Search & Filter ==========
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'nama_kategori';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kategori LIKE ? OR deskripsi LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns
$valid_sorts = ['nama_kategori', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'nama_kategori';
$sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : 'ASC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM kategori $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_kategori = $count_stmt->fetchColumn();
    $total_pages = ceil($total_kategori / $limit);

    // Get kategori data
    $query = "
        SELECT k.*, 
               COUNT(bk.isbn) as jumlah_buku
        FROM kategori k
        LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
        $where_clause
        GROUP BY k.id_kategori
        ORDER BY $sort_by $sort_order
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $kategori_list = $stmt->fetchAll();

    // Get statistics
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) FROM kategori")->fetchColumn(),
        'dengan_buku' => $conn->query("SELECT COUNT(DISTINCT id_kategori) FROM buku_kategori")->fetchColumn(),
        'tanpa_buku' => $conn->query("SELECT COUNT(*) FROM kategori WHERE id_kategori NOT IN (SELECT DISTINCT id_kategori FROM buku_kategori)")->fetchColumn(),
        'total_buku_berkategori' => $conn->query("SELECT COUNT(DISTINCT isbn) FROM buku_kategori")->fetchColumn()
    ];
    
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
                <span class="logo-emoji">🏷️</span>
                <span class="title-gradient">Kelola Kategori Buku</span>
            </h1>
            <p class="text-muted mb-0">Kelola kategori untuk pengelompokan buku</p>
        </div>
        <div>
            <a href="tambah.php" class="btn btn-modern btn-primary-modern">
                <i class="fas fa-plus me-2"></i>Tambah Kategori
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-tags text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total'] ?? 0 ?></div>
                <small class="stat-label">Total Kategori</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['dengan_buku'] ?? 0 ?></div>
                <small class="stat-label">Kategori Aktif</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-tag text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['tanpa_buku'] ?? 0 ?></div>
                <small class="stat-label">Kategori Kosong</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book-open text-info"></i>
                </div>
                <div class="stat-number text-info"><?= $stats['total_buku_berkategori'] ?? 0 ?></div>
                <small class="stat-label">Buku Berkategori</small>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control form-control-modern" 
                           placeholder="Cari nama kategori atau deskripsi..." 
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

    <!-- Kategori Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Daftar Kategori
                <?php if ($total_kategori > 0): ?>
                    <small class="text-muted">(<?= $total_kategori ?> kategori)</small>
                <?php endif; ?>
            </h5>
            <div class="d-flex gap-2">
                <select name="sort" class="form-select form-select-sm" onchange="updateSort(this)">
                    <option value="nama_kategori" <?= $sort_by === 'nama_kategori' ? 'selected' : '' ?>>Nama (A-Z)</option>
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
            <?php elseif (empty($kategori_list)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada kategori ditemukan</h5>
                    <?php if (!empty($search)): ?>
                        <p class="text-muted mb-3">Coba ubah kata kunci pencarian</p>
                        <a href="index.php" class="btn btn-secondary">Reset Pencarian</a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Belum ada kategori di sistem</p>
                        <a href="tambah.php" class="btn btn-primary">Tambah Kategori Pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Jumlah Buku</th>
                                <th>Tanggal Ditambahkan</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = $offset + 1;
                            foreach ($kategori_list as $kategori): 
                            ?>
                                <tr>
                                    <td><strong><?= $no++ ?></strong></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($kategori['nama_kategori']) ?></div>
                                    </td>
                                    <td>
                                        <small><?= $kategori['deskripsi'] ? htmlspecialchars(truncateText($kategori['deskripsi'], 60)) : '<em class="text-muted">-</em>' ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $kategori['jumlah_buku'] > 0 ? 'primary' : 'secondary' ?>">
                                            <?= $kategori['jumlah_buku'] ?> buku
                                        </span>
                                        <?php if ($kategori['jumlah_buku'] > 0): ?>
                                            <br><small class="text-muted">Tidak dapat dihapus</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= formatTanggal($kategori['created_at']) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit.php?id=<?= $kategori['id_kategori'] ?>" 
                                               class="btn btn-outline-primary" title="Edit Kategori">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($kategori['jumlah_buku'] == 0): ?>
                                                <a href="hapus.php?id=<?= $kategori['id_kategori'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   title="Hapus Kategori">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled 
                                                        title="Tidak dapat dihapus karena memiliki <?= $kategori['jumlah_buku'] ?> buku">
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
                        <nav aria-label="Kategori pagination">
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
                            Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_kategori ?> total kategori)
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Info Box -->
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Informasi:</strong> Kategori yang memiliki buku tidak dapat dihapus untuk menjaga integritas data klasifikasi buku. 
        Jika ingin menghapus kategori yang memiliki buku, hapus terlebih dahulu relasi kategori dari buku-buku tersebut.
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