<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireRole(['admin']);

$page_title = 'Pengaturan';
include '../../config/database.php';

// Get system info
try {
    $db_size = $conn->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size 
        FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "'
    ")->fetchColumn();
    
    $table_count = $conn->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = '" . DB_NAME . "'
    ")->fetchColumn();
    
    $db_version = $conn->query("SELECT VERSION()")->fetchColumn();
    
} catch (PDOException $e) {
    $db_size = 0;
    $table_count = 0;
    $db_version = 'Unknown';
}

include '../../includes/header.php';
?>

<style>
/* Modern Card Styles */
.modern-card {
    background: white;
    border-radius: 20px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    position: relative;
}

.modern-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}

.modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #5a67d8, #6b46c1);
}

.modern-card .card-body {
    padding: 2rem;
}

.modern-card-header {
    background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    padding: 1.5rem 2rem;
    color: white;
    border-radius: 20px 20px 0 0 !important;
}

.card-title-modern {
    font-weight: 700;
    font-size: 1.5rem;
    margin: 0;
}

/* Button Styles - More Subtle Gradients */
.btn-modern {
    padding: 0.8rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

/* Primary - Subtle Blue to Purple */
.btn-primary-modern {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    color: white;
    border: 1px solid #4c51bf;
}

.btn-primary-modern:hover {
    background: linear-gradient(135deg, #4c51bf, #553c9a);
    color: white;
}

/* Success - Subtle Green */
.btn-success-modern {
    background: linear-gradient(135deg, #0d9488, #059669);
    color: white;
    border: 1px solid #059669;
}

.btn-success-modern:hover {
    background: linear-gradient(135deg, #047857, #065f46);
    color: white;
}

/* Info - Subtle Pink */
.btn-info-modern {
    background: linear-gradient(135deg, #d946ef, #c026d3);
    color: white;
    border: 1px solid #c026d3;
}

.btn-info-modern:hover {
    background: linear-gradient(135deg, #a21caf, #86198f);
    color: white;
}

/* Danger - Subtle Red */
.btn-danger-modern {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border: 1px solid #b91c1c;
}

.btn-danger-modern:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    color: white;
}

/* Warning - Subtle Orange */
.btn-warning-modern {
    background: linear-gradient(135deg, #ea580c, #c2410c);
    color: white;
    border: 1px solid #c2410c;
}

.btn-warning-modern:hover {
    background: linear-gradient(135deg, #c2410c, #9a3412);
    color: white;
}

/* Purple - For User Management */
.btn-purple-modern {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: white;
    border: 1px solid #6d28d9;
}

.btn-purple-modern:hover {
    background: linear-gradient(135deg, #6d28d9, #5b21b6);
    color: white;
}

/* Settings Card Specific */
.settings-card {
    height: 100%;
    text-align: center;
}

.settings-icon {
    width: 90px;
    height: 90px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--icon-color-1), var(--icon-color-2));
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transition: all 0.4s ease;
    border: 3px solid white;
}

.settings-card:hover .settings-icon {
    transform: scale(1.08);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
}

.settings-icon i {
    color: white;
    font-size: 2.5rem;
}

.settings-card .card-title {
    font-weight: 700;
    font-size: 1.3rem;
    margin-bottom: 0.8rem;
    color: #333;
}

.settings-card .card-text {
    color: #666;
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
    min-height: 60px;
    line-height: 1.5;
}

/* Quick Stats */
.quick-stats-item {
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    border-radius: 15px;
}

.quick-stats-item:hover {
    background: #f7fafc;
    transform: translateY(-5px);
}

.quick-stats-item .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.quick-stats-item .stat-label {
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #666;
}

/* Modal Custom */
.modern-modal .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    border: 1px solid #e5e7eb;
}

.modern-modal .modal-header {
    border-radius: 20px 20px 0 0;
    padding: 1.5rem 2rem;
}

.modern-modal .modal-body {
    padding: 2rem;
}

/* Title Styling */
.title-gradient {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
}

.logo-emoji {
    font-size: 2rem;
    margin-right: 0.5rem;
}

/* Action Buttons Container */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    margin-top: 0.5rem;
}

.action-buttons .btn-modern {
    flex: 1;
    min-width: 120px;
    padding: 0.7rem 1.2rem;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .modern-card .card-body {
        padding: 1.5rem;
    }
    
    .settings-icon {
        width: 70px;
        height: 70px;
    }
    
    .settings-icon i {
        font-size: 2rem;
    }
    
    .btn-modern {
        padding: 0.6rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .action-buttons .btn-modern {
        min-width: auto;
        width: 100%;
    }
}

@media (max-width: 576px) {
    .quick-stats-item {
        margin-bottom: 1rem;
    }
    
    .quick-stats-item .stat-number {
        font-size: 2rem;
    }
}

/* List Group Items */
.list-group-item-action {
    border: 1px solid #e5e7eb;
    margin-bottom: 0.5rem;
    border-radius: 10px !important;
    transition: all 0.3s ease;
}

.list-group-item-action:hover {
    border-color: #5a67d8;
    transform: translateX(5px);
    background: #f8fafc;
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php" class="text-decoration-none">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-item active">Pengaturan</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <span class="logo-emoji">⚙️</span>
                <span class="title-gradient">Pengaturan Sistem</span>
            </h1>
        </div>
    </div>

    <!-- System Info Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="modern-card text-center p-4">
                <div class="settings-icon" style="--icon-color-1: #5a67d8; --icon-color-2: #6b46c1;">
                    <i class="fas fa-database"></i>
                </div>
                <div class="h4 mb-2"><?= $db_size ?> MB</div>
                <div class="stat-label">Ukuran Database</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="modern-card text-center p-4">
                <div class="settings-icon" style="--icon-color-1: #0d9488; --icon-color-2: #059669;">
                    <i class="fas fa-table"></i>
                </div>
                <div class="h4 mb-2"><?= $table_count ?></div>
                <div class="stat-label">Total Tabel</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="modern-card text-center p-4">
                <div class="settings-icon" style="--icon-color-1: #d946ef; --icon-color-2: #c026d3;">
                    <i class="fas fa-server"></i>
                </div>
                <div class="h6 mb-2"><?= explode('-', $db_version)[0] ?></div>
                <div class="stat-label">MySQL Version</div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="modern-card text-center p-4">
                <div class="settings-icon" style="--icon-color-1: #ea580c; --icon-color-2: #c2410c;">
                    <i class="fab fa-php"></i>
                </div>
                <div class="h6 mb-2"><?= phpversion() ?></div>
                <div class="stat-label">PHP Version</div>
            </div>
        </div>
    </div>

    <!-- Settings Menu -->
    <div class="row g-4 mb-5">
        <!-- Pengaturan Sistem -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #5a67d8; --icon-color-2: #6b46c1;">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h5 class="card-title">Pengaturan Sistem</h5>
                    <p class="card-text">
                        Konfigurasi perpustakaan, aturan peminjaman, dan informasi perpustakaan
                    </p>
                    <a href="sistem.php" class="btn btn-modern btn-primary-modern">
                        <i class="fas fa-cog me-2"></i>Kelola
                    </a>
                </div>
            </div>
        </div>

        <!-- Backup Database -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #0d9488; --icon-color-2: #059669;">
                        <i class="fas fa-database"></i>
                    </div>
                    <h5 class="card-title">Backup Database</h5>
                    <p class="card-text">
                        Backup dan restore data perpustakaan untuk keamanan
                    </p>
                    <a href="backup.php" class="btn btn-modern btn-success-modern">
                        <i class="fas fa-download me-2"></i>Backup
                    </a>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #d946ef; --icon-color-2: #c026d3;">
                        <i class="fas fa-history"></i>
                    </div>
                    <h5 class="card-title">Log Aktivitas</h5>
                    <p class="card-text">
                        Monitor dan lihat semua aktivitas sistem
                    </p>
                    <a href="log.php" class="btn btn-modern btn-info-modern">
                        <i class="fas fa-eye me-2"></i>Lihat Log
                    </a>
                </div>
            </div>
        </div>

        <!-- Manage Users -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #7c3aed; --icon-color-2: #6d28d9;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h5 class="card-title">Kelola Users</h5>
                    <p class="card-text">
                        Manajemen admin, petugas, dan anggota
                    </p>
                    <div class="action-buttons">
                        <a href="../users/anggota.php" class="btn btn-modern btn-success-modern">
                            <i class="fas fa-user me-1"></i>Anggota
                        </a>
                        <a href="../users/petugas.php" class="btn btn-modern btn-info-modern">
                            <i class="fas fa-user-tie me-1"></i>Petugas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Maintenance -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #dc2626; --icon-color-2: #b91c1c;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h5 class="card-title">Maintenance</h5>
                    <p class="card-text">
                        Optimasi database dan pembersihan data
                    </p>
                    <button type="button" class="btn btn-modern btn-danger-modern" onclick="showMaintenanceModal()">
                        <i class="fas fa-wrench me-2"></i>Maintenance
                    </button>
                </div>
            </div>
        </div>

        <!-- Test Notifikasi Email -->
        <div class="col-lg-4 col-md-6">
            <div class="modern-card settings-card">
                <div class="card-body">
                    <div class="settings-icon" style="--icon-color-1: #ea580c; --icon-color-2: #c2410c;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5 class="card-title">Test Notifikasi</h5>
                    <p class="card-text">
                        Test pengiriman email notifikasi keterlambatan
                    </p>
                    <a href="test_notification.php" class="btn btn-modern btn-warning-modern">
                        <i class="fas fa-paper-plane me-2"></i>Test Email
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="modern-card mb-4">
        <div class="modern-card-header">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-chart-line me-2"></i>Statistik Cepat
            </h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <?php
                try {
                    $stats = [
                        'users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                        'books' => $conn->query("SELECT COUNT(*) FROM buku")->fetchColumn(),
                        'members' => $conn->query("SELECT COUNT(*) FROM anggota")->fetchColumn(),
                        'loans' => $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'")->fetchColumn()
                    ];
                } catch (PDOException $e) {
                    $stats = ['users' => 0, 'books' => 0, 'members' => 0, 'loans' => 0];
                }
                ?>
                <div class="col-md-3 col-6 quick-stats-item">
                    <div class="stat-number" style="--stat-color-1: #5a67d8; --stat-color-2: #6b46c1;">
                        <?= $stats['users'] ?>
                    </div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="col-md-3 col-6 quick-stats-item">
                    <div class="stat-number" style="--stat-color-1: #0d9488; --stat-color-2: #059669;">
                        <?= $stats['books'] ?>
                    </div>
                    <div class="stat-label">Total Buku</div>
                </div>
                <div class="col-md-3 col-6 quick-stats-item">
                    <div class="stat-number" style="--stat-color-1: #d946ef; --stat-color-2: #c026d3;">
                        <?= $stats['members'] ?>
                    </div>
                    <div class="stat-label">Anggota Aktif</div>
                </div>
                <div class="col-md-3 col-6 quick-stats-item">
                    <div class="stat-number" style="--stat-color-1: #ea580c; --stat-color-2: #c2410c;">
                        <?= $stats['loans'] ?>
                    </div>
                    <div class="stat-label">Sedang Dipinjam</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Result Modal (Auto Show) -->
<?php if (isset($_GET['show_result']) && isset($_SESSION['maintenance_result'])): 
    $result = $_SESSION['maintenance_result'];
    unset($_SESSION['maintenance_result']);
?>
<div class="modal fade modern-modal" id="resultModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-<?= $result['color'] ?>">
            <div class="modal-header bg-<?= $result['color'] ?> text-white">
                <h5 class="modal-title">
                    <i class="fas <?= $result['icon'] ?> me-2"></i>
                    <?= $result['title'] ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($result['success']): ?>
                    <div class="alert alert-success border-0 bg-green-50">
                        <i class="fas fa-check-circle me-2 text-success"></i>
                        <strong class="text-success">Proses berhasil diselesaikan!</strong>
                    </div>
                    
                    <h6 class="mb-3 text-gray-700">📊 Hasil Detail:</h6>
                    <table class="table table-sm table-borderless">
                        <?php foreach ($result['stats'] as $label => $value): ?>
                        <tr>
                            <th width="50%" class="ps-3 text-gray-600">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= $label ?>
                            </th>
                            <td class="text-gray-800"><strong><?= $value ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <?php if (!empty($result['errors'])): ?>
                    <div class="alert alert-warning mt-3 border-0 bg-yellow-50">
                        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                        <strong class="text-warning">Warning:</strong> 
                        <span class="text-gray-700"><?= count($result['errors']) ?> items failed</span>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-danger border-0 bg-red-50">
                        <i class="fas fa-times-circle me-2 text-danger"></i>
                        <strong class="text-danger">Proses gagal!</strong>
                        <p class="mb-0 mt-2 text-gray-700"><?= htmlspecialchars($result['error_message']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-<?= $result['color'] ?>" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto show result modal
document.addEventListener('DOMContentLoaded', function() {
    const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
    resultModal.show();
    
    // Auto close after 10 seconds
    setTimeout(function() {
        resultModal.hide();
        // Remove show_result from URL
        const url = new URL(window.location);
        url.searchParams.delete('show_result');
        window.history.replaceState({}, '', url);
    }, 10000);
});
</script>
<?php endif; ?>

<!-- Maintenance Modal -->
<div class="modal fade modern-modal" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-tools me-2"></i>System Maintenance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0 bg-yellow-50">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    <strong class="text-warning">Peringatan!</strong> 
                    <span class="text-gray-700">Operasi maintenance dapat mempengaruhi performa sistem.</span>
                </div>

                <div class="list-group">
                    <a href="maintenance.php?action=optimize" class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="settings-icon me-3" style="--icon-color-1: #5a67d8; --icon-color-2: #6b46c1; width: 50px; height: 50px;">
                            <i class="fas fa-database"></i>
                        </div>
                        <div>
                            <strong class="text-gray-800">Optimize Database</strong>
                            <p class="mb-0 small text-gray-600">Optimalkan performa database</p>
                        </div>
                    </a>
                    <a href="maintenance.php?action=clear_cache" class="list-group-item list-group-item-action d-flex align-items-center mt-2">
                        <div class="settings-icon me-3" style="--icon-color-1: #0d9488; --icon-color-2: #059669; width: 50px; height: 50px;">
                            <i class="fas fa-broom"></i>
                        </div>
                        <div>
                            <strong class="text-gray-800">Clear Cache</strong>
                            <p class="mb-0 small text-gray-600">Bersihkan cache sistem</p>
                        </div>
                    </a>
                </div>
                
                <div class="alert alert-info mt-3 mb-0 border-0 bg-blue-50">
                    <i class="fas fa-info-circle me-2 text-info"></i>
                    <small class="text-gray-700">
                        <strong>Note:</strong> Untuk cleanup log lama, gunakan menu 
                        <a href="log.php" class="text-info fw-bold">Log Aktivitas</a>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showMaintenanceModal() {
    const modal = new bootstrap.Modal(document.getElementById('maintenanceModal'));
    modal.show();
}

// Animation for cards on load
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.modern-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 80);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>