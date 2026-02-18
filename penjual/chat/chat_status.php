<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../../config/database.php";

$pembeli_id = $_GET['pembeli_id'] ?? 0;

if ($pembeli_id) {
    $q = mysqli_query($conn, "SELECT status, TIMESTAMPDIFF(SECOND, last_activity, NOW()) as last_seen FROM users WHERE id='$pembeli_id'");
    $user = mysqli_fetch_assoc($q);
    
    $online = $user['status'] == 'online' || ($user['last_seen'] !== null && $user['last_seen'] < 300);
    
    echo json_encode([
        'success' => true,
        'online' => $online
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'ID pembeli tidak valid'
    ]);
}
?>