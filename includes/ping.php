<?php
session_start();

if (isset($_SESSION['user']['id'])) {
    include "../config/database.php";
    
    // Update last_activity
    mysqli_query($conn, "
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = '{$_SESSION['user']['id']}'
    ");
    
    // Update session timestamp
    $_SESSION['user']['last_activity'] = time();
    
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
} else {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
}
?>