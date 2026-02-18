<?php
session_start();
require_once "../../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$idPembeli = $_SESSION['user']['id'];
$penjual_id = isset($_GET['penjual_id']) ? (int)$_GET['penjual_id'] : 0;

if (!$penjual_id) {
    echo json_encode(['has_new' => false]);
    exit;
}

// Cek apakah ada pesan baru dari penjual ini
$query = "SELECT COUNT(*) as new_count 
          FROM chat c
          JOIN chat_rooms cr ON c.id_room = cr.id
          WHERE cr.id_pembeli = '$idPembeli' 
            AND cr.id_penjual = '$penjual_id'
            AND c.id_pengirim = '$penjual_id'
            AND c.dibaca = 0";

$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

echo json_encode([
    'has_new' => ($data['new_count'] > 0),
    'count' => $data['new_count']
]);
?>