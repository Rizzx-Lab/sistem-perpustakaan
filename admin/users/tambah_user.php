<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Require admin role
requireRole(['admin']);

include '../../config/database.php';

// Get role from URL parameter (if set)
$default_role = $_GET['role'] ?? '';
$nik_from_url = $_GET['nik'] ?? '';

// Determine where we came from for proper breadcrumb and redirect
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$from_anggota = strpos($referer, 'anggota.php') !== false;
$from_petugas = strpos($referer, 'petugas.php') !== false;
$from_users_index = strpos($referer, 'admin/users/index.php') !== false || 
                    (strpos($referer, 'admin/users/') !== false && 
                     strpos($referer, 'anggota.php') === false && 
                     strpos($referer, 'petugas.php') === false);

// Set default role based on where we came from
if ($from_anggota) {
    $default_role = 'anggota';
    $back_url = 'anggota.php';
    $back_label = 'Data Anggota';
    $breadcrumb_label = 'Data Anggota';
    $breadcrumb_icon = 'fas fa-users';
} elseif ($from_petugas) {
    $default_role = 'petugas';
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

// Override with URL parameter if provided
if (!empty($_GET['role'])) {
    $default_role = $_GET['role'];
    // Update back URL based on role from parameter
    if ($_GET['role'] === 'anggota') {
        $back_url = 'anggota.php';
        $back_label = 'Data Anggota';
        $breadcrumb_label = 'Data Anggota';
        $breadcrumb_icon = 'fas fa-users';
    } elseif ($_GET['role'] === 'petugas') {
        $back_url = 'petugas.php';
        $back_label = 'Data Petugas';
        $breadcrumb_label = 'Data Petugas';
        $breadcrumb_icon = 'fas fa-user-shield';
    }
}

// Get anggota data if NIK provided
$anggota_data = null;
if (!empty($nik_from_url)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM anggota WHERE nik = ?");
        $stmt->execute([$nik_from_url]);
        $anggota_data = $stmt->fetch();
        $default_role = 'anggota';
        $back_url = 'anggota.php';
        $back_label = 'Data Anggota';
        $breadcrumb_label = 'Data Anggota';
        $breadcrumb_icon = 'fas fa-users';
    } catch (PDOException $e) {
        // Silent fail
    }
}

// Handle form submission
if (isset($_POST['tambah_user'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $role = sanitizeInput($_POST['role']);
    $status = sanitizeInput($_POST['status']);
    
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

    if (empty($password)) {
        $errors[] = 'Password harus diisi';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Konfirmasi password tidak cocok';
    }

    if (empty($nama)) {
        $errors[] = 'Nama lengkap harus diisi';
    }

    if (empty($email)) {
        $errors[] = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    $valid_roles = ['admin', 'petugas', 'anggota'];
    if (!in_array($role, $valid_roles)) {
        $errors[] = 'Role tidak valid';
    }

    $valid_status = ['aktif', 'nonaktif'];
    if (!in_array($status, $valid_status)) {
        $errors[] = 'Status tidak valid';
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

    // Check if username already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = 'Username sudah digunakan';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi username: ' . $e->getMessage();
        }
    }

    // Check if email already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email sudah digunakan';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi email: ' . $e->getMessage();
        }
    }

    // Check if NIK already exists (for anggota)
    if ($role === 'anggota' && empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT nik FROM anggota WHERE nik = ?");
            $stmt->execute([$nik]);
            $existing_anggota = $stmt->fetch();
            
            if ($existing_anggota) {
                $errors[] = 'NIK sudah terdaftar di sistem';
            }
        } catch (PDOException $e) {
            $errors[] = 'Error validasi NIK: ' . $e->getMessage();
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, nama, email, role, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$username, $hashed_password, $nama, $email, $role, $status]);
            
            if ($result) {
                $user_id = $conn->lastInsertId();
                
                // If role is anggota, insert/update anggota table
                if ($role === 'anggota') {
                    // Check if anggota already exists (from manual input)
                    $stmt = $conn->prepare("SELECT nik FROM anggota WHERE nik = ?");
                    $stmt->execute([$nik]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing anggota with user_id
                        $sql_anggota = "UPDATE anggota SET user_id = ? WHERE nik = ?";
                        $stmt_anggota = $conn->prepare($sql_anggota);
                        $stmt_anggota->execute([$user_id, $nik]);
                    } else {
                        // Insert new anggota
                        $sql_anggota = "INSERT INTO anggota (nik, nama, no_hp, alamat, user_id, created_at) 
                                        VALUES (?, ?, ?, ?, ?, NOW())";
                        $stmt_anggota = $conn->prepare($sql_anggota);
                        $stmt_anggota->execute([$nik, $nama, $no_hp, $alamat, $user_id]);
                    }
                }
                
                $conn->commit();
                
                // ===== LOG ACTIVITY - TAMBAH USER =====
                logActivity(
                    'TAMBAH_USER',
                    "Menambahkan user baru: {$username} ({$nama}) dengan role {$role}",
                    'user',
                    $user_id
                );
                
                setFlashMessage('User berhasil ditambahkan!', 'success');
                
                // Redirect based on role
                if ($role === 'anggota') {
                    redirect(SITE_URL . 'admin/users/anggota.php');
                } elseif ($role === 'petugas') {
                    redirect(SITE_URL . 'admin/users/petugas.php');
                } else {
                    redirect(SITE_URL . 'admin/users/');
                }
            } else {
                $conn->rollback();
                $errors[] = 'Gagal menyimpan data user';
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

$page_title = 'Tambah User';
$body_class = 'admin-tambah-user';

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
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.form-label-modern {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    display: block;
}

/* Perbaikan untuk tombol sukses */
.btn-success-modern {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    color: white;
}

.btn-success-modern:hover {
    background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
    border-color: #1e7e34;
    color: white;
}

/* Perbaikan untuk tombol */
.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Perbaikan untuk alert */
.alert {
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
}

.alert ul {
    margin-bottom: 0;
    padding-left: 1.2rem;
}

.alert li {
    margin-bottom: 4px;
}

.alert li:last-child {
    margin-bottom: 0;
}

/* Perbaikan untuk quick actions */
.d-grid .btn {
    border-radius: 8px;
    padding: 10px;
    text-align: left;
}

/* Perbaikan untuk form-text */
.form-text {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Perbaikan untuk invalid feedback */
.invalid-feedback {
    display: block;
    margin-top: 5px;
    font-size: 0.85rem;
}

/* Perbaikan untuk breadcrumb */
.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 10px;
}

.breadcrumb-item a {
    text-decoration: none;
    color: #6c757d;
}

.breadcrumb-item.active {
    color: #495057;
    font-weight: 500;
}

/* Perbaikan untuk input group */
.input-group .btn-outline-secondary {
    border-color: #ced4da;
    border-radius: 0 8px 8px 0;
}

.input-group .form-control-modern {
    border-radius: 8px 0 0 8px;
}

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
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
    
    .btn-modern {
        padding: 8px 16px;
    }
    
    .input-group .btn-outline-secondary {
        padding: 8px 12px;
    }
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-user-plus me-2 text-success"></i>Tambah User Baru
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?= $back_url ?>"><i class="<?= $breadcrumb_icon ?>"></i> <?= $breadcrumb_label ?></a>
                    </li>
                    <li class="breadcrumb-item active">Tambah User</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="<?= $back_url ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke <?= $back_label ?>
            </a>
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
                        <i class="fas fa-user me-2"></i>Informasi User
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="userForm" novalidate>
                        <!-- Role Selection -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="role">
                                Role User <span class="text-danger">*</span>
                            </label>
                            <select name="role" id="role" class="form-control-modern" required onchange="toggleAnggotaFields()">
                                <option value="">-- Pilih Role --</option>
                                <option value="admin" <?= $default_role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                <option value="petugas" <?= $default_role === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                                <option value="anggota" <?= $default_role === 'anggota' ? 'selected' : '' ?>>Anggota</option>
                            </select>
                            <div class="form-text mt-2">Pilih role terlebih dahulu</div>
                        </div>

                        <!-- Username & Password -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label-modern" for="username">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       name="username" 
                                       id="username"
                                       class="form-control-modern" 
                                       placeholder="Username login"
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                       required>
                                <small class="text-muted">Min. 3 karakter</small>
                                <div class="invalid-feedback">Username harus diisi</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-modern" for="password">
                                    Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           name="password" 
                                           id="password"
                                           class="form-control-modern" 
                                           placeholder="Min. 6 karakter"
                                           required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password harus diisi</div>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="confirm_password">
                                Konfirmasi Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password"
                                       class="form-control-modern" 
                                       placeholder="Ulangi password"
                                       required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Konfirmasi password harus diisi</div>
                        </div>

                        <!-- Basic Info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label class="form-label-modern" for="nama">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       name="nama" 
                                       id="nama"
                                       class="form-control-modern" 
                                       placeholder="Nama lengkap"
                                       value="<?= $anggota_data ? htmlspecialchars($anggota_data['nama']) : (isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '') ?>"
                                       required>
                                <div class="invalid-feedback">Nama lengkap harus diisi</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern" for="status">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select name="status" id="status" class="form-control-modern" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Non-aktif</option>
                                </select>
                                <div class="invalid-feedback">Status harus dipilih</div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label class="form-label-modern" for="email">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   class="form-control-modern" 
                                   placeholder="alamat@email.com"
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   required>
                            <div class="invalid-feedback">Email harus diisi</div>
                        </div>

                        <!-- Anggota-specific fields -->
                        <div id="anggota-fields" style="display: <?= $default_role === 'anggota' ? 'block' : 'none' ?>">
                            <hr class="my-4">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-id-card me-2"></i>Data Khusus Anggota
                            </h6>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label-modern" for="nik">
                                        NIK <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           name="nik" 
                                           id="nik"
                                           class="form-control-modern" 
                                           placeholder="16 digit NIK"
                                           maxlength="16"
                                           value="<?= $anggota_data ? htmlspecialchars($anggota_data['nik']) : (isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : '') ?>"
                                           <?= $anggota_data ? 'readonly' : '' ?>>
                                    <div class="form-text mt-2">16 digit angka</div>
                                    <div class="invalid-feedback">NIK harus diisi</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-modern" for="no_hp">
                                        No. HP
                                    </label>
                                    <input type="text" 
                                           name="no_hp" 
                                           id="no_hp"
                                           class="form-control-modern" 
                                           placeholder="08xxxxxxxxxx"
                                           value="<?= $anggota_data ? htmlspecialchars($anggota_data['no_hp']) : (isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '') ?>">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-modern" for="alamat">
                                    Alamat <span class="text-danger">*</span>
                                </label>
                                <textarea name="alamat" 
                                          id="alamat"
                                          class="form-control-modern" 
                                          rows="3"
                                          placeholder="Alamat lengkap"><?= $anggota_data ? htmlspecialchars($anggota_data['alamat']) : (isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : '') ?></textarea>
                                <div class="invalid-feedback">Alamat harus diisi</div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between pt-3">
                            <a href="<?= $back_url ?>" class="btn btn-secondary btn-modern">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                            <button type="submit" name="tambah_user" class="btn btn-success-modern btn-modern">
                                <i class="fas fa-save me-2"></i>Simpan User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Guidelines Card -->
            <div class="modern-card">
                <div class="card-header-modern">
                    <h6 class="card-title-modern mb-0">
                        <i class="fas fa-info-circle me-2"></i>Panduan Role
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <strong><i class="fas fa-crown text-warning me-2"></i>Administrator:</strong>
                        <ul class="small text-muted mb-0 mt-2">
                            <li>Akses penuh ke semua fitur</li>
                            <li>Kelola buku, user, dan sistem</li>
                            <li>Bisa edit semua data</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <strong><i class="fas fa-user-tie text-info me-2"></i>Petugas:</strong>
                        <ul class="small text-muted mb-0 mt-2">
                            <li>Kelola transaksi peminjaman</li>
                            <li>Input dan edit buku</li>
                            <li>Kelola anggota</li>
                        </ul>
                    </div>

                    <div>
                        <strong><i class="fas fa-user text-success me-2"></i>Anggota:</strong>
                        <ul class="small text-muted mb-0 mt-2">
                            <li>Lihat katalog buku</li>
                            <li>Lihat riwayat peminjaman</li>
                            <li>Perlu NIK dan alamat lengkap</li>
                        </ul>
                    </div>
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
                    <button type="button" class="btn btn-outline-primary btn-modern w-100" onclick="generatePassword()">
                        <i class="fas fa-magic me-2"></i>Generate Password
                    </button>
                    <div id="generated-password" class="mt-3 text-center small" style="display: none;">
                        <code id="password-display" class="bg-light px-2 py-1 rounded d-inline-block mb-2"></code>
                        <button type="button" class="btn btn-link btn-sm" onclick="copyPassword()" title="Salin password">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Important Notes -->
            <div class="modern-card border-warning">
                <div class="card-header-modern bg-warning bg-opacity-10 border-warning">
                    <h6 class="card-title-modern mb-0 text-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Catatan Penting
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small mb-0">
                        <ul class="mb-0">
                            <li>Pastikan username belum digunakan</li>
                            <li>Email harus unik dan valid</li>
                            <li>Password minimal 6 karakter</li>
                            <li>Anggota wajib memiliki NIK</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAnggotaFields() {
    const role = document.getElementById('role').value;
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

// NIK formatting
document.getElementById('nik').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Phone formatting
document.getElementById('no_hp').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Form validation
document.getElementById('userForm').addEventListener('submit', function(e) {
    const form = this;
    let isValid = true;
    
    // Clear previous validation
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    // Validate required fields
    const requiredFields = ['role', 'username', 'password', 'confirm_password', 'nama', 'status', 'email'];
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });
    
    // Validate password match
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        document.getElementById('confirm_password').classList.add('is-invalid');
        document.getElementById('confirm_password').nextElementSibling.textContent = 'Password tidak cocok';
        isValid = false;
    }
    
    // Validate NIK if anggota
    const role = document.getElementById('role').value;
    if (role === 'anggota') {
        const nik = document.getElementById('nik').value;
        const alamat = document.getElementById('alamat').value;
        
        if (!nik || nik.length !== 16) {
            document.getElementById('nik').classList.add('is-invalid');
            document.getElementById('nik').nextElementSibling.textContent = 'NIK harus 16 digit';
            isValid = false;
        }
        
        if (!alamat.trim()) {
            document.getElementById('alamat').classList.add('is-invalid');
            document.getElementById('alamat').nextElementSibling.textContent = 'Alamat harus diisi';
            isValid = false;
        }
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }
});

// Initialize
toggleAnggotaFields();
</script>

<?php include '../../includes/footer.php'; ?>