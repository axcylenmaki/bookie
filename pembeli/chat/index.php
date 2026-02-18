<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";
require_once "includes/chat_functions.php";

$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'];

// Ambil foto pembeli dari database
$qUser = mysqli_query($conn, "SELECT foto FROM users WHERE id = '$idPembeli'");
$userData = mysqli_fetch_assoc($qUser);
$fotoPembeli = !empty($userData['foto']) ? "../../uploads/profile/" . $userData['foto'] : null;

/* =====================
   INISIALISASI CHAT
===================== */
$chat = new ChatSystem($conn, $idPembeli, $namaPembeli);

// Handle message sending via AJAX
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pesan']) && isset($_POST['penjual_id'])) {
    $result = $chat->sendMessage($_POST['penjual_id'], $_POST['pesan']);
    
    // Jika request AJAX
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => $result]);
        exit;
    }
    
    header("Location: index.php?penjual_id=" . $_POST['penjual_id']);
    exit;
}

// Handle AJAX request untuk get messages
if(isset($_GET['ajax']) && $_GET['ajax'] == 'get_messages') {
    $penjual_id = $_GET['penjual_id'] ?? 0;
    $last_id = $_GET['last_id'] ?? 0;
    
    if($penjual_id) {
        $messages = $chat->getChatHistory($penjual_id);
        $formattedMessages = [];
        foreach($messages as $msg) {
            $formattedMessages[] = [
                'id' => $msg['id'],
                'pesan' => htmlspecialchars($msg['pesan']),
                'is_me' => ($msg['id_pengirim'] == $idPembeli),
                'time' => date('H:i', strtotime($msg['created_at'])),
                'date' => date('d M Y', strtotime($msg['created_at'])),
                'status' => $msg['dibaca'] ?? 0
            ];
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $formattedMessages,
            'penjual' => $chat->getPenjual($penjual_id)
        ]);
        exit;
    }
}

// Get data
$penjual_id = $_GET['penjual_id'] ?? 0;
$penjual = $chat->getPenjual($penjual_id);
$penjual_list = $chat->getPenjualList();
$chat_history = $penjual_id ? $chat->getChatHistory($penjual_id) : [];
$unreadNotifCount = $chat->getUnreadNotificationCount();

// Auto-reply untuk chat baru
$auto_reply = '';
if($penjual_id && empty($chat_history)) {
    $auto_reply = $chat->getAutoReplyMessage($penjual_id);
}

// Get last message ID untuk AJAX polling
$lastMessageId = 0;
if(!empty($chat_history)) {
    $lastMessage = end($chat_history);
    $lastMessageId = $lastMessage['id'];
}

// Fungsi untuk mendapatkan avatar dengan path yang benar
function getAvatar($user, $role = 'penjual') {
    global $conn;
    
    if ($role == 'penjual') {
        if (is_array($user)) {
            $foto = $user['foto'] ?? '';
            $nama = $user['nama'] ?? 'Penjual';
            $id = $user['id'] ?? 0;
        } else {
            $id = $user;
            $q = mysqli_query($conn, "SELECT foto, nama FROM users WHERE id = '$id'");
            $data = mysqli_fetch_assoc($q);
            $foto = $data['foto'] ?? '';
            $nama = $data['nama'] ?? 'Penjual';
        }
    } else {
        $foto = $_SESSION['user']['foto'] ?? '';
        $nama = $_SESSION['user']['nama'] ?? 'Pembeli';
        $id = $_SESSION['user']['id'];
    }
    
    // Jika ada foto dan file exists, gunakan foto tersebut
    if (!empty($foto)) {
        // Cek di folder uploads/profile
        $pathProfile = "../../uploads/profile/" . $foto;
        if (file_exists($pathProfile)) {
            return $pathProfile;
        }
        
        // Cek di folder uploads langsung
        $pathUploads = "../../uploads/" . $foto;
        if (file_exists($pathUploads)) {
            return $pathUploads;
        }
    }
    
    // Jika tidak ada foto, return null
    return null;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f4f6f9;
            overflow: hidden;
        }

        main {
            margin-left: 250px;
            height: 100vh;
            overflow: hidden;
            padding: 20px;
        }

        .btn-purple {
            background-color: #6f42c1;
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-purple:hover {
            background-color: #5a32a3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(111, 66, 193, 0.3);
        }

        .chat-wrapper {
            height: calc(100vh - 120px);
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .chat-container {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .chat-main-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }

        /* Content row */
        .chat-content {
            flex: 1;
            min-height: 0;
            display: flex;
        }

        /* Left Sidebar - Daftar Penjual */
        .chat-sidebar {
            width: 320px;
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }

        .search-box {
            margin-top: 15px;
        }

        .search-box input {
            border-radius: 30px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
            font-size: 0.9rem;
        }

        .search-box input:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }

        .penjual-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .penjual-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 5px;
            position: relative;
        }

        .penjual-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .penjual-item.active {
            background: #6f42c1;
        }

        .penjual-item.active h6,
        .penjual-item.active .last-message,
        .penjual-item.active .text-muted {
            color: white !important;
        }

        .avatar-wrapper {
            position: relative;
            margin-right: 12px;
        }

        .penjual-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .avatar-inisial {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #6f42c1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .status-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .status-online { background: #28a745; }
        .status-offline { background: #6c757d; }

        .penjual-info {
            flex: 1;
            min-width: 0;
        }

        .penjual-info h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .last-message {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-count {
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
            margin-left: 8px;
        }

        /* Right Side - Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
            min-width: 0;
        }

        .chat-area-header {
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .seller-info {
            display: flex;
            align-items: center;
        }

        .seller-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 2px solid #6f42c1;
        }

        .seller-info .avatar-inisial {
            width: 45px;
            height: 45px;
            font-size: 18px;
            margin-right: 12px;
            border: 2px solid #6f42c1;
        }

        .seller-details h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }

        .seller-details small {
            color: #6c757d;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        /* Message Bubbles */
        .message-date {
            text-align: center;
            margin: 15px 0;
        }

        .message-date span {
            background: #e9ecef;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: #495057;
        }

        .message-wrapper {
            margin-bottom: 15px;
            max-width: 70%;
            clear: both;
        }

        .message-wrapper.pembeli {
            float: right;
        }

        .message-wrapper.penjual {
            float: left;
        }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            word-wrap: break-word;
            position: relative;
        }

        .message-wrapper.pembeli .message-bubble {
            background: #6f42c1;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-wrapper.penjual .message-bubble {
            background: white;
            color: #212529;
            border-bottom-left-radius: 4px;
        }

        .message-time {
            font-size: 0.65rem;
            margin-top: 4px;
            text-align: right;
            opacity: 0.8;
        }

        .message-wrapper.pembeli .message-time {
            color: #6f42c1;
        }

        .message-wrapper.penjual .message-time {
            color: #6c757d;
        }

        .message-status {
            font-size: 0.7rem;
            margin-left: 4px;
        }

        /* Sistem message */
        .message-wrapper.sistem {
            float: none;
            max-width: 90%;
            margin: 20px auto;
            text-align: center;
        }

        .message-wrapper.sistem .message-bubble {
            background: #e9ecef;
            color: #495057;
            font-style: italic;
            border-radius: 20px;
        }

        /* Empty states */
        .empty-chat {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #adb5bd;
        }

        .empty-chat i {
            font-size: 4rem;
            margin-bottom: 15px;
        }

        .empty-chat h5 {
            color: #495057;
            margin-bottom: 5px;
        }

        .empty-chat p {
            font-size: 0.9rem;
        }

        /* Message Input */
        .message-input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e9ecef;
        }

        .input-group-custom {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .input-group-custom input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid #dee2e6;
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .input-group-custom input:focus {
            outline: none;
            border-color: #6f42c1;
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
        }

        .input-group-custom button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #6f42c1;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .input-group-custom button:hover {
            background: #5a32a3;
            transform: scale(1.1);
        }

        .input-group-custom button:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
        }

        .offline-warning {
            font-size: 0.8rem;
            color: #ffc107;
            margin-top: 8px;
        }

        /* Scroll to bottom button */
        .scroll-bottom {
            position: absolute;
            bottom: 100px;
            right: 30px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6f42c1;
            color: white;
            border: none;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(111, 66, 193, 0.3);
            z-index: 100;
            transition: all 0.3s;
        }

        .scroll-bottom.show {
            display: flex;
        }

        .scroll-bottom:hover {
            background: #5a32a3;
            transform: scale(1.1);
        }

        /* Typing indicator */
        .typing-indicator {
            display: none;
            padding: 10px 20px;
            background: #e9ecef;
            border-radius: 20px;
            width: fit-content;
            margin-bottom: 10px;
            margin-left: 20px;
        }

        .typing-indicator.show {
            display: block;
        }

        .typing-indicator span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #6c757d;
            border-radius: 50%;
            margin-right: 3px;
            animation: typing 1.3s linear infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
            margin-right: 0;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        /* Scrollbar styling */
        .messages-container::-webkit-scrollbar,
        .penjual-list::-webkit-scrollbar {
            width: 6px;
        }

        .messages-container::-webkit-scrollbar-track,
        .penjual-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .messages-container::-webkit-scrollbar-thumb,
        .penjual-list::-webkit-scrollbar-thumb {
            background: #6f42c1;
            border-radius: 3px;
        }

        .messages-container::-webkit-scrollbar-thumb:hover,
        .penjual-list::-webkit-scrollbar-thumb:hover {
            background: #5a32a3;
        }

        /* Responsive */
        @media (max-width: 992px) {
            main {
                margin-left: 0;
                padding: 10px;
            }
            
            .chat-sidebar {
                width: 280px;
            }
        }

        @media (max-width: 768px) {
            .chat-content {
                flex-direction: column;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 200px;
            }
        }

        .text-purple { color: #6f42c1; }
        .bg-purple { background-color: #6f42c1; color: white; }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<main>
    <div class="chat-wrapper">
        <div class="chat-container">
            <!-- Header -->
            <div class="chat-main-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 fw-bold">
                        <i class="bi bi-chat-dots text-purple me-2"></i>
                        Chat dengan Penjual
                    </h4>
                    <p class="text-muted mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Diskusikan produk atau konfirmasi pesanan
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="../pesanan.php" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-receipt"></i>
                        <span class="d-none d-md-inline ms-1">Pesanan</span>
                    </a>
                    <a href="../keranjang.php" class="btn btn-purple btn-sm position-relative">
                        <i class="bi bi-cart"></i>
                        <span class="d-none d-md-inline ms-1">Keranjang</span>
                        <?php 
                        $qKeranjang = mysqli_query($conn, "SELECT SUM(jumlah) as total FROM keranjang WHERE id_user='$idPembeli'");
                        if($qKeranjang) {
                            $keranjang = mysqli_fetch_assoc($qKeranjang);
                            if($keranjang['total'] > 0):
                        ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                            <?= $keranjang['total'] ?>
                        </span>
                        <?php 
                            endif;
                        }
                        ?>
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="chat-content">
                <!-- Sidebar - Daftar Penjual -->
                <div class="chat-sidebar">
                    <div class="sidebar-header">
                        <h6 class="mb-0 fw-bold">
                            <i class="bi bi-people-fill text-purple me-2"></i>
                            Daftar Penjual
                            <span class="badge bg-purple ms-2"><?= count($penjual_list) ?></span>
                        </h6>
                        <div class="search-box">
                            <input type="text" 
                                   class="form-control form-control-sm" 
                                   placeholder="Cari penjual..." 
                                   id="searchPenjual">
                        </div>
                    </div>
                    
                    <div class="penjual-list" id="penjualList">
                        <?php if(count($penjual_list) > 0): ?>
                            <?php foreach($penjual_list as $pj): 
                                $avatar = getAvatar($pj, 'penjual');
                                $inisial = strtoupper(substr($pj['nama'], 0, 1));
                            ?>
                            <div class="penjual-item <?= ($penjual_id == $pj['id']) ? 'active' : '' ?>" 
                                 data-penjual-id="<?= $pj['id'] ?>"
                                 onclick="window.location.href='?penjual_id=<?= $pj['id'] ?>'">
                                <div class="avatar-wrapper">
                                    <?php if($avatar): ?>
                                        <img src="<?= $avatar ?>" 
                                             alt="<?= htmlspecialchars($pj['nama']) ?>"
                                             class="penjual-avatar">
                                    <?php else: ?>
                                        <div class="avatar-inisial">
                                            <?= $inisial ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="status-badge status-<?= $pj['status'] ?>"></span>
                                </div>
                                <div class="penjual-info">
                                    <div class="d-flex align-items-center">
                                        <h6 class="text-truncate"><?= htmlspecialchars($pj['nama']) ?></h6>
                                        <?php if(($pj['unread_count'] ?? 0) > 0): ?>
                                            <span class="unread-count ms-auto"><?= $pj['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="last-message">
                                        <?php if(!empty($pj['last_message'])): ?>
                                            <?= htmlspecialchars(mb_strimwidth($pj['last_message'], 0, 30, '...')) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Belum ada pesan</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-chat py-5">
                                <i class="bi bi-chat-dots"></i>
                                <h6>Belum Ada Percakapan</h6>
                                <p class="text-muted small">Mulai belanja dan chat dengan penjual</p>
                                <a href="../produk.php" class="btn btn-sm btn-purple mt-2">
                                    <i class="bi bi-shop"></i> Lihat Produk
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if($penjual_id && $penjual): 
                        $avatarPenjual = getAvatar($penjual, 'penjual');
                        $inisialPenjual = strtoupper(substr($penjual['nama'], 0, 1));
                        $lastMessageId = !empty($chat_history) ? end($chat_history)['id'] : 0;
                    ?>
                        <!-- Chat Header -->
                        <div class="chat-area-header">
                            <div class="seller-info">
                                <?php if($avatarPenjual): ?>
                                    <img src="<?= $avatarPenjual ?>" 
                                         alt="<?= htmlspecialchars($penjual['nama']) ?>"
                                         class="seller-avatar">
                                <?php else: ?>
                                    <div class="avatar-inisial">
                                        <?= $inisialPenjual ?>
                                    </div>
                                <?php endif; ?>
                                <div class="seller-details">
                                    <h5><?= htmlspecialchars($penjual['nama']) ?></h5>
                                    <small>
                                        <span class="badge bg-<?= $penjual['status'] == 'online' ? 'success' : 'secondary' ?>">
                                            <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i>
                                            <?= $penjual['status'] == 'online' ? 'Online' : 'Offline' ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                            <a href="../pesanan.php?penjual_id=<?= $penjual_id ?>" 
                               class="btn btn-outline-dark btn-sm"
                               data-bs-toggle="tooltip" 
                               title="Lihat Transaksi">
                                <i class="bi bi-receipt"></i>
                            </a>
                        </div>

                        <!-- Messages Container -->
                        <div class="messages-container" id="messagesContainer" data-penjual-id="<?= $penjual_id ?>" data-last-id="<?= $lastMessageId ?>">
                            <?php 
                            $lastDate = '';
                            if(count($chat_history) > 0): 
                                foreach($chat_history as $msg): 
                                    $isPembeli = ($msg['id_pengirim'] == $idPembeli);
                                    $currentDate = date('Y-m-d', strtotime($msg['created_at']));
                                    
                                    // Tampilkan tanggal jika berbeda
                                    if($currentDate != $lastDate):
                                        $lastDate = $currentDate;
                            ?>
                                <div class="message-date">
                                    <span><?= date('d M Y', strtotime($msg['created_at'])) ?></span>
                                </div>
                            <?php 
                                    endif;
                            ?>
                                <div class="message-wrapper <?= $isPembeli ? 'pembeli' : 'penjual' ?>" data-message-id="<?= $msg['id'] ?>">
                                    <div class="message-bubble">
                                        <?= nl2br(htmlspecialchars($msg['pesan'])) ?>
                                        <div class="message-time">
                                            <?= date('H:i', strtotime($msg['created_at'])) ?>
                                            <?php if($isPembeli): ?>
                                                <i class="bi bi-check2<?= ($msg['dibaca'] ?? 0) ? '-all' : '' ?> message-status"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach; 
                            elseif($auto_reply): 
                            ?>
                                <div class="message-wrapper sistem">
                                    <div class="message-bubble">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <?= nl2br(htmlspecialchars($auto_reply)) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="empty-chat">
                                    <i class="bi bi-chat-square-text"></i>
                                    <h5>Mulai Percakapan</h5>
                                    <p>Kirim pesan pertama kepada <?= htmlspecialchars($penjual['nama']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Typing Indicator -->
                        <div class="typing-indicator" id="typingIndicator">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span class="ms-2 small text-muted">sedang mengetik...</span>
                        </div>

                        <!-- Message Input -->
                        <div class="message-input-area">
                            <form method="POST" id="chatForm">
                                <input type="hidden" name="penjual_id" value="<?= $penjual_id ?>">
                                <div class="input-group-custom">
                                    <input type="text" 
                                           name="pesan" 
                                           id="messageInput"
                                           class="form-control" 
                                           placeholder="Ketik pesan..." 
                                           required
                                           autocomplete="off"
                                           <?= $penjual['status'] == 'offline' ? 'title="Penjual sedang offline"' : '' ?>>
                                    <button type="submit" id="sendButton" class="btn-purple">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                                <?php if($penjual['status'] == 'offline'): ?>
                                    <div class="offline-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Penjual sedang offline. Pesan akan terkirim dan dibaca saat penjual online.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Scroll to bottom button -->
                        <button class="scroll-bottom" id="scrollBottomBtn" onclick="scrollToBottom()">
                            <i class="bi bi-arrow-down"></i>
                        </button>

                    <?php else: ?>
                        <!-- No Chat Selected -->
                        <div class="empty-chat">
                            <i class="bi bi-chat-left-dots" style="font-size: 5rem;"></i>
                            <h4 class="mt-3">Pilih Penjual</h4>
                            <p class="text-muted">Pilih penjual dari daftar untuk memulai percakapan</p>
                            <?php if(count($penjual_list) == 0): ?>
                                <a href="../produk.php" class="btn btn-purple mt-3">
                                    <i class="bi bi-shop"></i> Lihat Produk
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Inisialisasi tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Variabel global
const messagesContainer = document.getElementById('messagesContainer');
let lastMessageId = <?= $lastMessageId ?>;
let penjualId = <?= $penjual_id ?: 0 ?>;

// Auto scroll ke bawah saat load
if (messagesContainer) {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Search penjual
const searchInput = document.getElementById('searchPenjual');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.penjual-item');
        
        items.forEach(item => {
            const nama = item.querySelector('h6')?.textContent.toLowerCase() || '';
            item.style.display = nama.includes(searchTerm) ? 'flex' : 'none';
        });
    });
}

// Real-time chat dengan AJAX polling
if (penjualId > 0) {
    let isChecking = false;
    
    function checkNewMessages() {
        if (isChecking) return;
        
        isChecking = true;
        
        fetch('ajax/get_new_messages.php?penjual_id=' + penjualId + '&last_id=' + lastMessageId)
            .then(response => response.json())
            .then(data => {
                if (data.has_new && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        addNewMessage(msg);
                    });
                    lastMessageId = data.last_id;
                    
                    // Tandai sebagai dibaca
                    fetch('ajax/mark_as_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'penjual_id=' + penjualId
                    });
                    
                    scrollToBottom();
                }
                isChecking = false;
            })
            .catch(err => {
                console.error('Error:', err);
                isChecking = false;
            });
    }
    
    function addNewMessage(msg) {
        if (!messagesContainer) return;
        
        // Cek apakah perlu menampilkan tanggal
        const lastMessage = messagesContainer.lastElementChild;
        const today = new Date().toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
        
        if (!lastMessage || !lastMessage.classList.contains('message-date')) {
            const dateHtml = `
                <div class="message-date">
                    <span>${today}</span>
                </div>
            `;
            messagesContainer.insertAdjacentHTML('beforeend', dateHtml);
        }
        
        const messageHtml = `
            <div class="message-wrapper ${msg.is_me ? 'pembeli' : 'penjual'}" data-message-id="${msg.id}">
                <div class="message-bubble">
                    ${msg.pesan.replace(/\n/g, '<br>')}
                    <div class="message-time">
                        ${msg.time}
                        ${msg.is_me ? '<i class="bi bi-check2 message-status"></i>' : ''}
                    </div>
                </div>
            </div>
        `;
        
        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
        
        // Update unread count di sidebar
        if (!msg.is_me) {
            updateUnreadCount(penjualId, 0);
        }
    }
    
    function updateUnreadCount(penjualId, count) {
        const item = document.querySelector('.penjual-item[data-penjual-id="' + penjualId + '"]');
        if (item) {
            const badge = item.querySelector('.unread-count');
            if (count > 0) {
                if (badge) {
                    badge.textContent = count;
                } else {
                    const badgeHtml = '<span class="unread-count ms-auto">' + count + '</span>';
                    item.querySelector('.d-flex')?.insertAdjacentHTML('beforeend', badgeHtml);
                }
            } else if (badge) {
                badge.remove();
            }
        }
    }
    
    // Mulai polling
    setInterval(checkNewMessages, 3000);
}

// Kirim pesan via AJAX
document.getElementById('chatForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const pesan = formData.get('pesan').trim();
    
    if (!pesan) return;
    
    const sendButton = document.getElementById('sendButton');
    const originalHtml = sendButton.innerHTML;
    
    // Disable button
    sendButton.disabled = true;
    sendButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    // Set header untuk AJAX
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear input
            document.getElementById('messageInput').value = '';
            
            // Tambah pesan ke container
            const now = new Date();
            const timeStr = now.getHours().toString().padStart(2, '0') + ':' + 
                           now.getMinutes().toString().padStart(2, '0');
            
            addNewMessage({
                id: Date.now(),
                pesan: pesan,
                time: timeStr,
                is_me: true
            });
            
            scrollToBottom();
        } else {
            alert('Gagal mengirim pesan');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Gagal mengirim pesan');
    })
    .finally(() => {
        // Enable button
        sendButton.disabled = false;
        sendButton.innerHTML = originalHtml;
    });
});

// Scroll functions
function scrollToBottom() {
    if (messagesContainer) {
        messagesContainer.scrollTo({
            top: messagesContainer.scrollHeight,
            behavior: 'smooth'
        });
    }
}

// Show/hide scroll button
if (messagesContainer) {
    messagesContainer.addEventListener('scroll', function() {
        const btn = document.getElementById('scrollBottomBtn');
        if (!btn) return;
        
        const isScrolledToBottom = this.scrollHeight - this.scrollTop - this.clientHeight < 50;
        btn.classList.toggle('show', !isScrolledToBottom);
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        document.getElementById('chatForm')?.dispatchEvent(new Event('submit'));
    }
    
    if (e.key === 'Escape') {
        document.getElementById('messageInput')?.focus();
    }
});

// Konfirmasi untuk offline penjual
const isOffline = <?= ($penjual && $penjual['status'] == 'offline') ? 'true' : 'false' ?>;
if (isOffline) {
    document.getElementById('chatForm')?.addEventListener('submit', function(e) {
        if (!confirm('Penjual sedang offline. Pesan akan tetap terkirim. Lanjutkan?')) {
            e.preventDefault();
        }
    });
}

// Observer untuk auto-scroll
if (messagesContainer) {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                scrollToBottom();
            }
        });
    });
    
    observer.observe(messagesContainer, { childList: true, subtree: true });
}
</script>
</body>
</html>