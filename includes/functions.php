<?php
// ==========================================
// KATEGORI 1: FORMAT & DISPLAY FUNCTIONS
// ==========================================

if (!function_exists('formatRupiah')) {
    /**
     * Format angka menjadi format Rupiah
     */
    function formatRupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('formatTanggal')) {
    /**
     * Format tanggal dengan format tertentu
     */
    function formatTanggal($date, $format = 'd/m/Y') {
        if (empty($date) || $date === '0000-00-00') {
            return '-';
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatTanggalIndonesia')) {
    /**
     * Format tanggal ke format Indonesia lengkap (Hari, Tanggal Bulan Tahun)
     */
    function formatTanggalIndonesia($date) {
        if (empty($date) || $date === '0000-00-00') {
            return '-';
        }
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $hari = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        $timestamp = strtotime($date);
        $day = date('j', $timestamp);
        $month = $bulan[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        $dayName = $hari[date('l', $timestamp)];
        
        return "{$dayName}, {$day} {$month} {$year}";
    }
}

if (!function_exists('formatBytes')) {
    /**
     * Format bytes ke format yang lebih mudah dibaca (KB, MB, GB)
     */
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('truncateText')) {
    /**
     * Potong teks dengan batas karakter tertentu
     */
    function truncateText($text, $limit = 100, $suffix = '...') {
        if (strlen($text) <= $limit) {
            return $text;
        }
        return substr($text, 0, $limit) . $suffix;
    }
}

// ==========================================
// KATEGORI 2: STOK MANAGEMENT FUNCTIONS
// ==========================================

if (!function_exists('getAvailableStock')) {
    /**
     * Mendapatkan stok tersedia buku berdasarkan ISBN
     * Menggunakan sistem stok_tersedia baru
     */
    function getAvailableStock($isbn) {
        global $conn;
        try {
            $stmt = $conn->prepare("SELECT stok_tersedia FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $result = $stmt->fetch();
            return $result ? (int)$result['stok_tersedia'] : 0;
        } catch (PDOException $e) {
            error_log("Error getAvailableStock: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('getTotalStock')) {
    /**
     * Mendapatkan stok total buku berdasarkan ISBN
     */
    function getTotalStock($isbn) {
        global $conn;
        try {
            $stmt = $conn->prepare("SELECT stok_total FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $result = $stmt->fetch();
            return $result ? (int)$result['stok_total'] : 0;
        } catch (PDOException $e) {
            error_log("Error getTotalStock: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('updateStokTersedia')) {
    /**
     * Memperbarui stok tersedia buku
     */
    function updateStokTersedia($isbn, $stok_tersedia) {
        global $conn;
        try {
            $stmt = $conn->prepare("UPDATE buku SET stok_tersedia = ? WHERE isbn = ?");
            return $stmt->execute([$stok_tersedia, $isbn]);
        } catch (PDOException $e) {
            error_log("Error updateStokTersedia: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('updateBothStocks')) {
    /**
     * Memperbarui stok total dan stok tersedia
     */
    function updateBothStocks($isbn, $stok_total, $stok_tersedia) {
        global $conn;
        try {
            // Validasi: stok_tersedia tidak boleh lebih besar dari stok_total
            if ($stok_tersedia > $stok_total) {
                $stok_tersedia = $stok_total;
            }
            
            // Validasi: stok_tersedia tidak boleh negatif
            if ($stok_tersedia < 0) {
                $stok_tersedia = 0;
            }
            
            $stmt = $conn->prepare("UPDATE buku SET stok_total = ?, stok_tersedia = ? WHERE isbn = ?");
            return $stmt->execute([$stok_total, $stok_tersedia, $isbn]);
        } catch (PDOException $e) {
            error_log("Error updateBothStocks: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('decreaseStokTersedia')) {
    /**
     * Mengurangi stok tersedia saat peminjaman
     */
    function decreaseStokTersedia($isbn, $quantity = 1) {
        global $conn;
        try {
            $stmt = $conn->prepare("
                UPDATE buku 
                SET stok_tersedia = stok_tersedia - ? 
                WHERE isbn = ? AND stok_tersedia >= ?
            ");
            $result = $stmt->execute([$quantity, $isbn, $quantity]);
            
            // Update status jika stok tersedia habis
            if ($result) {
                $stmt = $conn->prepare("
                    UPDATE buku 
                    SET status = CASE 
                        WHEN stok_tersedia <= 0 THEN 'tidak tersedia' 
                        ELSE 'tersedia' 
                    END 
                    WHERE isbn = ?
                ");
                $stmt->execute([$isbn]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error decreaseStokTersedia: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('increaseStokTersedia')) {
    /**
     * Menambah stok tersedia saat pengembalian
     */
    function increaseStokTersedia($isbn, $quantity = 1) {
        global $conn;
        try {
            // Cek stok total untuk batasi tidak melebihi stok total
            $stmt = $conn->prepare("SELECT stok_total FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $stok_total = $stmt->fetchColumn();
            
            $stmt = $conn->prepare("
                UPDATE buku 
                SET stok_tersedia = LEAST(stok_tersedia + ?, ?),
                    status = 'tersedia'
                WHERE isbn = ?
            ");
            $result = $stmt->execute([$quantity, $stok_total, $isbn]);
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error increaseStokTersedia: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getBorrowedCount')) {
    /**
     * Mendapatkan jumlah buku yang sedang dipinjam berdasarkan ISBN
     */
    function getBorrowedCount($isbn) {
        global $conn;
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM peminjaman 
                WHERE isbn = ? AND status = 'dipinjam'
            ");
            $stmt->execute([$isbn]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getBorrowedCount: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('getAvailableBooksForDropdown')) {
    /**
     * Mendapatkan buku yang tersedia untuk dropdown (stok_tersedia > 0)
     */
    function getAvailableBooksForDropdown() {
        global $conn;
        
        try {
            $sql = "
                SELECT b.isbn, b.judul, b.pengarang, b.tahun_terbit, 
                       b.stok_total, b.stok_tersedia,
                       p.nama_penerbit, r.kode_rak
                FROM buku b
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
                LEFT JOIN rak r ON b.id_rak = r.id_rak
                WHERE b.stok_tersedia > 0 AND b.status = 'tersedia'
                ORDER BY b.judul ASC
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getAvailableBooksForDropdown: " . $e->getMessage());
            return [];
        }
    }
}

// ==========================================
// KATEGORI 3: CALCULATION FUNCTIONS
// ==========================================

if (!function_exists('hitungHariTerlambat')) {
    /**
     * Hitung hari keterlambatan pengembalian
     */
    function hitungHariTerlambat($tanggal_kembali, $tanggal_pengembalian_aktual = null) {
        if (!$tanggal_pengembalian_aktual) {
            $tanggal_pengembalian_aktual = date('Y-m-d');
        }
        
        $selisih = strtotime($tanggal_pengembalian_aktual) - strtotime($tanggal_kembali);
        $hari = floor($selisih / (60 * 60 * 24));
        
        return $hari > 0 ? $hari : 0;
    }
}

if (!function_exists('hitungDenda')) {
    /**
     * Hitung denda berdasarkan hari keterlambatan
     */
    function hitungDenda($hari_terlambat, $denda_per_hari = 1000) {
        return $hari_terlambat * $denda_per_hari;
    }
}

if (!function_exists('calculateAge')) {
    /**
     * Hitung usia dari tanggal lahir
     */
    function calculateAge($birthdate) {
        $today = new DateTime();
        $birth = new DateTime($birthdate);
        return $today->diff($birth)->y;
    }
}

if (!function_exists('calculateReturnFine')) {
    /**
     * Hitung denda pengembalian lengkap (keterlambatan + kondisi buku)
     */
    function calculateReturnFine($tanggal_kembali, $tanggal_pengembalian_aktual = null, $kondisi_buku = 'baik', $denda_per_hari = 1000) {
        if (!$tanggal_pengembalian_aktual) {
            $tanggal_pengembalian_aktual = date('Y-m-d');
        }
        
        // Calculate late days
        $hari_terlambat = hitungHariTerlambat($tanggal_kembali, $tanggal_pengembalian_aktual);
        $denda_keterlambatan = $hari_terlambat * $denda_per_hari;
        
        // Calculate condition fine
        $denda_kondisi = 0;
        if ($kondisi_buku === 'rusak_ringan') {
            $denda_kondisi = 5000;
        } elseif ($kondisi_buku === 'rusak_berat') {
            $denda_kondisi = 25000;
        }
        
        $total_denda = $denda_keterlambatan + $denda_kondisi;
        
        return [
            'hari_terlambat' => $hari_terlambat,
            'denda_keterlambatan' => $denda_keterlambatan,
            'denda_kondisi' => $denda_kondisi,
            'total_denda' => $total_denda
        ];
    }
}

// ==========================================
// KATEGORI 4: STATUS & AVAILABILITY FUNCTIONS
// ==========================================

if (!function_exists('getStatusBuku')) {
    /**
     * Mendapatkan status buku berdasarkan stok tersedia
     */
    function getStatusBuku($input) {
        // Jika input adalah ISBN, ambil stok tersedia dari database
        if (is_string($input) && strlen($input) > 10) {
            $stok_tersedia = getAvailableStock($input);
        } else {
            // Jika input adalah stok langsung
            $stok_tersedia = (int)$input;
        }
        
        if ($stok_tersedia > 0) {
            return [
                'status' => 'tersedia',
                'badge_class' => 'bg-success',
                'text' => 'TERSEDIA',
                'icon' => 'fas fa-check-circle',
                'color' => 'text-success'
            ];
        } else {
            return [
                'status' => 'habis',
                'badge_class' => 'bg-danger',
                'text' => 'STOK HABIS',
                'icon' => 'fas fa-times-circle',
                'color' => 'text-danger'
            ];
        }
    }
}

if (!function_exists('getStatusPeminjaman')) {
    /**
     * Mendapatkan status peminjaman dengan detail
     */
    function getStatusPeminjaman($status, $tanggal_kembali, $id_peminjaman = null) {
        global $conn;
        $today = date('Y-m-d');
        
        if (strtolower($status) === 'dikembalikan') {
            // Cek dari tabel pengembalian
            if ($id_peminjaman) {
                try {
                    $stmt = $conn->prepare("SELECT tanggal_pengembalian_aktual, denda FROM pengembalian WHERE id_peminjaman = ?");
                    $stmt->execute([$id_peminjaman]);
                    $pengembalian = $stmt->fetch();
                    
                    if ($pengembalian && $pengembalian['denda'] > 0) {
                        return [
                            'status' => 'dikembalikan_terlambat',
                            'badge_class' => 'bg-warning',
                            'text' => 'DIKEMBALIKAN (TERLAMBAT)',
                            'icon' => 'fas fa-exclamation-triangle'
                        ];
                    }
                } catch (Exception $e) {
                    // Silent fail
                }
            }
            
            return [
                'status' => 'dikembalikan',
                'badge_class' => 'bg-success',
                'text' => 'DIKEMBALIKAN',
                'icon' => 'fas fa-check-circle'
            ];
        } elseif (strtolower($status) === 'dipinjam') {
            if ($today > $tanggal_kembali) {
                return [
                    'status' => 'terlambat',
                    'badge_class' => 'bg-danger',
                    'text' => 'TERLAMBAT',
                    'icon' => 'fas fa-exclamation-triangle'
                ];
            } else {
                return [
                    'status' => 'dipinjam',
                    'badge_class' => 'bg-primary',
                    'text' => 'DIPINJAM',
                    'icon' => 'fas fa-clock'
                ];
            }
        }
        
        return [
            'status' => 'unknown',
            'badge_class' => 'bg-secondary',
            'text' => 'UNKNOWN',
            'icon' => 'fas fa-question-circle'
        ];
    }
}

if (!function_exists('canBorrowMore')) {
    /**
     * Cek apakah anggota bisa meminjam lebih banyak buku
     */
    function canBorrowMore($nik, $max_books = 3) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
            $stmt->execute([$nik]);
            $current_borrowed = $stmt->fetchColumn();
            
            return $current_borrowed < $max_books;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('isLibraryOpen')) {
    /**
     * Cek apakah perpustakaan sedang buka
     */
    function isLibraryOpen() {
        $current_hour = (int)date('H');
        $jam_buka = (int)substr(getSetting('jam_buka', '08:00'), 0, 2);
        $jam_tutup = (int)substr(getSetting('jam_tutup', '16:00'), 0, 2);
        
        return $current_hour >= $jam_buka && $current_hour < $jam_tutup;
    }
}

if (!function_exists('getLibraryStatusMessage')) {
    /**
     * Dapatkan pesan status perpustakaan
     */
    function getLibraryStatusMessage() {
        global $conn;
        
        try {
            // Ambil jam operasional dari database
            $jam_buka = getSetting('jam_buka', '08:00');
            $jam_tutup = getSetting('jam_tutup', '16:00');
            
            $current_time = date('H:i');
            $current_hour = (int)date('H');
            $jam_buka_hour = (int)substr($jam_buka, 0, 2);
            $jam_tutup_hour = (int)substr($jam_tutup, 0, 2);
            
            if ($current_hour >= $jam_buka_hour && $current_hour < $jam_tutup_hour) {
                return [
                    'status' => 'open',
                    'message' => 'Buka',
                    'class' => 'text-success',
                    'icon' => 'fa-door-open'
                ];
            } else {
                return [
                    'status' => 'closed',
                    'message' => 'Tutup',
                    'class' => 'text-danger',
                    'icon' => 'fa-door-closed'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unknown',
                'message' => 'Status tidak diketahui',
                'class' => 'text-muted',
                'icon' => 'fa-question-circle'
            ];
        }
    }
}

if (!function_exists('getMemberBorrowingStatus')) {
    /**
     * Dapatkan status peminjaman anggota
     */
    function getMemberBorrowingStatus($nik) {
        global $conn;
        
        try {
            // Get current borrowed books
            $stmt = $conn->prepare("
                SELECT b.judul, p.tanggal_pinjam, p.tanggal_kembali, p.id_peminjaman,
                       CASE WHEN p.tanggal_kembali < CURDATE() THEN 'overdue'
                            ELSE 'active' END as status
                FROM peminjaman p 
                JOIN buku b ON p.isbn = b.isbn 
                WHERE p.nik = ? AND p.status = 'dipinjam'
                ORDER BY p.tanggal_kembali ASC
            ");
            $stmt->execute([$nik]);
            $borrowed_books = $stmt->fetchAll();
            
            // Get overdue count
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam' AND tanggal_kembali < CURDATE()");
            $stmt->execute([$nik]);
            $overdue_count = $stmt->fetchColumn();
            
            // Get total fines - dari tabel pengembalian
            $stmt = $conn->prepare("
                SELECT SUM(pg.denda) 
                FROM pengembalian pg
                JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                WHERE p.nik = ? AND pg.denda > 0
            ");
            $stmt->execute([$nik]);
            $total_fines = $stmt->fetchColumn() ?: 0;
            
            return [
                'borrowed_books' => $borrowed_books,
                'total_borrowed' => count($borrowed_books),
                'overdue_count' => $overdue_count,
                'total_fines' => $total_fines,
                'can_borrow' => canBorrowMore($nik) && $overdue_count == 0
            ];
            
        } catch (Exception $e) {
            return [
                'borrowed_books' => [],
                'total_borrowed' => 0,
                'overdue_count' => 0,
                'total_fines' => 0,
                'can_borrow' => false
            ];
        }
    }
}

if (!function_exists('checkMemberHasFines')) {
    /**
     * Cek apakah anggota memiliki denda yang belum dibayar
     */
    function checkMemberHasFines($nik) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT SUM(pg.denda) as total_denda
                FROM pengembalian pg
                JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                WHERE p.nik = ? AND pg.denda > 0
            ");
            $stmt->execute([$nik]);
            $result = $stmt->fetch();
            
            return $result && $result['total_denda'] > 0;
        } catch (Exception $e) {
            error_log("Error checkMemberHasFines: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('validateLoan')) {
    /**
     * Validasi apakah peminjaman bisa dilakukan
     */
    function validateLoan($nik, $isbn) {
        global $conn;
        
        try {
            $errors = [];
            
            // Check member exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM anggota WHERE nik = ?");
            $stmt->execute([$nik]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = "Anggota tidak ditemukan";
            }
            
            // Check book exists
            $stmt = $conn->prepare("SELECT stok_tersedia FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $book = $stmt->fetch();
            if (!$book) {
                $errors[] = "Buku tidak ditemukan";
            } elseif ($book['stok_tersedia'] <= 0) {
                $errors[] = "Stok buku tidak tersedia";
            }
            
            // Check if member already borrowed this book
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM peminjaman 
                WHERE nik = ? AND isbn = ? AND status = 'dipinjam'
            ");
            $stmt->execute([$nik, $isbn]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Anggota sudah meminjam buku ini";
            }
            
            // Check member has unpaid fines
            $stmt = $conn->prepare("
                SELECT SUM(pg.denda) as total_denda
                FROM pengembalian pg
                JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                WHERE p.nik = ? AND pg.denda > 0
            ");
            $stmt->execute([$nik]);
            $result = $stmt->fetch();
            if ($result && $result['total_denda'] > 0) {
                $errors[] = "Anggota memiliki denda yang belum lunas";
            }
            
            // Check max books per member
            $max_books = (int) getSetting('max_buku_pinjam', 3);
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
            $stmt->execute([$nik]);
            $current_borrowed = $stmt->fetchColumn();
            if ($current_borrowed >= $max_books) {
                $errors[] = "Anggota sudah mencapai batas maksimal peminjaman ($max_books buku)";
            }
            
            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'book_stock' => $book ? $book['stok_tersedia'] : 0
            ];
        } catch (Exception $e) {
            error_log("Error validateLoan: " . $e->getMessage());
            return [
                'valid' => false,
                'errors' => ["Error sistem: " . $e->getMessage()],
                'book_stock' => 0
            ];
        }
    }
}

// ==========================================
// KATEGORI 5: SEARCH & DATA RETRIEVAL FUNCTIONS
// ==========================================

if (!function_exists('searchBooksWithStock')) {
    /**
     * Mencari buku dengan informasi stok lengkap
     * Digunakan di halaman katalog
     */
    function searchBooksWithStock($query = '', $filters = []) {
        global $conn;
        
        try {
            $where_conditions = [];
            $params = [];
            
            // Search query
            if (!empty($query)) {
                $where_conditions[] = "(b.judul LIKE ? OR b.pengarang LIKE ? OR p.nama_penerbit LIKE ? OR b.isbn LIKE ?)";
                $search_param = "%$query%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }
            
            // Status filter - menggunakan stok_tersedia
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'tersedia') {
                    $where_conditions[] = "b.stok_tersedia > 0";
                } elseif ($filters['status'] === 'habis') {
                    $where_conditions[] = "b.stok_tersedia <= 0";
                }
            }
            
            // Year filter
            if (!empty($filters['tahun'])) {
                $where_conditions[] = "b.tahun_terbit = ?";
                $params[] = $filters['tahun'];
            }
            
            // Kategori filter
            if (!empty($filters['kategori'])) {
                $where_conditions[] = "k.id_kategori = ?";
                $params[] = $filters['kategori'];
            }
            
            // Penerbit filter
            if (!empty($filters['penerbit'])) {
                $where_conditions[] = "b.id_penerbit = ?";
                $params[] = $filters['penerbit'];
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $sql = "
                SELECT b.*, 
                       p.nama_penerbit, 
                       r.kode_rak, r.lokasi as lokasi_rak,
                       GROUP_CONCAT(k.nama_kategori SEPARATOR ', ') as kategori_list
                FROM buku b
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
                LEFT JOIN rak r ON b.id_rak = r.id_rak
                LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
                LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
                $where_clause
                GROUP BY b.isbn
                ORDER BY b.judul ASC
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Search books with stock error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('searchBooksAdvanced')) {
    /**
     * Pencarian buku lanjutan dengan multiple criteria
     */
    function searchBooksAdvanced($criteria = [], $limit = 50, $offset = 0) {
        global $conn;
        
        try {
            $where_conditions = [];
            $params = [];
            
            // ISBN search
            if (!empty($criteria['isbn'])) {
                $where_conditions[] = "b.isbn LIKE ?";
                $params[] = '%' . $criteria['isbn'] . '%';
            }
            
            // Title search
            if (!empty($criteria['judul'])) {
                $where_conditions[] = "b.judul LIKE ?";
                $params[] = '%' . $criteria['judul'] . '%';
            }
            
            // Author search
            if (!empty($criteria['pengarang'])) {
                $where_conditions[] = "b.pengarang LIKE ?";
                $params[] = '%' . $criteria['pengarang'] . '%';
            }
            
            // Publisher search
            if (!empty($criteria['penerbit'])) {
                $where_conditions[] = "p.nama_penerbit LIKE ?";
                $params[] = '%' . $criteria['penerbit'] . '%';
            }
            
            // Year search
            if (!empty($criteria['tahun'])) {
                $where_conditions[] = "b.tahun_terbit = ?";
                $params[] = $criteria['tahun'];
            }
            
            // Category search
            if (!empty($criteria['kategori'])) {
                $where_conditions[] = "k.id_kategori = ?";
                $params[] = $criteria['kategori'];
            }
            
            // Stock status
            if (!empty($criteria['status'])) {
                if ($criteria['status'] === 'tersedia') {
                    $where_conditions[] = "b.stok_tersedia > 0";
                } elseif ($criteria['status'] === 'habis') {
                    $where_conditions[] = "b.stok_tersedia <= 0";
                }
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Count total
            $count_sql = "
                SELECT COUNT(DISTINCT b.isbn)
                FROM buku b
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
                LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
                LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
                $where_clause
            ";
            
            $stmt = $conn->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Get data
            $sql = "
                SELECT b.*, 
                       p.nama_penerbit,
                       GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') as kategori_list
                FROM buku b
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
                LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
                LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
                $where_clause
                GROUP BY b.isbn
                ORDER BY b.judul ASC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'data' => $data,
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log("Error searchBooksAdvanced: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }
}

if (!function_exists('getPenerbitList')) {
    /**
     * Dapatkan daftar penerbit
     */
    function getPenerbitList() {
        global $conn;
        try {
            $stmt = $conn->query("SELECT * FROM penerbit ORDER BY nama_penerbit ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getRakList')) {
    /**
     * Dapatkan daftar rak
     */
    function getRakList() {
        global $conn;
        try {
            $stmt = $conn->query("SELECT * FROM rak ORDER BY kode_rak ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getKategoriList')) {
    /**
     * Dapatkan daftar kategori
     */
    function getKategoriList() {
        global $conn;
        try {
            $stmt = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getBookKategori')) {
    /**
     * Dapatkan kategori untuk buku tertentu
     */
    function getBookKategori($isbn) {
        global $conn;
        try {
            $stmt = $conn->prepare("
                SELECT k.* 
                FROM kategori k
                JOIN buku_kategori bk ON k.id_kategori = bk.id_kategori
                WHERE bk.isbn = ?
            ");
            $stmt->execute([$isbn]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getBookBorrowingHistory')) {
    /**
     * Dapatkan riwayat peminjaman buku
     */
    function getBookBorrowingHistory($isbn, $limit = 20) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT p.*, a.nama, a.nik,
                       pg.tanggal_pengembalian_aktual, pg.denda, pg.kondisi_buku,
                       CASE 
                           WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                           THEN DATEDIFF(CURDATE(), p.tanggal_kembali)
                           WHEN p.status = 'dikembalikan' AND pg.tanggal_pengembalian_aktual > p.tanggal_kembali
                           THEN DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali)
                           ELSE 0
                       END as hari_terlambat
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
                WHERE p.isbn = ?
                ORDER BY p.tanggal_pinjam DESC
                LIMIT ?
            ");
            $stmt->execute([$isbn, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getBookBorrowingHistory: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getMemberBorrowingHistory')) {
    /**
     * Dapatkan riwayat peminjaman anggota
     */
    function getMemberBorrowingHistory($nik, $limit = 10) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT p.*, b.judul, b.isbn, a.nama,
                       pg.tanggal_pengembalian_aktual, pg.denda, pg.kondisi_buku,
                       CASE 
                           WHEN p.status = 'dipinjam' AND p.tanggal_kembali < CURDATE() 
                           THEN DATEDIFF(CURDATE(), p.tanggal_kembali)
                           WHEN p.status = 'dikembalikan' AND pg.tanggal_pengembalian_aktual > p.tanggal_kembali
                           THEN DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali)
                           ELSE 0
                       END as hari_terlambat
                FROM peminjaman p
                JOIN buku b ON p.isbn = b.isbn
                JOIN anggota a ON p.nik = a.nik
                LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
                WHERE p.nik = ?
                ORDER BY p.tanggal_pinjam DESC
                LIMIT ?
            ");
            $stmt->execute([$nik, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getMemberBorrowingHistory: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getBookDetailWithStock')) {
    /**
     * Dapatkan detail buku dengan informasi stok
     */
    function getBookDetailWithStock($isbn) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT b.*, 
                       p.nama_penerbit, 
                       r.kode_rak, r.lokasi as lokasi_rak,
                       GROUP_CONCAT(k.nama_kategori SEPARATOR ', ') as kategori_list,
                       COUNT(DISTINCT bk.id_kategori) as jumlah_kategori
                FROM buku b
                LEFT JOIN penerbit p ON b.id_penerbit = p.id_penerbit
                LEFT JOIN rak r ON b.id_rak = r.id_rak
                LEFT JOIN buku_kategori bk ON b.isbn = bk.isbn
                LEFT JOIN kategori k ON bk.id_kategori = k.id_kategori
                WHERE b.isbn = ?
                GROUP BY b.isbn
            ");
            $stmt->execute([$isbn]);
            $book = $stmt->fetch();
            
            if ($book) {
                // Get borrowing statistics
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total_peminjaman,
                        SUM(CASE WHEN status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam,
                        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as sudah_dikembalikan
                    FROM peminjaman 
                    WHERE isbn = ?
                ");
                $stmt->execute([$isbn]);
                $stats = $stmt->fetch();
                
                $book['total_peminjaman'] = $stats['total_peminjaman'] ?? 0;
                $book['sedang_dipinjam'] = $stats['sedang_dipinjam'] ?? 0;
                $book['sudah_dikembalikan'] = $stats['sudah_dikembalikan'] ?? 0;
            }
            
            return $book ?: null;
        } catch (Exception $e) {
            error_log("Error getBookDetailWithStock: " . $e->getMessage());
            return null;
        }
    }
}

// ==========================================
// KATEGORI 6: STATISTICS & REPORT FUNCTIONS
// ==========================================

if (!function_exists('getDashboardStats')) {
    /**
     * Dapatkan statistik dashboard
     */
    function getDashboardStats() {
        global $conn;
        
        try {
            $stats = [];
            
            // Total books
            $stmt = $conn->query("SELECT COUNT(*) FROM buku");
            $stats['total_buku'] = $stmt->fetchColumn();
            
            // Available books (using stok_tersedia)
            $stmt = $conn->query("SELECT SUM(stok_tersedia) FROM buku WHERE stok_tersedia > 0");
            $stats['buku_tersedia'] = $stmt->fetchColumn() ?: 0;
            
            // Total members
            $stmt = $conn->query("SELECT COUNT(*) FROM anggota");
            $stats['total_anggota'] = $stmt->fetchColumn();
            
            // Currently borrowed books
            $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'");
            $stats['buku_dipinjam'] = $stmt->fetchColumn();
            
            // Overdue books
            $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali < CURDATE()");
            $stats['buku_terlambat'] = $stmt->fetchColumn();
            
            // Total fines - dari tabel pengembalian
            $stmt = $conn->query("SELECT SUM(denda) FROM pengembalian WHERE denda > 0");
            $stats['total_denda'] = $stmt->fetchColumn() ?: 0;
            
            // Stock statistics (NEW)
            $stmt = $conn->query("SELECT SUM(stok_total) FROM buku");
            $stats['total_stok_fisik'] = $stmt->fetchColumn() ?: 0;
            
            $stmt = $conn->query("SELECT COUNT(*) FROM buku WHERE stok_tersedia > 0");
            $stats['buku_dengan_stok_tersedia'] = $stmt->fetchColumn();
            
            $stmt = $conn->query("SELECT COUNT(*) FROM buku WHERE stok_tersedia = 0 AND stok_total > 0");
            $stats['buku_semua_dipinjam'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return [
                'total_buku' => 0,
                'buku_tersedia' => 0,
                'total_anggota' => 0,
                'buku_dipinjam' => 0,
                'buku_terlambat' => 0,
                'total_denda' => 0,
                'total_stok_fisik' => 0,
                'buku_dengan_stok_tersedia' => 0,
                'buku_semua_dipinjam' => 0
            ];
        }
    }
}

if (!function_exists('getBookStatistics')) {
    /**
     * Dapatkan statistik buku (untuk halaman edit buku)
     */
    function getBookStatistics($isbn) {
        global $conn;
        
        try {
            // Borrowing statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_peminjaman,
                    SUM(CASE WHEN status = 'dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam,
                    SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as sudah_dikembalikan
                FROM peminjaman 
                WHERE isbn = ?
            ");
            $stmt->execute([$isbn]);
            $stats = $stmt->fetch();
            
            // Add stock information
            $stmt = $conn->prepare("SELECT stok_total, stok_tersedia FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $stock_info = $stmt->fetch();
            
            if ($stock_info) {
                $stats['stok_total'] = $stock_info['stok_total'];
                $stats['stok_tersedia'] = $stock_info['stok_tersedia'];
            } else {
                $stats['stok_total'] = 0;
                $stats['stok_tersedia'] = 0;
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Book statistics error: " . $e->getMessage());
            return [
                'total_peminjaman' => 0,
                'sedang_dipinjam' => 0,
                'sudah_dikembalikan' => 0,
                'stok_total' => 0,
                'stok_tersedia' => 0
            ];
        }
    }
}

if (!function_exists('getUserStats')) {
    /**
     * Dapatkan statistik pengguna
     */
    function getUserStats($nik) {
        global $conn;
        
        try {
            $stats = [];
            
            // Currently borrowed books
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam'");
            $stmt->execute([$nik]);
            $stats['buku_dipinjam'] = $stmt->fetchColumn();
            
            // Total borrowing history
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ?");
            $stmt->execute([$nik]);
            $stats['total_riwayat'] = $stmt->fetchColumn();
            
            // Outstanding fines - dari tabel pengembalian
            $stmt = $conn->prepare("
                SELECT SUM(pg.denda) 
                FROM pengembalian pg
                JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                WHERE p.nik = ? AND pg.denda > 0
            ");
            $stmt->execute([$nik]);
            $stats['total_denda'] = $stmt->fetchColumn() ?: 0;
            
            // Overdue books
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE nik = ? AND status = 'dipinjam' AND tanggal_kembali < CURDATE()");
            $stmt->execute([$nik]);
            $stats['buku_terlambat'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (PDOException $e) {
            return [
                'buku_dipinjam' => 0,
                'total_riwayat' => 0,
                'total_denda' => 0,
                'buku_terlambat' => 0
            ];
        }
    }
}

if (!function_exists('getMemberTotalFines')) {
    /**
     * Dapatkan total denda anggota
     */
    function getMemberTotalFines($nik) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT SUM(pg.denda) as total_denda
                FROM pengembalian pg
                JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                WHERE p.nik = ? AND pg.denda > 0
            ");
            $stmt->execute([$nik]);
            $result = $stmt->fetch();
            
            return $result ? (float)$result['total_denda'] : 0;
        } catch (Exception $e) {
            error_log("Error getMemberTotalFines: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('getSystemStatistics')) {
    /**
     * Dapatkan statistik sistem lengkap
     */
    function getSystemStatistics() {
        global $conn;
        
        try {
            $stats = [];
            
            // Total books count
            $stmt = $conn->query("SELECT COUNT(*) as total, SUM(stok_total) as total_stok FROM buku");
            $book_stats = $stmt->fetch();
            $stats['total_buku'] = $book_stats['total'];
            $stats['total_stok_fisik'] = $book_stats['total_stok'] ?: 0;
            
            // Available books
            $stmt = $conn->query("SELECT SUM(stok_tersedia) FROM buku");
            $stats['buku_tersedia'] = $stmt->fetchColumn() ?: 0;
            
            // Total members
            $stmt = $conn->query("SELECT COUNT(*) FROM anggota");
            $stats['total_anggota'] = $stmt->fetchColumn();
            
            // Active borrowings
            $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam'");
            $stats['buku_dipinjam'] = $stmt->fetchColumn();
            
            // Overdue books
            $stmt = $conn->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'dipinjam' AND tanggal_kembali < CURDATE()");
            $stats['buku_terlambat'] = $stmt->fetchColumn();
            
            // Total fines
            $stmt = $conn->query("SELECT SUM(denda) FROM pengembalian WHERE denda > 0");
            $stats['total_denda'] = $stmt->fetchColumn() ?: 0;
            
            // Users by role
            $stmt = $conn->query("SELECT role, COUNT(*) as jumlah FROM users WHERE status = 'aktif' GROUP BY role");
            $stats['users_by_role'] = $stmt->fetchAll();
            
            // Activity logs count
            $stmt = $conn->query("SELECT COUNT(*) FROM log_aktivitas");
            $stats['total_logs'] = $stmt->fetchColumn();
            
            // Today's activity
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            $stats['logs_today'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getSystemStatistics: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getDailyStatistics')) {
    /**
     * Dapatkan statistik harian
     */
    function getDailyStatistics($date = null) {
        global $conn;
        
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        try {
            $stats = [];
            
            // Borrowings today
            $stmt = $conn->prepare("SELECT COUNT(*) FROM peminjaman WHERE DATE(tanggal_pinjam) = ?");
            $stmt->execute([$date]);
            $stats['peminjaman_hari_ini'] = $stmt->fetchColumn();
            
            // Returns today
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM pengembalian 
                WHERE DATE(tanggal_pengembalian_aktual) = ?
            ");
            $stmt->execute([$date]);
            $stats['pengembalian_hari_ini'] = $stmt->fetchColumn();
            
            // Fines collected today
            $stmt = $conn->prepare("
                SELECT SUM(denda) 
                FROM pengembalian 
                WHERE DATE(tanggal_pengembalian_aktual) = ? AND denda > 0
            ");
            $stmt->execute([$date]);
            $stats['denda_hari_ini'] = $stmt->fetchColumn() ?: 0;
            
            // New members today
            $stmt = $conn->prepare("SELECT COUNT(*) FROM anggota WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
            $stats['anggota_baru_hari_ini'] = $stmt->fetchColumn();
            
            // New books added today
            $stmt = $conn->prepare("SELECT COUNT(*) FROM buku WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
            $stats['buku_baru_hari_ini'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getDailyStatistics: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getMonthlyStatistics')) {
    /**
     * Dapatkan statistik bulanan
     */
    function getMonthlyStatistics($year = null, $month = null) {
        global $conn;
        
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        try {
            $stats = [];
            $start_date = "$year-$month-01";
            $end_date = date('Y-m-t', strtotime($start_date));
            
            // Monthly borrowings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total, DATE(tanggal_pinjam) as date 
                FROM peminjaman 
                WHERE DATE(tanggal_pinjam) BETWEEN ? AND ?
                GROUP BY DATE(tanggal_pinjam)
            ");
            $stmt->execute([$start_date, $end_date]);
            $stats['peminjaman_bulanan'] = $stmt->fetchAll();
            
            // Monthly returns
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total, DATE(tanggal_pengembalian_aktual) as date, SUM(denda) as total_denda
                FROM pengembalian 
                WHERE DATE(tanggal_pengembalian_aktual) BETWEEN ? AND ?
                GROUP BY DATE(tanggal_pengembalian_aktual)
            ");
            $stmt->execute([$start_date, $end_date]);
            $stats['pengembalian_bulanan'] = $stmt->fetchAll();
            
            // Most borrowed books
            $stmt = $conn->prepare("
                SELECT b.judul, COUNT(*) as jumlah
                FROM peminjaman p
                JOIN buku b ON p.isbn = b.isbn
                WHERE DATE(p.tanggal_pinjam) BETWEEN ? AND ?
                GROUP BY p.isbn
                ORDER BY jumlah DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $stats['buku_terpopuler'] = $stmt->fetchAll();
            
            // Most active members
            $stmt = $conn->prepare("
                SELECT a.nama, COUNT(*) as jumlah
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                WHERE DATE(p.tanggal_pinjam) BETWEEN ? AND ?
                GROUP BY p.nik
                ORDER BY jumlah DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $stats['anggota_teraktif'] = $stmt->fetchAll();
            
            // Total fines for month
            $stmt = $conn->prepare("
                SELECT SUM(denda) as total_denda
                FROM pengembalian 
                WHERE DATE(tanggal_pengembalian_aktual) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $result = $stmt->fetch();
            $stats['total_denda_bulanan'] = $result['total_denda'] ?: 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getMonthlyStatistics: " . $e->getMessage());
            return [];
        }
    }
}

// ==========================================
// KATEGORI 7: VALIDATION & SANITIZATION FUNCTIONS
// ==========================================

if (!function_exists('validateISBN')) {
    /**
     * Validasi format ISBN
     */
    function validateISBN($isbn) {
        $isbn = preg_replace('/[-\s]/', '', $isbn);
        return preg_match('/^(978|979)\d{10}$/', $isbn);
    }
}

if (!function_exists('validateNIK')) {
    /**
     * Validasi format NIK (16 digit)
     */
    function validateNIK($nik) {
        return preg_match('/^\d{16}$/', $nik);
    }
}

if (!function_exists('validatePhoneNumber')) {
    /**
     * Validasi format nomor telepon Indonesia
     */
    function validatePhoneNumber($phone) {
        return preg_match('/^(08|628|\+628)\d{8,11}$/', $phone);
    }
}

if (!function_exists('validateEmail')) {
    /**
     * Validasi format email
     */
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isValidDate')) {
    /**
     * Validasi format tanggal
     */
    function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitasi input untuk mencegah XSS
     * Digunakan di 22 file
     */
    function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateSecureToken')) {
    /**
     * Generate token aman untuk reset password, dll
     */
    function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

// ==========================================
// KATEGORI 8: SESSION & AUTH HELPER FUNCTIONS
// ==========================================

if (!function_exists('isLoggedIn')) {
    /**
     * Cek apakah user sudah login
     */
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
}

if (!function_exists('getCurrentUser')) {
    /**
     * Dapatkan informasi user yang sedang login
     */
    function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'nama' => $_SESSION['nama'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? ''
        ];
    }
}

if (!function_exists('requireLogin')) {
    /**
     * Wajibkan user untuk login
     */
    function requireLogin() {
        if (!isLoggedIn()) {
            redirect('../auth/login.php', 'Silakan login terlebih dahulu', 'warning');
        }
    }
}

if (!function_exists('requireRole')) {
    /**
     * Wajibkan user memiliki role tertentu
     */
    function requireRole($allowed_roles = []) {
        requireLogin();
        
        // Konversi $allowed_roles ke array jika bukan array
        if (!is_array($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        
        if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
            setFlashMessage('Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.', 'danger');
            redirect('../dashboard.php');
        }
    }
}

if (!function_exists('is_admin')) {
    /**
     * Cek apakah user adalah admin
     */
    function is_admin() {
        return isLoggedIn() && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('getUserNIK')) {
    /**
     * Dapatkan NIK dari user yang login
     */
    function getUserNIK() {
        global $conn;
        
        if (!isLoggedIn() || $_SESSION['role'] !== 'anggota') {
            return null;
        }
        
        try {
            $stmt = $conn->prepare("SELECT nik FROM anggota WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['nik'];
            }
            
            $stmt = $conn->prepare("SELECT nik FROM anggota WHERE nama LIKE ? LIMIT 1");
            $stmt->execute(['%' . $_SESSION['nama'] . '%']);
            $result = $stmt->fetch();
            
            return $result ? $result['nik'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('setFlashMessage')) {
    /**
     * Set flash message untuk ditampilkan di halaman berikutnya
     */
    function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }
}

if (!function_exists('getFlashMessage')) {
    /**
     * Dapatkan dan hapus flash message
     */
    function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        return null;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect ke URL tertentu dengan flash message
     */
    function redirect($url, $message = '', $type = 'info') {
        if (!empty($message)) {
            setFlashMessage($message, $type);
        }
        header("Location: $url");
        exit();
    }
}

// ==========================================
// KATEGORI 9: LOGGING & ACTIVITY FUNCTIONS
// ==========================================

if (!function_exists('logActivity')) {
    /**
     * Log aktivitas user
     */
    function logActivity($action, $description = '', $target_type = null, $target_id = null) {
        global $conn;
        
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'guest';
            $nama = $_SESSION['nama'] ?? 'Guest User';
            $role = $_SESSION['role'] ?? 'guest';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            
            $stmt = $conn->prepare("
                INSERT INTO log_aktivitas 
                (user_id, username, nama, role, action, description, target_type, target_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $username,
                $nama,
                $role,
                $action,
                $description,
                $target_type,
                $target_id,
                $ip_address,
                $user_agent
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Log activity error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getLogs')) {
    /**
     * Dapatkan logs dengan filter
     */
    function getLogs($filters = [], $limit = 50, $offset = 0) {
        global $conn;
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['username'])) {
            $where .= " AND username LIKE ?";
            $params[] = '%' . $filters['username'] . '%';
        }
        
        if (!empty($filters['role'])) {
            $where .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['action'])) {
            $where .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (description LIKE ? OR username LIKE ? OR action LIKE ? OR target_id LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM log_aktivitas $where";
        $stmt = $conn->prepare($count_query);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get data with pagination
        $query = "SELECT * FROM log_aktivitas $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total
        ];
    }
}

if (!function_exists('getLogStats')) {
    /**
     * Dapatkan statistik logs
     */
    function getLogStats($days = 30) {
        global $conn;
        
        $stats = [];
        $date_limit = date('Y-m-d', strtotime("-$days days"));
        
        // Total logs
        $stmt = $conn->query("SELECT COUNT(*) FROM log_aktivitas");
        $stats['total_logs'] = $stmt->fetchColumn();
        
        // Today's logs
        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $stats['logs_today'] = $stmt->fetchColumn();
        
        // This week's logs
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $stmt = $conn->prepare("SELECT COUNT(*) FROM log_aktivitas WHERE DATE(created_at) >= ?");
        $stmt->execute([$week_start]);
        $stats['logs_week'] = $stmt->fetchColumn();
        
        // Most active users (last $days days)
        $stmt = $conn->prepare("
            SELECT username, nama, role, COUNT(*) as total 
            FROM log_aktivitas 
            WHERE created_at >= ? AND username IS NOT NULL
            GROUP BY username, nama, role 
            ORDER BY total DESC 
            LIMIT 10
        ");
        $stmt->execute([$date_limit]);
        $stats['active_users'] = $stmt->fetchAll();
        
        // Most common actions
        $stmt = $conn->prepare("
            SELECT action, COUNT(*) as total 
            FROM log_aktivitas 
            WHERE created_at >= ?
            GROUP BY action 
            ORDER BY total DESC 
            LIMIT 10
        ");
        $stmt->execute([$date_limit]);
        $stats['common_actions'] = $stmt->fetchAll();
        
        // Daily activity
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as total 
            FROM log_aktivitas 
            WHERE created_at >= ?
            GROUP BY DATE(created_at) 
            ORDER BY date DESC
        ");
        $stmt->execute([$date_limit]);
        $stats['daily_activity'] = $stmt->fetchAll();
        
        return $stats;
    }
}

if (!function_exists('getLogActions')) {
    /**
     * Dapatkan daftar action logs yang tersedia
     */
    function getLogActions() {
        global $conn;
        
        $stmt = $conn->query("SELECT DISTINCT action FROM log_aktivitas ORDER BY action");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('cleanupOldLogs')) {
    /**
     * Hapus logs yang sudah lama
     */
    function cleanupOldLogs($days = 90) {
        global $conn;
        
        $date_limit = date('Y-m-d', strtotime("-$days days"));
        $stmt = $conn->prepare("DELETE FROM log_aktivitas WHERE DATE(created_at) < ?");
        $stmt->execute([$date_limit]);
        
        return $stmt->rowCount();
    }
}

if (!function_exists('exportLogsToCSV')) {
    /**
     * Export logs ke format CSV
     */
    function exportLogsToCSV($filters = []) {
        global $conn;
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="log_aktivitas_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, [
            'ID', 'Tanggal', 'Waktu', 'User ID', 'Username', 'Nama', 'Role', 
            'Aksi', 'Deskripsi', 'Target Type', 'Target ID', 'IP Address', 'User Agent'
        ]);
        
        // Build query
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['username'])) {
            $where .= " AND username LIKE ?";
            $params[] = '%' . $filters['username'] . '%';
        }
        
        if (!empty($filters['role'])) {
            $where .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['action'])) {
            $where .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (description LIKE ? OR username LIKE ? OR action LIKE ? OR target_id LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $query = "SELECT * FROM log_aktivitas $where ORDER BY id DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                date('d/m/Y', strtotime($row['created_at'])),
                date('H:i:s', strtotime($row['created_at'])),
                $row['user_id'],
                $row['username'],
                $row['nama'],
                $row['role'],
                $row['action'],
                $row['description'],
                $row['target_type'],
                $row['target_id'],
                $row['ip_address'],
                $row['user_agent']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// ==========================================
// KATEGORI 10: UTILITY & GENERATOR FUNCTIONS
// ==========================================

if (!function_exists('getSetting')) {
    /**
     * Dapatkan setting dari database
     */
    function getSetting($key, $default = null) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("SELECT setting_value FROM pengaturan WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('generateRandomPassword')) {
    /**
     * Generate password acak
     */
    function generateRandomPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
}

if (!function_exists('generateUniqueId')) {
    /**
     * Generate ID unik dengan prefix
     */
    function generateUniqueId($prefix = '') {
        return $prefix . date('Ymd') . substr(uniqid(), -6);
    }
}

if (!function_exists('generateBorrowingReceipt')) {
    /**
     * Generate kuitansi peminjaman
     */
    function generateBorrowingReceipt($peminjaman_id) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT p.*, a.nama as nama_anggota, a.nik, b.judul, b.isbn,
                       DATE_ADD(p.tanggal_pinjam, INTERVAL 14 DAY) as batas_kembali,
                       u.username as petugas
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                JOIN buku b ON p.isbn = b.isbn
                LEFT JOIN users u ON a.user_id = u.id
                WHERE p.id_peminjaman = ?
            ");
            $stmt->execute([$peminjaman_id]);
            $data = $stmt->fetch();
            
            if (!$data) {
                return null;
            }
            
            $receipt = "
                KUITANSI PEMINJAMAN BUKU
                ============================
                
                ID Peminjaman : #{$data['id_peminjaman']}
                Tanggal Pinjam: " . formatTanggal($data['tanggal_pinjam']) . "
                Batas Kembali : " . formatTanggal($data['batas_kembali']) . "
                
                Data Anggota:
                -------------
                Nama    : {$data['nama_anggota']}
                NIK     : {$data['nik']}
                
                Data Buku:
                -----------
                Judul   : {$data['judul']}
                ISBN    : {$data['isbn']}
                
                Petugas : " . ($data['petugas'] ?? 'Sistem') . "
                
                Catatan:
                • Buku harus dikembalikan sebelum tanggal {$data['batas_kembali']}
                • Denda keterlambatan Rp 1.000 per hari
                • Buku yang rusak akan dikenakan denda tambahan
                
                ============================
                Tanda tangan penerima:
                
                ____________________
                ({$data['nama_anggota']})
            ";
            
            return $receipt;
        } catch (Exception $e) {
            error_log("Error generateBorrowingReceipt: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('generateReturnReceipt')) {
    /**
     * Generate kuitansi pengembalian
     */
    function generateReturnReceipt($peminjaman_id) {
        global $conn;
        
        try {
            $stmt = $conn->prepare("
                SELECT p.*, a.nama as nama_anggota, a.nik, b.judul, b.isbn,
                       pg.tanggal_pengembalian_aktual, pg.denda, pg.kondisi_buku,
                       DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali) as hari_terlambat,
                       u.username as petugas
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                JOIN buku b ON p.isbn = b.isbn
                JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
                LEFT JOIN users u ON a.user_id = u.id
                WHERE p.id_peminjaman = ?
            ");
            $stmt->execute([$peminjaman_id]);
            $data = $stmt->fetch();
            
            if (!$data) {
                return null;
            }
            
            $kondisi_text = [
                'baik' => 'Baik',
                'rusak_ringan' => 'Rusak Ringan',
                'rusak_berat' => 'Rusak Berat'
            ];
            
            $receipt = "
                KUITANSI PENGEMBALIAN BUKU
                ============================
                
                ID Peminjaman : #{$data['id_peminjaman']}
                Tanggal Pinjam: " . formatTanggal($data['tanggal_pinjam']) . "
                Batas Kembali : " . formatTanggal($data['tanggal_kembali']) . "
                Tgl Kembali   : " . formatTanggal($data['tanggal_pengembalian_aktual']) . "
                
                Data Anggota:
                -------------
                Nama    : {$data['nama_anggota']}
                NIK     : {$data['nik']}
                
                Data Buku:
                -----------
                Judul   : {$data['judul']}
                ISBN    : {$data['isbn']}
                Kondisi : " . ($kondisi_text[$data['kondisi_buku']] ?? 'Baik') . "
                
                Perhitungan Denda:
                ------------------
                " . ($data['hari_terlambat'] > 0 ? 
                    "Keterlambatan: {$data['hari_terlambat']} hari x Rp 1.000 = Rp " . ($data['hari_terlambat'] * 1000) . "\n" : 
                    "Tidak ada keterlambatan\n") .
                ($data['denda'] > $data['hari_terlambat'] * 1000 ? 
                    "Denda kondisi buku: Rp " . ($data['denda'] - $data['hari_terlambat'] * 1000) . "\n" : "") . "
                TOTAL DENDA  : Rp " . number_format($data['denda'], 0, ',', '.') . "
                
                Petugas : " . ($data['petugas'] ?? 'Sistem') . "
                
                ============================
                Tanda tangan penerima:
                
                ____________________
                ({$data['nama_anggota']})
            ";
            
            return $receipt;
        } catch (Exception $e) {
            error_log("Error generateReturnReceipt: " . $e->getMessage());
            return null;
        }
    }
}

// ==========================================
// KATEGORI 11: PAGINATION FUNCTIONS
// ==========================================

if (!function_exists('paginate')) {
    /**
     * Helper untuk pagination
     */
    function paginate($page, $total_items, $items_per_page = 10) {
        $total_pages = ceil($total_items / $items_per_page);
        $current_page = max(1, min($page, $total_pages));
        $offset = ($current_page - 1) * $items_per_page;
        
        return [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'items_per_page' => $items_per_page,
            'offset' => $offset,
            'total_items' => $total_items
        ];
    }
}

// ==========================================
// KATEGORI 12: TRANSACTION MANAGEMENT FUNCTIONS
// ==========================================

if (!function_exists('getCurrentBorrowings')) {
    /**
     * Dapatkan daftar peminjaman aktif dengan filter
     */
    function getCurrentBorrowings($filter = []) {
        global $conn;
        
        try {
            $where = "WHERE p.status = 'dipinjam'";
            $params = [];
            
            if (!empty($filter['nik'])) {
                $where .= " AND p.nik LIKE ?";
                $params[] = '%' . $filter['nik'] . '%';
            }
            
            if (!empty($filter['isbn'])) {
                $where .= " AND p.isbn LIKE ?";
                $params[] = '%' . $filter['isbn'] . '%';
            }
            
            if (!empty($filter['nama'])) {
                $where .= " AND a.nama LIKE ?";
                $params[] = '%' . $filter['nama'] . '%';
            }
            
            if (!empty($filter['status']) && $filter['status'] === 'terlambat') {
                $where .= " AND p.tanggal_kembali < CURDATE()";
            }
            
            $stmt = $conn->prepare("
                SELECT p.*, a.nama, b.judul,
                       DATEDIFF(CURDATE(), p.tanggal_kembali) as hari_terlambat
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                JOIN buku b ON p.isbn = b.isbn
                $where
                ORDER BY p.tanggal_kembali ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getCurrentBorrowings: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getReturnHistory')) {
    /**
     * Dapatkan riwayat pengembalian dengan filter
     */
    function getReturnHistory($filter = [], $limit = 50, $offset = 0) {
        global $conn;
        
        try {
            $where = "WHERE p.status = 'dikembalikan'";
            $params = [];
            
            if (!empty($filter['nik'])) {
                $where .= " AND p.nik LIKE ?";
                $params[] = '%' . $filter['nik'] . '%';
            }
            
            if (!empty($filter['isbn'])) {
                $where .= " AND p.isbn LIKE ?";
                $params[] = '%' . $filter['isbn'] . '%';
            }
            
            if (!empty($filter['nama'])) {
                $where .= " AND a.nama LIKE ?";
                $params[] = '%' . $filter['nama'] . '%';
            }
            
            if (!empty($filter['date_from'])) {
                $where .= " AND DATE(pg.tanggal_pengembalian_aktual) >= ?";
                $params[] = $filter['date_from'];
            }
            
            if (!empty($filter['date_to'])) {
                $where .= " AND DATE(pg.tanggal_pengembalian_aktual) <= ?";
                $params[] = $filter['date_to'];
            }
            
            if (!empty($filter['has_fine']) && $filter['has_fine'] === 'yes') {
                $where .= " AND pg.denda > 0";
            }
            
            // Count total
            $count_sql = "
                SELECT COUNT(*)
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
                $where
            ";
            $stmt = $conn->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Get data
            $sql = "
                SELECT p.*, a.nama, b.judul, 
                       pg.tanggal_pengembalian_aktual, pg.denda, pg.kondisi_buku,
                       DATEDIFF(pg.tanggal_pengembalian_aktual, p.tanggal_kembali) as hari_terlambat
                FROM peminjaman p
                JOIN anggota a ON p.nik = a.nik
                JOIN buku b ON p.isbn = b.isbn
                JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
                $where
                ORDER BY pg.tanggal_pengembalian_aktual DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            return [
                'data' => $data,
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log("Error getReturnHistory: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }
}

// ==========================================
// KATEGORI 13: DATABASE MAINTENANCE FUNCTIONS
// ==========================================

if (!function_exists('createDatabaseBackup')) {
    /**
     * Buat backup database
     */
    function createDatabaseBackup($backup_type = 'structure_data') {
        global $conn;
        
        try {
            $backup_dir = __DIR__ . '/../backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $filename = 'perpustakaan_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . $filename;
            
            // Get all tables
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $backup_sql = "-- Database Backup: " . date('Y-m-d H:i:s') . "\n";
            $backup_sql .= "-- Backup Type: $backup_type\n\n";
            
            foreach ($tables as $table) {
                // Get table structure
                $stmt = $conn->query("SHOW CREATE TABLE `$table`");
                $result = $stmt->fetch();
                $backup_sql .= $result['Create Table'] . ";\n\n";
                
                if ($backup_type !== 'structure_only') {
                    // Get table data
                    $stmt = $conn->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rows) > 0) {
                        $backup_sql .= "INSERT INTO `$table` VALUES ";
                        $values = [];
                        
                        foreach ($rows as $row) {
                            $row_values = array_map(function($value) use ($conn) {
                                if ($value === null) return 'NULL';
                                return $conn->quote($value);
                            }, $row);
                            
                            $values[] = '(' . implode(', ', $row_values) . ')';
                        }
                        
                        $backup_sql .= implode(",\n       ", $values) . ";\n\n";
                    }
                }
            }
            
            // Write to file
            file_put_contents($filepath, $backup_sql);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'type' => $backup_type
            ];
        } catch (Exception $e) {
            error_log("Error createDatabaseBackup: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('cleanupSessions')) {
    /**
     * Bersihkan session yang sudah lama
     */
    function cleanupSessions($older_than_hours = 24) {
        try {
            $session_dir = session_save_path();
            if (empty($session_dir)) {
                $session_dir = sys_get_temp_dir();
            }
            
            $count = 0;
            $size_freed = 0;
            
            $files = glob($session_dir . '/sess_*');
            foreach ($files as $file) {
                if (time() - filemtime($file) > $older_than_hours * 3600) {
                    $size_freed += filesize($file);
                    unlink($file);
                    $count++;
                }
            }
            
            return [
                'count' => $count,
                'size_freed' => $size_freed
            ];
        } catch (Exception $e) {
            error_log("Error cleanupSessions: " . $e->getMessage());
            return [
                'count' => 0,
                'size_freed' => 0
            ];
        }
    }
}

// ==========================================
// KATEGORI: RAK CAPACITY FUNCTIONS - BARU
// ==========================================

if (!function_exists('getRakCapacityInfo')) {
    /**
     * Dapatkan informasi kapasitas rak
     */
    function getRakCapacityInfo($id_rak) {
        global $conn;
        
        try {
            // Get rak capacity
            $stmt = $conn->prepare("SELECT kapasitas FROM rak WHERE id_rak = ?");
            $stmt->execute([$id_rak]);
            $rak = $stmt->fetch();
            
            if (!$rak) {
                return ['max' => null, 'used' => 0, 'free' => null, 'persentase' => 0];
            }
            
            // Get total stok di rak
            $stmt = $conn->prepare("SELECT SUM(stok_total) FROM buku WHERE id_rak = ?");
            $stmt->execute([$id_rak]);
            $used = $stmt->fetchColumn() ?: 0;
            
            // Calculate percentage if there's a capacity limit
            $persentase = 0;
            if ($rak['kapasitas'] && $rak['kapasitas'] > 0) {
                $persentase = round(($used / $rak['kapasitas']) * 100);
            }
            
            return [
                'max' => $rak['kapasitas'],
                'used' => $used,
                'free' => $rak['kapasitas'] ? ($rak['kapasitas'] - $used) : null,
                'persentase' => $persentase
            ];
        } catch (Exception $e) {
            error_log("Error getRakCapacityInfo: " . $e->getMessage());
            return ['max' => null, 'used' => 0, 'free' => null, 'persentase' => 0];
        }
    }
}

if (!function_exists('checkRakCapacity')) {
    /**
     * Cek apakah rak bisa menampung tambahan buku
     */
    function checkRakCapacity($id_rak, $additional_stok, $exclude_isbn = null) {
        global $conn;
        
        if (!$id_rak) {
            return ['can_fit' => true, 'message' => 'No rak selected'];
        }
        
        try {
            $capacity_info = getRakCapacityInfo($id_rak);
            
            // Unlimited capacity
            if ($capacity_info['max'] === null) {
                return ['can_fit' => true, 'message' => 'Rak dengan kapasitas unlimited'];
            }
            
            // Adjust for excluded book
            $adjusted_free = $capacity_info['free'];
            if ($exclude_isbn) {
                $stmt = $conn->prepare("SELECT stok_total FROM buku WHERE isbn = ? AND id_rak = ?");
                $stmt->execute([$exclude_isbn, $id_rak]);
                $excluded_stok = $stmt->fetchColumn() ?: 0;
                $adjusted_free += $excluded_stok;
            }
            
            // Check if there's enough space
            if ($adjusted_free !== null && $additional_stok > $adjusted_free) {
                return [
                    'can_fit' => false,
                    'message' => "Kapasitas tidak cukup. Sisa: {$adjusted_free} buku, Dibutuhkan: {$additional_stok} buku"
                ];
            }
            
            return [
                'can_fit' => true,
                'message' => "OK. Sisa kapasitas: {$adjusted_free} buku"
            ];
            
        } catch (Exception $e) {
            error_log("Error checkRakCapacity: " . $e->getMessage());
            return ['can_fit' => false, 'message' => 'Error checking capacity'];
        }
    }
}

if (!function_exists('getRakListWithCapacity')) {
    /**
     * Dapatkan daftar rak dengan informasi kapasitas
     */
    function getRakListWithCapacity() {
        global $conn;
        
        try {
            $stmt = $conn->query("
                SELECT r.*, 
                       COALESCE(SUM(b.stok_total), 0) as stok_terpakai,
                       CASE 
                           WHEN r.kapasitas IS NULL THEN NULL
                           ELSE r.kapasitas - COALESCE(SUM(b.stok_total), 0)
                       END as sisa_kapasitas
                FROM rak r
                LEFT JOIN buku b ON r.id_rak = b.id_rak
                GROUP BY r.id_rak
                ORDER BY r.kode_rak ASC
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getRakListWithCapacity: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getRakStatusBadge')) {
    /**
     * Dapatkan badge status rak berdasarkan kapasitas
     */
    function getRakStatusBadge($id_rak) {
        $capacity_info = getRakCapacityInfo($id_rak);
        
        if ($capacity_info['max'] === null) {
            return [
                'badge' => '<span class="badge bg-secondary">Unlimited</span>',
                'text' => 'Kapasitas Unlimited',
                'class' => 'bg-secondary'
            ];
        }
        
        if ($capacity_info['used'] == 0) {
            return [
                'badge' => '<span class="badge bg-success">Kosong</span>',
                'text' => 'Rak kosong',
                'class' => 'bg-success'
            ];
        }
        
        if ($capacity_info['free'] <= 0) {
            return [
                'badge' => '<span class="badge bg-danger">Penuh</span>',
                'text' => 'Rak penuh',
                'class' => 'bg-danger'
            ];
        }
        
        if ($capacity_info['persentase'] >= 90) {
            return [
                'badge' => '<span class="badge bg-danger">Hampir Penuh</span>',
                'text' => 'Hampir penuh (' . $capacity_info['persentase'] . '%)',
                'class' => 'bg-danger'
            ];
        }
        
        if ($capacity_info['persentase'] >= 70) {
            return [
                'badge' => '<span class="badge bg-warning">Tersedia</span>',
                'text' => 'Tersedia (' . $capacity_info['persentase'] . '%)',
                'class' => 'bg-warning'
            ];
        }
        
        return [
            'badge' => '<span class="badge bg-info">Tersedia</span>',
            'text' => 'Tersedia (' . $capacity_info['persentase'] . '%)',
            'class' => 'bg-info'
        ];
    }
}

if (!function_exists('validateBookCapacity')) {
    /**
     * Validasi kapasitas untuk buku
     */
    function validateBookCapacity($id_rak, $current_stok_total, $new_stok_total, $book_isbn) {
        global $conn;
        
        if (!$id_rak) return ['valid' => true, 'message' => ''];
        
        try {
            // Get rak capacity
            $stmt = $conn->prepare("SELECT kapasitas FROM rak WHERE id_rak = ?");
            $stmt->execute([$id_rak]);
            $rak = $stmt->fetch();
            
            if (!$rak || !$rak['kapasitas']) {
                return ['valid' => true, 'message' => 'Rak tanpa kapasitas'];
            }
            
            // Get total stok di rak (excluding current book)
            $stmt = $conn->prepare("
                SELECT SUM(stok_total) 
                FROM buku 
                WHERE id_rak = ? AND isbn != ?
            ");
            $stmt->execute([$id_rak, $book_isbn]);
            $other_books_stok = $stmt->fetchColumn() ?: 0;
            
            // Calculate available space
            $current_space_used = $other_books_stok + $current_stok_total;
            $new_space_needed = $other_books_stok + $new_stok_total;
            
            if ($new_space_needed > $rak['kapasitas']) {
                $available = $rak['kapasitas'] - $other_books_stok;
                return [
                    'valid' => false,
                    'message' => "Rak hanya dapat menampung {$available} buku lagi. Anda mencoba menambahkan {$new_stok_total} buku."
                ];
            }
            
            return ['valid' => true, 'message' => ''];
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
// Tambahkan di functions.php
if (!function_exists('getBookingList')) {
    function getBookingList($filter = []) {
        global $conn;
        // ... implementasi
    }
}

if (!function_exists('checkBookingAvailability')) {
    function checkBookingAvailability($isbn) {
        global $conn;
        // ... implementasi
    }
}
?>