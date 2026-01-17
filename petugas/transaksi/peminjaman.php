<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole('petugas');

$page_title = 'Peminjaman Buku';
include '../../config/database.php';

// Get all available books for dropdown - UPDATED dengan stok_tersedia
try {
    $stmt = $conn->query("
        SELECT b.isbn, b.judul, b.pengarang, b.tahun_terbit, b.stok_total, b.stok_tersedia,
               p.nama_penerbit,
               r.kode_rak
        FROM buku b
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN rak r ON b.id_rak = r.id_rak
        WHERE b.stok_tersedia > 0 AND b.status = 'tersedia' -- DIUBAH: gunakan stok_tersedia
        ORDER BY b.judul ASC
    ");
    $available_books = $stmt->fetchAll();
} catch (PDOException $e) {
    $available_books = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        $nik = trim($_POST['nik']);
        $isbn = trim($_POST['isbn']);
        $tanggal_pinjam = $_POST['tanggal_pinjam'];
        
        // Get setting max pinjam hari
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_pinjam_hari'");
        $stmt->execute();
        $max_hari = $stmt->fetchColumn() ?? 14;
        
        $tanggal_kembali = date('Y-m-d', strtotime($tanggal_pinjam . " +{$max_hari} days"));
        
        // Validasi anggota
        $stmt = $conn->prepare("SELECT * FROM anggota WHERE nik = ?");
        $stmt->execute([$nik]);
        $anggota = $stmt->fetch();
        
        if (!$anggota) {
            throw new Exception('NIK anggota tidak ditemukan');
        }
        
        // Cek denda belum lunas dari tabel pengembalian
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM pengembalian pg
            JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
            WHERE p.nik = ? AND pg.denda > 0
        ");
        $stmt->execute([$nik]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Anggota memiliki denda yang belum lunas. Harap lunasi terlebih dahulu.');
        }
        
        // Validasi buku
        $stmt = $conn->prepare("SELECT * FROM buku WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $buku = $stmt->fetch();
        
        if (!$buku) {
            throw new Exception('ISBN buku tidak ditemukan');
        }
        
        if ($buku['stok_tersedia'] <= 0) { // DIUBAH: gunakan stok_tersedia
            throw new Exception('Stok buku tidak tersedia');
        }
        
        // Cek maksimal peminjaman
        $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = 'max_buku_pinjam'");
        $stmt->execute();
        $max_pinjam = $stmt->fetchColumn() ?? 3;
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
        $stmt->execute([$nik]);
        $jumlah_pinjam = $stmt->fetchColumn();
        
        if ($jumlah_pinjam >= $max_pinjam) {
            throw new Exception("Anggota sudah meminjam {$max_pinjam} buku (maksimal)");
        }
        
        // INSERT peminjaman
        $stmt = $conn->prepare("
            INSERT INTO peminjaman (nik, isbn, tanggal_pinjam, tanggal_kembali, status, created_at) 
            VALUES (?, ?, ?, ?, 'dipinjam', NOW())
        ");
        $stmt->execute([$nik, $isbn, $tanggal_pinjam, $tanggal_kembali]);
        $id_peminjaman = $conn->lastInsertId();
        
        // UPDATE STOK - HANYA KURANGI stok_tersedia (FIXED)
        $stmt = $conn->prepare("UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE isbn = ? AND stok_tersedia > 0");
        $result = $stmt->execute([$isbn]);
        
        if (!$result || $stmt->rowCount() == 0) {
            throw new Exception('Gagal mengurangi stok tersedia');
        }
        
        // Update status buku jika stok_tersedia habis
        $stmt = $conn->prepare("UPDATE buku SET status = 'tidak tersedia' WHERE isbn = ? AND stok_tersedia <= 0");
        $stmt->execute([$isbn]);
        
        $conn->commit();
        
        // LOG ACTIVITY - PEMINJAMAN
        logActivity(
            'PEMINJAMAN',
            "Peminjaman buku '{$buku['judul']}' oleh {$anggota['nama']} (NIK: {$nik}). " .
            "Stok total: {$buku['stok_total']}, stok tersedia setelah: " . ($buku['stok_tersedia'] - 1) . 
            ". Kembali: " . formatTanggal($tanggal_kembali),
            'peminjaman',
            $id_peminjaman
        );
        
        setFlashMessage('Peminjaman berhasil dicatat. Batas kembali: ' . formatTanggal($tanggal_kembali), 'success');
        redirect(SITE_URL . 'petugas/transaksi/peminjaman.php');
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_message = 'Gagal memproses peminjaman: ' . $e->getMessage();
    }
}

// Get recent peminjaman - UPDATED dengan JOIN
try {
    $query = "
        SELECT p.*, a.nama, 
               b.judul, b.pengarang,
               pen.nama_penerbit
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN penerbit pen ON b.id_penerbit = pen.id_penerbit
        WHERE p.status = 'dipinjam'
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    $stmt = $conn->query($query);
    $peminjaman_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $peminjaman_list = [];
}

include '../../includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= SITE_URL ?>petugas/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Peminjaman</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 title-container">
                <span class="logo-emoji">📚</span>
                <span class="title-gradient">Peminjaman Buku</span>
            </h1>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Peminjaman -->
        <div class="col-lg-5">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Form Peminjaman
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="formPeminjaman">
                        <div class="mb-3">
                            <label class="form-label">NIK Anggota <span class="text-danger">*</span></label>
                            <input type="text" name="nik" id="nikInput" class="form-control-modern" 
                                   placeholder="Masukkan 16 digit NIK anggota" 
                                   maxlength="20" required>
                            <small class="text-muted">Contoh: 1234567890123456</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pilih Buku <span class="text-danger">*</span></label>
                            <select name="isbn" id="isbnSelect" class="form-control-modern" required>
                                <option value="">-- Pilih Buku --</option>
                                <?php foreach ($available_books as $book): ?>
                                    <option value="<?= htmlspecialchars($book['isbn']) ?>" 
                                            data-judul="<?= htmlspecialchars($book['judul']) ?>"
                                            data-pengarang="<?= htmlspecialchars($book['pengarang']) ?>"
                                            data-penerbit="<?= htmlspecialchars($book['nama_penerbit'] ?? '-') ?>"
                                            data-rak="<?= htmlspecialchars($book['kode_rak'] ?? '-') ?>"
                                            data-tahun="<?= htmlspecialchars($book['tahun_terbit']) ?>"
                                            data-stok-total="<?= htmlspecialchars($book['stok_total']) ?>"
                                            data-stok-tersedia="<?= htmlspecialchars($book['stok_tersedia']) ?>">
                                        <?= htmlspecialchars($book['judul']) ?> (<?= htmlspecialchars($book['pengarang']) ?>)
                                        - Tersedia: <?= htmlspecialchars($book['stok_tersedia']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih buku dari daftar yang tersedia</small>
                        </div>

                        <!-- Info Buku Terpilih -->
                        <div id="bookInfo" class="alert alert-secondary d-none mb-3">
                            <h6 class="mb-2"><i class="fas fa-book me-2"></i>Detail Buku</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td width="100"><strong>ISBN</strong></td>
                                    <td>: <span id="infoISBN">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Judul</strong></td>
                                    <td>: <span id="infoJudul">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Pengarang</strong></td>
                                    <td>: <span id="infoPengarang">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Penerbit</strong></td>
                                    <td>: <span id="infoPenerbit">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Rak</strong></td>
                                    <td>: <span id="infoRak">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Tahun</strong></td>
                                    <td>: <span id="infoTahun">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Stok Total</strong></td>
                                    <td>: <span id="infoStokTotal" class="badge bg-secondary">-</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Stok Tersedia</strong></td>
                                    <td>: <span id="infoStokTersedia" class="badge bg-success">-</span></td>
                                </tr>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Pinjam <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pinjam" class="form-control-modern" 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Ketentuan:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Batas kembali otomatis 14 hari dari tanggal pinjam</li>
                                    <li>Maksimal peminjaman 3 buku per anggota</li>
                                    <li>Anggota tidak boleh punya denda belum lunas</li>
                                    <li><strong>Sistem Baru:</strong> Hanya stok tersedia yang berkurang saat pinjam</li>
                                </ul>
                            </small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary-modern">
                                <i class="fas fa-save me-2"></i>Simpan Peminjaman
                            </button>
                            <a href="<?= SITE_URL ?>petugas/dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- List Peminjaman Aktif -->
        <div class="col-lg-7">
            <div class="modern-card">
                <div class="card-header-modern">
                    <h5 class="card-title-modern mb-0">
                        <i class="fas fa-list me-2"></i>Peminjaman Aktif Terbaru
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($peminjaman_list)): ?>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>Anggota</th>
                                        <th>Buku</th>
                                        <th>Tgl Pinjam</th>
                                        <th>Batas Kembali</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($peminjaman_list as $row): ?>
                                        <tr>
                                            <td>
                                                <small class="fw-semibold"><?= htmlspecialchars($row['nama']) ?></small>
                                            </td>
                                            <td>
                                                <small class="fw-semibold"><?= htmlspecialchars($row['judul']) ?></small>
                                                <br><small class="text-muted"><?= htmlspecialchars($row['pengarang']) ?></small>
                                                <?php if ($row['nama_penerbit']): ?>
                                                    <br><small class="text-muted"><i class="fas fa-building"></i> <?= htmlspecialchars($row['nama_penerbit']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= formatTanggal($row['tanggal_pinjam']) ?></small></td>
                                            <td>
                                                <small><?= formatTanggal($row['tanggal_kembali']) ?></small>
                                                <?php if ($row['tanggal_kembali'] < date('Y-m-d')): ?>
                                                    <br><span class="badge bg-danger">Terlambat</span>
                                                <?php elseif ($row['tanggal_kembali'] <= date('Y-m-d', strtotime('+3 days'))): ?>
                                                    <br><span class="badge bg-warning">Segera Jatuh Tempo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada peminjaman aktif</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript untuk menampilkan info buku ketika dipilih
document.addEventListener('DOMContentLoaded', function() {
    const isbnSelect = document.getElementById('isbnSelect');
    const bookInfo = document.getElementById('bookInfo');
    
    isbnSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (this.value) {
            // Ambil data dari attribute option yang dipilih
            const isbn = selectedOption.value;
            const judul = selectedOption.getAttribute('data-judul');
            const pengarang = selectedOption.getAttribute('data-pengarang');
            const penerbit = selectedOption.getAttribute('data-penerbit');
            const rak = selectedOption.getAttribute('data-rak');
            const tahun = selectedOption.getAttribute('data-tahun');
            const stokTotal = selectedOption.getAttribute('data-stok-total');
            const stokTersedia = selectedOption.getAttribute('data-stok-tersedia');
            
            // Tampilkan info buku
            document.getElementById('infoISBN').textContent = isbn;
            document.getElementById('infoJudul').textContent = judul;
            document.getElementById('infoPengarang').textContent = pengarang;
            document.getElementById('infoPenerbit').textContent = penerbit;
            document.getElementById('infoRak').textContent = rak;
            document.getElementById('infoTahun').textContent = tahun;
            document.getElementById('infoStokTotal').textContent = stokTotal + ' buku';
            document.getElementById('infoStokTersedia').textContent = stokTersedia + ' buku';
            
            bookInfo.classList.remove('d-none');
        } else {
            // Sembunyikan info jika tidak ada yang dipilih
            bookInfo.classList.add('d-none');
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>