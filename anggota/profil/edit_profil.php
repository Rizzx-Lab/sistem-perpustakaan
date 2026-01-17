<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('anggota');

$page_title = 'Edit Profil';
include '../../config/database.php';

// Get user profile
try {
    $query = "
        SELECT u.*, a.nik, a.nama, a.alamat, a.no_hp
        FROM users u
        LEFT JOIN anggota a ON u.id = a.user_id
        WHERE u.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('Data pengguna tidak ditemukan', 'error');
        redirect(SITE_URL . 'anggota/dashboard.php');
    }
    
} catch (PDOException $e) {
    setFlashMessage('Terjadi kesalahan: ' . $e->getMessage(), 'error');
    redirect(SITE_URL . 'anggota/profil/');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Validate inputs
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $nama = trim($_POST['nama']);
        $alamat = trim($_POST['alamat']);
        $no_hp = trim($_POST['no_hp']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid');
        }
        
        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception('Email sudah digunakan oleh pengguna lain');
        }
        
        // Update users table
        if (!empty($password)) {
            // Validate password confirmation
            if ($password !== $_POST['password_confirm']) {
                throw new Exception('Password dan konfirmasi password tidak cocok');
            }
            if (strlen($password) < 6) {
                throw new Exception('Password minimal 6 karakter');
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET email = ?, password = ?, nama = ? WHERE id = ?");
            $stmt->execute([$email, $hashed_password, $nama, $_SESSION['user_id']]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, nama = ? WHERE id = ?");
            $stmt->execute([$email, $nama, $_SESSION['user_id']]);
        }
        
        // Update anggota table
        $stmt = $conn->prepare("
            UPDATE anggota 
            SET nama = ?, alamat = ?, no_hp = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$nama, $alamat, $no_hp, $_SESSION['user_id']]);
        
        $conn->commit();
        
        // Log activity
        logActivity('UPDATE_PROFIL', "Profil diperbarui: {$nama}");
        
        setFlashMessage('Profil berhasil diperbarui', 'success');
        redirect(SITE_URL . 'anggota/profil/');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = 'Gagal memperbarui profil: ' . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
    animation: float 20s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}

.container {
    position: relative;
    z-index: 1;
}

/* Page Header */
.page-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 25px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.8s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(120deg, #fff 0%, #ffd89b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 1rem;
}

.breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
}

.breadcrumb-item a:hover {
    color: #ffd89b;
}

.breadcrumb-item.active {
    color: rgba(255, 255, 255, 0.7);
}

.btn-back {
    padding: 0.8rem 2rem;
    background: white;
    color: #667eea;
    border: 2px solid white;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
}

.btn-back:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(255, 255, 255, 0.5);
    color: #667eea;
}

/* Alert */
.alert-custom {
    border-radius: 20px;
    padding: 1.5rem;
    border: none;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    animation: slideIn 0.5s ease;
    margin-bottom: 2rem;
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

/* Form Card */
.form-card {
    background: white;
    border-radius: 25px;
    overflow: hidden;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    margin-bottom: 2rem;
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-card-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    padding: 1.5rem 2rem;
    color: white;
}

.form-card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-card-body {
    padding: 2rem;
}

/* Form Inputs */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.95rem;
}

.form-label .required {
    color: #eb3349;
}

.form-input {
    width: 100%;
    padding: 0.9rem 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
}

.form-input:disabled {
    background: #e9ecef;
    cursor: not-allowed;
    color: #6c757d;
}

.form-textarea {
    width: 100%;
    padding: 0.9rem 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
    resize: vertical;
    min-height: 100px;
}

.form-textarea:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
}

.form-hint {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.3rem;
    display: block;
}

/* Action Buttons */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
}

.btn-action {
    padding: 0.9rem 2.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-cancel {
    background: white;
    color: #666;
    border: 2px solid #e0e0e0;
}

.btn-cancel:hover {
    background: #f8f9fa;
    border-color: #666;
    color: #333;
    transform: scale(1.02);
}

.btn-save {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.btn-save:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

/* Password Strength Indicator */
.password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #666;
    transition: color 0.3s ease;
}

.password-toggle:hover {
    color: #667eea;
}

/* Grid Layout */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.form-grid-full {
    grid-column: 1 / -1;
}

/* Info Box */
.info-box {
    background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
    border-left: 4px solid #667eea;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.info-box p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .page-header h1 {
        font-size: 1.8rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-card-body {
        padding: 1.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
}

/* Animation Delays */
.form-card:nth-child(1) {
    animation-delay: 0.1s;
}

.form-card:nth-child(2) {
    animation-delay: 0.2s;
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
                        <a href="index.php">Profil</a>
                    </li>
                    <li class="breadcrumb-item active">Edit Profil</li>
                </ol>
            </nav>
            <h1>
                Edit Profil
            </h1>
        </div>
        <a href="index.php" class="btn-back">
            Kembali
        </a>
    </div>

    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="alert-custom">
            <strong>Error!</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form method="POST" id="editProfilForm">
                <!-- Account Information -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h5>
                            <i class="fas fa-user-shield"></i>
                            Informasi Akun
                        </h5>
                    </div>
                    <div class="form-card-body">
                        <div class="info-box">
                            <p>
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Catatan:</strong> Username dan NIK tidak dapat diubah. Hubungi administrator jika perlu perubahan.
                            </p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input" 
                                       value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="form-hint">Username tidak dapat diubah</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                       placeholder="email@example.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password" id="password" 
                                           class="form-input" placeholder="Minimal 6 karakter">
                                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                </div>
                                <small class="form-hint">Kosongkan jika tidak ingin mengubah password</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="password_confirm" id="password_confirm" 
                                           class="form-input" placeholder="Ulangi password baru">
                                    <i class="fas fa-eye password-toggle" id="togglePasswordConfirm"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h5>
                            <i class="fas fa-address-card"></i>
                            Informasi Pribadi
                        </h5>
                    </div>
                    <div class="form-card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-input" 
                                       value="<?= htmlspecialchars($user['nik']) ?>" disabled>
                                <small class="form-hint">NIK tidak dapat diubah</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Nama Lengkap <span class="required">*</span>
                                </label>
                                <input type="text" name="nama" class="form-input" 
                                       value="<?= htmlspecialchars($user['nama']) ?>" 
                                       placeholder="Masukkan nama lengkap" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">No. HP</label>
                                <input type="tel" name="no_hp" class="form-input" 
                                       value="<?= htmlspecialchars($user['no_hp']) ?>" 
                                       placeholder="08xxxxxxxxxx">
                            </div>
                            
                            <div class="form-group form-grid-full">
                                <label class="form-label">Alamat</label>
                                <textarea name="alamat" class="form-textarea" 
                                          placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($user['alamat']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <a href="index.php" class="btn-action btn-cancel">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn-action btn-save">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('editProfilForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password_confirm').value;
    
    if (password !== '' && password !== passwordConfirm) {
        e.preventDefault();
        alert('Password dan konfirmasi password tidak cocok!');
        return false;
    }
    
    if (password !== '' && password.length < 6) {
        e.preventDefault();
        alert('Password minimal 6 karakter!');
        return false;
    }
});

// Toggle password visibility
function togglePasswordVisibility(inputId, toggleId) {
    const input = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    
    toggle.addEventListener('click', function() {
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
}

togglePasswordVisibility('password', 'togglePassword');
togglePasswordVisibility('password_confirm', 'togglePasswordConfirm');

// Smooth scroll on load
document.addEventListener('DOMContentLoaded', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

<?php include '../../includes/footer.php'; ?>