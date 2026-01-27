<?php
session_start();
include "../config/database.php";

$error = "";
$success = "";

if (isset($_POST['register'])) {
  $role     = $_POST['role'];
  $nama     = mysqli_real_escape_string($conn, $_POST['nama']);
  $email    = mysqli_real_escape_string($conn, $_POST['email']);
  $password = $_POST['password'];
  $confirm  = $_POST['confirm'];

  // VALIDASI ROLE
  if (!in_array($role, ['penjual','pembeli'])) {
    $error = "Role tidak valid!";
  } elseif ($password !== $confirm) {
    $error = "Password tidak sama!";
  } else {

    // CEK EMAIL
    $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if (mysqli_num_rows($cek) > 0) {
      $error = "Email sudah terdaftar!";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      mysqli_query($conn, "
        INSERT INTO users (role, nama, email, password, status, aktif)
        VALUES ('$role','$nama','$email','$hash','offline','ya')
      ");

      $success = "Registrasi berhasil! Silakan login.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Register | Bookie</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
  <div class="row justify-content-center vh-100 align-items-center">
    <div class="col-md-5">

      <div class="card shadow">
        <div class="card-body p-4">
          <h4 class="text-center fw-bold mb-3">REGISTER BOOKIE</h4>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label>Role</label>
              <select name="role" class="form-select" required>
                <option value="">-- Pilih Role --</option>
                <option value="penjual">Penjual</option>
                <option value="pembeli">Pembeli</option>
              </select>
            </div>

            <div class="mb-3">
              <label>Nama</label>
              <input type="text" name="nama" class="form-control" required>
            </div>

            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
              <label>Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
              <label>Konfirmasi Password</label>
              <input type="password" name="confirm" class="form-control" required>
            </div>

            <button name="register" class="btn btn-primary w-100">
              Register
            </button>
          </form>

          <hr>
          <div class="text-center">
            <small>Sudah punya akun? <a href="login.php">Login</a></small>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>
