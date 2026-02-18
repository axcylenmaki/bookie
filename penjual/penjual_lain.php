<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

/* =====================
   DATA LOGIN
===================== */
$idPenjual = $_SESSION['user']['id'];
$namaPenjual = $_SESSION['user']['nama'];
$fotoUser = $_SESSION['user']['foto'] ?? '';

// Path foto profil
$fotoPath = (!empty($fotoUser) && file_exists("../uploads/" . $fotoUser))
    ? "../uploads/" . $fotoUser
    : "../assets/img/user.png";

/* =====================
   HITUNG CHAT YANG BELUM DIBACA
===================== */
$qUnreadChat = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM chat 
    WHERE penerima_id = '$idPenjual' 
    AND dibaca = 0
");
$unreadChat = 0;
if ($qUnreadChat) {
    $dataChat = mysqli_fetch_assoc($qUnreadChat);
    $unreadChat = $dataChat['total'] ?? 0;
}

/* =====================
   FILTER & SEARCH
===================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // 12 penjual per halaman (grid view)
$offset = ($page - 1) * $limit;

// Base query - ambil semua penjual kecuali dirinya sendiri
$where = "WHERE role = 'penjual' AND id != '$idPenjual'";

if (!empty($search)) {
    $where .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%' OR no_hp LIKE '%$search%' OR alamat LIKE '%$search%')";
}

// Sorting
$orderBy = "ORDER BY ";
switch ($sort) {
    case 'nama_asc':
        $orderBy .= "nama ASC";
        break;
    case 'nama_desc':
        $orderBy .= "nama DESC";
        break;
    case 'terlama':
        $orderBy .= "created_at ASC";
        break;
    case 'terbaru':
    default:
        $orderBy .= "created_at DESC";
        break;
}

// Hitung total data untuk pagination
$qTotal = mysqli_query($conn, "SELECT COUNT(*) as total FROM users $where");
$totalData = mysqli_fetch_assoc($qTotal)['total'];
$totalPages = ceil($totalData / $limit);

// Ambil data penjual lain
$qPenjual = mysqli_query($conn, "
    SELECT 
        id,
        nama,
        email,
        no_hp,
        alamat,
        foto,
        status,
        created_at,
        (SELECT COUNT(*) FROM produk WHERE id_penjual = users.id) as total_produk
    FROM users 
    $where 
    $orderBy 
    LIMIT $offset, $limit
");

// Ambil statistik untuk setiap penjual (produk & transaksi)
$penjualData = [];
while ($row = mysqli_fetch_assoc($qPenjual)) {
    // Hitung total transaksi selesai untuk penjual ini
    $qTransaksi = mysqli_query($conn, "
        SELECT COUNT(DISTINCT t.id) as total_transaksi
        FROM transaksi t
        JOIN transaksi_detail td ON t.id = td.id_transaksi
        JOIN produk p ON td.id_produk = p.id
        WHERE p.id_penjual = '{$row['id']}' AND t.status = 'selesai'
    ");
    $transaksi = mysqli_fetch_assoc($qTransaksi);
    $row['total_transaksi'] = $transaksi['total_transaksi'] ?? 0;
    
    // Hitung rating (jika ada) - placeholder, bisa dikembangkan nanti
    $row['rating'] = 4.5; // Placeholder rating
    
    $penjualData[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjual Lain - BOOKIE</title>
    
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

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
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .hero h2 {
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 700;
        }

        .hero p {
            max-width: 520px;
            opacity: .9;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* =====================
           FILTER SECTION
        ===================== */
        .filter-section {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }

        .filter-select {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
        }

        .result-count {
            color: #64748b;
            font-size: 14px;
        }

        .result-count span {
            font-weight: 700;
            color: #3498db;
        }

        /* =====================
           SELLER GRID
        ===================== */
        .seller-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }

        .seller-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .seller-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
            border-color: #3498db;
        }

        .seller-header {
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            position: relative;
        }

        .seller-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #fff;
            background: #fff;
            position: absolute;
            bottom: -40px;
            left: 20px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .seller-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .seller-body {
            padding: 50px 20px 20px;
        }

        .seller-name {
            font-size: 18px;
            font-weight: 700;
            color: #020617;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .seller-email {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
            word-break: break-all;
        }

        .seller-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #020617;
        }

        .stat-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .seller-footer {
            padding: 15px 20px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
        }

        .seller-footer .btn {
            flex: 1;
            padding: 8px 0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }

        .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* =====================
           MODAL DETAIL PENJUAL
        ===================== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h3 {
            font-size: 18px;
            color: #020617;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: #e74c3c;
            transform: scale(1.1);
        }

        .modal-body {
            padding: 24px;
        }

        .profile-detail {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 24px;
        }

        .profile-detail img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 16px;
        }

        .profile-detail h2 {
            font-size: 20px;
            color: #020617;
            margin-bottom: 4px;
        }

        .profile-detail p {
            color: #64748b;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .detail-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .detail-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #020617;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* =====================
           PAGINATION
        ===================== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* =====================
           EMPTY STATE
        ===================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.5;
            margin-bottom: 20px;
            color: #94a3b8;
        }

        .empty-state h4 {
            margin-bottom: 10px;
            color: #020617;
        }

        /* =====================
           RESPONSIVE
        ===================== */
        @media (max-width: 1200px) {
            .seller-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .seller-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
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
            
            .search-toggle {
                display: flex !important;
            }
            
            .sidebar-logo span,
            .sidebar-profile > div,
            .menu-item span,
            .menu-badge,
            .sidebar-footer .footer-btn span {
                display: none;
            }
            
            .sidebar.active .sidebar-logo span,
            .sidebar.active .sidebar-profile > div,
            .sidebar.active .menu-item span,
            .sidebar.active .menu-badge,
            .sidebar.active .sidebar-footer .footer-btn span {
                display: block;
            }
            
            .seller-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-left {
                width: 100%;
            }
            
            .filter-select {
                flex: 1;
            }
            
            .result-count {
                width: 100%;
                text-align: left;
            }
        }

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
        }

        .search-toggle:hover {
            background: #f1f5f9;
            color: #3498db;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span>📚 BOOKIE</span>
    </div>

    <a href="profile.php" class="sidebar-profile">
        <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
        <div>
            <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
            <div class="role">Penjual</div>
        </div>
    </a>

    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="profile.php" class="menu-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        
        <a href="produk.php" class="menu-item">
            <i class="bi bi-box"></i>
            <span>Produk</span>
        </a>
        
        <a href="chat/chat.php" class="menu-item">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
            <?php if($unreadChat > 0): ?>
            <span class="menu-badge"><?= $unreadChat ?></span>
            <?php endif; ?>
        </a>
        
        <a href="pesanan.php" class="menu-item">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
        </a>
        
        <a href="status.php" class="menu-item">
            <i class="bi bi-activity"></i>
            <span>Status</span>
        </a>
        
        <a href="laporan.php" class="menu-item">
            <i class="bi bi-bar-chart"></i>
            <span>Laporan</span>
        </a>
        
        <a href="penjual_lain.php" class="menu-item active">
            <i class="bi bi-people"></i>
            <span>Penjual Lain</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <button class="footer-btn logout" onclick="logout()">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </button>
        
        <a href="help.php" class="footer-btn help">
            <i class="bi bi-question-circle"></i>
            <span>Help & FAQ</span>
        </a>
    </div>
</div>

<!-- TOP BAR -->
<div class="top-bar" id="topBar">
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="search-container" id="searchContainer">
        <i class="bi bi-search search-icon"></i>
        <form action="" method="GET" style="width: 100%;">
            <input type="text" name="search" class="search-box" placeholder="Cari penjual lain..." 
                   value="<?= htmlspecialchars($search) ?>">
            <?php if ($sort !== 'terbaru'): ?>
                <input type="hidden" name="sort" value="<?= $sort ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <button class="search-toggle" id="searchToggle">
        <i class="bi bi-search"></i>
    </button>
    
    <div class="top-bar-right">
        <a href="profile.php" class="user-profile-top">
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
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
        <h2>👥 Penjual Lain</h2>
        <p>
            Jelajahi toko-toko buku lain yang bergabung di BOOKIE. Lihat profil, produk, dan statistik mereka.
        </p>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="filter-left">
            <span class="filter-label"><i class="bi bi-sort-down"></i> Urutkan:</span>
            <select class="filter-select" onchange="location.href='?sort='+this.value+'<?= $search ? '&search='.urlencode($search) : '' ?>'">
                <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Terlama</option>
                <option value="nama_asc" <?= $sort == 'nama_asc' ? 'selected' : '' ?>>Nama A-Z</option>
                <option value="nama_desc" <?= $sort == 'nama_desc' ? 'selected' : '' ?>>Nama Z-A</option>
            </select>
        </div>
        <div class="result-count">
            <i class="bi bi-shop"></i> Menampilkan <span><?= count($penjualData) ?></span> dari <span><?= $totalData ?></span> penjual
        </div>
    </div>

    <!-- SELLER GRID -->
    <?php if (!empty($penjualData)): ?>
        <div class="seller-grid">
            <?php foreach ($penjualData as $penjual): 
                $isOnline = $penjual['status'] == 'online';
                $fotoPenjual = !empty($penjual['foto']) && file_exists("../uploads/".$penjual['foto']) 
                    ? "../uploads/".$penjual['foto'] 
                    : "../assets/img/user.png";
            ?>
            <div class="seller-card" onclick="showSellerDetail(<?= htmlspecialchars(json_encode($penjual)) ?>)">
                <div class="seller-header"></div>
                <div class="seller-avatar">
                    <img src="<?= htmlspecialchars($fotoPenjual) ?>" 
                         alt="<?= htmlspecialchars($penjual['nama']) ?>"
                         onerror="this.onerror=null; this.src='../assets/img/user.png'">
                </div>
                <div class="seller-body">
                    <div class="seller-name">
                        <?= htmlspecialchars($penjual['nama']) ?>
                        <span class="online-status <?= $isOnline ? 'online' : 'offline' ?>"></span>
                    </div>
                    <div class="seller-email">
                        <i class="bi bi-envelope"></i>
                        <?= htmlspecialchars($penjual['email']) ?>
                    </div>
                    
                    <div class="seller-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($penjual['total_produk']) ?></div>
                            <div class="stat-label">Produk</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($penjual['total_transaksi']) ?></div>
                            <div class="stat-label">Terjual</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">⭐ <?= number_format($penjual['rating'], 1) ?></div>
                            <div class="stat-label">Rating</div>
                        </div>
                    </div>
                </div>
                <div class="seller-footer">
                    <button class="btn btn-outline" onclick="event.stopPropagation(); window.location.href='chat/chat.php?pembeli_id=<?= $penjual['id'] ?>'">
                        <i class="bi bi-chat"></i> Chat
                    </button>
                    <button class="btn btn-primary" onclick="event.stopPropagation(); window.location.href='produk_penjual.php?id=<?= $penjual['id'] ?>'">
                        <i class="bi bi-box"></i> Lihat Produk
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page-1 ?>&sort=<?= $sort ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item">
                    <a class="page-link <?= $i == $page ? 'active' : '' ?>" 
                       href="?page=<?= $i ?>&sort=<?= $sort ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page+1 ?>&sort=<?= $sort ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h4>Tidak ada penjual ditemukan</h4>
            <p>
                <?php if (!empty($search)): ?>
                    Tidak ada penjual dengan kata kunci "<?= htmlspecialchars($search) ?>"
                <?php else: ?>
                    Belum ada penjual lain yang terdaftar
                <?php endif; ?>
            </p>
            <a href="penjual_lain.php" class="btn btn-primary" style="display: inline-flex; margin-top: 16px;">
                <i class="bi bi-arrow-clockwise"></i> Reset Filter
            </a>
        </div>
    <?php endif; ?>

    <!-- MODAL DETAIL PENJUAL -->
    <div id="sellerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="bi bi-person-badge"></i> Detail Penjual</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Akan diisi oleh JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i> Tutup
                </button>
                <a id="modalChatLink" href="#" class="btn btn-primary">
                    <i class="bi bi-chat"></i> Chat
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const searchToggle = document.getElementById('searchToggle');
const searchContainer = document.getElementById('searchContainer');
const sellerModal = document.getElementById('sellerModal');

// Menu Toggle
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

// Search Toggle (Mobile)
if (searchToggle) {
    searchToggle.addEventListener('click', () => {
        searchContainer.classList.toggle('active');
    });
}

// Logout function
function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = '../auth/logout.php';
    }
}

// Show Seller Detail Modal
function showSellerDetail(seller) {
    const modalBody = document.getElementById('modalBody');
    const modalChatLink = document.getElementById('modalChatLink');
    
    // Set chat link
    modalChatLink.href = `chat/chat.php?pembeli_id=${seller.id}`;
    
    // Determine online status
    const isOnline = seller.status === 'online';
    const onlineStatus = isOnline ? 
        '<span class="online-status online"></span> Online' : 
        '<span class="online-status offline"></span> Offline';
    
    // Format tanggal gabung
    const joinDate = new Date(seller.created_at).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
    
    // Foto path
    const fotoPath = seller.foto ? `../uploads/${seller.foto}` : '../assets/img/user.png';
    
    modalBody.innerHTML = `
        <div class="profile-detail">
            <img src="${fotoPath}" 
                 alt="${seller.nama}"
                 onerror="this.onerror=null; this.src='../assets/img/user.png'">
            <h2>${seller.nama}</h2>
            <p>${seller.email}</p>
            <p style="font-size: 13px; margin-top: 4px;">${onlineStatus}</p>
        </div>
        
        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-telephone"></i> No. Telepon
                </div>
                <div class="detail-value">${seller.no_hp || '-'}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-geo-alt"></i> Alamat
                </div>
                <div class="detail-value">${seller.alamat || '-'}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-box"></i> Total Produk
                </div>
                <div class="detail-value">${seller.total_produk.toLocaleString('id-ID')}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-cart-check"></i> Transaksi Selesai
                </div>
                <div class="detail-value">${seller.total_transaksi.toLocaleString('id-ID')}</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-star"></i> Rating
                </div>
                <div class="detail-value">⭐ ${seller.rating.toFixed(1)} / 5.0</div>
            </div>
            
            <div class="detail-item">
                <div class="detail-label">
                    <i class="bi bi-calendar"></i> Bergabung
                </div>
                <div class="detail-value">${joinDate}</div>
            </div>
        </div>
    `;
    
    sellerModal.classList.add('show');
}

// Close Modal
function closeModal() {
    sellerModal.classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == sellerModal) {
        closeModal();
    }
}

// Responsive handling
function handleResize() {
    if (window.innerWidth <= 768) {
        if (menuToggle) menuToggle.style.display = 'flex';
        if (searchToggle) searchToggle.style.display = 'flex';
    } else {
        if (menuToggle) menuToggle.style.display = 'none';
        if (searchToggle) searchToggle.style.display = 'none';
        if (searchContainer) searchContainer.classList.remove('active');
        if (sidebar) sidebar.classList.remove('active');
    }
}

window.addEventListener('resize', handleResize);
document.addEventListener('DOMContentLoaded', handleResize);

// Search on enter
const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });
}
</script>

</body>
</html>