<?php
include "../config/database.php";

$token = $_GET['token'] ?? '';
$error = "";
$success = "";

$q = mysqli_query($conn, "
  SELECT * FROM users 
  WHERE reset_token='$token' 
  AND reset_expired > NOW()
");

if (mysqli_num_rows($q) != 1) {
  die("Link reset tidak valid atau sudah kadaluarsa.");
}

$user = mysqli_fetch_assoc($q);

if (isset($_POST['reset'])) {
  $pass = $_POST['password'];
  $confirm = $_POST['confirm'];

  if ($pass !== $confirm) {
    $error = "Password tidak sama!";
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    mysqli_query($conn, "
      UPDATE users SET 
      password='$hash',
      reset_token=NULL,
      reset_expired=NULL
      WHERE id={$user['id']}
    ");

    header("Location: login.php?reset=success");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
  <div class="row justify-content-center vh-100 align-items-center">
    <div class="col-md-4">

      <div class="card shadow">
        <div class="card-body p-4">
          <h4 class="text-center fw-bold">Reset Password</h4>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label>Password Baru</label>
              <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
              <label>Konfirmasi Password</label>
              <input type="password" name="confirm" class="form-control" required>
            </div>

            <button name="reset" class="btn btn-primary w-100">
              Reset Password
            </button>
          </form>

        </div>
      </div>

    </div>
  </div>
</div>

</body>
</html>
