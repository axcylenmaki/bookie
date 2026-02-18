<?php
session_start();
require_once "../../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    exit;
}

$idPembeli = $_SESSION['user']['id'];
$penjual_id = $_GET['penjual_id'] ?? 0;

if($penjual_id) {
    // Update chat_rooms unread_count to 0
    mysqli_query($conn, "
        UPDATE chat_rooms 
        SET unread_count = 0 
        WHERE penjual_id='$penjual_id' AND pembeli_id='$idPembeli'
    ");
}
?>