<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin']);

include '../../config/database.php';

// ========== Toggle Status Petugas ==========
if (isset($_GET['toggle_status'])) {
    try {
        $user_id = (int)$_GET['toggle_status'];
        $new_status = $_GET['status'] ?? '';
        
        if (empty($user_id) || !in_array($new_status, ['aktif', 'nonaktif'])) {
            throw new Exception('Parameter tidak valid');
        }

        // Get petugas data
        $stmt = $conn->prepare("SELECT nama FROM users WHERE id = ? AND role = 'petugas'");
        $stmt->execute([$user_id]);
        $petugas = $stmt->fetch();

        if (!$petugas) {
            throw new Exception('Data petugas tidak ditemukan');
        }

        // Update status
        $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND role = 'petugas'");
        
        if ($stmt->execute([$new_status, $user_id])) {
            $status_text = $new_status === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';
            logActivity('TOGGLE_STATUS_PETUGAS', "Status petugas {$petugas['nama']} {$status_text}");
            redirect($_SERVER['REQUEST_URI'], "Petugas berhasil {$status_text}", 'success');
        } else {
            throw new Exception('Gagal mengubah status petugas');
        }
    } catch (Exception $e) {
        redirect($_SERVER['REQUEST_URI'], $e->getMessage(), 'error');
    }
}

// ========== Reset Password Petugas ==========
if (isset($_GET['reset_password'])) {
    try {
        $user_id = (int)$_GET['reset_password'];
        
        if (empty($user_id)) {
            throw new Exception('ID petugas tidak valid');
        }

        // Get petugas data
        $stmt = $conn->prepare("SELECT username, nama FROM users WHERE id = ? AND role = 'petugas'");
        $stmt->execute([$user_id]);
        $petugas = $stmt->fetch();

        if (!$petugas) {
            throw new Exception('Data petugas tidak ditemukan');
        }

        // Generate new password
        $new_password = generateRandomPassword();
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND role = 'petugas'");
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            logActivity('RESET_PASSWORD_PETUGAS', "Password petugas {$petugas['nama']} direset");
            
            // Store new password in session to show to admin
            $_SESSION['new_password'] = [
                'username' => $petugas['username'],
                'password' => $new_password
            ];
            
            redirect($_SERVER['REQUEST_URI'], 'Password berhasil direset. Password baru akan ditampilkan.', 'success');
        } else {
            throw new Exception('Gagal mereset password');
        }
    } catch (Exception $e) {
        redirect($_SERVER['REQUEST_URI'], $e->getMessage(), 'error');
    }
}

// ========== Filter dan Pencarian ==========
$where_conditions = ["role = ?"];
$params = ['petugas'];

if (!empty($_GET['search_nama'])) {
    $where_conditions[] = "nama LIKE ?";
    $params[] = '%' . $_GET['search_nama'] . '%';
}

if (!empty($_GET['search_username'])) {
    $where_conditions[] = "username LIKE ?";
    $params[] = '%' . $_GET['search_username'] . '%';
}

if (!empty($_GET['status_filter'])) {
    $where_conditions[] = "status = ?";
    $params[] = $_GET['status_filter'];
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// ========== Ambil Data Petugas ==========
try {
    $stmt = $conn->prepare("SELECT * FROM users $where_clause ORDER BY nama ASC");
    $stmt->execute($params);
    $petugas = $stmt->fetchAll();
} catch (PDOException $e) {
    $petugas = [];
    $error_message = "Error: " . $e->getMessage();
}

// Flash message
$flash = getFlashMessage();

$page_title = 'Kelola Petugas';
$body_class = 'admin-petugas';

// Include header
include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <h1 class="display-6 fw-bold mb-2">
                <i class="fas fa-user-tie text-primary me-2"></i>Kelola Data Petugas
            </h1>
            <p class="text-muted">Kelola dan pantau data petugas perpustakaan</p>
        </div>
        <div class="col-lg-4 text-end">
            <div class="modern-card p-3">
                <div class="h3 mb-0 text-primary"><?= count($petugas) ?></div>
                <small class="text-muted">Total Petugas</small>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- New Password Display -->
    <?php if (isset($_SESSION['new_password'])): ?>
        <div class="alert alert-success alert-dismissible fade show alert-permanent" role="alert">
            <h6 class="alert-heading"><i class="fas fa-key me-2"></i>Password Baru Berhasil Dibuat!</h6>
            <strong>Username:</strong> <?= htmlspecialchars($_SESSION['new_password']['username']) ?><br>
            <strong>Password Baru:</strong> <code><?= htmlspecialchars($_SESSION['new_password']['password']) ?></code>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <hr>
            <p class="mb-0 small">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Harap catat password ini dan berikan kepada petugas. Password ini tidak akan ditampilkan lagi.
            </p>
        </div>
        <?php unset($_SESSION['new_password']); ?>
    <?php endif; ?>

    <!-- Form Filter Modern -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-filter me-2"></i>Filter & Aksi
            </h5>
        </div>
        <div class="card-body p-4">
            <form method="GET" class="form-row">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="search_username" class="form-control form-control-modern" 
                               placeholder="Cari username..." 
                               value="<?= htmlspecialchars($_GET['search_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Nama Petugas</label>
                        <input type="text" name="search_nama" class="form-control form-control-modern" 
                               placeholder="Cari nama..." 
                               value="<?= htmlspecialchars($_GET['search_nama'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status_filter" class="form-control form-control-modern">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= ($_GET['status_filter'] ?? '') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= ($_GET['status_filter'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Non-aktif</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <?php if (!empty($_GET['search_nama']) || !empty($_GET['search_username']) || !empty($_GET['status_filter'])): ?>
                            <a href="petugas.php" class="btn btn-secondary w-100 mt-2">
                                <i class="fas fa-redo me-2"></i>Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <!-- Action Buttons Row -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="tambah_user.php?role=petugas" class="btn btn-modern btn-success-modern">
                            <i class="fas fa-user-plus me-2"></i>Tambah Petugas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Petugas Modern -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-database me-2"></i>Data Petugas
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Username</th>
                            <th width="20%">Nama Petugas</th>
                            <th width="20%">Email</th>
                            <th width="10%">Status</th>
                            <th width="12%">Dibuat</th>
                            <th width="18%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($petugas): ?>
                            <?php $no = 1; foreach ($petugas as $row): ?>
                            <tr>
                                <td><strong><?= $no++ ?></strong></td>
                                <td>
                                    <code><?= htmlspecialchars($row['username']) ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                    <small class="text-muted">ID: <?= $row['id'] ?></small>
                                </td>
                                <td>
                                    <small><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($row['email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'aktif'): ?>
                                        <span class="status-badge status-available">AKTIF</span>
                                    <?php else: ?>
                                        <span class="status-badge status-borrowed">NON-AKTIF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= isset($row['created_at']) ? formatTanggal($row['created_at']) : 'N/A' ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_user.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                            class="btn btn-<?= $row['status'] === 'aktif' ? 'secondary' : 'success' ?>" 
                                            onclick='toggleStatus(<?= $row['id'] ?>, "<?= addslashes($row['nama']) ?>", "<?= $row['status'] ?>")'
                                            title="<?= $row['status'] === 'aktif' ? 'Non-aktifkan' : 'Aktifkan' ?>">
                                            <i class="fas fa-<?= $row['status'] === 'aktif' ? 'ban' : 'check' ?>"></i>
                                        </button>
                                        
                                        <a href="hapus.php?type=petugas&id=<?= $row['id'] ?>" 
                                           class="btn btn-danger" 
                                           title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-users fa-3x mb-3 text-muted"></i>
                                    <h6>Belum ada data petugas</h6>
                                    <p class="small text-muted">Klik "Tambah Petugas" untuk menambah data</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStatus(id, nama, status) {
    const action = status === 'aktif' ? 'non-aktifkan' : 'aktifkan';
    const newStatus = status === 'aktif' ? 'nonaktif' : 'aktif';
    
    if (confirm(`Apakah Anda yakin ingin ${action} petugas "${nama}"?`)) {
        window.location.href = `?toggle_status=${id}&status=${newStatus}`;
    }
}

function resetPassword(id, username) {
    if (confirm(`Reset password untuk username "${username}"?\n\nPassword baru akan digenerate otomatis.`)) {
        window.location.href = `?reset_password=${id}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>