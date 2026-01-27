<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

$nama_bulan = [
  '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
  '04' => 'April', '05' => 'Mei', '06' => 'Juni',
  '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
  '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Data penjual
$user = mysqli_fetch_assoc(
  mysqli_query($conn,"SELECT * FROM users WHERE id='$idPenjual'")
);

// Data transaksi
$query = "
  SELECT 
    t.*,
    u.nama as nama_pembeli,
    DATE_FORMAT(t.created_at, '%d %M %Y %H:%i') as tanggal_format
  FROM transaksi t
  JOIN users u ON t.pembeli_id = u.id
  WHERE t.penjual_id = '$idPenjual'
  AND MONTH(t.created_at) = '$bulan'
  AND YEAR(t.created_at) = '$tahun'
  ORDER BY t.created_at DESC
";
$qTransaksi = mysqli_query($conn, $query);

// Statistik
$q_stat = mysqli_query($conn, "
  SELECT 
    COUNT(*) as total_transaksi,
    SUM(total) as total_pendapatan,
    AVG(total) as rata_rata
  FROM transaksi 
  WHERE penjual_id='$idPenjual' 
  AND MONTH(created_at) = '$bulan' 
  AND YEAR(created_at) = '$tahun'
  AND status IN ('approve', 'selesai')
");
$statistik = mysqli_fetch_assoc($q_stat);

// Transaksi tertinggi
$q_max = mysqli_query($conn, "
  SELECT MAX(total) as max FROM transaksi 
  WHERE penjual_id='$idPenjual' 
  AND MONTH(created_at) = '$bulan' 
  AND YEAR(created_at) = '$tahun'
  AND status IN ('approve', 'selesai')
");
$transaksi_tertinggi = mysqli_fetch_assoc($q_max)['max'] ?? 0;

// Data untuk grafik bulanan
$bulanan_labels = [];
$bulanan_pendapatan = [];

for ($i = 1; $i <= 12; $i++) {
  $bulan_angka = str_pad($i, 2, '0', STR_PAD_LEFT);
  $bulanan_labels[] = substr($nama_bulan[$bulan_angka], 0, 3); // Ambil 3 huruf pertama
  
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

// Generate HTML untuk PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Laporan Penjualan <?= $nama_bulan[$bulan] ?> <?= $tahun ?></title>
<style>
/* DomPDF mendukung sebagian besar CSS2 */
body {
  font-family: DejaVu Sans, Arial, sans-serif;
  font-size: 12px;
  line-height: 1.4;
  margin: 0;
  padding: 0;
}

.header {
  text-align: center;
  margin-bottom: 20px;
  border-bottom: 2px solid #333;
  padding-bottom: 10px;
}

.header h1 {
  margin: 0;
  font-size: 24px;
  color: #2c3e50;
}

.header h2 {
  margin: 5px 0;
  font-size: 16px;
  color: #7f8c8d;
}

.header h3 {
  margin: 5px 0;
  font-size: 14px;
  color: #34495e;
}

.info-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
}

.info-table td {
  padding: 8px;
  vertical-align: top;
  border: 1px solid #ddd;
}

.stats-container {
  display: table;
  width: 100%;
  margin-bottom: 20px;
}

.stat-box {
  display: table-cell;
  width: 25%;
  padding: 15px;
  text-align: center;
  border: 1px solid #ddd;
  border-radius: 5px;
  background: #f9f9f9;
  vertical-align: middle;
}

.stat-value {
  font-size: 18px;
  font-weight: bold;
  color: #2c3e50;
  display: block;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 11px;
  color: #7f8c8d;
  display: block;
}

.chart-container {
  margin: 20px 0;
  text-align: center;
}

.chart-title {
  font-size: 14px;
  font-weight: bold;
  margin-bottom: 10px;
  color: #34495e;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  font-size: 10px;
}

.table th {
  background-color: #34495e;
  color: white;
  padding: 8px;
  text-align: left;
  border: 1px solid #ddd;
  font-weight: bold;
}

.table td {
  padding: 6px;
  border: 1px solid #ddd;
}

.table tr:nth-child(even) {
  background-color: #f8f9fa;
}

.total-row {
  font-weight: bold;
  background-color: #e3f2fd !important;
}

.footer {
  margin-top: 30px;
  text-align: center;
  font-size: 10px;
  color: #666;
  border-top: 1px solid #ddd;
  padding-top: 10px;
}

.status-badge {
  padding: 3px 8px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: bold;
  display: inline-block;
}

.status-selesai { background-color: #d4edda; color: #155724; }
.status-approve { background-color: #d1ecf1; color: #0c5460; }
.status-menunggu { background-color: #fff3cd; color: #856404; }
.status-tolak { background-color: #f8d7da; color: #721c24; }
.status-refund { background-color: #f8d7da; color: #721c24; }

.page-break {
  page-break-before: always;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.bold {
  font-weight: bold;
}

.mt-20 {
  margin-top: 20px;
}

.mb-20 {
  margin-bottom: 20px;
}

/* Simple bar chart menggunakan tabel */
.bar-chart {
  width: 100%;
  height: 200px;
  position: relative;
  border-left: 2px solid #333;
  border-bottom: 2px solid #333;
  margin: 20px 0;
}

.bar {
  position: absolute;
  bottom: 0;
  background: linear-gradient(to top, #3498db, #2980b9);
  width: 30px;
  margin: 0 10px;
  border-radius: 3px 3px 0 0;
}

.bar-label {
  position: absolute;
  bottom: -25px;
  width: 50px;
  text-align: center;
  font-size: 10px;
}

.bar-value {
  position: absolute;
  top: -20px;
  width: 50px;
  text-align: center;
  font-size: 9px;
  font-weight: bold;
}
</style>
</head>
<body>

<div class="header">
<h1>LAPORAN PENJUALAN</h1>
<h2>BOOKIE - Toko Buku Online</h2>
<h3>Periode: <?= $nama_bulan[$bulan] ?> <?= $tahun ?></h3>
</div>

<table class="info-table">
<tr>
<td width="50%">
  <strong>Penjual:</strong><br>
  <?= htmlspecialchars($user['nama']) ?><br>
  <?= $user['email'] ?><br>
  <?= $user['no_hp'] ?: '-' ?>
</td>
<td width="50%">
  <strong>Tanggal Cetak:</strong> <?= date('d/m/Y H:i:s') ?><br>
  <strong>Total Transaksi:</strong> <?= number_format($statistik['total_transaksi'] ?? 0) ?><br>
  <strong>Total Pendapatan:</strong> Rp <?= number_format($statistik['total_pendapatan'] ?? 0) ?>
</td>
</tr>
</table>

<div class="stats-container">
<div class="stat-box">
  <span class="stat-value"><?= number_format($statistik['total_transaksi'] ?? 0) ?></span>
  <span class="stat-label">Total Transaksi</span>
</div>
<div class="stat-box">
  <span class="stat-value">Rp <?= number_format($statistik['total_pendapatan'] ?? 0) ?></span>
  <span class="stat-label">Total Pendapatan</span>
</div>
<div class="stat-box">
  <span class="stat-value">Rp <?= number_format($statistik['rata_rata'] ?? 0) ?></span>
  <span class="stat-label">Rata-rata Transaksi</span>
</div>
<div class="stat-box">
  <span class="stat-value">Rp <?= number_format($transaksi_tertinggi) ?></span>
  <span class="stat-label">Transaksi Tertinggi</span>
</div>
</div>

<!-- Grafik Sederhana -->
<div class="chart-container">
<div class="chart-title">Grafik Pendapatan Tahunan <?= $tahun ?></div>
<div class="bar-chart">
  <?php
  $max_pendapatan = max($bulanan_pendapatan) > 0 ? max($bulanan_pendapatan) : 1;
  $chart_height = 180;
  $bar_width = 30;
  $spacing = 20;
  $total_width = (count($bulanan_labels) * ($bar_width + $spacing)) - $spacing;
  $start_x = (100 - ($total_width / 600 * 100)) / 2;
  
  for ($i = 0; $i < count($bulanan_labels); $i++):
    $height = ($bulanan_pendapatan[$i] / $max_pendapatan) * $chart_height;
    $left = $start_x + ($i * ($bar_width + $spacing));
    $value = $bulanan_pendapatan[$i] > 0 ? 'Rp ' . number_format($bulanan_pendapatan[$i] / 1000000, 1) . 'Jt' : 'Rp 0';
  ?>
  <div class="bar" style="left: <?= $left ?>%; height: <?= $height ?>px;"></div>
  <div class="bar-label" style="left: <?= $left - 10 ?>%;"><?= $bulanan_labels[$i] ?></div>
  <div class="bar-value" style="left: <?= $left - 10 ?>%;"><?= $value ?></div>
  <?php endfor; ?>
</div>
</div>

<h3>Detail Transaksi</h3>
<table class="table">
<thead>
<tr>
<th width="5%">No</th>
<th width="10%">ID Transaksi</th>
<th width="20%">Pembeli</th>
<th width="15%">Tanggal</th>
<th width="15%">Total</th>
<th width="10%">Status</th>
<th width="15%">No. Resi</th>
</tr>
</thead>
<tbody>
<?php 
$no = 1;
$total_keseluruhan = 0;
mysqli_data_seek($qTransaksi, 0);
while($t = mysqli_fetch_assoc($qTransaksi)): 
  $total_keseluruhan += $t['total'];
?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td class="text-center">#<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></td>
<td><?= htmlspecialchars($t['nama_pembeli']) ?></td>
<td><?= $t['tanggal_format'] ?></td>
<td class="text-right">Rp <?= number_format($t['total']) ?></td>
<td class="text-center">
  <span class="status-badge status-<?= $t['status'] ?>">
    <?= strtoupper($t['status']) ?>
  </span>
</td>
<td class="text-center"><?= $t['no_resi'] ?: '-' ?></td>
</tr>
<?php endwhile; ?>
<tr class="total-row">
<td colspan="4" class="text-right bold">TOTAL KESELURUHAN:</td>
<td colspan="3" class="bold">Rp <?= number_format($total_keseluruhan) ?></td>
</tr>
</tbody>
</table>

<div class="footer">
<p>Laporan ini dicetak secara otomatis dari sistem BOOKIE</p>
<p>© <?= date('Y') ?> BOOKIE - Toko Buku Online | www.bookie.com</p>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// Load DomPDF
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Setup DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Untuk load gambar eksternal jika ada
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

// Load HTML
$dompdf->loadHtml($html);

// Setup paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// Output PDF untuk download
$filename = 'Laporan_Penjualan_' . $nama_bulan[$bulan] . '_' . $tahun . '.pdf';
$dompdf->stream($filename, array("Attachment" => true));

exit;