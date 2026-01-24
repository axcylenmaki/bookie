<?php
session_start();
include "../config/database.php";

// cek dulu ada session user atau tidak
if (isset($_SESSION['user']['id'])) {
  $id = (int) $_SESSION['user']['id'];

  // set status offline
  mysqli_query(
    $conn,
    "UPDATE users SET status='offline' WHERE id='$id'"
  );
}

// hancurkan session
session_unset();
session_destroy();

// arahkan ke halaman login
header("Location: ../auth/login.php");
exit;
