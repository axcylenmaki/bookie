<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Parameter
$pembeli_id = $_GET['pembeli_id'] ?? 0;
$last_id = $_GET['last_id'] ?? 0;

if (empty($pembeli_id)) {
  echo json_encode(['success' => false, 'error' => 'Pembeli ID required']);
  exit;
}

// Ambil pesan baru
$query = "
  SELECT c.*, u.nama as pengirim_nama
  FROM chat c
  JOIN users u ON c.pengirim_id = u.id
  WHERE ((c.pengirim_id = '$idPenjual' AND c.penerima_id = '$pembeli_id')
     OR (c.pengirim_id = '$pembeli_id' AND c.penerima_id = '$idPenjual'))
  AND c.id > '$last_id'
  ORDER BY c.created_at ASC
";

$qMessages = mysqli_query($conn, $query);
$messages = [];

while ($msg = mysqli_fetch_assoc($qMessages)) {
  $messages[] = [
    'id' => $msg['id'],
    'pengirim_id' => $msg['pengirim_id'],
    'penerima_id' => $msg['penerima_id'],
    'pesan' => $msg['pesan'],
    'status' => $msg['status'],
    'created_at' => $msg['created_at'],
    'pengirim_nama' => $msg['pengirim_nama']
  ];
}

// Update status pesan yang diterima jadi dibaca
if (!empty($messages)) {
  $newMessagesFromPembeli = array_filter($messages, function($msg) use ($idPenjual) {
    return $msg['pengirim_id'] != $idPenjual && $msg['status'] == 'terkirim';
  });
  
  if (!empty($newMessagesFromPembeli)) {
    $messageIds = array_column($newMessagesFromPembeli, 'id');
    $idsString = implode(',', $messageIds);
    
    mysqli_query($conn, "
      UPDATE chat SET status='dibaca' 
      WHERE id IN ($idsString) 
      AND penerima_id='$idPenjual'
    ");
  }
}

// Cek status online pembeli
$qStatus = mysqli_query($conn, "
  SELECT status FROM users 
  WHERE id='$pembeli_id' AND role='pembeli'
");
$statusData = mysqli_fetch_assoc($qStatus);
$onlineStatus = $statusData['status'] == 'online';

echo json_encode([
  'success' => true,
  'messages' => $messages,
  'online_status' => $onlineStatus,
  'count' => count($messages)
]);
?>