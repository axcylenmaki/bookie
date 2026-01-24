<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   DATA USER
===================== */
$namaUser = $_SESSION['user']['nama'];
$roleUser = $_SESSION['user']['role'];

/* =====================
   DATA MASTER
===================== */
$totalPenjual  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role='penjual'"));
$totalPembeli  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role='pembeli'"));
$totalKategori = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kategori"));

/* =====================
   DATA TRANSAKSI
===================== */
$totalTransaksi    = 0;
$totalPendapatan   = 0;
$transaksiHariIni  = 0;
$pendapatanHariIni = 0;

$cekTabel = mysqli_query($conn, "SHOW TABLES LIKE 'transaksi'");
if (mysqli_num_rows($cekTabel) > 0) {

  $totalTransaksi = mysqli_num_rows(
    mysqli_query($conn, "SELECT id FROM transaksi WHERE status='selesai'")
  );

  $qPendapatan = mysqli_query(
    $conn, "SELECT SUM(total) AS pendapatan FROM transaksi WHERE status='selesai'"
  );
  $totalPendapatan = mysqli_fetch_assoc($qPendapatan)['pendapatan'] ?? 0;

  $qHariIni = mysqli_query(
    $conn,
    "SELECT COUNT(id) AS total, SUM(total) AS pendapatan
     FROM transaksi
     WHERE status='selesai'
     AND DATE(created_at)=CURDATE()"
  );
  $hariIni = mysqli_fetch_assoc($qHariIni);
  $transaksiHariIni  = $hariIni['total'] ?? 0;
  $pendapatanHariIni = $hariIni['pendapatan'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Super Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
  <div class="row">

    <!-- SIDEBAR -->
    <div class="col-2 bg-dark text-white min-vh-100 p-3">
      <h4 class="text-center mb-4">BOOKIE</h4>

      <div class="text-center mb-4">
        <div class="fw-semibold"><?= $namaUser ?></div>
        <small class="text-secondary"><?= ucfirst(str_replace('_',' ',$roleUser)) ?></small>
      </div>

      <ul class="nav flex-column gap-1">
        <li><a class="nav-link text-white fw-bold bg-secondary rounded" href="dashboard.php">Dashboard</a></li>
        <li><a class="nav-link text-white" href="penjual.php">Penjual</a></li>
        <li><a class="nav-link text-white" href="pembeli.php">Pembeli</a></li>
        <li><a class="nav-link text-white" href="kategori.php">Kategori</a></li>
      </ul>

      <a href="../auth/logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4">

      <h3 class="mb-1">Dashboard</h3>
      <p class="text-muted mb-4">Ringkasan data sistem Bookie</p>

      <div class="row g-4">

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Penjual</small>
              <h3><?= $totalPenjual ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Pembeli</small>
              <h3><?= $totalPembeli ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card shadow-sm">
            <div class="card-body">
              <small class="text-muted">Total Kategori</small>
              <h3><?= $totalKategori ?></h3>
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
              <small class="text-muted">Pendapatan Total</small>
              <h5>Rp <?= number_format($totalPendapatan,0,',','.') ?></h5>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card text-bg-success shadow-sm">
            <div class="card-body">
              <small>Transaksi Hari Ini</small>
              <h3><?= $transaksiHariIni ?></h3>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card text-bg-success shadow-sm">
            <div class="card-body">
              <small>Pendapatan Hari Ini</small>
              <h5>Rp <?= number_format($pendapatanHariIni,0,',','.') ?></h5>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

</body>
</html>
