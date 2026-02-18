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
   AMBIL DATA PRODUK
===================== */
$id = $_GET['id'] ?? 0;
$query = mysqli_query($conn, "SELECT * FROM produk WHERE id='$id' AND id_penjual='$idPenjual'");
$produk = mysqli_fetch_assoc($query);

if (!$produk) {
  header("Location: produk.php");
  exit;
}

/* =====================
   EDIT PRODUK
===================== */
if (isset($_POST['simpan'])) {
  $nama = mysqli_real_escape_string($conn, htmlspecialchars($_POST['nama']));
  $pengarang = mysqli_real_escape_string($conn, htmlspecialchars($_POST['pengarang'] ?? ''));
  $isbn = mysqli_real_escape_string($conn, htmlspecialchars($_POST['isbn'] ?? ''));
  $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
  $stok = (int)$_POST['stok'];
  $harga = (int)$_POST['harga'];
  $modal = (int)$_POST['modal'];
  $deskripsi = mysqli_real_escape_string($conn, htmlspecialchars($_POST['deskripsi']));

  $margin = $harga - $modal;
  $keuntungan = $margin * $stok;

  $updateGambar = "";
  $namaFile = $produk['gambar']; // default pakai gambar lama

  if (!empty($_FILES['gambar']['name'])) {
    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($ext, $allowed_ext)) {
      if ($_FILES['gambar']['size'] <= 2 * 1024 * 1024) { // 2MB max
        // Hapus gambar lama jika ada
        if (!empty($produk['gambar']) && file_exists("../uploads/".$produk['gambar'])) {
          @unlink("../uploads/".$produk['gambar']);
        }
        
        $namaFile = "produk_".time()."_".rand(100,999).".".$ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], "../uploads/".$namaFile);
        $updateGambar = ", gambar='$namaFile'";
      } else {
        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
      }
    } else {
      $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
    }
  }

  if (!isset($error)) {
    $query = "UPDATE produk SET 
        nama_produk='$nama',
        pengarang='$pengarang',
        isbn='$isbn',
        kategori_id='$kategori',
        stok='$stok',
        harga='$harga',
        modal='$modal',
        margin='$margin',
        keuntungan='$keuntungan',
        deskripsi='$deskripsi'
        $updateGambar
      WHERE id='$id' AND id_penjual='$idPenjual'";
    
    mysqli_query($conn, $query);

    header("Location: produk.php?success=edit");
    exit;
  }
}

$qKategori = mysqli_query($conn,"SELECT * FROM kategori ORDER BY nama_kategori");

// Path gambar produk
$gambarPath = (!empty($produk['gambar']) && file_exists("../uploads/".$produk['gambar']))
    ? "../uploads/".$produk['gambar']
    : "../assets/img/product-placeholder.png";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Produk - BOOKIE</title>

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
   SIDEBAR (COPY DARI DASHBOARD)
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
   ALERTS
===================== */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: none;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(46, 204, 113, 0.1);
    color: #27ae60;
    border-left: 4px solid #2ecc71;
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    color: #c0392b;
    border-left: 4px solid #e74c3c;
}

/* =====================
   FORM CONTAINER
===================== */
.form-container {
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

/* =====================
   FORM SECTIONS
===================== */
.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid #f1f5f9;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.section-header i {
    color: #3498db;
    font-size: 20px;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    color: #020617;
}

/* =====================
   FORM CONTROLS
===================== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.form-label.required::after {
    content: " *";
    color: #e74c3c;
}

.form-label.optional::after {
    content: " (Opsional)";
    color: #64748b;
    font-weight: 400;
    font-size: 12px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.input-group {
    display: flex;
    border-radius: 10px;
    overflow: hidden;
}

.input-group .form-control {
    border-radius: 0 10px 10px 0;
    border-left: none;
}

.input-group-prepend {
    display: flex;
    align-items: center;
    padding: 0 16px;
    background: #f1f5f9;
    border: 2px solid #e2e8f0;
    border-right: none;
    border-radius: 10px 0 0 10px;
    color: #64748b;
    font-weight: 500;
}

/* =====================
   CALCULATION CARD
===================== */
.calculation-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid #3498db;
    margin-top: 20px;
}

.calculation-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.calculation-item:last-child {
    border-bottom: none;
    padding-top: 15px;
    font-weight: 700;
    color: #020617;
}

.calculation-label {
    color: #64748b;
}

.calculation-value {
    font-weight: 600;
    color: #020617;
}

.calculation-value.success {
    color: #2ecc71;
}

.calculation-value.danger {
    color: #e74c3c;
}

.calculation-value.info {
    color: #3498db;
}

/* =====================
   IMAGE UPLOAD & PREVIEW
===================== */
.image-upload {
    margin-top: 15px;
}

.file-input {
    position: relative;
}

.file-input input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-label {
    display: block;
    padding: 20px;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    text-align: center;
    color: #64748b;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.file-label:hover {
    border-color: #3498db;
    color: #3498db;
}

.file-label i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

.image-preview {
    margin-top: 20px;
    text-align: center;
}

.preview-img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
}

.current-image {
    border: 3px solid #28a745;
    padding: 5px;
    background: #f8f9fa;
}

.new-image {
    border: 3px dashed #3498db;
    padding: 5px;
    background: #f8f9fa;
}

.image-comparison {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    margin-bottom: 20px;
}

.image-box {
    flex: 1;
    text-align: center;
}

.image-box h6 {
    margin-bottom: 15px;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
}

.current-badge {
    background: #28a745;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    position: absolute;
    top: 10px;
    left: 10px;
}

/* =====================
   FORM ACTIONS
===================== */
.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
    justify-content: flex-end;
}

.btn {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
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

.btn-secondary {
    background: #f1f5f9;
    color: #64748b;
}

.btn-secondary:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
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
    
    .hero {
        padding: 20px;
    }
    
    .form-container {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .image-comparison {
        flex-direction: column;
        gap: 20px;
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

.text-muted {
    color: #64748b !important;
    font-size: 12px;
    margin-top: 4px;
    display: block;
}

.d-none {
    display: none !important;
}

.mb-3 {
    margin-bottom: 1rem;
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
        </a>
        
        <a href="chat.php" class="menu-item">
            <i class="bi bi-chat-dots"></i>
            <span>Chat</span>
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
    <!-- ALERTS -->
    <?php if(isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- HERO -->
    <div class="hero">
        <h2>Edit Produk</h2>
        <p>
            Perbarui informasi produk buku yang sudah ada. Pastikan semua data diisi dengan benar.
        </p>
    </div>

    <!-- FORM CONTAINER -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" id="productForm">
            <!-- INFORMASI PRODUK -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-info-circle"></i>
                    <h3>Informasi Produk</h3>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nama" class="form-label required">Nama Produk</label>
                        <input type="text" id="nama" name="nama" class="form-control" required 
                               value="<?= htmlspecialchars($produk['nama_produk']) ?>" 
                               placeholder="Masukkan nama produk" maxlength="150">
                        <span class="text-muted">Maksimal 150 karakter</span>
                    </div>

                    <div class="form-group">
                        <label for="pengarang" class="form-label optional">Pengarang</label>
                        <input type="text" id="pengarang" name="pengarang" class="form-control" 
                               value="<?= htmlspecialchars($produk['pengarang'] ?? '') ?>" 
                               placeholder="Nama pengarang" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="isbn" class="form-label optional">ISBN</label>
                        <input type="text" id="isbn" name="isbn" class="form-control" 
                               value="<?= htmlspecialchars($produk['isbn'] ?? '') ?>" 
                               placeholder="Nomor ISBN" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label for="kategori" class="form-label required">Kategori</label>
                        <select id="kategori" name="kategori" class="form-control" required>
                            <option value="">Pilih Kategori</option>
                            <?php 
                            mysqli_data_seek($qKategori, 0);
                            while($k = mysqli_fetch_assoc($qKategori)): 
                            ?>
                            <option value="<?= $k['id'] ?>" <?= $produk['kategori_id']==$k['id']?'selected':'' ?>>
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="deskripsi" class="form-label">Deskripsi Produk</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="4"
                                  placeholder="Deskripsikan produk Anda..."
                                  maxlength="1000"><?= htmlspecialchars($produk['deskripsi']) ?></textarea>
                        <span class="text-muted">Maksimal 1000 karakter</span>
                    </div>
                </div>
            </div>

            <!-- HARGA DAN KALKULASI -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-cash-stack"></i>
                    <h3>Harga & Kalkulasi</h3>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal" class="form-label required">Harga Modal (per unit)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">Rp</div>
                            <input type="number" id="modal" name="modal" class="form-control" 
                                   min="0" required value="<?= $produk['modal'] ?? 0 ?>">
                        </div>
                        <span class="text-muted">Harga beli dari supplier</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="harga" class="form-label required">Harga Jual (per unit)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">Rp</div>
                            <input type="number" id="harga" name="harga" class="form-control" 
                                   min="0" required value="<?= $produk['harga'] ?>">
                        </div>
                        <span class="text-muted">Harga yang dibayar pembeli</span>
                    </div>

                    <div class="form-group">
                        <label for="stok" class="form-label required">Stok</label>
                        <input type="number" id="stok" name="stok" class="form-control" 
                               min="0" required value="<?= $produk['stok'] ?>">
                    </div>
                </div>
                
                <!-- KALKULASI -->
                <div class="calculation-card">
                    <h4 class="mb-3">Kalkulasi Keuntungan</h4>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Harga Modal:</span>
                        <span class="calculation-value" id="modalDisplay">Rp <?= number_format($produk['modal'] ?? 0) ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Harga Jual:</span>
                        <span class="calculation-value" id="hargaDisplay">Rp <?= number_format($produk['harga']) ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Margin per Unit:</span>
                        <span class="calculation-value info" id="marginDisplay">Rp <?= number_format($produk['margin'] ?? 0) ?></span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Stok:</span>
                        <span class="calculation-value" id="stokDisplay"><?= $produk['stok'] ?> unit</span>
                    </div>
                    
                    <div class="calculation-item">
                        <span class="calculation-label">Total Keuntungan Potensial:</span>
                        <span class="calculation-value success" id="keuntunganDisplay">Rp <?= number_format(($produk['margin'] ?? 0) * $produk['stok']) ?></span>
                    </div>
                </div>
            </div>

            <!-- GAMBAR PRODUK -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-image"></i>
                    <h3>Gambar Produk</h3>
                </div>
                
                <?php if (!empty($produk['gambar'])): ?>
                <div class="image-comparison">
                    <div class="image-box">
                        <h6>Gambar Saat Ini</h6>
                        <div style="position: relative; display: inline-block;">
                            <img src="<?= $gambarPath ?>" 
                                 class="preview-img current-image"
                                 onerror="this.src='../assets/img/product-placeholder.png'"
                                 alt="Gambar Saat Ini">
                            <span class="current-badge">Saat Ini</span>
                        </div>
                    </div>
                    
                    <div class="image-box">
                        <h6>Pratinjau Gambar Baru</h6>
                        <div id="imagePreview" class="d-none">
                            <img id="preview" class="preview-img new-image" alt="Preview Gambar Baru">
                        </div>
                        <div id="noPreview" class="text-center py-4">
                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">Belum ada gambar baru</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Produk ini belum memiliki gambar
                </div>
                <?php endif; ?>
                
                <div class="image-upload">
                    <div class="form-group">
                        <label class="form-label optional">Upload Gambar Baru</label>
                        <div class="file-input">
                            <input type="file" id="gambar" name="gambar" accept="image/*">
                            <label for="gambar" class="file-label">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>Klik untuk upload gambar baru</span>
                                <small>JPG, PNG, GIF, WebP (Maks. 2MB)</small>
                            </label>
                        </div>
                        <span class="text-muted">Kosongkan jika tidak ingin mengganti gambar</span>
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <a href="produk.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
                <button type="submit" name="simpan" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Simpan Perubahan
                </button>
            </div>
        </form>
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
const productForm = document.getElementById('productForm');
const modalInput = document.getElementById('modal');
const hargaInput = document.getElementById('harga');
const stokInput = document.getElementById('stok');
const gambarInput = document.getElementById('gambar');
const previewImg = document.getElementById('preview');
const imagePreview = document.getElementById('imagePreview');
const noPreview = document.getElementById('noPreview');
const submitBtn = productForm.querySelector('button[type="submit"]');

// Menu Toggle
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Search Toggle (Mobile)
searchToggle.addEventListener('click', () => {
    searchContainer.classList.toggle('active');
});

// Image Preview
if (gambarInput) {
    gambarInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Validasi ukuran file
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran file terlalu besar. Maksimal 2MB.');
                this.value = '';
                if (imagePreview) imagePreview.classList.add('d-none');
                if (noPreview) noPreview.classList.remove('d-none');
                return;
            }
            
            // Validasi tipe file
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
                this.value = '';
                if (imagePreview) imagePreview.classList.add('d-none');
                if (noPreview) noPreview.classList.remove('d-none');
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if (previewImg) {
                    previewImg.src = e.target.result;
                }
                if (imagePreview) {
                    imagePreview.classList.remove('d-none');
                }
                if (noPreview) {
                    noPreview.classList.add('d-none');
                }
            }
            
            reader.readAsDataURL(file);
        } else {
            if (imagePreview) imagePreview.classList.add('d-none');
            if (noPreview) noPreview.classList.remove('d-none');
        }
    });
}

// Kalkulasi keuntungan
function calculateProfit() {
    const modal = parseInt(modalInput.value) || 0;
    const harga = parseInt(hargaInput.value) || 0;
    const stok = parseInt(stokInput.value) || 0;
    
    const margin = harga - modal;
    const totalKeuntungan = margin * stok;
    
    // Update display
    document.getElementById('modalDisplay').textContent = formatRupiah(modal);
    document.getElementById('hargaDisplay').textContent = formatRupiah(harga);
    document.getElementById('marginDisplay').textContent = formatRupiah(margin);
    document.getElementById('stokDisplay').textContent = stok + ' unit';
    document.getElementById('keuntunganDisplay').textContent = formatRupiah(totalKeuntungan);
    
    // Update margin color
    const marginDisplay = document.getElementById('marginDisplay');
    if (margin > 0) {
        marginDisplay.className = 'calculation-value success';
    } else if (margin < 0) {
        marginDisplay.className = 'calculation-value danger';
    } else {
        marginDisplay.className = 'calculation-value info';
    }
    
    // Update keuntungan color
    const keuntunganDisplay = document.getElementById('keuntunganDisplay');
    if (totalKeuntungan > 0) {
        keuntunganDisplay.className = 'calculation-value success';
    } else if (totalKeuntungan < 0) {
        keuntunganDisplay.className = 'calculation-value danger';
    } else {
        keuntunganDisplay.className = 'calculation-value';
    }
}

// Format Rupiah
function formatRupiah(angka) {
    return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Validasi harga
function validatePrice() {
    const modal = parseInt(modalInput.value) || 0;
    const harga = parseInt(hargaInput.value) || 0;
    
    if (harga < modal) {
        hargaInput.classList.add('is-invalid');
        
        // Tampilkan pesan error
        let errorMsg = hargaInput.nextElementSibling;
        if (!errorMsg || !errorMsg.classList.contains('invalid-feedback')) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'invalid-feedback';
            hargaInput.parentNode.appendChild(errorMsg);
        }
        errorMsg.textContent = 'Harga jual tidak boleh lebih rendah dari harga modal';
        
        submitBtn.disabled = true;
        return false;
    } else {
        hargaInput.classList.remove('is-invalid');
        
        // Hapus pesan error jika ada
        const errorMsg = hargaInput.nextElementSibling;
        if (errorMsg && errorMsg.classList.contains('invalid-feedback')) {
            errorMsg.remove();
        }
        
        submitBtn.disabled = false;
        return true;
    }
}

// Event listeners untuk kalkulasi
if (modalInput) {
    modalInput.addEventListener('input', () => {
        calculateProfit();
        validatePrice();
    });
}

if (hargaInput) {
    hargaInput.addEventListener('input', () => {
        calculateProfit();
        validatePrice();
    });
}

if (stokInput) {
    stokInput.addEventListener('input', calculateProfit);
}

// Form validation
productForm.addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const kategori = document.getElementById('kategori').value;
    const modal = parseInt(modalInput.value) || 0;
    const harga = parseInt(hargaInput.value) || 0;
    const stok = parseInt(stokInput.value) || 0;
    
    // Validasi dasar
    if (!nama) {
        e.preventDefault();
        alert('Nama produk harus diisi!');
        document.getElementById('nama').focus();
        return false;
    }
    
    if (!kategori) {
        e.preventDefault();
        alert('Kategori harus dipilih!');
        document.getElementById('kategori').focus();
        return false;
    }
    
    if (stok < 0) {
        e.preventDefault();
        alert('Stok tidak boleh negatif!');
        stokInput.focus();
        return false;
    }
    
    if (modal < 0) {
        e.preventDefault();
        alert('Harga modal tidak boleh negatif!');
        modalInput.focus();
        return false;
    }
    
    if (harga < 0) {
        e.preventDefault();
        alert('Harga jual tidak boleh negatif!');
        hargaInput.focus();
        return false;
    }
    
    if (harga < modal) {
        e.preventDefault();
        alert('Harga jual tidak boleh lebih rendah dari harga modal!');
        hargaInput.focus();
        return false;
    }
    
    // Konfirmasi sebelum submit
    if (!confirm('Simpan perubahan pada produk ini?')) {
        e.preventDefault();
        return false;
    }
    
    return true;
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

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    handleResize();
    calculateProfit();
    validatePrice();
    
    // Auto-focus on nama field
    document.getElementById('nama').focus();
});
</script>

</body>
</html>