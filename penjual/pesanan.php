<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

$namaPenjual = $_SESSION['user']['nama'];
$fotoUser = $_SESSION['user']['foto'] ?? '';
$penjual_id = $_SESSION['user']['id'];

// PATH FOTO PROFIL - LANGSUNG DARI UPLOADS/PROFILE/
$fotoPath = "../uploads/profile/" . $fotoUser;

// Koneksi database
require_once '../config/database.php';

// Function format rupiah
function formatRupiah($angka) {
    if ($angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Function timeAgo
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

// PERBAIKAN: Handle Approve + Input Resi SEKALIGUS
if (isset($_POST['approve_with_resi'])) {
    $transaksi_id = mysqli_real_escape_string($conn, $_POST['transaksi_id']);
    $no_resi = mysqli_real_escape_string($conn, $_POST['no_resi']);
    $resi_ekspedisi = mysqli_real_escape_string($conn, $_POST['resi_ekspedisi']);
    
    // Update status langsung ke 'dikirim' + input resi
    $update = "UPDATE transaksi SET 
               status = 'dikirim', 
               no_resi = '$no_resi', 
               resi_ekspedisi = '$resi_ekspedisi', 
               updated_at = NOW() 
               WHERE id = '$transaksi_id' AND penjual_id = '$penjual_id'";
    
    if (mysqli_query($conn, $update)) {
        if (mysqli_affected_rows($conn) > 0) {
            // Dapatkan id_user (pembeli) dari transaksi
            $query_pembeli = "SELECT id_user FROM transaksi WHERE id = '$transaksi_id'";
            $result_pembeli = mysqli_query($conn, $query_pembeli);
            $data_pembeli = mysqli_fetch_assoc($result_pembeli);
            $pembeli_id = $data_pembeli['id_user'];
            
            // Buat notifikasi untuk pembeli
            $notif_pesan = "Pesanan Anda telah dikirim. Nomor resi: $no_resi ($resi_ekspedisi)";
            $insert_notif = "INSERT INTO notifikasi (id_user, pesan, status, created_at) VALUES ('$pembeli_id', '$notif_pesan', 'unread', NOW())";
            mysqli_query($conn, $insert_notif);
            
            // Kirim chat otomatis ke pembeli
            $chat_pesan = "✅ *Pembayaran Anda telah di-APPROVE dan PESANAN TELAH DIKIRIM*\n\n" .
                          "*Nomor Resi:* $no_resi\n" .
                          "*Ekspedisi:* $resi_ekspedisi\n\n" .
                          "Terima kasih telah berbelanja. Anda dapat melacak paket melalui website ekspedisi terkait.";
            
            // Cek apakah chat room sudah ada
            $check_room = "SELECT id FROM chat_rooms WHERE id_pembeli = '$pembeli_id' AND id_penjual = '$penjual_id'";
            $result_room = mysqli_query($conn, $check_room);
            
            if (mysqli_num_rows($result_room) > 0) {
                $room = mysqli_fetch_assoc($result_room);
                $room_id = $room['id'];
            } else {
                // Buat room baru
                $insert_room = "INSERT INTO chat_rooms (id_pembeli, id_penjual, created_at) VALUES ('$pembeli_id', '$penjual_id', NOW())";
                mysqli_query($conn, $insert_room);
                $room_id = mysqli_insert_id($conn);
            }
            
            // Escape string untuk chat
            $chat_pesan_escaped = mysqli_real_escape_string($conn, $chat_pesan);
            
            // Kirim chat
            $insert_chat = "INSERT INTO chat (id_room, id_pengirim, penerima_id, pesan, dibaca, created_at) 
                            VALUES ('$room_id', '$penjual_id', '$pembeli_id', '$chat_pesan_escaped', 0, NOW())";
            mysqli_query($conn, $insert_chat);
            
            header("Location: pesanan.php?success=approved_and_sent");
            exit;
        } else {
            header("Location: pesanan.php?error=not_authorized");
            exit;
        }
    } else {
        header("Location: pesanan.php?error=approve_failed");
        exit;
    }
}

// PERBAIKAN: Handle Reject Payment - Kembalikan ke 'pending'
if (isset($_POST['reject_payment'])) {
    $transaksi_id = mysqli_real_escape_string($conn, $_POST['transaksi_id']);
    $alasan_penolakan = mysqli_real_escape_string($conn, $_POST['alasan_penolakan']);
    
    // Update status transaksi menjadi 'pending' (kembali ke awal)
    $update = "UPDATE transaksi SET status = 'pending', updated_at = NOW() WHERE id = '$transaksi_id' AND penjual_id = '$penjual_id'";
    
    if (mysqli_query($conn, $update)) {
        if (mysqli_affected_rows($conn) > 0) {
            // Dapatkan id_user (pembeli) dari transaksi
            $query_pembeli = "SELECT id_user FROM transaksi WHERE id = '$transaksi_id'";
            $result_pembeli = mysqli_query($conn, $query_pembeli);
            $data_pembeli = mysqli_fetch_assoc($result_pembeli);
            $pembeli_id = $data_pembeli['id_user'];
            
            // Buat notifikasi untuk pembeli
            $notif_pesan = "Pesanan Anda ditolak. Silakan cek chat untuk detail alasan penolakan.";
            $insert_notif = "INSERT INTO notifikasi (id_user, pesan, status, created_at) VALUES ('$pembeli_id', '$notif_pesan', 'unread', NOW())";
            mysqli_query($conn, $insert_notif);
            
            // Kirim chat otomatis ke pembeli dengan alasan penolakan
            $chat_pesan = "❌ *Pembayaran Anda DITOLAK*\n\n" .
                          "*Alasan Penolakan:*\n$alasan_penolakan\n\n" .
                          "Silakan upload ulang bukti transfer yang benar.";
            
            // Cek apakah chat room sudah ada
            $check_room = "SELECT id FROM chat_rooms WHERE id_pembeli = '$pembeli_id' AND id_penjual = '$penjual_id'";
            $result_room = mysqli_query($conn, $check_room);
            
            if (mysqli_num_rows($result_room) > 0) {
                $room = mysqli_fetch_assoc($result_room);
                $room_id = $room['id'];
            } else {
                // Buat room baru
                $insert_room = "INSERT INTO chat_rooms (id_pembeli, id_penjual, created_at) VALUES ('$pembeli_id', '$penjual_id', NOW())";
                mysqli_query($conn, $insert_room);
                $room_id = mysqli_insert_id($conn);
            }
            
            // Escape string untuk chat
            $chat_pesan_escaped = mysqli_real_escape_string($conn, $chat_pesan);
            
            // Kirim chat
            $insert_chat = "INSERT INTO chat (id_room, id_pengirim, penerima_id, pesan, dibaca, created_at) 
                            VALUES ('$room_id', '$penjual_id', '$pembeli_id', '$chat_pesan_escaped', 0, NOW())";
            mysqli_query($conn, $insert_chat);
            
            header("Location: pesanan.php?success=reject");
            exit;
        } else {
            header("Location: pesanan.php?error=not_authorized");
            exit;
        }
    } else {
        header("Location: pesanan.php?error=reject_failed");
        exit;
    }
}

// PERBAIKAN: Ambil semua pesanan untuk penjual ini
$query_pesanan = "SELECT DISTINCT t.*, u.nama as nama_pembeli, u.foto as foto_pembeli,
                  (SELECT SUM(td.harga * td.jumlah) FROM transaksi_detail td WHERE td.id_transaksi = t.id) as total_keseluruhan,
                  (SELECT COUNT(*) FROM transaksi_detail td WHERE td.id_transaksi = t.id) as jumlah_item
                  FROM transaksi t
                  JOIN users u ON t.id_user = u.id
                  WHERE t.penjual_id = '$penjual_id'
                  ORDER BY 
                    CASE 
                        WHEN t.status = 'dibayar' THEN 1
                        WHEN t.status = 'pending' THEN 2
                        WHEN t.status = 'dikirim' THEN 3
                        WHEN t.status = 'selesai' THEN 4
                        ELSE 5
                    END,
                    t.created_at DESC";

$result_pesanan = mysqli_query($conn, $query_pesanan);

// PERBAIKAN: Hitung statistik sesuai struktur DB
$statistik = [
    'pending' => 0,
    'dibayar' => 0,
    'dikirim' => 0,
    'selesai' => 0,
    'total' => 0
];

// Query statistik untuk masing-masing status
$status_list = ['pending', 'dibayar', 'dikirim', 'selesai'];

foreach ($status_list as $status) {
    $q = "SELECT COUNT(*) as total FROM transaksi WHERE penjual_id = '$penjual_id' AND status = '$status'";
    $r = mysqli_query($conn, $q);
    $d = mysqli_fetch_assoc($r);
    $statistik[$status] = $d['total'];
}

// Hitung total semua
$q_total = "SELECT COUNT(*) as total FROM transaksi WHERE penjual_id = '$penjual_id'";
$r_total = mysqli_query($conn, $q_total);
$d_total = mysqli_fetch_assoc($r_total);
$statistik['total'] = $d_total['total'];

// Ambil filter status dari URL
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Label status untuk tampilan
$status_labels = [
    'pending' => 'Menunggu Pembayaran',
    'dibayar' => 'Menunggu Validasi',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai'
];

$status_classes = [
    'pending' => 'status-pending',
    'dibayar' => 'status-dibayar',
    'dikirim' => 'status-dikirim',
    'selesai' => 'status-selesai'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesanan - BOOKIE</title>
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

/* SIDEBAR */
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

/* TOP BAR */
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

/* MAIN CONTENT */
.main-content {
    flex: 1;
    margin-left: 260px;
    margin-top: 70px;
    padding: 24px;
    min-height: calc(100vh - 70px);
    background: #f8f9fa;
    transition: all 0.3s ease;
}

/* PAGE HEADER */
.page-header {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
}

.page-header h1 {
    margin: 0;
    font-size: 26px;
    font-weight: 700;
    color: #020617;
}

.page-header .breadcrumb {
    margin-top: 8px;
    color: #64748b;
    font-size: 14px;
}

.page-header .breadcrumb a {
    color: #3498db;
    text-decoration: none;
}

/* STATS CARDS */
.stats-mini {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 24px;
}

.stat-mini {
    background: #fff;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    cursor: pointer;
}

.stat-mini:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.stat-mini.active {
    border-left: 4px solid #3498db;
    background: #f0f9ff;
}

.stat-mini h4 {
    margin: 0;
    font-size: 20px;
    color: #020617;
    font-weight: 700;
}

.stat-mini span {
    color: #64748b;
    font-size: 13px;
    display: block;
    margin-top: 4px;
}

.stat-mini small {
    color: #3498db;
    font-size: 11px;
    display: block;
    margin-top: 4px;
}

/* FILTER SECTION */
.filter-section {
    background: #fff;
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    border: 1px solid #e2e8f0;
}

.filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    background: #f1f5f9;
    color: #64748b;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.filter-tab:hover {
    background: #e2e8f0;
    color: #334155;
}

.filter-tab.active {
    background: #3498db;
    color: white;
}

.search-filter {
    flex: 1;
    min-width: 250px;
    display: flex;
    gap: 10px;
}

.search-filter input {
    flex: 1;
    padding: 8px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    font-size: 13px;
}

.search-filter button {
    padding: 8px 20px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.3s ease;
}

.search-filter button:hover {
    background: #2980b9;
}

/* PESANAN CARDS */
.pesanan-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pesanan-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.pesanan-card:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-header {
    padding: 16px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
}

.order-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
}

.order-code {
    font-weight: 700;
    font-size: 16px;
    color: #020617;
}

.order-code span {
    color: #64748b;
    font-weight: 400;
    font-size: 13px;
}

.customer-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.customer-info img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e2e8f0;
}

.customer-info .name {
    font-weight: 600;
    font-size: 14px;
    color: #1e293b;
}

.order-status {
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-dibayar { background: #dbeafe; color: #1e40af; }
.status-dikirim { background: #dcfce7; color: #166534; }
.status-selesai { background: #d1fae5; color: #065f46; }

.card-body {
    padding: 20px;
}

.produk-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.produk-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 12px;
}

.produk-image {
    width: 50px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    background: #e2e8f0;
}

.produk-detail {
    flex: 1;
}

.produk-nama {
    font-weight: 600;
    font-size: 15px;
    color: #020617;
    margin-bottom: 4px;
}

.produk-meta {
    font-size: 12px;
    color: #64748b;
    display: flex;
    gap: 15px;
}

.produk-harga {
    font-weight: 700;
    color: #3498db;
    font-size: 14px;
}

.order-summary {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
    margin-top: 8px;
}

.total-info {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.total-info .label {
    color: #64748b;
    font-size: 13px;
}

.total-info .value {
    font-weight: 700;
    font-size: 18px;
    color: #020617;
}

.total-info .item-count {
    background: #f1f5f9;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 12px;
    color: #64748b;
}

.card-footer {
    padding: 16px 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
}

.payment-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.bukti-btn {
    background: none;
    border: 1px solid #3498db;
    color: #3498db;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.bukti-btn:hover {
    background: #3498db;
    color: white;
}

.resi-info {
    background: #f1f5f9;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.resi-info .ekspedisi {
    color: #64748b;
}

.resi-info .no-resi {
    font-weight: 600;
    color: #020617;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-approve {
    background: #10b981;
    color: white;
}

.btn-approve:hover {
    background: #059669;
    transform: translateY(-2px);
}

.btn-reject {
    background: #ef4444;
    color: white;
}

.btn-reject:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-resi {
    background: #3498db;
    color: white;
}

.btn-resi:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-chat {
    background: #f1f5f9;
    color: #475569;
}

.btn-chat:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-lacak {
    background: #f1f5f9;
    color: #475569;
}

.btn-lacak:hover {
    background: #e2e8f0;
    color: #1e293b;
}

/* MODAL */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1100;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #020617;
}

.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
    transition: all 0.3s ease;
}

.modal-close:hover {
    color: #ef4444;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
}

.empty-state i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 18px;
    color: #334155;
    margin-bottom: 8px;
}

.empty-state p {
    color: #64748b;
    font-size: 14px;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

/* Utility */
.menu-toggle {
    display: none;
}

@media (max-width: 768px) {
    .menu-toggle {
        display: flex;
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
    
    .stats-mini {
        grid-template-columns: repeat(2, 1fr);
    }
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
        <!-- FOTO PROFILE - DARI UPLOADS/PROFILE/ -->
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
        </a>
        <a href="pesanan.php" class="menu-item active">
            <i class="bi bi-receipt"></i>
            <span>Pesanan</span>
        </a>
        <a href="laporan.php" class="menu-item">
            <i class="bi bi-bar-chart"></i>
            <span>Laporan</span>
        </a>
        <a href="help.php" class="menu-item">
            <i class="bi bi-question-circle"></i>
            <span>Help & FAQ</span>
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
        <input type="text" class="search-box" placeholder="Cari pesanan..." id="searchInput">
    </div>
    
    <div class="top-bar-right">
        <a href="profile.php" class="user-profile-top">
            <!-- FOTO PROFILE - DARI UPLOADS/PROFILE/ -->
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

    <!-- Page Header -->
    <div class="page-header">
        <h1>Manajemen Pesanan</h1>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > Pesanan
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if(isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <?php 
        if($_GET['success'] == 'approved_and_sent') echo '✅ Pesanan berhasil di-approve dan dikirim! Nomor resi telah diinput.';
        if($_GET['success'] == 'reject') echo '❌ Pembayaran ditolak. Notifikasi telah dikirim ke pembeli.';
        ?>
    </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php
        if($_GET['error'] == 'approve_failed') echo 'Gagal memproses pesanan. Silakan coba lagi.';
        if($_GET['error'] == 'reject_failed') echo 'Gagal menolak pembayaran. Silakan coba lagi.';
        if($_GET['error'] == 'not_authorized') echo 'Anda tidak memiliki akses ke transaksi ini.';
        ?>
    </div>
    <?php endif; ?>

    <!-- Stats Mini -->
    <div class="stats-mini">
        <div class="stat-mini <?= $filter_status == 'all' ? 'active' : '' ?>" onclick="window.location.href='?status=all'">
            <h4><?= $statistik['total'] ?></h4>
            <span>Semua Pesanan</span>
        </div>
        <div class="stat-mini <?= $filter_status == 'dibayar' ? 'active' : '' ?>" onclick="window.location.href='?status=dibayar'">
            <h4><?= $statistik['dibayar'] ?></h4>
            <span>Perlu Validasi</span>
            <small>Approve + Input Resi</small>
        </div>
        <div class="stat-mini <?= $filter_status == 'dikirim' ? 'active' : '' ?>" onclick="window.location.href='?status=dikirim'">
            <h4><?= $statistik['dikirim'] ?></h4>
            <span>Dikirim</span>
            <small>Lacak</small>
        </div>
        <div class="stat-mini <?= $filter_status == 'selesai' ? 'active' : '' ?>" onclick="window.location.href='?status=selesai'">
            <h4><?= $statistik['selesai'] ?></h4>
            <span>Selesai</span>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?= $filter_status == 'all' ? 'active' : '' ?>">Semua</a>
            <a href="?status=dibayar" class="filter-tab <?= $filter_status == 'dibayar' ? 'active' : '' ?>">Perlu Validasi</a>
            <a href="?status=dikirim" class="filter-tab <?= $filter_status == 'dikirim' ? 'active' : '' ?>">Dikirim</a>
            <a href="?status=selesai" class="filter-tab <?= $filter_status == 'selesai' ? 'active' : '' ?>">Selesai</a>
        </div>
        <div class="search-filter">
            <input type="text" id="searchInput" placeholder="Cari kode atau nama pembeli...">
            <button onclick="searchOrders()"><i class="bi bi-search"></i> Cari</button>
        </div>
    </div>

    <!-- Pesanan List -->
    <div class="pesanan-list" id="pesananList">
        <?php 
        if (mysqli_num_rows($result_pesanan) > 0) {
            $found = false;
            while($pesanan = mysqli_fetch_assoc($result_pesanan)) {
                
                // Filter berdasarkan status jika diperlukan
                if ($filter_status != 'all' && $pesanan['status'] != $filter_status) {
                    continue;
                }
                $found = true;
                
                // Ambil detail produk untuk pesanan ini
                $query_detail = "SELECT td.*, p.nama_produk, p.gambar
                                FROM transaksi_detail td
                                JOIN produk p ON td.id_produk = p.id
                                WHERE td.id_transaksi = '{$pesanan['id']}'";
                $result_detail = mysqli_query($conn, $query_detail);
                
                // Tentukan class status
                $status_class = $status_classes[$pesanan['status']] ?? 'status-pending';
        ?>
        <div class="pesanan-card" data-status="<?= $pesanan['status'] ?>">
            <!-- Card Header -->
            <div class="card-header">
                <div class="order-info">
                    <div class="order-code">
                        #<?= $pesanan['kode_transaksi'] ?>
                        <span><?= date('d M Y H:i', strtotime($pesanan['created_at'])) ?></span>
                    </div>
                    
                    <div class="customer-info">
                        <!-- FOTO PEMBELI - DARI UPLOADS/PROFILE/ -->
                        <img src="<?= '../uploads/profile/' . $pesanan['foto_pembeli'] ?>" alt="Foto">
                        <span class="name"><?= htmlspecialchars($pesanan['nama_pembeli']) ?></span>
                    </div>
                </div>
                
                <div class="order-status <?= $status_class ?>">
                    <i class="bi <?php 
                        if($pesanan['status'] == 'pending') echo 'bi-clock';
                        elseif($pesanan['status'] == 'dibayar') echo 'bi-cash';
                        elseif($pesanan['status'] == 'dikirim') echo 'bi-truck';
                        elseif($pesanan['status'] == 'selesai') echo 'bi-check2-circle';
                        else echo 'bi-check-circle';
                    ?>"></i>
                    <?= $status_labels[$pesanan['status']] ?? ucfirst($pesanan['status']) ?>
                </div>
            </div>
            
            <!-- Card Body -->
            <div class="card-body">
                <!-- Produk List -->
                <div class="produk-list">
                    <?php while($detail = mysqli_fetch_assoc($result_detail)): ?>
                    <div class="produk-item">
                        <!-- FOTO PRODUK - DARI UPLOADS/ (langsung) -->
                        <img src="<?= '../uploads/' . $detail['gambar'] ?>" alt="Produk" class="produk-image">
                        <div class="produk-detail">
                            <div class="produk-nama"><?= htmlspecialchars($detail['nama_produk']) ?></div>
                            <div class="produk-meta">
                                <span><?= $detail['jumlah'] ?> x <?= formatRupiah($detail['harga']) ?></span>
                                <span class="produk-harga">= <?= formatRupiah($detail['jumlah'] * $detail['harga']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="total-info">
                        <span class="label">Total Pesanan:</span>
                        <span class="value"><?= formatRupiah($pesanan['total_keseluruhan']) ?></span>
                        <span class="item-count"><?= $pesanan['jumlah_item'] ?> item</span>
                    </div>
                </div>
            </div>
            
            <!-- Card Footer -->
            <div class="card-footer">
                <div class="payment-info">
                    <?php if($pesanan['bukti_transfer']): ?>
                    <button class="bukti-btn" onclick="viewBukti('<?= $pesanan['bukti_transfer'] ?>')">
                        <i class="bi bi-eye"></i> Lihat Bukti Transfer
                    </button>
                    <?php endif; ?>
                    
                    <?php if($pesanan['no_resi']): ?>
                    <div class="resi-info">
                        <span class="ekspedisi"><?= $pesanan['resi_ekspedisi'] ?>:</span>
                        <span class="no-resi"><?= $pesanan['no_resi'] ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <!-- Tombol Chat -->
                    <button class="btn-action btn-chat" onclick="openChat(<?= $pesanan['id_user'] ?>)">
                        <i class="bi bi-chat"></i> Chat
                    </button>
                    
                    <?php if($pesanan['status'] == 'dibayar'): ?>
                    <!-- Approve & Reject - Approve langsung dengan input resi -->
                    <button class="btn-action btn-approve" onclick="openApproveWithResiModal(<?= $pesanan['id'] ?>)">
                        <i class="bi bi-check-lg"></i> Approve & Kirim
                    </button>
                    <button class="btn-action btn-reject" onclick="openRejectModal(<?= $pesanan['id'] ?>)">
                        <i class="bi bi-x-lg"></i> Tolak
                    </button>
                    
                    <?php elseif($pesanan['status'] == 'dikirim'): ?>
                    <!-- Tombol Lacak Paket -->
                    <button class="btn-action btn-lacak" onclick="lacakPaket('<?= $pesanan['no_resi'] ?>', '<?= $pesanan['resi_ekspedisi'] ?>')">
                        <i class="bi bi-box-arrow-up-right"></i> Lacak
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php 
            }
            if (!$found) {
        ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h3>Tidak Ada Pesanan</h3>
            <p>Tidak ada pesanan dengan status "<?= $filter_status ?>"</p>
        </div>
        <?php
            }
        } else {
        ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h3>Belum Ada Pesanan</h3>
            <p>Pesanan dari pembeli akan muncul di sini</p>
        </div>
        <?php } ?>
    </div>
</div>

<!-- MODAL APPROVE WITH RESI -->
<div class="modal" id="approveWithResiModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve & Kirim Pesanan</h3>
            <button class="modal-close" onclick="closeModal('approveWithResiModal')">&times;</button>
        </div>
        <form method="POST" id="approveWithResiForm">
            <div class="modal-body">
                <input type="hidden" name="transaksi_id" id="approveWithResiTransaksiId">
                
                <div class="form-group">
                    <label>Ekspedisi <span style="color: #ef4444;">*</span></label>
                    <select name="resi_ekspedisi" class="form-control" required>
                        <option value="">Pilih Ekspedisi</option>
                        <option value="JNE">JNE</option>
                        <option value="J&T">J&T Express</option>
                        <option value="SiCepat">SiCepat</option>
                        <option value="Pos Indonesia">Pos Indonesia</option>
                        <option value="AnterAja">AnterAja</option>
                        <option value="Ninja Xpress">Ninja Xpress</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nomor Resi <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="no_resi" class="form-control" required placeholder="Contoh: JNE123456789">
                </div>
                
                <p class="text-muted" style="font-size: 13px; color: #64748b;">
                    <i class="bi bi-info-circle"></i> 
                    ✅ Pesanan akan langsung berstatus <strong>"Dikirim"</strong><br>
                    💬 Pembeli akan mendapat notifikasi dan resi pengiriman
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-chat" onclick="closeModal('approveWithResiModal')">Batal</button>
                <button type="submit" name="approve_with_resi" class="btn-action btn-approve">Approve & Kirim</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REJECT -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Tolak Pembayaran</h3>
            <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="transaksi_id" id="rejectTransaksiId">
                <div class="form-group">
                    <label>Alasan Penolakan <span style="color: #ef4444;">*</span></label>
                    <textarea name="alasan_penolakan" class="form-control" required placeholder="Contoh: Bukti transfer tidak jelas, jumlah tidak sesuai, dll."></textarea>
                </div>
                <p class="text-muted" style="font-size: 13px; color: #64748b;">
                    <i class="bi bi-info-circle"></i> 
                    ❌ Status akan kembali menjadi <strong>"Pending"</strong><br>
                    💬 Alasan akan dikirim ke pembeli via chat
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-chat" onclick="closeModal('rejectModal')">Batal</button>
                <button type="submit" name="reject_payment" class="btn-action btn-reject">Tolak</button>
            </div>
        </form>
    </div>
</div>

<script>
// =====================
// SIDEBAR & RESPONSIVE
// =====================
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');

if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

// =====================
// MODAL FUNCTIONS
// =====================
function openApproveWithResiModal(transaksiId) {
    document.getElementById('approveWithResiTransaksiId').value = transaksiId;
    document.getElementById('approveWithResiModal').classList.add('active');
}

function openRejectModal(transaksiId) {
    document.getElementById('rejectTransaksiId').value = transaksiId;
    document.getElementById('rejectModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// =====================
// CHAT FUNCTION
// =====================
function openChat(pembeliId) {
    window.location.href = 'chat/chat.php?user=' + pembeliId;
}

// =====================
// LACAK PAKET
// =====================
function lacakPaket(noResi, ekspedisi) {
    let url = '#';
    const ekspedisiLower = ekspedisi.toLowerCase();
    
    if (ekspedisiLower.includes('jne')) {
        url = 'https://www.jne.co.id/tracking/trace/' + noResi;
    } else if (ekspedisiLower.includes('j&t') || ekspedisiLower.includes('jnt')) {
        url = 'https://www.jet.co.id/tracking/' + noResi;
    } else if (ekspedisiLower.includes('sicepat')) {
        url = 'https://www.sicepat.com/tracking/' + noResi;
    } else if (ekspedisiLower.includes('pos')) {
        url = 'https://www.posindonesia.co.id/tracking/' + noResi;
    } else {
        alert('Silakan buka website ' + ekspedisi + ' dan masukkan nomor resi: ' + noResi);
        return;
    }
    
    window.open(url, '_blank');
}

// =====================
// VIEW BUKTI TRANSFER
// =====================
function viewBukti(filename) {
    window.open('../uploads/bukti_transfer/' + filename, '_blank');
}

// =====================
// SEARCH ORDERS
// =====================
function searchOrders() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.pesanan-card');
    
    cards.forEach(card => {
        const cardText = card.textContent.toLowerCase();
        if (cardText.includes(searchText)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// =====================
// LOGOUT FUNCTION
// =====================
function logout() {
    if (confirm('Yakin ingin logout?')) {
        window.location.href = '../auth/logout.php';
    }
}

// =====================
// ENTER KEY SEARCH
// =====================
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchOrders();
    }
});
</script>

</body>
</html>