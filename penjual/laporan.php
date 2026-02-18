<?php
session_start();

// Cek session dan role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

$idPenjual = $_SESSION['user']['id'];
$namaPenjual = $_SESSION['user']['nama'];

// Hitung chat yang belum dibaca untuk badge
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

// Ambil parameter filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$status = isset($_GET['status']) ? $_GET['status'] : 'selesai';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query untuk statistik - PERBAIKAN: menggunakan t.total (bukan total_harga) dan t.id_user (bukan pembeli_id)
$qStat = mysqli_query($conn, "
    SELECT 
        COUNT(DISTINCT t.id) as total_transaksi,
        COALESCE(SUM(t.total), 0) as total_penjualan,
        COUNT(DISTINCT t.id_user) as total_pembeli,
        COALESCE(AVG(t.total), 0) as rata_rata
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual'
        AND t.status = 'selesai'
        AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
");

$stat = mysqli_fetch_assoc($qStat);
if (!$stat) {
    $stat = [
        'total_transaksi' => 0,
        'total_penjualan' => 0,
        'total_pembeli' => 0,
        'rata_rata' => 0
    ];
}

// Query untuk grafik (penjualan per hari) - PERBAIKAN: menggunakan t.total
$qGrafik = mysqli_query($conn, "
    SELECT 
        DATE(t.created_at) as tanggal,
        COUNT(DISTINCT t.id) as jumlah_transaksi,
        SUM(t.total) as total
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual'
        AND t.status = 'selesai'
        AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(t.created_at)
    ORDER BY tanggal ASC
");

$labelsGrafik = [];
$valuesGrafik = [];

while ($row = mysqli_fetch_assoc($qGrafik)) {
    $labelsGrafik[] = date('d/m', strtotime($row['tanggal']));
    $valuesGrafik[] = $row['total'];
}

// Query untuk produk terlaris - PERBAIKAN: menggunakan td.jumlah (bukan qty) dan td.harga
$qProduk = mysqli_query($conn, "
    SELECT 
        p.nama_produk,
        p.harga,
        COUNT(td.id) as jumlah_transaksi,
        SUM(td.jumlah) as jumlah_terjual,
        SUM(td.harga * td.jumlah) as total_penjualan
    FROM transaksi_detail td
    JOIN produk p ON td.id_produk = p.id
    JOIN transaksi t ON td.id_transaksi = t.id
    WHERE p.id_penjual = '$idPenjual'
        AND t.status = 'selesai'
        AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY p.id
    ORDER BY jumlah_terjual DESC
    LIMIT 5
");

// Query utama untuk tabel transaksi - PERBAIKAN: menggunakan t.total dan t.id_user
$query = "
    SELECT 
        t.id,
        CONCAT('INV-', DATE_FORMAT(t.created_at, '%Y%m'), '-', LPAD(t.id, 4, '0')) as kode_transaksi,
        u.nama as pembeli,
        u.email as email_pembeli,
        t.total as total_harga,
        t.status,
        'transfer' as payment_method,
        t.created_at,
        COUNT(td.id) as total_item,
        GROUP_CONCAT(p.nama_produk SEPARATOR '; ') as produk,
        GROUP_CONCAT(td.jumlah SEPARATOR '; ') as qty_produk,
        SUM(td.jumlah) as total_qty
    FROM transaksi t
    LEFT JOIN users u ON t.id_user = u.id
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual'
        AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
";

if ($status != 'semua') {
    $query .= " AND t.status = '$status'";
}

if (!empty($search)) {
    $query .= " AND (u.nama LIKE '%$search%' OR p.nama_produk LIKE '%$search%')";
}

$query .= " GROUP BY t.id ORDER BY t.created_at DESC";

$qTransaksi = mysqli_query($conn, $query);

// Hitung total untuk footer - PERBAIKAN: menggunakan t.total
$qTotalFooter = mysqli_query($conn, "
    SELECT SUM(t.total) as total_all
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    WHERE p.id_penjual = '$idPenjual'
        AND t.status = 'selesai'
        AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
");
$totalFooter = mysqli_fetch_assoc($qTotalFooter);

// Format tanggal untuk tampilan
$tglStart = date('d/m/Y', strtotime($start_date));
$tglEnd = date('d/m/Y', strtotime($end_date));

// Proses export PDF (tetap sama, tapi perlu disesuaikan dengan struktur)
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    // Cek apakah FPDF tersedia
    if (!file_exists(__DIR__ . '/../vendor/fpdf/fpdf.php')) {
        die('FPDF library tidak ditemukan. Silakan install terlebih dahulu.');
    }
    
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
    
    class PDF extends FPDF
    {
        function Header()
        {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'LAPORAN PENJUALAN BOOKIE', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 11);
            $this->Cell(0, 6, 'Periode: ' . date('d/m/Y', strtotime($_GET['start_date'] ?? date('Y-m-01'))) . ' - ' . date('d/m/Y', strtotime($_GET['end_date'] ?? date('Y-m-t'))), 0, 1, 'C');
            $this->Ln(5);
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 6, 'Tanggal Cetak: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
            $this->Ln(5);
        }
        
        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . ' - Bookie', 0, 0, 'C');
        }
    }
    
    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);
    
    // Header tabel
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Invoice', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Pembeli', 1, 0, 'C', true);
    $pdf->Cell(65, 8, 'Produk', 1, 0, 'C', true);
    $pdf->Cell(15, 8, 'Item', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Total', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Pembayaran', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Tanggal', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    
    mysqli_data_seek($qTransaksi, 0);
    $no = 1;
    $total = 0;
    
    while ($row = mysqli_fetch_assoc($qTransaksi)) {
        $produk = strlen($row['produk']) > 60 ? substr($row['produk'], 0, 57) . '...' : ($row['produk'] ?? '-');
        
        $pdf->Cell(10, 7, $no++, 1, 0, 'C');
        $pdf->Cell(30, 7, $row['kode_transaksi'], 1, 0, 'L');
        $pdf->Cell(30, 7, substr($row['pembeli'] ?? '-', 0, 15), 1, 0, 'L');
        $pdf->Cell(65, 7, $produk, 1, 0, 'L');
        $pdf->Cell(15, 7, $row['total_qty'] ?? $row['total_item'], 1, 0, 'C');
        $pdf->Cell(35, 7, 'Rp ' . number_format($row['total_harga'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 7, strtoupper($row['payment_method'] ?? 'TRANSFER'), 1, 0, 'C');
        $pdf->Cell(35, 7, date('d/m/Y', strtotime($row['created_at'])), 1, 1, 'C');
        
        $total += $row['total_harga'];
    }
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(185, 8, 'TOTAL PENJUALAN', 1, 0, 'R');
    $pdf->Cell(70, 8, 'Rp ' . number_format($total, 0, ',', '.'), 1, 1, 'R');
    
    $pdf->Output('D', 'laporan_penjualan_' . date('Ymd') . '.pdf');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Bookie</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
           LAPORAN SPECIFIC STYLES
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

        /* Filter Form */
        .filter-form {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .btn-primary {
            background: #3498db;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-success {
            background: #2ecc71;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-danger {
            background: #e74c3c;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
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
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-icon.blue { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .stat-icon.green { background: rgba(46, 204, 113, 0.1); color: #2ecc71; }
        .stat-icon.purple { background: rgba(155, 89, 182, 0.1); color: #9b59b6; }
        .stat-icon.orange { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #020617;
            margin-bottom: 4px;
        }

        .stat-label {
            color: #64748b;
            font-size: 14px;
        }

        /* Chart Card */
        .chart-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 18px;
            color: #020617;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Product List */
        .product-list {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info h4 {
            font-size: 15px;
            color: #020617;
            margin-bottom: 4px;
        }

        .product-info small {
            color: #64748b;
            font-size: 12px;
        }

        .product-sales {
            font-weight: 700;
            color: #2ecc71;
        }

        /* Table */
        .table-container {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 18px;
            color: #020617;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-total {
            font-size: 16px;
            font-weight: 600;
            color: #3498db;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 12px;
            font-size: 14px;
            color: #020617;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-selesai { background: rgba(46, 204, 113, 0.1); color: #27ae60; }
        .badge-diproses { background: rgba(243, 156, 18, 0.1); color: #f39c12; }
        .badge-dikirim { background: rgba(52, 152, 219, 0.1); color: #3498db; }
        .badge-batal { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-outline-primary {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }

        .btn-outline-primary:hover {
            background: #3498db;
            color: white;
        }

        .btn-outline-success {
            background: transparent;
            border: 2px solid #2ecc71;
            color: #2ecc71;
        }

        .btn-outline-success:hover {
            background: #2ecc71;
            color: white;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding: 20px;
            text-align: center;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            opacity: 0.5;
            margin-bottom: 16px;
        }

        .empty-state h4 {
            margin-bottom: 8px;
            color: #020617;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form .row > div {
                margin-bottom: 10px;
            }
            
            .btn {
                width: 100%;
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
        <img src="<?= htmlspecialchars($fotoPath ?? '../assets/img/user.png') ?>" alt="Profile">
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
        
        <a href="laporan.php" class="menu-item active">
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
        <input type="text" class="search-box" placeholder="Cari laporan...">
    </div>
    
    <button class="search-toggle" id="searchToggle">
        <i class="bi bi-search"></i>
    </button>
    
    <div class="top-bar-right">
        <a href="profile.php" class="user-profile-top">
            <img src="<?= htmlspecialchars($fotoPath ?? '../assets/img/user.png') ?>" alt="Profile">
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
        <h2>📊 Laporan Penjualan</h2>
        <p>
            Pantau performa toko Anda dengan laporan detail transaksi, statistik penjualan, 
            dan analisis produk terlaris.
        </p>
    </div>

    <!-- FILTER FORM -->
    <div class="filter-form">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="semua" <?= $status == 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="selesai" <?= $status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="dikirim" <?= $status == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                        <option value="dibayar" <?= $status == 'dibayar' ? 'selected' : '' ?>>Dibayar</option>
                        <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cari</label>
                    <input type="text" class="form-control" name="search" placeholder="Nama pembeli..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="bi bi-cart"></i>
            </div>
            <div class="stat-value"><?= number_format($stat['total_transaksi']) ?></div>
            <div class="stat-label">Total Transaksi</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-value">Rp <?= number_format($stat['total_penjualan']) ?></div>
            <div class="stat-label">Total Penjualan</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= number_format($stat['total_pembeli']) ?></div>
            <div class="stat-label">Total Pembeli</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value">Rp <?= number_format($stat['rata_rata']) ?></div>
            <div class="stat-label">Rata-rata Transaksi</div>
        </div>
    </div>

    <!-- CHART & TOP PRODUCTS -->
    <div class="row">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-bar-chart-line" style="color: #3498db;"></i>
                        Grafik Penjualan
                    </h3>
                    <span class="badge" style="background: #e8f4fd; color: #3498db; padding: 6px 12px;">
                        <i class="bi bi-calendar"></i> <?= $tglStart ?> - <?= $tglEnd ?>
                    </span>
                </div>
                <canvas id="salesChart" height="300"></canvas>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="product-list">
                <h3 style="margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-trophy" style="color: #f39c12;"></i>
                    Produk Terlaris
                </h3>
                
                <?php if (mysqli_num_rows($qProduk) > 0): ?>
                    <?php mysqli_data_seek($qProduk, 0); ?>
                    <?php while ($produk = mysqli_fetch_assoc($qProduk)): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <h4><?= htmlspecialchars($produk['nama_produk']) ?></h4>
                                <small>
                                    <i class="bi bi-box"></i> <?= $produk['jumlah_terjual'] ?> terjual
                                </small>
                            </div>
                            <div class="product-sales">
                                Rp <?= number_format($produk['total_penjualan']) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 30px 0;">
                        <i class="bi bi-box"></i>
                        <h4>Belum ada data</h4>
                        <p>Belum ada produk terjual pada periode ini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TRANSACTIONS TABLE -->
    <div class="table-container">
        <div class="table-header">
            <h3>
                <i class="bi bi-table" style="color: #3498db;"></i>
                Detail Transaksi
            </h3>
            <div class="table-total">
                <i class="bi bi-calculator"></i>
                Total: Rp <?= number_format($totalFooter['total_all'] ?? 0) ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Invoice</th>
                    <th>Pembeli</th>
                    <th>Produk</th>
                    <th>Item</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($qTransaksi) > 0): ?>
                    <?php 
                    $no = 1;
                    mysqli_data_seek($qTransaksi, 0);
                    while ($row = mysqli_fetch_assoc($qTransaksi)): 
                        $statusClass = '';
                        switch($row['status']) {
                            case 'selesai': $statusClass = 'badge-selesai'; break;
                            case 'dikirim': $statusClass = 'badge-dikirim'; break;
                            case 'dibayar': $statusClass = 'badge-diproses'; break;
                            default: $statusClass = 'badge-batal'; break;
                        }
                        
                        // Format produk untuk tampilan
                        $produkArr = explode('; ', $row['produk']);
                        $qtyArr = explode('; ', $row['qty_produk']);
                        $produkTampil = [];
                        for ($i = 0; $i < min(3, count($produkArr)); $i++) {
                            if (!empty($produkArr[$i])) {
                                $produkTampil[] = $produkArr[$i] . (isset($qtyArr[$i]) ? ' x' . $qtyArr[$i] : '');
                            }
                        }
                        $produkText = implode(', ', $produkTampil);
                        if (count($produkArr) > 3) {
                            $produkText .= ' ...';
                        }
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($row['kode_transaksi']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($row['pembeli'] ?? '-') ?>
                            <?php if (!empty($row['email_pembeli'])): ?>
                                <br><small style="color: #64748b;"><?= htmlspecialchars($row['email_pembeli']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($produkText) ?></td>
                        <td class="text-center"><?= $row['total_qty'] ?? $row['total_item'] ?></td>
                        <td style="font-weight: 600; color: #3498db;">
                            Rp <?= number_format($row['total_harga']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $statusClass ?>">
                                <?= strtoupper($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                            <br><small style="color: #64748b;"><?= date('H:i', strtotime($row['created_at'])) ?></small>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="detail_transaksi.php?id=<?= $row['id'] ?>" class="btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="invoice.php?id=<?= $row['id'] ?>" class="btn-sm btn-outline-success" target="_blank">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 60px;">
                            <i class="bi bi-inbox" style="font-size: 48px; color: #cbd5e1;"></i>
                            <p style="margin-top: 16px; color: #64748b;">Tidak ada data transaksi</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p class="mb-0">&copy; <?= date('Y') ?> BOOKIE - Platform Jual Beli Buku. All rights reserved.</p>
        <small class="text-muted">Laporan digenerate pada <?= date('d/m/Y H:i:s') ?></small>
    </div>
</div>

<script>
// DOM Elements
const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const searchToggle = document.getElementById('searchToggle');
const searchContainer = document.getElementById('searchContainer');

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

// Grafik Penjualan
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labelsGrafik) ?>,
        datasets: [{
            label: 'Total Penjualan',
            data: <?= json_encode($valuesGrafik) ?>,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3498db',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Rp ' + context.raw.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                },
                grid: {
                    color: '#e2e8f0'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>
</html>
<?php
// Tutup koneksi database
mysqli_close($conn);
?>