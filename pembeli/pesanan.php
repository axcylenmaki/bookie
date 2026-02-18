<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

/* =====================
   DATA LOGIN
===================== */
$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'] ?? 'Pembeli';

/* =====================
   FILTER & PAGINATION
===================== */
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'semua';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Map status filter ke database
$statusMap = [
    'semua' => '',
    'pending' => 'pending',
    'dibayar' => 'dibayar',
    'dikirim' => 'dikirim',
    'selesai' => 'selesai'
];
$dbStatus = $statusMap[$statusFilter] ?? '';

/* =====================
   HITUNG TOTAL & PAGINATION
===================== */
$countWhere = "WHERE t.id_user = '$idPembeli'";
if (!empty($dbStatus)) {
    $countWhere .= " AND t.status = '$dbStatus'";
}
if (!empty($searchQuery)) {
    $countWhere .= " AND (t.id LIKE '%$searchQuery%' OR p.nama_produk LIKE '%$searchQuery%')";
}

$countQuery = mysqli_query($conn, "
    SELECT COUNT(DISTINCT t.id) as total 
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    $countWhere
");

if (!$countQuery) {
    die("Error count: " . mysqli_error($conn));
}

$totalOrders = mysqli_fetch_assoc($countQuery)['total'];
$totalPages = ceil($totalOrders / $limit);

/* =====================
   AMBIL DATA PESANAN
===================== */
$whereClause = "WHERE t.id_user = '$idPembeli'";

if (!empty($dbStatus)) {
    $whereClause .= " AND t.status = '$dbStatus'";
}

if (!empty($searchQuery)) {
    $whereClause .= " AND (t.id LIKE '%$searchQuery%' OR p.nama_produk LIKE '%$searchQuery%')";
}

$qPesanan = mysqli_query($conn, "
    SELECT 
        t.id,
        t.total,
        t.status,
        t.created_at,
        t.updated_at,
        t.no_resi,
        t.resi_ekspedisi,
        COUNT(DISTINCT td.id) as jumlah_item,
        SUM(td.jumlah) as total_qty,
        GROUP_CONCAT(DISTINCT p.nama_produk SEPARATOR '||') as produk_list,
        GROUP_CONCAT(DISTINCT p.gambar SEPARATOR '||') as gambar_list,
        GROUP_CONCAT(DISTINCT u.nama SEPARATOR '||') as penjual_list,
        GROUP_CONCAT(DISTINCT u.id SEPARATOR '||') as penjual_id_list,
        GROUP_CONCAT(DISTINCT td.jumlah SEPARATOR '||') as qty_list,
        GROUP_CONCAT(DISTINCT td.harga SEPARATOR '||') as harga_list
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    LEFT JOIN users u ON p.id_penjual = u.id
    $whereClause
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT $offset, $limit
");

if (!$qPesanan) {
    die("Error mengambil pesanan: " . mysqli_error($conn));
}

/* =====================
   PROCESS DATA
===================== */
$pesanan = [];
while ($row = mysqli_fetch_assoc($qPesanan)) {
    // Parse produk list
    $produkList = explode('||', $row['produk_list']);
    $gambarList = explode('||', $row['gambar_list']);
    $penjualList = explode('||', $row['penjual_list']);
    $penjualIdList = explode('||', $row['penjual_id_list']);
    $qtyList = explode('||', $row['qty_list']);
    $hargaList = explode('||', $row['harga_list']);
    
    $items = [];
    $penjualPertama = '';
    $penjualIdPertama = '';
    
    for ($i = 0; $i < count($produkList); $i++) {
        if (!empty($produkList[$i])) {
            $items[] = [
                'nama' => $produkList[$i],
                'gambar' => $gambarList[$i] ?? '',
                'penjual' => $penjualList[$i] ?? 'Toko BOOKIE',
                'penjual_id' => $penjualIdList[$i] ?? 0,
                'qty' => $qtyList[$i] ?? 0,
                'harga' => $hargaList[$i] ?? 0
            ];
            
            if (empty($penjualPertama)) {
                $penjualPertama = $penjualList[$i] ?? 'Toko BOOKIE';
                $penjualIdPertama = $penjualIdList[$i] ?? 0;
            }
        }
    }
    
    $row['items'] = $items;
    $row['penjual'] = $penjualPertama;
    $row['penjual_id'] = $penjualIdPertama;
    $row['produk_pertama'] = $items[0] ?? null;
    $row['sisa_produk'] = count($items) - 1;
    $pesanan[] = $row;
}

/* =====================
   HITUNG STATUS COUNTS (untuk badge)
===================== */
$countSemua = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli'"))['total'];
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli' AND status='pending'"))['total'];
$countDibayar = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli' AND status='dibayar'"))['total'];
$countDikirim = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli' AND status='dikirim'"))['total'];
$countSelesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli' AND status='selesai'"))['total'];

/* =====================
   FUNGSI BANTU
===================== */
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge-status pending"><i class="bi bi-clock"></i> Menunggu Pembayaran</span>';
        case 'dibayar':
            return '<span class="badge-status paid"><i class="bi bi-check-circle"></i> Diproses Penjual</span>';
        case 'dikirim':
            return '<span class="badge-status shipped"><i class="bi bi-truck"></i> Dalam Pengiriman</span>';
        case 'selesai':
            return '<span class="badge-status completed"><i class="bi bi-check2-all"></i> Selesai</span>';
        default:
            return '<span class="badge-status">' . $status . '</span>';
    }
}

function getStatusIcon($status) {
    switch($status) {
        case 'pending': return 'bi-clock text-warning';
        case 'dibayar': return 'bi-check-circle text-primary';
        case 'dikirim': return 'bi-truck text-info';
        case 'selesai': return 'bi-check2-all text-success';
        default: return 'bi-question-circle';
    }
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* Main Layout */
        .main-content {
            margin-left: 250px;
            padding: 25px 30px;
            transition: all 0.3s;
        }

        /* Header */
        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
        }

        .page-header p {
            color: #6c757d;
            margin: 5px 0 0 0;
            font-size: 1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stat-icon.pending { background: #fff3e0; color: #f39c12; }
        .stat-icon.paid { background: #e3f2fd; color: #2196f3; }
        .stat-icon.shipped { background: #e8f5e9; color: #4caf50; }
        .stat-icon.completed { background: #e8eaf6; color: #3f51b5; }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 5px 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            flex: 1;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 30px;
            background: #f8f9fa;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
        }

        .filter-tab:hover {
            background: #e9ecef;
            color: #1a1a1a;
        }

        .filter-tab.active {
            background: #6f42c1;
            color: white;
        }

        .filter-tab .count {
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .filter-tab.active .count {
            background: rgba(255, 255, 255, 0.2);
        }

        .search-wrapper {
            position: relative;
            min-width: 280px;
        }

        .search-wrapper input {
            width: 100%;
            padding: 10px 45px 10px 20px;
            border: 1px solid #e9ecef;
            border-radius: 30px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #f8f9fa;
        }

        .search-wrapper input:focus {
            outline: none;
            border-color: #6f42c1;
            background: white;
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.1);
        }

        .search-wrapper button {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6f42c1;
            font-size: 1.1rem;
            cursor: pointer;
        }

        /* Order Cards */
        .order-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        }

        .order-header {
            padding: 15px 20px;
            background: #fafbfc;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-info {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .order-id {
            font-weight: 600;
            color: #1a1a1a;
            background: white;
            padding: 5px 12px;
            border-radius: 30px;
            border: 1px solid #e9ecef;
        }

        .order-id i {
            color: #6f42c1;
            margin-right: 6px;
        }

        .order-date {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status.pending { background: #fff3e0; color: #f39c12; }
        .badge-status.paid { background: #e3f2fd; color: #2196f3; }
        .badge-status.shipped { background: #e8f5e9; color: #4caf50; }
        .badge-status.completed { background: #e8eaf6; color: #3f51b5; }

        .order-body {
            padding: 20px;
        }

        /* Seller Info */
        .seller-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #f0f0f0;
        }

        .seller-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #6f42c1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .seller-details {
            flex: 1;
        }

        .seller-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .seller-rating {
            color: #f39c12;
            font-size: 0.8rem;
        }

        .seller-rating i {
            margin-right: 2px;
        }

        .seller-rating span {
            color: #6c757d;
            margin-left: 5px;
        }

        /* Product Summary */
        .product-summary {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .product-image {
            width: 70px;
            height: 90px;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            flex-shrink: 0;
            border: 1px solid #f0f0f0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .product-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 8px;
        }

        .product-price {
            color: #6f42c1;
            font-weight: 600;
        }

        .product-qty {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .more-items {
            color: #6f42c1;
            font-size: 0.9rem;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
        }

        .more-items i {
            font-size: 0.8rem;
        }

        /* Order Footer */
        .order-footer {
            padding: 15px 20px;
            background: #fafbfc;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-total {
            font-size: 1rem;
            font-weight: 500;
            color: #1a1a1a;
        }

        .order-total span {
            color: #28a745;
            font-size: 1.3rem;
            font-weight: 700;
            margin-left: 10px;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #6f42c1;
            color: white;
        }

        .btn-primary:hover {
            background: #5a32a3;
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid #6f42c1;
            color: #6f42c1;
        }

        .btn-outline:hover {
            background: #6f42c1;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #1a1a1a;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            list-style: none;
        }

        .page-item .page-link {
            display: block;
            padding: 10px 18px;
            border-radius: 30px;
            background: white;
            color: #1a1a1a;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid #f0f0f0;
            font-weight: 500;
        }

        .page-item .page-link:hover {
            background: #f8f9fa;
            border-color: #6f42c1;
            color: #6f42c1;
        }

        .page-item.active .page-link {
            background: #6f42c1;
            color: white;
            border-color: #6f42c1;
        }

        .page-item.disabled .page-link {
            background: #f8f9fa;
            color: #adb5bd;
            pointer-events: none;
            border-color: #f0f0f0;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
            border: 1px solid #f0f0f0;
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #1a1a1a;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            margin-bottom: 20px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-shop {
            background: #6f42c1;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-shop:hover {
            background: #5a32a3;
            color: white;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-wrapper {
                width: 100%;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-actions {
                width: 100%;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .product-summary {
                flex-direction: column;
                text-align: center;
            }

            .product-image {
                width: 120px;
                height: 150px;
            }

            .order-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-card {
            animation: slideIn 0.3s ease;
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>

<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h1>Pesanan Saya</h1>
        <p>Kelola dan pantau semua pesanan Anda dengan mudah</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-value"><?= $countPending ?></div>
            <div class="stat-label">Menunggu Bayar</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon paid">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?= $countDibayar ?></div>
            <div class="stat-label">Diproses</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon shipped">
                <i class="bi bi-truck"></i>
            </div>
            <div class="stat-value"><?= $countDikirim ?></div>
            <div class="stat-label">Dikirim</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon completed">
                <i class="bi bi-check2-all"></i>
            </div>
            <div class="stat-value"><?= $countSelesai ?></div>
            <div class="stat-label">Selesai</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-tabs">
            <a href="pesanan.php" class="filter-tab <?= $statusFilter == 'semua' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> Semua
                <span class="count"><?= $countSemua ?></span>
            </a>
            <a href="?status=pending" class="filter-tab <?= $statusFilter == 'pending' ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> Menunggu
                <span class="count"><?= $countPending ?></span>
            </a>
            <a href="?status=dibayar" class="filter-tab <?= $statusFilter == 'dibayar' ? 'active' : '' ?>">
                <i class="bi bi-check-circle"></i> Diproses
                <span class="count"><?= $countDibayar ?></span>
            </a>
            <a href="?status=dikirim" class="filter-tab <?= $statusFilter == 'dikirim' ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Dikirim
                <span class="count"><?= $countDikirim ?></span>
            </a>
            <a href="?status=selesai" class="filter-tab <?= $statusFilter == 'selesai' ? 'active' : '' ?>">
                <i class="bi bi-check2-all"></i> Selesai
                <span class="count"><?= $countSelesai ?></span>
            </a>
        </div>

        <div class="search-wrapper">
            <form action="" method="GET">
                <?php if($statusFilter != 'semua'): ?>
                    <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Cari pesanan..." value="<?= htmlspecialchars($searchQuery) ?>">
                <button type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <?php if(count($pesanan) > 0): ?>
        <?php foreach($pesanan as $order): ?>
            <!-- Order Card -->
            <div class="order-card">
                <!-- Header -->
                <div class="order-header">
                    <div class="order-info">
                        <div class="order-id">
                            <i class="bi bi-receipt"></i> #<?= $order['id'] ?>
                        </div>
                        <div class="order-date">
                            <i class="bi bi-calendar3"></i> <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                        </div>
                    </div>
                    <?= getStatusBadge($order['status']) ?>
                </div>

                <!-- Body -->
                <div class="order-body">
                    <!-- Seller Info -->
                    <div class="seller-info">
                        <div class="seller-avatar">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div class="seller-details">
                            <div class="seller-name"><?= htmlspecialchars($order['penjual']) ?></div>
                            <div class="seller-rating">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-half"></i>
                                <span>(4.5)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Product Summary -->
                    <?php if($order['produk_pertama']): ?>
                    <div class="product-summary">
                        <div class="product-image">
                            <?php 
                            $gambarProduk = $order['produk_pertama']['gambar'] ?? '';
                            $pathGambar = '../uploads/' . $gambarProduk; // LANGSUNG KE FOLDER UPLOADS
                            
                            if(!empty($gambarProduk) && file_exists($pathGambar)): 
                            ?>
                                <img src="<?= $pathGambar ?>" 
                                     alt="<?= htmlspecialchars($order['produk_pertama']['nama']) ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                    <i class="bi bi-image" style="font-size: 2rem; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($order['produk_pertama']['nama']) ?></div>
                            <div class="product-meta">
                                <span class="product-price"><?= formatRupiah($order['produk_pertama']['harga']) ?></span>
                                <span class="product-qty">x<?= $order['produk_pertama']['qty'] ?></span>
                            </div>
                            <?php if($order['sisa_produk'] > 0): ?>
                            <div class="more-items">
                                <i class="bi bi-plus-circle"></i> +<?= $order['sisa_produk'] ?> produk lainnya
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="order-footer">
                    <div class="order-total">
                        Total Belanja: <span><?= formatRupiah($order['total']) ?></span>
                    </div>
                    <div class="order-actions">
                        <!-- Actions based on status -->
                        <?php if($order['status'] == 'pending'): ?>
                            <a href="bayar.php?id=<?= $order['id'] ?>" class="btn-action btn-primary">
                                <i class="bi bi-credit-card"></i> Bayar Sekarang
                            </a>
                            <a href="batalkan.php?id=<?= $order['id'] ?>" class="btn-action btn-danger" 
                               onclick="return confirm('Batalkan pesanan ini?')">
                                <i class="bi bi-x-circle"></i> Batalkan
                            </a>

                        <?php elseif($order['status'] == 'dibayar'): ?>
                            <a href="chat.php?penjual_id=<?= $order['penjual_id'] ?>" class="btn-action btn-outline">
                                <i class="bi bi-chat"></i> Chat Penjual
                            </a>

                        <?php elseif($order['status'] == 'dikirim'): ?>
                            <a href="status.php?id=<?= $order['id'] ?>" class="btn-action btn-primary">
                                <i class="bi bi-truck"></i> Lacak Pengiriman
                            </a>
                            <a href="terima.php?id=<?= $order['id'] ?>" class="btn-action btn-success" 
                               onclick="return confirm('Konfirmasi pesanan sudah diterima?')">
                                <i class="bi bi-check-lg"></i> Terima Pesanan
                            </a>

                        <?php elseif($order['status'] == 'selesai'): ?>
                            <a href="beli_lagi.php?id=<?= $order['id'] ?>" class="btn-action btn-primary">
                                <i class="bi bi-cart-plus"></i> Beli Lagi
                            </a>
                            <a href="rating.php?id=<?= $order['id'] ?>" class="btn-action btn-warning">
                                <i class="bi bi-star"></i> Beri Rating
                            </a>
                        <?php endif; ?>

                        <!-- Common action - View Detail -->
                        <a href="status.php?id=<?= $order['id'] ?>" class="btn-action btn-outline">
                            <i class="bi bi-eye"></i> Detail Pesanan
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <ul class="pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <?php if($i == 1 || $i == $totalPages || ($i >= $page-2 && $i <= $page+2)): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php elseif($i == 2 && $page > 4): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php elseif($i == $totalPages-1 && $page < $totalPages-3): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
        <?php endif; ?>

    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="bi bi-bag-x"></i>
            <h4>Tidak Ada Pesanan</h4>
            <p>
                <?php if(!empty($searchQuery)): ?>
                    Maaf, pencarian "<strong><?= htmlspecialchars($searchQuery) ?></strong>" tidak ditemukan.
                    Coba dengan kata kunci lain.
                <?php elseif($statusFilter != 'semua'): ?>
                    Belum ada pesanan dengan status "<?= $statusFilter ?>".
                <?php else: ?>
                    Anda belum memiliki pesanan apapun. Yuk, mulai belanja sekarang!
                <?php endif; ?>
            </p>
            <a href="produk.php" class="btn-shop">
                <i class="bi bi-shop me-2"></i>Mulai Belanja
            </a>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search with debounce
let searchTimeout;
document.querySelector('.search-wrapper input')?.addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if(this.value.length >= 2 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Auto refresh notification count
function updateNotificationCount() {
    fetch('ajax/get_notif_count.php')
        .then(response => response.json())
        .then(data => {
            document.querySelectorAll('.notification-badge').forEach(badge => {
                if(data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            });
        })
        .catch(err => console.log('Error fetching notifications:', err));
}
setInterval(updateNotificationCount, 30000);

// Smooth scroll to top when pagination clicked
document.querySelectorAll('.page-link').forEach(link => {
    link.addEventListener('click', function(e) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

// Konfirmasi sebelum aksi penting
document.querySelectorAll('.btn-danger[onclick]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Apakah Anda yakin?')) {
            e.preventDefault();
        }
    });
});

// Tooltip initialization
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});

// Print order
function printOrder(orderId) {
    window.open('print_order.php?id=' + orderId, '_blank');
}

// Track order
function trackOrder(resi, ekspedisi) {
    if(resi && ekspedisi) {
        window.open(`https://cekresi.com/?noresi=${resi}`, '_blank');
    }
}

// Mark as received confirmation
function confirmReceived(orderId) {
    if(confirm('Apakah Anda yakin pesanan sudah diterima?')) {
        window.location.href = 'terima.php?id=' + orderId;
    }
}

// Cancel order confirmation
function confirmCancel(orderId) {
    if(confirm('Batalkan pesanan ini? Aksi ini tidak dapat dibatalkan.')) {
        window.location.href = 'batalkan.php?id=' + orderId;
    }
}
</script>

</body>
</html>