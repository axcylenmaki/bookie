<?php
session_start();
error_reporting(0); // Matikan error reporting untuk production
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Koneksi database
require_once "../../config/database.php";

$pembeli_id = (int)$_SESSION['user']['id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak valid']);
    exit;
}

// Cek stok
$q = mysqli_query($conn, "SELECT stok FROM produk WHERE id='$product_id'");
if (!$q || mysqli_num_rows($q) == 0) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    exit;
}

$produk = mysqli_fetch_assoc($q);
if ($produk['stok'] < $qty) {
    echo json_encode(['success' => false, 'message' => 'Stok tidak cukup']);
    exit;
}

// Cek apakah produk sudah ada di keranjang
$check = mysqli_query($conn, "SELECT id, jumlah FROM keranjang WHERE id_user='$pembeli_id' AND id_produk='$product_id'");

if (mysqli_num_rows($check) > 0) {
    // Update jumlah
    $row = mysqli_fetch_assoc($check);
    $new_qty = $row['jumlah'] + $qty;
    $update = mysqli_query($conn, "UPDATE keranjang SET jumlah='$new_qty' WHERE id='{$row['id']}'");
    
    if ($update) {
        echo json_encode(['success' => true, 'message' => 'Jumlah produk diperbarui']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update keranjang']);
    }
} else {
    // Insert baru
    $insert = mysqli_query($conn, "INSERT INTO keranjang (id_user, id_produk, jumlah) VALUES ('$pembeli_id', '$product_id', '$qty')");
    
    if ($insert) {
        echo json_encode(['success' => true, 'message' => 'Produk ditambahkan ke keranjang']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambah ke keranjang']);
    }
}
?>