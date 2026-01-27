<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";
$pembeli_id = $_GET['pembeli_id'] ?? 0;

// Cek status online
$qStatus = mysqli_query($conn, "
  SELECT status FROM users 
  WHERE id='$pembeli_id' AND role='pembeli'
");
$statusData = mysqli_fetch_assoc($qStatus);

echo json_encode([
  'success' => true,
  'online' => $statusData['status'] == 'online'
]);
?>