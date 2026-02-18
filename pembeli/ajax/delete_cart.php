<?php
session_start();
error_reporting(0);
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

// Koneksi database
require_once "../../config/database.php";

// Ambil data POST
$cart_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Debug
error_log("Delete cart - ID: $cart_id");

// Validasi
if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID keranjang tidak valid']);
    exit;
}

// Cek apakah keranjang milik user yang login
$check = mysqli_query($conn, "SELECT id FROM keranjang WHERE id='$cart_id' AND id_user='{$_SESSION['user']['id']}'");

if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['success' => false, 'message' => 'Keranjang tidak ditemukan']);
    exit;
}

// Hapus
$delete = mysqli_query($conn, "DELETE FROM keranjang WHERE id='$cart_id'");

if ($delete) {
    echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal hapus database: ' . mysqli_error($conn)]);
}
?>