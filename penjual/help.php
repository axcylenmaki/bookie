<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

/* =====================
   DATA PENJUAL
===================== */
$qUser = mysqli_query($conn, "SELECT * FROM users WHERE id='$idPenjual' LIMIT 1");
$user  = mysqli_fetch_assoc($qUser);

$namaPenjual = $user['nama'] ?? 'Penjual';
$fotoUser = $user['foto'] ?? '';

// Path foto profil
$fotoPath = (!empty($fotoUser) && file_exists("../uploads/".$fotoUser))
    ? "../uploads/".$fotoUser
    : "../assets/img/user.png";

/* =====================
   HITUNG CHAT YANG BELUM DIBACA
===================== */
$qUnreadChat = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM chat 
    WHERE penerima_id = '$idPenjual' 
    AND dibaca = 0
");
$unreadChat = 0;
if ($qUnreadChat) {
    $dataChat = mysqli_fetch_assoc($qUnreadChat);
    $unreadChat = $dataChat['total'] ?? 0;
}

/* =====================
   STATISTIK PRODUK & PESANAN
===================== */
// Total produk
$qTotalProduk = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE id_penjual='$idPenjual'");
$totalProduk = 0;
if ($qTotalProduk && mysqli_num_rows($qTotalProduk) > 0) {
    $dataProduk = mysqli_fetch_assoc($qTotalProduk);
    $totalProduk = $dataProduk['total'];
}

// Transaksi pending
$qPendingTransaksi = mysqli_query($conn, "
  SELECT COUNT(DISTINCT t.id) as total 
  FROM transaksi t
  JOIN transaksi_detail td ON t.id = td.id_transaksi
  JOIN produk p ON td.id_produk = p.id
  WHERE p.id_penjual = '$idPenjual' 
  AND t.status IN ('pending', 'menunggu', 'diproses', 'dibayar', 'dikirim', 'approve')
");
$pendingTransaksi = 0;
if ($qPendingTransaksi && mysqli_num_rows($qPendingTransaksi) > 0) {
    $dataPending = mysqli_fetch_assoc($qPendingTransaksi);
    $pendingTransaksi = $dataPending['total'];
}

/* =====================
   DATA QNA
===================== */
$qna_categories = [
  'pembayaran' => 'Pembayaran',
  'transaksi' => 'Transaksi',
  'pengiriman' => 'Pengiriman',
  'akun' => 'Akun & Keamanan',
  'lainnya' => 'Lainnya'
];

$qna_data = [
  'pembayaran' => [
    [
      'question' => 'Bagaimana cara pembeli melakukan pembayaran?',
      'answer' => 'Pembeli dapat melakukan pembayaran melalui transfer bank ke rekening yang telah Anda tentukan di profil toko. Setelah transfer, pembeli akan mengupload bukti transfer di halaman transaksi mereka.',
      'icon' => 'bi-cash-stack'
    ],
    [
      'question' => 'Berapa lama waktu yang diberikan untuk pembeli melakukan pembayaran?',
      'answer' => 'Pembeli memiliki waktu 24 jam sejak checkout untuk melakukan pembayaran. Jika melebihi waktu tersebut, transaksi akan otomatis dibatalkan.',
      'icon' => 'bi-clock'
    ],
    [
      'question' => 'Apa yang harus saya lakukan setelah pembeli mengupload bukti transfer?',
      'answer' => '1. Cek bukti transfer di halaman Transaksi<br>
                    2. Verifikasi jumlah transfer dan nama rekening<br>
                    3. Jika valid, klik tombol "Approve"<br>
                    4. Jika tidak valid, klik "Tolak" dan berikan alasan',
      'icon' => 'bi-check-circle'
    ],
    [
      'question' => 'Bank apa saja yang didukung untuk pembayaran?',
      'answer' => 'Sistem mendukung semua bank di Indonesia. Anda dapat menambahkan informasi rekening bank Anda di halaman Profil.',
      'icon' => 'bi-bank'
    ],
    [
      'question' => 'Apakah ada biaya transaksi yang dikenakan?',
      'answer' => 'BOOKIE tidak mengenakan biaya transaksi kepada penjual. Semua biaya transfer ditanggung oleh pembeli.',
      'icon' => 'bi-percent'
    ]
  ],
  
  'transaksi' => [
    [
      'question' => 'Bagaimana cara mengapprove pembayaran?',
      'answer' => '1. Buka menu "Transaksi"<br>
                    2. Cari transaksi dengan status "Menunggu"<br>
                    3. Klik tombol "Detail" untuk melihat bukti transfer<br>
                    4. Jika valid, klik "Approve"<br>
                    5. Sistem akan mengubah status menjadi "Diproses"',
      'icon' => 'bi-check-square'
    ],
    [
      'question' => 'Kapan saya harus menolak pembayaran?',
      'answer' => 'Tolak pembayaran jika:<br>
                    • Bukti transfer tidak jelas/tidak terbaca<br>
                    • Jumlah transfer tidak sesuai<br>
                    • Nama rekening pengirim tidak sesuai<br>
                    • Pembayaran terlambat (>24 jam)<br>
                    • Terindikasi penipuan',
      'icon' => 'bi-x-circle'
    ],
    [
      'question' => 'Apa yang terjadi setelah saya approve pembayaran?',
      'answer' => 'Setelah approve:<br>
                    1. Status transaksi berubah menjadi "Approved"<br>
                    2. Anda dapat menginput nomor resi pengiriman<br>
                    3. Pembeli akan mendapat notifikasi<br>
                    4. Anda harus segera mengirimkan barang',
      'icon' => 'bi-truck'
    ],
    [
      'question' => 'Bagaimana cara menghapus transaksi?',
      'answer' => 'Transaksi hanya dapat dihapus jika:<br>
                    • Status "Selesai" atau "Refund"<br>
                    • Sudah lewat 1 menit sejak transaksi selesai<br>
                    Tombol hapus akan aktif secara otomatis setelah waktu tersebut.',
      'icon' => 'bi-trash'
    ]
  ],
  
  'pengiriman' => [
    [
      'question' => 'Kapan saya harus menginput nomor resi?',
      'answer' => 'Input nomor resi setelah:<br>
                    1. Pembayaran sudah di-approve<br>
                    2. Barang sudah dikirimkan ke kurir<br>
                    3. Anda mendapatkan nomor resi dari kurir',
      'icon' => 'bi-box-seam'
    ],
    [
      'question' => 'Bagaimana cara input nomor resi?',
      'answer' => '1. Buka menu "Transaksi"<br>
                    2. Cari transaksi dengan status "Approved"<br>
                    3. Klik tombol "Input Resi"<br>
                    4. Masukkan nomor resi dan pilih kurir<br>
                    5. Klik "Simpan"',
      'icon' => 'bi-input-cursor'
    ],
    [
      'question' => 'Kurir apa saja yang didukung?',
      'answer' => 'Semua kurir pengiriman didukung:<br>
                    • JNE<br>
                    • J&T<br>
                    • TIKI<br>
                    • POS Indonesia<br>
                    • SiCepat<br>
                    • Ninja Xpress<br>
                    • Dan kurir lainnya',
      'icon' => 'bi-truck'
    ],
    [
      'question' => 'Bagaimana pembeli melacak paket?',
      'answer' => 'Setelah Anda input nomor resi:<br>
                    1. Pembeli bisa melihat nomor resi di transaksi mereka<br>
                    2. Klik nomor resi untuk otomatis redirect ke website kurir<br>
                    3. Pembeli dapat melacak status pengiriman langsung',
      'icon' => 'bi-geo-alt'
    ]
  ],
  
  'akun' => [
    [
      'question' => 'Bagaimana cara mengubah informasi rekening bank?',
      'answer' => '1. Buka menu "Profil"<br>
                    2. Scroll ke bagian "Informasi Bank"<br>
                    3. Edit data rekening<br>
                    4. Klik "Simpan Perubahan"',
      'icon' => 'bi-credit-card'
    ],
    [
      'question' => 'Apakah data saya aman di BOOKIE?',
      'answer' => 'Ya, BOOKIE menggunakan:<br>
                    • Enkripsi data sensitif<br>
                    • Proteksi terhadap SQL Injection<br>
                    • Sistem autentikasi yang aman<br>
                    • Backup data rutin',
      'icon' => 'bi-shield-check'
    ],
    [
      'question' => 'Bagaimana jika saya lupa password?',
      'answer' => '1. Klik "Lupa Password" di halaman login<br>
                    2. Masukkan email Anda<br>
                    3. Cek email untuk link reset password<br>
                    4. Buat password baru<br>
                    5. Login dengan password baru',
      'icon' => 'bi-key'
    ],
    [
      'question' => 'Bagaimana cara menghubungi admin?',
      'answer' => 'Anda dapat menghubungi admin melalui:<br>
                    • Email: ayu.syafira39@gmail.com<br>
                    • Telepon: 021-12345678<br>
                    • WhatsApp: 0856-9701-1994<br>
                    • Form kontak di halaman Help',
      'icon' => 'bi-headset'
    ]
  ],
  
  'lainnya' => [
    [
      'question' => 'Bagaimana cara menambah produk baru?',
      'answer' => '1. Buka menu "Produk"<br>
                    2. Klik tombol "+" (tambah)<br>
                    3. Isi semua informasi produk<br>
                    4. Upload gambar produk<br>
                    5. Klik "Simpan Produk"',
      'icon' => 'bi-plus-circle'
    ],
    [
      'question' => 'Bagaimana cara melihat laporan penjualan?',
      'answer' => '1. Buka menu "Laporan"<br>
                    2. Pilih bulan dan tahun<br>
                    3. Sistem akan menampilkan:<br>
                       - Statistik penjualan<br>
                       - Grafik pendapatan<br>
                       - Detail transaksi<br>
                    4. Anda bisa download PDF',
      'icon' => 'bi-bar-chart'
    ],
    [
      'question' => 'Apa itu status "Refund"?',
      'answer' => 'Status "Refund" berarti:<br>
                    • Transaksi dibatalkan<br>
                    • Uang dikembalikan ke pembeli<br>
                    • Biasanya karena pembayaran ditolak<br>
                    • Setelah 1 menit, transaksi bisa dihapus',
      'icon' => 'bi-arrow-counterclockwise'
    ],
    [
      'question' => 'Jam operasional layanan BOOKIE?',
      'answer' => 'BOOKIE tersedia 24/7. Untuk dukungan admin:<br>
                    • Senin-Jumat: 08:00-17:00 WIB<br>
                    • Sabtu: 08:00-12:00 WIB<br>
                    • Minggu & Hari Libur: Tutup',
      'icon' => 'bi-clock-history'
    ]
  ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bantuan & QnA - BOOKIE</title>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
/* =====================
   RESET & GLOBAL
===================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
    display: flex;
}

/* =====================
   SIDEBAR
===================== */
.sidebar {
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    transition: all 0.3s ease;
}

.sidebar-logo {
    padding: 20px;
    text-align: center;
    font-size: 24px;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    letter-spacing: 1px;
    color: #fff;
}

.sidebar-profile {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-decoration: none;
    color: #fff;
    transition: all 0.3s ease;
}

.sidebar-profile:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.sidebar-profile img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.sidebar-profile .name {
    font-size: 16px;
    font-weight: 600;
}

.sidebar-profile .role {
    font-size: 12px;
    color: #95a5a6;
    margin-top: 2px;
}

.sidebar-menu {
    flex: 1;
    padding: 15px 0;
    overflow-y: auto;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 14px 20px;
    color: #bdc3c7;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    margin: 2px 10px;
    border-radius: 8px;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateX(8px);
}

.menu-item.active {
    background: linear-gradient(90deg, #3498db, #2980b9);
    color: #fff;
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.menu-item i {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.menu-badge {
    margin-left: auto;
    background: #e74c3c;
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.sidebar-footer {
    padding: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin-bottom: 8px;
    border: none;
    cursor: pointer;
    width: 100%;
}

.logout {
    background: linear-gradient(90deg, #e74c3c, #c0392b);
    color: white;
}

.logout:hover {
    background: linear-gradient(90deg, #c0392b, #a93226);
    transform: translateY(-2px);
}

.help {
    background: transparent;
    border: 2px solid #3498db;
    color: #3498db;
}

.help:hover {
    background: rgba(52, 152, 219, 0.1);
    transform: translateY(-2px);
}

/* =====================
   TOP BAR
===================== */
.top-bar {
    position: fixed;
    top: 0;
    right: 0;
    left: 260px;
    height: 70px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 999;
    transition: left 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.search-container {
    flex: 1;
    max-width: 500px;
    position: relative;
}

.search-box {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.search-box:focus {
    outline: none;
    border-color: #3498db;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 18px;
}

.top-bar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.user-profile-top {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: #1e293b;
}

.user-profile-top:hover {
    background: #f1f5f9;
}

.user-profile-top img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}

.user-info-top .name {
    font-size: 14px;
    font-weight: 600;
}

.user-info-top .role {
    font-size: 12px;
    color: #64748b;
}

/* =====================
   MAIN CONTENT
===================== */
.main-content {
    flex: 1;
    margin-left: 260px;
    margin-top: 70px;
    padding: 24px;
    min-height: calc(100vh - 70px);
    background: #f8f9fa;
    transition: all 0.3s ease;
}

/* =====================
   HERO SECTION
===================== */
.hero {
    background: linear-gradient(135deg, #020617, #1e3a8a);
    color: #fff;
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.hero h2 {
    margin: 0 0 8px;
    font-size: 26px;
    font-weight: 700;
}

.hero p {
    max-width: 520px;
    opacity: .9;
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: 20px;
}

/* =====================
   FAQ STYLES
===================== */
.faq-category {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border-left: 5px solid #3498db;
    transition: all 0.3s ease;
}

.faq-category:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.faq-category h4 {
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}

.faq-item {
    margin-bottom: 16px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 12px;
    border-left: 4px solid #3498db;
    transition: all 0.3s ease;
    cursor: pointer;
}

.faq-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.faq-question {
    color: #2c3e50;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.faq-question .icon {
    color: #3498db;
    font-size: 1.2rem;
}

.faq-answer {
    color: #555;
    margin-top: 12px;
    padding-left: 30px;
    line-height: 1.6;
}

.search-box {
    position: relative;
    margin-bottom: 30px;
}

.search-box input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border-radius: 30px;
    border: 2px solid #e2e8f0;
    background: #fff;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-box .bi-search {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
}

.contact-card {
    background: linear-gradient(135deg, #3498db, #2c3e50);
    color: white;
    border-radius: 16px;
    padding: 32px;
    margin-top: 30px;
}

.contact-card h4 {
    color: white;
    margin-bottom: 24px;
}

.contact-info {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 16px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.contact-info:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.contact-info .icon {
    font-size: 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 25px;
}

.quick-link {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 8px 24px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
}

.quick-link:hover {
    background: #3498db;
    color: white;
    border-color: #3498db;
    transform: translateY(-2px);
}

.tips-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-top: 24px;
    border: 1px solid #e2e8f0;
}

.tips-card h5 {
    color: #2c3e50;
    margin-bottom: 15px;
}

mark {
    background-color: #ffeb3b;
    padding: 2px 4px;
    border-radius: 3px;
}

/* =====================
   MENU TOGGLE (MOBILE)
===================== */
.menu-toggle {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 20px;
    color: #64748b;
    transition: all 0.3s ease;
}

.menu-toggle:hover {
    background: #e2e8f0;
    color: #3498db;
}

.search-toggle {
    display: none;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: none;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 20px;
    color: #64748b;
    transition: all 0.3s ease;
}

.search-toggle:hover {
    background: #f1f5f9;
    color: #3498db;
}

/* =====================
   RESPONSIVE
===================== */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .top-bar {
        left: 0;
    }
    
    .main-content {
        margin-left: 0;
        margin-top: 70px;
    }
    
    .menu-toggle {
        display: flex !important;
    }
    
    .search-toggle {
        display: flex !important;
    }
    
    .sidebar-logo span,
    .sidebar-profile > div,
    .menu-item span,
    .menu-badge,
    .footer-btn span {
        display: none;
    }
    
    .sidebar.active .sidebar-logo span,
    .sidebar.active .sidebar-profile > div,
    .sidebar.active .menu-item span,
    .sidebar.active .menu-badge,
    .sidebar.active .footer-btn span {
        display: block;
    }
    
    .search-container {
        display: none;
    }
    
    .search-container.active {
        display: block;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        padding: 10px;
        background: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .quick-links {
        justify-content: center;
    }
    
    .contact-info {
        padding: 10px;
    }
}

@media (max-width: 480px) {
    .top-bar {
        padding: 0 12px;
    }
    
    .user-info-top {
        display: none;
    }
    
    .main-content {
        padding: 16px;
    }
    
    .hero {
        padding: 20px;
    }
    
    .contact-card {
        padding: 20px;
    }
}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <!-- LOGO -->
    <div class="sidebar-logo">
        <span>📚 BOOKIE</span>
    </div>

    <!-- PROFIL -->
    <a href="profile.php" class="sidebar-profile">
        <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
        <div>
            <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
            <div class="role">Penjual</div>
        </div>
    </a>

    <!-- MENU SCROLLABLE -->
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="profile.php" class="menu-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        
        <a href="produk.php" class="menu-item">
            <i class="bi bi-box"></i>
            <span>Produk</span>
            <?php if($totalProduk > 0): ?>
            <span class="menu-badge"><?= $totalProduk ?></span>
            <?php endif; ?>
        </a>
        
        <a href="chat/chat.php" class="menu-item">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
            <?php if($unreadChat > 0): ?>
            <span class="menu-badge"><?= $unreadChat ?></span>
            <?php endif; ?>
        </a>
        
        <a href="pesanan.php" class="menu-item">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
            <?php if($pendingTransaksi > 0): ?>
            <span class="menu-badge"><?= $pendingTransaksi ?></span>
            <?php endif; ?>
        </a>
        
        <a href="status.php" class="menu-item">
            <i class="bi bi-activity"></i>
            <span>Status</span>
        </a>
        
        <a href="laporan.php" class="menu-item">
            <i class="bi bi-bar-chart"></i>
            <span>Laporan</span>
        </a>
        
        <a href="penjual_lain.php" class="menu-item">
            <i class="bi bi-people"></i>
            <span>Penjual Lain</span>
        </a>
    </div>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <button class="footer-btn logout" onclick="logout()">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </button>
        
        <a href="help.php" class="footer-btn help active">
            <i class="bi bi-question-circle"></i>
            <span>Help & FAQ</span>
        </a>
    </div>
</div>

<!-- TOP BAR -->
<div class="top-bar" id="topBar">
    <!-- Menu Toggle (Mobile) -->
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Search Bar -->
    <div class="search-container" id="searchContainer">
        <i class="bi bi-search search-icon"></i>
        <input type="text" class="search-box" placeholder="Cari produk, pesanan, atau pelanggan...">
    </div>
    
    <!-- Search Toggle (Mobile) -->
    <button class="search-toggle" id="searchToggle">
        <i class="bi bi-search"></i>
    </button>
    
    <!-- Right Section -->
    <div class="top-bar-right">
        <!-- User Profile -->
        <a href="profile.php" class="user-profile-top">
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
            <div class="user-info-top">
                <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
                <div class="role">Penjual</div>
            </div>
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    
    <!-- HERO -->
    <div class="hero">
        <h2><i class="bi bi-question-circle"></i> Bantuan & Pertanyaan Umum (QnA)</h2>
        <p>Temukan jawaban untuk pertanyaan umum tentang sistem pembayaran dan transaksi</p>
    </div>

    <!-- QUICK LINKS -->
    <div class="quick-links">
        <a href="#pembayaran" class="quick-link">
            <i class="bi bi-arrow-right-circle me-1"></i> Pembayaran
        </a>
        <a href="#transaksi" class="quick-link">
            <i class="bi bi-arrow-right-circle me-1"></i> Transaksi
        </a>
        <a href="#pengiriman" class="quick-link">
            <i class="bi bi-arrow-right-circle me-1"></i> Pengiriman
        </a>
        <a href="#akun" class="quick-link">
            <i class="bi bi-arrow-right-circle me-1"></i> Akun & Keamanan
        </a>
        <a href="#lainnya" class="quick-link">
            <i class="bi bi-arrow-right-circle me-1"></i> Lainnya
        </a>
    </div>

    <!-- SEARCH BOX -->
    <div class="search-box">
        <input type="text" id="searchFaq" class="form-control" placeholder="Cari pertanyaan atau topik...">
        <i class="bi bi-search"></i>
    </div>

    <!-- FAQ BY CATEGORY -->
    <?php foreach($qna_categories as $cat_key => $cat_name): ?>
    <div class="faq-category" id="<?= $cat_key ?>">
        <h4><i class="bi bi-question-circle me-2"></i> <?= $cat_name ?></h4>
        
        <?php foreach($qna_data[$cat_key] as $index => $faq): 
            $faq_id = $cat_key . $index;
        ?>
        <div class="faq-item">
            <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#collapse<?= $faq_id ?>">
                <span class="icon"><i class="bi <?= $faq['icon'] ?>"></i></span>
                <?= $faq['question'] ?>
            </div>
            <div id="collapse<?= $faq_id ?>" class="collapse">
                <div class="faq-answer">
                    <?= $faq['answer'] ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- CONTACT SUPPORT -->
    <div class="contact-card">
        <h4><i class="bi bi-headset me-2"></i> Butuh Bantuan Lebih Lanjut?</h4>
        <p>Jika Anda tidak menemukan jawaban yang dicari, hubungi tim support kami:</p>
        
        <div class="row">
            <div class="col-md-4">
                <div class="contact-info">
                    <div class="icon">
                        <i class="bi bi-telephone"></i>
                    </div>
                    <div>
                        <strong>Telepon</strong><br>
                        <small>021-12345678</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-info">
                    <div class="icon">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div>
                        <strong>WhatsApp</strong><br>
                        <small>0856-9701-1994</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="contact-info">
                    <div class="icon">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div>
                        <strong>Email</strong><br>
                        <small>ayu.syafira39@gmail.com</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5><i class="bi bi-clock-history me-2"></i> Jam Operasional Support</h5>
            <p class="mb-1">Senin - Jumat: 08:00 - 17:00 WIB</p>
            <p class="mb-1">Sabtu: 08:00 - 12:00 WIB</p>
            <p class="mb-0">Minggu & Hari Libur: Tutup</p>
        </div>
    </div>

    <!-- TIPS & TRIK -->
    <div class="tips-card">
        <h5><i class="bi bi-lightbulb me-2"></i> Tips & Trik untuk Penjual</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="d-flex align-items-start mb-3">
                    <i class="bi bi-check2-circle text-success me-3 fs-4"></i>
                    <div>
                        <h6>Verifikasi Cepat</h6>
                        <small class="text-muted">Verifikasi pembayaran maksimal 6 jam setelah pembeli upload bukti transfer untuk meningkatkan kepercayaan.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start mb-3">
                    <i class="bi bi-chat-dots text-info me-3 fs-4"></i>
                    <div>
                        <h6>Komunikasi Baik</h6>
                        <small class="text-muted">Jika menolak pembayaran, berikan alasan yang jelas melalui chat kepada pembeli.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start mb-3">
                    <i class="bi bi-truck text-warning me-3 fs-4"></i>
                    <div>
                        <h6>Input Resi Tepat Waktu</h6>
                        <small class="text-muted">Input nomor resi segera setelah barang dikirim ke kurir untuk menghindari komplain.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const topBar = document.getElementById('topBar');
const mainContent = document.getElementById('mainContent');
const searchContainer = document.getElementById('searchContainer');
const searchToggle = document.getElementById('searchToggle');

// Menu Toggle
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Search Toggle (Mobile)
searchToggle.addEventListener('click', () => {
    searchContainer.classList.toggle('active');
});

// Logout function
function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = '../auth/logout.php';
    }
}

// Search functionality
document.getElementById('searchFaq').addEventListener('keyup', function() {
  const searchTerm = this.value.toLowerCase();
  const faqItems = document.querySelectorAll('.faq-item');
  
  faqItems.forEach(item => {
    const question = item.querySelector('.faq-question').textContent.toLowerCase();
    const answer = item.querySelector('.faq-answer')?.textContent.toLowerCase() || '';
    
    if (question.includes(searchTerm) || answer.includes(searchTerm)) {
      item.style.display = 'block';
      
      // Auto expand if search term found
      const collapseId = item.querySelector('.collapse').id;
      const collapseElement = new bootstrap.Collapse('#' + collapseId, {
        toggle: false
      });
      collapseElement.show();
      
      // Highlight search term
      highlightText(item, searchTerm);
    } else {
      item.style.display = 'none';
    }
  });
  
  // Show/hide categories based on visible items
  document.querySelectorAll('.faq-category').forEach(category => {
    const visibleItems = category.querySelectorAll('.faq-item[style="display: block"]').length;
    category.style.display = visibleItems > 0 ? 'block' : 'none';
  });
});

function highlightText(element, term) {
  if (!term) return;
  
  const walker = document.createTreeWalker(
    element,
    NodeFilter.SHOW_TEXT,
    null,
    false
  );
  
  const nodes = [];
  let node;
  while (node = walker.nextNode()) {
    nodes.push(node);
  }
  
  nodes.forEach(node => {
    const text = node.nodeValue;
    const regex = new RegExp(`(${term})`, 'gi');
    const newText = text.replace(regex, '<mark>$1</mark>');
    
    if (newText !== text) {
      const span = document.createElement('span');
      span.innerHTML = newText;
      node.parentNode.replaceChild(span, node);
    }
  });
}

// Auto expand first item in each category on page load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.faq-category').forEach(category => {
    const firstItem = category.querySelector('.faq-item');
    if (firstItem) {
      const collapseId = firstItem.querySelector('.collapse').id;
      const collapse = new bootstrap.Collapse('#' + collapseId, {
        toggle: false
      });
      collapse.show();
    }
  });
});

// Smooth scroll for quick links
document.querySelectorAll('.quick-link').forEach(link => {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    const targetId = this.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);
    
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: 'smooth'
      });
    }
  });
});

// Responsive adjustments
function handleResize() {
    if (window.innerWidth <= 768) {
        menuToggle.style.display = 'flex';
        searchToggle.style.display = 'flex';
    } else {
        menuToggle.style.display = 'none';
        searchToggle.style.display = 'none';
        searchContainer.classList.remove('active');
        sidebar.classList.remove('active');
    }
}

window.addEventListener('resize', handleResize);
handleResize();
</script>

</body>
</html>