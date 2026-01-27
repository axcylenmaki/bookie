<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Terima data POST
$penerima_id = $_POST['penerima_id'] ?? 0;
$pesan = trim($_POST['pesan'] ?? '');

if (empty($penerima_id) || empty($pesan)) {
  echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
  exit;
}

// Validasi penerima adalah pembeli
$qPembeli = mysqli_query($conn, "SELECT id FROM users WHERE id='$penerima_id' AND role='pembeli'");
if (mysqli_num_rows($qPembeli) == 0) {
  echo json_encode(['success' => false, 'error' => 'Penerima tidak valid']);
  exit;
}

// Simpan pesan
$pesan = mysqli_real_escape_string($conn, $pesan);
$now = date('Y-m-d H:i:s');

mysqli_query($conn, "
  INSERT INTO chat (pengirim_id, penerima_id, pesan, status, created_at)
  VALUES ('$idPenjual', '$penerima_id', '$pesan', 'terkirim', '$now')
");

$message_id = mysqli_insert_id($conn);

// Ambil data pesan yang baru dikirim
$qMessage = mysqli_query($conn, "
  SELECT c.*, u.nama as pengirim_nama
  FROM chat c
  JOIN users u ON c.pengirim_id = u.id
  WHERE c.id = '$message_id'
");
$message = mysqli_fetch_assoc($qMessage);

echo json_encode([
  'success' => true,
  'message' => [
    'id' => $message['id'],
    'pengirim_id' => $message['pengirim_id'],
    'penerima_id' => $message['penerima_id'],
    'pesan' => $message['pesan'],
    'status' => $message['status'],
    'created_at' => $message['created_at'],
    'pengirim_nama' => $message['pengirim_nama']
  ]
]);
?>