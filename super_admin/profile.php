<?php
session_start();

if (!isset($_SESSION['user'])) {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
include "../includes/header.php";

$id = $_SESSION['user']['id'];
$message = "";

/* =====================
   UPDATE PROFILE
===================== */
if (isset($_POST['update'])) {

  $nama   = mysqli_real_escape_string($conn, $_POST['nama']);
  $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
  $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

  if (!empty($_FILES['foto']['name'])) {

    $folder = "../uploads/profile/";
    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];
    $namaFile = "user_" . $id . "." . $ext;

    if (in_array($ext, $allowed)) {
      move_uploaded_file($_FILES['foto']['tmp_name'], $folder . $namaFile);
      mysqli_query($conn, "
        UPDATE users SET 
          nama='$nama',
          no_hp='$no_hp',
          alamat='$alamat',
          foto='$namaFile'
        WHERE id='$id'
      ");
    } else {
      $message = "Format foto harus JPG atau PNG";
    }

  } else {
    mysqli_query($conn, "
      UPDATE users SET 
        nama='$nama',
        no_hp='$no_hp',
        alamat='$alamat'
      WHERE id='$id'
    ");
  }

  $_SESSION['user']['nama'] = $nama;
  if ($message == "") $message = "Profil berhasil diperbarui";
}

/* DATA USER */
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$id'"));

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
        <div class="fw-semibold"><?= htmlspecialchars($user['nama']) ?></div>
        <small class="text-secondary"><?= ucfirst($user['role']) ?></small>
      </div>

    <ul class="nav flex-column gap-1">

  <li class="nav-item">
    <a class="nav-link text-white" href="dashboard.php">Dashboard</a>
  </li>

  <li class="nav-item">
    <a class="nav-link text-white" href="penjual.php">Penjual</a>
  </li>

  <li class="nav-item">
    <a class="nav-link text-white" href="pembeli.php">Pembeli</a>
  </li>

  <li class="nav-item">
    <a class="nav-link text-white" href="kategori.php">Kategori</a>
  </li>

</ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4 bg-light">

      <h3 class="mb-1">Profil Saya</h3>
      <p class="text-muted mb-4">Kelola data akun Anda</p>

      <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-body">

          <form method="POST" enctype="multipart/form-data">

            <div class="row mb-4 align-items-center">
              <div class="col-md-3 text-center">
                <img src="<?= $foto ?>" class="img-thumbnail mb-2" style="max-width:150px;">
                <input type="file" name="foto" class="form-control form-control-sm mt-2">
              </div>

              <div class="col-md-9">
                <div class="row g-3">

                  <div class="col-md-6">
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" class="form-control"
                      value="<?= htmlspecialchars($user['nama']) ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control"
                      value="<?= htmlspecialchars($user['email']) ?>" readonly>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control"
                      value="<?= strtoupper($user['role']) ?>" readonly>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-control"
                      value="<?= htmlspecialchars($user['no_hp']) ?>">
                  </div>

                  <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($user['alamat']) ?></textarea>
                  </div>

                </div>
              </div>
            </div>

            <div class="text-end">
              <button type="submit" name="update" class="btn btn-primary">
                Simpan Perubahan
              </button>
            </div>

          </form>

        </div>
      </div>

    </div>
  </div>
</div>

<?php include "../includes/footer.php"; ?>
