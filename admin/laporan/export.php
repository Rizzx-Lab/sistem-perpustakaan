<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireRole(['admin', 'petugas']);

// Get export parameters
$type = $_GET['type'] ?? 'buku';
$format = $_GET['format'] ?? 'excel';
$filter = $_GET;

// Remove export-specific parameters
unset($filter['type']);
unset($filter['format']);

include '../../config/database.php';

// Prepare data based on type
$filename = '';
$data = [];
$headers = [];

switch ($type) {
    case 'buku':
        $filename = 'laporan_buku_' . date('Ymd_His');
        $data = getBukuData($conn, $filter);
        $headers = ['ISBN', 'Judul', 'Pengarang', 'Penerbit', 'Tahun Terbit', 'Kategori', 'Stok Total', 'Stok Tersedia', 'Status', 'Total Dipinjam', 'Sedang Dipinjam'];
        break;
        
    case 'anggota':
        $filename = 'laporan_anggota_' . date('Ymd_His');
        $data = getAnggotaData($conn, $filter);
        $headers = ['NIK', 'Nama', 'No. HP', 'Alamat', 'Tanggal Daftar', 'Total Pinjam', 'Sedang Dipinjam', 'Total Denda', 'Terakhir Pinjam', 'Status'];
        break;
        
    case 'kategori':
        $filename = 'laporan_kategori_' . date('Ymd_His');
        $data = getKategoriData($conn, $filter);
        $headers = ['ID', 'Nama Kategori', 'Deskripsi', 'Jumlah Buku', 'Total Eksemplar', 'Stok Tersedia', 'Total Pinjam', 'Sedang Dipinjam', 'Rata-rata Tahun', 'Kategori Terbanyak'];
        break;
        
    case 'penerbit':
        $filename = 'laporan_penerbit_' . date('Ymd_His');
        $data = getPenerbitData($conn, $filter);
        $headers = ['ID', 'Nama Penerbit', 'Alamat', 'Telepon', 'Email', 'Jumlah Buku', 'Total Eksemplar', 'Stok Tersedia', 'Total Pinjam', 'Jumlah Kategori', 'Rata-rata Tahun'];
        break;
        
    case 'denda':
        $filename = 'laporan_denda_' . date('Ymd_His');
        $data = getDendaData($conn, $filter);
        $headers = ['ID', 'Tanggal', 'Nama Anggota', 'NIK', 'Judul Buku', 'ISBN', 'Hari Telat', 'Jumlah Denda', 'Jenis Denda', 'Status', 'Keterangan'];
        break;
        
    case 'peminjaman':
        $filename = 'laporan_peminjaman_' . date('Ymd_His');
        $data = getPeminjamanData($conn, $filter);
        $headers = ['ID', 'Tanggal Pinjam', 'Nama Anggota', 'NIK', 'Judul Buku', 'ISBN', 'Batas Kembali', 'Tanggal Kembali', 'Status', 'Denda', 'Keterangan'];
        break;
        
    default:
        die('Tipe laporan tidak valid');
}

// Export based on format
if ($format === 'excel') {
    exportExcel($filename, $headers, $data);
} elseif ($format === 'csv') {
    exportCSV($filename, $headers, $data);
} elseif ($format === 'pdf') {
    exportPDF($filename, $headers, $data);
} else {
    die('Format export tidak valid');
}

// Data retrieval functions
function getBukuData($conn, $filter) {
    $where = [];
    $params = [];
    
    if (!empty($filter['search'])) {
        $where[] = "(b.judul LIKE ? OR b.pengarang LIKE ? OR b.isbn LIKE ?)";
        $search_term = "%{$filter['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter['kategori'])) {
        $where[] = "bk.id_kategori = ?";
        $params[] = $filter['kategori'];
    }
    
    if (!empty($filter['penerbit'])) {
        $where[] = "b.id_penerbit = ?";
        $params[] = $filter['penerbit'];
    }
    
    if (!empty($filter['tahun'])) {
        $where[] = "b.tahun_terbit = ?";
        $params[] = $filter['tahun'];
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            b.isbn,
            b.judul,
            b.pengarang,
            p.nama_penerbit,
            b.tahun_terbit,
            GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') as kategori,
            b.stok_total,
            b.stok_tersedia,
            CASE WHEN b.stok_tersedia > 0 THEN 'Tersedia' ELSE 'Habis' END as status,
            COUNT(DISTINCT pm.id_peminjaman) as total_pinjam,
            SUM(CASE WHEN pm.status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam
        FROM buku b
        LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
        LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
        LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
        LEFT JOIN peminjaman pm ON b.isbn = pm.isbn
        $where_clause
        GROUP BY b.isbn
        ORDER BY b.judul
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted = [];
    foreach ($result as $row) {
        $formatted[] = [
            $row['isbn'],
            $row['judul'],
            $row['pengarang'],
            $row['nama_penerbit'] ?? '-',
            $row['tahun_terbit'],
            $row['kategori'] ?? '-',
            $row['stok_total'],
            $row['stok_tersedia'],
            $row['status'],
            $row['total_pinjam'],
            $row['sedang_dipinjam']
        ];
    }
    
    return $formatted;
}

function getAnggotaData($conn, $filter) {
    $where = [];
    $params = [];
    
    if (!empty($filter['search'])) {
        $where[] = "(a.nama LIKE ? OR a.nik LIKE ? OR a.no_hp LIKE ?)";
        $search_term = "%{$filter['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter['tgl_daftar_dari']) && !empty($filter['tgl_daftar_sampai'])) {
        $where[] = "DATE(a.created_at) BETWEEN ? AND ?";
        $params[] = $filter['tgl_daftar_dari'];
        $params[] = $filter['tgl_daftar_sampai'];
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            a.nik,
            a.nama,
            a.no_hp,
            a.alamat,
            DATE(a.created_at) as tanggal_daftar,
            COUNT(DISTINCT p.id_peminjaman) as total_pinjam,
            SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam,
            COALESCE(SUM(pg.denda), 0) as total_denda,
            MAX(p.tanggal_pinjam) as terakhir_pinjam,
            CASE 
                WHEN COUNT(DISTINCT p.id_peminjaman) = 0 THEN 'Tidak Aktif'
                WHEN COALESCE(SUM(pg.denda), 0) > 0 THEN 'Punya Denda'
                ELSE 'Aktif'
            END as status
        FROM anggota a
        LEFT JOIN peminjaman p ON a.nik = p.nik
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        $where_clause
        GROUP BY a.nik
        ORDER BY a.nama
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($result as $row) {
        $formatted[] = [
            $row['nik'],
            $row['nama'],
            $row['no_hp'] ?? '-',
            $row['alamat'],
            $row['tanggal_daftar'],
            $row['total_pinjam'],
            $row['sedang_dipinjam'],
            $row['total_denda'],
            $row['terakhir_pinjam'] ?? '-',
            $row['status']
        ];
    }
    
    return $formatted;
}

function getKategoriData($conn, $filter) {
    $where = [];
    $params = [];
    
    if (!empty($filter['search'])) {
        $where[] = "(k.nama_kategori LIKE ? OR k.deskripsi LIKE ?)";
        $search_term = "%{$filter['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            k.id_kategori,
            k.nama_kategori,
            k.deskripsi,
            COUNT(DISTINCT bk.isbn) as jumlah_buku,
            COALESCE(SUM(b.stok_total), 0) as total_eksemplar,
            COALESCE(SUM(b.stok_tersedia), 0) as stok_tersedia,
            COALESCE(COUNT(DISTINCT p.id_peminjaman), 0) as total_pinjam,
            COALESCE(COUNT(DISTINCT CASE WHEN p.status = 'dipinjam' THEN p.id_peminjaman END), 0) as sedang_dipinjam,
            COALESCE(ROUND(AVG(b.tahun_terbit)), 0) as rata_rata_tahun,
            GROUP_CONCAT(DISTINCT b2.judul ORDER BY b2.judul LIMIT 3 SEPARATOR ', ') as buku_terbanyak
        FROM kategori k
        LEFT JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
        LEFT JOIN buku b ON bk.isbn = b.isbn
        LEFT JOIN peminjaman p ON b.isbn = p.isbn
        LEFT JOIN buku b2 ON bk.isbn = b2.isbn
        $where_clause
        GROUP BY k.id_kategori
        ORDER BY k.nama_kategori
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($result as $row) {
        $formatted[] = [
            $row['id_kategori'],
            $row['nama_kategori'],
            $row['deskripsi'] ?? '-',
            $row['jumlah_buku'],
            $row['total_eksemplar'],
            $row['stok_tersedia'],
            $row['total_pinjam'],
            $row['sedang_dipinjam'],
            $row['rata_rata_tahun'] > 0 ? $row['rata_rata_tahun'] : '-',
            $row['buku_terbanyak'] ?? '-'
        ];
    }
    
    return $formatted;
}

function getPenerbitData($conn, $filter) {
    $where = [];
    $params = [];
    
    if (!empty($filter['search'])) {
        $where[] = "(pn.nama_penerbit LIKE ? OR pn.alamat LIKE ? OR pn.email LIKE ?)";
        $search_term = "%{$filter['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            pn.id_penerbit,
            pn.nama_penerbit,
            pn.alamat,
            pn.telepon,
            pn.email,
            COUNT(DISTINCT b.isbn) as jumlah_buku,
            COALESCE(SUM(b.stok_total), 0) as total_eksemplar,
            COALESCE(SUM(b.stok_tersedia), 0) as stok_tersedia,
            COALESCE(COUNT(DISTINCT p.id_peminjaman), 0) as total_pinjam,
            COALESCE(COUNT(DISTINCT bk.id_kategori), 0) as jumlah_kategori,
            COALESCE(ROUND(AVG(b.tahun_terbit)), 0) as rata_rata_tahun
        FROM penerbit pn
        LEFT JOIN buku b ON pn.id_penerbit = b.id_penerbit
        LEFT JOIN peminjaman p ON b.isbn = p.isbn
        LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
        $where_clause
        GROUP BY pn.id_penerbit
        ORDER BY pn.nama_penerbit
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($result as $row) {
        $formatted[] = [
            $row['id_penerbit'],
            $row['nama_penerbit'],
            $row['alamat'] ?? '-',
            $row['telepon'] ?? '-',
            $row['email'] ?? '-',
            $row['jumlah_buku'],
            $row['total_eksemplar'],
            $row['stok_tersedia'],
            $row['total_pinjam'],
            $row['jumlah_kategori'],
            $row['rata_rata_tahun'] > 0 ? $row['rata_rata_tahun'] : '-'
        ];
    }
    
    return $formatted;
}

function getDendaData($conn, $filter) {
    // Get denda per hari
    $denda_per_hari = $conn->query("SELECT setting_value FROM pengaturan WHERE setting_key = 'denda_per_hari'")->fetchColumn() ?? 1000;
    
    // Get paid fines
    $where = [];
    $params = [];
    
    if (!empty($filter['tgl_dari']) && !empty($filter['tgl_sampai'])) {
        $where[] = "DATE(pg.created_at) BETWEEN ? AND ?";
        $params[] = $filter['tgl_dari'];
        $params[] = $filter['tgl_sampai'];
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query_paid = "
        SELECT 
            pg.id_pengembalian,
            DATE(pg.created_at) as tanggal,
            a.nama,
            a.nik,
            b.judul,
            b.isbn,
            DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali) as hari_terlambat,
            pg.denda,
            CASE 
                WHEN pg.kondisi_buku = 'baik' THEN 'Keterlambatan'
                WHEN pg.kondisi_buku = 'rusak_ringan' THEN 'Rusak Ringan'
                WHEN pg.kondisi_buku = 'rusak_berat' THEN 'Rusak Berat'
                ELSE 'Lainnya'
            END as jenis_denda,
            'Lunas' as status,
            CONCAT('Denda ', CASE WHEN pg.kondisi_buku = 'baik' THEN 'keterlambatan' ELSE 'kerusakan buku' END) as keterangan
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        WHERE pg.denda > 0
        $where_clause
        ORDER BY pg.created_at DESC
    ";
    
    $stmt_paid = $conn->prepare($query_paid);
    $stmt_paid->execute($params);
    $paid_fines = $stmt_paid->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unpaid fines
    $query_unpaid = "
        SELECT 
            p.id_peminjaman,
            CURDATE() as tanggal,
            a.nama,
            a.nik,
            b.judul,
            b.isbn,
            DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat,
            DATEDIFF(CURDATE(), p.tanggal_kembali) * {$denda_per_hari} as denda,
            'Keterlambatan' as jenis_denda,
            'Belum Lunas' as status,
            CONCAT('Tertunggak - akan bertambah ', {$denda_per_hari}, '/hari') as keterangan
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        WHERE p.status = 'dipinjam'
        AND p.tanggal_kembali < CURDATE()
        AND NOT EXISTS (
            SELECT 1 FROM pengembalian pg 
            WHERE pg.id_peminjaman = p.id_peminjaman
        )
        ORDER BY p.tanggal_kembali ASC
    ";
    
    $unpaid_fines = $conn->query($query_unpaid)->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine and format
    $all_fines = array_merge($paid_fines, $unpaid_fines);
    
    $formatted = [];
    foreach ($all_fines as $fine) {
        $formatted[] = [
            $fine['id_pengembalian'] ?? $fine['id_peminjaman'],
            $fine['tanggal'],
            $fine['nama'],
            $fine['nik'],
            $fine['judul'],
            $fine['isbn'],
            $fine['hari_terlambat'] > 0 ? $fine['hari_terlambat'] : 0,
            $fine['denda'],
            $fine['jenis_denda'],
            $fine['status'],
            $fine['keterangan']
        ];
    }
    
    return $formatted;
}

function getPeminjamanData($conn, $filter) {
    $where = [];
    $params = [];
    
    if (!empty($filter['tgl_dari']) && !empty($filter['tgl_sampai'])) {
        $where[] = "p.tanggal_pinjam BETWEEN ? AND ?";
        $params[] = $filter['tgl_dari'];
        $params[] = $filter['tgl_sampai'];
    }
    
    if (!empty($filter['status'])) {
        $where[] = "p.status = ?";
        $params[] = $filter['status'];
    }
    
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $query = "
        SELECT 
            p.id_peminjaman,
            p.tanggal_pinjam,
            a.nama,
            a.nik,
            b.judul,
            b.isbn,
            p.tanggal_kembali,
            pg.tanggal_pengembalian_aktual,
            p.status,
            COALESCE(pg.denda, 0) as denda,
            CASE 
                WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                THEN CONCAT('Terlambat ', DATEDIFF(CURDATE(), p.tanggal_kembali), ' hari')
                WHEN pg.kondisi_buku = 'rusak_ringan' THEN 'Buku rusak ringan'
                WHEN pg.kondisi_buku = 'rusak_berat' THEN 'Buku rusak berat'
                ELSE '-'
            END as keterangan
        FROM peminjaman p
        JOIN anggota a ON p.nik = a.nik
        JOIN buku b ON p.isbn = b.isbn
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        $where_clause
        ORDER BY p.tanggal_pinjam DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($result as $row) {
        $formatted[] = [
            $row['id_peminjaman'],
            $row['tanggal_pinjam'],
            $row['nama'],
            $row['nik'],
            $row['judul'],
            $row['isbn'],
            $row['tanggal_kembali'],
            $row['tanggal_pengembalian_aktual'] ?? '-',
            $row['status'],
            $row['denda'],
            $row['keterangan']
        ];
    }
    
    return $formatted;
}

// Export functions
function exportExcel($filename, $headers, $data) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($output, $headers, "\t");
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row, "\t");
    }
    
    fclose($output);
    exit;
}

function exportCSV($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportPDF($filename, $headers, $data) {
    // Simple PDF generation using HTML
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
    
    $html = '<html><head><meta charset="UTF-8"><title>' . $filename . '</title>';
    $html .= '<style>';
    $html .= 'body { font-family: Arial, sans-serif; font-size: 10px; }';
    $html .= 'table { width: 100%; border-collapse: collapse; }';
    $html .= 'th { background-color: #f2f2f2; text-align: left; padding: 6px; border: 1px solid #ddd; }';
    $html .= 'td { padding: 4px; border: 1px solid #ddd; }';
    $html .= 'h1 { font-size: 16px; text-align: center; margin-bottom: 20px; }';
    $html .= '.header { margin-bottom: 20px; }';
    $html .= '.footer { margin-top: 30px; font-size: 8px; text-align: center; }';
    $html .= '</style></head><body>';
    
    $html .= '<div class="header">';
    $html .= '<h1>' . strtoupper(str_replace('_', ' ', $filename)) . '</h1>';
    $html .= '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<p>Total Data: ' . count($data) . '</p>';
    $html .= '</div>';
    
    $html .= '<table>';
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead>';
    
    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    
    $html .= '<div class="footer">';
    $html .= '<p>Perpustakaan Nusantara - Sistem Informasi Perpustakaan</p>';
    $html .= '<p>Dicetak secara otomatis oleh sistem</p>';
    $html .= '</div>';
    
    $html .= '</body></html>';
    
    // Use DOMPDF or TCPDF if available, otherwise output HTML
    // Note: For production, install and use a proper PDF library like TCPDF or DOMPDF
    echo $html;
    exit;
}