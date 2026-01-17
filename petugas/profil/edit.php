<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Pastikan hanya petugas yang bisa mengakses
if ($_SESSION['role'] !== 'petugas') {
    header('Location: ' . SITE_URL);
    exit();
}

$page_title = 'Edit Profil Petugas';
$user_id = $_SESSION['user_id'];

// Ambil data petugas dari database
try {
    $query = "
        SELECT u.*, a.nik, a.no_hp, a.alamat 
        FROM users u 
        LEFT JOIN anggota a ON u.id = a.user_id 
        WHERE u.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $petugas = $stmt->fetch();
    
    if (!$petugas) {
        setFlashMessage('Data petugas tidak ditemukan', 'error');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = sanitizeInput($_POST['nama']);
    $email = sanitizeInput($_POST['email']);
    $no_hp = sanitizeInput($_POST['no_hp']);
    $alamat = sanitizeInput($_POST['alamat']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    $success_message = '';
    
    // Validasi
    if (empty($nama)) {
        $errors[] = 'Nama lengkap harus diisi';
    }
    
    if (empty($email)) {
        $errors[] = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }
    
    // Check email uniqueness
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email sudah digunakan oleh user lain';
        }
    } catch (PDOException $e) {
        $errors[] = 'Error validasi email: ' . $e->getMessage();
    }
    
    // Password validation if provided
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = 'Password saat ini harus diisi untuk mengganti password';
        } elseif (!password_verify($current_password, $petugas['password'])) {
            $errors[] = 'Password saat ini salah';
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'Password baru minimal 6 karakter';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Konfirmasi password tidak cocok';
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Update user table
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nama, $email, $hashed_password, $user_id]);
            } else {
                $sql = "UPDATE users SET nama = ?, email = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nama, $email, $user_id]);
            }
            
            // Update or insert anggota data
            if ($petugas['nik']) {
                // Update existing anggota
                $sql_anggota = "UPDATE anggota SET nama = ?, no_hp = ?, alamat = ? WHERE user_id = ?";
                $stmt_anggota = $conn->prepare($sql_anggota);
                $stmt_anggota->execute([$nama, $no_hp, $alamat, $user_id]);
            } else {
                // Check if user wants to add NIK data
                $nik = $_POST['nik'] ?? '';
                if (!empty($nik)) {
                    // Insert new anggota data
                    $sql_anggota = "INSERT INTO anggota (nik, nama, no_hp, alamat, user_id, created_at) 
                                    VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt_anggota = $conn->prepare($sql_anggota);
                    $stmt_anggota->execute([$nik, $nama, $no_hp, $alamat, $user_id]);
                }
            }
            
            $conn->commit();
            
            // Update session
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;
            
            // Log activity
            logActivity('UPDATE_PROFILE', "Profil petugas diupdate: $nama");
            
            $success_message = 'Profil berhasil diperbarui!';
            setFlashMessage($success_message, 'success');
            
            // Refresh data
            $stmt = $conn->prepare($query);
            $stmt->execute([$user_id]);
            $petugas = $stmt->fetch();
            
        } catch (PDOException $e) {
            $conn->rollback();
            $errors[] = 'Error database: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'error');
    }
}

include '../../includes/header.php';
?>

<style>
/* HANYA BACKGROUND YANG DIUBAH */
body {
    background: #f8f9fa;
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
}

/* SEMUA STYLE LAIN TETAP SAMA */
.container {
    position: relative;
    z-index: 1;
}

/* Page Header TETAP SAMA */
.page-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(120deg, #4facfe, #00f2fe);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.btn-back-header {
    padding: 0.8rem 2rem;
    background: rgba(255, 255, 255, 0.2);
    color: #4facfe;
    border: 2px solid #4facfe;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-back-header:hover {
    background: #4facfe;
    color: white;
    transform: scale(1.05);
}

/* Edit Form Card TETAP SAMA */
.edit-form-card {
    background: white;
    border-radius: 30px;
    padding: 3rem;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
}

.edit-form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 150px;
    background: linear-gradient(135deg, #4facfe, #00f2fe);
}

.form-content {
    position: relative;
    z-index: 1;
}

.form-section {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #eee;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #4facfe;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #4facfe;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: block;
}

.required::after {
    content: ' *';
    color: #eb3349;
}

.form-control-modern {
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    padding: 0.8rem 1.2rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.form-control-modern:focus {
    border-color: #4facfe;
    box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
    background: white;
}

.input-group-text-modern {
    background: #4facfe;
    border: 2px solid #4facfe;
    color: white;
    border-radius: 15px;
    cursor: pointer;
}

.input-group-text-modern:hover {
    background: #00f2fe;
    border-color: #00f2fe;
}

.form-text-small {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.3rem;
}

/* Current Info TETAP SAMA */
.current-info {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 2px solid #dee2e6;
}

.current-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.8rem;
}

.current-info-row:last-child {
    margin-bottom: 0;
}

.current-info-label {
    font-weight: 600;
    color: #666;
}

.current-info-value {
    font-weight: 700;
    color: #333;
}

/* Buttons TETAP SAMA */
.btn-submit {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 1rem 3rem;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(79, 172, 254, 0.4);
    color: white;
}

.btn-cancel {
    background: white;
    color: #666;
    border: 2px solid #dee2e6;
    border-radius: 50px;
    padding: 1rem 3rem;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-cancel:hover {
    background: #f8f9fa;
    color: #333;
    transform: translateY(-3px);
}

/* Alert TETAP SAMA */
.alert-custom {
    border-radius: 20px;
    padding: 1.5rem;
    border: none;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    animation: slideIn 0.5s ease;
}

.alert-custom.alert-success {
    background: linear-gradient(135deg, #d4edda, #e8f5e9);
    border-left: 5px solid #28a745;
}

.alert-custom.alert-error {
    background: linear-gradient(135deg, #f8d7da, #ffe0e3);
    border-left: 5px solid #eb3349;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive TETAP SAMA */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .edit-form-card {
        padding: 2rem 1.5rem;
    }
    
    .btn-submit, .btn-cancel {
        width: 100%;
        justify-content: center;
        margin-bottom: 1rem;
    }
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="index.php">Profil Petugas</a>
                    </li>
                    <li class="breadcrumb-item active">Edit Profil</li>
                </ol>
            </nav>
            <h1>Edit Profil Petugas</h1>
        </div>
        <a href="index.php" class="btn-back-header">
            <i class="fas fa-arrow-left me-2"></i>Kembali ke Profil
        </a>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert-custom alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= $flash['message'] ?>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="edit-form-card">
        <div class="form-content">
            <!-- Current Info -->
            <div class="current-info">
                <div class="current-info-row">
                    <span class="current-info-label">Username saat ini:</span>
                    <span class="current-info-value"><?= htmlspecialchars($petugas['username']) ?></span>
                </div>
                <div class="current-info-row">
                    <span class="current-info-label">Role:</span>
                    <span class="current-info-value">
                        <span class="badge bg-info"><?= ucfirst($petugas['role']) ?></span>
                    </span>
                </div>
                <div class="current-info-row">
                    <span class="current-info-label">Status:</span>
                    <span class="current-info-value">
                        <span class="badge bg-<?= $petugas['status'] === 'aktif' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($petugas['status']) ?>
                        </span>
                    </span>
                </div>
            </div>

            <form method="POST" id="editProfileForm">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>Informasi Pribadi
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="nama">Nama Lengkap</label>
                            <input type="text" 
                                   name="nama" 
                                   id="nama"
                                   class="form-control-modern" 
                                   value="<?= htmlspecialchars($petugas['nama']) ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required" for="email">Email</label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   class="form-control-modern" 
                                   value="<?= htmlspecialchars($petugas['email']) ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-address-book"></i>Informasi Kontak
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nik">NIK</label>
                            <input type="text" 
                                   name="nik" 
                                   id="nik"
                                   class="form-control-modern" 
                                   value="<?= htmlspecialchars($petugas['nik'] ?? '') ?>"
                                   placeholder="Opsional">
                            <div class="form-text-small">
                                Kosongkan jika tidak ingin menambahkan NIK
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label" for="no_hp">No. HP</label>
                            <input type="text" 
                                   name="no_hp" 
                                   id="no_hp"
                                   class="form-control-modern" 
                                   value="<?= htmlspecialchars($petugas['no_hp'] ?? '') ?>"
                                   placeholder="08xxxxxxxxxx">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label" for="alamat">Alamat</label>
                            <textarea name="alamat" 
                                      id="alamat"
                                      class="form-control-modern" 
                                      rows="3"
                                      placeholder="Alamat lengkap"><?= htmlspecialchars($petugas['alamat'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-lock"></i>Ubah Password
                    </h3>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Kosongkan jika tidak ingin mengubah password
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Password Saat Ini</label>
                            <div class="input-group">
                                <input type="password" 
                                       name="current_password" 
                                       id="current_password"
                                       class="form-control-modern"
                                       placeholder="Password saat ini">
                                <button type="button" class="input-group-text input-group-text-modern" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye" id="current_password_eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label" for="new_password">Password Baru</label>
                            <div class="input-group">
                                <input type="password" 
                                       name="new_password" 
                                       id="new_password"
                                       class="form-control-modern"
                                       placeholder="Minimal 6 karakter">
                                <button type="button" class="input-group-text input-group-text-modern" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label" for="confirm_password">Konfirmasi Password</label>
                            <div class="input-group">
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password"
                                       class="form-control-modern"
                                       placeholder="Ulangi password baru">
                                <button type="button" class="input-group-text input-group-text-modern" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-section text-center">
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                        <a href="index.php" class="btn-cancel">
                            <i class="fas fa-times me-2"></i>Batal
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eye = document.getElementById(fieldId + '_eye');
    
    if (field.type === 'password') {
        field.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Phone number formatting
document.getElementById('no_hp').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// NIK formatting
document.getElementById('nik').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
});

// Form validation
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const email = document.getElementById('email').value.trim();
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Basic validation
    if (!nama) {
        e.preventDefault();
        alert('Nama lengkap harus diisi');
        document.getElementById('nama').focus();
        return false;
    }
    
    if (!email) {
        e.preventDefault();
        alert('Email harus diisi');
        document.getElementById('email').focus();
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Format email tidak valid');
        document.getElementById('email').focus();
        return false;
    }
    
    // Password validation
    if (currentPassword || newPassword || confirmPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Password saat ini harus diisi untuk mengganti password');
            document.getElementById('current_password').focus();
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('Password baru minimal 6 karakter');
            document.getElementById('new_password').focus();
            return false;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Konfirmasi password tidak cocok');
            document.getElementById('confirm_password').focus();
            return false;
        }
    }
    
    return true;
});
</script>

<?php include '../../includes/footer.php'; ?>