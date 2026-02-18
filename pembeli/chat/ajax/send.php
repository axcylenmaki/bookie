<?php
session_start();
require_once "../../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'];
$penjual_id = $_POST['penjual_id'] ?? 0;
$pesan = $_POST['pesan'] ?? '';

if(!$penjual_id || empty($pesan)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

$pesan = mysqli_real_escape_string($conn, $pesan);

// Insert chat
mysqli_query($conn, "
    INSERT INTO chat (pengirim_id, penerima_id, pesan)
    VALUES ('$idPembeli', '$penjual_id', '$pesan')
");

// Update chat_rooms
$checkRoom = mysqli_query($conn, "
    SELECT id FROM chat_rooms 
    WHERE penjual_id='$penjual_id' AND pembeli_id='$idPembeli'
");

if(mysqli_num_rows($checkRoom) > 0) {
    mysqli_query($conn, "
        UPDATE chat_rooms 
        SET last_message = '$pesan', 
            last_message_at = NOW(),
            unread_count = unread_count + 1
        WHERE penjual_id='$penjual_id' AND pembeli_id='$idPembeli'
    ");
} else {
    mysqli_query($conn, "
        INSERT INTO chat_rooms (penjual_id, pembeli_id, last_message)
        VALUES ('$penjual_id', '$idPembeli', '$pesan')
    ");
}

// Notification for seller
mysqli_query($conn, "
    INSERT INTO notifikasi (user_id, title, pesan, link, type)
    VALUES (
        '$penjual_id',
        'Pesan Baru',
        '$namaPembeli: " . substr($pesan, 0, 50) . "...',
        '../penjual/chat/index.php?pembeli_id=$idPembeli',
        'chat'
    )
");

echo json_encode(['success' => true]);
?>