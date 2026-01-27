<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'] ?? 'Pembeli';

// Ambil parameter filter
$search = $_GET['search'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'terbaru';
$page = $_GET['page'] ?? 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Query produk dengan filter
$where = "WHERE p.stok > 0";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.nama_buku LIKE ? OR p.penulis LIKE ? OR k.nama_kategori LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($kategori)) {
    $where .= " AND k.nama_kategori = ?";
    $params[] = $kategori;
}

// Query untuk mengambil produk
$sql = "SELECT 
    p.id,
    p.nama_buku,
    p.penulis,
    p.harga,
    p.gambar,
    p.stok,
    k.nama_kategori AS kategori,
    u.nama AS nama_penjual,
    COALESCE(SUM(td.qty), 0) AS jumlah_terjual
FROM produk p
LEFT JOIN kategori k ON p.kategori_id = k.id
LEFT JOIN users u ON p.penjual_id = u.id
LEFT JOIN transaksi_detail td ON p.id = td.produk_id
LEFT JOIN transaksi t ON td.transaksi_id = t.id AND t.status = 'selesai'
$where
GROUP BY p.id";

// Sorting
switch($sort) {
    case 'termurah':
        $sql .= " ORDER BY p.harga ASC";
        break;
    case 'termahal':
        $sql .= " ORDER BY p.harga DESC";
        break;
    case 'terlaris':
        $sql .= " ORDER BY jumlah_terjual DESC";
        break;
    case 'stok':
        $sql .= " ORDER BY p.stok DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

// Tambah limit untuk pagination
$sql .= " LIMIT $limit OFFSET $offset";

// Prepare statement
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$produk = [];
while ($row = mysqli_fetch_assoc($result)) {
    $produk[] = $row;
}

// Query untuk total produk (untuk pagination)
$countSql = "SELECT COUNT(*) as total FROM produk p LEFT JOIN kategori k ON p.kategori_id = k.id WHERE p.stok > 0";
if (!empty($search)) {
    $countSql .= " AND (p.nama_buku LIKE '%$search%' OR p.penulis LIKE '%$search%' OR k.nama_kategori LIKE '%$search%')";
}
if (!empty($kategori)) {
    $countSql .= " AND k.nama_kategori = '$kategori'";
}
$countResult = mysqli_query($conn, $countSql);
$totalProduk = mysqli_fetch_assoc($countResult)['total'] ?? 0;
$totalPages = ceil($totalProduk / $limit);

// Ambil kategori untuk filter
$qKategori = mysqli_query($conn, "SELECT DISTINCT k.nama_kategori FROM kategori k JOIN produk p ON k.id = p.kategori_id WHERE p.stok > 0 ORDER BY k.nama_kategori");
$kategoriList = [];
while ($row = mysqli_fetch_assoc($qKategori)) {
    $kategoriList[] = $row['nama_kategori'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Produk Buku & ATK - BOOKIE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; }
    .product-card { border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; transition: all 0.3s; }
    .product-card:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(-3px); }
    .product-img { width: 100%; height: 200px; object-fit: cover; }
    .filter-sidebar { background: white; border-radius: 10px; padding: 20px; }
    .price-tag { color: #28a745; font-weight: bold; font-size: 1.1rem; }
    .pagination .page-link { color: #6f42c1; }
    .pagination .page-item.active .page-link { background-color: #6f42c1; border-color: #6f42c1; }
  </style>
</head>
<body>
<?php include "includes/sidebar.php"; ?>

<main class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="fw-bold text-dark">Produk Buku & ATK</h3>
      <p class="text-muted mb-0">Temukan berbagai macam buku dan alat tulis dari penjual terpercaya</p>
    </div>
    <div class="d-flex align-items-center">
      <span class="text-muted me-3"><?= number_format($totalProduk) ?> produk tersedia</span>
      <div class="d-inline-block">
        <input type="text" class="form-control search-box" placeholder="Cari produk..." value="<?= htmlspecialchars($search) ?>" id="searchInput">
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Filter Sidebar -->
    <div class="col-md-3">
      <div class="filter-sidebar mb-4">
        <h5 class="fw-bold mb-3">Filter</h5>
        
        <div class="mb-4">
          <h6 class="fw-semibold mb-2">Kategori</h6>
          <div class="list-group">
            <a href="buku.php" class="list-group-item list-group-item-action <?= empty($kategori) ? 'active' : '' ?>">
              Semua Kategori
            </a>
            <?php foreach($kategoriList as $kat): ?>
            <a href="buku.php?kategori=<?= urlencode($kat) ?>" class="list-group-item list-group-item-action <?= $kategori == $kat ? 'active' : '' ?>">
              <?= htmlspecialchars($kat) ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        
        <div class="mb-4">
          <h6 class="fw-semibold mb-2">Urutkan</h6>
          <div class="list-group">
            <a href="buku.php?sort=terbaru<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori=' . urlencode($kategori) : '' ?>" class="list-group-item list-group-item-action <?= $sort == 'terbaru' ? 'active' : '' ?>">
              Terbaru
            </a>
            <a href="buku.php?sort=termurah<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori=' . urlencode($kategori) : '' ?>" class="list-group-item list-group-item-action <?= $sort == 'termurah' ? 'active' : '' ?>">
              Termurah
            </a>
            <a href="buku.php?sort=termahal<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori=' . urlencode($kategori) : '' ?>" class="list-group-item list-group-item-action <?= $sort == 'termahal' ? 'active' : '' ?>">
              Termahal
            </a>
            <a href="buku.php?sort=terlaris<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori=' . urlencode($kategori) : '' ?>" class="list-group-item list-group-item-action <?= $sort == 'terlaris' ? 'active' : '' ?>">
              Terlaris
            </a>
            <a href="buku.php?sort=stok<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori=' . urlencode($kategori) : '' ?>" class="list-group-item list-group-item-action <?= $sort == 'stok' ? 'active' : '' ?>">
              Stok Terbanyak
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Produk List -->
    <div class="col-md-9">
      <?php if(empty($produk)): ?>
      <div class="text-center py-5">
        <i class="bi bi-search" style="font-size: 4rem; color: #6c757d;"></i>
        <h4 class="mt-3">Produk tidak ditemukan</h4>
        <p class="text-muted">Coba gunakan kata kunci lain atau lihat semua produk</p>
        <a href="buku.php" class="btn btn-purple">Lihat Semua Produk</a>
      </div>
      <?php else: ?>
      <div class="row g-3">
        <?php foreach($produk as $item): ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
          <div class="product-card">
            <div class="position-relative">
              <img src="<?= !empty($item['gambar']) ? '../uploads/'.$item['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                   class="product-img" 
                   alt="<?= htmlspecialchars($item['nama_buku']) ?>"
                   onerror="this.src='../assets/img/book-placeholder.png'">
              <?php if($item['stok'] == 0): ?>
              <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center">
                <span class="bg-danger text-white px-3 py-1 rounded">HABIS</span>
              </div>
              <?php endif; ?>
            </div>
            <div class="p-3">
              <h6 class="fw-bold mb-1"><?= htmlspecialchars($item['nama_buku']) ?></h6>
              <p class="text-muted mb-1 small"><?= htmlspecialchars($item['penulis']) ?></p>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <?php if(!empty($item['kategori'])): ?>
                <span class="badge bg-purple bg-opacity-10 text-purple"><?= htmlspecialchars($item['kategori']) ?></span>
                <?php endif; ?>
                <span class="badge bg-info bg-opacity-10 text-info">
                  <i class="bi bi-shop"></i> <?= htmlspecialchars($item['nama_penjual']) ?>
                </span>
              </div>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="price-tag">Rp <?= number_format($item['harga'],0,',','.') ?></div>
                <div class="text-muted small">
                  <?php if($item['jumlah_terjual'] > 0): ?>
                  <i class="bi bi-cart-check"></i> <?= $item['jumlah_terjual'] ?> terjual
                  <?php endif; ?>
                </div>
              </div>
              <div class="d-grid gap-2">
                <?php if($item['stok'] > 0): ?>
                <button class="btn btn-purple add-to-cart" data-id="<?= $item['id'] ?>">
                  <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                </button>
                <?php else: ?>
                <button class="btn btn-secondary" disabled>
                  <i class="bi bi-cart-x"></i> Stok Habis
                </button>
                <?php endif; ?>
                <a href="produk_detail.php?id=<?= $item['id'] ?>" class="btn btn-outline-purple">
                  <i class="bi bi-eye"></i> Lihat Detail
                </a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php if($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="buku.php?page=<?= $page-1 ?>&sort=<?= $sort ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori='.urlencode($kategori) : '' ?>">Sebelumnya</a>
          </li>
          <?php endif; ?>
          
          <?php for($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="buku.php?page=<?= $i ?>&sort=<?= $sort ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori='.urlencode($kategori) : '' ?>"><?= $i ?></a>
          </li>
          <?php endfor; ?>
          
          <?php if($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="buku.php?page=<?= $page+1 ?>&sort=<?= $sort ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($kategori) ? '&kategori='.urlencode($kategori) : '' ?>">Selanjutnya</a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Search functionality
  document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      const query = this.value.trim();
      const url = new URL(window.location);
      url.searchParams.set('search', query);
      url.searchParams.set('page', '1');
      window.location.href = url.toString();
    }
  });

  // Add to cart functionality (sama seperti di dashboard)
  document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
      const productId = this.getAttribute('data-id');
      
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="bi bi-hourglass-split"></i> Menambahkan...';
      this.disabled = true;
      
      fetch('ajax/add_to_cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `product_id=${productId}&qty=1`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.innerHTML = '<i class="bi bi-check-circle"></i> Ditambahkan!';
          this.classList.remove('btn-purple');
          this.classList.add('btn-success');
          
          // Update badge di sidebar
          const cartBadge = document.querySelector('.sidebar .badge');
          if (cartBadge) {
            cartBadge.textContent = parseInt(cartBadge.textContent) + 1;
          }
          
          setTimeout(() => {
            this.innerHTML = originalText;
            this.classList.remove('btn-success');
            this.classList.add('btn-purple');
            this.disabled = false;
          }, 2000);
        } else {
          alert(data.message);
          this.innerHTML = originalText;
          this.disabled = false;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan');
        this.innerHTML = originalText;
        this.disabled = false;
      });
    });
  });
</script>
</body>
</html>