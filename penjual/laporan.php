<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Data penjual sudah diambil di sidebar.php
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';

/* =====================
   FILTER BULAN & TAHUN
===================== */
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Generate pilihan tahun (3 tahun terakhir)
$tahun_sekarang = date('Y');
$tahun_options = [];
for ($i = 0; $i < 3; $i++) {
  $tahun_options[] = $tahun_sekarang - $i;
}

// Nama bulan
$nama_bulan = [
  '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
  '04' => 'April', '05' => 'Mei', '06' => 'Juni',
  '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
  '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

/* =====================
   STATISTIK RINGKASAN
===================== */
// Total transaksi bulan ini
$q_total_transaksi = mysqli_query($conn, "
  SELECT COUNT(*) as total FROM transaksi 
  WHERE penjual_id='$idPenjual' 
  AND MONTH(created_at) = '$bulan' 
  AND YEAR(created_at) = '$tahun'
  AND status IN ('approve', 'selesai')
");
$total_transaksi = mysqli_fetch_assoc($q_total_transaksi)['total'] ?? 0;

// Total pendapatan bulan ini
$q_total_pendapatan = mysqli_query($conn, "
  SELECT SUM(total) as total FROM transaksi 
  WHERE penjual_id='$idPenjual' 
  AND MONTH(created_at) = '$bulan' 
  AND YEAR(created_at) = '$tahun'
  AND status IN ('approve', 'selesai')
");
$total_pendapatan = mysqli_fetch_assoc($q_total_pendapatan)['total'] ?? 0;

// Total produk terjual bulan ini (dari transaksi_detail)
$q_total_produk_terjual = mysqli_query($conn, "
  SELECT SUM(td.qty) as total 
  FROM transaksi_detail td
  JOIN transaksi t ON td.transaksi_id = t.id
  WHERE t.penjual_id = '$idPenjual'
  AND MONTH(t.created_at) = '$bulan'
  AND YEAR(t.created_at) = '$tahun'
  AND t.status IN ('approve', 'selesai')
");
$total_produk_terjual = mysqli_fetch_assoc($q_total_produk_terjual)['total'] ?? 0;

// Transaksi tertinggi
$q_transaksi_tertinggi = mysqli_query($conn, "
  SELECT MAX(total) as max FROM transaksi 
  WHERE penjual_id='$idPenjual' 
  AND MONTH(created_at) = '$bulan' 
  AND YEAR(created_at) = '$tahun'
  AND status IN ('approve', 'selesai')
");
$transaksi_tertinggi = mysqli_fetch_assoc($q_transaksi_tertinggi)['max'] ?? 0;

/* =====================
   DATA UNTUK GRAFIK (30 hari terakhir)
===================== */
$grafik_data = [];
$labels = [];
$margin_data = [];
$keuntungan_data = [];

for ($i = 29; $i >= 0; $i--) {
  $tanggal = date('Y-m-d', strtotime("-$i days"));
  $label = date('d M', strtotime("-$i days"));
  
  // Query untuk menghitung margin dan keuntungan per hari
  $query_grafik = mysqli_query($conn, "
    SELECT 
      SUM((td.harga - p.modal) * td.qty) as total_margin,
      SUM((td.harga - p.modal) * td.qty) as total_keuntungan
    FROM transaksi t
    JOIN transaksi_detail td ON t.id = td.transaksi_id
    JOIN produk p ON td.produk_id = p.id
    WHERE t.penjual_id = '$idPenjual'
    AND DATE(t.created_at) = '$tanggal'
    AND t.status IN ('approve', 'selesai')
  ");
  
  $data = mysqli_fetch_assoc($query_grafik);
  
  $labels[] = $label;
  $margin_data[] = $data['total_margin'] ?? 0;
  $keuntungan_data[] = $data['total_keuntungan'] ?? 0;
}

/* =====================
   DATA TRANSAKSI PER BULAN
===================== */
$query = "
  SELECT 
    t.*,
    u.nama as nama_pembeli,
    DATE_FORMAT(t.created_at, '%d %M %Y') as tanggal_format,
    (SELECT GROUP_CONCAT(p.nama_buku SEPARATOR ', ') 
     FROM transaksi_detail td
     JOIN produk p ON td.produk_id = p.id
     WHERE td.transaksi_id = t.id) as produk_list
  FROM transaksi t
  JOIN users u ON t.pembeli_id = u.id
  WHERE t.penjual_id = '$idPenjual'
  AND MONTH(t.created_at) = '$bulan'
  AND YEAR(t.created_at) = '$tahun'
  ORDER BY t.created_at DESC
";

$qTransaksi = mysqli_query($conn, $query);

/* =====================
   DATA UNTUK GRAFIK BULANAN
===================== */
$bulanan_labels = [];
$bulanan_pendapatan = [];

for ($i = 1; $i <= 12; $i++) {
  $bulan_angka = str_pad($i, 2, '0', STR_PAD_LEFT);
  $bulanan_labels[] = $nama_bulan[$bulan_angka];
  
  $query_bulanan = mysqli_query($conn, "
    SELECT SUM(total) as total FROM transaksi 
    WHERE penjual_id='$idPenjual' 
    AND MONTH(created_at) = '$bulan_angka' 
    AND YEAR(created_at) = '$tahun'
    AND status IN ('approve', 'selesai')
  ");
  
  $data_bulanan = mysqli_fetch_assoc($query_bulanan);
  $bulanan_pendapatan[] = $data_bulanan['total'] ?? 0;
}

/* =====================
   TOP 5 PRODUK TERLARIS
===================== */
$q_top_produk = mysqli_query($conn, "
  SELECT 
    p.nama_buku,
    SUM(td.qty) as total_terjual,
    SUM(td.qty * td.harga) as total_pendapatan
  FROM transaksi_detail td
  JOIN produk p ON td.produk_id = p.id
  JOIN transaksi t ON td.transaksi_id = t.id
  WHERE t.penjual_id = '$idPenjual'
  AND MONTH(t.created_at) = '$bulan'
  AND YEAR(t.created_at) = '$tahun'
  AND t.status IN ('approve', 'selesai')
  GROUP BY p.id
  ORDER BY total_terjual DESC
  LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Penjualan - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* CSS khusus untuk halaman laporan */
.stat-card {
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
  color: white;
  transition: transform 0.3s;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 15px rgba(0,0,0,0.2);
}
.stat-card.total { background: linear-gradient(45deg, #3498db, #2980b9); }
.stat-card.pendapatan { background: linear-gradient(45deg, #2ecc71, #27ae60); }
.stat-card.produk { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
.stat-card.tertinggi { background: linear-gradient(45deg, #e74c3c, #c0392b); }
.chart-container {
  background: white;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  border: 1px solid #e0e0e0;
}
.top-product-card {
  border-left: 4px solid #3498db;
  padding: 15px;
  margin-bottom: 10px;
  background: #f8f9fa;
  border-radius: 0 8px 8px 0;
  transition: background-color 0.3s;
}
.top-product-card:hover {
  background-color: #e9ecef;
}
.badge-custom {
  font-size: 0.8rem;
  padding: 4px 10px;
}
.filter-card {
  background: white;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  border: 1px solid #dee2e6;
}
.table-custom th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
}
.table-custom tr:hover {
  background-color: #f8f9fa;
}
.progress-thin {
  height: 5px;
  border-radius: 3px;
}
.resi-link {
  font-family: monospace;
  font-size: 0.85rem;
}
.download-btn {
  background: linear-gradient(45deg, #27ae60, #229954);
  border: none;
  color: white;
  padding: 8px 20px;
  border-radius: 5px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: transform 0.3s;
}
.download-btn:hover {
  transform: translateY(-2px);
  color: white;
  background: linear-gradient(45deg, #229954, #1e8449);
}
.empty-state {
  padding: 60px 20px;
  text-align: center;
  color: #6c757d;
}
.empty-state i {
  font-size: 4rem;
  opacity: 0.5;
  margin-bottom: 20px;
}
</style>
</head>

<body class="bg-light">
<div class="container-fluid">
<div class="row">
    
<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- CONTENT -->
<div class="col-10 p-4">
<h3 class="mb-3">Laporan Penjualan</h3>
<p class="text-muted mb-4">Analisis performa penjualan Anda secara detail</p>

<!-- FILTER -->
<div class="filter-card mb-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="mb-0"><i class="bi bi-funnel me-2"></i> Filter Laporan</h5>
<a href="laporan_download.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" 
   class="download-btn">
  <i class="bi bi-file-earmark-pdf"></i> Download PDF
</a>
</div>
<form method="GET" class="row g-3 align-items-center">
<div class="col-md-5">
<label class="form-label fw-semibold">Bulan</label>
<select name="bulan" class="form-select" style="border-radius: 8px;">
<?php foreach($nama_bulan as $key => $value): ?>
<option value="<?= $key ?>" <?= $bulan == $key ? 'selected' : '' ?>>
<?= $value ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-5">
<label class="form-label fw-semibold">Tahun</label>
<select name="tahun" class="form-select" style="border-radius: 8px;">
<?php foreach($tahun_options as $thn): ?>
<option value="<?= $thn ?>" <?= $tahun == $thn ? 'selected' : '' ?>>
<?= $thn ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<button type="submit" class="btn btn-dark mt-4" style="padding: 10px 25px; border-radius: 8px;">
  <i class="bi bi-funnel me-2"></i> Terapkan
</button>
</div>
</form>
</div>

<!-- STATISTIK RINGKASAN -->
<div class="row mb-4">
<div class="col-md-3">
<div class="stat-card total">
<div class="d-flex justify-content-between align-items-center">
  <div>
    <h6 class="mb-2">Total Transaksi</h6>
    <h3 class="mb-1"><?= number_format($total_transaksi) ?></h3>
    <small class="opacity-75"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></small>
  </div>
  <i class="bi bi-receipt" style="font-size: 2.5rem; opacity: 0.8;"></i>
</div>
<div class="mt-3">
  <div class="progress progress-thin">
    <?php 
    $max_transaksi = max(1, $total_transaksi);
    $width = min(100, ($total_transaksi / $max_transaksi) * 100); 
    ?>
    <div class="progress-bar bg-white opacity-75" style="width: <?= $width ?>%"></div>
  </div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card pendapatan">
<div class="d-flex justify-content-between align-items-center">
  <div>
    <h6 class="mb-2">Total Pendapatan</h6>
    <h3 class="mb-1">Rp <?= number_format($total_pendapatan) ?></h3>
    <small class="opacity-75"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></small>
  </div>
  <i class="bi bi-cash-stack" style="font-size: 2.5rem; opacity: 0.8;"></i>
</div>
<div class="mt-3">
  <div class="progress progress-thin">
    <?php 
    $max_pendapatan = max(1, $total_pendapatan);
    $width = min(100, ($total_pendapatan / $max_pendapatan) * 100); 
    ?>
    <div class="progress-bar bg-white opacity-75" style="width: <?= $width ?>%"></div>
  </div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card produk">
<div class="d-flex justify-content-between align-items-center">
  <div>
    <h6 class="mb-2">Produk Terjual</h6>
    <h3 class="mb-1"><?= number_format($total_produk_terjual) ?></h3>
    <small class="opacity-75"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></small>
  </div>
  <i class="bi bi-box-seam" style="font-size: 2.5rem; opacity: 0.8;"></i>
</div>
<div class="mt-3">
  <div class="progress progress-thin">
    <?php 
    $max_produk = max(1, $total_produk_terjual);
    $width = min(100, ($total_produk_terjual / $max_produk) * 100); 
    ?>
    <div class="progress-bar bg-white opacity-75" style="width: <?= $width ?>%"></div>
  </div>
</div>
</div>
</div>

<div class="col-md-3">
<div class="stat-card tertinggi">
<div class="d-flex justify-content-between align-items-center">
  <div>
    <h6 class="mb-2">Transaksi Tertinggi</h6>
    <h3 class="mb-1">Rp <?= number_format($transaksi_tertinggi) ?></h3>
    <small class="opacity-75"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></small>
  </div>
  <i class="bi bi-trophy" style="font-size: 2.5rem; opacity: 0.8;"></i>
</div>
<div class="mt-3">
  <div class="progress progress-thin">
    <?php 
    $max_tertinggi = max(1, $transaksi_tertinggi);
    $width = min(100, ($transaksi_tertinggi / $max_tertinggi) * 100); 
    ?>
    <div class="progress-bar bg-white opacity-75" style="width: <?= $width ?>%"></div>
  </div>
</div>
</div>
</div>
</div>

<!-- ROW GRAFIK & TOP PRODUK -->
<div class="row mb-4">
<!-- GRAFIK 30 HARI TERAKHIR -->
<div class="col-md-8">
<div class="chart-container">
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i> Grafik Margin & Keuntungan (30 Hari Terakhir)</h5>
  <span class="badge bg-primary"><?= count($labels) ?> hari</span>
</div>
<canvas id="grafikHarian"></canvas>
</div>
</div>

<!-- TOP 5 PRODUK TERLARIS -->
<div class="col-md-4">
<div class="chart-container">
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0"><i class="bi bi-star-fill me-2"></i> Top 5 Produk Terlaris</h5>
  <span class="badge bg-success"><?= $nama_bulan[$bulan] ?></span>
</div>
<?php if(mysqli_num_rows($q_top_produk) > 0): ?>
  <?php 
  $top_products = [];
  while($produk = mysqli_fetch_assoc($q_top_produk)) {
    $top_products[] = $produk;
  }
  
  // Cari nilai maksimal untuk progress bar
  $max_terjual = 0;
  foreach($top_products as $p) {
    if($p['total_terjual'] > $max_terjual) {
      $max_terjual = $p['total_terjual'];
    }
  }
  
  $rank = 1;
  foreach($top_products as $produk): 
  ?>
  <div class="top-product-card">
    <div class="d-flex justify-content-between align-items-start">
      <div class="flex-grow-1">
        <div class="d-flex align-items-center mb-2">
          <span class="badge bg-primary me-2">#<?= $rank++ ?></span>
          <h6 class="mb-0"><?= htmlspecialchars(mb_strimwidth($produk['nama_buku'], 0, 25, '...')) ?></h6>
        </div>
        <small class="text-muted d-block mb-2">
          <i class="bi bi-cart3 me-1"></i> Terjual: <?= number_format($produk['total_terjual']) ?> pcs
        </small>
      </div>
      <div class="text-end">
        <strong class="text-success">Rp <?= number_format($produk['total_pendapatan']) ?></strong>
      </div>
    </div>
    <?php if($max_terjual > 0): ?>
    <div class="progress progress-thin mt-2">
      <?php $persentase = ($produk['total_terjual'] / $max_terjual) * 100; ?>
      <div class="progress-bar bg-success" style="width: <?= $persentase ?>%"></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="empty-state">
    <i class="bi bi-box"></i>
    <p class="mt-3">Belum ada data penjualan produk</p>
  </div>
<?php endif; ?>
</div>
</div>
</div>

<!-- GRAFIK BULANAN -->
<div class="chart-container mb-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i> Grafik Pendapatan Tahunan <?= $tahun ?></h5>
<span class="badge bg-info">12 bulan</span>
</div>
<canvas id="grafikBulanan"></canvas>
</div>

<!-- TABEL TRANSAKSI -->
<div class="chart-container">
<div class="d-flex justify-content-between align-items-center mb-4">
<h5 class="mb-0">
  <i class="bi bi-list-ul me-2"></i> Detail Transaksi <?= $nama_bulan[$bulan] ?> <?= $tahun ?>
</h5>
<span class="badge bg-dark"><?= mysqli_num_rows($qTransaksi) ?> transaksi</span>
</div>

<?php if(mysqli_num_rows($qTransaksi) > 0): ?>
<div class="table-responsive">
<table class="table table-custom table-hover">
<thead>
<tr>
<th>ID Transaksi</th>
<th>Pembeli</th>
<th>Tanggal</th>
<th>Produk</th>
<th>Total</th>
<th>Status</th>
<th>No. Resi</th>
</tr>
</thead>
<tbody>
<?php while($t = mysqli_fetch_assoc($qTransaksi)): ?>
<tr>
<td class="fw-semibold">#<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($t['nama_pembeli']) ?></td>
<td><?= $t['tanggal_format'] ?></td>
<td>
  <small><?= $t['produk_list'] ? mb_strimwidth($t['produk_list'], 0, 50, '...') : '-' ?></small>
</td>
<td class="fw-semibold">Rp <?= number_format($t['total']) ?></td>
<td>
  <?php
  $badge_class = [
    'menunggu' => 'warning',
    'approve' => 'info',
    'tolak' => 'danger',
    'refund' => 'secondary',
    'selesai' => 'success'
  ][$t['status']] ?? 'secondary';
  ?>
  <span class="badge badge-custom bg-<?= $badge_class ?>">
    <?= strtoupper($t['status']) ?>
  </span>
</td>
<td>
  <?php if($t['no_resi']): ?>
    <a href="https://www.jne.co.id/tracking?q=<?= $t['no_resi'] ?>" 
       target="_blank" 
       class="resi-link text-decoration-none">
      <?= $t['no_resi'] ?>
    </a>
  <?php else: ?>
    <span class="text-muted">-</span>
  <?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
  <i class="bi bi-receipt"></i>
  <h5 class="text-muted mt-3">Tidak ada transaksi</h5>
  <p>Tidak ada transaksi pada <?= $nama_bulan[$bulan] ?> <?= $tahun ?></p>
</div>
<?php endif; ?>
</div>

</div>
</div>
</div>

<!-- CHART JS SCRIPT -->
<script>
// Grafik Harian (30 hari)
const ctxHarian = document.getElementById('grafikHarian').getContext('2d');
const grafikHarian = new Chart(ctxHarian, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      {
        label: 'Margin',
        data: <?= json_encode($margin_data) ?>,
        borderColor: '#3498db',
        backgroundColor: 'rgba(52, 152, 219, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#3498db',
        pointRadius: 4
      },
      {
        label: 'Keuntungan',
        data: <?= json_encode($keuntungan_data) ?>,
        borderColor: '#2ecc71',
        backgroundColor: 'rgba(46, 204, 113, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#2ecc71',
        pointRadius: 4
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: {
            size: 14
          }
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `${context.dataset.label}: Rp ${context.raw.toLocaleString('id-ID')}`;
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
          color: 'rgba(0,0,0,0.05)'
        }
      },
      x: {
        grid: {
          color: 'rgba(0,0,0,0.05)'
        }
      }
    }
  }
});

// Grafik Bulanan
const ctxBulanan = document.getElementById('grafikBulanan').getContext('2d');
const grafikBulanan = new Chart(ctxBulanan, {
  type: 'bar',
  data: {
    labels: <?= json_encode($bulanan_labels) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode($bulanan_pendapatan) ?>,
      backgroundColor: [
        '#3498db', '#2ecc71', '#9b59b6', '#e74c3c',
        '#f1c40f', '#1abc9c', '#34495e', '#d35400',
        '#7f8c8d', '#27ae60', '#8e44ad', '#c0392b'
      ].map(color => color + 'CC'),
      borderColor: '#fff',
      borderWidth: 2,
      borderRadius: 5,
      hoverBackgroundColor: [
        '#2980b9', '#27ae60', '#8e44ad', '#c0392b',
        '#f39c12', '#16a085', '#2c3e50', '#a35200',
        '#95a5a6', '#229954', '#7d3c98', '#a93226'
      ]
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `Pendapatan: Rp ${context.raw.toLocaleString('id-ID')}`;
          },
          title: function(context) {
            return context[0].label;
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
          color: 'rgba(0,0,0,0.05)'
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>