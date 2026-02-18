<?php
session_start();

// Cek jika session user ada
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Update last_activity setiap halaman diakses
if (isset($_SESSION['user']['id'])) {
    include "../config/database.php";
    
    // Update last_activity di database
    mysqli_query($conn, "
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = '{$_SESSION['user']['id']}'
    ");
    
    // Update timestamp di session
    $_SESSION['user']['last_activity'] = time();
}

// Auto logout jika idle lebih dari 30 menit
if (isset($_SESSION['user']['last_activity'])) {
    $inactive = 1800; // 30 menit dalam detik
    
    // Jika melebihi waktu idle
    if (time() - $_SESSION['user']['last_activity'] > $inactive) {
        // Update status di database
        if (isset($_SESSION['user']['id'])) {
            mysqli_query($conn, "
                UPDATE users 
                SET status = 'offline', 
                    last_activity = NULL 
                WHERE id = '{$_SESSION['user']['id']}'
            ");
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Redirect ke login
        header("Location: ../auth/login.php?expired=1");
        exit;
    }
    
    // Update session activity
    $_SESSION['user']['last_activity'] = time();
}
?>