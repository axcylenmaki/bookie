<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Log request
error_log("Update cart request - POST: " . print_r($_POST, true));

// Cek login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// Koneksi database
require_once "../../config/database.php";

// Ambil data POST
$cart_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;

// Log data
error_log("Cart ID: $cart_id, Quantity: $qty");

// Validasi
if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID keranjang tidak valid']);
    exit;
}

if ($qty < 1) {
    echo json_encode(['success' => false, 'message' => 'Jumlah tidak boleh kurang dari 1']);
    exit;
}

// Cek apakah keranjang milik user yang login
$check = mysqli_query($conn, "SELECT k.*, p.stok, p.nama_produk FROM keranjang k 
                               JOIN produk p ON k.id_produk = p.id 
                               WHERE k.id='$cart_id' AND k.id_user='{$_SESSION['user']['id']}'");

if (!$check) {
    error_log("Error query: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Error database: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($check) == 0) {
    echo json_encode(['success' => false, 'message' => 'Keranjang tidak ditemukan atau bukan milik Anda']);
    exit;
}

$cart = mysqli_fetch_assoc($check);
error_log("Cart data: " . print_r($cart, true));

// Cek stok
if ($qty > $cart['stok']) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi. Maksimal ' . $cart['stok']]);
    exit;
}

// Update jumlah
$update = mysqli_query($conn, "UPDATE keranjang SET jumlah='$qty' WHERE id='$cart_id'");

if ($update) {
    echo json_encode(['success' => true, 'message' => 'Kuantitas berhasil diperbarui']);
} else {
    error_log("Error update: " . mysqli_error($conn));
    echo json_encode(['success' => false, 'message' => 'Gagal update database: ' . mysqli_error($conn)]);
}
?>