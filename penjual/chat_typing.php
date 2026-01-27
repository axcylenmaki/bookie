<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Ambil data JSON
$data = json_decode(file_get_contents('php://input'), true);
$pembeli_id = $data['pembeli_id'] ?? 0;
$typing = $data['typing'] ?? false;

// Simpan status typing ke database atau session
// (Untuk implementasi real, butuh tabel tersendiri atau Redis)
$_SESSION['typing_' . $pembeli_id . '_' . $idPenjual] = $typing ? time() : 0;

echo json_encode(['success' => true]);
?>