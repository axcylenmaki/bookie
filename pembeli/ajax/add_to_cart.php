<?php
// bookie/pembeli/ajax/add_to_cart.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => ''];

try {
    // 1. Validasi session
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
        throw new Exception('Silakan login terlebih dahulu');
    }
    
    // 2. Validasi input
    $product_id = intval($_POST['product_id'] ?? 0);
    if ($product_id <= 0) throw new Exception('Produk tidak valid');
    
    // 3. Cari file database.php
    // Coba beberapa lokasi
    $base_dir = dirname(__DIR__, 2); // Dua level naik: C:/xampp/htdocs/bookie/
    $config_file = $base_dir . '/config/database.php';
    
    if (!file_exists($config_file)) {
        // Coba lokasi lain
        $config_file = dirname(__DIR__) . '/../config/database.php';
    }
    
    if (!file_exists($config_file)) {
        throw new Exception('File konfigurasi tidak ditemukan di: ' . $config_file);
    }
    
    // 4. Include config
    require_once $config_file;
    
    if (!isset($conn)) {
        throw new Exception('Koneksi database gagal');
    }
    
    $pembeli_id = $_SESSION['user']['id'];
    
    // 5. Cek produk
    $stmt = $conn->prepare("SELECT id, stok FROM produk WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Produk tidak ditemukan');
    }
    
    $produk = $result->fetch_assoc();
    
    // 6. Cek dan update keranjang
    $stmt2 = $conn->prepare("SELECT id, qty FROM keranjang WHERE pembeli_id = ? AND produk_id = ?");
    $stmt2->bind_param("ii", $pembeli_id, $product_id);
    $stmt2->execute();
    $cart_result = $stmt2->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update
        $cart = $cart_result->fetch_assoc();
        $new_qty = $cart['qty'] + 1;
        
        $stmt3 = $conn->prepare("UPDATE keranjang SET qty = ? WHERE id = ?");
        $stmt3->bind_param("ii", $new_qty, $cart['id']);
        $stmt3->execute();
        
        $response['message'] = 'Jumlah diperbarui';
    } else {
        // Insert baru
        $stmt3 = $conn->prepare("INSERT INTO keranjang (pembeli_id, produk_id, qty) VALUES (?, ?, 1)");
        $stmt3->bind_param("ii", $pembeli_id, $product_id);
        $stmt3->execute();
        
        $response['message'] = 'Ditambahkan ke keranjang';
    }
    
    $response['success'] = true;
    
    // Hitung total item
    $stmt4 = $conn->prepare("SELECT COUNT(*) as total FROM keranjang WHERE pembeli_id = ?");
    $stmt4->bind_param("i", $pembeli_id);
    $stmt4->execute();
    $total_result = $stmt4->get_result();
    $total_data = $total_result->fetch_assoc();
    
    $response['total_items'] = $total_data['total'];
    
    $conn->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
exit;
?>