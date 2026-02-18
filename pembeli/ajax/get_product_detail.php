<?php
session_start();
error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once "../../config/database.php";

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product ID tidak valid']);
    exit;
}

$query = "SELECT 
    p.*,
    k.nama_kategori,
    u.nama as nama_penjual,
    u.status as status_penjual
FROM produk p
LEFT JOIN kategori k ON p.kategori_id = k.id
LEFT JOIN users u ON p.id_penjual = u.id
WHERE p.id = '$product_id'";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

$product = mysqli_fetch_assoc($result);

echo json_encode([
    'success' => true,
    'product' => $product
]);
?>