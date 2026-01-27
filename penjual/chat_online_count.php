<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";

// Hitung pembeli online
$qOnline = mysqli_query($conn, "
  SELECT COUNT(*) as online_count
  FROM users
  WHERE role = 'pembeli'
  AND status = 'online'
");
$onlineData = mysqli_fetch_assoc($qOnline);

echo json_encode([
  'success' => true,
  'count' => $onlineData['online_count'] ?? 0
]);
?>