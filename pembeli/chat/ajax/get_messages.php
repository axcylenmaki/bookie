<?php
session_start();
require_once "../../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    exit;
}

$idPembeli = $_SESSION['user']['id'];
$penjual_id = $_GET['penjual_id'] ?? 0;
$last_check = $_GET['last_check'] ?? 0;

if(!$penjual_id) exit;

// Get messages after last check
$last_time = date('Y-m-d H:i:s', $last_check / 1000 - 5); // 5 seconds buffer
$query = "SELECT 
            c.*,
            CASE 
                WHEN c.pengirim_id = '$idPembeli' THEN 'pembeli'
                WHEN c.pengirim_id = 0 THEN 'sistem'
                ELSE 'penjual'
            END as pengirim_role
          FROM chat c
          WHERE ((c.pengirim_id = '$penjual_id' AND c.penerima_id = '$idPembeli')
                 OR (c.pengirim_id = 0 AND c.penerima_id = '$idPembeli'))
            AND c.created_at > '$last_time'
          ORDER BY c.created_at ASC";

$result = mysqli_query($conn, $query);

// Update unread_count to 0 in chat_rooms
mysqli_query($conn, "
    UPDATE chat_rooms 
    SET unread_count = 0 
    WHERE penjual_id='$penjual_id' AND pembeli_id='$idPembeli'
");

while($msg = mysqli_fetch_assoc($result)) {
    $time = date('H:i', strtotime($msg['created_at']));
    $role = $msg['pengirim_role'];
    
    echo '<div class="message-bubble ' . $role . '">';
    echo '<div class="message-content">';
    if($role == 'sistem') {
        echo '<i class="bi bi-info-circle me-1"></i>';
    }
    echo nl2br(htmlspecialchars($msg['pesan']));
    echo '</div>';
    echo '<div class="message-time">' . $time . '</div>';
    echo '</div>';
}
?>