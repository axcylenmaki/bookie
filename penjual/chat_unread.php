<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Hitung total pesan belum dibaca
$qUnread = mysqli_query($conn, "
  SELECT COUNT(*) as total_unread
  FROM chat
  WHERE penerima_id = '$idPenjual'
  AND status = 'terkirim'
");
$unreadData = mysqli_fetch_assoc($qUnread);

echo json_encode([
  'success' => true,
  'total_unread' => $unreadData['total_unread'] ?? 0
]);
?>