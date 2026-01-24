<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

$id = $_SESSION['user']['id'];
$namaUser = $_SESSION['user']['nama'];

/* AMBIL DATA PENJUAL */
$q = mysqli_query($conn, "SELECT * FROM users WHERE id=$id");
$user = mysqli_fetch_assoc($q);

/* UPDATE PROFILE */
if (isset($_POST['update'])) {
  $nama   = htmlspecialchars($_POST['nama']);
  $no_hp  = htmlspecialchars($_POST['no_hp']);
  $alamat = htmlspecialchars($_POST['alamat']);

  $foto = $user['foto'];

  if (!empty($_FILES['foto']['name'])) {
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $fotoBaru = "toko_$id.$ext";
    move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/$fotoBaru");
    $foto = $fotoBaru;
  }

  mysqli_query($conn, "
    UPDATE users SET
      nama='$nama',
      no_hp='$no_hp',
      alamat='$alamat',
      foto='$foto'
    WHERE id=$id
  ");

  $_SESSION['user']['nama'] = $nama;
  header("Location: profile.php?success=1");
  exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Profil Penjual</title>
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
          <a class="nav-link text-white" href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="produk.php">Produk</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="transaksi.php">Transaksi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white fw-bold bg-secondary rounded" href="profile.php">
            Profil
          </a>
        </li>
      </ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4">

      <h4 class="mb-3">Profil Toko</h4>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Profil berhasil diperbarui</div>
      <?php endif; ?>

      <div class="row">

        <!-- FOTO TOKO -->
        <div class="col-md-4">
          <div class="card shadow-sm text-center">
            <div class="card-body">
              <img src="../uploads/<?= $user['foto'] ?: 'default.png' ?>"
                   class="rounded mb-3"
                   style="width:150px;height:150px;object-fit:cover">

              <h5><?= htmlspecialchars($user['nama']) ?></h5>
              <small class="text-muted">Penjual</small>
            </div>
          </div>
        </div>

        <!-- FORM -->
        <div class="col-md-8">
          <div class="card shadow-sm">
            <div class="card-body">
              <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                  <label class="form-label">Nama Toko</label>
                  <input type="text" name="nama" class="form-control"
                         value="<?= htmlspecialchars($user['nama']) ?>" required>
                </div>

                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control"
                         value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>

                <div class="mb-3">
                  <label class="form-label">No HP</label>
                  <input type="text" name="no_hp" class="form-control"
                         value="<?= htmlspecialchars($user['no_hp']) ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label">Alamat Toko</label>
                  <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($user['alamat']) ?></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label">Foto / Logo Toko</label>
                  <input type="file" name="foto" class="form-control">
                </div>

                <button name="update" class="btn btn-primary">
                  Simpan Perubahan
                </button>

              </form>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

</body>
</html>
