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
   ACTION HANDLER
===================== */
if (isset($_POST['aksi'])) {
  $transaksi_id = $_POST['transaksi_id'] ?? 0;
  
  if ($_POST['aksi'] == 'approve') {
    mysqli_query($conn, "UPDATE transaksi SET status='approve' WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
    
  } elseif ($_POST['aksi'] == 'tolak') {
    mysqli_query($conn, "UPDATE transaksi SET status='tolak' WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
    
  } elseif ($_POST['aksi'] == 'selesai') {
    mysqli_query($conn, "UPDATE transaksi SET status='selesai' WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
    
  } elseif ($_POST['aksi'] == 'input_resi') {
    $no_resi = mysqli_real_escape_string($conn, $_POST['no_resi']);
    mysqli_query($conn, "UPDATE transaksi SET no_resi='$no_resi', status='selesai' WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
  }
  
  header("Location: transaksi.php");
  exit;
}

/* =====================
   DELETE HANDLER (dengan delay 1 menit)
===================== */
if (isset($_GET['hapus'])) {
  $transaksi_id = $_GET['hapus'];
  
  // Cek apakah sudah lebih dari 1 menit sejak created_at
  $result = mysqli_query($conn, "SELECT created_at, status FROM transaksi WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
  $transaksi = mysqli_fetch_assoc($result);
  
  if ($transaksi) {
    $createdTime = strtotime($transaksi['created_at']);
    $currentTime = time();
    $diffMinutes = ($currentTime - $createdTime) / 60;
    
    // Hanya boleh hapus jika status selesai/refund DAN sudah lebih dari 1 menit
    if (($transaksi['status'] == 'selesai' || $transaksi['status'] == 'refund') && $diffMinutes >= 1) {
      mysqli_query($conn, "DELETE FROM transaksi WHERE id='$transaksi_id' AND penjual_id='$idPenjual'");
    }
  }
  
  header("Location: transaksi.php");
  exit;
}

/* =====================
   FILTER & GET DATA
===================== */
$status = $_GET['status'] ?? '';

$where = "WHERE t.penjual_id='$idPenjual'";
if ($status && $status != 'semua') {
  $where .= " AND t.status='$status'";
}

// Query dengan kolom yang benar (no_hp bukan telepon)
$query = "
  SELECT t.*, u.nama as nama_pembeli, u.email as email_pembeli, u.no_hp as no_hp_pembeli
  FROM transaksi t
  JOIN users u ON t.pembeli_id = u.id
  $where
  ORDER BY t.created_at DESC
";

$qTransaksi = mysqli_query($conn, $query);

// Hitung jumlah transaksi per status untuk badge
$qStatusCount = mysqli_query($conn, "
  SELECT 
    status,
    COUNT(*) as jumlah
  FROM transaksi 
  WHERE penjual_id='$idPenjual'
  GROUP BY status
");

$statusCounts = [];
while ($row = mysqli_fetch_assoc($qStatusCount)) {
  $statusCounts[$row['status']] = $row['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Transaksi - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">

<style>
/* CSS khusus untuk halaman transaksi */
.status-badge {
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 0.85em;
  font-weight: 500;
}
.status-menunggu { background-color: #fff3cd; color: #856404; }
.status-approve { background-color: #d1ecf1; color: #0c5460; }
.status-tolak { background-color: #f8d7da; color: #721c24; }
.status-refund { background-color: #f8d7da; color: #721c24; }
.status-selesai { background-color: #d4edda; color: #155724; }
.badge-kurir {
  background-color: #6c757d;
  color: white;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.75em;
  font-family: monospace;
}
.filter-btn {
  position: relative;
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}
.filter-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background-color: #dc3545;
  color: white;
  font-size: 0.7rem;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.table-custom th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
}
.action-btn {
  padding: 5px 10px;
  font-size: 0.85rem;
  border-radius: 5px;
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
.modal-custom .modal-header {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
}
.resi-input {
  font-family: monospace;
  letter-spacing: 1px;
}
.table-row-hover tr:hover {
  background-color: #f8f9fa;
}
.pembeli-info {
  max-width: 200px;
}
.pembeli-name {
  font-weight: 600;
  color: #2c3e50;
}
.pembeli-contact {
  font-size: 0.85rem;
}
.timer-badge {
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 10px;
  background-color: #6c757d;
  color: white;
}
.filter-card {
  background: white;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  border: 1px solid #dee2e6;
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
<div class="d-flex justify-content-between align-items-center mb-4">
<div>
  <h3 class="mb-1">Manajemen Transaksi</h3>
  <p class="text-muted mb-0">Kelola semua transaksi pembeli Anda</p>
</div>
<div class="text-end">
  <span class="badge bg-dark">
    <i class="bi bi-receipt me-1"></i> 
    <?= mysqli_num_rows($qTransaksi) ?> transaksi
  </span>
</div>
</div>

<!-- FILTER STATUS -->
<div class="filter-card mb-4">
<h5 class="mb-3"><i class="bi bi-funnel me-2"></i> Filter Status Transaksi</h5>
<div class="d-flex gap-2 flex-wrap">
<a href="?status=semua" 
   class="filter-btn btn <?= (!$status || $status=='semua')?'btn-primary':'btn-outline-primary' ?>">
  <i class="bi bi-grid"></i> Semua
  <?php if(array_sum($statusCounts) > 0): ?>
  <span class="filter-badge"><?= array_sum($statusCounts) ?></span>
  <?php endif; ?>
</a>
<a href="?status=menunggu" 
   class="filter-btn btn <?= $status=='menunggu'?'btn-warning':'btn-outline-warning' ?>">
  <i class="bi bi-clock"></i> Menunggu
  <?php if(isset($statusCounts['menunggu']) && $statusCounts['menunggu'] > 0): ?>
  <span class="filter-badge"><?= $statusCounts['menunggu'] ?></span>
  <?php endif; ?>
</a>
<a href="?status=approve" 
   class="filter-btn btn <?= $status=='approve'?'btn-info':'btn-outline-info' ?>">
  <i class="bi bi-check-circle"></i> Approved
  <?php if(isset($statusCounts['approve']) && $statusCounts['approve'] > 0): ?>
  <span class="filter-badge"><?= $statusCounts['approve'] ?></span>
  <?php endif; ?>
</a>
<a href="?status=tolak" 
   class="filter-btn btn <?= $status=='tolak'?'btn-danger':'btn-outline-danger' ?>">
  <i class="bi bi-x-circle"></i> Ditolak
  <?php if(isset($statusCounts['tolak']) && $statusCounts['tolak'] > 0): ?>
  <span class="filter-badge"><?= $statusCounts['tolak'] ?></span>
  <?php endif; ?>
</a>
<a href="?status=selesai" 
   class="filter-btn btn <?= $status=='selesai'?'btn-success':'btn-outline-success' ?>">
  <i class="bi bi-check-lg"></i> Selesai
  <?php if(isset($statusCounts['selesai']) && $statusCounts['selesai'] > 0): ?>
  <span class="filter-badge"><?= $statusCounts['selesai'] ?></span>
  <?php endif; ?>
</a>
<a href="?status=refund" 
   class="filter-btn btn <?= $status=='refund'?'btn-secondary':'btn-outline-secondary' ?>">
  <i class="bi bi-arrow-counterclockwise"></i> Refund
  <?php if(isset($statusCounts['refund']) && $statusCounts['refund'] > 0): ?>
  <span class="filter-badge"><?= $statusCounts['refund'] ?></span>
  <?php endif; ?>
</a>
</div>
</div>

<!-- TABLE TRANSAKSI -->
<div class="card shadow-sm">
<div class="card-header bg-white d-flex justify-content-between align-items-center">
<h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Daftar Transaksi</h5>
<div>
  <small class="text-muted">
    <?php if($status && $status != 'semua'): ?>
      Menampilkan: <strong><?= ucfirst($status) ?></strong>
    <?php else: ?>
      Menampilkan semua transaksi
    <?php endif; ?>
  </small>
</div>
</div>
<div class="card-body p-0">
<?php if(mysqli_num_rows($qTransaksi) > 0): ?>
<div class="table-responsive">
  <table class="table table-custom table-hover table-row-hover mb-0">
    <thead>
      <tr>
        <th style="width: 120px;">ID Transaksi</th>
        <th style="min-width: 200px;">Pembeli</th>
        <th style="width: 150px;">Total</th>
        <th style="width: 120px;">Status</th>
        <th style="width: 150px;">Tanggal</th>
        <th style="width: 150px;">No. Resi</th>
        <th style="width: 220px;" class="text-center">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php while($t = mysqli_fetch_assoc($qTransaksi)): 
        $createdTime = strtotime($t['created_at']);
        $currentTime = time();
        $diffMinutes = ($currentTime - $createdTime) / 60;
        $diffSeconds = $currentTime - $createdTime;
        $bolehHapus = (($t['status'] == 'selesai' || $t['status'] == 'refund') && $diffMinutes >= 1);
      ?>
      <tr>
        <td class="fw-semibold">
          #<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?>
          <?php if($t['status'] == 'menunggu' && $diffSeconds < 86400): ?>
            <span class="timer-badge d-block mt-1" title="Transaksi baru">
              <i class="bi bi-clock me-1"></i> Baru
            </span>
          <?php endif; ?>
        </td>
        <td class="pembeli-info">
          <div class="pembeli-name"><?= htmlspecialchars($t['nama_pembeli']) ?></div>
          <div class="pembeli-contact text-muted">
            <small>
              <i class="bi bi-envelope me-1"></i> <?= $t['email_pembeli'] ?>
            </small>
          </div>
          <?php if(!empty($t['no_hp_pembeli'])): ?>
          <div class="pembeli-contact text-muted">
            <small>
              <i class="bi bi-telephone me-1"></i> <?= $t['no_hp_pembeli'] ?>
            </small>
          </div>
          <?php endif; ?>
        </td>
        <td class="fw-semibold">Rp <?= number_format($t['total']) ?></td>
        <td>
          <span class="status-badge status-<?= $t['status'] ?>">
            <?= strtoupper($t['status']) ?>
          </span>
        </td>
        <td>
          <div><?= date('d/m/Y', strtotime($t['created_at'])) ?></div>
          <small class="text-muted"><?= date('H:i', strtotime($t['created_at'])) ?></small>
        </td>
        <td>
          <?php if($t['no_resi']): ?>
            <span class="badge-kurir" title="Klik untuk melacak">
              <i class="bi bi-truck me-1"></i> <?= $t['no_resi'] ?>
            </span>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
        <td class="text-center">
          <div class="d-flex flex-wrap gap-1 justify-content-center">
            <!-- BUTTON DETAIL (Modal) -->
            <button class="btn btn-sm btn-info action-btn" 
                    data-bs-toggle="modal" data-bs-target="#detailModal<?= $t['id'] ?>"
                    title="Lihat detail transaksi">
              <i class="bi bi-eye"></i> Detail
            </button>
            
            <?php if($t['status'] == 'menunggu'): ?>
              <!-- APPROVE & TOLAK -->
              <button class="btn btn-sm btn-success action-btn" 
                      data-bs-toggle="modal" data-bs-target="#approveModal<?= $t['id'] ?>"
                      title="Approve pembayaran">
                <i class="bi bi-check-lg"></i> Approve
              </button>
              <button class="btn btn-sm btn-danger action-btn" 
                      data-bs-toggle="modal" data-bs-target="#tolakModal<?= $t['id'] ?>"
                      title="Tolak pembayaran">
                <i class="bi bi-x-lg"></i> Tolak
              </button>
            
            <?php elseif($t['status'] == 'approve' && empty($t['no_resi'])): ?>
              <!-- INPUT RESI -->
              <button class="btn btn-sm btn-primary action-btn" 
                      data-bs-toggle="modal" data-bs-target="#resiModal<?= $t['id'] ?>"
                      title="Input nomor resi pengiriman">
                <i class="bi bi-input-cursor"></i> Input Resi
              </button>
            
            <?php elseif($t['status'] == 'approve' && !empty($t['no_resi'])): ?>
              <!-- LACAK PAKET & SELESAI -->
              <a href="https://www.jne.co.id/tracking?q=<?= $t['no_resi'] ?>" 
                 target="_blank" 
                 class="btn btn-sm btn-warning action-btn"
                 title="Lacak paket di website kurir">
                <i class="bi bi-geo-alt"></i> Lacak
              </a>
              <form method="POST" class="d-inline">
                <input type="hidden" name="aksi" value="selesai">
                <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-success action-btn" title="Tandai sebagai selesai">
                  <i class="bi bi-check2-circle"></i> Selesai
                </button>
              </form>
            
            <?php elseif($t['status'] == 'selesai' || $t['status'] == 'refund'): ?>
              <!-- HAPUS (dengan delay 1 menit) -->
              <?php if($bolehHapus): ?>
                <a href="?hapus=<?= $t['id'] ?>" 
                   class="btn btn-sm btn-danger action-btn"
                   onclick="return confirm('Yakin hapus transaksi ini? Data tidak dapat dikembalikan.')"
                   title="Hapus transaksi">
                  <i class="bi bi-trash"></i> Hapus
                </a>
              <?php else: ?>
                <button class="btn btn-sm btn-secondary action-btn" disabled 
                        title="Tunggu <?= max(0, ceil(1 - $diffMinutes)) ?> menit lagi untuk menghapus">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>

      <!-- MODAL DETAIL -->
      <div class="modal fade modal-custom" id="detailModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="bi bi-receipt me-2"></i> 
                Detail Transaksi #<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?>
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <h6><i class="bi bi-person me-2"></i> Info Pembeli</h6>
                  <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between">
                      <span>Nama</span>
                      <strong><?= htmlspecialchars($t['nama_pembeli']) ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                      <span>Email</span>
                      <span><?= $t['email_pembeli'] ?></span>
                    </div>
                    <?php if(!empty($t['no_hp_pembeli'])): ?>
                    <div class="list-group-item d-flex justify-content-between">
                      <span>No. HP</span>
                      <span><?= $t['no_hp_pembeli'] ?></span>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <h6><i class="bi bi-info-circle me-2"></i> Info Transaksi</h6>
                  <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between">
                      <span>Total Pembayaran</span>
                      <strong class="text-success">Rp <?= number_format($t['total']) ?></strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                      <span>Status</span>
                      <span class="status-badge status-<?= $t['status'] ?>">
                        <?= strtoupper($t['status']) ?>
                      </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                      <span>Tanggal</span>
                      <span><?= date('d F Y H:i', strtotime($t['created_at'])) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                      <span>No. Resi</span>
                      <span><?= $t['no_resi'] ?: '<span class="text-muted">Belum ada</span>' ?></span>
                    </div>
                  </div>
                </div>
              </div>

              <?php if($t['bukti_transfer'] && file_exists("../uploads/bukti_transfer/".$t['bukti_transfer'])): ?>
              <div class="mt-4">
                <h6><i class="bi bi-receipt me-2"></i> Bukti Transfer</h6>
                <div class="text-center">
                  <img src="../uploads/bukti_transfer/<?= $t['bukti_transfer'] ?>" 
                       class="img-fluid rounded" 
                       style="max-height: 300px;"
                       alt="Bukti Transfer"
                       onerror="this.style.display='none'">
                  <?php if($t['status'] == 'menunggu'): ?>
                  <p class="text-muted mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Verifikasi bukti transfer sebelum approve
                  </p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Tutup
              </button>
              <?php if($t['status'] == 'menunggu'): ?>
              <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $t['id'] ?>" data-bs-dismiss="modal">
                <i class="bi bi-check-lg me-1"></i> Approve
              </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL APPROVE -->
      <div class="modal fade" id="approveModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST">
              <input type="hidden" name="aksi" value="approve">
              <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i> Konfirmasi Approve</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  Pastikan semua verifikasi sudah dilakukan sebelum approve.
                </div>
                <p>Anda akan mengapprove transaksi:</p>
                <div class="alert alert-light">
                  <strong>#<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></strong><br>
                  Pembeli: <?= htmlspecialchars($t['nama_pembeli']) ?><br>
                  Total: <strong class="text-success">Rp <?= number_format($t['total']) ?></strong>
                </div>
                <p><strong>Pastikan:</strong></p>
                <ul>
                  <li>✅ Bukti transfer valid dan jelas</li>
                  <li>✅ Jumlah transfer sesuai</li>
                  <li>✅ Nama rekening pengirim sesuai</li>
                  <li>✅ Tidak ada indikasi penipuan</li>
                </ul>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-success">
                  <i class="bi bi-check-lg me-1"></i> Ya, Approve Transaksi
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- MODAL TOLAK -->
      <div class="modal fade" id="tolakModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST">
              <input type="hidden" name="aksi" value="tolak">
              <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i> Konfirmasi Penolakan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-warning">
                  <i class="bi bi-exclamation-triangle me-2"></i>
                  Pembeli akan menerima notifikasi penolakan.
                </div>
                <p>Anda akan menolak transaksi:</p>
                <div class="alert alert-light">
                  <strong>#<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></strong><br>
                  Pembeli: <?= htmlspecialchars($t['nama_pembeli']) ?><br>
                  Total: <strong class="text-success">Rp <?= number_format($t['total']) ?></strong>
                </div>
                <p><strong>Akibat:</strong></p>
                <ul>
                  <li>❌ Transaksi akan dibatalkan</li>
                  <li>❌ Pembeli akan diberitahu via notifikasi</li>
                  <li>❌ Status berubah menjadi "Ditolak"</li>
                </ul>
                <div class="mb-3">
                  <label class="form-label">Alasan Penolakan <small class="text-muted">(Opsional)</small></label>
                  <textarea class="form-control" name="alasan" rows="3" 
                            placeholder="Contoh: Bukti transfer tidak jelas, Jumlah transfer kurang, Nama rekening tidak sesuai, dll."></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-danger">
                  <i class="bi bi-x-lg me-1"></i> Ya, Tolak Transaksi
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- MODAL INPUT RESI -->
      <div class="modal fade" id="resiModal<?= $t['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <form method="POST">
              <input type="hidden" name="aksi" value="input_resi">
              <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i> Input Nomor Resi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  Input nomor resi setelah paket dikirim ke kurir.
                </div>
                <p>Transaksi: <strong>#<?= str_pad($t['id'], 6, '0', STR_PAD_LEFT) ?></strong></p>
                <div class="mb-3">
                  <label class="form-label">Nomor Resi</label>
                  <input type="text" name="no_resi" class="form-control resi-input" required 
                         placeholder="Contoh: JP1234567890ID, JTE1234567890, dll"
                         maxlength="50">
                  <small class="text-muted">
                    Masukkan nomor resi dari kurir pengiriman (JNE, J&T, TIKI, POS, dll)
                  </small>
                </div>
                <div class="alert alert-light">
                  <strong>Catatan:</strong>
                  <ul class="mb-0">
                    <li>Setelah input resi, status otomatis berubah menjadi "Selesai"</li>
                    <li>Pembeli dapat melacak paket dengan nomor resi ini</li>
                    <li>Pastikan nomor resi sudah benar sebelum disimpan</li>
                  </ul>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-lg me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i> Simpan Resi
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty-state">
  <i class="bi bi-receipt"></i>
  <h5 class="text-muted mt-3">Belum ada transaksi</h5>
  <p>
    <?php if($status && $status != 'semua'): ?>
      Tidak ada transaksi dengan status "<?= ucfirst($status) ?>"
    <?php else: ?>
      Belum ada transaksi yang masuk ke toko Anda
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>
</div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh untuk update status tombol hapus
setInterval(() => {
  const deleteButtons = document.querySelectorAll('button[disabled][title*="menit"]');
  deleteButtons.forEach(btn => {
    const title = btn.getAttribute('title');
    const match = title.match(/Tunggu (\d+) menit lagi/);
    if (match) {
      const sisaMenit = parseInt(match[1]);
      if (sisaMenit <= 0) {
        location.reload(); // Refresh halaman jika sudah bisa hapus
      }
    }
  });
}, 30000); // Check setiap 30 detik

// Validasi input resi
document.querySelectorAll('.resi-input').forEach(input => {
  input.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  });
});

// Smooth scroll dan auto-focus modal
document.addEventListener('DOMContentLoaded', function() {

  const urlParams = new URLSearchParams(window.location.search);
  const statusParam = urlParams.get('status');
  
  if (statusParam) {
    const filterBtn = document.querySelector(`a[href="?status=${statusParam}"]`);
    if (filterBtn) {
      filterBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
});

// Konfirmasi sebelum hapus
document.querySelectorAll('a[href*="hapus="]').forEach(link => {
  link.addEventListener('click', function(e) {
    if (!confirm('Yakin ingin menghapus transaksi ini? Data tidak dapat dikembalikan.')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>