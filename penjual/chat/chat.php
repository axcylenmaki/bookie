<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

$baseDir = dirname(__FILE__);
include $baseDir . "/../../config/database.php";

$idPenjual = $_SESSION['user']['id'];
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';
$fotoUser = $_SESSION['user']['foto'] ?? '';

// Path foto penjual
$fotoPath = (!empty($fotoUser) && file_exists(dirname(dirname(dirname(__FILE__))) . "/uploads/" . $fotoUser))
    ? "../../uploads/" . $fotoUser
    : "../../assets/img/user.png";

// Update status online user
mysqli_query($conn, "UPDATE users SET status='online', last_activity=NOW() WHERE id='$idPenjual'");

// Query daftar pembeli - dengan kolom yang benar: id_pengirim, penerima_id, dibaca
$qChatPartners = mysqli_query($conn, "
    SELECT DISTINCT 
        u.id as pembeli_id,
        u.nama as pembeli_nama,
        u.foto as pembeli_foto,
        u.status as pembeli_status,
        u.last_activity as pembeli_last_seen,
        (SELECT COUNT(*) FROM chat WHERE id_pengirim = u.id AND penerima_id = '$idPenjual' AND dibaca = 0) as unread_count,
        (SELECT pesan FROM chat 
         WHERE (id_pengirim = u.id AND penerima_id = '$idPenjual') 
            OR (id_pengirim = '$idPenjual' AND penerima_id = u.id) 
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM chat 
         WHERE (id_pengirim = u.id AND penerima_id = '$idPenjual') 
            OR (id_pengirim = '$idPenjual' AND penerima_id = u.id) 
         ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM chat c
    JOIN users u ON (c.id_pengirim = u.id OR c.penerima_id = u.id)
    WHERE (c.id_pengirim = '$idPenjual' OR c.penerima_id = '$idPenjual')
        AND u.id != '$idPenjual'
        AND u.role = 'pembeli'
    GROUP BY u.id
    ORDER BY unread_count DESC, last_message_time DESC
");

// Ambil chat dengan pembeli tertentu
$pembeli_id = $_GET['pembeli_id'] ?? 0;
$pembeliData = null;
$chatMessages = [];

if ($pembeli_id) {
    $qPembeli = mysqli_query($conn, "SELECT *, TIMESTAMPDIFF(SECOND, last_activity, NOW()) as last_seen_seconds FROM users WHERE id='$pembeli_id' AND role='pembeli'");
    $pembeliData = mysqli_fetch_assoc($qPembeli);
    
    if ($pembeliData) {
        // Ambil pesan dengan status baca
        $qChat = mysqli_query($conn, "
            SELECT c.*, u.nama as pengirim_nama, u.foto as pengirim_foto
            FROM chat c
            JOIN users u ON c.id_pengirim = u.id
            WHERE (c.id_pengirim = '$idPenjual' AND c.penerima_id = '$pembeli_id')
               OR (c.id_pengirim = '$pembeli_id' AND c.penerima_id = '$idPenjual')
            ORDER BY c.created_at ASC
        ");
        
        while ($msg = mysqli_fetch_assoc($qChat)) {
            $chatMessages[] = $msg;
        }
        
        // Update status jadi dibaca
        mysqli_query($conn, "
            UPDATE chat SET dibaca = 1 
            WHERE penerima_id = '$idPenjual' 
            AND id_pengirim = '$pembeli_id'
            AND dibaca = 0
        ");
    }
}

// Hitung total unread
$totalUnread = 0;
$qUnread = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM chat 
    WHERE penerima_id = '$idPenjual' 
    AND dibaca = 0
");
if ($qUnread) {
    $result = mysqli_fetch_assoc($qUnread);
    $totalUnread = $result['total'] ?? 0;
}

// Hitung jumlah pembeli online
$onlineCount = 0;
$qOnline = mysqli_query($conn, "
    SELECT COUNT(DISTINCT u.id) as online_count
    FROM users u
    WHERE u.role = 'pembeli'
        AND u.status = 'online'
        AND u.id IN (
            SELECT DISTINCT id_pengirim FROM chat WHERE penerima_id = '$idPenjual'
            UNION
            SELECT DISTINCT penerima_id FROM chat WHERE id_pengirim = '$idPenjual'
        )
");
if ($qOnline) {
    $result = mysqli_fetch_assoc($qOnline);
    $onlineCount = $result['online_count'] ?? 0;
}

// Hitung jumlah total pembeli yang pernah chat
$totalChatPartners = $qChatPartners ? mysqli_num_rows($qChatPartners) : 0;

// Fungsi untuk mendapatkan path foto yang benar
function getFotoPath($foto, $baseDir) {
    if (empty($foto) || $foto === 'user.png') {
        return '../../assets/img/user.png';
    }
    
    $uploadPath = dirname(dirname(dirname(__FILE__))) . '/uploads/' . $foto;
    if (file_exists($uploadPath)) {
        return '../../uploads/' . htmlspecialchars($foto);
    }
    
    return '../../assets/img/user.png';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - BOOKIE</title>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        /* =====================
           RESET & GLOBAL
        ===================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }

        /* =====================
           SIDEBAR
        ===================== */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        /* LOGO */
        .sidebar-logo {
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 1px;
            color: #fff;
        }

        /* PROFIL */
        .sidebar-profile {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: #fff;
            transition: all 0.3s ease;
        }

        .sidebar-profile:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar-profile img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .sidebar-profile .name {
            font-size: 16px;
            font-weight: 600;
        }

        .sidebar-profile .role {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 2px;
        }

        /* MENU */
        .sidebar-menu {
            flex: 1;
            padding: 15px 0;
            overflow-y: auto;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 20px;
            color: #bdc3c7;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 2px 10px;
            border-radius: 8px;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateX(8px);
        }

        .menu-item.active {
            background: linear-gradient(90deg, #3498db, #2980b9);
            color: #fff;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .menu-item i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .menu-badge {
            margin-left: auto;
            background: #e74c3c;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* FOOTER */
        .sidebar-footer {
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-bottom: 8px;
            border: none;
            cursor: pointer;
            width: 100%;
        }

        .logout {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
            color: white;
        }

        .logout:hover {
            background: linear-gradient(90deg, #c0392b, #a93226);
            transform: translateY(-2px);
        }

        .help {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }

        .help:hover {
            background: rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        /* =====================
           TOP BAR
        ===================== */
        .top-bar {
            position: fixed;
            top: 0;
            right: 0;
            left: 260px;
            height: 70px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 999;
            transition: left 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* Search Bar */
        .search-container {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-box {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .search-box:focus {
            outline: none;
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 18px;
        }

        /* Top Bar Right */
        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* User Profile in Top Bar */
        .user-profile-top {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: #1e293b;
        }

        .user-profile-top:hover {
            background: #f1f5f9;
        }

        .user-profile-top img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }

        .user-info-top .name {
            font-size: 14px;
            font-weight: 600;
        }

        .user-info-top .role {
            font-size: 12px;
            color: #64748b;
        }

        /* =====================
           MAIN CONTENT
        ===================== */
        .main-content {
            flex: 1;
            margin-left: 260px;
            margin-top: 70px;
            padding: 24px;
            min-height: calc(100vh - 70px);
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        /* =====================
           HERO SECTION
        ===================== */
        .hero {
            background: linear-gradient(135deg, #020617, #1e3a8a);
            color: #fff;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .hero h2 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
        }

        .hero p {
            max-width: 520px;
            opacity: .9;
            font-size: 14px;
            line-height: 1.5;
        }

        /* =====================
           CHAT CONTAINER
        ===================== */
        .chat-container {
            display: flex;
            height: calc(100vh - 220px);
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        /* =====================
           CHAT SIDEBAR (DAFTAR PEMBELI)
        ===================== */
        .chat-sidebar {
            width: 350px;
            border-right: 1px solid #e5e7eb;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #fafafa;
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }

        .user-search {
            margin-top: 12px;
        }

        .user-search input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239CA3AF' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 14px center;
            transition: all 0.2s;
        }

        .user-search input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .users-list {
            flex: 1;
            overflow-y: auto;
        }

        .chat-user {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .chat-user:hover {
            background: #f9fafb;
        }

        .chat-user.active {
            background: #eff6ff;
            border-left: 3px solid #3498db;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 2px solid #e5e7eb;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
            color: #111827;
            margin-bottom: 4px;
        }

        .last-message {
            font-size: 13px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .last-time {
            font-size: 11px;
            color: #9ca3af;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }

        .online-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .online-status.online {
            background-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .online-status.offline {
            background-color: #9ca3af;
        }

        /* =====================
           CHAT MAIN AREA
        ===================== */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fafafa;
        }

        .chat-header {
            padding: 16px 24px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-partner {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .partner-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .partner-info h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }

        .partner-info p {
            margin: 0;
            font-size: 13px;
            color: #6b7280;
        }

        .chat-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .message-date {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .date-label {
            display: inline-block;
            background: #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 500;
            padding: 4px 12px;
            border-radius: 12px;
        }

        .message {
            display: flex;
            margin-bottom: 16px;
            animation: fadeIn 0.3s ease;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message.received {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 65%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            line-height: 1.4;
        }

        .message.received .message-content {
            background: #fff;
            color: #111827;
            border: 1px solid #e5e7eb;
            border-top-left-radius: 4px;
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border-top-right-radius: 4px;
        }

        .message-time {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
            justify-content: flex-end;
        }

        .message-status {
            font-size: 12px;
        }

        .chat-input-area {
            padding: 20px 24px;
            background: #fff;
            border-top: 1px solid #e5e7eb;
        }

        .chat-input-container {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .message-input-container {
            flex: 1;
            position: relative;
        }

        #messageInput {
            width: 100%;
            min-height: 44px;
            max-height: 120px;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 22px;
            font-size: 14px;
            line-height: 1.4;
            resize: none;
            background: #f9fafb;
            transition: all 0.2s;
        }

        #messageInput:focus {
            outline: none;
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .send-button {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .send-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* =====================
           EMPTY STATES
        ===================== */
        .empty-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 40px;
            color: #6b7280;
        }

        .empty-chat-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty-chat h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .empty-chat p {
            margin: 0;
            font-size: 14px;
            text-align: center;
            max-width: 400px;
        }

        /* =====================
           RESPONSIVE DESIGN
        ===================== */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .top-bar {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: flex !important;
            }
            
            .sidebar-logo span,
            .sidebar-profile > div,
            .menu-item span,
            .menu-badge,
            .footer-btn span {
                display: none;
            }
            
            .sidebar.active .sidebar-logo span,
            .sidebar.active .sidebar-profile > div,
            .sidebar.active .menu-item span,
            .sidebar.active .menu-badge,
            .sidebar.active .footer-btn span {
                display: block;
            }
            
            .search-container {
                display: none;
            }
            
            .search-container.active {
                display: block;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                padding: 10px;
                background: white;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            }
            
            .chat-container {
                flex-direction: column;
                height: auto;
                min-height: calc(100vh - 240px);
            }
            
            .chat-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
                max-height: 300px;
            }
            
            .chat-main {
                min-height: 400px;
            }
            
            .chat-header {
                flex-wrap: wrap;
            }
        }

        /* =====================
           UTILITY CLASSES
        ===================== */
        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 20px;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #e2e8f0;
            color: #3498db;
        }

        .search-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: none;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 20px;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .search-toggle:hover {
            background: #f1f5f9;
            color: #3498db;
        }

        .d-none {
            display: none !important;
        }

        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <!-- LOGO -->
    <div class="sidebar-logo">
        <span>📚 BOOKIE</span>
    </div>

    <!-- PROFIL -->
    <a href="../profile.php" class="sidebar-profile">
        <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile" onerror="this.onerror=null; this.src='../../assets/img/user.png'">
        <div>
            <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
            <div class="role">Penjual</div>
        </div>
    </a>

    <!-- MENU SCROLLABLE -->
    <div class="sidebar-menu">
        <a href="../dashboard.php" class="menu-item">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="../profile.php" class="menu-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        
        <a href="../produk.php" class="menu-item">
            <i class="bi bi-box"></i>
            <span>Produk</span>
        </a>
        
        <a href="chat.php" class="menu-item active">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
            <?php if($totalUnread > 0): ?>
                <span class="menu-badge"><?= $totalUnread ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../pesanan.php" class="menu-item">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
        </a>
        
        <a href="../status.php" class="menu-item">
            <i class="bi bi-activity"></i>
            <span>Status</span>
        </a>
        
        <a href="../laporan.php" class="menu-item">
            <i class="bi bi-bar-chart"></i>
            <span>Laporan</span>
        </a>
        
        <a href="../penjual_lain.php" class="menu-item">
            <i class="bi bi-people"></i>
            <span>Penjual Lain</span>
        </a>
    </div>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <button class="footer-btn logout" onclick="logout()">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </button>
        
        <a href="../help.php" class="footer-btn help">
            <i class="bi bi-question-circle"></i>
            <span>Help & FAQ</span>
        </a>
    </div>
</div>

<!-- TOP BAR -->
<div class="top-bar" id="topBar">
    <!-- Menu Toggle (Mobile) -->
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Search Bar -->
    <div class="search-container" id="searchContainer">
        <i class="bi bi-search search-icon"></i>
        <input type="text" class="search-box" placeholder="Cari pembeli...">
    </div>
    
    <!-- Search Toggle (Mobile) -->
    <button class="search-toggle" id="searchToggle">
        <i class="bi bi-search"></i>
    </button>
    
    <!-- Right Section -->
    <div class="top-bar-right">
        <!-- User Profile -->
        <a href="../profile.php" class="user-profile-top">
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile" onerror="this.onerror=null; this.src='../../assets/img/user.png'">
            <div class="user-info-top">
                <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
                <div class="role">Penjual</div>
            </div>
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <!-- HERO -->
    <div class="hero">
        <h2>Pesan</h2>
        <p>Kelola komunikasi dengan pelanggan Anda secara real-time</p>
    </div>

    <!-- CHAT CONTAINER -->
    <div class="chat-container">
        <!-- SIDEBAR: DAFTAR PEMBELI -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h3>Percakapan</h3>
                <div class="user-search">
                    <input type="text" id="searchUser" placeholder="Cari pembeli...">
                </div>
            </div>
            
            <div class="users-list">
                <?php if($qChatPartners && mysqli_num_rows($qChatPartners) > 0): ?>
                    <?php 
                    mysqli_data_seek($qChatPartners, 0);
                    while($partner = mysqli_fetch_assoc($qChatPartners)): 
                        $isActive = $pembeli_id == $partner['pembeli_id'];
                        $isOnline = $partner['pembeli_status'] == 'online' || 
                                   ($partner['pembeli_last_seen'] && strtotime($partner['pembeli_last_seen']) > (time() - 300));
                        $lastSeen = '';
                        
                        if (!$isOnline && $partner['pembeli_last_seen']) {
                            $diff = time() - strtotime($partner['pembeli_last_seen']);
                            if ($diff < 3600) {
                                $lastSeen = round($diff / 60) . ' menit lalu';
                            } elseif ($diff < 86400) {
                                $lastSeen = round($diff / 3600) . ' jam lalu';
                            } else {
                                $lastSeen = date('d M', strtotime($partner['pembeli_last_seen']));
                            }
                        }
                        
                        // Tentukan path foto pembeli
                        $fotoPembeli = getFotoPath($partner['pembeli_foto'], $baseDir);
                    ?>
                    <div class="chat-user <?= $isActive ? 'active' : '' ?>" 
                         data-pembeli-id="<?= $partner['pembeli_id'] ?>"
                         data-user-name="<?= htmlspecialchars($partner['pembeli_nama']) ?>">
                        
                        <img src="<?= htmlspecialchars($fotoPembeli) ?>" 
                             class="user-avatar"
                             onerror="this.onerror=null; this.src='../../assets/img/user.png'"
                             alt="<?= htmlspecialchars($partner['pembeli_nama']) ?>">
                        
                        <div class="user-info">
                            <div class="user-name">
                                <span class="text-truncate"><?= htmlspecialchars($partner['pembeli_nama']) ?></span>
                                <span class="online-status <?= $isOnline ? 'online' : 'offline' ?>"></span>
                            </div>
                            
                            <div class="last-message text-truncate">
                                <?= $partner['last_message'] ? 
                                    mb_strimwidth(htmlspecialchars($partner['last_message']), 0, 30, '...') : 
                                    'Belum ada pesan' ?>
                            </div>
                            
                            <div class="last-time">
                                <?= $isOnline ? 'Online' : ($lastSeen ? 'Terakhir online ' . $lastSeen : 'Offline') ?>
                            </div>
                        </div>
                        
                        <?php if($partner['unread_count'] > 0): ?>
                            <div class="unread-badge"><?= $partner['unread_count'] ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-chat">
                        <div class="empty-chat-icon">
                            <i class="bi bi-chat-left-dots"></i>
                        </div>
                        <h3>Belum ada percakapan</h3>
                        <p>Mulai percakapan dengan pelanggan Anda dari halaman pesanan atau produk</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- AREA CHAT UTAMA -->
        <div class="chat-main">
            <?php if($pembeliData): ?>
                <!-- HEADER CHAT -->
                <div class="chat-header">
                    <div class="chat-partner">
                        <?php
                        // Tentukan path foto partner chat
                        $fotoPartner = getFotoPath($pembeliData['foto'], $baseDir);
                        ?>
                        <img src="<?= htmlspecialchars($fotoPartner) ?>" 
                             class="partner-avatar"
                             onerror="this.onerror=null; this.src='../../assets/img/user.png'"
                             alt="<?= htmlspecialchars($pembeliData['nama']) ?>">
                        
                        <div class="partner-info">
                            <h4><?= htmlspecialchars($pembeliData['nama']) ?></h4>
                            <?php 
                                $isOnline = $pembeliData['status'] == 'online' || 
                                          ($pembeliData['last_seen_seconds'] !== null && $pembeliData['last_seen_seconds'] < 300);
                            ?>
                            <p id="partnerStatus">
                                <span class="online-status <?= $isOnline ? 'online' : 'offline' ?>"></span>
                                <?= $isOnline ? 'Online' : 'Offline' ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="chat-actions">
                        <button class="action-btn" title="Info pembeli">
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <button class="action-btn" title="Transaksi" onclick="window.location.href='../pesanan.php?pembeli_id=<?= $pembeli_id ?>'">
                            <i class="bi bi-receipt"></i>
                        </button>
                    </div>
                </div>

                <!-- MESSAGES AREA -->
                <div class="chat-messages" id="chatMessages">
                    <?php 
                    $currentDate = null;
                    if(count($chatMessages) > 0): 
                        foreach($chatMessages as $msg): 
                            $isSent = $msg['id_pengirim'] == $idPenjual;
                            $messageDate = date('Y-m-d', strtotime($msg['created_at']));
                            
                            // Tampilkan tanggal jika berbeda
                            if ($messageDate != $currentDate) {
                                $currentDate = $messageDate;
                                $displayDate = '';
                                if ($messageDate == date('Y-m-d')) {
                                    $displayDate = 'Hari ini';
                                } elseif ($messageDate == date('Y-m-d', strtotime('-1 day'))) {
                                    $displayDate = 'Kemarin';
                                } else {
                                    $displayDate = date('d M Y', strtotime($msg['created_at']));
                                }
                                echo '<div class="message-date"><span class="date-label">' . $displayDate . '</span></div>';
                            }
                            
                            // Tentukan foto pengirim pesan
                            $fotoPengirim = getFotoPath($msg['pengirim_foto'], $baseDir);
                    ?>
                        <div class="message <?= $isSent ? 'sent' : 'received' ?>" data-message-id="<?= $msg['id'] ?>">
                            <?php if(!$isSent): ?>
                            <!-- Foto pengirim untuk pesan yang diterima -->
                            <div style="margin-right: 10px;">
                                <img src="<?= $fotoPengirim ?>" 
                                     style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;"
                                     onerror="this.onerror=null; this.src='../../assets/img/user.png'"
                                     alt="<?= htmlspecialchars($msg['pengirim_nama']) ?>">
                            </div>
                            <?php endif; ?>
                            
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($msg['pesan'])) ?>
                                <div class="message-time">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?>
                                    <?php if($isSent): ?>
                                        <i class="bi bi-check<?= isset($msg['dibaca']) && $msg['dibaca'] == 1 ? '2' : '' ?>-all message-status"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-chat">
                            <div class="empty-chat-icon">
                                <i class="bi bi-chat-left-quote"></i>
                            </div>
                            <h3>Belum ada pesan</h3>
                            <p>Mulai percakapan dengan <?= htmlspecialchars($pembeliData['nama']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- INPUT AREA -->
                <div class="chat-input-area">
                    <form id="chatForm" method="POST" action="chat_send.php">
                        <input type="hidden" name="penerima_id" value="<?= $pembeli_id ?>">
                        <div class="chat-input-container">
                            <div class="message-input-container">
                                <textarea name="pesan" 
                                          id="messageInput" 
                                          class="message-input"
                                          placeholder="Ketik pesan..." 
                                          rows="1"
                                          required></textarea>
                            </div>
                            <button type="submit" class="send-button" id="sendButton" title="Kirim pesan">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- DEFAULT STATE (belum pilih pembeli) -->
                <div class="empty-chat">
                    <div class="empty-chat-icon">
                        <i class="bi bi-chat-left-text"></i>
                    </div>
                    <h3>Pilih pembeli untuk memulai chat</h3>
                    <p>Klik salah satu pembeli di daftar untuk melihat percakapan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ===== VARIABLES =====
let currentPembeliId = <?= $pembeli_id ?>;
let lastMessageId = 0;
let pollingInterval = null;

// ===== DOM ELEMENTS =====
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const topBar = document.getElementById('topBar');
const mainContent = document.getElementById('mainContent');
const searchContainer = document.getElementById('searchContainer');
const searchToggle = document.getElementById('searchToggle');
const chatForm = document.getElementById('chatForm');
const messageInput = document.getElementById('messageInput');
const sendButton = document.getElementById('sendButton');
const chatMessages = document.getElementById('chatMessages');
const partnerStatus = document.getElementById('partnerStatus');
const searchUser = document.getElementById('searchUser');

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupAutoResize();
    scrollToBottom();
    
    if (currentPembeliId) {
        initializeChat();
    }
    
    setupResponsive();
});

function setupEventListeners() {
    // Menu toggle
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });

    // Search toggle
    searchToggle.addEventListener('click', () => {
        searchContainer.classList.toggle('active');
    });

    // User search
    if (searchUser) {
        searchUser.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.chat-user').forEach(user => {
                const userName = user.dataset.userName.toLowerCase();
                user.style.display = userName.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    // Chat user click
    document.querySelectorAll('.chat-user').forEach(user => {
        user.addEventListener('click', function() {
            const pembeliId = this.dataset.pembeliId;
            window.location.href = `chat.php?pembeli_id=${pembeliId}`;
        });
    });

    // Chat form submission
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }

    // Message input events
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });
    }
}

function setupAutoResize() {
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
    }
}

function initializeChat() {
    // Get last message ID
    const lastMessage = document.querySelector('.message:last-child');
    if (lastMessage) {
        lastMessageId = parseInt(lastMessage.dataset.messageId) || 0;
    }
    
    // Start polling for new messages
    startPolling();
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    
    pollingInterval = setInterval(() => {
        fetchNewMessages();
        updateOnlineStatus();
    }, 3000); // Poll every 3 seconds
}

async function fetchNewMessages() {
    if (!currentPembeliId) return;
    
    try {
        const response = await fetch(`chat_get.php?pembeli_id=${currentPembeliId}&last_id=${lastMessageId}`);
        const data = await response.json();
        
        if (data.success && data.messages.length > 0) {
            data.messages.forEach(msg => {
                addMessageToChat(msg);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });
            
            scrollToBottom();
        }
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
}

function addMessageToChat(msg) {
    const isSent = msg.id_pengirim == <?= $idPenjual ?>;
    const messageTime = new Date(msg.created_at).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const messageHtml = `
        <div class="message ${isSent ? 'sent' : 'received'}" data-message-id="${msg.id}">
            ${!isSent ? `
            <div style="margin-right: 10px;">
                <img src="../../uploads/${msg.pengirim_foto || 'user.png'}" 
                     style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;"
                     onerror="this.onerror=null; this.src='../../assets/img/user.png'"
                     alt="${msg.pengirim_nama}">
            </div>
            ` : ''}
            <div class="message-content">
                ${msg.pesan.replace(/\n/g, '<br>')}
                <div class="message-time">
                    ${messageTime}
                    ${isSent ? `<i class="bi bi-check${msg.dibaca == 1 ? '2' : ''}-all message-status"></i>` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Remove empty state if exists
    const emptyState = chatMessages.querySelector('.empty-chat');
    if (emptyState) {
        emptyState.remove();
    }
    
    chatMessages.insertAdjacentHTML('beforeend', messageHtml);
}

async function sendMessage() {
    if (!messageInput.value.trim()) return;
    
    const formData = new FormData(chatForm);
    const message = messageInput.value.trim();
    
    // Disable input while sending
    messageInput.disabled = true;
    sendButton.disabled = true;
    
    try {
        const response = await fetch('chat_send.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Clear input
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Add message to chat
            addMessageToChat(data.message);
            scrollToBottom();
        } else {
            alert('Gagal mengirim pesan: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Gagal mengirim pesan. Periksa koneksi internet Anda.');
    } finally {
        messageInput.disabled = false;
        sendButton.disabled = false;
        messageInput.focus();
    }
}

async function updateOnlineStatus() {
    if (!currentPembeliId) return;
    
    try {
        const response = await fetch(`chat_status.php?pembeli_id=${currentPembeliId}`);
        const data = await response.json();
        
        if (data.success) {
            updateOnlineIndicator(data.online);
        }
    } catch (error) {
        console.error('Error updating online status:', error);
    }
}

function updateOnlineIndicator(isOnline) {
    if (partnerStatus) {
        const statusText = isOnline ? 'Online' : 'Offline';
        const statusHtml = `
            <span class="online-status ${isOnline ? 'online' : 'offline'}"></span>
            ${statusText}
        `;
        partnerStatus.innerHTML = statusHtml;
    }
}

function scrollToBottom() {
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

function setupResponsive() {
    function handleResize() {
        if (window.innerWidth <= 768) {
            menuToggle.style.display = 'flex';
            searchToggle.style.display = 'flex';
        } else {
            menuToggle.style.display = 'none';
            searchToggle.style.display = 'none';
            searchContainer.classList.remove('active');
            sidebar.classList.remove('active');
        }
    }
    
    window.addEventListener('resize', handleResize);
    handleResize();
}

function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = '../auth/logout.php';
    }
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (pollingInterval) clearInterval(pollingInterval);
});
</script>
</body>
</html>