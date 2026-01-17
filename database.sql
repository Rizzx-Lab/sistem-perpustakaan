-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Jan 2026 pada 17.11
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `testing`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `anggota`
--

CREATE TABLE `anggota` (
  `nik` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `alamat` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `anggota`
--

INSERT INTO `anggota` (`nik`, `nama`, `no_hp`, `alamat`, `created_at`, `user_id`) VALUES
('3578131309080001', 'aditya ramadhani', '085819820260', 'Tembok Dukuh V/50', '2025-12-16 02:13:04', NULL),
('3578141604090002', 'Muhammad Farid Setiawan', '0881027099361', 'palem pertiwi, menganti', '2025-09-30 02:29:27', 7),
('3578141604090006', 'Muhammad Faridz Setiawan', '', 'JL. Manukan Lor 3F/No. 19', '2025-12-16 01:53:46', 12),
('3578141604090010', 'faizuz', '081234567890', 'Jl. Contoh No. 123, Surabaya', '2025-12-03 13:00:20', 6),
('3578141604090011', 'Ahmad Yusuf', '081234567891', 'Jl. Default No. 456, Surabaya', '2025-12-03 13:00:23', 3),
('3578266404090004', 'Poetry Lunaris Azka', '089508177700', 'Jl. Sememi Jaya VB.1/10', '2025-10-27 05:24:25', 9),
('3578300311080001', 'rendra dwi santoso', '08696969669', 'jl.surga', '2025-12-16 01:44:29', 11),
('3578313012080001', 'Faathirta Tri Tedsa', '082140811418', 'Jl. sambirogoIV M/28', '2025-09-30 03:27:08', 8);

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking`
--

CREATE TABLE `booking` (
  `id_booking` int(11) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `isbn` varchar(20) NOT NULL,
  `tanggal_booking` date NOT NULL,
  `status` enum('menunggu','dibatalkan','dipinjam') DEFAULT 'menunggu',
  `expired_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `booking`
--

INSERT INTO `booking` (`id_booking`, `nik`, `isbn`, `tanggal_booking`, `status`, `expired_at`, `created_at`) VALUES
(1, '3578141604090002', '978-602-4567-89-0', '2025-12-17', 'dibatalkan', '0000-00-00', '2025-12-17 11:46:27'),
(2, '3578300311080001', '978-149-1901-42-7', '2025-12-17', 'dipinjam', '0000-00-00', '2025-12-17 12:32:48'),
(3, '3578141604090006', '978-602-8901-23-4', '2025-12-17', 'dibatalkan', '0000-00-00', '2025-12-17 12:55:47'),
(4, '3578141604090002', '978-602-8901-23-4', '2025-12-17', 'dibatalkan', '0000-00-00', '2025-12-17 12:59:05'),
(5, '3578300311080001', '978-602-4567-89-0', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:16:57'),
(6, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:18:28'),
(7, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:27:07'),
(8, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:28:52'),
(9, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:29:36'),
(10, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:36:49'),
(11, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:37:39'),
(12, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:42:37'),
(13, '3578300311080001', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 02:45:00'),
(14, '3578141604090002', '978-602-4567-89-0', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 03:13:11'),
(15, '3578141604090002', '978-149-1901-42-7', '2025-12-18', 'dibatalkan', '2025-12-20', '2025-12-18 03:29:35'),
(16, '3578300311080001', '978-602-8901-23-4', '2026-01-06', 'dipinjam', '0000-00-00', '2026-01-06 15:13:58'),
(17, '3578300311080001', '978-602-6789-01-2', '2026-01-06', 'menunggu', '2026-01-08', '2026-01-06 15:19:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `buku`
--

CREATE TABLE `buku` (
  `isbn` varchar(20) NOT NULL,
  `judul` varchar(100) NOT NULL,
  `pengarang` varchar(50) NOT NULL,
  `id_penerbit` int(11) DEFAULT NULL,
  `id_rak` int(11) DEFAULT NULL,
  `tahun_terbit` int(4) NOT NULL,
  `stok_total` int(11) DEFAULT 0,
  `stok_tersedia` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'tersedia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `buku`
--

INSERT INTO `buku` (`isbn`, `judul`, `pengarang`, `id_penerbit`, `id_rak`, `tahun_terbit`, `stok_total`, `stok_tersedia`, `status`, `created_at`) VALUES
('978-149-1901-42-7', 'Data Science from Scratch', 'Joel Grus', 5, 1, 2019, 5, 3, 'tersedia', '2025-12-05 12:00:32'),
('978-602-2345-67-8', 'Database MySQL Lanjutan', 'Budi Rahardjo', 1, 1, 2023, 3, 3, 'tersedia', '2025-09-25 08:39:46'),
('978-602-3456-78-9', 'Web Development Modern', 'Citra Dewi', 2, 2, 2024, 4, 4, 'tersedia', '2025-09-25 08:39:46'),
('978-602-4567-89-0', 'Algoritma dan Struktur Data', 'Dedi Supardi', 3, NULL, 2022, 0, 0, 'tidak tersedia', '2025-09-25 08:39:46'),
('978-602-5678-90-1', 'JavaScript ES6 dan Beyond', 'Eka Firmansyah', 4, NULL, 2024, 6, 6, 'tersedia', '2025-09-25 08:39:46'),
('978-602-6789-01-2', 'Python untuk Data Science', 'Fajar Nugroho', 1, NULL, 2023, 3, 3, 'tersedia', '2025-09-25 08:39:46'),
('978-602-8901-23-4', 'Framework Laravel 10', 'Hendra Wijaya', 3, NULL, 2023, 2, 1, 'tersedia', '2025-09-25 08:39:46');

-- --------------------------------------------------------

--
-- Struktur dari tabel `buku_hilang`
--

CREATE TABLE `buku_hilang` (
  `id_hilang` int(11) NOT NULL,
  `id_peminjaman` int(11) NOT NULL,
  `status` enum('hilang','rusak_parah') NOT NULL,
  `denda_hilang` decimal(10,2) NOT NULL,
  `alasan` text DEFAULT NULL,
  `tanggal_laporan` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `buku_hilang`
--

INSERT INTO `buku_hilang` (`id_hilang`, `id_peminjaman`, `status`, `denda_hilang`, `alasan`, `tanggal_laporan`, `created_at`) VALUES
(1, 17, 'hilang', 50000.00, 'buku hilang ketinggalan di angkutan umum', '2025-12-17', '2025-12-17 13:04:30'),
(2, 13, 'hilang', 50000.00, 'buku hilangterkena banjir', '2025-12-18', '2025-12-18 03:31:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `buku_kategori`
--

CREATE TABLE `buku_kategori` (
  `id` int(11) NOT NULL,
  `isbn` varchar(20) NOT NULL,
  `id_kategori` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `buku_kategori`
--

INSERT INTO `buku_kategori` (`id`, `isbn`, `id_kategori`, `created_at`) VALUES
(3, '978-602-4567-89-0', 3, '2025-11-25 04:20:09'),
(4, '978-602-5678-90-1', 2, '2025-11-25 04:20:09'),
(5, '978-602-6789-01-2', 4, '2025-11-25 04:20:09'),
(6, '978-602-8901-23-4', 2, '2025-11-25 04:20:09'),
(7, '978-602-2345-67-8', 1, '2025-12-01 05:07:22'),
(9, '978-149-1901-42-7', 4, '2025-12-05 12:00:32'),
(10, '978-602-3456-78-9', 2, '2025-12-17 01:44:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Database & Backend', 'Buku tentang sistem database, MySQL, dan backend development', '2025-11-25 04:20:09'),
(2, 'Web Development', 'Buku tentang web development, framework, dan teknologi web', '2025-11-25 04:20:09'),
(3, 'Programming & Algorithm', 'Buku tentang pemrograman umum, algoritma, dan struktur data', '2025-11-25 04:20:09'),
(4, 'Data Science', 'Buku tentang data science, machine learning, dan analisis data', '2025-11-25 04:20:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'Tipe target: buku, user, peminjaman, dll',
  `target_id` varchar(100) DEFAULT NULL COMMENT 'ID dari target yang diubah',
  `booking_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `user_id`, `username`, `nama`, `role`, `action`, `description`, `target_type`, `target_id`, `booking_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0', '2025-10-07 13:45:39'),
(2, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_BUKU', 'Menambahkan buku: Pemrograman PHP', 'buku', '978-602-1234-56-7', NULL, '127.0.0.1', 'Mozilla/5.0', '2025-10-07 13:45:39'),
(3, 5, 'Rizzx', 'Muhammad Fariz Setiawan', 'petugas', 'LOGIN', 'User Rizzx logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0', '2025-10-07 13:45:39'),
(4, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-07 13:46:44'),
(5, 4, 'admin', 'Administrator Sistem', 'admin', 'CLEANUP_LOGS', 'Deleted 0 old log entries (older than 90 days)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-07 13:47:52'),
(6, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:07:31'),
(7, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:12:15'),
(8, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:16:24'),
(9, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:29:36'),
(10, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'BACKUP_DATABASE', 'Backup database dibuat: perpustakaan_backup_2025-10-08_11-39-24.sql (Type: structure_data, Size: 15.02 KB)', 'backup', 'perpustakaan_backup_2025-10-08_11-39-24.sql', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:39:24'),
(11, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'EXPORT_LAPORAN', 'Export laporan peminjaman', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 04:47:30'),
(12, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 06:50:37'),
(13, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 07:00:16'),
(14, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 13:49:15'),
(15, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 13:50:01'),
(16, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Framework Laravel 10\' oleh Muhammad Farid Setiawan (NIK: 3578141604090002). Kembali: 22/10/2025', 'peminjaman', '6', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 13:50:24'),
(17, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-08 13:53:32'),
(18, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-10 14:58:06'),
(19, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-10 15:22:19'),
(20, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 05:32:54'),
(21, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 05:53:41'),
(22, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_BUKU', 'Buku dihapus: Machine Learning Praktis (ISBN: 978-602-7890-12-3)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 06:03:42'),
(23, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 11:31:08'),
(24, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 12:20:30'),
(25, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_BUKU', 'Buku dihapus: Pemrograman PHP untuk Pemula (ISBN: 978-602-1234-56-7)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-11 12:34:05'),
(26, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-12 09:47:36'),
(27, 4, 'admin', 'Administrator Sistem', 'admin', 'CLEAR_CACHE', 'Cleared 17 old session files (6.19 KB)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-12 10:12:13'),
(28, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 00:26:22'),
(29, 4, 'admin', 'Administrator Sistem', 'admin', 'CLEAR_CACHE', '2 files deleted, 300 B freed', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 00:27:22'),
(30, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 01:47:57'),
(31, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 01:51:58'),
(32, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 01:52:43'),
(33, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:04:51'),
(34, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:05:09'),
(35, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:05:31'),
(36, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 02:06:12'),
(37, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:06:28'),
(38, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:06:45'),
(39, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:15:45'),
(40, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:16:04'),
(41, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:16:21'),
(42, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:17:00'),
(43, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:17:57'),
(44, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:18:31'),
(45, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/17.5 Mobile/15A5370a Safari/602.1', '2025-10-13 02:19:22'),
(46, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-13 02:21:08'),
(47, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:03:06'),
(48, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:11:04'),
(49, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:15:01'),
(50, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-17 00:05:52'),
(51, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-17 00:11:37'),
(52, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 01:27:28'),
(53, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 13:57:13'),
(54, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-26 13:58:45'),
(55, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 01:31:30'),
(56, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 01:32:07'),
(57, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 02:03:08'),
(58, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 02:04:11'),
(59, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 02:05:53'),
(60, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:10:48'),
(61, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:16:56'),
(62, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:18:27'),
(63, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_USER', 'Menambahkan user baru: lunaris (Poetry Lunaris Azka) dengan role anggota', 'user', '9', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:24:25'),
(64, 9, 'lunaris', 'Poetry Lunaris Azka', 'anggota', 'LOGIN', 'User lunaris logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 05:25:27'),
(65, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-31 00:22:59'),
(66, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_BUKU', 'Export data buku', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-31 00:23:24'),
(67, 4, 'admin', 'Administrator Sistem', 'admin', 'CLEANUP_LOGS', 'Deleted 0 old log entries (older than 30 days)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-31 00:24:28'),
(68, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-31 01:31:52'),
(69, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-11 02:21:42'),
(70, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 03:37:32'),
(71, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 03:38:58'),
(72, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/17.5 Mobile/15A5370a Safari/602.1', '2025-11-18 03:40:58'),
(73, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 03:43:44'),
(74, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 01:32:59'),
(75, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 01:39:28'),
(76, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 01:40:23'),
(77, 4, 'admin', 'Administrator Sistem', 'admin', 'BACKUP_DATABASE', 'Backup database dibuat: perpustakaan_backup_2025-11-25_08-59-57.sql (Type: structure_data, Size: 32.17 KB)', 'backup', 'perpustakaan_backup_2025-11-25_08-59-57.sql', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 01:59:57'),
(78, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 34 hari terlambat - Denda: Rp 34.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-11-25 02:15:26'),
(79, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'REMINDER_DENDA', 'Reminder pembayaran denda terkirim via email - Total: Rp 34.000 untuk 1 peminjaman', 'denda', '3578141604090002', NULL, 'CRON_JOB', 'Fine Reminder System', '2025-11-25 02:32:33'),
(80, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test reminder denda - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 02:32:33'),
(81, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 03:24:50'),
(82, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'REMINDER_DENDA', 'Reminder pembayaran denda terkirim via email - Total: Rp 34.000 untuk 1 peminjaman', 'denda', '3578141604090002', NULL, 'CRON_JOB', 'Fine Reminder System', '2025-11-25 03:25:38'),
(83, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test reminder denda - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 03:25:38'),
(84, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGOUT', 'User petugas1 logged out', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 04:36:44'),
(85, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 04:36:54'),
(86, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-28 11:52:48'),
(87, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 07:51:08'),
(88, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 07:53:35'),
(89, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 07:55:17'),
(90, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_LAPORAN', 'Export laporan peminjaman ke Excel', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 07:57:24'),
(91, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 08:00:46'),
(92, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 08:04:48'),
(93, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 08:46:47'),
(94, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 04:19:36'),
(95, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_BUKU', 'Buku diupdate: Database MySQL Lanjutan (ISBN: 978-602-2345-67-8)', 'buku', '978-602-2345-67-8', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 05:07:22'),
(96, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 05:30:33'),
(97, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 05:49:51'),
(98, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 06:00:30'),
(99, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 06:08:04'),
(100, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 40 hari terlambat - Denda: Rp 40.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-01 06:20:23'),
(101, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 06:20:23'),
(102, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 06:34:31'),
(103, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:02:18'),
(104, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 40 hari terlambat - Denda: Rp 40.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-01 07:09:56'),
(105, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:09:56'),
(106, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:22:56'),
(107, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_LAPORAN', 'Export laporan peminjaman ke Excel', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:23:43'),
(108, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 40 hari terlambat - Denda: Rp 40.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-01 07:38:48'),
(109, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:38:48'),
(110, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGOUT', 'User Riddz logged out', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:39:21'),
(111, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: lunaris (Role: anggota)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:40:18'),
(112, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:40:56'),
(113, 9, 'lunaris', 'Poetry Lunaris Azka', 'anggota', 'LOGIN', 'User lunaris logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:41:10'),
(114, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 07:48:43'),
(115, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 08:55:29'),
(116, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:16:07'),
(117, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/17.5 Mobile/15A5370a Safari/602.1', '2025-12-01 09:38:55'),
(118, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:40:10'),
(119, 8, 'Faat', 'Faathirta Tri Tedsa', 'petugas', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-01 09:43:11'),
(120, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:23:06'),
(121, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:31:30'),
(122, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_LAPORAN', 'Export laporan peminjaman ke Excel', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:41:49'),
(123, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_LAPORAN', 'Export laporan peminjaman ke Excel', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:41:52'),
(124, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:44:33'),
(125, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:50:48'),
(126, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:53:12'),
(127, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 05:54:12'),
(128, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 06:28:05'),
(129, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Kembali: 16/12/2025', 'peminjaman', '7', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 06:34:59'),
(130, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Python untuk Data Science\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Kembali: 16/12/2025', 'peminjaman', '8', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 06:41:03'),
(131, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Python untuk Data Science\' oleh Faathirta Tri Tedsa (Tepat waktu)', 'peminjaman', '8', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 06:42:08'),
(132, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (Tepat waktu)', 'peminjaman', '7', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 06:42:38'),
(133, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Kembali: 16/12/2025', 'peminjaman', '9', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 07:12:30'),
(134, 4, 'admin', 'Administrator Sistem', 'admin', 'EXPORT_LAPORAN', 'Export laporan peminjaman ke Excel', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 07:24:47'),
(135, 4, 'admin', 'Administrator Sistem', 'admin', 'PEMINJAMAN', 'Peminjaman buku \'Python untuk Data Science\' oleh Poetry Lunaris Azka (NIK: 3578266404090004). Stok awal: 3, stok akhir: 2. Kembali: 16/12/2025', 'peminjaman', '10', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 07:37:15'),
(136, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Python untuk Data Science\' oleh Poetry Lunaris Azka (Tepat waktu)', 'peminjaman', '10', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 07:42:58'),
(137, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (Tepat waktu)', 'peminjaman', '9', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 08:46:18'),
(138, 4, 'admin', 'Administrator Sistem', 'admin', 'PEMINJAMAN', 'Peminjaman buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Stok awal: 2, stok akhir: 1. Kembali: 16/12/2025', 'peminjaman', '11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:00:39'),
(139, 4, 'admin', 'Administrator Sistem', 'admin', 'PENGEMBALIAN', 'Pengembalian buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa', 'peminjaman', '11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:01:39'),
(140, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 13:06:17'),
(141, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 13:31:48'),
(142, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 13:45:36'),
(143, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 13:46:33'),
(144, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_ANGGOTA', 'Anggota Budi Santoso (NIK: 3456789012345678) dihapus', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 14:24:54'),
(145, 4, 'admin', 'Administrator Sistem', 'admin', 'PEMINJAMAN', 'Peminjaman buku \'Framework Laravel 10\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Stok awal: 1, stok akhir: 0. Kembali: 16/12/2025', 'peminjaman', '12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 14:32:29'),
(146, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 14:33:35'),
(147, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Framework Laravel 10\' oleh Faathirta Tri Tedsa (Tepat waktu). Stok total: 1, stok tersedia setelah: 1', 'peminjaman', '12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 14:33:54'),
(148, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 15:02:36'),
(149, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_ANGGOTA', 'Anggota Ahmad Yusuf (NIK: 1234567890123456) dihapus', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 15:16:05'),
(150, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_ANGGOTA', 'Anggota Andi Wijaya (NIK: 5678901234567890) dihapus', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 15:16:13'),
(151, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 08:12:39'),
(152, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_PENERBIT', 'Menambahkan penerbit: O’Reilly Media', 'penerbit', '5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 08:14:27'),
(153, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_BUKU', 'Menambahkan buku: Designing Data-Intensive Applications oleh Martin Kleppmann (Penerbit: O’Reilly Media) - Stok Total: 1, Stok Tersedia: 1', 'buku', '978-144-9373-32-0', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 08:15:39'),
(154, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_BUKU', 'Buku dihapus: Designing Data-Intensive Applications (ISBN: 978-144-9373-32-0)', 'buku', '978-144-9373-32-0', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 08:19:49'),
(155, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 12:12:09'),
(156, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: zena (Role: anggota)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 13:01:24'),
(157, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 13:10:25'),
(158, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 13:11:06'),
(159, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 14:34:12'),
(160, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 14:34:30'),
(161, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 14:39:55'),
(162, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 14:47:15'),
(163, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 16:06:00'),
(164, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 00:59:43'),
(165, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 01:03:39'),
(166, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 01:07:18'),
(167, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 01:29:29'),
(168, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: Rizzx (Role: petugas)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 02:52:17'),
(169, 5, 'Rizzx', 'Muhammad Fariz Setiawan', 'petugas', 'LOGIN', 'User Rizzx logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 02:52:34'),
(170, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 03:56:17'),
(171, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 04:04:29'),
(172, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 04:21:34'),
(173, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 05:23:28'),
(174, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 05:24:53'),
(175, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 05:46:27'),
(176, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 07:07:55'),
(177, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:22:10'),
(178, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:25:49'),
(179, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:51:49'),
(180, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:52:20'),
(181, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:52:25'),
(182, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test reminder jatuh tempo - 0 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:59:26'),
(183, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 43 hari terlambat - Denda: Rp 43.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-04 08:59:47'),
(184, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:59:47'),
(185, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 09:04:26'),
(186, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 09:05:27'),
(187, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 09:10:22'),
(188, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 09:18:02'),
(189, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 09:32:37');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `username`, `nama`, `role`, `action`, `description`, `target_type`, `target_id`, `booking_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(190, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 10:19:27'),
(191, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 10:59:48'),
(192, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 11:00:15'),
(193, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'TAMBAH_BUKU_PETUGAS', 'Petugas menambahkan buku: Data Science from Scratch oleh Joel Grus (Penerbit: O’Reilly Media) - Kategori: Data Science - Stok Total: 5, Stok Tersedia: 5', 'buku', '978-149-1901-42-7', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 12:00:32'),
(194, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 12:32:07'),
(195, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 14:22:09'),
(196, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_USER', 'Menambahkan user baru: Kembar1212 (Fadhilah Nanda Kusuma) dengan role petugas', 'user', '10', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:27:44'),
(197, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(198, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(199, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(200, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(201, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(202, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(203, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(204, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(205, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(206, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(207, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(208, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:02'),
(209, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(210, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(211, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(212, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(213, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(214, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(215, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(216, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:03'),
(217, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(218, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(219, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(220, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(221, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(222, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(223, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(224, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(225, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(226, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(227, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(228, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(229, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(230, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(231, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:04'),
(232, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:05'),
(233, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:05'),
(234, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:05'),
(235, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:05'),
(236, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma diaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:05'),
(237, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: Kembar1212 (Role: petugas)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:28:19'),
(238, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(239, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(240, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(241, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(242, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(243, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(244, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(245, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(246, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(247, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(248, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(249, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(250, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(251, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(252, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(253, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(254, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(255, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(256, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(257, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:20'),
(258, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:21'),
(259, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:21'),
(260, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(261, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(262, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(263, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(264, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(265, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(266, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(267, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(268, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(269, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(270, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(271, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(272, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(273, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(274, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(275, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(276, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(277, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:22'),
(278, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(279, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(280, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(281, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(282, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(283, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(284, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(285, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:25'),
(286, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(287, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(288, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(289, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(290, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(291, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(292, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(293, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(294, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(295, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(296, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(297, 4, 'admin', 'Administrator Sistem', 'admin', 'TOGGLE_STATUS_PETUGAS', 'Status petugas Fadhilah Nanda Kusuma dinonaktifkan', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:29:26'),
(298, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: Kembar1212 (Role: petugas)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:30:01'),
(299, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:36:44'),
(300, 4, 'admin', 'Administrator Sistem', 'admin', 'HAPUS_ANGGOTA', 'Anggota Muhammad Fariz Setiawan (NIK: 3578141604090004) dihapus', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:37:21'),
(301, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: zena (Role: anggota)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-05 15:41:36'),
(302, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:42:11'),
(303, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:45:01'),
(304, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 01:32:42'),
(305, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 07:57:40'),
(306, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 08:05:08'),
(307, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 08:40:27'),
(308, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 08:44:36'),
(309, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 08:48:47'),
(310, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 08:50:37'),
(311, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 08:57:13'),
(312, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 09:05:39'),
(313, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 13:54:23'),
(314, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 46 hari terlambat - Denda: Rp 46.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-07 13:55:00'),
(315, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 1 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 13:55:00'),
(316, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 13:55:39'),
(317, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:16:16'),
(318, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:36:57'),
(319, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:37:17'),
(320, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:38:53'),
(321, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 14:39:41'),
(322, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:40:29'),
(323, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:46:51'),
(324, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:50:40'),
(325, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-07 14:56:44'),
(326, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-07 15:14:35'),
(327, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-08 00:05:15'),
(328, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 00:05:52'),
(329, 4, 'admin', 'Administrator Sistem', 'admin', 'PEMINJAMAN', 'Peminjaman buku \'Algoritma dan Struktur Data\' oleh Faathirta Tri Tedsa (NIK: 3578313012080001). Stok awal: 2, stok akhir: 1. Kembali: 22/12/2025', 'peminjaman', '13', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 00:40:24'),
(330, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-08 00:49:12'),
(331, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-08 01:28:31'),
(332, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-08 02:12:54'),
(333, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-08 03:59:23'),
(334, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 47 hari terlambat - Denda: Rp 47.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-08 14:24:57'),
(335, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-08 14:29:04'),
(336, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-09 00:43:20'),
(337, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 00:47:07'),
(338, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 00:47:39'),
(339, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 00:47:53'),
(340, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Data Science from Scratch\' oleh Poetry Lunaris Azka (NIK: 3578266404090004). Stok total: 5, stok tersedia setelah: 4. Kembali: 23/12/2025', 'peminjaman', '14', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 00:50:55'),
(341, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Data Science from Scratch\' oleh Poetry Lunaris Azka (Tepat waktu). Stok total: 5, stok tersedia setelah: 5', 'peminjaman', '14', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 00:54:27'),
(342, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-09 01:05:07'),
(343, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-09 01:10:18'),
(344, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-09 01:17:24'),
(345, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-09 01:18:57'),
(346, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-09 01:50:23'),
(347, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:09:43'),
(348, 4, 'admin', 'Administrator Sistem', 'admin', 'TEST_NOTIFIKASI', 'Manual test notifikasi keterlambatan - 0 email terkirim', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:10:41'),
(349, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:25:08'),
(350, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:40:18'),
(351, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:41:12'),
(352, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:41:29'),
(353, NULL, 'guest', 'Guest User', 'guest', 'REGISTER', 'New user registered: rendra', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:44:29'),
(354, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:46:25'),
(355, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:47:08'),
(356, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:48:10'),
(357, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Algoritma dan Struktur Data\' oleh rendra dwi santoso (NIK: 3578300311080001). Stok total: 2, stok tersedia setelah: 0. Kembali: 30/12/2025', 'peminjaman', '15', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:48:54'),
(358, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:49:53'),
(359, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:50:50'),
(360, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:51:23'),
(361, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:52:58'),
(362, 4, 'admin', 'Administrator Sistem', 'admin', 'TAMBAH_USER', 'Menambahkan user baru: K123 (Muhammad Faridz Setiawan) dengan role anggota', 'user', '12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 01:53:46'),
(363, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:04:05'),
(364, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Data Science from Scratch\' oleh rendra dwi santoso (NIK: 3578300311080001). Stok total: 5, stok tersedia setelah: 4. Kembali: 30/12/2025', 'peminjaman', '16', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:05:49'),
(365, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 55 hari terlambat - Denda: Rp 55.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-16 02:06:39'),
(366, NULL, 'guest', 'Guest User', 'guest', 'REGISTER', 'New user registered: adit', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:13:04');
INSERT INTO `log_aktivitas` (`id`, `user_id`, `username`, `nama`, `role`, `action`, `description`, `target_type`, `target_id`, `booking_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(367, 13, 'adit', 'aditya ramadhani', 'anggota', 'LOGIN', 'User adit logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:13:37'),
(368, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:15:34'),
(369, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Algoritma dan Struktur Data\' oleh rendra dwi santoso (Tepat waktu) + Denda kondisi: Rp 5.000. Stok total: 2, stok tersedia setelah: 1', 'peminjaman', '15', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 02:59:54'),
(370, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 03:13:52'),
(371, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 06:19:36'),
(372, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 06:22:14'),
(373, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: rendra (Role: anggota)', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 06:24:49'),
(374, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 06:24:57'),
(375, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 06:30:12'),
(376, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 11:39:23'),
(377, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 12:01:57'),
(378, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-16 12:03:35'),
(379, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 01:11:06'),
(380, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 01:28:58'),
(381, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 01:31:54'),
(382, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 01:43:50'),
(383, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_BUKU', 'Buku diupdate: Web Development Modern (ISBN: 978-602-3456-78-9) - Stok Total: 4, Stok Tersedia: 4', 'buku', '978-602-3456-78-9', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 01:44:42'),
(384, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:36:11'),
(385, 13, 'adit', 'aditya ramadhani', 'anggota', 'LOGIN', 'User adit logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:38:18'),
(386, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:39:07'),
(387, 4, 'admin', 'Administrator Sistem', 'admin', 'CHANGE_ROLE', 'Role user diubah: adit (anggota -> petugas)', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:40:26'),
(388, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: adit (Role: petugas)', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:40:26'),
(389, 13, 'adit', 'aditya ramadhani', 'petugas', 'LOGIN', 'User adit logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:40:48'),
(390, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:41:15'),
(391, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: adit (Role: petugas)', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:41:49'),
(392, 13, 'adit', 'aditya ramadhani', 'petugas', 'LOGIN', 'User adit logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:42:09'),
(393, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:42:28'),
(394, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: adit (Role: petugas)', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:42:53'),
(395, 4, 'admin', 'Administrator Sistem', 'admin', 'UPDATE_USER', 'User diupdate: adit (Role: petugas)', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 02:43:36'),
(396, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'NOTIFIKASI_KETERLAMBATAN', 'Notifikasi keterlambatan terkirim untuk buku \'Framework Laravel 10\' - 56 hari terlambat - Denda: Rp 56.000', 'peminjaman', '6', NULL, 'CRON_JOB', 'Auto Notification System', '2025-12-17 03:01:21'),
(397, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:16:30'),
(398, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:24:51'),
(399, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:28:46'),
(400, 4, 'admin', 'Administrator Sistem', 'admin', 'PENGEMBALIAN', 'Pengembalian buku \'Framework Laravel 10\' oleh Muhammad Farid Setiawan (56 hari terlambat - Denda: Rp 56.000) + Denda kondisi: Rp 5.000. Stok total: 2, stok tersedia setelah: 2', 'peminjaman', '6', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:30:26'),
(401, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:32:41'),
(402, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 03:51:20'),
(403, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 07:42:43'),
(404, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 08:06:46'),
(405, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 08:19:36'),
(406, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 08:54:39'),
(407, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 09:03:53'),
(408, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 09:04:37'),
(409, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:45:59'),
(410, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #1 untuk buku \'Algoritma dan Struktur Data\'', 'booking', '1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:46:27'),
(411, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:47:58'),
(412, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:50:57'),
(413, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:52:34'),
(414, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:17:12'),
(415, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:22:12'),
(416, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:22:14'),
(417, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'APPROVE_BOOKING_PETUGAS', 'Petugas approve booking #1 -> Peminjaman #17. Buku: \'Algoritma dan Struktur Data\' oleh Muhammad Farid Setiawan', 'booking', '1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:24:23'),
(418, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:31:11'),
(419, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:32:13'),
(420, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #2 untuk buku \'Data Science from Scratch\'', 'booking', '2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:32:48'),
(421, 4, 'admin', 'Administrator Sistem', 'admin', 'APPROVE_BOOKING', 'Booking #2 disetujui -> Peminjaman #18. Buku: \'Data Science from Scratch\' oleh rendra dwi santoso', 'booking', '2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:35:00'),
(422, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:52:42'),
(423, 12, 'K123', 'Muhammad Faridz Setiawan', 'anggota', 'LOGIN', 'User K123 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:55:16'),
(424, 12, 'K123', 'Muhammad Faridz Setiawan', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #3 untuk buku \'Framework Laravel 10\'', 'booking', '3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:55:47'),
(425, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:58:43'),
(426, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #4 untuk buku \'Framework Laravel 10\'', 'booking', '4', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 12:59:05'),
(427, 4, 'admin', 'Administrator Sistem', 'admin', 'EMAIL_CANCELLATION', 'Email pembatalan booking #4 dikirim ke muhammadfaridzsetiawan@gmail.com', 'booking', '4', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:01:55'),
(428, 4, 'admin', 'Administrator Sistem', 'admin', 'CANCEL_BOOKING', 'Booking #4 dibatalkan oleh admin. Alasan: karena masih dalam suspend setelah melakukan keterlambatan pengembalian buku dalam waktu yang lama | Buku: \'Framework Laravel 10\' | Anggota: Muhammad Farid Setiawan', 'booking', '4', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:01:55'),
(429, 4, 'admin', 'Administrator Sistem', 'admin', 'LAPOR_BUKU_HILANG', 'Buku \'Algoritma dan Struktur Data\' dilaporkan hilang oleh Muhammad Farid Setiawan. Denda: Rp 50.000', 'buku_hilang', '1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:04:30'),
(430, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:05:54'),
(431, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:07:46'),
(432, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 13:22:42'),
(433, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 14:10:06'),
(434, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 14:10:25'),
(435, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:12:33'),
(436, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:19:38'),
(437, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:22:53'),
(438, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:23:27'),
(439, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:48:49'),
(440, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PENGEMBALIAN', 'Pengembalian buku \'Data Science from Scratch\' oleh rendra dwi santoso (Tepat waktu). Stok total: 5, stok tersedia setelah: 4', 'peminjaman', '18', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 00:49:43'),
(441, 4, 'admin', 'Administrator Sistem', 'admin', 'APPROVE_BOOKING', 'Booking #3 disetujui -> Peminjaman #19. Buku: \'Framework Laravel 10\' oleh Muhammad Faridz Setiawan', 'booking', '3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:03:29'),
(442, 4, 'admin', 'Administrator Sistem', 'admin', 'EMAIL_APPROVAL', 'Notifikasi approval booking dikirim ke p251474@gmail.com', 'booking', '3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:03:29'),
(443, 4, 'admin', 'Administrator Sistem', 'admin', 'BOOKING_SYNC_ON_RETURN', 'Booking #3 diubah status menjadi \'dibatalkan\' setelah pengembalian peminjaman #19', 'booking', '3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:04:32'),
(444, 4, 'admin', 'Administrator Sistem', 'admin', 'PENGEMBALIAN', 'Pengembalian buku \'Framework Laravel 10\' oleh Muhammad Faridz Setiawan (Tepat waktu). Stok total: 2, stok tersedia setelah: 2', 'peminjaman', '19', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:04:32'),
(445, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:17:21'),
(446, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:24:31'),
(447, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:45:29'),
(448, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 01:45:31'),
(449, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #5 untuk buku \'Algoritma dan Struktur Data\' - Posisi: 1', 'booking', '5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:16:57'),
(450, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #5 dibatalkan oleh anggota', 'booking', '5', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:17:56'),
(451, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #6 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '6', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:18:28'),
(452, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #6 dibatalkan oleh anggota', 'booking', '6', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:26:50'),
(453, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #7 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '7', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:27:07'),
(454, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #7 dibatalkan oleh anggota', 'booking', '7', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:28:37'),
(455, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #8 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:28:52'),
(456, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #8 dibatalkan oleh anggota', 'booking', '8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:29:08'),
(457, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #9 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '9', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:29:36'),
(458, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #9 dibatalkan oleh anggota', 'booking', '9', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:29:51'),
(459, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #10 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '10', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:36:49'),
(460, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #10 dibatalkan oleh anggota', 'booking', '10', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:37:10'),
(461, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #11 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '11', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:37:39'),
(462, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #11 dibatalkan oleh anggota', 'booking', '11', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:42:19'),
(463, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #12 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '12', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:42:37'),
(464, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #12 dibatalkan oleh anggota', 'booking', '12', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:44:46'),
(465, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #13 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '13', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 02:45:00'),
(466, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CANCEL_BOOKING', 'Booking #13 dibatalkan oleh anggota', 'booking', '13', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 03:03:39'),
(467, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #14 untuk buku \'Algoritma dan Struktur Data\' - Posisi: 1', 'booking', '14', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 03:13:11'),
(468, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #15 untuk buku \'Data Science from Scratch\' - Posisi: 1', 'booking', '15', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 03:29:35'),
(469, 4, 'admin', 'Administrator Sistem', 'admin', 'LAPOR_BUKU_HILANG', 'Buku \'Algoritma dan Struktur Data\' dilaporkan hilang oleh Faathirta Tri Tedsa. Denda: Rp 50.000', 'buku_hilang', '2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 03:31:01'),
(470, 8, 'Faat', 'Faathirta Tri Tedsa', 'anggota', 'LOGIN', 'User Faat logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 03:31:24'),
(471, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-18 05:39:54'),
(472, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 13:50:37'),
(473, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-21 13:51:11'),
(474, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 04:49:46'),
(475, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 04:51:04'),
(476, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CANCEL_BOOKING', 'Booking #15 dibatalkan oleh anggota', 'booking', '15', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 04:51:31'),
(477, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'CANCEL_BOOKING', 'Booking #14 dibatalkan oleh anggota', 'booking', '14', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 04:51:37'),
(478, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 04:58:54'),
(479, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 05:24:48'),
(480, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 05:25:15'),
(481, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 07:42:34'),
(482, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'PEMINJAMAN', 'Peminjaman buku \'Data Science from Scratch\' oleh faizuz (NIK: 3578141604090010). Stok total: 5, stok tersedia setelah: 3. Kembali: 20/01/2026', 'peminjaman', '20', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 07:43:03'),
(483, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 13:28:53'),
(484, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 14:32:28'),
(485, 7, 'Riddz', 'Muhammad Farid Setiawan', 'anggota', 'LOGIN', 'User Riddz logged in', NULL, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 14:33:16'),
(486, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'LOGIN', 'User petugas1 logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 15:12:30'),
(487, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'LOGIN', 'User rendra logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 15:13:24'),
(488, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #16 untuk buku \'Framework Laravel 10\' - Posisi: 1', 'booking', '16', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 15:13:58'),
(489, 2, 'petugas1', 'Petugas Perpustakaan', 'petugas', 'APPROVE_BOOKING_PETUGAS', 'Petugas approve booking #16 -> Peminjaman #21. Buku: \'Framework Laravel 10\' oleh rendra dwi santoso', 'booking', '16', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 15:14:12'),
(490, 11, 'rendra', 'rendra dwi santoso', 'anggota', 'CREATE_BOOKING', 'Booking baru dibuat: #17 untuk buku \'Python untuk Data Science\' - Posisi: 1', 'booking', '17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-06 15:19:04'),
(491, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 15:23:28'),
(492, 4, 'admin', 'Administrator Sistem', 'admin', 'LOGIN', 'User admin logged in', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 16:09:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `otp_code`, `expires_at`, `is_used`, `created_at`) VALUES
(1, 'muhammadfaridzsetiawan@gmail.com', '003124', '2025-09-30 05:19:38', 1, '2025-09-30 03:04:38'),
(2, 'fathiirtatri.3@gmail.com', '684773', '2025-09-30 05:42:39', 1, '2025-09-30 03:27:39'),
(3, 'fathiirtatri.3@gmail.com', '409820', '2025-09-30 05:42:44', 0, '2025-09-30 03:27:44'),
(4, 'fathiirtatri.3@gmail.com', '229435', '2025-09-30 05:42:50', 0, '2025-09-30 03:27:50'),
(5, 'fathiirtatri.3@gmail.com', '787391', '2025-09-30 05:42:55', 0, '2025-09-30 03:27:55'),
(6, 'fathiirtatri.3@gmail.com', '045279', '2025-09-30 05:43:00', 0, '2025-09-30 03:28:00'),
(7, 'fathiirtatri.3@gmail.com', '525693', '2025-09-30 05:43:05', 0, '2025-09-30 03:28:05'),
(8, 'fathiirtatri.3@gmail.com', '369757', '2025-09-30 05:43:11', 0, '2025-09-30 03:28:11'),
(9, 'muhammadfaridzsetiawan@gmail.com', '711641', '2025-10-01 08:02:14', 1, '2025-10-01 05:47:14'),
(10, 'muhammadfaridzsetiawan@gmail.com', '525000', '2025-10-06 06:53:31', 0, '2025-10-06 04:38:31'),
(11, 'muhammadfaridzsetiawan@gmail.com', '930618', '2025-10-06 06:56:15', 1, '2025-10-06 04:41:15'),
(12, 'muhammadfaridzsetiawan@gmail.com', '153845', '2025-10-06 06:57:34', 1, '2025-10-06 04:42:34'),
(13, 'muhammadfaridzsetiawan@gmail.com', '842288', '2025-10-27 06:30:34', 1, '2025-10-27 05:15:34'),
(14, 'fathiirtatri.3@gmail.com', '754607', '2025-12-09 02:23:16', 1, '2025-12-09 01:08:16'),
(15, 'rendradwisantoso03@gmail.com', '476322', '2025-12-16 02:59:42', 1, '2025-12-16 01:44:42'),
(16, 'rendradwisantoso03@gmail.com', '358980', '2025-12-16 02:59:46', 0, '2025-12-16 01:44:46'),
(17, 'aditr7850@gmail.com', '759978', '2025-12-17 03:52:11', 1, '2025-12-17 02:37:11'),
(18, 'muhammadfaridzsetiawan@gmail.com', '557124', '2025-12-21 15:07:28', 1, '2025-12-21 13:52:28'),
(19, 'fathiirtatri.3@gmail.com', '461148', '2026-01-06 15:45:03', 1, '2026-01-06 14:30:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` int(11) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `isbn` varchar(20) NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `status` enum('dipinjam','dikembalikan') DEFAULT 'dipinjam',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id_peminjaman`, `nik`, `isbn`, `tanggal_pinjam`, `tanggal_kembali`, `status`, `created_at`) VALUES
(6, '3578141604090002', '978-602-8901-23-4', '2025-10-08', '2025-10-22', 'dikembalikan', '2025-10-08 13:50:24'),
(7, '3578313012080001', '978-602-4567-89-0', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 06:34:59'),
(8, '3578313012080001', '978-602-6789-01-2', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 06:41:03'),
(9, '3578313012080001', '978-602-4567-89-0', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 07:12:30'),
(10, '3578266404090004', '978-602-6789-01-2', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 07:37:15'),
(11, '3578313012080001', '978-602-4567-89-0', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 09:00:39'),
(12, '3578313012080001', '978-602-8901-23-4', '2025-12-02', '2025-12-16', 'dikembalikan', '2025-12-02 14:32:29'),
(13, '3578313012080001', '978-602-4567-89-0', '2025-12-08', '2025-12-22', 'dikembalikan', '2025-12-08 00:40:24'),
(14, '3578266404090004', '978-149-1901-42-7', '2025-12-09', '2025-12-23', 'dikembalikan', '2025-12-09 00:50:55'),
(15, '3578300311080001', '978-602-4567-89-0', '2025-12-16', '2025-12-30', 'dikembalikan', '2025-12-16 01:48:54'),
(16, '3578300311080001', '978-149-1901-42-7', '2025-12-16', '2025-12-30', 'dipinjam', '2025-12-16 02:05:49'),
(17, '3578141604090002', '978-602-4567-89-0', '2025-12-17', '2025-12-31', 'dikembalikan', '2025-12-17 12:24:23'),
(18, '3578300311080001', '978-149-1901-42-7', '2025-12-17', '2025-12-31', 'dikembalikan', '2025-12-17 12:35:00'),
(19, '3578141604090006', '978-602-8901-23-4', '2025-12-18', '2026-01-01', 'dikembalikan', '2025-12-18 01:03:29'),
(20, '3578141604090010', '978-149-1901-42-7', '2026-01-06', '2026-01-20', 'dipinjam', '2026-01-06 07:43:03'),
(21, '3578300311080001', '978-602-8901-23-4', '2026-01-06', '2026-01-20', 'dipinjam', '2026-01-06 15:14:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penerbit`
--

CREATE TABLE `penerbit` (
  `id_penerbit` int(11) NOT NULL,
  `nama_penerbit` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penerbit`
--

INSERT INTO `penerbit` (`id_penerbit`, `nama_penerbit`, `alamat`, `telepon`, `email`, `created_at`) VALUES
(1, 'Andi Publisher', NULL, NULL, NULL, '2025-11-25 03:55:59'),
(2, 'Elex Media', NULL, NULL, NULL, '2025-11-25 03:55:59'),
(3, 'Graha Ilmu', NULL, NULL, NULL, '2025-11-25 03:55:59'),
(4, 'Informatika', NULL, NULL, NULL, '2025-11-25 03:55:59'),
(5, 'O’Reilly Media', '', '', '', '2025-12-03 08:14:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'max_pinjam_hari', '14', 'Maksimal hari meminjam buku', '2025-09-25 08:39:46'),
(2, 'denda_per_hari', '1000', 'Denda per hari keterlambatan (Rupiah)', '2025-09-25 08:39:46'),
(3, 'max_buku_pinjam', '3', 'Maksimal buku yang bisa dipinjam per anggota', '2025-09-25 08:39:46'),
(4, 'nama_perpustakaan', 'Perpustakaan Nusantara', 'Nama perpustakaan', '2025-09-25 08:39:46'),
(5, 'alamat_perpustakaan', 'Surabaya, East Java, ID', 'Alamat perpustakaan', '2025-09-25 08:39:46'),
(6, 'jam_buka', '08:00', 'Jam buka perpustakaan', '2025-09-25 08:39:46'),
(7, 'jam_tutup', '16:00', 'Jam tutup perpustakaan', '2025-09-25 08:39:46'),
(8, 'email_perpustakaan', 'info@perpustakaan.com', 'Email kontak perpustakaan', '2025-09-25 08:39:46'),
(9, 'telepon_perpustakaan', '031-1234567', 'Telepon perpustakaan', '2025-09-25 08:39:46'),
(10, 'max_booking_days', '3', 'Maksimal hari booking buku (hari)', '2025-12-17 07:21:58'),
(11, 'booking_expire_days', '2', 'Masa berlaku booking (hari)', '2025-12-17 07:21:58'),
(12, 'denda_hilang', '50000', 'Denda buku hilang (Rupiah)', '2025-12-17 07:21:58'),
(13, 'denda_rusak_parah', '25000', 'Denda buku rusak parah (Rupiah)', '2025-12-17 07:21:58'),
(14, 'enable_booking', '1', 'Aktifkan fitur booking (1=ya, 0=tidak)', '2025-12-17 07:21:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_pengembalian` int(11) NOT NULL,
  `id_peminjaman` int(11) NOT NULL,
  `tanggal_pengembalian_aktual` date NOT NULL,
  `kondisi_buku` enum('baik','rusak_ringan','rusak_berat') DEFAULT 'baik',
  `denda` decimal(10,2) DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengembalian`
--

INSERT INTO `pengembalian` (`id_pengembalian`, `id_peminjaman`, `tanggal_pengembalian_aktual`, `kondisi_buku`, `denda`, `catatan`, `created_at`) VALUES
(3, 8, '2025-12-02', 'baik', 0.00, '', '2025-12-02 06:42:08'),
(4, 7, '2025-12-02', 'baik', 0.00, '', '2025-12-02 06:42:38'),
(5, 10, '2025-12-02', 'baik', 0.00, '', '2025-12-02 07:42:58'),
(6, 9, '2025-12-02', 'baik', 0.00, '', '2025-12-02 08:46:18'),
(7, 11, '2025-12-02', 'baik', 0.00, NULL, '2025-12-02 09:01:39'),
(8, 12, '2025-12-02', 'baik', 0.00, '', '2025-12-02 14:33:54'),
(9, 14, '2025-12-09', 'baik', 0.00, '', '2025-12-09 00:54:27'),
(10, 15, '2025-12-16', 'rusak_ringan', 5000.00, 'Kondisi buku rusak ringan (denda Rp 5.000)\nrusak ringan di halaman 40,90, dan daftar isi', '2025-12-16 02:59:54'),
(11, 6, '2025-12-17', 'rusak_ringan', 61000.00, 'Kondisi buku rusak ringan (denda Rp 5.000)\nbuku dikembalikan terlambat, dengan kondisi rusak ringan di cover dan daftar isi', '2025-12-17 03:30:26'),
(12, 17, '2025-12-17', 'rusak_ringan', 50000.00, 'Buku hilang - buku hilang ketinggalan di angkutan umum', '2025-12-17 13:04:30'),
(13, 18, '2025-12-18', 'baik', 0.00, 'baik', '2025-12-18 00:49:43'),
(14, 19, '2025-12-18', 'baik', 0.00, 'buku keadaan baik dan tepat waktu', '2025-12-18 01:04:32'),
(15, 13, '2025-12-18', 'rusak_ringan', 50000.00, 'Buku hilang - buku hilangterkena banjir', '2025-12-18 03:31:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rak`
--

CREATE TABLE `rak` (
  `id_rak` int(11) NOT NULL,
  `kode_rak` varchar(20) NOT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `kapasitas` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rak`
--

INSERT INTO `rak` (`id_rak`, `kode_rak`, `lokasi`, `kapasitas`, `created_at`) VALUES
(1, 'A1', 'Rak 1 - Sudut Utara Timur', 50, '2025-11-25 04:20:09'),
(2, 'A2', 'Rak 2 - Sudut Utara Barat', 50, '2025-11-25 04:20:09'),
(3, 'B1', 'Rak 3 - Tengah Timur', 50, '2025-11-25 04:20:09'),
(4, 'B2', 'Rak 4 - Tengah Barat', 50, '2025-11-25 04:20:09'),
(5, 'C1', 'Rak 5 - Sudut Selatan Timur', 50, '2025-11-25 04:20:09'),
(6, 'C2', 'Rak 6 - Sudut Selatan Barat', 50, '2025-11-25 04:20:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','petugas','anggota') NOT NULL DEFAULT 'anggota',
  `promoted_by` int(11) DEFAULT NULL,
  `promoted_at` timestamp NULL DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `email`, `role`, `promoted_by`, `promoted_at`, `status`, `created_at`, `updated_at`) VALUES
(2, 'petugas1', '$2y$10$a5c8vU8dOIZw05OnZ92B/Ohd4ncMqYXVqTDbh3SkS8H/4aht/KcBC', 'Petugas Perpustakaan', 'petugas@perpustakaan.com', 'petugas', NULL, NULL, 'aktif', '2025-09-25 08:39:46', '2025-09-26 12:32:52'),
(3, 'anggota1', '$2y$10$a5c8vU8dOIZw05OnZ92B/Ohd4ncMqYXVqTDbh3SkS8H/4aht/KcBC', 'Ahmad Yusuf', 'ahmad@email.com', 'anggota', NULL, NULL, 'aktif', '2025-09-25 08:39:46', '2025-09-26 12:32:52'),
(4, 'admin', '$2y$10$a5c8vU8dOIZw05OnZ92B/Ohd4ncMqYXVqTDbh3SkS8H/4aht/KcBC', 'Administrator Sistem', 'admin@perpustakaan.com', 'admin', NULL, NULL, 'aktif', '2025-09-26 07:51:37', '2025-12-03 14:35:16'),
(5, 'Rizzx', '$2y$10$86dfl7W0jl/i0gqU/jOoNunNA.T9nhgZMWGRH5HbKmqk8y4wO5lA.', 'Muhammad Fariz Setiawan', 'muhammadfarizsetiawan1604@gmail.com', 'petugas', 4, '2025-09-30 05:30:52', 'aktif', '2025-09-29 10:25:44', '2025-12-04 02:52:17'),
(6, 'zena', '$2y$10$eegb4myAoaHK4qxF8O/dIOyd7xb6XzZMwmXXFJhvEn.uWAkShBW/q', 'faizuz', 'zuzizuz05@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-09-30 02:24:29', '2025-12-05 15:41:36'),
(7, 'Riddz', '$2y$10$dz98oTwH6zGxSzV1YzABHun7aCi5RR1bEvuwfU4OhXJS0nxm0Ws3C', 'Muhammad Farid Setiawan', 'muhammadfaridzsetiawan@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-09-30 02:29:27', '2025-09-30 03:05:55'),
(8, 'Faat', '$2y$10$dvLRujkIZzlYTOi0t4r.geVuYB5eYzuimBoqTkpvBb0r9O2wd2dPS', 'Faathirta Tri Tedsa', 'fathiirtatri.3@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-09-30 03:27:08', '2025-12-02 06:27:35'),
(9, 'lunaris', '$2y$10$7bn7brBnDOKBLIb8kSnQx.8F5eQVy5IiZxHeR1eBwQWjXFFQ6z4fu', 'Poetry Lunaris Azka', 'poetry2404@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-10-27 05:24:25', '2025-12-01 07:40:18'),
(10, 'Kembar1212', '$2y$10$Av0qnP8MVoInzDOwPgYsHet1bWojAD2WyxG8K..vMCGB5eXmFjAZ6', 'Fadhilah Nanda Kusuma', 'farizgans1010@gmail.com', 'petugas', NULL, NULL, 'aktif', '2025-12-05 15:27:44', '2025-12-05 15:30:01'),
(11, 'rendra', '$2y$10$RKT2LVdVPiw0jRaK.2uFruEnO59pzlk7QWrMGNRtIvE2M41rUlD0q', 'rendra dwi santoso', 'rendradwisantoso03@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-12-16 01:44:29', '2025-12-16 06:24:49'),
(12, 'K123', '$2y$10$G2sPFxPKgWzWS0o4GneKWOawSjB49uhdmxZK6oE2cJXXhxpwDVypO', 'Muhammad Faridz Setiawan', 'p251474@gmail.com', 'anggota', NULL, NULL, 'aktif', '2025-12-16 01:53:46', '2025-12-16 01:53:46'),
(13, 'adit', '$2y$10$Yiovt9E24ugSxm1en8QZ0e2hz6eKpzJaVONzA.FVVjyEx/yPw07ZW', 'aditya ramadhani', 'aditr7850@gmail.com', 'petugas', NULL, NULL, 'aktif', '2025-12-16 02:13:04', '2025-12-17 02:43:36');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`nik`),
  ADD KEY `idx_anggota_nama` (`nama`);

--
-- Indeks untuk tabel `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id_booking`),
  ADD KEY `idx_booking_status` (`status`),
  ADD KEY `idx_booking_expired` (`expired_at`),
  ADD KEY `idx_booking_nik` (`nik`),
  ADD KEY `idx_booking_isbn` (`isbn`);

--
-- Indeks untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD PRIMARY KEY (`isbn`),
  ADD KEY `idx_buku_status` (`status`),
  ADD KEY `fk_buku_penerbit` (`id_penerbit`),
  ADD KEY `fk_buku_rak` (`id_rak`);

--
-- Indeks untuk tabel `buku_hilang`
--
ALTER TABLE `buku_hilang`
  ADD PRIMARY KEY (`id_hilang`),
  ADD KEY `idx_hilang_peminjaman` (`id_peminjaman`),
  ADD KEY `idx_hilang_status` (`status`);

--
-- Indeks untuk tabel `buku_kategori`
--
ALTER TABLE `buku_kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_buku_kategori` (`isbn`,`id_kategori`),
  ADD KEY `fk_buku_kategori_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

--
-- Indeks untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_log_booking` (`booking_id`);

--
-- Indeks untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_otp` (`email`,`otp_code`);

--
-- Indeks untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `nik` (`nik`),
  ADD KEY `isbn` (`isbn`),
  ADD KEY `idx_peminjaman_status` (`status`),
  ADD KEY `idx_peminjaman_tanggal` (`tanggal_pinjam`);

--
-- Indeks untuk tabel `penerbit`
--
ALTER TABLE `penerbit`
  ADD PRIMARY KEY (`id_penerbit`),
  ADD UNIQUE KEY `nama_penerbit` (`nama_penerbit`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_pengembalian`),
  ADD UNIQUE KEY `unique_peminjaman` (`id_peminjaman`);

--
-- Indeks untuk tabel `rak`
--
ALTER TABLE `rak`
  ADD PRIMARY KEY (`id_rak`),
  ADD UNIQUE KEY `kode_rak` (`kode_rak`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_promoted_by` (`promoted_by`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `booking`
--
ALTER TABLE `booking`
  MODIFY `id_booking` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `buku_hilang`
--
ALTER TABLE `buku_hilang`
  MODIFY `id_hilang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `buku_kategori`
--
ALTER TABLE `buku_kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=493;

--
-- AUTO_INCREMENT untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_peminjaman` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `penerbit`
--
ALTER TABLE `penerbit`
  MODIFY `id_penerbit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_pengembalian` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `rak`
--
ALTER TABLE `rak`
  MODIFY `id_rak` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`nik`) REFERENCES `anggota` (`nik`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`isbn`) REFERENCES `buku` (`isbn`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `buku`
--
ALTER TABLE `buku`
  ADD CONSTRAINT `fk_buku_penerbit` FOREIGN KEY (`id_penerbit`) REFERENCES `penerbit` (`id_penerbit`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_buku_rak` FOREIGN KEY (`id_rak`) REFERENCES `rak` (`id_rak`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `buku_hilang`
--
ALTER TABLE `buku_hilang`
  ADD CONSTRAINT `buku_hilang_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `buku_kategori`
--
ALTER TABLE `buku_kategori`
  ADD CONSTRAINT `fk_buku_kategori_buku` FOREIGN KEY (`isbn`) REFERENCES `buku` (`isbn`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_buku_kategori_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`nik`) REFERENCES `anggota` (`nik`) ON DELETE CASCADE,
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`isbn`) REFERENCES `buku` (`isbn`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `fk_pengembalian_peminjaman` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
