<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../../config/database.php";

$idPenjual = $_SESSION['user']['id'];
$penerima_id = $_POST['penerima_id'] ?? 0;
$pesan = mysqli_real_escape_string($conn, $_POST['pesan']);

if ($penerima_id && $pesan) {
    // Dapatkan id_room (gunakan kombinasi id pengirim dan penerima)
    $id_room = min($idPenjual, $penerima_id) . '_' . max($idPenjual, $penerima_id);
    
    $query = "INSERT INTO chat (id_room, id_pengirim, penerima_id, pesan, created_at, dibaca) 
              VALUES ('$id_room', '$idPenjual', '$penerima_id', '$pesan', NOW(), 0)";
    
    if (mysqli_query($conn, $query)) {
        $id = mysqli_insert_id($conn);
        
        // Ambil data pesan yang baru dikirim
        $q = mysqli_query($conn, "
            SELECT c.*, u.nama as pengirim_nama, u.foto as pengirim_foto 
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id
            WHERE c.id = '$id'
        ");
        $message = mysqli_fetch_assoc($q);
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Data tidak lengkap'
    ]);
}
?>