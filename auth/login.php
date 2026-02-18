<?php
session_start();
include "../config/database.php";

// JIKA SUDAH LOGIN
if (isset($_SESSION['user'])) {
  $role = $_SESSION['user']['role'];
  header("Location: ../$role/dashboard.php");
  exit;
}

$error = "";

/* =====================
   PROSES LOGIN
===================== */
if (isset($_POST['login'])) {
  $email    = mysqli_real_escape_string($conn, $_POST['email']);
  $password = $_POST['password'];

  $query = mysqli_query($conn, "
    SELECT * FROM users 
    WHERE email='$email' AND aktif='ya'
    LIMIT 1
  ");

  if (mysqli_num_rows($query) == 1) {
    $user = mysqli_fetch_assoc($query);

    if (password_verify($password, $user['password'])) {

      // SET SESSION
      $_SESSION['user'] = [
        'id'    => $user['id'],
        'nama'  => $user['nama'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'last_activity' => time() // Tambah timestamp session
      ];

      // SET STATUS ONLINE dengan last_activity
      mysqli_query($conn, "
        UPDATE users 
        SET status = 'online', 
            last_activity = NOW() 
        WHERE id = '{$user['id']}'
      ");

      header("Location: ../{$user['role']}/dashboard.php");
      exit;
    } else {
      $error = "Email atau password salah!";
    }
  } else {
    $error = "Email atau password salah!";
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login | Bookie</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
  <div class="row justify-content-center align-items-center vh-100">
    <div class="col-md-4">

      <div class="card shadow">
        <div class="card-body p-4">

          <h4 class="text-center mb-4 fw-bold">LOGIN BOOKIE</h4>

          <?php if ($error): ?>
            <div class="alert alert-danger text-center">
              <?= $error ?>
            </div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" name="login" class="btn btn-primary w-100">
              Login
            </button>
          </form>

          <hr>

          <div class="text-center">
            <small>
              Lupa password?
              <a href="forgot_password.php">Klik di sini</a>
            </small>
            <br>
            <small>
              Belum punya akun?
              <a href="register.php">Daftar</a>
            </small>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>