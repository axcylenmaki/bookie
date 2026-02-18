<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../../config/database.php";

$idPenjual = $_SESSION['user']['id'];
$pembeli_id = $_GET['pembeli_id'] ?? 0;
$last_id = $_GET['last_id'] ?? 0;

if ($pembeli_id) {
    $query = "
        SELECT c.*, u.nama as pengirim_nama, u.foto as pengirim_foto
        FROM chat c
        JOIN users u ON c.id_pengirim = u.id
        WHERE ((c.id_pengirim = '$idPenjual' AND c.penerima_id = '$pembeli_id')
           OR (c.id_pengirim = '$pembeli_id' AND c.penerima_id = '$idPenjual'))
           AND c.id > '$last_id'
        ORDER BY c.created_at ASC
    ";
    
    $result = mysqli_query($conn, $query);
    $messages = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'ID pembeli tidak valid'
    ]);
}
?>