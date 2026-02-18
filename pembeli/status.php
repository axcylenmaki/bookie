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
   VALIDASI ID TRANSAKSI
===================== */
$id_transaksi = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idPembeli = $_SESSION['user']['id'];

// Ambil data transaksi
$qTransaksi = mysqli_query($conn, "
    SELECT t.*, 
           u.nama as nama_penjual,
           u.foto as foto_penjual,
           u.no_hp as no_hp_penjual,
           r.bank,
           r.no_rekening,
           r.nama_pemilik
    FROM transaksi t
    LEFT JOIN transaksi_detail td ON t.id = td.id_transaksi
    LEFT JOIN produk p ON td.id_produk = p.id
    LEFT JOIN users u ON p.id_penjual = u.id
    LEFT JOIN rekening_penjual r ON u.id = r.id_penjual
    WHERE t.id = '$id_transaksi' AND t.id_user = '$idPembeli'
    GROUP BY t.id
");

if (!$qTransaksi || mysqli_num_rows($qTransaksi) == 0) {
    header("Location: pesanan.php?error=Transaksi tidak ditemukan");
    exit;
}

$transaksi = mysqli_fetch_assoc($qTransaksi);

// Ambil detail produk
$qDetail = mysqli_query($conn, "
    SELECT td.*, 
           p.nama_produk,
           p.gambar,
           p.id_penjual,
           u.nama as nama_penjual,
           u.foto as foto_penjual
    FROM transaksi_detail td
    JOIN produk p ON td.id_produk = p.id
    LEFT JOIN users u ON p.id_penjual = u.id
    WHERE td.id_transaksi = '$id_transaksi'
");

$detail_produk = [];
$penjual_id = 0;
while ($row = mysqli_fetch_assoc($qDetail)) {
    $detail_produk[] = $row;
    if ($penjual_id == 0) {
        $penjual_id = $row['id_penjual'];
    }
}

$chats = [];
$rating = null;

// Cek apakah user sudah upload bukti
$sudah_upload = !empty($transaksi['bukti_transfer']);

/* =====================
   FUNGSI BANTU
===================== */
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge-status pending"><i class="bi bi-clock"></i> Menunggu Pembayaran</span>';
        case 'dibayar':
            return '<span class="badge-status paid"><i class="bi bi-check-circle"></i> Menunggu Konfirmasi</span>';
        case 'dikirim':
            return '<span class="badge-status shipped"><i class="bi bi-truck"></i> Dalam Pengiriman</span>';
        case 'selesai':
            return '<span class="badge-status completed"><i class="bi bi-check2-all"></i> Selesai</span>';
        default:
            return '<span class="badge-status">' . $status . '</span>';
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
    <title>Status Pesanan #<?= $transaksi['id'] ?> - BOOKIE</title>
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
            color: #1a1a1a;
        }

        .main-content {
            margin-left: 250px;
            padding: 25px 30px;
            transition: all 0.3s;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border-radius: 30px;
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            border: 1px solid #f0f0f0;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: #f8f9fa;
            color: #6f42c1;
            transform: translateX(-3px);
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
        }

        .order-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .order-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .order-code {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 600;
            color: #6f42c1;
        }

        .order-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #6c757d;
        }

        .order-meta i {
            margin-right: 5px;
            color: #6f42c1;
        }

        .tracking-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 25px;
            margin-bottom: 25px;
        }

        .timeline-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
        }

        .timeline-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-card h3 i {
            color: #6f42c1;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: white;
            border: 2px solid #adb5bd;
            z-index: 1;
        }

        .timeline-item.completed::before {
            background: #28a745;
            border-color: #28a745;
        }

        .timeline-item.active::before {
            background: #6f42c1;
            border-color: #6f42c1;
            box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.2);
        }

        .timeline-time {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline-desc {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            margin-bottom: 25px;
        }

        .info-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .seller-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, #667eea 0%, #6f42c1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .seller-info h4 {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .seller-info p {
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
        }

        .info-value.highlight {
            color: #28a745;
            font-size: 1.2rem;
        }

        .products-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            margin-bottom: 25px;
        }

        .product-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .product-item:last-child {
            border-bottom: none;
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

        .product-details {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .product-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .product-price {
            color: #6f42c1;
            font-weight: 600;
        }

        .product-seller {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .action-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            border: 1px solid #f0f0f0;
            margin-bottom: 25px;
            text-align: center;
        }

        .action-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: #6f42c1;
        }

        .action-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .action-desc {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .action-button {
            width: 100%;
            padding: 12px;
            border-radius: 30px;
            border: none;
            font-weight: 600;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .action-button.primary {
            background: #6f42c1;
            color: white;
        }

        .action-button.primary:hover {
            background: #5a32a3;
        }

        .action-button.success {
            background: #28a745;
            color: white;
        }

        .action-button.success:hover {
            background: #218838;
        }

        .action-button.outline {
            background: white;
            border: 1px solid #6f42c1;
            color: #6f42c1;
        }

        .action-button.outline:hover {
            background: #6f42c1;
            color: white;
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

        @media (max-width: 992px) {
            .tracking-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>

<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
    <a href="pesanan.php" class="back-button">
        <i class="bi bi-arrow-left"></i> Kembali ke Pesanan
    </a>

    <div class="page-header">
        <div class="order-title">
            <h1>Status Pesanan</h1>
            <span class="order-code">#<?= $transaksi['id'] ?></span>
        </div>
        <div class="order-meta">
            <span><i class="bi bi-calendar3"></i> <?= date('d M Y H:i', strtotime($transaksi['created_at'])) ?></span>
            <span><i class="bi bi-shop"></i> <?= htmlspecialchars($transaksi['nama_penjual']) ?></span>
            <?= getStatusBadge($transaksi['status']) ?>
        </div>
    </div>

    <div class="tracking-grid">
        <!-- Left Column -->
        <div>
            <!-- Timeline Card -->
            <div class="timeline-card">
                <h3><i class="bi bi-clock-history"></i> Timeline Pesanan</h3>
                <div class="timeline">
                    <?php
                    $status = $transaksi['status'];
                    $tgl_buat = date('d M Y H:i', strtotime($transaksi['created_at']));
                    $tgl_update = date('d M Y H:i', strtotime($transaksi['updated_at']));
                    ?>
                    
                    <div class="timeline-item completed">
                        <div class="timeline-time"><?= $tgl_buat ?></div>
                        <div class="timeline-title">
                            <i class="bi bi-check-circle-fill text-success"></i> Pesanan Dibuat
                        </div>
                        <div class="timeline-desc">Pesanan berhasil dibuat</div>
                    </div>

                    <?php if($status != 'pending'): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-time"><?= $tgl_update ?></div>
                        <div class="timeline-title">
                            <i class="bi bi-check-circle-fill text-success"></i> Pembayaran Diverifikasi
                        </div>
                        <div class="timeline-desc">Pembayaran telah dikonfirmasi oleh penjual</div>
                    </div>
                    <?php else: ?>
                    <div class="timeline-item active">
                        <div class="timeline-time">Sekarang</div>
                        <div class="timeline-title">
                            <i class="bi bi-hourglass-split"></i> Menunggu Pembayaran
                        </div>
                        <div class="timeline-desc">Silakan lakukan pembayaran dan upload bukti transfer</div>
                    </div>
                    <?php endif; ?>

                    <?php if(in_array($status, ['dibayar', 'dikirim', 'selesai'])): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-time"><?= $tgl_update ?></div>
                        <div class="timeline-title">
                            <i class="bi bi-check-circle-fill text-success"></i> Diproses Penjual
                        </div>
                        <div class="timeline-desc">Penjual sedang memproses pesanan Anda</div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($transaksi['no_resi'])): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-time"><?= $tgl_update ?></div>
                        <div class="timeline-title">
                            <i class="bi bi-check-circle-fill text-success"></i> Pesanan Dikirim
                        </div>
                        <div class="timeline-desc">
                            No. Resi: <strong><?= $transaksi['no_resi'] ?></strong><br>
                            Ekspedisi: <?= $transaksi['resi_ekspedisi'] ?? 'JNE' ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($status == 'selesai'): ?>
                    <div class="timeline-item completed">
                        <div class="timeline-time"><?= $tgl_update ?></div>
                        <div class="timeline-title">
                            <i class="bi bi-check-circle-fill text-success"></i> Pesanan Selesai
                        </div>
                        <div class="timeline-desc">Terima kasih telah berbelanja di BOOKIE</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Products Card -->
            <div class="products-card">
                <h3><i class="bi bi-box"></i> Produk yang Dibeli</h3>
                <?php foreach($detail_produk as $produk): ?>
                <div class="product-item">
                    <div class="product-image">
                        <?php 
                        $gambarProduk = $produk['gambar'] ?? '';
                        $pathGambar = '../uploads/' . $gambarProduk;
                        
                        if(!empty($gambarProduk) && file_exists($pathGambar)): 
                        ?>
                            <img src="<?= $pathGambar ?>" 
                                 alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                <i class="bi bi-image" style="font-size: 2rem; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-details">
                        <div class="product-name"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                        <div class="product-meta">
                            <span class="product-price"><?= formatRupiah($produk['harga']) ?></span>
                            <span>x<?= $produk['jumlah'] ?></span>
                        </div>
                        <div class="product-seller">
                            <i class="bi bi-shop"></i> <?= htmlspecialchars($produk['nama_penjual']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <div class="info-row">
                        <span class="info-label">Subtotal</span>
                        <span class="info-value"><?= formatRupiah($transaksi['total']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ongkos Kirim</span>
                        <span class="info-value"><?= formatRupiah(0) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label fw-bold">Total</span>
                        <span class="info-value highlight"><?= formatRupiah($transaksi['total']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Payment Info Card -->
            <div class="info-card">
                <h3><i class="bi bi-credit-card"></i> Informasi Pembayaran</h3>
                
                <?php if(!empty($transaksi['bank'])): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <p class="mb-2"><strong>Transfer ke:</strong></p>
                    <p class="mb-1">Bank: <?= $transaksi['bank'] ?></p>
                    <p class="mb-1">No. Rekening: <?= $transaksi['no_rekening'] ?></p>
                    <p class="mb-0">Atas Nama: <?= $transaksi['nama_pemilik'] ?></p>
                </div>
                <?php endif; ?>

                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value"><?= getStatusBadge($transaksi['status']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Dibayar</span>
                    <span class="info-value highlight"><?= formatRupiah($transaksi['total']) ?></span>
                </div>
                
                <?php if(!empty($transaksi['bukti_transfer'])): ?>
                <div class="info-row">
                    <span class="info-label">Bukti Transfer</span>
                    <span class="info-value">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#buktiModal">
                            <i class="bi bi-file-image"></i> Lihat
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Shipping Info Card -->
            <?php if(!empty($transaksi['no_resi'])): ?>
            <div class="info-card">
                <h3><i class="bi bi-truck"></i> Informasi Pengiriman</h3>
                <div class="info-row">
                    <span class="info-label">Kurir</span>
                    <span class="info-value"><?= $transaksi['resi_ekspedisi'] ?? 'JNE' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">No. Resi</span>
                    <span class="info-value"><?= $transaksi['no_resi'] ?></span>
                </div>
                <button class="action-button primary mt-3" onclick="trackPackage('<?= $transaksi['no_resi'] ?>', '<?= $transaksi['resi_ekspedisi'] ?? 'jne' ?>')">
                    <i class="bi bi-geo-alt"></i> Lacak Pengiriman
                </button>
            </div>
            <?php endif; ?>

            <!-- Seller Info Card -->
            <div class="info-card">
                <h3><i class="bi bi-shop"></i> Informasi Penjual</h3>
                <div class="seller-profile">
                    <div class="seller-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="seller-info">
                        <h4><?= htmlspecialchars($transaksi['nama_penjual']) ?></h4>
                        <p><i class="bi bi-telephone"></i> <?= $transaksi['no_hp_penjual'] ?? '-' ?></p>
                    </div>
                </div>
                <!-- PERBAIKAN 1: Ubah chat.php menjadi chat/ -->
                <a href="chat/?penjual_id=<?= $penjual_id ?>" class="action-button outline">
                    <i class="bi bi-chat-dots"></i> Hubungi Penjual
                </a>
            </div>

            <!-- Action Card -->
            <div class="action-card">
                <?php if($transaksi['status'] == 'pending'): ?>
                    <div class="action-icon">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div class="action-title">Menunggu Pembayaran</div>
                    <div class="action-desc">Silakan transfer dan upload bukti pembayaran</div>
                    
                    <!-- TOMBOL UPLOAD - TANPA KONDISI, SELALU TAMPIL -->
                    <button class="action-button primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="bi bi-upload"></i> Upload Bukti Transfer
                    </button>
                    
                    <button class="action-button outline" onclick="window.print()">
                        <i class="bi bi-printer"></i> Cetak Invoice
                    </button>

                <?php elseif($transaksi['status'] == 'dibayar'): ?>
                    <div class="action-icon">
                        <i class="bi bi-check-circle" style="color: #28a745;"></i>
                    </div>
                    <div class="action-title">Menunggu Konfirmasi Penjual</div>
                    <div class="action-desc">Penjual akan memproses pesanan Anda segera</div>
                    <!-- PERBAIKAN 2: Ubah chat.php menjadi chat/ -->
                    <a href="chat/?penjual_id=<?= $penjual_id ?>" class="action-button outline">
                        <i class="bi bi-chat-dots"></i> Chat Penjual
                    </a>

                <?php elseif($transaksi['status'] == 'dikirim'): ?>
                    <div class="action-icon">
                        <i class="bi bi-truck" style="color: #4caf50;"></i>
                    </div>
                    <div class="action-title">Pesanan Dalam Perjalanan</div>
                    <div class="action-desc">Pesanan Anda sedang dalam perjalanan</div>
                    
                    <button class="action-button success" onclick="confirmReceived(<?= $transaksi['id'] ?>)">
                        <i class="bi bi-check-lg"></i> Konfirmasi Diterima
                    </button>

                <?php elseif($transaksi['status'] == 'selesai'): ?>
                    <div class="action-icon">
                        <i class="bi bi-check2-all" style="color: #3f51b5;"></i>
                    </div>
                    <div class="action-title">Pesanan Selesai</div>
                    <div class="action-desc">Terima kasih telah berbelanja di BOOKIE</div>
                    <a href="beli_lagi.php?id=<?= $transaksi['id'] ?>" class="action-button primary">
                        <i class="bi bi-cart-plus"></i> Beli Lagi
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- MODAL UPLOAD BUKTI TRANSFER -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="ajax/upload_bukti.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="transaksi_id" value="<?= $transaksi['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">
                        <i class="bi bi-upload me-2 text-primary"></i> Upload Bukti Transfer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <p class="mb-2"><strong>Total Pembayaran:</strong></p>
                        <h4 class="text-primary mb-3"><?= formatRupiah($transaksi['total']) ?></h4>
                        
                        <?php if(!empty($transaksi['bank'])): ?>
                        <p class="mb-1"><strong>Transfer ke:</strong></p>
                        <p class="mb-1">Bank: <?= $transaksi['bank'] ?></p>
                        <p class="mb-1">No. Rekening: <?= $transaksi['no_rekening'] ?></p>
                        <p class="mb-0">Atas Nama: <?= $transaksi['nama_pemilik'] ?></p>
                        <?php else: ?>
                        <p class="mb-0 text-warning">Informasi rekening belum tersedia</p>
                        <?php endif; ?>
                    </div>

                    <!-- File Input -->
                    <div class="mb-3">
                        <label for="bukti_file" class="form-label fw-bold">Pilih File Bukti Transfer</label>
                        <input type="file" 
                               class="form-control" 
                               id="bukti_file" 
                               name="bukti_transfer" 
                               accept="image/jpeg,image/png" 
                               required>
                        <div class="form-text">Format: JPG, PNG (Max 2MB)</div>
                    </div>

                    <!-- Preview Image -->
                    <div id="previewContainer" style="display: none; margin-top: 15px; text-align: center;">
                        <p class="mb-2">Preview:</p>
                        <img src="#" alt="Preview" id="previewImage" style="max-width: 100%; max-height: 200px; border-radius: 10px; border: 1px solid #ddd;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitUpload">
                        <i class="bi bi-cloud-upload"></i> Upload Bukti
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL LIHAT BUKTI -->
<?php if(!empty($transaksi['bukti_transfer'])): ?>
<div class="modal fade" id="buktiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-file-image me-2"></i> Bukti Transfer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="../uploads/bukti_transfer/<?= $transaksi['bukti_transfer'] ?>" 
                     alt="Bukti Transfer" 
                     style="max-width: 100%; max-height: 400px; border-radius: 10px;">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preview file function
document.getElementById('bukti_file')?.addEventListener('change', function(e) {
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('previewImage');
    const file = this.files[0];
    
    if (file) {
        // Validasi ukuran file
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file maksimal 2MB');
            this.value = '';
            previewContainer.style.display = 'none';
            return;
        }
        
        // Validasi tipe file
        if (!file.type.match('image.*')) {
            alert('Hanya file gambar yang diperbolehkan');
            this.value = '';
            previewContainer.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewContainer.style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        previewContainer.style.display = 'none';
    }
});

// Fungsi lacak paket
function trackPackage(resi, courier = 'jne') {
    const trackingUrls = {
        'jne': 'https://www.jne.co.id/tracking/trace/' + resi,
        'jnt': 'https://www.jet.co.id/track/trace/' + resi,
        'sicepat': 'https://www.sicepat.com/tracking/' + resi,
        'pos': 'https://www.posindonesia.co.id/tracking/' + resi
    };
    
    const url = trackingUrls[courier.toLowerCase()] || trackingUrls['jne'];
    window.open(url, '_blank');
}

// Fungsi konfirmasi terima
function confirmReceived(transaksiId) {
    if(confirm('Apakah Anda yakin pesanan sudah diterima?')) {
        window.location.href = 'ajax/konfirmasi_terima.php?id=' + transaksiId;
    }
}

// Print invoice
function printInvoice() {
    window.print();
}

// Form submission handling
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('bukti_file');
    if (!fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        alert('Silakan pilih file bukti transfer terlebih dahulu');
    }
});

// Debug: Cek apakah modal bisa dibuka
document.querySelector('[data-bs-target="#uploadModal"]')?.addEventListener('click', function() {
    console.log('Tombol upload diklik');
});
</script>

</body>
</html>