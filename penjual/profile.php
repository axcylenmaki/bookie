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

/* =====================
   DATA PENJUAL
===================== */
$qUser = mysqli_query($conn,"SELECT * FROM users WHERE id='$idPenjual' LIMIT 1");
$user  = mysqli_fetch_assoc($qUser);

if (!$user) {
  header("Location: ../auth/login.php");
  exit;
}

$namaPenjual = $user['nama'] ?? 'Penjual';
$emailPenjual = $user['email'] ?? '';
$noHpPenjual = $user['no_hp'] ?? '';
$alamatPenjual = $user['alamat'] ?? '';
$statusUser = $user['status'] ?? 'offline';
$aktifUser = $user['aktif'] ?? 'ya';
$tanggalGabung = date('d M Y', strtotime($user['created_at'] ?? 'now'));
$fotoUser = $user['foto'] ?? '';

// Path foto profil
$fotoPath = (!empty($fotoUser) && file_exists("../uploads/".$fotoUser))
    ? "../uploads/".$fotoUser
    : "../assets/img/user.png";

/* =====================
   DATA REKENING PENJUAL
===================== */
$qRekening = mysqli_query($conn, "SELECT * FROM rekening_penjual WHERE id_penjual='$idPenjual' LIMIT 1");
$rekening = mysqli_fetch_assoc($qRekening);

$bank = $rekening['bank'] ?? '';
$noRekening = $rekening['no_rekening'] ?? '';
$namaPemilikRekening = $rekening['nama_pemilik'] ?? '';

/* =====================
   STATISTIK TOKO
===================== */
// Total produk
$qTotalProduk = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE id_penjual='$idPenjual'");
$totalProduk = 0;
if ($qTotalProduk && mysqli_num_rows($qTotalProduk) > 0) {
    $dataProduk = mysqli_fetch_assoc($qTotalProduk);
    $totalProduk = $dataProduk['total'];
}

// Total transaksi selesai
$qTotalTransaksi = mysqli_query($conn, "
  SELECT COUNT(DISTINCT t.id) as total 
  FROM transaksi t
  JOIN transaksi_detail td ON t.id = td.id_transaksi
  JOIN produk p ON td.id_produk = p.id
  WHERE p.id_penjual = '$idPenjual' 
  AND t.status = 'selesai'
");
$totalTransaksi = 0;
if ($qTotalTransaksi && mysqli_num_rows($qTotalTransaksi) > 0) {
    $dataTransaksi = mysqli_fetch_assoc($qTotalTransaksi);
    $totalTransaksi = $dataTransaksi['total'];
}

// Total omzet
$qTotalOmzet = mysqli_query($conn, "
  SELECT SUM(td.harga * td.jumlah) as total 
  FROM transaksi t
  JOIN transaksi_detail td ON t.id = td.id_transaksi
  JOIN produk p ON td.id_produk = p.id
  WHERE p.id_penjual = '$idPenjual' 
  AND t.status = 'selesai'
");
$totalOmzet = 0;
if ($qTotalOmzet && mysqli_num_rows($qTotalOmzet) > 0) {
    $dataOmzet = mysqli_fetch_assoc($qTotalOmzet);
    $totalOmzet = $dataOmzet['total'] ?? 0;
}

// Transaksi pending
$qPendingTransaksi = mysqli_query($conn, "
  SELECT COUNT(DISTINCT t.id) as total 
  FROM transaksi t
  JOIN transaksi_detail td ON t.id = td.id_transaksi
  JOIN produk p ON td.id_produk = p.id
  WHERE p.id_penjual = '$idPenjual' 
  AND t.status IN ('pending', 'menunggu', 'diproses', 'dibayar', 'dikirim', 'approve')
");
$pendingTransaksi = 0;
if ($qPendingTransaksi && mysqli_num_rows($qPendingTransaksi) > 0) {
    $dataPending = mysqli_fetch_assoc($qPendingTransaksi);
    $pendingTransaksi = $dataPending['total'];
}

// Hitung chat yang belum dibaca
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
   UPDATE PROFILE & REKENING
===================== */
if (isset($_POST['update'])) {
  $nama   = mysqli_real_escape_string($conn, htmlspecialchars($_POST['nama']));
  $no_hp  = mysqli_real_escape_string($conn, htmlspecialchars($_POST['no_hp']));
  $alamat = mysqli_real_escape_string($conn, htmlspecialchars($_POST['alamat']));
  
  // Data rekening
  $bank = mysqli_real_escape_string($conn, htmlspecialchars($_POST['bank']));
  $no_rekening = mysqli_real_escape_string($conn, htmlspecialchars($_POST['no_rekening']));
  $nama_pemilik = mysqli_real_escape_string($conn, htmlspecialchars($_POST['nama_pemilik']));

  $fotoBaru = $user['foto'];

  // Proses upload foto
  if (!empty($_FILES['foto']['name'])) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($ext, $allowed_ext)) {
      if ($_FILES['foto']['size'] <= 2 * 1024 * 1024) {
        if ($fotoBaru && $fotoBaru != 'user.png' && file_exists("../uploads/".$fotoBaru)) {
          @unlink("../uploads/".$fotoBaru);
        }
        
        $fotoBaru = "toko_".$idPenjual."_".time().".".$ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/".$fotoBaru);
      } else {
        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
      }
    } else {
      $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
    }
  }

  if (!isset($error)) {
    // Update users table
    mysqli_query($conn,"
      UPDATE users SET
        nama='$nama',
        no_hp='$no_hp',
        alamat='$alamat',
        foto='$fotoBaru'
      WHERE id='$idPenjual'
    ");

    // Update atau insert rekening penjual
    if ($rekening) {
      // Update rekening yang sudah ada
      mysqli_query($conn,"
        UPDATE rekening_penjual SET
          bank='$bank',
          no_rekening='$no_rekening',
          nama_pemilik='$nama_pemilik'
        WHERE id_penjual='$idPenjual'
      ");
    } else {
      // Insert rekening baru
      mysqli_query($conn,"
        INSERT INTO rekening_penjual (id_penjual, bank, no_rekening, nama_pemilik)
        VALUES ('$idPenjual', '$bank', '$no_rekening', '$nama_pemilik')
      ");
    }

    $_SESSION['user']['nama'] = $nama;
    $_SESSION['user']['foto'] = $fotoBaru;
    
    header("Location: profile.php?success=1");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Toko - BOOKIE</title>

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
   PROFILE HEADER
===================== */
.profile-header {
    display: flex;
    align-items: center;
    gap: 30px;
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.profile-avatar {
    position: relative;
}

.profile-avatar img {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #fff;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.status-badge {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid #fff;
}

.status-online {
    background: #2ecc71;
}

.status-offline {
    background: #e74c3c;
}

.profile-info h1 {
    margin: 0 0 8px;
    font-size: 28px;
    color: #020617;
}

.profile-info .email {
    color: #64748b;
    margin-bottom: 12px;
    font-size: 16px;
}

.profile-tags {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.tag {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.tag-primary {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.tag-success {
    background: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
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
   PROFILE FORM
===================== */
.profile-form-section {
    background: #fff;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f1f5f9;
}

.section-title i {
    color: #3498db;
    font-size: 20px;
}

.section-title h3 {
    margin: 0;
    font-size: 18px;
    color: #020617;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.form-group label i {
    margin-right: 5px;
    color: #3498db;
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

.form-control[readonly] {
    background: #f1f5f9;
    cursor: not-allowed;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

/* =====================
   BANK ACCOUNT SECTION
===================== */
.bank-section {
    margin-top: 30px;
    padding-top: 25px;
    border-top: 2px dashed #e2e8f0;
}

.bank-section .section-title i {
    color: #27ae60;
}

.bank-info {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 20px;
    border-radius: 15px;
    border-left: 4px solid #3498db;
}

.bank-icon {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.bank-icon i {
    font-size: 28px;
    color: #3498db;
    background: rgba(52, 152, 219, 0.1);
    padding: 12px;
    border-radius: 50%;
}

.bank-icon h4 {
    font-size: 16px;
    color: #020617;
    margin: 0;
}

.bank-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    background: #fff;
    cursor: pointer;
}

.bank-select:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* =====================
   PHOTO UPLOAD
===================== */
.photo-upload {
    display: flex;
    gap: 30px;
    align-items: flex-start;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 2px dashed #e2e8f0;
}

.current-photo {
    text-align: center;
}

.current-photo p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 10px;
}

.current-photo img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #f1f5f9;
    margin-bottom: 10px;
}

.upload-area {
    flex: 1;
}

.file-input {
    position: relative;
    margin-bottom: 15px;
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
    padding: 12px;
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
    font-size: 24px;
    margin-bottom: 5px;
    display: block;
}

.photo-preview {
    margin-top: 15px;
}

.photo-preview p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 10px;
}

.preview-img {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid #e2e8f0;
    display: none;
}

/* =====================
   BUTTONS
===================== */
.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
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
   RESPONSIVE DESIGN
===================== */
@media (max-width: 1200px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-grid {
        grid-template-columns: 1fr;
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
    
    .stats {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .profile-tags {
        justify-content: center;
    }
    
    .photo-upload {
        flex-direction: column;
        align-items: center;
        text-align: center;
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
    
    .profile-form-section {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
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
    color: #6c757d !important;
    font-size: 12px;
    margin-top: 4px;
    display: block;
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
        
        <a href="profile.php" class="menu-item active">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        
        <a href="produk.php" class="menu-item">
            <i class="bi bi-box"></i>
            <span>Produk</span>
            <?php if($totalProduk > 0): ?>
            <span class="menu-badge"><?= $totalProduk ?></span>
            <?php endif; ?>
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
            <?php if($pendingTransaksi > 0): ?>
            <span class="menu-badge"><?= $pendingTransaksi ?></span>
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
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span>Profil berhasil diperbarui!</span>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= $error ?></span>
    </div>
    <?php endif; ?>

    <!-- HERO -->
    <div class="hero">
        <h2>Profil Toko</h2>
        <p>
            Kelola informasi profil toko Anda. Update data diri, foto profil, dan pantau statistik toko.
        </p>
    </div>

    <!-- PROFILE HEADER -->
    <div class="profile-header">
        <div class="profile-avatar">
            <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile" id="currentPhoto">
            <span class="status-badge <?= $statusUser == 'online' ? 'status-online' : 'status-offline' ?>"></span>
        </div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($namaPenjual) ?></h1>
            <p class="email"><?= htmlspecialchars($emailPenjual) ?></p>
            <p>Bergabung sejak: <strong><?= $tanggalGabung ?></strong></p>
            <div class="profile-tags">
                <span class="tag tag-primary">
                    <i class="bi bi-shop"></i> Toko Buku
                </span>
                <span class="tag tag-success">
                    <i class="bi bi-<?= $aktifUser == 'ya' ? 'check-circle' : 'x-circle' ?>"></i>
                    <?= $aktifUser == 'ya' ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <h3 id="stat-produk"><?= number_format($totalProduk) ?></h3>
            <span>Total Produk</span>
        </div>
        <div class="stat">
            <h3 id="stat-transaksi"><?= number_format($totalTransaksi) ?></h3>
            <span>Transaksi Selesai</span>
        </div>
        <div class="stat">
            <h3 id="stat-pending"><?= number_format($pendingTransaksi) ?></h3>
            <span>Pesanan Proses</span>
        </div>
        <div class="stat">
            <h3 id="stat-omzet">Rp <?= number_format($totalOmzet, 0, ',', '.') ?></h3>
            <span>Total Omzet</span>
        </div>
    </div>

    <!-- PROFILE FORM -->
    <div class="profile-form-section">
        <div class="section-title">
            <i class="bi bi-pencil-square"></i>
            <h3>Edit Profil Toko</h3>
        </div>

        <form method="POST" enctype="multipart/form-data" id="profileForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="nama"><i class="bi bi-shop"></i> Nama Toko</label>
                    <input type="text" id="nama" name="nama" class="form-control" 
                           value="<?= htmlspecialchars($namaPenjual) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="bi bi-envelope"></i> Email</label>
                    <input type="email" id="email" class="form-control" 
                           value="<?= htmlspecialchars($emailPenjual) ?>" readonly>
                    <small class="text-muted">Email tidak dapat diubah</small>
                </div>

                <div class="form-group">
                    <label for="no_hp"><i class="bi bi-telephone"></i> Nomor Telepon/HP</label>
                    <input type="text" id="no_hp" name="no_hp" class="form-control" 
                           value="<?= htmlspecialchars($noHpPenjual) ?>"
                           placeholder="Contoh: 081234567890">
                </div>

                <div class="form-group">
                    <label for="alamat"><i class="bi bi-geo-alt"></i> Alamat Toko</label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="3"
                              placeholder="Masukkan alamat lengkap toko Anda"><?= htmlspecialchars($alamatPenjual) ?></textarea>
                </div>
            </div>

            <!-- BANK ACCOUNT SECTION -->
            <div class="bank-section">
                <div class="section-title">
                    <i class="bi bi-bank"></i>
                    <h3>Informasi Rekening Bank</h3>
                </div>
                
                <div class="bank-info">
                    <div class="bank-icon">
                        <i class="bi bi-cash-stack"></i>
                        <h4>Rekening untuk Pencairan Dana</h4>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bank"><i class="bi bi-bank2"></i> Nama Bank</label>
                            <select id="bank" name="bank" class="bank-select">
                                <option value="">-- Pilih Bank --</option>
                                <option value="BCA" <?= $bank == 'BCA' ? 'selected' : '' ?>>Bank BCA</option>
                                <option value="BNI" <?= $bank == 'BNI' ? 'selected' : '' ?>>Bank BNI</option>
                                <option value="BRI" <?= $bank == 'BRI' ? 'selected' : '' ?>>Bank BRI</option>
                                <option value="Mandiri" <?= $bank == 'Mandiri' ? 'selected' : '' ?>>Bank Mandiri</option>
                                <option value="BSI" <?= $bank == 'BSI' ? 'selected' : '' ?>>Bank Syariah Indonesia (BSI)</option>
                                <option value="CIMB Niaga" <?= $bank == 'CIMB Niaga' ? 'selected' : '' ?>>CIMB Niaga</option>
                                <option value="Danamon" <?= $bank == 'Danamon' ? 'selected' : '' ?>>Bank Danamon</option>
                                <option value="Maybank" <?= $bank == 'Maybank' ? 'selected' : '' ?>>Maybank</option>
                                <option value="Permata" <?= $bank == 'Permata' ? 'selected' : '' ?>>Bank Permata</option>
                                <option value="Panin" <?= $bank == 'Panin' ? 'selected' : '' ?>>Bank Panin</option>
                                <option value="UOB" <?= $bank == 'UOB' ? 'selected' : '' ?>>Bank UOB</option>
                                <option value="OCBC" <?= $bank == 'OCBC' ? 'selected' : '' ?>>OCBC NISP</option>
                                <option value="BTN" <?= $bank == 'BTN' ? 'selected' : '' ?>>Bank BTN</option>
                                <option value="Lainnya" <?= $bank == 'Lainnya' ? 'selected' : '' ?>>Bank Lainnya</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="no_rekening"><i class="bi bi-credit-card"></i> Nomor Rekening</label>
                            <input type="text" id="no_rekening" name="no_rekening" class="form-control" 
                                   value="<?= htmlspecialchars($noRekening) ?>"
                                   placeholder="Masukkan nomor rekening"
                                   pattern="[0-9]{8,20}"
                                   title="Nomor rekening harus berupa angka 8-20 digit">
                        </div>

                        <div class="form-group">
                            <label for="nama_pemilik"><i class="bi bi-person-circle"></i> Nama Pemilik Rekening</label>
                            <input type="text" id="nama_pemilik" name="nama_pemilik" class="form-control" 
                                   value="<?= htmlspecialchars($namaPemilikRekening) ?>"
                                   placeholder="Sesuai dengan nama di rekening">
                        </div>
                    </div>
                    
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Pastikan data rekening benar untuk kelancaran pencairan dana penjualan.
                    </small>
                </div>
            </div>

            <!-- PHOTO UPLOAD -->
            <div class="photo-upload">
                <div class="current-photo">
                    <p>Foto Saat Ini</p>
                    <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Current Photo">
                </div>
                
                <div class="upload-area">
                    <div class="form-group">
                        <label><i class="bi bi-camera"></i> Ubah Foto Profil</label>
                        <div class="file-input">
                            <input type="file" name="foto" id="photoInput" accept="image/*">
                            <label for="photoInput" class="file-label">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>Klik untuk upload foto baru</span>
                                <small>JPG, PNG, GIF, WebP (Maks. 2MB)</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="photo-preview">
                        <p>Pratinjau:</p>
                        <img id="photoPreview" class="preview-img">
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" name="update" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Simpan Perubahan
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
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
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');
const profileForm = document.getElementById('profileForm');

// Menu Toggle
menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
});

// Search Toggle (Mobile)
searchToggle.addEventListener('click', () => {
    searchContainer.classList.toggle('active');
});

// Photo Preview
photoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    
    if (file) {
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file terlalu besar. Maksimal 2MB.');
            this.value = '';
            photoPreview.style.display = 'none';
            return;
        }
        
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
            this.value = '';
            photoPreview.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            photoPreview.src = e.target.result;
            photoPreview.style.display = 'block';
        }
        
        reader.readAsDataURL(file);
    } else {
        photoPreview.style.display = 'none';
    }
});

// Form Validation
profileForm.addEventListener('submit', function(e) {
    const nama = document.getElementById('nama').value.trim();
    const noRekening = document.getElementById('no_rekening').value;
    const bank = document.getElementById('bank').value;
    const namaPemilik = document.getElementById('nama_pemilik').value;
    
    if (!nama) {
        e.preventDefault();
        alert('Nama toko tidak boleh kosong!');
        document.getElementById('nama').focus();
        return false;
    }
    
    // Validasi rekening jika diisi
    if (bank && !noRekening) {
        e.preventDefault();
        alert('Nomor rekening harus diisi jika memilih bank!');
        document.getElementById('no_rekening').focus();
        return false;
    }
    
    if (noRekening && !bank) {
        e.preventDefault();
        alert('Pilih bank terlebih dahulu!');
        document.getElementById('bank').focus();
        return false;
    }
    
    if (noRekening && !namaPemilik) {
        e.preventDefault();
        alert('Nama pemilik rekening harus diisi!');
        document.getElementById('nama_pemilik').focus();
        return false;
    }
    
    // Validasi format nomor rekening (hanya angka)
    if (noRekening && !/^\d+$/.test(noRekening)) {
        e.preventDefault();
        alert('Nomor rekening hanya boleh berisi angka!');
        document.getElementById('no_rekening').focus();
        return false;
    }
    
    if (!confirm('Simpan perubahan pada profil toko?')) {
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

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    handleResize();
    
    const namaField = document.getElementById('nama');
    if (namaField && !namaField.value.trim()) {
        namaField.focus();
    }
});
</script>

</body>
</html>