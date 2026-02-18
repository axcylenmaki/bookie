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
   DATA PENJUAL (buat sidebar & topbar)
===================== */
$qUser = mysqli_query($conn, "SELECT * FROM users WHERE id='$idPenjual' LIMIT 1");
$user  = mysqli_fetch_assoc($qUser);

if (!$user) {
    header("Location: ../auth/login.php");
    exit;
}

$namaPenjual = $user['nama'] ?? 'Penjual';
$emailPenjual = $user['email'] ?? '';
$fotoUser = $user['foto'] ?? '';
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
   LACAK RESI (AJAX)
===================== */
if (isset($_GET['track_resi'])) {
    $no_resi = mysqli_real_escape_string($conn, $_GET['no_resi']);
    
    // Cari transaksi berdasarkan no resi
    $qTrack = mysqli_query($conn, "
        SELECT 
            t.id,
            CONCAT('INV-', DATE_FORMAT(t.created_at, '%Y%m'), '-', LPAD(t.id, 4, '0')) as kode_transaksi,
            t.no_resi,
            t.resi_ekspedisi,
            t.created_at,
            t.status,
            u.id as pembeli_id,
            u.nama as nama_pembeli,
            u.alamat as alamat_pembeli,
            u.no_hp as no_hp_pembeli,
            td.jumlah as qty,
            td.harga,
            p.nama_produk,
            p.gambar as foto_produk
        FROM transaksi t
        LEFT JOIN users u ON t.id_user = u.id
        LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
        LEFT JOIN produk p ON td.id_produk = p.id
        WHERE t.no_resi = '$no_resi'
    ");
    
    $transaksi = [];
    while ($row = mysqli_fetch_assoc($qTrack)) {
        $transaksi[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($transaksi);
    exit;
}

/* =====================
   AMBIL DATA STATUS PENGIRIMAN
===================== */

// 1. DAFTAR RESI AKTIF (Dikirim)
$qResiAktif = mysqli_query($conn, "
    SELECT 
        t.id,
        CONCAT('INV-', DATE_FORMAT(t.created_at, '%Y%m'), '-', LPAD(t.id, 4, '0')) as kode_transaksi,
        t.no_resi,
        t.resi_ekspedisi,
        t.created_at as tgl_kirim,
        t.updated_at,
        t.status,
        u.nama as nama_pembeli,
        u.alamat as alamat_pembeli
    FROM transaksi t
    LEFT JOIN users u ON t.id_user = u.id
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual' 
        AND t.status IN ('dikirim', 'selesai')
        AND t.no_resi IS NOT NULL 
        AND t.no_resi != ''
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 50
");

// 2. RINGKASAN PER EKSPEDISI
$qEkspedisi = mysqli_query($conn, "
    SELECT 
        t.resi_ekspedisi as kurir,
        COUNT(*) as total,
        SUM(CASE WHEN t.status = 'dikirim' THEN 1 ELSE 0 END) as dalam_perjalanan,
        SUM(CASE WHEN t.status = 'selesai' THEN 1 ELSE 0 END) as selesai
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual' 
        AND t.no_resi IS NOT NULL 
        AND t.no_resi != ''
    GROUP BY t.resi_ekspedisi
    ORDER BY total DESC
");

// 3. STATISTIK PENGIRIMAN
$qStatPengiriman = mysqli_query($conn, "
    SELECT 
        SUM(CASE WHEN t.status = 'dikirim' AND t.no_resi IS NOT NULL AND t.no_resi != '' THEN 1 ELSE 0 END) as dalam_perjalanan,
        SUM(CASE WHEN t.status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN t.status IN ('pending', 'dibayar') THEN 1 ELSE 0 END) as belum_dikirim,
        SUM(CASE WHEN t.status = 'dikirim' AND DATEDIFF(NOW(), t.created_at) > 7 THEN 1 ELSE 0 END) as lama
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual'
");
$statPengiriman = mysqli_fetch_assoc($qStatPengiriman);
if (!$statPengiriman) {
    $statPengiriman = [
        'dalam_perjalanan' => 0,
        'selesai' => 0,
        'belum_dikirim' => 0,
        'lama' => 0
    ];
}

// 4. RIWAYAT PENGIRIMAN (10 Terakhir)
$qRiwayat = mysqli_query($conn, "
    SELECT 
        CONCAT('INV-', DATE_FORMAT(t.created_at, '%Y%m'), '-', LPAD(t.id, 4, '0')) as kode_transaksi,
        t.no_resi,
        t.resi_ekspedisi,
        t.status,
        t.created_at,
        t.updated_at,
        u.nama as nama_pembeli
    FROM transaksi t
    LEFT JOIN users u ON t.id_user = u.id
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual' 
        AND t.no_resi IS NOT NULL 
        AND t.no_resi != ''
    GROUP BY t.id
    ORDER BY t.updated_at DESC
    LIMIT 10
");

// ============ FUNGSI GET LINK LACAK ============
function getTrackingLink($no_resi, $kurir = '') {
    // Deteksi kurir dari no_resi jika tidak ada
    if (empty($kurir)) {
        $kurir = strtoupper(substr($no_resi, 0, 3));
    }
    
    // Link tracking resmi
    $links = [
        'JNE' => 'https://www.jne.co.id/id/beranda/tracking/trace?noresi=',
        'J&T' => 'https://jet.co.id/tracking/?resi=',
        'JNT' => 'https://jet.co.id/tracking/?resi=',
        'SICEPAT' => 'https://www.sicepat.com/tracking/',
        'SPX' => 'https://track.spx.co.id/',
        'POS' => 'https://www.posindonesia.co.id/id/tracking/',
        'ANTERAJA' => 'https://anteraja.id/tracking-resi/',
        'NINJA' => 'https://ninja.id/tracking/',
        'GOSEND' => 'https://gosend.co/track/',
        'GRAB' => 'https://grab.com/id/express/track/'
    ];
    
    // Default ke cekresi.com
    $default = 'https://cekresi.com/?noresi=';
    
    foreach ($links as $key => $link) {
        if (strpos($kurir, $key) !== false || strpos(strtoupper($no_resi), $key) !== false) {
            return $link . $no_resi;
        }
    }
    
    return $default . $no_resi;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pengiriman - BOOKIE</title>
    
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
           STATUS PAGE SPECIFIC STYLES
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

        /* Track Card */
        .track-card {
            background: #fff;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .track-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .track-title i {
            font-size: 28px;
            color: #3498db;
        }

        .track-title h3 {
            margin: 0;
            font-size: 18px;
            color: #020617;
        }

        .track-input-group {
            display: flex;
            gap: 15px;
        }

        .track-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .track-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .track-btn {
            padding: 15px 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .track-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue { background: #e8f4fd; color: #3498db; }
        .stat-icon.green { background: #e3f7ec; color: #2ecc71; }
        .stat-icon.orange { background: #fff3e0; color: #f39c12; }
        .stat-icon.red { background: #fee9e7; color: #e74c3c; }

        .stat-info h4 {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .stat-info .number {
            font-size: 24px;
            font-weight: 700;
            color: #020617;
        }

        /* Resi List */
        .resi-list {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .resi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .resi-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            color: #020617;
        }

        .resi-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .resi-item:hover {
            background: #f8fafc;
        }

        .resi-item:last-child {
            border-bottom: none;
        }

        .resi-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .resi-icon {
            width: 40px;
            height: 40px;
            background: #e8f4fd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
        }

        .resi-detail {
            flex: 1;
        }

        .resi-detail h5 {
            margin: 0 0 5px;
            font-size: 15px;
            color: #020617;
        }

        .resi-detail p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }

        .resi-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-dikirim { background: #cce5ff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-lama { background: #fff3cd; color: #856404; }

        .resi-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            border: none;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Ekspedisi Summary */
        .ekspedisi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .ekspedisi-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .ekspedisi-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .ekspedisi-header i {
            font-size: 24px;
            color: #3498db;
        }

        .ekspedisi-header h4 {
            margin: 0;
            font-size: 16px;
            color: #020617;
        }

        .ekspedisi-stats {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #3498db;
            border-radius: 3px;
        }

        /* Tracking Result */
        .tracking-result {
            margin-top: 30px;
            padding: 30px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            display: none;
        }

        .tracking-result.show {
            display: block;
        }

        .kurir-badge {
            display: inline-block;
            padding: 6px 16px;
            background: #e8f4fd;
            color: #3498db;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #e2e8f0;
        }

        .timeline-item.done::before { background: #2ecc71; }
        .timeline-item.active::before { background: #3498db; animation: pulse 2s infinite; }
        .timeline-item.pending::before { background: #95a5a6; }

        .timeline-time {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-weight: 600;
            color: #020617;
            margin-bottom: 4px;
        }

        .timeline-desc {
            font-size: 13px;
            color: #64748b;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
            100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
        }

        /* Riwayat Table */
        .riwayat-table {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-size: 13px;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 15px 12px;
            font-size: 14px;
            color: #020617;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-dikirim { background: #cce5ff; color: #004085; }
        .badge-selesai { background: #d4edda; color: #155724; }

        .btn-outline-sm {
            padding: 6px 12px;
            font-size: 12px;
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .btn-outline-sm:hover {
            background: #3498db;
            color: white;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid,
            .ekspedisi-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .resi-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .resi-actions {
                width: 100%;
            }
            
            .btn-sm {
                flex: 1;
                justify-content: center;
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
            
            .stats-grid,
            .ekspedisi-grid {
                grid-template-columns: 1fr;
            }
            
            .track-input-group {
                flex-direction: column;
            }
            
            .track-btn {
                width: 100%;
                justify-content: center;
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

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
        
        <a href="status.php" class="menu-item active">
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
        <input type="text" id="searchResi" class="search-box" placeholder="Cari nomor resi..." 
               onkeypress="if(event.key === 'Enter') trackResi()">
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
        <h2>🚚 Status Pengiriman</h2>
        <p>
            Lacak status pengiriman pesanan, monitor resi aktif, 
            dan pantau perjalanan paket sampai ke tangan pembeli.
        </p>
    </div>

    <!-- TRACKING CARD -->
    <div class="track-card">
        <div class="track-title">
            <i class="bi bi-upc-scan"></i>
            <h3>Lacak Resi Pengiriman</h3>
        </div>
        
        <div class="track-input-group">
            <input type="text" id="resiInput" class="track-input" 
                   placeholder="Masukkan nomor resi (contoh: JNE1234567890)" 
                   autocomplete="off">
            <button onclick="trackResi()" class="track-btn">
                <i class="bi bi-search"></i> Lacak
            </button>
        </div>
        
        <!-- Tracking Result Container -->
        <div id="trackingResult" class="tracking-result"></div>
    </div>

    <!-- STATISTIK PENGIRIMAN -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="bi bi-truck"></i>
            </div>
            <div class="stat-info">
                <h4>Dalam Perjalanan</h4>
                <div class="number"><?= $statPengiriman['dalam_perjalanan'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-info">
                <h4>Selesai</h4>
                <div class="number"><?= $statPengiriman['selesai'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-info">
                <h4>Belum Dikirim</h4>
                <div class="number"><?= $statPengiriman['belum_dikirim'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h4>Pengiriman >7 Hari</h4>
                <div class="number"><?= $statPengiriman['lama'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- RINGKASAN PER EKSPEDISI -->
    <div class="ekspedisi-grid">
        <?php 
        $kurirIcons = [
            'JNE' => 'bi-truck',
            'J&T' => 'bi-truck',
            'JNT' => 'bi-truck',
            'SICEPAT' => 'bi-lightning',
            'SPX' => 'bi-box',
            'POS' => 'bi-envelope',
            'ANTERAJA' => 'bi-box',
            'NINJA' => 'bi-truck',
            'GOSEND' => 'bi-bicycle',
            'GRAB' => 'bi-bicycle'
        ];
        
        if (mysqli_num_rows($qEkspedisi) > 0) {
            mysqli_data_seek($qEkspedisi, 0);
            while ($eks = mysqli_fetch_assoc($qEkspedisi)): 
                $persentase = $eks['total'] > 0 ? round(($eks['dalam_perjalanan'] / $eks['total']) * 100) : 0;
                $icon = $kurirIcons[strtoupper($eks['kurir'])] ?? 'bi-truck';
        ?>
        <div class="ekspedisi-card">
            <div class="ekspedisi-header">
                <i class="bi <?= $icon ?>"></i>
                <h4><?= htmlspecialchars($eks['kurir'] ?: 'Lainnya') ?></h4>
            </div>
            <div class="ekspedisi-stats">
                <span>Dalam perjalanan</span>
                <span><strong><?= $eks['dalam_perjalanan'] ?></strong> / <?= $eks['total'] ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $persentase ?>%;"></div>
            </div>
        </div>
        <?php 
            endwhile;
        } else {
            // Tampilkan beberapa kurir default
            $defaultKurir = [
                'JNE' => 0,
                'J&T' => 0,
                'SICEPAT' => 0,
                'ANTERAJA' => 0
            ];
            foreach ($defaultKurir as $nama => $total):
        ?>
        <div class="ekspedisi-card">
            <div class="ekspedisi-header">
                <i class="bi bi-truck"></i>
                <h4><?= $nama ?></h4>
            </div>
            <div class="ekspedisi-stats">
                <span>Dalam perjalanan</span>
                <span><strong>0</strong> / 0</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
        </div>
        <?php 
            endforeach;
        }
        ?>
    </div>

    <!-- DAFTAR RESI AKTIF -->
    <div class="resi-list">
        <div class="resi-header">
            <h3>
                <i class="bi bi-truck"></i>
                Pesanan Dalam Perjalanan
            </h3>
            <span style="color: #64748b;">Total: <?= mysqli_num_rows($qResiAktif) ?> resi</span>
        </div>
        
        <?php if (mysqli_num_rows($qResiAktif) > 0): ?>
            <?php mysqli_data_seek($qResiAktif, 0); ?>
            <?php while ($resi = mysqli_fetch_assoc($qResiAktif)): 
                $hari = floor((time() - strtotime($resi['tgl_kirim'])) / (60 * 60 * 24));
                $statusClass = $hari > 7 ? 'status-lama' : ($resi['status'] == 'selesai' ? 'status-selesai' : 'status-dikirim');
                $statusText = $resi['status'] == 'selesai' ? '✅ Selesai' : ($hari > 7 ? "⚠️ {$hari} hari" : "📦 {$hari} hari");
                $trackLink = getTrackingLink($resi['no_resi'], $resi['resi_ekspedisi']);
            ?>
            <div class="resi-item">
                <div class="resi-info">
                    <div class="resi-icon">
                        <i class="bi bi-upc-scan"></i>
                    </div>
                    <div class="resi-detail">
                        <h5><?= htmlspecialchars($resi['no_resi']) ?></h5>
                        <p>
                            <strong><?= htmlspecialchars($resi['kode_transaksi']) ?></strong> • 
                            <?= htmlspecialchars($resi['nama_pembeli']) ?>
                            <?php if ($resi['resi_ekspedisi']): ?>
                                • <span style="color: #3498db;"><?= htmlspecialchars($resi['resi_ekspedisi']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="resi-actions">
                    <span class="resi-status <?= $statusClass ?>">
                        <?= $statusText ?>
                    </span>
                    <a href="<?= $trackLink ?>" target="_blank" class="btn-sm btn-outline">
                        <i class="bi bi-box-arrow-up-right"></i> Lacak
                    </a>
                    <button onclick="trackResi('<?= $resi['no_resi'] ?>')" class="btn-sm btn-primary">
                        <i class="bi bi-search"></i> Detail
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                <p style="margin-top: 16px;">Tidak ada pesanan dalam perjalanan</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIWAYAT PENGIRIMAN -->
    <div class="riwayat-table">
        <h3 style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 18px;">
            <i class="bi bi-clock-history" style="color: #3498db;"></i>
            Riwayat Pengiriman Terakhir
        </h3>
        
        <?php if (mysqli_num_rows($qRiwayat) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Kode Transaksi</th>
                    <th>No. Resi</th>
                    <th>Ekspedisi</th>
                    <th>Pembeli</th>
                    <th>Status</th>
                    <th>Tanggal Kirim</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php mysqli_data_seek($qRiwayat, 0); ?>
                <?php while ($row = mysqli_fetch_assoc($qRiwayat)): 
                    $trackLink = getTrackingLink($row['no_resi'], $row['resi_ekspedisi']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['kode_transaksi']) ?></strong></td>
                    <td style="font-family: monospace;"><?= htmlspecialchars($row['no_resi']) ?></td>
                    <td><?= htmlspecialchars($row['resi_ekspedisi'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['status'] ?>">
                            <?= $row['status'] == 'dikirim' ? '📦 Dikirim' : '✅ Selesai' ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td>
                        <a href="<?= $trackLink ?>" target="_blank" class="btn-outline-sm">
                            <i class="bi bi-box-arrow-up-right"></i> Lacak
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; color: #64748b;">
            <i class="bi bi-clock" style="font-size: 48px;"></i>
            <p style="margin-top: 16px;">Belum ada riwayat pengiriman</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const searchToggle = document.getElementById('searchToggle');
const searchContainer = document.getElementById('searchContainer');
const resiInput = document.getElementById('resiInput');
const trackingResult = document.getElementById('trackingResult');

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

// Track Resi
function trackResi(resi = null) {
    const noResi = resi || resiInput.value.trim();
    
    if (!noResi) {
        alert('Masukkan nomor resi terlebih dahulu!');
        resiInput.focus();
        return;
    }
    
    // Show loading
    trackingResult.style.display = 'block';
    trackingResult.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="bi bi-arrow-repeat" style="font-size: 48px; color: #3498db; animation: spin 1s linear infinite;"></i>
            <p style="margin-top: 16px; color: #64748b;">Melacak resi ${noResi}...</p>
        </div>
    `;
    
    // Scroll to result
    trackingResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    fetch(`status.php?track_resi=1&no_resi=${encodeURIComponent(noResi)}`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                displayTrackingResult(data, noResi);
            } else {
                trackingResult.innerHTML = `
                    <div style="text-align: center; padding: 40px; background: #fff3cd; border-radius: 12px;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #856404;"></i>
                        <p style="margin-top: 16px; color: #856404; font-size: 16px;">
                            Resi <strong>${noResi}</strong> tidak ditemukan!
                        </p>
                        <p style="color: #856404; font-size: 14px; margin-top: 8px;">
                            Pastikan nomor resi sudah benar dan merupakan pesanan Anda.
                        </p>
                        <button onclick="trackResi()" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="bi bi-arrow-repeat"></i> Coba Lagi
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            trackingResult.innerHTML = `
                <div style="text-align: center; padding: 40px; background: #f8d7da; border-radius: 12px;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 48px; color: #721c24;"></i>
                    <p style="margin-top: 16px; color: #721c24; font-size: 16px;">
                        Gagal melacak resi. Silakan coba lagi.
                    </p>
                </div>
            `;
        });
}

// Display Tracking Result
function displayTrackingResult(data, noResi) {
    const order = data[0];
    const kurir = order.resi_ekspedisi || noResi.split('-')[0] || 'JNE';
    
    // Generate tracking link
    let trackUrl = getTrackingLink(noResi, kurir);
    
    // Generate produk list
    let produkHTML = '';
    let subtotal = 0;
    data.forEach(item => {
        const harga = parseInt(item.harga) || 0;
        const qty = parseInt(item.qty) || 1;
        subtotal += harga * qty;
        produkHTML += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e2e8f0;">
                <span style="display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-book" style="color: #3498db;"></i>
                    ${item.nama_produk || 'Produk'}
                </span>
                <span style="font-weight: 600;">${qty} x Rp ${formatRupiah(harga)}</span>
            </div>
        `;
    });

    // Generate timeline dummy
    const timeline = generateTimeline(order.created_at);

    trackingResult.innerHTML = `
        <div style="background: #fff; border-radius: 16px; padding: 30px; border: 2px solid #3498db;">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #e8f4fd; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-upc-scan" style="font-size: 24px; color: #3498db;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 5px; font-size: 18px; color: #020617;">Hasil Pelacakan Resi</h4>
                        <p style="margin: 0; color: #64748b; font-family: monospace; font-size: 16px;">
                            <strong>${noResi}</strong>
                        </p>
                    </div>
                </div>
                <span class="kurir-badge">
                    <i class="bi bi-truck"></i> ${kurir}
                </span>
            </div>
            
            <!-- Info Transaksi -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 12px;">
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Kode Transaksi</div>
                    <div style="font-weight: 600; font-size: 15px;">${order.kode_transaksi || '-'}</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Pembeli</div>
                    <div style="font-weight: 600;">${order.nama_pembeli || '-'}</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Tanggal Kirim</div>
                    <div>${formatDate(order.created_at)}</div>
                </div>
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Total</div>
                    <div style="font-weight: 600; color: #3498db;">Rp ${formatRupiah(subtotal)}</div>
                </div>
            </div>
            
            <!-- Daftar Produk -->
            <div style="margin-bottom: 30px;">
                <h5 style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px; color: #020617;">
                    <i class="bi bi-box"></i> Daftar Produk
                </h5>
                ${produkHTML}
            </div>
            
            <!-- Timeline -->
            <div style="margin-bottom: 30px;">
                <h5 style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #020617;">
                    <i class="bi bi-truck"></i> Riwayat Pelacakan
                </h5>
                <div class="timeline">
                    ${timeline}
                </div>
            </div>
            
            <!-- Alamat -->
            <div style="margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 12px;">
                <h5 style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #020617;">
                    <i class="bi bi-geo-alt"></i> Alamat Pengiriman
                </h5>
                <p style="color: #64748b;">${order.alamat_pembeli || '-'}</p>
                <p style="color: #64748b; font-size: 13px;">${order.no_hp_pembeli ? 'Telp: ' + order.no_hp_pembeli : ''}</p>
            </div>
            
            <!-- Tombol Lacak -->
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                <a href="${trackUrl}" target="_blank" class="btn btn-primary" style="padding: 12px 24px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="bi bi-box-arrow-up-right"></i> Lacak di Website ${kurir}
                </a>
            </div>
        </div>
    `;
}

// Helper function untuk mendapatkan link tracking
function getTrackingLink(noResi, kurir = '') {
    const links = {
        'JNE': 'https://www.jne.co.id/id/beranda/tracking/trace?noresi=',
        'J&T': 'https://jet.co.id/tracking/?resi=',
        'JNT': 'https://jet.co.id/tracking/?resi=',
        'SICEPAT': 'https://www.sicepat.com/tracking/',
        'SPX': 'https://track.spx.co.id/',
        'POS': 'https://www.posindonesia.co.id/id/tracking/',
        'ANTERAJA': 'https://anteraja.id/tracking-resi/',
        'NINJA': 'https://ninja.id/tracking/',
        'GOSEND': 'https://gosend.co/track/',
        'GRAB': 'https://grab.com/id/express/track/'
    };
    
    const defaultLink = 'https://cekresi.com/?noresi=';
    
    for (let key in links) {
        if (kurir.toUpperCase().includes(key) || noResi.toUpperCase().includes(key)) {
            return links[key] + noResi;
        }
    }
    
    return defaultLink + noResi;
}

// Generate Timeline Dummy
function generateTimeline(tglKirim) {
    const date = new Date(tglKirim);
    const jam = date.getHours().toString().padStart(2, '0');
    const menit = date.getMinutes().toString().padStart(2, '0');
    
    const h1 = new Date(date);
    h1.setDate(h1.getDate() + 1);
    
    const h2 = new Date(date);
    h2.setDate(h2.getDate() + 2);
    
    const today = new Date();
    
    return `
        <div class="timeline-item done">
            <div class="timeline-time">${formatDate(date)} ${jam}:${menit}</div>
            <div class="timeline-title">✅ Paket diterima kurir</div>
            <div class="timeline-desc">Paket telah diserahkan ke kurir</div>
        </div>
        <div class="timeline-item ${today >= h1 ? 'done' : 'pending'}">
            <div class="timeline-time">${formatDate(h1)} 14:30</div>
            <div class="timeline-title">🚚 Dalam perjalanan</div>
            <div class="timeline-desc">Paket dalam perjalanan ke kota tujuan</div>
        </div>
        <div class="timeline-item ${today >= h2 ? 'done' : 'active'}">
            <div class="timeline-time">${formatDate(h2)} 09:15</div>
            <div class="timeline-title">📍 Transit di hub sorting</div>
            <div class="timeline-desc">Paket sedang diproses di pusat sorting</div>
        </div>
        <div class="timeline-item pending">
            <div class="timeline-time">-</div>
            <div class="timeline-title">🏠 Sedang diantar kurir</div>
            <div class="timeline-desc">Paket akan segera diantar ke alamat tujuan</div>
        </div>
    `;
}

// Format Date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric' 
    });
}

// Format Rupiah
function formatRupiah(angka) {
    if (!angka) return '0';
    return new Intl.NumberFormat('id-ID').format(angka);
}

// Enter key on resi input
if (resiInput) {
    resiInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            trackResi();
        }
    });
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
</script>

</body>
</html>