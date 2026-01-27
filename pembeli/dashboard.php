<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   DATA LOGIN
===================== */
$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'] ?? 'Pembeli';

/* =====================
   DATA PEMESANAN
===================== */
// Total pesanan
$qTotalPesanan = mysqli_query($conn,"
  SELECT COUNT(*) AS total_pesanan
  FROM transaksi
  WHERE pembeli_id='$idPembeli'
") or die(mysqli_error($conn));

$totalPesanan = mysqli_fetch_assoc($qTotalPesanan)['total_pesanan'] ?? 0;

// Pesanan aktif (belum selesai)
$qPesananAktif = mysqli_query($conn,"
  SELECT COUNT(*) AS pesanan_aktif
  FROM transaksi
  WHERE pembeli_id='$idPembeli' 
    AND status NOT IN ('selesai', 'tolak')
") or die(mysqli_error($conn));

$pesananAktif = mysqli_fetch_assoc($qPesananAktif)['pesanan_aktif'] ?? 0;

// Total pengeluaran (hitung dari transaksi_detail)
$qTotalPengeluaran = mysqli_query($conn,"
  SELECT SUM(td.qty * td.harga) AS total_pengeluaran
  FROM transaksi t
  JOIN transaksi_detail td ON t.id = td.transaksi_id
  WHERE t.pembeli_id='$idPembeli' 
    AND t.status = 'selesai'
") or die(mysqli_error($conn));

$totalPengeluaran = mysqli_fetch_assoc($qTotalPengeluaran)['total_pengeluaran'] ?? 0;

// Item di keranjang
$qKeranjang = mysqli_query($conn,"
  SELECT SUM(qty) AS jumlah_keranjang
  FROM keranjang
  WHERE pembeli_id='$idPembeli'
") or die(mysqli_error($conn));

$keranjangResult = mysqli_fetch_assoc($qKeranjang);
$jumlahKeranjang = $keranjangResult['jumlah_keranjang'] ?? 0;

/* =====================
   PESANAN TERBARU
===================== */
$qPesananTerbaru = mysqli_query($conn,"
  SELECT 
    t.id,
    t.id AS kode_transaksi,
    t.status,
    t.created_at,
    SUM(td.qty) AS jumlah_item,
    SUM(td.qty * td.harga) AS total
  FROM transaksi t
  LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
  WHERE t.pembeli_id = '$idPembeli'
  GROUP BY t.id
  ORDER BY t.created_at DESC
  LIMIT 5
") or die(mysqli_error($conn));

$pesananTerbaru = [];
if ($qPesananTerbaru) {
    while ($row = mysqli_fetch_assoc($qPesananTerbaru)) {
        $pesananTerbaru[] = $row;
    }
}

/* =====================
   PRODUK TERLARIS (BUKU & ATK)
===================== */
// Cek apakah tabel produk ada kolom kategori_id
$checkKategoriId = mysqli_query($conn, "SHOW COLUMNS FROM produk LIKE 'kategori_id'");
$hasKategoriIdColumn = mysqli_num_rows($checkKategoriId) > 0;

// Query produk terlaris (buku & ATK)
if ($hasKategoriIdColumn) {
    $qTerlaris = mysqli_query($conn,"
      SELECT 
        p.id,
        p.nama_buku,
        p.penulis,
        p.harga,
        p.gambar,
        k.nama_kategori AS kategori,
        COALESCE(SUM(td.qty), 0) AS jumlah_terjual,
        p.stok,
        u.nama AS nama_penjual
      FROM produk p
      LEFT JOIN kategori k ON p.kategori_id = k.id
      LEFT JOIN transaksi_detail td ON p.id = td.produk_id
      LEFT JOIN transaksi t ON td.transaksi_id = t.id AND t.status = 'selesai'
      LEFT JOIN users u ON p.penjual_id = u.id
      WHERE p.stok > 0
      GROUP BY p.id
      ORDER BY jumlah_terjual DESC
      LIMIT 8
    ");
} else {
    $qTerlaris = mysqli_query($conn,"
      SELECT 
        p.id,
        p.nama_buku,
        p.penulis,
        p.harga,
        p.gambar,
        COALESCE(SUM(td.qty), 0) AS jumlah_terjual,
        p.stok,
        u.nama AS nama_penjual
      FROM produk p
      LEFT JOIN transaksi_detail td ON p.id = td.produk_id
      LEFT JOIN transaksi t ON td.transaksi_id = t.id AND t.status = 'selesai'
      LEFT JOIN users u ON p.penjual_id = u.id
      WHERE p.stok > 0
      GROUP BY p.id
      ORDER BY jumlah_terjual DESC
      LIMIT 8
    ");
}

$produkTerlaris = [];
if ($qTerlaris) {
    while ($row = mysqli_fetch_assoc($qTerlaris)) {
        $produkTerlaris[] = $row;
    }
}

/* =====================
   PRODUK TERBARU (BUKU & ATK)
===================== */
if ($hasKategoriIdColumn) {
    $qTerbaru = mysqli_query($conn,"
      SELECT 
        p.id,
        p.nama_buku,
        p.penulis,
        p.harga,
        p.gambar,
        k.nama_kategori AS kategori,
        p.stok,
        u.nama AS nama_penjual,
        p.created_at
      FROM produk p
      LEFT JOIN kategori k ON p.kategori_id = k.id
      LEFT JOIN users u ON p.penjual_id = u.id
      WHERE p.stok > 0
      ORDER BY p.created_at DESC
      LIMIT 8
    ");
} else {
    $qTerbaru = mysqli_query($conn,"
      SELECT 
        p.id,
        p.nama_buku,
        p.penulis,
        p.harga,
        p.gambar,
        p.stok,
        u.nama AS nama_penjual,
        p.created_at
      FROM produk p
      LEFT JOIN users u ON p.penjual_id = u.id
      WHERE p.stok > 0
      ORDER BY p.created_at DESC
      LIMIT 8
    ");
}

$produkTerbaru = [];
if ($qTerbaru) {
    while ($row = mysqli_fetch_assoc($qTerbaru)) {
        $produkTerbaru[] = $row;
    }
}

/* =====================
   KATEGORI PRODUK (BUKU & ATK)
===================== */
$kategoriProduk = [];

if ($hasKategoriIdColumn) {
    $qKategori = mysqli_query($conn,"
      SELECT 
        k.nama_kategori AS kategori,
        k.foto AS gambar_kategori,
        COUNT(p.id) as jumlah
      FROM kategori k
      LEFT JOIN produk p ON k.id = p.kategori_id AND p.stok > 0
      GROUP BY k.id
      ORDER BY jumlah DESC
      LIMIT 8
    ");
    
    if ($qKategori) {
        while ($row = mysqli_fetch_assoc($qKategori)) {
            $kategoriProduk[] = $row;
        }
    }
}

// Jika tidak ada kategori di database, gunakan default
if (empty($kategoriProduk)) {
    $kategoriProduk = [
        ['kategori' => 'Buku Pelajaran', 'jumlah' => 25, 'gambar_kategori' => ''],
        ['kategori' => 'Buku Novel', 'jumlah' => 18, 'gambar_kategori' => ''],
        ['kategori' => 'Alat Tulis', 'jumlah' => 42, 'gambar_kategori' => ''],
        ['kategori' => 'Buku Referensi', 'jumlah' => 15, 'gambar_kategori' => ''],
        ['kategori' => 'ATK Kantor', 'jumlah' => 30, 'gambar_kategori' => ''],
        ['kategori' => 'Buku Anak', 'jumlah' => 22, 'gambar_kategori' => ''],
        ['kategori' => 'Peralatan Gambar', 'jumlah' => 12, 'gambar_kategori' => ''],
        ['kategori' => 'Buku Import', 'jumlah' => 8, 'gambar_kategori' => '']
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Pembeli - BOOKIE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
    }
    
    .main-content {
      margin-left: 250px;
      padding: 20px;
      min-height: 100vh;
    }
    
    .card-dark {
      background-color: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 10px;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .card-dark:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .product-card {
      height: 100%;
      border: 1px solid #e0e0e0;
      border-radius: 10px;
      overflow: hidden;
      transition: all 0.3s;
    }
    
    .product-card:hover {
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      transform: translateY(-3px);
    }
    
    .product-img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-bottom: 1px solid #e0e0e0;
    }
    
    .product-img-sm {
      width: 70px;
      height: 90px;
      object-fit: cover;
      border-radius: 6px;
    }
    
    .kategori-card {
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      color: white;
      font-weight: bold;
      font-size: 1.1rem;
      text-align: center;
      padding: 15px;
      cursor: pointer;
      transition: all 0.3s;
      background-size: cover;
      background-position: center;
    }
    
    .kategori-card:hover {
      transform: scale(1.03);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
    }
    
    .bg-primary { background-color: #4361ee !important; }
    .bg-success { background-color: #28a745 !important; }
    .bg-warning { background-color: #ffc107 !important; }
    .bg-danger { background-color: #dc3545 !important; }
    .bg-info { background-color: #17a2b8 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
    
    .card-title {
      color: #6c757d;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .card-value {
      font-weight: 700;
      font-size: 1.8rem;
      color: #2d3748;
    }
    
    .status-badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .welcome-card {
      background: linear-gradient(135deg, #6f42c1 0%, #6610f2 100%);
      color: white;
      border-radius: 10px;
      overflow: hidden;
    }
    
    .section-title {
      position: relative;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    
    .section-title:after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 60px;
      height: 3px;
      background: #6f42c1;
      border-radius: 2px;
    }
    
    .btn-purple {
      background-color: #6f42c1;
      border-color: #6f42c1;
      color: white;
    }
    
    .btn-purple:hover {
      background-color: #5a32a3;
      border-color: #5a32a3;
      color: white;
    }
    
    .btn-outline-purple {
      border-color: #6f42c1;
      color: #6f42c1;
    }
    
    .btn-outline-purple:hover {
      background-color: #6f42c1;
      color: white;
    }
    
    .search-box {
      border-radius: 25px;
      border: 2px solid #e0e0e0;
      padding: 8px 20px;
      width: 300px;
    }
    
    .search-box:focus {
      border-color: #6f42c1;
      box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
    }
    
    .stok-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 0.7rem;
    }
    
    .seller-badge {
      font-size: 0.75rem;
      background-color: #e9ecef;
      color: #495057;
      padding: 2px 8px;
      border-radius: 10px;
    }
    
    .price-tag {
      font-weight: bold;
      color: #28a745;
      font-size: 1.1rem;
    }
    
    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
      animation: fadeIn 0.5s ease-out;
    }
  </style>
</head>
<body>

<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
  <!-- HEADER -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="fw-bold text-dark">Dashboard Pembeli</h3>
      <p class="text-muted mb-0">Selamat datang kembali, <?= htmlspecialchars($namaPembeli) ?>! 👋</p>
    </div>
    <div>
      <span class="text-muted me-3"><?= date('l, d F Y') ?></span>
      <div class="d-inline-block">
        <input type="text" class="form-control search-box" placeholder="Cari buku atau alat tulis...">
      </div>
    </div>
  </div>

  <!-- WELCOME CARD -->
  <div class="welcome-card p-4 mb-4">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-bold">BOOKIE - Toko Buku & ATK Online</h4>
        <p class="mb-0">Temukan buku dan alat tulis favorit Anda dari berbagai penjual terpercaya.</p>
      </div>
      <div class="col-3 text-end">
        <i class="bi bi-shop" style="font-size: 4rem; opacity: 0.7;"></i>
      </div>
    </div>
  </div>

  <!-- STATISTIK UTAMA -->
  <div class="row g-3 mb-4 fade-in">
    <div class="col-md-3">
      <div class="card-dark shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="card-title">Total Pesanan</p>
              <h2 class="card-value"><?= $totalPesanan ?></h2>
              <small class="text-<?= $totalPesanan > 0 ? 'success' : 'info' ?>">
                <i class="bi bi-<?= $totalPesanan > 0 ? 'check-circle' : 'info-circle' ?>"></i> 
                <?= $totalPesanan > 0 ? $totalPesanan.' pesanan dibuat' : 'Belum ada pesanan' ?>
              </small>
            </div>
            <div class="stat-icon bg-primary">
              <i class="bi bi-cart-check"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-dark shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="card-title">Pesanan Aktif</p>
              <h2 class="card-value"><?= $pesananAktif ?></h2>
              <small class="text-<?= $pesananAktif > 0 ? 'warning' : 'success' ?>">
                <i class="bi bi-<?= $pesananAktif > 0 ? 'clock' : 'check-circle' ?>"></i> 
                <?= $pesananAktif > 0 ? $pesananAktif.' pesanan diproses' : 'Tidak ada pesanan aktif' ?>
              </small>
            </div>
            <div class="stat-icon bg-warning">
              <i class="bi bi-hourglass-split"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-dark shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="card-title">Total Pengeluaran</p>
              <h2 class="card-value">Rp <?= number_format($totalPengeluaran,0,',','.') ?></h2>
              <small class="text-<?= $totalPengeluaran > 0 ? 'info' : 'muted' ?>">
                <i class="bi bi-<?= $totalPengeluaran > 0 ? 'currency-dollar' : 'dash-circle' ?>"></i> 
                <?= $totalPengeluaran > 0 ? 'Total belanja selesai' : 'Belum ada transaksi selesai' ?>
              </small>
            </div>
            <div class="stat-icon bg-success">
              <i class="bi bi-cash-stack"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card-dark shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="card-title">Keranjang</p>
              <h2 class="card-value"><?= $jumlahKeranjang ?></h2>
              <small class="text-<?= $jumlahKeranjang > 0 ? 'primary' : 'muted' ?>">
                <i class="bi bi-cart"></i> 
                <?= $jumlahKeranjang > 0 ? $jumlahKeranjang.' item menunggu' : 'Keranjang kosong' ?>
              </small>
            </div>
            <div class="stat-icon bg-info">
              <i class="bi bi-basket"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- KATEGORI PRODUK -->
  <div class="row mb-4 fade-in">
    <div class="col-12">
      <div class="card-dark p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="fw-bold mb-0 section-title">Kategori Produk</h4>
          <a href="kategori.php" class="btn btn-outline-purple btn-sm">Lihat Semua</a>
        </div>
        <div class="row g-3">
          <?php foreach($kategoriProduk as $index => $kategori): 
            // Warna background berbeda untuk setiap kategori
            $colors = ['#4361ee', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8', '#fd7e14', '#20c997'];
            $color = $colors[$index % count($colors)];
          ?>
          <div class="col-md-3 col-sm-6">
            <div class="kategori-card" style="background-color: <?= $color ?>; <?= !empty($kategori['gambar_kategori']) ? "background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('../uploads/{$kategori['gambar_kategori']}')" : '' ?>">
              <div>
                <i class="bi bi-<?= $index % 8 == 0 ? 'book' : ($index % 8 == 1 ? 'bookmark' : ($index % 8 == 2 ? 'pencil' : ($index % 8 == 3 ? 'journal' : ($index % 8 == 4 ? 'briefcase' : ($index % 8 == 5 ? 'balloon' : ($index % 8 == 6 ? 'palette' : 'globe')))))) ?> me-2"></i>
                <?= htmlspecialchars($kategori['kategori']) ?>
                <div class="mt-2">
                  <span class="badge bg-light text-dark"><?= $kategori['jumlah'] ?> produk</span>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- PRODUK TERLARIS -->
  <div class="row mb-4 fade-in">
    <div class="col-12">
      <div class="card-dark p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="fw-bold mb-0 section-title">Produk Terlaris</h4>
          <a href="produk.php?sort=terlaris" class="btn btn-outline-purple btn-sm">Lihat Semua</a>
        </div>
        <div class="row g-3">
          <?php if(count($produkTerlaris) > 0): ?>
            <?php foreach($produkTerlaris as $index => $produk): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
              <div class="product-card">
                <div class="position-relative">
                  <img src="<?= !empty($produk['gambar']) ? '../uploads/'.$produk['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                       class="product-img" 
                       alt="<?= htmlspecialchars($produk['nama_buku']) ?>"
                       onerror="this.src='../assets/img/book-placeholder.png'">
                  <span class="position-absolute top-0 start-0 bg-danger text-white px-2 py-1 m-2 rounded stok-badge">
                    <i class="bi bi-fire"></i> Terlaris
                  </span>
                  <?php if($produk['stok'] < 10 && $produk['stok'] > 0): ?>
                  <span class="position-absolute top-0 end-0 bg-warning text-dark px-2 py-1 m-2 rounded stok-badge">
                    Stok: <?= $produk['stok'] ?>
                  </span>
                  <?php elseif($produk['stok'] == 0): ?>
                  <span class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 m-2 rounded stok-badge">
                    Habis
                  </span>
                  <?php endif; ?>
                </div>
                <div class="p-3">
                  <h6 class="fw-bold mb-1"><?= htmlspecialchars(mb_strimwidth($produk['nama_buku'], 0, 35, '...')) ?></h6>
                  <p class="text-muted mb-1 small"><?= htmlspecialchars(mb_strimwidth($produk['penulis'] ?? '-', 0, 25, '...')) ?></p>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <?php if(isset($produk['kategori'])): ?>
                    <span class="badge bg-purple bg-opacity-10 text-purple"><?= htmlspecialchars($produk['kategori']) ?></span>
                    <?php endif; ?>
                    <span class="seller-badge">
                      <i class="bi bi-shop"></i> <?= htmlspecialchars(mb_strimwidth($produk['nama_penjual'] ?? 'Toko', 0, 15, '...')) ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="price-tag">Rp <?= number_format($produk['harga'],0,',','.') ?></div>
                    <div class="text-muted small">
                      <i class="bi bi-cart-check"></i> <?= $produk['jumlah_terjual'] ?? 0 ?> terjual
                    </div>
                  </div>
                  <div class="mt-3">
                    <button class="btn btn-purple btn-sm w-100 add-to-cart" data-id="<?= $produk['id'] ?>">
                      <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12 text-center py-4">
              <i class="bi bi-emoji-frown" style="font-size: 3rem; color: #6c757d;"></i>
              <p class="mt-2">Belum ada produk terlaris</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- PRODUK TERBARU -->
  <div class="row mb-4 fade-in">
    <div class="col-12">
      <div class="card-dark p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="fw-bold mb-0 section-title">Produk Terbaru</h4>
          <a href="produk.php?sort=terbaru" class="btn btn-outline-purple btn-sm">Lihat Semua</a>
        </div>
        <div class="row g-3">
          <?php if(count($produkTerbaru) > 0): ?>
            <?php foreach($produkTerbaru as $index => $produk): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
              <div class="product-card">
                <div class="position-relative">
                  <img src="<?= !empty($produk['gambar']) ? '../uploads/'.$produk['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                       class="product-img" 
                       alt="<?= htmlspecialchars($produk['nama_buku']) ?>"
                       onerror="this.src='../assets/img/book-placeholder.png'">
                  <span class="position-absolute top-0 start-0 bg-success text-white px-2 py-1 m-2 rounded stok-badge">
                    <i class="bi bi-star"></i> Baru
                  </span>
                  <?php if($produk['stok'] < 10 && $produk['stok'] > 0): ?>
                  <span class="position-absolute top-0 end-0 bg-warning text-dark px-2 py-1 m-2 rounded stok-badge">
                    Stok: <?= $produk['stok'] ?>
                  </span>
                  <?php endif; ?>
                </div>
                <div class="p-3">
                  <h6 class="fw-bold mb-1"><?= htmlspecialchars(mb_strimwidth($produk['nama_buku'], 0, 35, '...')) ?></h6>
                  <p class="text-muted mb-1 small"><?= htmlspecialchars(mb_strimwidth($produk['penulis'] ?? '-', 0, 25, '...')) ?></p>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <?php if(isset($produk['kategori'])): ?>
                    <span class="badge bg-purple bg-opacity-10 text-purple"><?= htmlspecialchars($produk['kategori']) ?></span>
                    <?php endif; ?>
                    <span class="seller-badge">
                      <i class="bi bi-shop"></i> <?= htmlspecialchars(mb_strimwidth($produk['nama_penjual'] ?? 'Toko', 0, 15, '...')) ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="price-tag">Rp <?= number_format($produk['harga'],0,',','.') ?></div>
                    <div class="text-muted small">
                      Stok: <?= $produk['stok'] ?? 0 ?>
                    </div>
                  </div>
                  <div class="mt-3">
                    <button class="btn btn-purple btn-sm w-100 add-to-cart" data-id="<?= $produk['id'] ?>">
                      <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12 text-center py-4">
              <i class="bi bi-emoji-frown" style="font-size: 3rem; color: #6c757d;"></i>
              <p class="mt-2">Belum ada produk baru</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- TABEL PESANAN TERBARU -->
  <div class="row fade-in">
    <div class="col-12">
      <div class="card-dark p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h4 class="fw-bold mb-0 section-title">Pesanan Terbaru</h4>
          <a href="pesanan.php" class="btn btn-outline-purple btn-sm">Lihat Semua</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>ID Pesanan</th>
                <th>Tanggal</th>
                <th>Jumlah Item</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($pesananTerbaru) > 0): ?>
                <?php foreach($pesananTerbaru as $pesanan): ?>
                <tr>
                  <td class="fw-semibold">#<?= htmlspecialchars($pesanan['kode_transaksi']) ?></td>
                  <td><?= date('d/m/Y', strtotime($pesanan['created_at'])) ?></td>
                  <td><?= $pesanan['jumlah_item'] ?? 0 ?> item</td>
                  <td>Rp <?= number_format($pesanan['total'] ?? 0,0,',','.') ?></td>
                  <td>
                    <?php
                      $status = $pesanan['status'] ?? 'menunggu';
                      $statusClass = 'bg-secondary';
                      if($status == 'selesai') $statusClass = 'bg-success';
                      if($status == 'approve') $statusClass = 'bg-primary';
                      if($status == 'diproses') $statusClass = 'bg-warning';
                      if($status == 'tolak') $statusClass = 'bg-danger';
                      if($status == 'refund') $statusClass = 'bg-info';
                    ?>
                    <span class="status-badge <?= $statusClass ?> text-white">
                      <?= ucfirst($status) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted">
                    <i class="bi bi-receipt" style="font-size: 2rem;"></i>
                    <p class="mt-2">Belum ada pesanan</p>
                    <a href="produk.php" class="btn btn-purple btn-sm mt-2">Mulai Belanja</a>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Update waktu
  function updateTime() {
    const now = new Date();
    const options = { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    };
    const dateString = now.toLocaleDateString('id-ID', options);
    const timeElement = document.querySelector('.text-muted.me-3');
    if (timeElement) {
      timeElement.textContent = dateString;
    }
  }
  
  updateTime();
  setInterval(updateTime, 60000);
  
  // Search functionality
  const searchBox = document.querySelector('.search-box');
  if (searchBox) {
    searchBox.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        const query = this.value.trim();
        if (query) {
          window.location.href = `produk.php?search=${encodeURIComponent(query)}`;
        }
      }
    });
  }
  
  // Kategori click
  document.querySelectorAll('.kategori-card').forEach(card => {
    card.addEventListener('click', function() {
      const text = this.textContent.trim();
      const kategori = text.split(' ')[0].replace(/\d+/g, '').trim();
      window.location.href = `produk.php?kategori=${encodeURIComponent(kategori)}`;
    });
  });
  
  // Add to cart functionality
  document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
      const productId = this.getAttribute('data-id');
      
      // Tampilkan loading
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="bi bi-hourglass-split"></i> Menambahkan...';
      this.disabled = true;
      
      // Kirim request AJAX
      fetch('ajax/add_to_cart.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&qty=1`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update button
          this.innerHTML = '<i class="bi bi-check-circle"></i> Ditambahkan!';
          this.classList.remove('btn-purple');
          this.classList.add('btn-success');
          
          // Update badge keranjang di sidebar
          const cartBadge = document.querySelector('.sidebar .badge');
          if (cartBadge) {
            const currentCount = parseInt(cartBadge.textContent) || 0;
            cartBadge.textContent = currentCount + 1;
          } else {
            // Jika belum ada badge, buat baru
            const cartLink = document.querySelector('a[href="keranjang.php"]');
            if (cartLink) {
              const badge = document.createElement('span');
              badge.className = 'badge bg-danger float-end';
              badge.textContent = '1';
              cartLink.appendChild(badge);
            }
          }
          
          // Reset button setelah 2 detik
          setTimeout(() => {
            this.innerHTML = originalText;
            this.classList.remove('btn-success');
            this.classList.add('btn-purple');
            this.disabled = false;
          }, 2000);
        } else {
          alert(data.message || 'Gagal menambahkan ke keranjang');
          this.innerHTML = originalText;
          this.disabled = false;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menambahkan ke keranjang');
        this.innerHTML = originalText;
        this.disabled = false;
      });
    });
  });
  
  // Animation on scroll
  const observerOptions = {
    threshold: 0.1
  };
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('fade-in');
      }
    });
  }, observerOptions);
  
  // Observe all sections
  document.querySelectorAll('.row').forEach(section => {
    observer.observe(section);
  });
</script>

</body>
</html>