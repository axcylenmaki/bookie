<?php
include_once "../config/database.php";

$id = $_SESSION['user']['id'];
$user = mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT nama, email, role, foto FROM users WHERE id='$id'")
);

$foto = (!empty($user['foto']) && file_exists("../uploads/profile/".$user['foto']))
  ? "../uploads/profile/".$user['foto']
  : "../assets/img/user.png";
?>

<div class="container-fluid">
  <div class="row">

    <!-- SIDEBAR -->
    <div class="col-2 bg-dark text-white min-vh-100 p-3">
      <h4 class="text-center mb-4">BOOKIE</h4>

      <div class="text-center mb-4">
        <img src="<?= $foto ?>" class="rounded-circle mb-2" width="80">
        <div class="fw-semibold"><?= $_SESSION['user']['nama'] ?></div>
        <small class="text-secondary"><?= $_SESSION['user']['email'] ?></small>
      </div>

      <ul class="nav flex-column gap-1">
        <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="profile.php">profile</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="users.php?role=penjual">Penjual</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="users.php?role=pembeli">Pembeli</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="kategori.php">Kategori</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="transaksi.php">Transaksi</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="laporan.php">Laporan</a></li>
      </ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>
