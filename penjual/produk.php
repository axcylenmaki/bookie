<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Data penjual
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';
$fotoUser = $_SESSION['user']['foto'] ?? '';

// Path foto profil
$fotoPath = (!empty($fotoUser) && file_exists("../uploads/".$fotoUser))
    ? "../uploads/".$fotoUser
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
   HAPUS PRODUK
===================== */
if (isset($_POST['aksi']) && $_POST['aksi']=="hapus") {
  $id = mysqli_real_escape_string($conn, $_POST['id']);
  mysqli_query($conn,"DELETE FROM produk 
    WHERE id='$id' AND id_penjual='$idPenjual' AND stok=0");
  header("Location: produk.php");
  exit;
}

/* =====================
   FILTER
===================== */
$cari = isset($_GET['cari']) ? mysqli_real_escape_string($conn, $_GET['cari']) : '';
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($conn, $_GET['kategori']) : '';
$stok = isset($_GET['stok']) ? mysqli_real_escape_string($conn, $_GET['stok']) : '';

$where = "WHERE p.id_penjual='$idPenjual'";
if ($cari) $where .= " AND p.nama_produk LIKE '%$cari%'";
if ($kategori) $where .= " AND p.kategori_id='$kategori'";
if ($stok=='habis') $where .= " AND p.stok=0";
if ($stok=='ada') $where .= " AND p.stok>0";

/* =====================
   DATA
===================== */
$qKategori = mysqli_query($conn,"SELECT * FROM kategori");

$qProduk = mysqli_query($conn,"
  SELECT p.*, k.nama_kategori
  FROM produk p 
  JOIN kategori k ON p.kategori_id = k.id
  $where 
  ORDER BY p.id DESC
");

$totalProduk = mysqli_num_rows($qProduk);

// Hitung statistik produk
$qStatProduk = mysqli_query($conn, "
  SELECT 
    SUM(CASE WHEN stok = 0 THEN 1 ELSE 0 END) as habis,
    SUM(CASE WHEN stok > 0 AND stok < 5 THEN 1 ELSE 0 END) as rendah,
    SUM(CASE WHEN stok >= 5 THEN 1 ELSE 0 END) as cukup
  FROM produk 
  WHERE id_penjual='$idPenjual'
");
$statProduk = mysqli_fetch_assoc($qStatProduk);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Produk - BOOKIE</title>

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

/* =====================
   PRODUCT STATS
===================== */
.product-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.product-stat {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
    position: relative;
    overflow: hidden;
}

.product-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.product-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.product-stat:nth-child(1)::before { background: #3498db; }
.product-stat:nth-child(2)::before { background: #2ecc71; }
.product-stat:nth-child(3)::before { background: #f39c12; }
.product-stat:nth-child(4)::before { background: #e74c3c; }

.product-stat h3 {
    margin: 0;
    font-size: 28px;
    color: #020617;
    font-weight: 700;
}

.product-stat span {
    color: #64748b;
    font-size: 14px;
    display: block;
    margin-top: 4px;
}

/* =====================
   FILTER SECTION
===================== */
.filter-section {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.filter-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.filter-header i {
    color: #3498db;
    font-size: 20px;
}

.filter-header h3 {
    margin: 0;
    font-size: 18px;
    color: #020617;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.filter-group {
    margin-bottom: 0;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.filter-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.filter-input:focus {
    outline: none;
    border-color: #3498db;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.filter-button {
    display: flex;
    align-items: flex-end;
}

.filter-button .btn {
    width: 100%;
    padding: 12px;
    border-radius: 10px;
    font-weight: 600;
    border: none;
    background: #3498db;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-button .btn:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

/* =====================
   PRODUCTS TABLE
===================== */
.products-section {
    background: #fff;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    color: #020617;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header h3 i {
    color: #3498db;
}

.add-product-btn {
    padding: 10px 20px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.add-product-btn:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    color: white;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.product-table {
    width: 100%;
    border-collapse: collapse;
}

.product-table thead {
    background: #f8fafc;
}

.product-table th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    border-bottom: 2px solid #e2e8f0;
}

.product-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.product-table tbody tr {
    transition: all 0.3s ease;
}

.product-table tbody tr:hover {
    background: #f8fafc;
}

/* Product Image */
.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    cursor: pointer;
}

/* Product Info */
.product-info {
    display: flex;
    flex-direction: column;
}

.product-name {
    font-weight: 600;
    color: #020617;
    margin-bottom: 4px;
    cursor: pointer;
}

.product-name:hover {
    color: #3498db;
}

/* Category Badge */
.category-badge {
    padding: 6px 12px;
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

/* Stock Badge */
.stock-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.stock-badge.high {
    background: rgba(46, 204, 113, 0.1);
    color: #27ae60;
}

.stock-badge.low {
    background: rgba(243, 156, 18, 0.1);
    color: #f39c12;
}

.stock-badge.empty {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
}

.stock-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.stock-dot.high { background: #2ecc71; }
.stock-dot.low { background: #f39c12; }
.stock-dot.empty { background: #e74c3c; }

/* Price */
.product-price {
    font-weight: 700;
    color: #020617;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.edit-btn {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.edit-btn:hover {
    background: #3498db;
    color: white;
    transform: translateY(-2px);
}

.delete-btn {
    background: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
}

.delete-btn:hover:not(:disabled) {
    background: #e74c3c;
    color: white;
    transform: translateY(-2px);
}

.delete-btn:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    transform: none;
}

/* =====================
   EMPTY STATE
===================== */
.empty-state {
    padding: 60px 20px;
    text-align: center;
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
    color: #64748b;
}

.empty-state p {
    max-width: 400px;
    margin: 0 auto 20px;
    line-height: 1.6;
}

/* =====================
   RESPONSIVE DESIGN
===================== */
@media (max-width: 1200px) {
    .product-stats,
    .filter-grid {
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
    
    .product-stats,
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .add-product-btn {
        width: 100%;
        justify-content: center;
    }
    
    .table-container {
        border: none;
        border-radius: 0;
    }
    
    .product-table {
        display: block;
    }
    
    .product-table thead {
        display: none;
    }
    
    .product-table tbody tr {
        display: block;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
    }
    
    .product-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    
    .product-table td:last-child {
        border-bottom: none;
    }
    
    .product-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #374151;
        margin-right: 10px;
    }
    
    .action-buttons {
        justify-content: flex-end;
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
    
    .hero {
        padding: 20px;
    }
    
    .filter-section,
    .products-section {
        padding: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
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

.d-flex {
    display: flex;
}

.align-items-center {
    align-items: center;
}

.gap-3 {
    gap: 15px;
}

.me-2 {
    margin-right: 8px;
}

.d-inline {
    display: inline;
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
        <a href="dashboard.php" class="menu-item">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="profile.php" class="menu-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        
        <a href="produk.php" class="menu-item active">
            <i class="bi bi-box"></i>
            <span>Produk</span>
            <?php if($totalProduk > 0): ?>
            <span class="menu-badge"><?= $totalProduk ?></span>
            <?php endif; ?>
        </a>
        
        <!-- PERBAIKAN: Link chat mengarah ke chat/chat.php -->
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

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">
    <!-- HERO -->
    <div class="hero">
        <h2>Manajemen Produk</h2>
        <p>
            Kelola produk buku yang Anda jual. Tambah, edit, atau hapus produk dengan mudah.
        </p>
    </div>

    <!-- PRODUCT STATS -->
    <div class="product-stats">
        <div class="product-stat">
            <h3><?= number_format($totalProduk) ?></h3>
            <span>Total Produk</span>
        </div>
        <div class="product-stat">
            <h3><?= number_format($statProduk['cukup'] ?? 0) ?></h3>
            <span>Stok Cukup</span>
        </div>
        <div class="product-stat">
            <h3><?= number_format($statProduk['rendah'] ?? 0) ?></h3>
            <span>Stok Rendah</span>
        </div>
        <div class="product-stat">
            <h3><?= number_format($statProduk['habis'] ?? 0) ?></h3>
            <span>Stok Habis</span>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="filter-header">
            <i class="bi bi-funnel"></i>
            <h3>Filter Produk</h3>
        </div>
        
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label for="cari">Cari Buku</label>
                <input type="text" id="cari" name="cari" class="filter-input" 
                       placeholder="Nama buku..." value="<?= htmlspecialchars($cari) ?>">
            </div>
            
            <div class="filter-group">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" class="filter-input">
                    <option value="">Semua Kategori</option>
                    <?php 
                    mysqli_data_seek($qKategori, 0); 
                    while($k = mysqli_fetch_assoc($qKategori)): 
                    ?>
                    <option value="<?= $k['id'] ?>" <?= $kategori==$k['id']?'selected':'' ?>>
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="stok">Status Stok</label>
                <select id="stok" name="stok" class="filter-input">
                    <option value="">Semua Stok</option>
                    <option value="ada" <?= $stok=='ada'?'selected':'' ?>>Stok Tersedia</option>
                    <option value="habis" <?= $stok=='habis'?'selected':'' ?>>Stok Habis</option>
                </select>
            </div>
            
            <div class="filter-group filter-button">
                <button type="submit" class="btn">
                    <i class="bi bi-funnel"></i> Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- PRODUCTS SECTION -->
    <div class="products-section">
        <div class="section-header">
            <h3>
                <i class="bi bi-box"></i>
                Daftar Produk
            </h3>
            <a href="produk_tambah.php" class="add-product-btn">
                <i class="bi bi-plus-circle"></i>
                Tambah Produk
            </a>
        </div>
        
        <div class="table-container">
            <?php if(mysqli_num_rows($qProduk) > 0): ?>
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Harga</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($qProduk, 0);
                    while($p = mysqli_fetch_assoc($qProduk)): 
                        // Determine stock class
                        $stockClass = 'high';
                        if($p['stok'] == 0) {
                            $stockClass = 'empty';
                        } elseif($p['stok'] < 5) {
                            $stockClass = 'low';
                        }
                        
                        $imagePath = !empty($p['gambar']) && file_exists("../uploads/".$p['gambar']) 
                            ? "../uploads/".$p['gambar'] 
                            : "../assets/img/product-placeholder.png";
                    ?>
                    <tr>
                        <td data-label="Produk">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= htmlspecialchars($imagePath) ?>" 
                                     alt="<?= htmlspecialchars($p['nama_produk']) ?>" 
                                     class="product-img">
                                <div class="product-info">
                                    <div class="product-name">
                                        <?= htmlspecialchars($p['nama_produk']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Kategori">
                            <span class="category-badge">
                                <?= htmlspecialchars($p['nama_kategori']) ?>
                            </span>
                        </td>
                        <td data-label="Stok">
                            <span class="stock-badge <?= $stockClass ?>">
                                <span class="stock-dot <?= $stockClass ?>"></span>
                                <?= $p['stok'] ?> unit
                            </span>
                        </td>
                        <td data-label="Harga" class="product-price">
                            Rp <?= number_format($p['harga'], 0, ',', '.') ?>
                        </td>
                        <td data-label="Aksi">
                            <div class="action-buttons">
                                <a href="produk_edit.php?id=<?= $p['id'] ?>" 
                                   class="action-btn edit-btn">
                                    <i class="bi bi-pencil"></i>
                                    Edit
                                </a>
                                <?php if ($p['stok'] == 0): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" 
                                            class="action-btn delete-btn"
                                            onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                        <i class="bi bi-trash"></i>
                                        Hapus
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="action-btn delete-btn" disabled title="Produk dengan stok > 0 tidak dapat dihapus">
                                    <i class="bi bi-trash"></i>
                                    Hapus
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-box"></i>
                <h4>Tidak ada produk</h4>
                <p>
                    <?php if($cari || $kategori || $stok): ?>
                    Tidak ada produk yang sesuai dengan filter yang Anda pilih.
                    <?php else: ?>
                    Belum ada produk. Mulai dengan menambahkan produk pertama Anda.
                    <?php endif; ?>
                </p>
                <a href="produk_tambah.php" class="add-product-btn" style="display: inline-flex;">
                    <i class="bi bi-plus-circle"></i>
                    Tambah Produk Pertama
                </a>
            </div>
            <?php endif; ?>
        </div>
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

// Menu Toggle
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Search Toggle (Mobile)
searchToggle.addEventListener('click', () => {
    searchContainer.classList.toggle('active');
});

// Auto focus search field if there's search parameter
document.addEventListener('DOMContentLoaded', () => {
    const searchField = document.querySelector('input[name="cari"]');
    if (searchField && searchField.value) {
        searchField.focus();
        searchField.select();
    }
    
    // Responsive adjustments
    handleResize();
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

// Search functionality
const searchBox = document.querySelector('.search-box');
searchBox.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const query = searchBox.value.trim();
        if (query) {
            window.location.href = `produk.php?cari=${encodeURIComponent(query)}`;
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