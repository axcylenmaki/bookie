<?php
session_start();
include "../config/database.php";

$email    = mysqli_real_escape_string($conn, $_POST['email']);
$password = $_POST['password'];

$query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND aktif='ya'");
$user  = mysqli_fetch_assoc($query);

if ($user && password_verify($password, $user['password'])) {

  // set session
  $_SESSION['user'] = [
    'id'    => $user['id'],
    'nama'  => $user['nama'],
    'email' => $user['email'],
    'role'  => $user['role']
  ];

  // update status online
  mysqli_query($conn, "UPDATE users SET status='online' WHERE id=".$user['id']);

  // redirect sesuai role
  if ($user['role'] == 'super_admin') {
    header("Location: ../super_admin/dashboard.php");
  } elseif ($user['role'] == 'penjual') {
    header("Location: ../penjual/dashboard.php");
  } else {
    header("Location: ../pembeli/dashboard.php");
  }
  exit;

} else {
  header("Location: login.php?error=1");
  exit;
}
