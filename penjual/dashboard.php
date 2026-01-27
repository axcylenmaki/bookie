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

// Data penjual sudah diambil di sidebar.php, jadi kita bisa pakai dari session
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';

/* =====================
   PRODUK
===================== */
$qProduk = mysqli_query($conn,"
  SELECT 
    COUNT(*) AS total_produk,
    SUM(stok) AS total_stok,
    SUM(CASE WHEN stok=0 THEN 1 ELSE 0 END) AS stok_habis,
    SUM(CASE WHEN stok < 5 AND stok > 0 THEN 1 ELSE 0 END) AS stok_menipis
  FROM produk
  WHERE penjual_id='$idPenjual'
");

if (!$qProduk) {
    die("Query produk error: " . mysqli_error($conn));
}

$p = mysqli_fetch_assoc($qProduk);

$totalProduk = $p['total_produk'] ?? 0;
$totalStok   = $p['total_stok'] ?? 0;
$stokHabis   = $p['stok_habis'] ?? 0;
$stokMenipis = $p['stok_menipis'] ?? 0;

/* =====================
   TRANSAKSI
===================== */
$qTransaksi = mysqli_query($conn,"
  SELECT 
    COUNT(DISTINCT t.id) AS total_transaksi,
    SUM(d.qty * d.harga) AS omzet,
    AVG(d.qty * d.harga) AS rata_transaksi
  FROM transaksi t
  JOIN transaksi_detail d ON t.id=d.transaksi_id
  JOIN produk p ON d.produk_id=p.id
  WHERE p.penjual_id='$idPenjual'
    AND t.status='selesai'
");

if (!$qTransaksi) {
    die("Query transaksi error: " . mysqli_error($conn));
}

$trx = mysqli_fetch_assoc($qTransaksi);

$totalTransaksi = $trx['total_transaksi'] ?? 0;
$totalOmzet     = $trx['omzet'] ?? 0;
$rataTransaksi  = $trx['rata_transaksi'] ?? 0;

/* =====================
   TRANSAKSI HARI INI
===================== */
$qHariIni = mysqli_query($conn,"
  SELECT 
    COUNT(DISTINCT t.id) AS total,
    SUM(d.qty * d.harga) AS omzet
  FROM transaksi t
  JOIN transaksi_detail d ON t.id=d.transaksi_id
  JOIN produk p ON d.produk_id=p.id
  WHERE p.penjual_id='$idPenjual'
    AND t.status='selesai'
    AND DATE(t.created_at)=CURDATE()
");

if (!$qHariIni) {
    die("Query hari ini error: " . mysqli_error($conn));
}

$hari = mysqli_fetch_assoc($qHariIni);

$trxHariIni   = $hari['total'] ?? 0;
$omzetHariIni = $hari['omzet'] ?? 0;

/* =====================
   TRANSAKSI MINGGU INI
===================== */
$qMingguIni = mysqli_query($conn,"
  SELECT 
    COUNT(DISTINCT t.id) AS total,
    SUM(d.qty * d.harga) AS omzet
  FROM transaksi t
  JOIN transaksi_detail d ON t.id=d.transaksi_id
  JOIN produk p ON d.produk_id=p.id
  WHERE p.penjual_id='$idPenjual'
    AND t.status='selesai'
    AND YEARWEEK(t.created_at, 1)=YEARWEEK(CURDATE(), 1)
");

if (!$qMingguIni) {
    $trxMingguIni = 0;
    $omzetMingguIni = 0;
} else {
    $minggu = mysqli_fetch_assoc($qMingguIni);
    $trxMingguIni   = $minggu['total'] ?? 0;
    $omzetMingguIni = $minggu['omzet'] ?? 0;
}

/* =====================
   PESANAN DIPROSES
===================== */
$qPending = mysqli_query($conn,"
  SELECT COUNT(DISTINCT t.id) AS pending
  FROM transaksi t
  JOIN transaksi_detail d ON t.id=d.transaksi_id
  JOIN produk p ON d.produk_id=p.id
  WHERE p.penjual_id='$idPenjual'
    AND t.status!='selesai'
");

if (!$qPending) {
    $pending = 0;
} else {
    $pending = mysqli_fetch_assoc($qPending)['pending'] ?? 0;
}

/* =====================
   PRODUK TERLARIS
===================== */
$qTerlaris = mysqli_query($conn,"
  SELECT p.nama_buku AS nama, SUM(d.qty) AS total_terjual, p.gambar AS foto
  FROM produk p
  LEFT JOIN transaksi_detail d ON p.id=d.produk_id
  LEFT JOIN transaksi t ON d.transaksi_id=t.id AND t.status='selesai'
  WHERE p.penjual_id='$idPenjual'
  GROUP BY p.id
  ORDER BY total_terjual DESC
  LIMIT 5
");

$produkTerlaris = [];
if ($qTerlaris) {
    while ($row = mysqli_fetch_assoc($qTerlaris)) {
        $produkTerlaris[] = $row;
    }
}

/* =====================
   TRANSAKSI TERBARU
===================== */
$qTransaksiTerbaru = mysqli_query($conn,"
  SELECT t.id, t.id AS kode_transaksi, u.nama AS nama_pembeli, t.total AS total_harga, 
         t.status, t.created_at, COUNT(d.id) AS jumlah_item
  FROM transaksi t
  JOIN transaksi_detail d ON t.id=d.transaksi_id
  JOIN produk p ON d.produk_id=p.id
  JOIN users u ON t.pembeli_id = u.id
  WHERE p.penjual_id='$idPenjual'
  GROUP BY t.id
  ORDER BY t.created_at DESC
  LIMIT 5
");

$transaksiTerbaru = [];
if ($qTransaksiTerbaru) {
    while ($row = mysqli_fetch_assoc($qTransaksiTerbaru)) {
        $transaksiTerbaru[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Penjual - BOOKIE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
   <link rel="stylesheet" href="includes/sidebar.css">
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    /* CARD DARK */
    .card-dark {
      background-color: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .card-dark:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
    }
    
    .bg-primary { background-color: #4361ee !important; }
    .bg-success { background-color: #28a745 !important; }
    .bg-warning { background-color: #ffc107 !important; }
    .bg-danger { background-color: #dc3545 !important; }
    .bg-info { background-color: #17a2b8 !important; }
    
    .card-title {
      color: #6c757d;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .card-value {
      font-weight: 700;
      font-size: 1.8rem;
      color: #2d3748;
    }
    
    .product-img {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid #e0e0e0;
    }
    
    .status-badge {
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .welcome-card {
      background: linear-gradient(135deg, #495057 0%, #343a40 100%);
      color: white;
      border-radius: 10px;
      overflow: hidden;
    }
    
    .chart-container {
      background: white;
      border-radius: 10px;
      padding: 20px;
      border: 1px solid #e0e0e0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .table-responsive {
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #e0e0e0;
    }
    
    .table th {
      background-color: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      font-weight: 600;
      color: #495057;
      font-size: 0.9rem;
    }
    
    .table td {
      font-size: 0.9rem;
      vertical-align: middle;
    }
    
    /* HOVER EFFECTS */
    .list-group-item {
      border-left: 0;
      border-right: 0;
      transition: background-color 0.2s;
    }
    
    .list-group-item:hover {
      background-color: #f8f9fa;
    }
    
    /* PROGRESS BAR */
    .progress {
      height: 8px;
      border-radius: 4px;
      background-color: #e9ecef;
    }
    
    .progress-bar {
      border-radius: 4px;
    }
    
    .btn-dark {
      background-color: #343a40;
      border-color: #343a40;
    }
    
    .btn-dark:hover {
      background-color: #495057;
      border-color: #495057;
    }
    
    .btn-outline-dark:hover {
      background-color: #343a40;
      color: white;
    }
    
    .badge-position {
      position: absolute;
      top: -8px;
      right: -8px;
      font-size: 0.7rem;
      padding: 4px 8px;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <!-- INCLUDE SIDEBAR -->
    <?php include "includes/sidebar.php"; ?>

    <!-- CONTENT -->
    <div class="col-10 p-4">
      <!-- HEADER -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h3 class="fw-bold text-dark">Dashboard Penjual</h3>
          <p class="text-muted mb-0">Selamat datang kembali, <?= htmlspecialchars($namaPenjual) ?>! 👋</p>
        </div>
        <div>
          <span class="text-muted me-3"><?= date('l, d F Y') ?></span>
          <a href="produk.php?action=tambah" class="btn btn-dark">
            <i class="bi bi-plus-circle"></i> Tambah Produk
          </a>
        </div>
      </div>

      <!-- WELCOME CARD -->
      <div class="welcome-card p-4 mb-4">
        <div class="row align-items-center">
          <div class="col-9">
            <h4 class="fw-bold">Selalu di depan melayani kebutuhan pelanggan</h4>
            <p class="mb-0">Tingkatkan penjualan Anda dengan mengelola produk, stok, dan transaksi dengan mudah.</p>
          </div>
          <div class="col-3 text-end">
            <i class="bi bi-shop-window" style="font-size: 4rem; opacity: 0.7;"></i>
          </div>
        </div>
      </div>

      <!-- STATISTIK UTAMA -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card-dark shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <p class="card-title">Total Produk</p>
                  <h2 class="card-value"><?= $totalProduk ?></h2>
                  <?php if($totalProduk > 0): ?>
                  <small class="text-success">
                    <i class="bi bi-check-circle"></i> <?= $totalProduk ?> produk aktif
                  </small>
                  <?php else: ?>
                  <small class="text-danger">
                    <i class="bi bi-exclamation-circle"></i> Belum ada produk
                  </small>
                  <?php endif; ?>
                </div>
                <div class="stat-icon bg-primary">
                  <i class="bi bi-box"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card-dark shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <p class="card-title">Total Omzet</p>
                  <h2 class="card-value">Rp <?= number_format($totalOmzet,0,',','.') ?></h2>
                  <?php if($omzetHariIni > 0): ?>
                  <small class="text-success">
                    <i class="bi bi-arrow-up"></i> Rp <?= number_format($omzetHariIni,0,',','.') ?> hari ini
                  </small>
                  <?php else: ?>
                  <small class="text-muted">
                    <i class="bi bi-dash-circle"></i> Belum ada transaksi hari ini
                  </small>
                  <?php endif; ?>
                </div>
                <div class="stat-icon bg-success">
                  <i class="bi bi-cash-stack"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card-dark shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <p class="card-title">Pesanan Diproses</p>
                  <h2 class="card-value"><?= $pending ?></h2>
                  <?php if($pending > 0): ?>
                  <small class="text-warning">
                    <i class="bi bi-clock"></i> Perlu tindakan
                  </small>
                  <?php else: ?>
                  <small class="text-success">
                    <i class="bi bi-check-circle"></i> Semua transaksi selesai
                  </small>
                  <?php endif; ?>
                </div>
                <div class="stat-icon bg-warning">
                  <i class="bi bi-hourglass-split"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card-dark shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <p class="card-title">Produk Habis</p>
                  <h2 class="card-value"><?= $stokHabis ?></h2>
                  <?php if($stokHabis > 0): ?>
                  <small class="text-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= $stokHabis ?> produk habis
                  </small>
                  <?php elseif($stokMenipis > 0): ?>
                  <small class="text-warning">
                    <i class="bi bi-exclamation-triangle"></i> <?= $stokMenipis ?> produk menipis
                  </small>
                  <?php else: ?>
                  <small class="text-success">
                    <i class="bi bi-check-circle"></i> Stok aman
                  </small>
                  <?php endif; ?>
                </div>
                <div class="stat-icon bg-danger">
                  <i class="bi bi-exclamation-octagon"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RINGKASAN TRANSAKSI -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card-dark shadow-sm h-100">
            <div class="card-body">
              <h6 class="card-title mb-3">Transaksi Hari Ini</h6>
              <div class="d-flex align-items-center mb-3">
                <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                  <i class="bi bi-cart text-primary" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                  <h3 class="mb-0"><?= $trxHariIni ?></h3>
                  <small>Transaksi</small>
                </div>
              </div>
              <div class="d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                  <i class="bi bi-currency-dollar text-success" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                  <h5 class="mb-0">Rp <?= number_format($omzetHariIni,0,',','.') ?></h5>
                  <small>Omzet hari ini</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card-dark shadow-sm h-100">
            <div class="card-body">
              <h6 class="card-title mb-3">Rata-rata Transaksi</h6>
              <div class="text-center py-4">
                <h1 class="display-5 fw-bold text-primary">Rp <?= number_format($rataTransaksi,0,',','.') ?></h1>
                <p class="text-muted">Per transaksi selesai</p>
              </div>
              <?php if($totalTransaksi > 0): ?>
              <div class="progress" style="height: 8px;">
                <div class="progress-bar bg-success" style="width: 75%"></div>
              </div>
              <small class="text-muted"><?= $totalTransaksi ?> transaksi selesai</small>
              <?php else: ?>
              <div class="alert alert-light mt-3 mb-0">
                <i class="bi bi-info-circle"></i> Belum ada transaksi selesai
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card-dark shadow-sm h-100">
            <div class="card-body">
              <h6 class="card-title mb-3">Transaksi Minggu Ini</h6>
              <div class="d-flex align-items-center mb-3">
                <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                  <i class="bi bi-calendar-week text-info" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                  <h3 class="mb-0"><?= $trxMingguIni ?></h3>
                  <small>Transaksi</small>
                </div>
              </div>
              <div class="d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                  <i class="bi bi-graph-up-arrow text-warning" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                  <h5 class="mb-0">Rp <?= number_format($omzetMingguIni,0,',','.') ?></h5>
                  <small>Omzet minggu ini</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TABEL TRANSAKSI TERBARU & PRODUK TERLARIS -->
      <div class="row g-3">
        <div class="col-md-8">
          <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h5 class="fw-bold mb-0">Transaksi Terbaru</h5>
              <a href="transaksi.php" class="btn btn-sm btn-outline-dark">Lihat Semua</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>ID Transaksi</th>
                    <th>Pembeli</th>
                    <th>Tanggal</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(count($transaksiTerbaru) > 0): ?>
                    <?php foreach($transaksiTerbaru as $transaksi): ?>
                    <tr>
                      <td class="fw-semibold">#<?= htmlspecialchars($transaksi['kode_transaksi']) ?></td>
                      <td><?= htmlspecialchars($transaksi['nama_pembeli']) ?></td>
                      <td><?= date('d/m/Y', strtotime($transaksi['created_at'])) ?></td>
                      <td>Rp <?= number_format($transaksi['total_harga'],0,',','.') ?></td>
                      <td>
                        <?php
                          $statusClass = 'bg-secondary';
                          if($transaksi['status'] == 'selesai') $statusClass = 'bg-success';
                          if($transaksi['status'] == 'diproses') $statusClass = 'bg-warning';
                          if($transaksi['status'] == 'dibatalkan') $statusClass = 'bg-danger';
                        ?>
                        <span class="status-badge <?= $statusClass ?> text-white">
                          <?= ucfirst($transaksi['status']) ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-receipt" style="font-size: 2rem;"></i>
                        <p class="mt-2">Belum ada transaksi</p>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- PRODUK TERLARIS -->
        <div class="col-md-4">
          <div class="chart-container h-100">
            <h5 class="fw-bold mb-4">Produk Terlaris</h5>
            <div class="list-group">
              <?php if(count($produkTerlaris) > 0): ?>
                <?php foreach($produkTerlaris as $index => $produk): ?>
                <div class="list-group-item border-0 px-0 py-3">
                  <div class="d-flex align-items-center">
                    <div class="position-relative">
                      <img src="<?= !empty($produk['foto']) ? '../uploads/'.$produk['foto'] : '../assets/img/product-placeholder.png' ?>" 
                           class="product-img me-3" 
                           alt="<?= htmlspecialchars($produk['nama']) ?>"
                           onerror="this.src='../assets/img/product-placeholder.png'">
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark badge-position">
                        <?= $index + 1 ?>
                      </span>
                    </div>
                    <div class="flex-grow-1">
                      <h6 class="mb-1"><?= htmlspecialchars(mb_strimwidth($produk['nama'], 0, 25, '...')) ?></h6>
                      <small class="text-muted">Terjual: <?= $produk['total_terjual'] ?? 0 ?> item</small>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-trophy" style="font-size: 2rem;"></i>
                  <p class="mt-2">Belum ada data penjualan</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Update waktu
  function updateTime() {
    const now = new Date();
    const options = { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    };
    const dateString = now.toLocaleDateString('id-ID', options);
    document.querySelector('.text-muted.me-3').textContent = dateString;
  }
  
  updateTime();
  setInterval(updateTime, 60000);
  
  // Animasi hover card
  document.querySelectorAll('.card-dark').forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
</script>

</body>
</html>