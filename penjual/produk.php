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
   HAPUS PRODUK
===================== */
if (isset($_POST['aksi']) && $_POST['aksi']=="hapus") {
  $id = $_POST['id'];
  mysqli_query($conn,"DELETE FROM produk 
    WHERE id='$id' AND penjual_id='$idPenjual' AND stok=0");
  header("Location: produk.php");
  exit;
}

/* =====================
   FILTER
===================== */
$cari = $_GET['cari'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$stok = $_GET['stok'] ?? '';

$where = "WHERE p.penjual_id='$idPenjual'";
if ($cari) $where .= " AND p.nama_buku LIKE '%$cari%'";
if ($kategori) $where .= " AND p.kategori_id='$kategori'";
if ($stok=='habis') $where .= " AND p.stok=0";
if ($stok=='ada') $where .= " AND p.stok>0";

/* =====================
   DATA
===================== */
$qKategori = mysqli_query($conn,"SELECT * FROM kategori");

$qProduk = mysqli_query($conn,"
  SELECT p.*, k.nama_kategori
  FROM produk p 
  JOIN kategori k ON p.kategori_id = k.id
  $where 
  ORDER BY p.id DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Produk Penjual - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">

<style>
/* CSS khusus untuk halaman produk */
.product-img {
  height: 70px;
  width: 60px;
  object-fit: cover;
  border-radius: 6px;
  border: 1px solid #dee2e6;
}
.modal-img {
  height: 260px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #dee2e6;
}
.add-btn {
  position: fixed;
  bottom: 30px;
  right: 30px;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  font-size: 30px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  z-index: 1000;
  transition: transform 0.3s, box-shadow 0.3s;
}
.add-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0,0,0,0.3);
}
.clickable {
  cursor: pointer;
  color: #0d6efd;
  transition: color 0.2s;
}
.clickable:hover {
  color: #0a58ca;
  text-decoration: underline;
}
.product-card {
  transition: transform 0.3s, box-shadow 0.3s;
}
.product-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.table-custom th {
  background-color: #f8f9fa;
  font-weight: 600;
  color: #495057;
}
.badge-stock {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.85rem;
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
.filter-card {
  background: white;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  border: 1px solid #dee2e6;
}
.stock-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 6px;
}
.stock-high { background-color: #28a745; }
.stock-low { background-color: #ffc107; }
.stock-empty { background-color: #dc3545; }
.action-btn {
  padding: 5px 12px;
  border-radius: 5px;
  font-size: 0.85rem;
}
.modal-custom .modal-header {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
}
.modal-custom .list-group-item {
  border-left: 0;
  border-right: 0;
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
  <h3 class="mb-1">Manajemen Produk</h3>
  <p class="text-muted mb-0">Kelola produk buku yang Anda jual</p>
</div>
<div class="text-end">
  <span class="badge bg-dark">
    <i class="bi bi-box-seam me-1"></i> 
    <?= mysqli_num_rows($qProduk) ?> produk
  </span>
</div>
</div>

<!-- FILTER -->
<div class="filter-card">
<h5 class="mb-3"><i class="bi bi-funnel me-2"></i> Filter Produk</h5>
<form method="GET" class="row g-3 align-items-end">
<div class="col-md-4">
  <label class="form-label fw-semibold">Cari Buku</label>
  <div class="input-group">
    <span class="input-group-text">
      <i class="bi bi-search"></i>
    </span>
    <input type="text" class="form-control" name="cari" 
           placeholder="Masukkan nama buku..." value="<?= htmlspecialchars($cari) ?>">
  </div>
</div>
<div class="col-md-3">
  <label class="form-label fw-semibold">Kategori</label>
  <select class="form-select" name="kategori">
    <option value="">Semua Kategori</option>
    <?php 
    mysqli_data_seek($qKategori, 0); 
    while($k = mysqli_fetch_assoc($qKategori)): 
    ?>
    <option value="<?= $k['id'] ?>" <?= $kategori==$k['id']?'selected':'' ?>>
      <?= htmlspecialchars($k['nama_kategori']) ?>
    </option>
    <?php endwhile; ?>
  </select>
</div>
<div class="col-md-3">
  <label class="form-label fw-semibold">Status Stok</label>
  <select class="form-select" name="stok">
    <option value="">Semua Stok</option>
    <option value="ada" <?= $stok=='ada'?'selected':'' ?>>Stok Tersedia</option>
    <option value="habis" <?= $stok=='habis'?'selected':'' ?>>Stok Habis</option>
  </select>
</div>
<div class="col-md-2">
  <button type="submit" class="btn btn-dark w-100">
    <i class="bi bi-funnel me-1"></i> Terapkan
  </button>
</div>
</form>
</div>

<!-- TABLE -->
<div class="card shadow-sm">
<div class="card-header bg-white d-flex justify-content-between align-items-center">
<h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Daftar Produk</h5>
<a href="produk_tambah.php" class="btn btn-primary btn-sm">
  <i class="bi bi-plus-circle me-1"></i> Tambah Produk
</a>
</div>
<div class="card-body p-0">
<div class="table-responsive">
  <table class="table table-custom table-hover mb-0">
    <thead>
      <tr>
        <th style="width: 80px;">Gambar</th>
        <th>Nama Buku</th>
        <th style="width: 150px;">Kategori</th>
        <th style="width: 120px;" class="text-center">Stok</th>
        <th style="width: 150px;">Harga</th>
        <th style="width: 180px;" class="text-center">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if(mysqli_num_rows($qProduk) > 0): ?>
        <?php while($p = mysqli_fetch_assoc($qProduk)): 
          // Tentukan kelas warna untuk stok
          $stock_class = 'stock-high';
          $stock_badge = 'bg-success';
          if($p['stok'] == 0) {
            $stock_class = 'stock-empty';
            $stock_badge = 'bg-danger';
          } elseif($p['stok'] < 5) {
            $stock_class = 'stock-low';
            $stock_badge = 'bg-warning';
          }
        ?>
        <tr class="product-card">
          <td class="text-center">
            <img src="../uploads/<?= htmlspecialchars($p['gambar'] ?? 'default.png') ?>" 
                 class="product-img"
                 onerror="this.src='../assets/img/product-placeholder.png'">
          </td>
          <td>
            <div class="clickable" data-bs-toggle="modal" data-bs-target="#detail<?= $p['id'] ?>">
              <?= htmlspecialchars($p['nama_buku']) ?>
            </div>
            <?php if($p['penulis']): ?>
            <small class="text-muted d-block">
              <i class="bi bi-person me-1"></i> <?= htmlspecialchars($p['penulis']) ?>
            </small>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-light text-dark border">
              <?= htmlspecialchars($p['nama_kategori']) ?>
            </span>
          </td>
          <td class="text-center">
            <span class="badge badge-stock <?= $stock_badge ?>">
              <span class="<?= $stock_class ?>"></span>
              <?= $p['stok'] ?> unit
            </span>
          </td>
          <td class="fw-semibold">Rp <?= number_format($p['harga']) ?></td>
          <td class="text-center">
            <div class="d-flex gap-2 justify-content-center">
              <a href="produk_edit.php?id=<?= $p['id'] ?>" 
                 class="btn btn-warning btn-sm action-btn" 
                 title="Edit Produk">
                <i class="bi bi-pencil"></i> Edit
              </a>
              <?php if ($p['stok'] == 0): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-danger btn-sm action-btn" 
                        onclick="return confirm('Hapus produk ini?')"
                        title="Hapus Produk">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </form>
              <?php else: ?>
              <button class="btn btn-outline-secondary btn-sm action-btn" disabled 
                      title="Produk dengan stok tidak bisa dihapus">
                <i class="bi bi-trash"></i> Hapus
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <!-- MODAL DETAIL -->
        <div class="modal fade modal-custom" id="detail<?= $p['id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($p['nama_buku']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-4">
                  <div class="col-md-5">
                    <img src="../uploads/<?= htmlspecialchars($p['gambar'] ?? 'default.png') ?>" 
                         class="modal-img w-100"
                         onerror="this.src='../assets/img/product-placeholder.png'">
                    <div class="mt-3 text-center">
                      <span class="badge bg-primary"><?= htmlspecialchars($p['nama_kategori']) ?></span>
                      <?php if($p['isbn']): ?>
                      <div class="mt-2">
                        <small class="text-muted">ISBN:</small>
                        <div class="fw-semibold"><?= htmlspecialchars($p['isbn']) ?></div>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="col-md-7">
                    <?php if($p['deskripsi']): ?>
                    <div class="mb-4">
                      <h6>Deskripsi</h6>
                      <p class="text-muted"><?= nl2br(htmlspecialchars($p['deskripsi'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="list-group">
                      <?php if($p['penulis']): ?>
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Penulis</span>
                        <strong><?= htmlspecialchars($p['penulis']) ?></strong>
                      </div>
                      <?php endif; ?>
                      
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Stok Tersedia</span>
                        <strong><?= $p['stok'] ?> unit</strong>
                      </div>
                      
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Harga Jual</span>
                        <strong class="text-success">Rp <?= number_format($p['harga']) ?></strong>
                      </div>
                      
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Harga Modal</span>
                        <strong>Rp <?= number_format($p['modal']) ?></strong>
                      </div>
                      
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Margin</span>
                        <strong class="text-info">Rp <?= number_format($p['margin']) ?></strong>
                      </div>
                      
                      <div class="list-group-item d-flex justify-content-between">
                        <span>Keuntungan per Unit</span>
                        <strong class="text-primary">Rp <?= number_format($p['keuntungan']) ?></strong>
                      </div>
                    </div>
                    
                    <div class="mt-4">
                      <small class="text-muted">
                        <i class="bi bi-clock me-1"></i> 
                        Ditambahkan: <?= date('d M Y', strtotime($p['created_at'])) ?>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-circle me-1"></i> Tutup
                </button>
                <a href="produk_edit.php?id=<?= $p['id'] ?>" class="btn btn-primary">
                  <i class="bi bi-pencil me-1"></i> Edit Produk
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center">
            <div class="empty-state">
              <i class="bi bi-box"></i>
              <h5 class="text-muted mt-3">Tidak ada produk</h5>
              <p>
                <?php if($cari || $kategori || $stok): ?>
                  Tidak ada produk yang sesuai dengan filter
                <?php else: ?>
                  Belum ada produk. Mulai dengan menambahkan produk pertama Anda.
                <?php endif; ?>
              </p>
              <a href="produk_tambah.php" class="btn btn-primary mt-2">
                <i class="bi bi-plus-circle me-1"></i> Tambah Produk
              </a>
            </div>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>
</div>

<!-- Floating Action Button -->
<a href="produk_tambah.php" class="btn btn-primary add-btn" title="Tambah Produk Baru">
  <i class="bi bi-plus"></i>
</a>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirm sebelum hapus
document.querySelectorAll('form[method="POST"] button').forEach(button => {
  button.addEventListener('click', function(e) {
    if(!confirm('Yakin ingin menghapus produk ini?')) {
      e.preventDefault();
    }
  });
});

// Auto focus search field jika ada parameter cari
document.addEventListener('DOMContentLoaded', function() {
  const searchField = document.querySelector('input[name="cari"]');
  if(searchField && searchField.value) {
    searchField.focus();
    searchField.select();
  }
});

// Smooth scroll untuk modal
document.querySelectorAll('.clickable').forEach(item => {
  item.addEventListener('click', function() {
    const modalId = this.getAttribute('data-bs-target');
    const modal = new bootstrap.Modal(document.querySelector(modalId));
    modal.show();
  });
});
</script>
</body>
</html>