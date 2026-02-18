<?php
session_start();
error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

require_once "../../config/database.php";

$idPembeli = (int)$_SESSION['user']['id'];

$query = mysqli_query($conn, "SELECT SUM(jumlah) as total FROM keranjang WHERE id_user='$idPembeli'");

if (!$query) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

$result = mysqli_fetch_assoc($query);

echo json_encode([
    'success' => true,
    'count' => (int)($result['total'] ?? 0)
]);
?>