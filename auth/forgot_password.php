<?php
session_start();
include "../config/database.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoload
require "../vendor/autoload.php";

$msg = "";
$error = "";

if (isset($_POST['submit'])) {

  // 1. Ambil & amankan email
  $email = mysqli_real_escape_string($conn, $_POST['email']);

  // 2. Cari user aktif
  $q = mysqli_query($conn, "
    SELECT id, nama, email 
    FROM users 
    WHERE email='$email' AND aktif='ya'
    LIMIT 1
  ");

  if (mysqli_num_rows($q) !== 1) {
    $error = "Email tidak terdaftar!";
  } else {

    $user = mysqli_fetch_assoc($q);

    // 3. Generate token & expiry
    $token   = bin2hex(random_bytes(32)); // 64 char
$token = bin2hex(random_bytes(32));

mysqli_query($conn, "
  UPDATE users SET 
    reset_token='$token',
    reset_expired = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
  WHERE id={$user['id']}
");


    // 5. Buat link reset
    $link = "http://localhost/bookie/auth/reset_password.php?token=$token";

    // 6. Kirim email
    $mail = new PHPMailer(true);

    try {
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'ayusyafira3003@gmail.com';
      $mail->Password   = 'dmvo fsfe gdss pkae';
      $mail->SMTPSecure = 'tls';
      $mail->Port       = 587;

      $mail->setFrom('ayusyafira3003@gmail.com', 'Bookie');
      $mail->addAddress($user['email'], $user['nama']);

      $mail->isHTML(true);
      $mail->Subject = 'Reset Password Bookie';
      $mail->Body = "
        Halo <b>{$user['nama']}</b>,<br><br>
        Kami menerima permintaan reset password.<br><br>
        Klik link di bawah untuk membuat password baru:<br>
        <a href='$link'>$link</a><br><br>
        <small>Link berlaku selama 15 menit.</small>
      ";

      $mail->send();
      $msg = "Link reset password berhasil dikirim ke email!";
    } catch (Exception $e) {
      $error = "Gagal kirim email: {$mail->ErrorInfo}";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | Bookie</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
  <div class="row justify-content-center align-items-center vh-100">
    <div class="col-md-4">

      <div class="card shadow">
        <div class="card-body p-4">
          <h4 class="text-center fw-bold mb-3">Forgot Password</h4>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php endif; ?>

          <?php if ($msg): ?>
            <div class="alert alert-success"><?= $msg ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label>Email</label>
              <input type="email" name="email" class="form-control" required autofocus>
            </div>

            <button name="submit" class="btn btn-primary w-100">
              Kirim Link Reset
            </button>
          </form>

          <hr>
          <div class="text-center">
            <small><a href="login.php">Kembali ke Login</a></small>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>
