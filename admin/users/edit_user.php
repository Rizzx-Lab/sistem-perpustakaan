<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

include '../../config/database.php';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    setFlashMessage('ID user tidak valid', 'error');
    redirect(SITE_URL . 'admin/users/');
}

// CHECK SELF-EDIT MODE
$is_self_edit = ($user_id == $_SESSION['user_id']);

// Get user data
try {
    $stmt = $conn->prepare("
        SELECT u.*, a.nik, a.no_hp, a.alamat 
        FROM users u 
        LEFT JOIN anggota a ON u.id = a.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('User tidak ditemukan', 'error');
        redirect(SITE_URL . 'admin/users/');
    }
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'admin/users/');
}

// Tentukan referer/asal untuk breadcrumb dan redirect
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$from_anggota = strpos($referer, 'anggota.php') !== false || $user['role'] === 'anggota';
$from_petugas = strpos($referer, 'petugas.php') !== false || $user['role'] === 'petugas';

// Tentukan URL kembali berdasarkan asal
if ($from_anggota) {
    $back_url = 'anggota.php';
    $back_label = 'Data Anggota';
    $breadcrumb_label = 'Data Anggota';
    $breadcrumb_icon = 'fas fa-users';
} elseif ($from_petugas) {
    $back_url = 'petugas.php';
    $back_label = 'Data Petugas';
    $breadcrumb_label = 'Data Petugas';
    $breadcrumb_icon = 'fas fa-user-shield';
} else {
    $back_url = 'index.php';
    $back_label = 'Manajemen User';
    $breadcrumb_label = 'Manajemen User';
    $breadcrumb_icon = 'fas fa-users-cog';
}

// Handle form submission
if (isset($_POST['update_user'])) {
    $username = sanitizeInput($_POST['username']);
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $status = sanitizeInput($_POST['status']);
    
    // Password (optional)
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Role handling
    if ($is_self_edit) {
        // SELF EDIT: Tidak bisa ubah role sendiri
        $role = $user['role'];
        $role_changed = false;
    } elseif (isset($_POST['role'])) {
        // EDIT USER LAIN: Admin bisa ubah role
        $role = sanitizeInput($_POST['role']);
        $role_changed = ($user['role'] != $role);
    } else {
        $role = $user['role'];
        $role_changed = false;
    }
    
    // For anggota, get additional data
    $nik = '';
    $no_hp = '';
    $alamat = '';
    
    if ($role === 'anggota') {
        $nik = sanitizeInput($_POST['nik']);
        $no_hp = sanitizeInput($_POST['no_hp']);
        $alamat = sanitizeInput($_POST['alamat']);
    }

    $errors = [];

    // Validasi input
    if (empty($username)) {
        $errors[] = 'Username harus diisi';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username minimal 3 karakter';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username hanya boleh mengandung huruf, angka, dan underscore';
    }

    // Validate password if provided
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Konfirmasi password tidak cocok';
        }
    }

    if (empty($nama)) {
        $errors[] = 'Nama lengkap harus diisi';
    }

    if (empty($email)) {
        $errors[] = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    // Validasi role
    $valid_roles = ['admin', 'petugas', 'anggota'];
    if (!in_array($role, $valid_roles)) {
        $errors[] = 'Role tidak valid';
    }

    // Validasi status
    $valid_status = ['aktif', 'nonaktif'];
    if (!in_array($status, $valid_status)) {
        $errors[] = 'Status tidak valid';
    }

    // SELF EDIT: Tidak boleh nonaktifkan status sendiri
    if ($is_self_edit && $status === 'nonaktif') {
        $errors[] = 'Anda tidak dapat menonaktifkan akun Anda sendiri!';
    }

    // Validasi khusus untuk anggota
    if ($role === 'anggota') {
        if (empty($nik)) {
            $errors[] = 'NIK harus diisi untuk anggota';
        } elseif (!preg_match('/^\d{16}$/', $nik)) {
            $errors[] = 'NIK harus 16 digit angka';
        }

        if (empty($alamat)) {
            $errors[] = 'Alamat harus diisi untuk anggota';
        }
    }

    // Check if username already exists (exclude current user)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Username sudah digunakan oleh user lain';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi username: ' . $e->getMessage();
        }
    }

    // Check if email already exists (exclude current user)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email sudah digunakan oleh user lain';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi email: ' . $e->getMessage();
        }
    }

    // Check if NIK already exists (for anggota, exclude current user)
    if ($role === 'anggota' && empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT nik FROM anggota WHERE nik = ? AND (user_id != ? OR user_id IS NULL)");
            $stmt->execute([$nik, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'NIK sudah digunakan oleh anggota lain';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi NIK: ' . $e->getMessage();
        }
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Update user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                if ($role_changed) {
                    $sql = "UPDATE users SET username = ?, password = ?, nama = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute([$username, $hashed_password, $nama, $email, $role, $status, $user_id]);
                } else {
                    $sql = "UPDATE users SET username = ?, password = ?, nama = ?, email = ?, status = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute([$username, $hashed_password, $nama, $email, $status, $user_id]);
                }
            } else {
                if ($role_changed) {
                    $sql = "UPDATE users SET username = ?, nama = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute([$username, $nama, $email, $role, $status, $user_id]);
                } else {
                    $sql = "UPDATE users SET username = ?, nama = ?, email = ?, status = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute([$username, $nama, $email, $status, $user_id]);
                }
            }
            
            if ($result) {
                // Handle role change
                $old_role = $user['role'];
                
                if ($old_role === 'anggota' && $role !== 'anggota') {
                    // Remove link but don't delete anggota data
                    $stmt = $conn->prepare("UPDATE anggota SET user_id = NULL WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                } elseif ($role === 'anggota') {
                    // Check if anggota exists
                    $stmt = $conn->prepare("SELECT nik FROM anggota WHERE nik = ?");
                    $stmt->execute([$nik]);
                    $existing_anggota = $stmt->fetch();
                    
                    if ($existing_anggota) {
                        // Update existing anggota
                        $sql_anggota = "UPDATE anggota SET nama = ?, no_hp = ?, alamat = ?, user_id = ? WHERE nik = ?";
                        $stmt_anggota = $conn->prepare($sql_anggota);
                        $stmt_anggota->execute([$nama, $no_hp, $alamat, $user_id, $nik]);
                    } else {
                        // Insert new anggota
                        $sql_anggota = "INSERT INTO anggota (nik, nama, no_hp, alamat, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                        $stmt_anggota = $conn->prepare($sql_anggota);
                        $stmt_anggota->execute([$nik, $nama, $no_hp, $alamat, $user_id]);
                    }
                }
                
                $conn->commit();
                
                // Update session jika edit profil sendiri
                if ($is_self_edit) {
                    $_SESSION['username'] = $username;
                    $_SESSION['nama'] = $nama;
                    $_SESSION['email'] = $email;
                }
                
                if ($role_changed) {
                    logActivity('CHANGE_ROLE', "Role user diubah: $username ($old_role -> $role)");
                }
                
                $log_message = $is_self_edit ? "Profil diupdate: $username" : "User diupdate: $username (Role: $role)";
                logActivity('UPDATE_USER', $log_message);
                
                $success_message = $is_self_edit ? 'Profil berhasil diupdate!' : 'User berhasil diupdate!';
                setFlashMessage($success_message, 'success');
                
                // Redirect berdasarkan asal
                if ($is_self_edit) {
                    redirect(SITE_URL . 'admin/dashboard.php');
                } else {
                    redirect(SITE_URL . 'admin/users/' . $back_url . '?success=updated');
                }
            } else {
                $conn->rollback();
                $errors[] = 'Gagal mengupdate data';
            }
        } catch (PDOException $e) {
            $conn->rollback();
            $errors[] = 'Error database: ' . $e->getMessage();
        }
    }

    // Store errors in session for display
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'error');
    }
}

$page_title = $is_self_edit ? 'Edit Profil' : 'Edit User';
$body_class = $is_self_edit ? 'admin-edit-profile' : 'admin-edit-user';

include '../../includes/header.php';
?>

<style>
/* Tambahan styling untuk memperbaiki card yang terlalu mepet */
.modern-card {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
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

/* Spacing untuk info user di sidebar */
.row.mb-3 {
    margin-bottom: 12px !important;
    padding-bottom: 8px;    
    border-bottom: 1px solid #f0f0f0;
}

.row.mb-3:last-child {
    border-bottom: none;
    margin-bottom: 0 !important;
}

/* Padding tambahan untuk form controls */
.form-control-modern {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #ced4da;
}

.form-control-modern:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Perbaikan spacing untuk alert */
.alert {
    border-radius: 8px;
    padding: 12px 15px;
}

/* Perbaikan untuk badge agar tidak kepotong */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
}

/* Perbaikan untuk tombol */
.btn {
    border-radius: 8px;
    padding: 8px 16px;
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
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-<?= $is_self_edit ? 'user-circle' : 'user-edit' ?> me-2 text-<?= $is_self_edit ? 'primary' : 'warning' ?>"></i>
                <?= $is_self_edit ? 'Edit Profil Saya' : 'Edit User' ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <?php if (!$is_self_edit): ?>
                        <li class="breadcrumb-item">
                            <a href="<?= $back_url ?>"><i class="<?= $breadcrumb_icon ?>"></i> <?= $breadcrumb_label ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">
                        <?= $is_self_edit ? 'Edit Profil' : 'Edit ' . htmlspecialchars($user['nama']) ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <?php if ($is_self_edit): ?>
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="<?= $back_url ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke <?= $back_label ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Main Form -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-user me-2"></i><?= $is_self_edit ? 'Edit Informasi Profil' : 'Edit Informasi User' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="userForm" novalidate>
                        <!-- Current Info Alert -->
                        <div class="alert alert-<?= $is_self_edit ? 'primary' : 'info' ?> mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Username saat ini:</strong> <?= htmlspecialchars($user['username']) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Role saat ini:</strong> 
                                    <span class="badge bg-<?= 
                                        $user['role'] === 'admin' ? 'warning' : 
                                        ($user['role'] === 'petugas' ? 'info' : 'success') 
                                    ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_self_edit): ?>
                            <!-- SELF EDIT MODE: Role tidak bisa diubah -->
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-lock me-2"></i>
                                <strong>Catatan:</strong> Anda tidak dapat mengubah role atau status akun Anda sendiri.
                            </div>
                            <input type="hidden" name="role" value="<?= htmlspecialchars($user['role']) ?>">
                            <input type="hidden" name="status" value="aktif">
                        <?php else: ?>
                            <!-- EDIT USER LAIN MODE -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="role">
                                    Role User <span class="text-danger">*</span>
                                </label>
                                <select name="role" id="role" class="form-control form-control-modern" required onchange="toggleAnggotaFields()">
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                    <option value="petugas" <?= $user['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                                    <option value="anggota" <?= $user['role'] === 'anggota' ? 'selected' : '' ?>>Anggota</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Username & Basic Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-<?= $is_self_edit ? '12' : '6' ?>">
                                <label class="form-label fw-semibold" for="username">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       name="username" 
                                       id="username"
                                       class="form-control form-control-modern" 
                                       placeholder="Username login"
                                       value="<?= htmlspecialchars($user['username']) ?>"
                                       required>
                                <small class="text-muted">Min. 3 karakter</small>
                            </div>
                            <?php if (!$is_self_edit): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="status">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select name="status" id="status" class="form-control form-control-modern" required>
                                    <option value="aktif" <?= $user['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $user['status'] === 'nonaktif' ? 'selected' : '' ?>>Non-aktif</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Password Section -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-lock me-2"></i>Ubah Password <small class="text-muted">(opsional)</small>
                            </h6>
                            <div class="alert alert-warning small">
                                <i class="fas fa-info-circle me-2"></i>
                                Kosongkan jika tidak ingin mengubah password
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="password">
                                        Password Baru
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="password" 
                                               id="password"
                                               class="form-control form-control-modern" 
                                               placeholder="Min. 6 karakter">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                            <i class="fas fa-eye" id="password-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="confirm_password">
                                        Konfirmasi Password Baru
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               name="confirm_password" 
                                               id="confirm_password"
                                               class="form-control form-control-modern" 
                                               placeholder="Ulangi password baru">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye" id="confirm_password-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Name & Email -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="nama">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       name="nama" 
                                       id="nama"
                                       class="form-control form-control-modern" 
                                       placeholder="Nama lengkap"
                                       value="<?= htmlspecialchars($user['nama']) ?>"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="email">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       class="form-control form-control-modern" 
                                       placeholder="alamat@email.com"
                                       value="<?= htmlspecialchars($user['email']) ?>"
                                       required>
                            </div>
                        </div>

                        <!-- Anggota-specific fields -->
                        <div id="anggota-fields" style="display: <?= $user['role'] === 'anggota' ? 'block' : 'none' ?>">
                            <hr>
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-id-card me-2"></i>Data Khusus Anggota
                            </h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="nik">
                                        NIK <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           name="nik" 
                                           id="nik"
                                           class="form-control form-control-modern" 
                                           placeholder="16 digit NIK"
                                           maxlength="16"
                                           value="<?= htmlspecialchars($user['nik'] ?? '') ?>">
                                    <small class="text-muted">16 digit angka</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold" for="no_hp">
                                        No. HP
                                    </label>
                                    <input type="text" 
                                           name="no_hp" 
                                           id="no_hp"
                                           class="form-control form-control-modern" 
                                           placeholder="08xxxxxxxxxx"
                                           value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="alamat">
                                    Alamat <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat" 
                                          id="alamat"
                                          class="form-control form-control-modern" 
                                          rows="3"
                                          placeholder="Alamat lengkap"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Role Change History -->
                        <?php if (!$is_self_edit && $user['promoted_by'] && $user['promoted_at']): ?>
                        <div class="alert alert-info">
                            <strong><i class="fas fa-history me-2"></i>Riwayat Perubahan Role:</strong><br>
                            <?php
                            try {
                                $stmt = $conn->prepare("SELECT nama, role FROM users WHERE id = ?");
                                $stmt->execute([$user['promoted_by']]);
                                $promoter = $stmt->fetch();
                                
                                if ($promoter) {
                                    echo "Diubah oleh: <strong>" . htmlspecialchars($promoter['nama']) . "</strong> ";
                                    echo "(<span class='badge bg-" . ($promoter['role'] === 'admin' ? 'warning' : ($promoter['role'] === 'petugas' ? 'info' : 'success')) . "'>";
                                    echo ucfirst($promoter['role']);
                                    echo "</span>)<br>";
                                }
                            } catch (Exception $e) {
                                echo "Diubah oleh administrator<br>";
                            }
                            ?>
                            Pada: <?= date('d/m/Y H:i', strtotime($user['promoted_at'])) ?> WIB
                        </div>
                        <?php endif; ?>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between">
                            <?php if ($is_self_edit): ?>
                                <a href="../dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                                <button type="submit" name="update_user" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profil
                                </button>
                            <?php else: ?>
                                <a href="<?= $back_url ?>" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Batal
                                </a>
                                <button type="submit" name="update_user" class="btn btn-warning text-white">
                                    <i class="fas fa-save me-2"></i>Update User
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Current User Info -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Info <?= $is_self_edit ? 'Profil' : 'User' ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">ID:</div>
                        <div class="col-sm-7"><?= $user['id'] ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">Username:</div>
                        <div class="col-sm-7"><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($user['username']) ?></code></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">Role:</div>
                        <div class="col-sm-7">
                            <span class="badge bg-<?= 
                                $user['role'] === 'admin' ? 'warning' : 
                                ($user['role'] === 'petugas' ? 'info' : 'success') 
                            ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">Status:</div>
                        <div class="col-sm-7">
                            <span class="badge bg-<?= $user['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">Terdaftar:</div>
                        <div class="col-sm-7"><?= formatTanggal($user['created_at']) ?></div>
                    </div>
                    <?php if ($user['updated_at']): ?>
                    <div class="row mb-3">
                        <div class="col-sm-5 fw-semibold">Diupdate:</div>
                        <div class="col-sm-7"><?= formatTanggal($user['updated_at']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Password Generator -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-key me-2"></i>Generator Password
                    </h6>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary w-100" onclick="generatePassword()">
                        <i class="fas fa-magic me-2"></i>Generate Password Baru
                    </button>
                    <div id="generated-password" class="mt-2 text-center small" style="display: none;">
                        <code id="password-display" class="bg-light px-2 py-1 rounded d-inline-block mb-2"></code>
                        <button type="button" class="btn btn-link btn-sm" onclick="copyPassword()" title="Salin password">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (!$is_self_edit): ?>
            <!-- Access Control Info -->
            <div class="modern-card border-warning">
                <div class="card-header-modern bg-warning bg-opacity-10 border-warning">
                    <h6 class="card-title-modern mb-0 text-warning">
                        <i class="fas fa-shield-alt me-2"></i>Kontrol Akses
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning alert-sm mb-0">
                        <i class="fas fa-user-shield me-1"></i>
                        <strong>Administrator</strong><br>
                        Anda dapat mengubah role semua user.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAnggotaFields() {
    const roleSelect = document.getElementById('role');
    const role = roleSelect ? roleSelect.value : '<?= $user['role'] ?>';
    const anggotaFields = document.getElementById('anggota-fields');
    
    if (role === 'anggota') {
        anggotaFields.style.display = 'block';
        document.getElementById('nik').required = true;
        document.getElementById('alamat').required = true;
    } else {
        anggotaFields.style.display = 'none';
        document.getElementById('nik').required = false;
        document.getElementById('alamat').required = false;
    }
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eye = document.getElementById(fieldId + '-eye');
    
    if (field.type === 'password') {
        field.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    
    password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
    password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
    password += '0123456789'[Math.floor(Math.random() * 10)];
    password += '!@#$%^&*'[Math.floor(Math.random() * 8)];
    
    for (let i = 4; i < 10; i++) {
        password += chars[Math.floor(Math.random() * chars.length)];
    }
    
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    document.getElementById('password-display').textContent = password;
    document.getElementById('generated-password').style.display = 'block';
    document.getElementById('password').value = password;
    document.getElementById('confirm_password').value = password;
}

function copyPassword() {
    const password = document.getElementById('password-display').textContent;
    navigator.clipboard.writeText(password).then(() => {
        alert('Password disalin!');
    });
}

// NIK & Phone formatting
document.getElementById('nik').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

document.getElementById('no_hp').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Initialize
toggleAnggotaFields();
</script>

<?php include '../../includes/footer.php'; ?>