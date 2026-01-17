<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

$page_title = 'Kelola Buku';
include '../../config/database.php';

// ========== Search & Filter ==========
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$filter_penerbit = isset($_GET['penerbit']) ? (int)$_GET['penerbit'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.judul LIKE ? OR b.pengarang LIKE ? OR p.nama_penerbit LIKE ? OR b.isbn LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($filter_status)) {
    if ($filter_status === 'tersedia') {
        $where_conditions[] = "b.stok_tersedia > 0 AND b.status = 'tersedia'";
    } elseif ($filter_status === 'habis') {
        $where_conditions[] = "b.stok_tersedia <= 0 OR b.status = 'tidak tersedia'";
    }
}

if (!empty($filter_kategori)) {
    $where_conditions[] = "bk.id_kategori = ?";
    $params[] = $filter_kategori;
}

if (!empty($filter_penerbit)) {
    $where_conditions[] = "b.id_penerbit = ?";
    $params[] = $filter_penerbit;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns
$valid_sorts = ['judul', 'pengarang', 'tahun_terbit', 'stok_total', 'stok_tersedia', 'created_at'];
$sort_by = in_array($sort_by, $valid_sorts) ? $sort_by : 'created_at';
$sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(DISTINCT b.isbn) 
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON b.id_rak = r.id_rak
        LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
        LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
        $where_clause
    ";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_books = $count_stmt->fetchColumn();
    $total_pages = ceil($total_books / $limit);

    // Get books data dengan JOIN
    $query = "
        SELECT b.*, 
               p.nama_penerbit, 
               r.kode_rak, 
               r.lokasi as lokasi_rak,
               GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') as kategori_list
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON b.id_rak = r.id_rak
        LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
        LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
        $where_clause
        GROUP BY b.isbn
        ORDER BY b.$sort_by $sort_order 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    // Get statistics
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) FROM buku")->fetchColumn(),
        'tersedia' => $conn->query("SELECT COUNT(*) FROM buku WHERE stok_tersedia > 0 AND status = 'tersedia'")->fetchColumn(),
        'habis' => $conn->query("SELECT COUNT(*) FROM buku WHERE stok_tersedia <= 0 OR status = 'tidak tersedia'")->fetchColumn(),
        'dipinjam' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn()
    ];
    
    // Get filter lists
    $kategori_list = getKategoriList();
    $penerbit_list = getPenerbitList();

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
                <span class="title-gradient">Kelola Buku</span>
            </h1>
            <p class="text-muted mb-0">Kelola koleksi buku perpustakaan</p>
        </div>
        <div>
            <a href="tambah.php" class="btn btn-modern btn-primary-modern">
                <i class="fas fa-plus me-2"></i>Tambah Buku
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-book text-primary"></i>
                </div>
                <div class="stat-number text-primary"><?= $stats['total'] ?? 0 ?></div>
                <small class="stat-label">Total Buku</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-number text-success"><?= $stats['tersedia'] ?? 0 ?></div>
                <small class="stat-label">Tersedia</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-times-circle text-warning"></i>
                </div>
                <div class="stat-number text-warning"><?= $stats['habis'] ?? 0 ?></div>
                <small class="stat-label">Stok Habis</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="modern-card text-center p-3">
                <div class="stat-icon mb-2">
                    <i class="fas fa-hand-holding-heart text-info"></i>
                </div>
                <div class="stat-number text-info"><?= $stats['dipinjam'] ?? 0 ?></div>
                <small class="stat-label">Sedang Dipinjam</small>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-modern" 
                           placeholder="Cari judul, pengarang, penerbit, atau ISBN..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-control-modern">
                        <option value="">Semua Status</option>
                        <option value="tersedia" <?= $filter_status === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="habis" <?= $filter_status === 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="kategori" class="form-select form-control-modern">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kat): ?>
                            <option value="<?= $kat['id_kategori'] ?>" <?= $filter_kategori == $kat['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="penerbit" class="form-select form-control-modern">
                        <option value="">Semua Penerbit</option>
                        <?php foreach ($penerbit_list as $pen): ?>
                            <option value="<?= $pen['id_penerbit'] ?>" <?= $filter_penerbit == $pen['id_penerbit'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pen['nama_penerbit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-modern btn-primary-modern w-100">
                        <i class="fas fa-search me-1"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Table -->
    <div class="modern-card">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Daftar Buku
                <?php if ($total_books > 0): ?>
                    <small class="text-muted">(<?= $total_books ?> buku)</small>
                <?php endif; ?>
            </h5>
            <div>
                <select name="sort" class="form-select form-select-sm" onchange="updateSort(this)">
                    <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Terbaru</option>
                    <option value="judul" <?= $sort_by === 'judul' ? 'selected' : '' ?>>Judul</option>
                    <option value="pengarang" <?= $sort_by === 'pengarang' ? 'selected' : '' ?>>Pengarang</option>
                    <option value="tahun_terbit" <?= $sort_by === 'tahun_terbit' ? 'selected' : '' ?>>Tahun</option>
                    <option value="stok_total" <?= $sort_by === 'stok_total' ? 'selected' : '' ?>>Stok Total</option>
                    <option value="stok_tersedia" <?= $sort_by === 'stok_tersedia' ? 'selected' : '' ?>>Stok Tersedia</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger m-3"><?= htmlspecialchars($error_message) ?></div>
            <?php elseif (empty($books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada buku ditemukan</h5>
                    <?php if (!empty($search) || !empty($filter_status) || !empty($filter_kategori) || !empty($filter_penerbit)): ?>
                        <p class="text-muted mb-3">Coba ubah kriteria pencarian atau filter</p>
                        <a href="index.php" class="btn btn-secondary">Reset Filter</a>
                    <?php else: ?>
                        <p class="text-muted mb-3">Belum ada buku di perpustakaan</p>
                        <a href="tambah.php" class="btn btn-primary">Tambah Buku Pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ISBN</th>
                                <th>Judul & Pengarang</th>
                                <th>Penerbit</th>
                                <th>Kategori</th>
                                <th>Rak</th>
                                <th>Tahun</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = $offset + 1;
                            foreach ($books as $book): 
                                // Gunakan stok_tersedia untuk status
                                $stok_total = $book['stok_total'] ?? $book['stok'];
                                $stok_tersedia = $book['stok_tersedia'] ?? $book['stok'];
                                $status_info = getStatusBuku($stok_tersedia);
                            ?>
                                <tr>
                                    <td><strong><?= $no++ ?></strong></td>
                                    <td><code class="small"><?= htmlspecialchars($book['isbn']) ?></code></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($book['judul']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($book['pengarang']) ?></small>
                                    </td>
                                    <td>
                                        <small><?= $book['nama_penerbit'] ? htmlspecialchars($book['nama_penerbit']) : '<em class="text-muted">-</em>' ?></small>
                                    </td>
                                    <td>
                                        <small><?= $book['kategori_list'] ? htmlspecialchars($book['kategori_list']) : '<em class="text-muted">-</em>' ?></small>
                                    </td>
                                    <td>
                                        <small><?= $book['kode_rak'] ? htmlspecialchars($book['kode_rak']) : '<em class="text-muted">-</em>' ?></small>
                                    </td>
                                    <td><?= $book['tahun_terbit'] ?></td>
                                    <td>
                                        <!-- Tampilkan stok total dan stok tersedia -->
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-secondary mb-1" title="Stok Total (Buku Fisik)">
                                                <i class="fas fa-box me-1"></i><?= $stok_total ?>
                                            </span>
                                            <span class="badge bg-<?= $stok_tersedia > 0 ? 'success' : 'danger' ?>" 
                                                  title="Stok Tersedia (Buku yang siap dipinjam)">
                                                <i class="fas fa-hand-holding me-1"></i><?= $stok_tersedia ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $status_info['badge_class'] ?>">
                                            <?= $status_info['text'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit.php?isbn=<?= urlencode($book['isbn']) ?>" 
                                               class="btn btn-outline-primary" title="Edit Buku">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- DIPERBAIKI: Hapus onclick confirm, langsung redirect -->
                                            <?php if ($book['status'] !== 'dipinjam'): ?>
                                                <a href="hapus.php?isbn=<?= urlencode($book['isbn']) ?>" 
                                                   class="btn btn-outline-danger" 
                                                   title="Hapus Buku">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary" disabled title="Buku sedang dipinjam">
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
                        <nav aria-label="Book pagination">
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
                            Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_books ?> total buku)
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Script: Simple dan Langsung Redirect -->
<script>
// Auto-submit on filter change
document.querySelectorAll('select[name="status"], select[name="kategori"], select[name="penerbit"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});

// Update sort
function updateSort(select) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', select.value);
    window.location.href = url.toString();
}
</script>

<?php include '../../includes/footer.php'; ?>