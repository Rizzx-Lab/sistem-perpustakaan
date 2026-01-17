<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Hanya admin yang bisa akses
requireRole(['admin']);

include '../../config/database.php';

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filters = [
        'username' => $_GET['filter_username'] ?? '',
        'role' => $_GET['filter_role'] ?? '',
        'action' => $_GET['filter_action'] ?? '',
        'date_from' => $_GET['filter_date_from'] ?? '',
        'date_to' => $_GET['filter_date_to'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    logActivity('EXPORT_LOG', 'Export log aktivitas ke CSV');
    exportLogsToCSV($filters);
    exit;
}

// Handle cleanup - Hanya admin yang bisa cleanup logs
if (isset($_POST['cleanup_logs'])) {
    if ($_SESSION['role'] !== 'admin') {
        setFlashMessage('Akses ditolak. Hanya admin yang dapat menghapus log.', 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
    
    $days = intval($_POST['cleanup_days'] ?? 90);
    $deleted = cleanupOldLogs($days);
    
    // Log the cleanup action
    logActivity('CLEANUP_LOGS', "Deleted $deleted old log entries (older than $days days)");
    
    setFlashMessage("Berhasil menghapus {$deleted} log lama (lebih dari {$days} hari)", 'success');
    redirect($_SERVER['PHP_SELF']);
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$filters = [
    'username' => $_GET['filter_username'] ?? '',
    'role' => $_GET['filter_role'] ?? '',
    'action' => $_GET['filter_action'] ?? '',
    'date_from' => $_GET['filter_date_from'] ?? '',
    'date_to' => $_GET['filter_date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get logs
$logs_result = getLogs($filters, $limit, $offset);
$logs = $logs_result['data'];
$total_logs = $logs_result['total'];
$total_pages = ceil($total_logs / $limit);

// Get statistics
$stats = getLogStats(30); // 30 hari terakhir

// Get available actions untuk filter
$available_actions = getLogActions();

$page_title = 'Log Aktivitas Sistem';
include '../../includes/header.php';
?>

<style>
/* Base styles untuk menghindari geter-geter */
* {
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    overflow-x: hidden;
}

/* Styling untuk memperbaiki card yang mepet */
.modern-card {
    border-radius: 12px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
    background: #fff;
    position: relative;
    z-index: 1;
}

.card-header-modern {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 20px;
    border-radius: 12px 12px 0 0 !important;
    position: sticky;
    top: 0;
    z-index: 2;
}

.card-body {
    padding: 20px;
    position: relative;
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
    background-color: #fff;
}

.form-control-modern:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
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

/* Perbaikan untuk tombol primary */
.btn-primary-modern {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-color: #007bff;
    color: white;
}

.btn-primary-modern:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    border-color: #0056b3;
    color: white;
}

/* Perbaikan untuk tombol danger */
.btn-danger-modern {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border-color: #dc3545;
    color: white;
}

.btn-danger-modern:hover {
    background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
    border-color: #c82333;
    color: white;
}

/* Perbaikan untuk tombol */
.btn-modern {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.btn-modern:active {
    transform: translateY(1px);
}

/* Perbaikan untuk alert */
.alert {
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
    position: relative;
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

/* Perbaikan untuk form-text */
.form-text {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #6c757d;
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

/* Perbaikan untuk badge */
.badge {
    padding: 5px 10px;
    border-radius: 6px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 60px;
}

/* Perbaikan untuk table modern - Mencegah geter-geter */
.table-modern {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    margin: 0;
    table-layout: fixed;
}

.table-modern th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 15px;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-modern td {
    padding: 10px 15px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
    vertical-align: middle;
}

.table-modern tr:hover td {
    background-color: #f8f9fa;
}

.table-modern tr:last-child td {
    border-bottom: none;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

/* Perbaikan untuk title gradient */
.title-gradient {
    background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.logo-emoji {
    margin-right: 10px;
    font-size: 1.5rem;
    display: inline-block;
}

/* Statistics cards */
.modern-card.text-center {
    padding: 20px;
    height: 100%;
}

.stat-icon {
    margin-bottom: 10px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin: 10px 0;
    display: block;
}

.stat-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Modal styling - Perbaikan khusus untuk mencegah geter-geter */
.modal {
    overflow-y: auto !important;
}

.modal-dialog {
    margin: 1.75rem auto;
    display: flex;
    align-items: center;
    min-height: calc(100% - 3.5rem);
}

.modal-dialog.modal-lg {
    max-width: 900px;
}

.modal-dialog.modal-xl {
    max-width: 1140px;
}

.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    overflow: hidden;
}

.modal-header {
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 20px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e0e0e0;
    background-color: #f8f9fa;
}

/* Progress bar */
.progress {
    height: 24px;
    border-radius: 12px;
    overflow: hidden;
    background-color: #e9ecef;
    position: relative;
}

.progress-bar {
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 30px;
}

/* Code styling */
code {
    background-color: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    display: inline-block;
}

/* Pagination */
.pagination {
    margin-bottom: 0;
    flex-wrap: wrap;
}

.page-link {
    border-radius: 6px;
    margin: 0 2px;
    border: 1px solid #dee2e6;
    color: #007bff;
    min-width: 38px;
    text-align: center;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border-color: #007bff;
}

/* Detail log specific styles */
.modal-body .row {
    margin-bottom: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.modal-body .row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.modal-body .fw-semibold {
    font-weight: 600;
    color: #495057;
}

.modal-body .bg-light {
    background-color: #f8f9fa !important;
    border-radius: 6px;
}

/* Statistics modal specific styles */
#statsModal .modal-body {
    padding: 15px;
}

#statsModal .modern-card {
    margin-bottom: 15px;
}

#statsModal .table-responsive {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
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
        position: relative;
    }
    
    .form-control-modern {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }
    
    .btn-modern {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .table-modern th,
    .table-modern td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }
    
    .badge {
        padding: 4px 8px;
        font-size: 0.8rem;
        min-width: 50px;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-icon i {
        font-size: 1.5rem;
    }
    
    .modal-body {
        padding: 15px;
        max-height: calc(100vh - 150px);
    }
    
    .modal-header {
        padding: 12px 15px;
    }
    
    .modal-footer {
        padding: 12px 15px;
    }
    
    .col-xl-3, .col-xl-2, .col-xl-1, .col-xl-8, .col-xl-4 {
        padding-bottom: 0.5rem;
    }
    
    /* Mobile modal fix */
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
        min-height: auto;
    }
    
    .modal-dialog.modal-xl,
    .modal-dialog.modal-lg {
        max-width: calc(100% - 1rem);
    }
    
    #statsModal .modal-body {
        padding: 10px;
    }
    
    #statsModal .table-responsive {
        max-height: 300px;
    }
}

@media (max-width: 576px) {
    .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .d-flex.gap-2 .btn-modern {
        margin-bottom: 0.5rem;
        width: 100%;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .modal-body .row {
        margin-bottom: 10px;
        padding: 8px 0;
    }
    
    /* Fix untuk tabel pada mobile */
    .table-modern {
        font-size: 0.8rem;
    }
    
    .table-modern th,
    .table-modern td {
        padding: 6px 8px;
    }
    
    /* Statistik card mobile */
    .stat-icon {
        height: 30px;
        margin-bottom: 5px;
    }
    
    .stat-icon i {
        font-size: 1.2rem;
    }
    
    .stat-number {
        font-size: 1.2rem;
        margin: 5px 0;
    }
    
    .stat-label {
        font-size: 0.7rem;
    }
}

/* Fix untuk tinggi modal statistik */
#statsModal .modal-body {
    display: flex;
    flex-direction: column;
    min-height: 400px;
}

#statsModal .row {
    flex: 1;
}

/* Smooth scroll behavior */
html {
    scroll-behavior: smooth;
}

/* Loading state untuk tombol */
.btn-modern.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-modern.loading:after {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-left: 8px;
    border: 2px solid #fff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    vertical-align: middle;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Fix untuk z-index overlapping */
.modal {
    z-index: 1060 !important;
}

.modal-backdrop {
    z-index: 1050 !important;
}

/* Prevent text selection during animation */
.modal-content {
    user-select: none;
}

/* Custom scrollbar untuk modal */
.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="./"><i class="fas fa-cogs"></i> Pengaturan</a>
                    </li>
                    <li class="breadcrumb-item active">Log Aktivitas</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📊</span>
                <span class="title-gradient">Log Aktivitas Sistem</span>
            </h1>
            <p class="text-muted mb-0 mt-2">Monitor semua aktivitas pengguna di sistem perpustakaan</p>
        </div>
        <div>
            <a href="./" class="btn btn-modern btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show mb-4" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-database fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary mb-1"><?= number_format($stats['total_logs'] ?? 0) ?></div>
                    <small class="stat-label">Total Log</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success mb-1"><?= number_format($stats['logs_today'] ?? 0) ?></div>
                    <small class="stat-label">Hari Ini</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning mb-1"><?= number_format($stats['logs_week'] ?? 0) ?></div>
                    <small class="stat-label">Minggu Ini</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="modern-card text-center">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-users fa-2x text-info"></i>
                    </div>
                    <div class="stat-number text-info mb-1"><?= count($stats['active_users'] ?? []) ?></div>
                    <small class="stat-label">User Aktif</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="modern-card mb-4">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-filter me-2"></i>Filter & Pencarian Log
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label-modern">Username</label>
                    <input type="text" 
                           name="filter_username" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filters['username']) ?>" 
                           placeholder="Filter username...">
                </div>
                
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label-modern">Role</label>
                    <select name="filter_role" class="form-control-modern">
                        <option value="">Semua Role</option>
                        <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $filters['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="anggota" <?= $filters['role'] === 'anggota' ? 'selected' : '' ?>>Anggota</option>
                    </select>
                </div>
                
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label-modern">Aksi</label>
                    <select name="filter_action" class="form-control-modern">
                        <option value="">Semua Aksi</option>
                        <?php foreach ($available_actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" 
                                    <?= $filters['action'] === $action ? 'selected' : '' ?>>
                                <?= htmlspecialchars($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label-modern">Dari Tanggal</label>
                    <input type="date" 
                           name="filter_date_from" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                
                <div class="col-xl-2 col-lg-3 col-md-6">
                    <label class="form-label-modern">Sampai Tanggal</label>
                    <input type="date" 
                           name="filter_date_to" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                
                <div class="col-xl-1 col-lg-3 col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-modern btn-primary-modern w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
                
                <div class="col-xl-8 col-lg-9">
                    <label class="form-label-modern">Pencarian Global</label>
                    <input type="text" 
                           name="search" 
                           class="form-control-modern" 
                           value="<?= htmlspecialchars($filters['search']) ?>" 
                           placeholder="Cari dalam deskripsi, aksi, username, atau IP address...">
                </div>
                <div class="col-xl-4 col-lg-12">
                    <div class="d-flex gap-2 mt-4">
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-modern btn-outline-secondary flex-fill">
                            <i class="fas fa-redo me-2"></i>Reset Filter
                        </a>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>?export=csv&<?= http_build_query($filters) ?>" 
                           class="btn btn-modern btn-success-modern flex-fill">
                            <i class="fas fa-download me-2"></i>Export CSV
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button type="button" 
                                class="btn btn-modern btn-danger-modern flex-fill" 
                                data-bs-toggle="modal" 
                                data-bs-target="#cleanupModal">
                            <i class="fas fa-trash me-2"></i>Cleanup
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Info -->
    <div class="modern-card mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">
                        <i class="fas fa-history me-2 text-primary"></i>Daftar Log Aktivitas
                    </h6>
                    <small class="text-muted">
                        Menampilkan <?= number_format(min($offset + 1, $total_logs)) ?> - 
                        <?= number_format(min($offset + $limit, $total_logs)) ?> dari 
                        <?= number_format($total_logs) ?> log aktivitas
                    </small>
                </div>
                <div>
                    <button class="btn btn-modern btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statsModal">
                        <i class="fas fa-chart-bar me-2"></i>Lihat Statistik
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Table -->
    <div class="modern-card">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada log aktivitas ditemukan</h5>
                    <p class="text-muted mb-3">Coba ubah filter pencarian atau tambah periode tanggal</p>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-modern btn-primary-modern">Reset Pencarian</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th width="140">Tanggal & Waktu</th>
                                <th width="150">User</th>
                                <th width="90">Role</th>
                                <th width="120">Aksi</th>
                                <th>Deskripsi</th>
                                <th width="120">IP Address</th>
                                <th width="70" class="text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="fw-semibold text-muted">#<?= $log['id'] ?></td>
                                    <td>
                                        <div class="small">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($log['username'] ?? 'N/A') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($log['nama'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $role_colors = [
                                            'admin' => 'warning',
                                            'petugas' => 'info',
                                            'anggota' => 'success'
                                        ];
                                        $role_color = $role_colors[$log['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $role_color ?>">
                                            <?= htmlspecialchars($log['role'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $action_icons = [
                                            'LOGIN' => ['icon' => 'sign-in-alt', 'color' => 'success'],
                                            'LOGOUT' => ['icon' => 'sign-out-alt', 'color' => 'secondary'],
                                            'TAMBAH_BUKU' => ['icon' => 'plus-circle', 'color' => 'primary'],
                                            'EDIT_BUKU' => ['icon' => 'edit', 'color' => 'warning'],
                                            'HAPUS_BUKU' => ['icon' => 'trash', 'color' => 'danger'],
                                            'TAMBAH_USER' => ['icon' => 'user-plus', 'color' => 'primary'],
                                            'EDIT_USER' => ['icon' => 'user-edit', 'color' => 'warning'],
                                            'HAPUS_USER' => ['icon' => 'user-times', 'color' => 'danger'],
                                            'PEMINJAMAN' => ['icon' => 'hand-holding-heart', 'color' => 'info'],
                                            'PENGEMBALIAN' => ['icon' => 'undo', 'color' => 'success'],
                                            'EXPORT_LOG' => ['icon' => 'download', 'color' => 'primary'],
                                            'CLEANUP_LOGS' => ['icon' => 'broom', 'color' => 'danger']
                                        ];
                                        
                                        $action = $log['action'];
                                        $action_data = $action_icons[$action] ?? ['icon' => 'info-circle', 'color' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $action_data['color'] ?>">
                                            <i class="fas fa-<?= $action_data['icon'] ?> me-1"></i>
                                            <?= htmlspecialchars($action) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 400px;" 
                                             title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                                            <?= htmlspecialchars($log['description'] ?? '-') ?>
                                        </div>
                                        <?php if ($log['target_type']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-bullseye me-1"></i>
                                                <?= htmlspecialchars($log['target_type']) ?>: 
                                                <code><?= htmlspecialchars($log['target_id']) ?></code>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary view-detail-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal<?= $log['id'] ?>"
                                                title="Lihat Detail"
                                                data-log-id="<?= $log['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-light pt-3">
                        <nav aria-label="Log pagination">
                            <ul class="pagination justify-content-center mb-2">
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
                        <div class="text-center text-muted small">
                            Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_logs ?> total log)
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Modals (Ditempatkan di luar container untuk menghindari z-index issues) -->
<?php foreach ($logs as $log): ?>
    <?php
    $role_colors = [
        'admin' => 'warning',
        'petugas' => 'info',
        'anggota' => 'success'
    ];
    $role_color = $role_colors[$log['role']] ?? 'secondary';
    
    $action_icons = [
        'LOGIN' => ['icon' => 'sign-in-alt', 'color' => 'success'],
        'LOGOUT' => ['icon' => 'sign-out-alt', 'color' => 'secondary'],
        'TAMBAH_BUKU' => ['icon' => 'plus-circle', 'color' => 'primary'],
        'EDIT_BUKU' => ['icon' => 'edit', 'color' => 'warning'],
        'HAPUS_BUKU' => ['icon' => 'trash', 'color' => 'danger'],
        'TAMBAH_USER' => ['icon' => 'user-plus', 'color' => 'primary'],
        'EDIT_USER' => ['icon' => 'user-edit', 'color' => 'warning'],
        'HAPUS_USER' => ['icon' => 'user-times', 'color' => 'danger'],
        'PEMINJAMAN' => ['icon' => 'hand-holding-heart', 'color' => 'info'],
        'PENGEMBALIAN' => ['icon' => 'undo', 'color' => 'success'],
        'EXPORT_LOG' => ['icon' => 'download', 'color' => 'primary'],
        'CLEANUP_LOGS' => ['icon' => 'broom', 'color' => 'danger']
    ];
    
    $action = $log['action'];
    $action_data = $action_icons[$action] ?? ['icon' => 'info-circle', 'color' => 'secondary'];
    ?>
    <div class="modal fade detail-modal" id="detailModal<?= $log['id'] ?>" tabindex="-1" aria-labelledby="detailModalLabel<?= $log['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailModalLabel<?= $log['id'] ?>">
                        <i class="fas fa-info-circle me-2"></i>
                        Detail Log #<?= $log['id'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">ID Log:</div>
                        <div class="col-md-8"><code>#<?= $log['id'] ?></code></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Tanggal & Waktu:</div>
                        <div class="col-md-8">
                            <i class="fas fa-calendar me-1"></i>
                            <?= formatTanggalIndonesia($log['created_at']) ?>
                            <i class="fas fa-clock ms-3 me-1"></i>
                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">User ID:</div>
                        <div class="col-md-8">
                            <span class="badge bg-secondary"><?= $log['user_id'] ?? '-' ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Username:</div>
                        <div class="col-md-8"><?= htmlspecialchars($log['username'] ?? 'N/A') ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Nama Lengkap:</div>
                        <div class="col-md-8"><?= htmlspecialchars($log['nama'] ?? '-') ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Role:</div>
                        <div class="col-md-8">
                            <span class="badge bg-<?= $role_color ?>">
                                <?= htmlspecialchars($log['role'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Aksi:</div>
                        <div class="col-md-8">
                            <span class="badge bg-<?= $action_data['color'] ?>">
                                <i class="fas fa-<?= $action_data['icon'] ?> me-1"></i>
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">Deskripsi:</div>
                        <div class="col-md-8">
                            <div class="p-3 bg-light rounded border"><?= htmlspecialchars($log['description'] ?? '-') ?></div>
                        </div>
                    </div>
                    <?php if ($log['target_type']): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">Target Type:</div>
                            <div class="col-md-8"><?= htmlspecialchars($log['target_type']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-semibold">Target ID:</div>
                            <div class="col-md-8"><code class="bg-light px-2 py-1 rounded d-inline-block"><?= htmlspecialchars($log['target_id']) ?></code></div>
                        </div>
                    <?php endif; ?>
                    <div class="row mb-3">
                        <div class="col-md-4 fw-semibold">IP Address:</div>
                        <div class="col-md-8">
                            <code class="bg-light px-2 py-1 rounded d-inline-block"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 fw-semibold">User Agent:</div>
                        <div class="col-md-8">
                            <small class="text-muted d-block p-2 bg-light rounded border"><?= htmlspecialchars($log['user_agent'] ?? 'N/A') ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modern btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Statistics Modal -->
<div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="statsModalLabel">
                    <i class="fas fa-chart-bar me-2"></i>Statistik Log Aktivitas (30 Hari Terakhir)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="modern-card mb-0 h-100">
                            <div class="card-header-modern bg-transparent">
                                <h6 class="card-title-modern mb-0">
                                    <i class="fas fa-users me-2 text-primary"></i>User Paling Aktif
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-modern mb-0">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Nama</th>
                                                <th>Role</th>
                                                <th class="text-end">Aktivitas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($stats['active_users'])): ?>
                                                <?php foreach ($stats['active_users'] as $user): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                                        <td><?= htmlspecialchars($user['nama']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $user['role'] === 'admin' ? 'warning' : 
                                                                ($user['role'] === 'petugas' ? 'info' : 'success') 
                                                            ?>">
                                                                <?= $user['role'] ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="badge bg-primary"><?= number_format($user['total']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="modern-card mb-0 h-100">
                            <div class="card-header-modern bg-transparent">
                                <h6 class="card-title-modern mb-0">
                                    <i class="fas fa-bolt me-2 text-warning"></i>Aksi Paling Sering
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm table-modern mb-0">
                                        <thead>
                                            <tr>
                                                <th>Aksi</th>
                                                <th class="text-end">Jumlah</th>
                                                <th class="text-end">Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($stats['common_actions'])): ?>
                                                <?php 
                                                $total_actions = array_sum(array_column($stats['common_actions'], 'total'));
                                                foreach ($stats['common_actions'] as $action): 
                                                    $percentage = $total_actions > 0 ? ($action['total'] / $total_actions * 100) : 0;
                                                ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($action['action']) ?></strong></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-success"><?= number_format($action['total']) ?></span>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="badge bg-info"><?= number_format($percentage, 1) ?>%</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-3">Tidak ada data</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="modern-card mb-0">
                            <div class="card-header-modern bg-transparent">
                                <h6 class="card-title-modern mb-0">
                                    <i class="fas fa-chart-line me-2 text-success"></i>Aktivitas Harian
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-sm table-modern mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th class="text-end">Total Aktivitas</th>
                                                <th class="text-center">Grafik</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($stats['daily_activity'])): ?>
                                                <?php 
                                                $max_activity = max(array_column($stats['daily_activity'], 'total'));
                                                foreach ($stats['daily_activity'] as $daily): 
                                                    $bar_width = $max_activity > 0 ? ($daily['total'] / $max_activity * 100) : 0;
                                                ?>
                                                    <tr>
                                                        <td><strong><?= formatTanggal($daily['date']) ?></strong></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-primary"><?= number_format($daily['total']) ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-success" 
                                                                     style="width: <?= $bar_width ?>%" 
                                                                     title="<?= $daily['total'] ?> aktivitas">
                                                                    <span class="px-2 fw-semibold small"><?= $daily['total'] ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="3" class="text-center text-muted py-3">Tidak ada data</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-modern btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Modal (Admin Only) -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cleanupModalLabel">
                    <i class="fas fa-trash me-2"></i>Cleanup Old Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian!</strong> Tindakan ini akan menghapus log lama secara permanen.
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label-modern">Hapus log lebih dari:</label>
                        <select name="cleanup_days" class="form-control-modern" required>
                            <option value="30">30 hari</option>
                            <option value="60">60 hari</option>
                            <option value="90" selected>90 hari (Rekomendasi)</option>
                            <option value="180">180 hari</option>
                            <option value="365">1 tahun</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmCleanup" required>
                        <label class="form-check-label" for="confirmCleanup">
                            Saya memahami bahwa log yang dihapus tidak dapat dikembalikan
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modern btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Batal
                    </button>
                    <button type="submit" name="cleanup_logs" class="btn btn-modern btn-danger-modern">
                        <i class="fas fa-trash me-2"></i>Hapus Log Lama
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Mencegah geter-geter pada modal
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi semua modal dengan konfigurasi khusus
    var modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            // Mencegah scroll background
            document.body.style.overflow = 'hidden';
            document.body.style.paddingRight = '0px';
            
            // Reset modal position
            var modalDialog = this.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.style.transform = 'translateY(0)';
                modalDialog.style.opacity = '1';
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function(event) {
            // Kembalikan scroll
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    });
    
    // Smooth opening untuk detail modal
    var detailButtons = document.querySelectorAll('.view-detail-btn');
    detailButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var logId = this.getAttribute('data-log-id');
            var modal = document.getElementById('detailModal' + logId);
            if (modal) {
                var modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }
        });
    });
    
    // Tooltip untuk long text
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        if (tooltipTriggerEl.scrollWidth > tooltipTriggerEl.clientWidth) {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover'
            });
        }
    });
    
    // Loading state untuk tombol export
    var exportBtn = document.querySelector('a[href*="export=csv"]');
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            this.classList.add('loading');
            setTimeout(() => {
                this.classList.remove('loading');
            }, 2000);
        });
    }
    
    // Fix untuk tinggi modal statistik
    var statsModal = document.getElementById('statsModal');
    if (statsModal) {
        statsModal.addEventListener('shown.bs.modal', function() {
            var modalBody = this.querySelector('.modal-body');
            if (modalBody) {
                var windowHeight = window.innerHeight;
                var modalHeaderHeight = this.querySelector('.modal-header').offsetHeight;
                var modalFooterHeight = this.querySelector('.modal-footer').offsetHeight;
                var maxHeight = windowHeight - modalHeaderHeight - modalFooterHeight - 100;
                modalBody.style.maxHeight = maxHeight + 'px';
            }
        });
    }
    
    // Mencegah event bubbling
    document.addEventListener('scroll', function(e) {
        if (document.querySelector('.modal.show')) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
    
    // Keyboard navigation untuk modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var openModal = document.querySelector('.modal.show');
            if (openModal) {
                var modalInstance = bootstrap.Modal.getInstance(openModal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        }
    });
});

// Helper function untuk format tanggal
function formatTanggal(dateStr) {
    var date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}
</script>

<?php include '../../includes/footer.php'; ?>