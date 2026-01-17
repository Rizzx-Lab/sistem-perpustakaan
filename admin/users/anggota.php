<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

include '../../config/database.php';

$flash = getFlashMessage();
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(a.nik LIKE ? OR a.nama LIKE ? OR a.no_hp LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $total_anggota = $conn->query("SELECT COUNT(*) FROM anggota")->fetchColumn();
} catch(PDOException $e) {
    $total_anggota = 0;
}

$page_title = 'Kelola Anggota';
$body_class = 'admin-anggota';

include '../../includes/header.php';
?>

<!-- MINIMAL INLINE CSS - Hanya untuk transparansi card -->
<style>
body.admin-anggota .modern-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

body.admin-anggota .alert {
    backdrop-filter: blur(10px);
}
</style>

<div class="container py-3">
    <div class="row mb-3">
        <div class="col-lg-8">
            <h2 class="fw-bold mb-2" style="color: #1e3c72;">
                <i class="fas fa-users me-2"></i>Kelola Anggota Perpustakaan
            </h2>
            <p class="text-muted mb-0">Manajemen data anggota perpustakaan</p>
        </div>
        <div class="col-lg-4">
            <div class="modern-card text-center p-3">
                <h3 class="mb-1 text-primary"><?= $total_anggota ?></h3>
                <small class="text-muted">Total Anggota Terdaftar</small>
            </div>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="modern-card mb-3 p-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari NIK, Nama, atau No HP..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-5">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Cari
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="anggota.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    <?php endif; ?>
                    <a href="tambah_user.php?role=anggota" class="btn btn-modern btn-success-modern">
                        <i class="fas fa-user-plus me-1"></i>Tambah Anggota
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="modern-card" style="padding: 0;">
        <div class="card-header-modern">
            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Anggota</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%;">NO</th>
                            <th style="width: 15%;">NIK</th>
                            <th style="width: 20%;">NAMA LENGKAP</th>
                            <th style="width: 15%;">NO. HP</th>
                            <th style="width: 25%;">ALAMAT</th>
                            <th style="width: 10%;">TGL DAFTAR</th>
                            <th style="width: 10%;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $sql = "SELECT a.*, u.id as user_id 
                                    FROM anggota a 
                                    LEFT JOIN users u ON a.user_id = u.id 
                                    $where_clause 
                                    ORDER BY a.created_at DESC, a.nama ASC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute($params);
                            
                            $no = 1;
                            $found = false;
                            
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                                $found = true;
                        ?>
                        <tr>
                            <td><strong><?= $no++ ?></strong></td>
                            <td><code style="color: #d63384;"><?= htmlspecialchars($row['nik']) ?></code></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($row['no_hp'])): ?>
                                    <small>
                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($row['no_hp']) ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars(substr($row['alamat'], 0, 50)) ?><?= strlen($row['alamat']) > 50 ? '...' : '' ?></small>
                            </td>
                            <td>
                                <small><?= formatTanggal($row['created_at']) ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($row['user_id']): ?>
                                        <a href="edit_user.php?id=<?= $row['user_id'] ?>" 
                                           class="btn btn-warning" 
                                           title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="tambah_user.php?role=anggota&nik=<?= urlencode($row['nik']) ?>" 
                                           class="btn btn-success" 
                                           title="Buat Akun User">
                                            <i class="fas fa-user-plus"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-info" 
                                            onclick="showDetail('<?= htmlspecialchars(addslashes($row['nik'])) ?>', '<?= htmlspecialchars(addslashes($row['nama'])) ?>', '<?= htmlspecialchars(addslashes($row['no_hp'])) ?>', '<?= htmlspecialchars(addslashes($row['alamat'])) ?>', '<?= formatTanggal($row['created_at']) ?>')"
                                            title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="hapus.php?type=anggota&nik=<?= urlencode($row['nik']) ?>" 
                                       class="btn btn-danger" 
                                       title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                            
                            if (!$found) {
                                echo "<tr><td colspan='7' class='text-center text-muted py-4'>";
                                echo "<i class='fas fa-users fa-3x mb-3 d-block'></i>";
                                echo empty($search) ? "Belum ada anggota terdaftar" : "Tidak ada data yang sesuai dengan pencarian";
                                echo "</td></tr>";
                            }
                        } catch(PDOException $e) {
                            echo "<tr><td colspan='7' class='text-center text-danger py-4'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>Detail Anggota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">NIK</th>
                        <td>: <span id="detail-nik"></span></td>
                    </tr>
                    <tr>
                        <th>Nama Lengkap</th>
                        <td>: <span id="detail-nama"></span></td>
                    </tr>
                    <tr>
                        <th>No. HP</th>
                        <td>: <span id="detail-hp"></span></td>
                    </tr>
                    <tr>
                        <th>Alamat</th>
                        <td>: <span id="detail-alamat"></span></td>
                    </tr>
                    <tr>
                        <th>Tanggal Daftar</th>
                        <td>: <span id="detail-daftar"></span></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showDetail(nik, nama, hp, alamat, daftar) {
    document.getElementById('detail-nik').textContent = nik;
    document.getElementById('detail-nama').textContent = nama;
    document.getElementById('detail-hp').textContent = hp || '-';
    document.getElementById('detail-alamat').textContent = alamat;
    document.getElementById('detail-daftar').textContent = daftar;
    
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>