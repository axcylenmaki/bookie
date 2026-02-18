<?php
session_start();
include "../config/database.php";

if (isset($_SESSION['user']['id'])) {
    $id = (int)$_SESSION['user']['id'];
    mysqli_query($conn, "
        UPDATE users 
        SET status = 'offline', 
            last_activity = NULL 
        WHERE id = '$id'
    ");
}

session_unset();
session_destroy();
header("Location: ../auth/login.php");
exit;
?>