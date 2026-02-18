<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

$namaPenjual = $_SESSION['user']['nama'];
$fotoUser = $_SESSION['user']['foto'] ?? '';
$penjual_id = $_SESSION['user']['id'];

// PERBAIKAN PATH FOTO PROFIL - Langsung dari folder uploads/
if (!empty($fotoUser) && file_exists("../uploads/" . $fotoUser)) {
    $fotoPath = "../uploads/" . $fotoUser;
} else {
    $fotoPath = "../assets/img/user.png";
}

// Koneksi database
require_once '../config/database.php';

// Inisialisasi statistik
$stats = [
    'total_produk' => 0,
    'pesanan_aktif' => 0,
    'chat_baru' => 0,
    'omzet_bulan_ini' => 0,
    'notifikasi' => 0,
    'stok_habis' => 0
];

// 1. Total Produk
$query_produk = "SELECT COUNT(*) as total FROM produk WHERE id_penjual = '$penjual_id'";
$result_produk = mysqli_query($conn, $query_produk);
if ($result_produk && mysqli_num_rows($result_produk) > 0) {
    $data_produk = mysqli_fetch_assoc($result_produk);
    $stats['total_produk'] = $data_produk['total'];
}

// 2. Pesanan Aktif
$query_pesanan = "SELECT COUNT(DISTINCT t.id) as total 
                  FROM transaksi t 
                  JOIN transaksi_detail td ON t.id = td.id_transaksi
                  JOIN produk p ON td.id_produk = p.id
                  WHERE p.id_penjual = '$penjual_id' 
                  AND t.status IN ('pending', 'dibayar', 'diproses', 'dikirim', 'menunggu', 'approve')";
$result_pesanan = mysqli_query($conn, $query_pesanan);
if ($result_pesanan && mysqli_num_rows($result_pesanan) > 0) {
    $data_pesanan = mysqli_fetch_assoc($result_pesanan);
    $stats['pesanan_aktif'] = $data_pesanan['total'];
}

// 3. Chat Baru (chat yang belum dibaca oleh penjual)
$query_chat = "SELECT COUNT(*) as total FROM chat 
               WHERE penerima_id = '$penjual_id' 
               AND dibaca = 0";
$result_chat = mysqli_query($conn, $query_chat);
if ($result_chat && mysqli_num_rows($result_chat) > 0) {
    $data_chat = mysqli_fetch_assoc($result_chat);
    $stats['chat_baru'] = $data_chat['total'];
}

// 4. Omzet Bulan Ini
$bulan_ini = date('Y-m');
$query_omzet = "SELECT SUM(td.harga * td.jumlah) as total 
                FROM transaksi t
                JOIN transaksi_detail td ON t.id = td.id_transaksi
                JOIN produk p ON td.id_produk = p.id
                WHERE p.id_penjual = '$penjual_id' 
                AND t.status = 'selesai' 
                AND DATE_FORMAT(t.created_at, '%Y-%m') = '$bulan_ini'";
$result_omzet = mysqli_query($conn, $query_omzet);
if ($result_omzet && mysqli_num_rows($result_omzet) > 0) {
    $data_omzet = mysqli_fetch_assoc($result_omzet);
    $stats['omzet_bulan_ini'] = $data_omzet['total'] ?: 0;
}

// 5. Notifikasi belum dibaca
$query_notif = "SELECT COUNT(*) as total FROM notifikasi 
                WHERE id_user = '$penjual_id' 
                AND status = 'unread'";
$result_notif = mysqli_query($conn, $query_notif);
if ($result_notif && mysqli_num_rows($result_notif) > 0) {
    $data_notif = mysqli_fetch_assoc($result_notif);
    $stats['notifikasi'] = $data_notif['total'];
}

// 6. Produk stok hampir habis
$query_stok = "SELECT COUNT(*) as total FROM produk 
               WHERE id_penjual = '$penjual_id' 
               AND stok < 5 AND stok > 0";
$result_stok = mysqli_query($conn, $query_stok);
if ($result_stok && mysqli_num_rows($result_stok) > 0) {
    $data_stok = mysqli_fetch_assoc($result_stok);
    $stats['stok_habis'] = $data_stok['total'];
}

// Format omzet ke format Rupiah
function formatRupiah($angka) {
    if ($angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$omzet_formatted = formatRupiah($stats['omzet_bulan_ini']);

// Function timeAgo untuk notifikasi
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    if ($time_difference < 60) {
        return 'Baru saja';
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return "$minutes menit lalu";
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return "$hours jam lalu";
    } elseif ($time_difference < 2592000) {
        $days = floor($time_difference / 86400);
        return "$days hari lalu";
    } else {
        return date('d M Y', $time_ago);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Penjual - BOOKIE</title>

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

/* Notification Bell */
.notification-btn {
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 10px;
    transition: all 0.3s ease;
    color: #64748b;
    font-size: 20px;
}

.notification-btn:hover {
    background: #f1f5f9;
    color: #3498db;
}

.notification-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #e74c3c;
    color: white;
    font-size: 10px;
    font-weight: 700;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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

.hero .action {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.hero a {
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
    border: none;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
}

.btn-outline {
    border: 2px solid #fff;
    color: #fff;
    background: transparent;
}

.btn-outline:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

/* =====================
   STATS CARDS
===================== */
.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}

.stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #3498db;
}

.stat:nth-child(1)::before { background: #3498db; }
.stat:nth-child(2)::before { background: #2ecc71; }
.stat:nth-child(3)::before { background: #e74c3c; }
.stat:nth-child(4)::before { background: #f39c12; }

.stat h3 {
    margin: 0;
    font-size: 28px;
    color: #020617;
    font-weight: 700;
}

.stat span {
    color: #64748b;
    font-size: 14px;
    display: block;
    margin-top: 4px;
}

/* =====================
   EXTRA STATS
===================== */
.extra-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.extra-stat {
    background: #fff;
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.extra-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.extra-stat-icon.warning { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
.extra-stat-icon.info { background: rgba(52, 152, 219, 0.1); color: #3498db; }

.extra-stat-content h4 {
    margin: 0;
    font-size: 20px;
    color: #020617;
    font-weight: 700;
}

.extra-stat-content span {
    color: #64748b;
    font-size: 13px;
}

/* =====================
   QUICK ACTIONS
===================== */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}

.action-card {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    text-align: center;
    text-decoration: none;
    color: #020617;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    background: linear-gradient(135deg, #f8fafc, #e0f2fe);
}

.action-card i {
    font-size: 28px;
    color: #3498db;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(52, 152, 219, 0.1);
    border-radius: 14px;
}

.action-card span {
    font-size: 14px;
    font-weight: 600;
}

/* =====================
   RESPONSIVE DESIGN
===================== */
@media (max-width: 1200px) {
    .stats,
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

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
        margin-top: 70px;
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
    
    .stats,
    .quick-actions,
    .extra-stats {
        grid-template-columns: 1fr;
    }
    
    .hero {
        padding: 20px;
    }
    
    .hero .action {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .top-bar {
        padding: 0 12px;
    }
    
    .user-info-top {
        display: none;
    }
    
    .main-content {
        padding: 16px;
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

/* NOTIFICATION PANEL */
.notification-panel {
    position: fixed;
    top: 70px;
    right: 24px;
    width: 380px;
    max-height: 500px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    z-index: 1001;
    display: none;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.notification-panel.active {
    display: flex;
}

.notification-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.mark-all-read {
    background: none;
    border: none;
    color: #3498db;
    font-size: 12px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.mark-all-read:hover {
    background: #f1f5f9;
}

.notification-list {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.notification-item {
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #f0f9ff;
}

.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.notification-icon.order { background: rgba(52, 152, 219, 0.1); color: #3498db; }
.notification-icon.chat { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
.notification-icon.alert { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }
.notification-icon.system { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }

.notification-content {
    flex: 1;
}

.notification-text {
    font-size: 13px;
    color: #1e293b;
    margin-bottom: 4px;
    line-height: 1.4;
}

.notification-time {
    font-size: 11px;
    color: #94a3b8;
}

.notification-footer {
    padding: 16px;
    text-align: center;
    border-top: 1px solid #e2e8f0;
}

.view-all {
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.view-all:hover {
    color: #2980b9;
}

/* Notification backdrop */
.notification-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1000;
    display: none;
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
    <a href="profile.php" class="sidebar-profile">
        <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
        <div>
            <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
            <div class="role">Penjual</div>
        </div>
    </a>

    <!-- MENU SCROLLABLE -->
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item active">
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
            <?php if($stats['total_produk'] > 0): ?>
            <span class="menu-badge" id="produk-badge"><?= $stats['total_produk'] ?></span>
            <?php endif; ?>
        </a>
        
        <a href="chat/chat.php" class="menu-item">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
            <?php if($stats['chat_baru'] > 0): ?>
            <span class="menu-badge" id="chat-badge"><?= $stats['chat_baru'] ?></span>
            <?php endif; ?>
        </a>
        
        <a href="pesanan.php" class="menu-item">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
            <?php if($stats['pesanan_aktif'] > 0): ?>
            <span class="menu-badge" id="pesanan-badge"><?= $stats['pesanan_aktif'] ?></span>
            <?php endif; ?>
        </a>
        
        <a href="status.php" class="menu-item">
            <i class="bi bi-activity"></i>
            <span>Status</span>
        </a>
        
        <a href="laporan.php" class="menu-item">
            <i class="bi bi-bar-chart"></i>
            <span>Laporan</span>
        </a>
        
        <a href="penjual_lain.php" class="menu-item">
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
        
        <a href="help.php" class="footer-btn help">
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
        <input type="text" class="search-box" placeholder="Cari produk, pesanan, atau pelanggan...">
    </div>
    
    <!-- Search Toggle (Mobile) -->
    <button class="search-toggle" id="searchToggle">
        <i class="bi bi-search"></i>
    </button>
    
    <!-- Right Section -->
    <div class="top-bar-right">
        <!-- Notification -->
        <div class="notification-wrapper">
            <button class="notification-btn" id="notificationBtn">
                <i class="bi bi-bell"></i>
                <?php if($stats['notifikasi'] > 0): ?>
                <span class="notification-badge"><?= $stats['notifikasi'] ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Notification Panel -->
            <div class="notification-panel" id="notificationPanel">
                <div class="notification-header">
                    <h4>Notifikasi</h4>
                    <button class="mark-all-read">Tandai semua dibaca</button>
                </div>
                <div class="notification-list" id="notificationList">
                    <?php
                    // Ambil notifikasi terbaru
                    $query_notifications = "SELECT * FROM notifikasi 
                                           WHERE id_user = '$penjual_id' 
                                           ORDER BY created_at DESC 
                                           LIMIT 5";
                    $result_notifications = mysqli_query($conn, $query_notifications);
                    
                    if ($result_notifications && mysqli_num_rows($result_notifications) > 0) {
                        while ($notif = mysqli_fetch_assoc($result_notifications)) {
                            // Tentukan tipe notifikasi (default system)
                            $tipe = 'system';
                            $icon_class = 'system';
                            $icon = 'bell';
                            
                            // Cek apakah pesan mengandung kata kunci tertentu
                            $pesan_lower = strtolower($notif['pesan']);
                            if (strpos($pesan_lower, 'pesanan') !== false || strpos($pesan_lower, 'order') !== false) {
                                $tipe = 'order';
                                $icon_class = 'order';
                                $icon = 'cart-check';
                            } elseif (strpos($pesan_lower, 'pembayaran') !== false || strpos($pesan_lower, 'payment') !== false) {
                                $tipe = 'payment';
                                $icon_class = 'alert';
                                $icon = 'cash-coin';
                            } elseif (strpos($pesan_lower, 'status') !== false) {
                                $tipe = 'status';
                                $icon_class = 'chat';
                                $icon = 'info-circle';
                            }
                            
                            $unread_class = ($notif['status'] == 'unread') ? 'unread' : '';
                    ?>
                    <div class="notification-item <?= $unread_class ?>" data-id="<?= $notif['id'] ?>">
                        <div class="notification-icon <?= $icon_class ?>">
                            <i class="bi bi-<?= $icon ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-text">
                                <?= htmlspecialchars($notif['pesan']) ?>
                            </div>
                            <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                        </div>
                    </div>
                    <?php
                        }
                    } else {
                        echo '<div class="notification-item"><div class="notification-content"><div class="notification-text">Tidak ada notifikasi</div></div></div>';
                    }
                    ?>
                </div>
                <div class="notification-footer">
                    <a href="notifikasi.php" class="view-all">
                        Lihat semua notifikasi <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- User Profile -->
        <a href="profile.php" class="user-profile-top">
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile">
            <div class="user-info-top">
                <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
                <div class="role">Penjual</div>
            </div>
        </a>
    </div>
</div>

<!-- NOTIFICATION BACKDROP -->
<div class="notification-backdrop" id="notificationBackdrop"></div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <!-- HERO -->
    <div class="hero">
        <h2>Selamat datang, <?= htmlspecialchars($namaPenjual) ?>! 👋</h2>
        <p>
            Kelola toko buku Anda dengan mudah. Pantau statistik, kelola produk, dan layani pelanggan dengan lebih baik.
        </p>
        <div class="action">
            <a href="produk_tambah.php" class="btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Produk
            </a>
            <a href="pesanan.php" class="btn-outline">
                <i class="bi bi-receipt"></i> Kelola Pesanan
            </a>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <h3 id="stat-produk"><?= $stats['total_produk'] ?></h3>
            <span>Total Produk</span>
        </div>
        <div class="stat">
            <h3 id="stat-pesanan"><?= $stats['pesanan_aktif'] ?></h3>
            <span>Pesanan Aktif</span>
        </div>
        <div class="stat">
            <h3 id="stat-chat"><?= $stats['chat_baru'] ?></h3>
            <span>Chat Baru</span>
        </div>
        <div class="stat">
            <h3 id="stat-omzet"><?= $omzet_formatted ?></h3>
            <span>Omzet Bulan Ini</span>
        </div>
    </div>

    <!-- EXTRA STATS -->
    <div class="extra-stats">
        <div class="extra-stat">
            <div class="extra-stat-icon warning">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="extra-stat-content">
                <h4><?= $stats['stok_habis'] ?></h4>
                <span>Produk Stok Rendah</span>
            </div>
        </div>
        <div class="extra-stat">
            <div class="extra-stat-icon info">
                <i class="bi bi-bell"></i>
            </div>
            <div class="extra-stat-content">
                <h4><?= $stats['notifikasi'] ?></h4>
                <span>Notifikasi Baru</span>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions">
        <a href="produk.php" class="action-card">
            <i class="bi bi-box"></i>
            <span>Kelola Produk</span>
        </a>
        <a href="pesanan.php" class="action-card">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
        </a>
        <a href="chat.php" class="action-card">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
        </a>
        <a href="laporan.php" class="action-card">
            <i class="bi bi-graph-up"></i>
            <span>Laporan</span>
        </a>
    </div>
</div>

<script>
// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const topBar = document.getElementById('topBar');
const mainContent = document.getElementById('mainContent');
const searchContainer = document.getElementById('searchContainer');
const searchToggle = document.getElementById('searchToggle');
const notificationBtn = document.getElementById('notificationBtn');
const notificationPanel = document.getElementById('notificationPanel');
const notificationBackdrop = document.getElementById('notificationBackdrop');
const notificationList = document.getElementById('notificationList');
const notificationBadge = document.querySelector('.notification-badge');

// Menu Toggle
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Search Toggle (Mobile)
searchToggle.addEventListener('click', () => {
    searchContainer.classList.toggle('active');
});

// Notification Toggle
let notificationOpen = false;

notificationBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationOpen = !notificationOpen;
    notificationPanel.classList.toggle('active');
    notificationBackdrop.style.display = notificationOpen ? 'block' : 'none';
});

// Close notification when clicking outside
document.addEventListener('click', (e) => {
    if (notificationOpen && 
        !notificationPanel.contains(e.target) && 
        !notificationBtn.contains(e.target)) {
        closeNotifications();
    }
});

notificationBackdrop.addEventListener('click', closeNotifications);

function closeNotifications() {
    notificationOpen = false;
    notificationPanel.classList.remove('active');
    notificationBackdrop.style.display = 'none';
}

// Mark notification as read when clicked
notificationList.addEventListener('click', (e) => {
    const notificationItem = e.target.closest('.notification-item');
    if (notificationItem && notificationItem.dataset.id) {
        // Remove unread class
        notificationItem.classList.remove('unread');
        
        // Send AJAX request to mark as read
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + notificationItem.dataset.id
        });
    }
});

// Mark all as read
document.querySelector('.mark-all-read')?.addEventListener('click', () => {
    document.querySelectorAll('.notification-item.unread').forEach(item => {
        item.classList.remove('unread');
    });
    
    // Send AJAX request to mark all as read
    fetch('mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=<?= $penjual_id ?>'
    });
});

// Animated counter for stats
function animateCounter(element, target, isCurrency = false) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            if (isCurrency) {
                element.textContent = formatRupiah(target);
            } else {
                element.textContent = target;
            }
            clearInterval(timer);
        } else {
            if (isCurrency) {
                element.textContent = formatRupiah(Math.floor(current));
            } else {
                element.textContent = Math.floor(current);
            }
        }
    }, 30);
}

function formatRupiah(angka) {
    if (angka == 0) return 'Rp 0';
    return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Initialize counter animations
document.addEventListener('DOMContentLoaded', () => {
    // Get stat values
    const statProduk = parseInt(document.getElementById('stat-produk').textContent) || 0;
    const statPesanan = parseInt(document.getElementById('stat-pesanan').textContent) || 0;
    const statChat = parseInt(document.getElementById('stat-chat').textContent) || 0;
    
    // Get omzet value (remove non-numeric characters)
    const omzetText = document.getElementById('stat-omzet').textContent;
    const statOmzet = parseInt(omzetText.replace(/[^0-9]/g, '')) || 0;
    
    // Reset text to 0 for animation (if not already 0)
    if (statProduk > 0) document.getElementById('stat-produk').textContent = '0';
    if (statPesanan > 0) document.getElementById('stat-pesanan').textContent = '0';
    if (statChat > 0) document.getElementById('stat-chat').textContent = '0';
    if (statOmzet > 0) document.getElementById('stat-omzet').textContent = 'Rp 0';
    
    // Start animations after a short delay
    setTimeout(() => {
        if (statProduk > 0) animateCounter(document.getElementById('stat-produk'), statProduk);
        if (statPesanan > 0) animateCounter(document.getElementById('stat-pesanan'), statPesanan);
        if (statChat > 0) animateCounter(document.getElementById('stat-chat'), statChat);
        if (statOmzet > 0) animateCounter(document.getElementById('stat-omzet'), statOmzet, true);
    }, 500);
});

// Responsive adjustments
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

// Search functionality
const searchBox = document.querySelector('.search-box');
searchBox.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const query = searchBox.value.trim();
        if (query) {
            window.location.href = `produk.php?search=${encodeURIComponent(query)}`;
        }
    }
});

// Logout function
function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = '../auth/logout.php';
    }
}
</script>

</body>
</html>