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

/* =====================
   DATA USER LOGIN
===================== */
$idPenjual = $_SESSION['user']['id'];
$namaUser  = $_SESSION['user']['nama'];

/* =====================
   TOTAL PRODUK
===================== */
$qProduk = mysqli_query($conn,"
  SELECT COUNT(*) AS total_produk,
         SUM(stok) AS total_stok
  FROM produk
  WHERE penjual_id = '$idPenjual'
");
$produk = mysqli_fetch_assoc($qProduk);
$totalProduk = $produk['total_produk'] ?? 0;
$totalStok   = $produk['total_stok'] ?? 0;

/* =====================
   TOTAL TRANSAKSI & OMZET
===================== */
$qTransaksi = mysqli_query($conn,"
  SELECT 
    COUNT(DISTINCT t.id) AS total_transaksi,
    SUM(d.qty * d.harga) AS omzet
  FROM transaksi t
  JOIN transaksi_detail d ON t.id = d.transaksi_id
  JOIN produk p ON d.produk_id = p.id
  WHERE p.penjual_id = '$idPenjual'
    AND t.status = 'selesai'
");
$trx = mysqli_fetch_assoc($qTransaksi);
$totalTransaksi = $trx['total_transaksi'] ?? 0;
$totalOmzet     = $trx['omzet'] ?? 0;

/* =====================
   TRANSAKSI HARI INI
===================== */
$qHariIni = mysqli_query($conn,"
  SELECT 
    COUNT(DISTINCT t.id) AS total,
    SUM(d.qty * d.harga) AS omzet
  FROM transaksi t
  JOIN transaksi_detail d ON t.id = d.transaksi_id
  JOIN produk p ON d.produk_id = p.id
  WHERE p.penjual_id = '$idPenjual'
    AND t.status = 'selesai'
    AND DATE(t.created_at) = CURDATE()
");
$hariIni = mysqli_fetch_assoc($qHariIni);
$trxHariIni  = $hariIni['total'] ?? 0;
$omzetHariIni = $hariIni['omzet'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Penjual</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
  <div class="row">

    <!-- SIDEBAR -->
    <div class="col-2 bg-dark text-white min-vh-100 p-3">
      <h4 class="text-center mb-4">BOOKIE</h4>

      <div class="text-center mb-4">
        <div class="fw-semibold"><?= htmlspecialchars($namaUser) ?></div>
        <small class="text-secondary">Penjual</small>
      </div>

      <ul class="nav flex-column gap-1">
        <li class="nav-item">
          <a class="nav-link text-white fw-bold bg-secondary rounded" href="dashboard.php">
            Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="produk.php">Produk</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="transaksi.php">Transaksi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="profile.php">Profil</a>
        </li>
      </ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4">

      <h3 class="mb-1">Dashboard Penjual</h3>
      <p class="text-muted mb-4">
        Ringkasan aktivitas penjualan Anda
      </p>

      <!-- CARD RINGKASAN -->
      <div class="row g-4">

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Produk</small>
              <h3><?= $totalProduk ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Stok</small>
              <h3><?= $totalStok ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Transaksi</small>
              <h3><?= $totalTransaksi ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Omzet</small>
              <h5>Rp <?= number_format($totalOmzet,0,',','.') ?></h5>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card text-bg-success shadow-sm">
            <div class="card-body">
              <small>Transaksi Hari Ini</small>
              <h3><?= $trxHariIni ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card text-bg-success shadow-sm">
            <div class="card-body">
              <small>Omzet Hari Ini</small>
              <h5>Rp <?= number_format($omzetHariIni,0,',','.') ?></h5>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

</body>
</html>
