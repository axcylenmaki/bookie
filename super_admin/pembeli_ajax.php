<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header('HTTP/1.0 403 Forbidden');
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

include "../config/database.php";

// Update admin activity
if (isset($_SESSION['user']['id'])) {
    mysqli_query($conn, "
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = '{$_SESSION['user']['id']}'
    ");
}

if (isset($_GET['action']) && $_GET['action'] == 'get_status') {
    $result = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN last_activity IS NOT NULL AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 1 ELSE 0 END) as online_count,
            SUM(CASE WHEN last_activity IS NULL OR TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > 5 THEN 1 ELSE 0 END) as offline_count
        FROM users 
        WHERE role = 'pembeli'
    "));
    
    echo json_encode([
        'success' => true,
        'total' => $result['total'] ?? 0,
        'online' => $result['online_count'] ?? 0,
        'offline' => $result['offline_count'] ?? 0,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>