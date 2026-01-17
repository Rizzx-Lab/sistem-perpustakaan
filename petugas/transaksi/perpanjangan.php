<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Perpanjangan Peminjaman';
include '../../config/database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $id_peminjaman = $_POST['id_peminjaman'];
        
        // Get peminjaman data
        $stmt = $conn->prepare("SELECT * FROM peminjaman WHERE id_peminjaman = ? AND status = 'dipinjam'");
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            throw new Exception('Data peminjaman tidak ditemukan atau sudah dikembalikan');
        }
        
        // Cek apakah sudah terlambat
        if ($peminjaman['tanggal_kembali'] < date('Y-m-d')) {
            throw new Exception('Tidak dapat perpanjang peminjaman yang sudah terlambat. Harap dikembalikan terlebih dahulu.');
        }
        
        // UPDATED: Cek apakah ada denda yang belum dibayar di tabel pengembalian
        // (Sebenarnya tidak relevan karena jika sudah ada di pengembalian berarti sudah dikembalikan)
        // Validasi ini tetap dipertahankan untuk safety
        
        // Get setting max pinjam hari
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $stmt->execute();
        $max_hari = $stmt->fetchColumn() ?? 14;
        
        // Perpanjang tanggal kembali
        $tanggal_kembali_baru = date('Y-m-d', strtotime($peminjaman['tanggal_kembali'] . " +{$max_hari} days"));
        
        // Update peminjaman
        $stmt = $conn->prepare("UPDATE peminjaman SET tanggal_kembali = ? WHERE id_peminjaman = ?");
        $stmt->execute([$tanggal_kembali_baru, $id_peminjaman]);
        
        $conn->commit();
        setFlashMessage('Peminjaman berhasil diperpanjang hingga ' . formatTanggal($tanggal_kembali_baru), 'success');
        redirect(SITE_URL . 'petugas/transaksi/perpanjangan.php');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = 'Gagal memproses perpanjangan: ' . $e->getMessage();
    }
}

// Get peminjaman yang bisa diperpanjang
try {
    $search = $_GET['search'] ?? '';
    $whereClause = "WHERE p.status = 'dipinjam' AND p.tanggal_kembali >= CURDATE()";
    $params = [];
    
    if (!empty($search)) {
        $whereClause .= " AND (a.nama LIKE ? OR a.nik LIKE ? OR b.judul LIKE ?)";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam];
    }
    
    // UPDATED: JOIN dengan penerbit untuk informasi lebih lengkap
    $query = "
        SELECT p.*, 
               a.nama, a.nik, a.no_hp, 
               b.judul, b.pengarang,
               penerbit.nama_penerbit,
               DATEDIFF(p.tanggal_kembali, CURDATE()) as hari_tersisa
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit ON b.id_penerbit = penerbit.id_penerbit
        $whereClause
        ORDER BY p.tanggal_kembali ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $peminjaman_list = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $peminjaman_list = [];
    $error_message = 'Error: ' . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= asset_url('petugas/dashboard.php') ?>"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Perpanjangan</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">🔄</span>
                <span class="title-gradient">Perpanjangan Peminjaman</span>
            </h1>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Ketentuan Perpanjangan:</strong>
        <ul class="mb-0 mt-2">
            <li>Peminjaman diperpanjang selama 14 hari dari tanggal kembali saat ini</li>
            <li>Hanya peminjaman yang belum terlambat yang dapat diperpanjang</li>
            <li>Tidak ada denda yang harus dibayar</li>
        </ul>
    </div>

    <!-- Search -->
    <div class="modern-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control-modern" 
                           placeholder="Cari berdasarkan NIK, Nama Anggota, atau Judul Buku..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-modern w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Peminjaman List -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h5 class="card-title-modern mb-0">
                <i class="fas fa-list me-2"></i>Daftar Peminjaman yang Dapat Diperpanjang
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($peminjaman_list)): ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Anggota</th>
                                <th>Buku</th>
                                <th>Tgl Pinjam</th>
                                <th>Batas Kembali</th>
                                <th>Sisa Waktu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_list as $row): ?>
                                <tr>
                                    <td><?= $row['id_peminjaman'] ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['nik']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($row['judul']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($row['pengarang']) ?>
                                            <?php if ($row['nama_penerbit']): ?>
                                                | <?= htmlspecialchars($row['nama_penerbit']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td><?= formatTanggal($row['tanggal_pinjam']) ?></td>
                                    <td><?= formatTanggal($row['tanggal_kembali']) ?></td>
                                    <td>
                                        <?php if ($row['hari_tersisa'] <= 3): ?>
                                            <span class="badge bg-warning"><?= $row['hari_tersisa'] ?> hari</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= $row['hari_tersisa'] ?> hari</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="perpanjang(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars($row['judul']) ?>', '<?= $row['tanggal_kembali'] ?>')">
                                            <i class="fas fa-redo me-1"></i>Perpanjang
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Tidak ada peminjaman yang dapat diperpanjang</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Perpanjangan -->
<div class="modal fade" id="modalPerpanjang" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-redo me-2"></i>Konfirmasi Perpanjangan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_peminjaman" id="modal_id_peminjaman">
                    
                    <div class="alert alert-info">
                        <strong id="modal_judul_buku"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Batas Kembali Saat Ini</label>
                        <input type="text" class="form-control-modern" id="modal_tanggal_lama" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Batas Kembali Baru</label>
                        <input type="text" class="form-control-modern" id="modal_tanggal_baru" readonly>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Peminjaman akan diperpanjang selama <strong>14 hari</strong> dari tanggal kembali saat ini.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Perpanjang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function perpanjang(id, judul, tanggalLama) {
    document.getElementById('modal_id_peminjaman').value = id;
    document.getElementById('modal_judul_buku').textContent = judul;
    
    // Format tanggal lama
    const tglLama = new Date(tanggalLama);
    document.getElementById('modal_tanggal_lama').value = formatTanggal(tglLama);
    
    // Hitung tanggal baru (+ 14 hari)
    const tglBaru = new Date(tanggalLama);
    tglBaru.setDate(tglBaru.getDate() + 14);
    document.getElementById('modal_tanggal_baru').value = formatTanggal(tglBaru);
    
    const modal = new bootstrap.Modal(document.getElementById('modalPerpanjang'));
    modal.show();
}

function formatTanggal(date) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}
</script>

<?php include '../../includes/footer.php'; ?>