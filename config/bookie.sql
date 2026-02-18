-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 18 Feb 2026 pada 10.44
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
-- Database: `bookie`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `alamat_pengiriman`
--

CREATE TABLE `alamat_pengiriman` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `alamat` text NOT NULL,
  `kota` varchar(100) NOT NULL,
  `kodepos` varchar(10) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `alamat_pengiriman`
--

INSERT INTO `alamat_pengiriman` (`id`, `transaksi_id`, `alamat`, `kota`, `kodepos`, `telepon`, `created_at`) VALUES
(1, 1, 'jalan', 'jalan', '11111', '0987666667', '2026-02-17 14:04:23'),
(2, 2, 'MW9W+8WM, Jl. Damai 2, RT.002/RW.003, Jatisari, Kec. Jatiasih, Kota Bks, Jawa Barat 17426', 'Bekasi', '17426', '085697011994', '2026-02-18 02:33:16'),
(3, 3, 'MW9W+8WM, Jl. Damai 2, RT.002/RW.003, Jatisari, Kec. Jatiasih, Kota Bks, Jawa Barat 17426', 'Bekasi', '17426', '085697011994', '2026-02-18 02:38:00'),
(4, 4, 'MW9W+8WM, Jl. Damai 2, RT.002/RW.003, Jatisari, Kec. Jatiasih, Kota Bks, Jawa Barat 17426', 'Bekasi', '17426', '085697011994', '2026-02-18 02:52:05'),
(5, 5, 'j', 'j', '9', '9', '2026-02-18 05:09:52'),
(6, 6, 'h', 'j', '8', '8', '2026-02-18 05:12:57'),
(7, 7, 'e', 'e', '3333', '56767554', '2026-02-18 05:28:49'),
(8, 8, 'tij', 'jsdaj', '2877', '098543', '2026-02-18 06:43:44'),
(9, 9, 'jalan raya kampung duren', 'jakarta selatan', '12345', '0812345678998', '2026-02-18 07:01:12'),
(10, 10, 'jalan nomor rumah', 'jakarta selatan', '1245', '081627268268', '2026-02-18 07:22:37'),
(11, 11, 'jalan damai', 'bekasi', '12345', '0989883838', '2026-02-18 08:08:31'),
(12, 12, 'jalan damai', 'bekasi', '12345', '0989883838', '2026-02-18 08:08:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `chat`
--

CREATE TABLE `chat` (
  `id` int(11) NOT NULL,
  `id_room` int(11) NOT NULL,
  `id_pengirim` int(11) NOT NULL,
  `penerima_id` int(11) DEFAULT NULL,
  `pesan` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dibaca` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `chat`
--

INSERT INTO `chat` (`id`, `id_room`, `id_pengirim`, `penerima_id`, `pesan`, `created_at`, `dibaca`) VALUES
(2, 1, 6, 5, 'tes', '2026-02-17 19:02:19', 1),
(3, 1, 6, 5, 'halo', '2026-02-17 19:07:40', 1),
(6, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006797\nDari: yukiio\nTotal: Rp 205.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 02:38:00', 0),
(7, 1, 6, 5, 'done', '2026-02-18 02:38:57', 1),
(9, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006364\nDari: yukiio\nTotal: Rp 119.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 02:52:05', 0),
(10, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 03:12:38', 1),
(11, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006753\nDari: yukiio\nTotal: Rp 55.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 05:09:52', 0),
(12, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 05:10:16', 1),
(13, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006513\nDari: yukiio\nTotal: Rp 119.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 05:12:57', 0),
(14, 1, 5, 6, '❌ *Pembayaran Anda DITOLAK*\n\n*Alasan Penolakan:*\naneh\n\nSilakan lakukan refund dengan klik tombol \'Minta Refund\' di halaman pesanan Anda.', '2026-02-18 05:21:37', 1),
(15, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 05:23:02', 1),
(16, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006283\nDari: yukiio\nTotal: Rp 55.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 05:28:49', 0),
(17, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 05:54:32', 1),
(18, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 05:57:06', 1),
(19, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006142\nDari: yukiio\nTotal: Rp 55.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 06:43:45', 0),
(20, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE*\n\nTerima kasih, pembayaran Anda sudah kami konfirmasi. Pesanan akan segera kami proses dan kirim. Mohon tunggu nomor resi pengiriman.', '2026-02-18 06:44:18', 1),
(21, 1, 5, 6, '❌ *Pembayaran Anda DITOLAK*\n\n*Alasan Penolakan:*\nTOLAK\n\nSilakan lakukan refund dengan klik tombol \'Minta Refund\' di halaman pesanan Anda.', '2026-02-18 06:47:01', 1),
(22, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006313\nDari: yukiio\nTotal: Rp 55.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 07:01:12', 0),
(23, 1, 5, 6, '✅ *Pembayaran Anda telah di-APPROVE dan PESANAN TELAH DIKIRIM*\n\n*Nomor Resi:* SCPT1234\n*Ekspedisi:* SiCepat\n\nTerima kasih telah berbelanja. Anda dapat melacak paket melalui website ekspedisi terkait.', '2026-02-18 07:01:45', 1),
(24, 1, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX20260218005006547\nDari: yukiio\nTotal: Rp 85.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 07:22:37', 0),
(25, 1, 5, 6, '❌ *Pembayaran Anda DITOLAK*\n\n*Alasan Penolakan:*\naneh\n\nSilakan upload ulang bukti transfer yang benar.', '2026-02-18 07:23:14', 1),
(26, 2, 999, 1001, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX2026021810011002150\nDari: yuuuuki\nTotal: Rp 70.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 08:08:31', 0),
(27, 3, 999, 5, '🛒 **PESANAN BARU** 🛒\n\nKode Transaksi: #TRX202602180051002853\nDari: yuuuuki\nTotal: Rp 85.000\nStatus: Menunggu Pembayaran\n\nSilakan cek detail pesanan di menu Transaksi.', '2026-02-18 08:08:31', 0),
(28, 3, 1002, 5, 'tes', '2026-02-18 08:10:05', 1),
(29, 2, 1002, 1001, 'tes', '2026-02-18 08:10:28', 1),
(30, 2, 1001, 1002, '✅ *Pembayaran Anda telah di-APPROVE dan PESANAN TELAH DIKIRIM*\n\n*Nomor Resi:* scpt12345\n*Ekspedisi:* SiCepat\n\nTerima kasih telah berbelanja. Anda dapat melacak paket melalui website ekspedisi terkait.', '2026-02-18 08:10:56', 1),
(33, 3, 5, 1002, '❌ *Pembayaran Anda DITOLAK*\n\n*Alasan Penolakan:*\nburem\n\nSilakan upload ulang bukti transfer yang benar.', '2026-02-18 08:14:06', 1),
(34, 3, 1002, 5, 'tes', '2026-02-18 08:29:07', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL,
  `id_pembeli` int(11) NOT NULL,
  `id_penjual` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `chat_rooms`
--

INSERT INTO `chat_rooms` (`id`, `id_pembeli`, `id_penjual`, `created_at`) VALUES
(1, 6, 5, '2026-02-17 14:04:23'),
(2, 1002, 1001, '2026-02-18 08:08:31'),
(3, 1002, 5, '2026-02-18 08:08:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `deskripsi`, `foto`) VALUES
(1, 'Novel', 'karya sastra berbentuk prosa naratif panjang yang menceritakan rangkaian kisah kehidupan seseorang beserta orang di sekelilingnya dengan menonjolkan watak dan sifat setiap pelaku', 'kategori_1771147106.jpg'),
(2, 'Komik', 'media komunikasi visual berupa rangkaian gambar tidak bergerak (statis) dan teks yang disusun berurutan membentuk alur cerita', 'kategori_1771147147.jpg'),
(3, 'Buku pelajaran sekolah (SD, SMP, SMA)', 'buku teks wajib berisi materi pembelajaran yang disusun berdasarkan kurikulum nasional untuk mencapai kompetensi dasar dan tujuan pendidikan tertentu', 'kategori_1771147332.jpg'),
(4, 'Buku kuliah', 'materi cetak atau digital (buku ajar, referensi, diktat) yang disusun berdasarkan kurikulum untuk mendukung pembelajaran, penelitian, dan pemahaman teori di tingkat perguruan tinggi.', 'kategori_1771147384.jpg'),
(5, 'Buku nonfiksi (motivasi, bisnis, self-help)', ' jenis buku yang isinya didasarkan pada fakta, pengalaman nyata, studi kasus, dan prinsip-prinsip psikologi atau manajemen, bertujuan untuk memberikan wawasan, panduan praktis, serta inspirasi kepada pembaca untuk meningkatkan kualitas hidup, kemampuan diri, atau kinerja bisnis. ', 'kategori_1771147452.jpg'),
(6, 'Buku anak', 'media bacaan, baik fiksi maupun nonfiksi, yang dirancang khusus dengan materi, bahasa, dan ilustrasi yang sesuai dengan tingkat kemampuan membaca serta perkembangan psikologis anak-anak, mulai dari usia prasekolah hingga sekolah dasar (sekitar usia 0–12 tahun)', 'kategori_1771147506.jpg'),
(7, 'Buku agama', 'karya tulis yang memuat ajaran, pedoman hidup, nilai-nilai spiritual, hukum (fikih), akidah, akhlak, serta sejarah yang bersumber dari kitab suci, hadits, atau pemikiran keagamaan', 'kategori_1771147614.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keranjang`
--

CREATE TABLE `keranjang` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `pesan` text NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `id_user`, `pesan`, `status`, `created_at`) VALUES
(1, 5, 'Pesanan baru dari yukiio - TRX20260217005006600 - Total: Rp 55.000', 'unread', '2026-02-17 14:04:23'),
(2, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #1', 'unread', '2026-02-17 18:34:47'),
(3, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #1', 'unread', '2026-02-17 18:35:01'),
(4, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #1', 'unread', '2026-02-17 18:35:48'),
(5, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #1', 'unread', '2026-02-17 18:36:52'),
(6, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #1. Silakan konfirmasi pembayaran.', 'unread', '2026-02-17 18:38:11'),
(7, 5, 'Pesan baru dari yukiio: tes...', 'unread', '2026-02-17 19:02:19'),
(8, 5, 'Pesan baru dari yukiio: halo...', 'unread', '2026-02-17 19:07:40'),
(9, 6, 'Status pesanan #1 berubah menjadi 📦 Diproses', 'unread', '2026-02-17 19:16:41'),
(10, 5, 'Pesanan baru dari yukiio - TRX20260218005006477 - Total: Rp 205.000', 'unread', '2026-02-18 02:33:16'),
(11, 5, 'Pesanan baru dari yukiio - TRX20260218005006797 - Total: Rp 205.000', 'unread', '2026-02-18 02:38:00'),
(12, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #3. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 02:38:40'),
(13, 5, 'Pesan baru dari yukiio: done...', 'unread', '2026-02-18 02:38:57'),
(14, 6, 'Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.', 'unread', '2026-02-18 02:39:51'),
(15, 5, 'Pesanan baru dari yukiio - TRX20260218005006364 - Total: Rp 119.000', 'unread', '2026-02-18 02:52:05'),
(16, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #4. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 03:11:57'),
(17, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 03:12:38'),
(18, 5, 'Pesanan baru dari yukiio - TRX20260218005006753 - Total: Rp 55.000', 'unread', '2026-02-18 05:09:52'),
(19, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #5. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 05:10:03'),
(20, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 05:10:16'),
(21, 5, 'Pesanan baru dari yukiio - TRX20260218005006513 - Total: Rp 119.000', 'unread', '2026-02-18 05:12:57'),
(22, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #6. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 05:13:17'),
(23, 6, 'Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.', 'unread', '2026-02-18 05:21:37'),
(24, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #2. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 05:22:41'),
(25, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 05:23:02'),
(26, 5, 'Pesanan baru dari yukiio - TRX20260218005006283 - Total: Rp 55.000', 'unread', '2026-02-18 05:28:49'),
(27, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #7. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 05:29:24'),
(28, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 05:54:32'),
(29, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 05:57:06'),
(30, 5, 'Pesanan baru dari yukiio - TRX20260218005006142 - Total: Rp 55.000', 'unread', '2026-02-18 06:43:45'),
(31, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #8. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 06:44:00'),
(32, 6, 'Pesanan Anda telah di-approve oleh penjual. Menunggu input resi pengiriman.', 'unread', '2026-02-18 06:44:18'),
(33, 6, 'Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.', 'unread', '2026-02-18 06:47:01'),
(34, 5, 'Pesanan baru dari yukiio - TRX20260218005006313 - Total: Rp 55.000', 'unread', '2026-02-18 07:01:12'),
(35, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #9. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 07:01:23'),
(36, 6, 'Pesanan Anda telah dikirim. Nomor resi: SCPT1234 (SiCepat)', 'unread', '2026-02-18 07:01:45'),
(37, 5, 'Pesanan baru dari yukiio - TRX20260218005006547 - Total: Rp 85.000', 'unread', '2026-02-18 07:22:37'),
(38, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #10. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 07:22:59'),
(39, 6, 'Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.', 'unread', '2026-02-18 07:23:14'),
(40, 1001, 'Pesanan baru dari yuuuuki - TRX2026021810011002150 - Total: Rp 70.000', 'unread', '2026-02-18 08:08:31'),
(41, 5, 'Pesanan baru dari yuuuuki - TRX202602180051002853 - Total: Rp 85.000', 'unread', '2026-02-18 08:08:31'),
(42, 1001, 'Pembeli telah mengupload bukti transfer untuk transaksi #11. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 08:08:57'),
(43, 5, 'Pesan baru dari yuuuuki: tes...', 'unread', '2026-02-18 08:10:05'),
(44, 1001, 'Pesan baru dari yuuuuki: tes...', 'unread', '2026-02-18 08:10:28'),
(45, 1002, 'Pesanan Anda telah dikirim. Nomor resi: scpt12345 (SiCepat)', 'unread', '2026-02-18 08:10:56'),
(46, 5, 'Pembeli telah mengupload bukti transfer untuk transaksi #12. Silakan konfirmasi pembayaran.', 'unread', '2026-02-18 08:12:39'),
(47, 1002, 'Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.', 'unread', '2026-02-18 08:14:06'),
(48, 5, 'Pesan baru dari yuuuuki: tes...', 'unread', '2026-02-18 08:29:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `id_penjual` int(11) NOT NULL,
  `nama_produk` varchar(150) NOT NULL,
  `pengarang` varchar(100) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `modal` int(11) DEFAULT NULL,
  `margin` int(11) DEFAULT NULL,
  `keuntungan` int(11) DEFAULT NULL,
  `stok` int(11) DEFAULT 0,
  `deskripsi` text DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `kategori_id`, `id_penjual`, `nama_produk`, `pengarang`, `isbn`, `harga`, `modal`, `margin`, `keuntungan`, `stok`, `deskripsi`, `gambar`, `created_at`) VALUES
(1, 1, 5, 'Bumi', 'Tere Liye', '9786020332956', 109000, 80000, 29000, 2900000, 98, 'Novel &amp;amp;quot;Bumi&amp;amp;quot; karya Tere Liye mengisahkan petualangan fantasi Raib, remaja 15 tahun yang bisa menghilang, bersama dua temannya, Seli (penjinak api) dan Ali (si jenius). Mereka menjelajahi dunia paralel, khususnya Klan Bulan, melawan Tamus yang jahat demi menyelamatkan dunia dari kekacauan. ', 'produk_1771156213_390.jpg', '2026-02-15 11:33:51'),
(2, 2, 5, 'Spy × Family vol 1', 'ENDOU TATSUYA', '9786230021312', 45000, 35000, 10000, 1000000, 93, 'Komik Spy x Family Volume 1 karya Tatsuya Endo (penerbit M&amp;amp;amp;C) memperkenalkan keluarga Forger: Twilight (agen rahasia), Yor (pembunuh profesional), dan Anya (pembaca pikiran) yang menyembunyikan identitas asli mereka', 'produk_1771156499_166.jpg', '2026-02-15 11:54:59'),
(3, 5, 5, 'filosofi terass', 'Henry Manampiring', '978-602-4125-18-9', 75000, 45000, 30000, 2850000, 94, 'Filosofi Teras karya Henry Manampiring adalah buku pengantar Stoikisme (filsafat Yunani-Romawi kuno) yang dirancang untuk mengatasi emosi negatif dan menciptakan ketenangan mental.', 'produk_1771157403_614.jpg', '2026-02-15 12:10:03'),
(5, 4, 1001, 'matematika', 'Henry Manampiring', '978-602-4125-18-9', 60000, 50000, 10000, 1000000, 99, 'buku matematika kelas 12', 'produk_1771401562_320.jpg', '2026-02-18 07:59:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk_gambar`
--

CREATE TABLE `produk_gambar` (
  `id` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `refund`
--

CREATE TABLE `refund` (
  `id` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `id_pembeli` int(11) NOT NULL,
  `id_penjual` int(11) NOT NULL,
  `alasan` text NOT NULL,
  `jumlah` int(11) NOT NULL,
  `bukti_refund` varchar(255) DEFAULT NULL,
  `status` enum('pending','diproses','selesai','ditolak') DEFAULT 'pending',
  `catatan_penjual` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rekening_penjual`
--

CREATE TABLE `rekening_penjual` (
  `id` int(11) NOT NULL,
  `id_penjual` int(11) NOT NULL,
  `bank` varchar(50) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `nama_pemilik` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rekening_penjual`
--

INSERT INTO `rekening_penjual` (`id`, `id_penjual`, `bank`, `no_rekening`, `nama_pemilik`) VALUES
(1, 5, 'BCA', '67890393', 'yuuuki');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(50) NOT NULL,
  `id_user` int(11) NOT NULL,
  `penjual_id` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `status` enum('pending','dibayar','dikirim','selesai') DEFAULT 'pending',
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `no_resi` varchar(50) DEFAULT NULL,
  `resi_ekspedisi` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `id_user`, `penjual_id`, `total`, `status`, `bukti_transfer`, `catatan`, `no_resi`, `resi_ekspedisi`, `created_at`, `updated_at`) VALUES
(1, 'TRX20260217005006600', 6, 5, 55000, 'pending', '1771353491_download16.jpg', 'JSQBSDBD', NULL, NULL, '2026-02-17 14:04:23', '2026-02-18 05:33:50'),
(2, 'TRX20260218005006477', 6, 5, 205000, 'pending', '1771392161_download17.jpg', 'warna bebas', NULL, NULL, '2026-02-18 02:33:16', '2026-02-18 05:33:50'),
(3, 'TRX20260218005006797', 6, 5, 205000, 'pending', '1771382320_download17.jpg', 'warna bebas', NULL, NULL, '2026-02-18 02:38:00', '2026-02-18 05:33:50'),
(4, 'TRX20260218005006364', 6, 5, 119000, 'pending', '1771384317_download17.jpg', 'bubble wrap', NULL, NULL, '2026-02-18 02:52:05', '2026-02-18 05:33:50'),
(5, 'TRX20260218005006753', 6, 5, 55000, 'pending', '1771391403_download17.jpg', 'j', NULL, NULL, '2026-02-18 05:09:52', '2026-02-18 05:33:50'),
(6, 'TRX20260218005006513', 6, 5, 119000, 'pending', '1771391597_download17.jpg', 'j', NULL, NULL, '2026-02-18 05:12:57', '2026-02-18 05:33:50'),
(7, 'TRX20260218005006283', 6, 5, 55000, '', '1771392564_Haerin.jpg', 'dfggfds', NULL, NULL, '2026-02-18 05:28:49', '2026-02-18 05:57:06'),
(8, 'TRX20260218005006142', 6, 5, 55000, 'dikirim', '1771397040_.jpg', 'jjgfvj', NULL, NULL, '2026-02-18 06:43:44', '2026-02-18 06:48:14'),
(9, 'TRX20260218005006313', 6, 5, 55000, 'dikirim', '1771398083_WhatsAppImage20260218at02.40.28.jpeg', 'bungkus', 'SCPT1234', 'SiCepat', '2026-02-18 07:01:12', '2026-02-18 07:01:45'),
(10, 'TRX20260218005006547', 6, 5, 85000, 'pending', '1771399379_Haerin.jpg', 'tolong', NULL, NULL, '2026-02-18 07:22:37', '2026-02-18 07:23:14'),
(11, 'TRX2026021810011002150', 1002, 1001, 70000, 'dikirim', '1771402137_download17.jpg', 'merah', 'scpt12345', 'SiCepat', '2026-02-18 08:08:31', '2026-02-18 08:10:56'),
(12, 'TRX202602180051002853', 1002, 5, 85000, 'pending', '1771402359_download17.jpg', 'merah', NULL, NULL, '2026-02-18 08:08:31', '2026-02-18 08:14:06');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `id_produk` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi_detail`
--

INSERT INTO `transaksi_detail` (`id`, `id_transaksi`, `id_produk`, `jumlah`, `harga`) VALUES
(1, 1, 2, 1, 45000),
(2, 2, 2, 1, 45000),
(3, 2, 3, 2, 75000),
(4, 3, 2, 1, 45000),
(5, 3, 3, 2, 75000),
(6, 4, 1, 1, 109000),
(7, 5, 2, 1, 45000),
(8, 6, 1, 1, 109000),
(9, 7, 2, 1, 45000),
(10, 8, 2, 1, 45000),
(11, 9, 2, 1, 45000),
(12, 10, 3, 1, 75000),
(13, 11, 5, 1, 60000),
(14, 12, 3, 1, 75000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','penjual','pembeli') DEFAULT 'pembeli',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktif` enum('ya','tidak') DEFAULT 'ya',
  `status` enum('online','offline') DEFAULT 'offline',
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `no_hp`, `alamat`, `foto`, `password`, `role`, `created_at`, `aktif`, `status`, `last_activity`) VALUES
(4, 'Super Adminn', 'admin@bookie.com', '085697011994', 'MW9W+8WM, Jl. Damai 2, RT.002/RW.003, Jatisari, Kec. Jatiasih, Kota Bks, Jawa Barat 17426', '1771148410_webcam-toy-photo7 (2).jpg', '$2y$10$H5AX7gG6y0qUFjBIBdX/kO/FxzT/aUUZwz3Kwjn/SkLManWG58ydm', 'super_admin', '2026-02-15 09:06:13', 'ya', 'offline', NULL),
(5, 'yuukistoree', 'ayushafira2107@gmail.com', '085717063811', 'KP. JEMBATAN RT 005 RW 017', 'toko_5_1771215973.jpg', '$2y$10$m2C0LyZTz/JkjP9ymaO15eH1yruZXtYJvPII2DOR9QJnaALlNGWZW', 'penjual', '2026-02-15 09:13:58', 'ya', 'online', '2026-02-18 15:29:14'),
(6, 'yukiio', 'ayusyafira3003@gmail.com', '0987665777676', 'jlan jalannnnn', 'pembeli_6_1771350209.jpg', '$2y$10$lbPIBbz4E/.WJm.xCzRw.O8TlOp58s/V8g7Oz/j/4I2I96qsM08G2', 'pembeli', '2026-02-16 05:54:01', 'ya', 'offline', NULL),
(999, 'Sistem', 'system@bookie.local', NULL, NULL, NULL, '', '', '2026-02-18 02:34:15', 'ya', 'offline', NULL),
(1001, 'ayuu', 'ayuu123@gmail.com', '087652444382', 'jalan kampung duren nomor 124', NULL, '$2y$10$Ye933gLRJ18b1U6sHiSK6.pjoo1ItjN436M8hIXE6JZ9C2l7jHxtK', 'penjual', '2026-02-18 07:49:04', 'ya', 'online', '2026-02-18 15:10:34'),
(1002, 'yuuuuki', 'yuki123@gmail.com', NULL, NULL, NULL, '$2y$10$.CjdGPW4jJgdkf26rLNCs.MBUi3utUCHmju/7vEhzd5Me3uB2gD32', 'pembeli', '2026-02-18 07:50:02', 'ya', 'online', '2026-02-18 15:49:02');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`);

--
-- Indeks untuk tabel `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_room` (`id_room`),
  ADD KEY `idx_pengirim` (`id_pengirim`),
  ADD KEY `idx_penerima` (`penerima_id`),
  ADD KEY `idx_dibaca` (`dibaca`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeks untuk tabel `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_penjual` (`id_penjual`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_penjual` (`id_penjual`),
  ADD KEY `fk_produk_kategori` (`kategori_id`);

--
-- Indeks untuk tabel `produk_gambar`
--
ALTER TABLE `produk_gambar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `refund`
--
ALTER TABLE `refund`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_pembeli` (`id_pembeli`),
  ADD KEY `id_penjual` (`id_penjual`);

--
-- Indeks untuk tabel `rekening_penjual`
--
ALTER TABLE `rekening_penjual`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_penjual` (`id_penjual`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_transaksi` (`id_transaksi`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `chat`
--
ALTER TABLE `chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT untuk tabel `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT untuk tabel `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `produk_gambar`
--
ALTER TABLE `produk_gambar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `refund`
--
ALTER TABLE `refund`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `rekening_penjual`
--
ALTER TABLE `rekening_penjual`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1003;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `alamat_pengiriman`
--
ALTER TABLE `alamat_pengiriman`
  ADD CONSTRAINT `alamat_pengiriman_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `chat_ibfk_1` FOREIGN KEY (`id_room`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_ibfk_2` FOREIGN KEY (`id_pengirim`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_chat_penerima` FOREIGN KEY (`penerima_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_pengirim` FOREIGN KEY (`id_pengirim`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`id_pembeli`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chat_rooms_ibfk_2` FOREIGN KEY (`id_penjual`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `keranjang_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_penjual`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `produk_gambar`
--
ALTER TABLE `produk_gambar`
  ADD CONSTRAINT `produk_gambar_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rekening_penjual`
--
ALTER TABLE `rekening_penjual`
  ADD CONSTRAINT `rekening_penjual_ibfk_1` FOREIGN KEY (`id_penjual`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
