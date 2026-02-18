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

// Hitung jumlah item di keranjang untuk badge
$qKeranjang = mysqli_query($conn, "SELECT SUM(jumlah) AS total FROM keranjang WHERE id_user='$idPembeli'");
$jumlahKeranjang = 0;
if ($qKeranjang) {
    $keranjang = mysqli_fetch_assoc($qKeranjang);
    $jumlahKeranjang = $keranjang['total'] ?? 0;
}

/* =====================
   AMBIL SEMUA PRODUK DARI SEMUA PENJUAL
===================== */
$qProduk = mysqli_query($conn, "
    SELECT 
        p.id,
        p.nama_produk,
        p.pengarang,
        p.harga,
        p.gambar,
        p.stok,
        k.nama_kategori AS kategori,
        u.nama AS nama_penjual,
        u.id AS id_penjual,
        p.created_at
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
    LEFT JOIN users u ON p.id_penjual = u.id
    WHERE p.stok > 0
    ORDER BY p.created_at DESC
    LIMIT 12
");

$produk = [];
if ($qProduk) {
    while ($row = mysqli_fetch_assoc($qProduk)) {
        $produk[] = $row;
    }
}

/* =====================
   AMBIL KATEGORI UNTUK FILTER
===================== */
$qKategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
$kategoriList = [];
if ($qKategori) {
    while ($row = mysqli_fetch_assoc($qKategori)) {
        $kategoriList[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOKIE - Belanja Buku & ATK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Navbar atas */
        .top-navbar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .search-box {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 8px 20px;
            width: 400px;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
            outline: none;
        }
        
        /* Welcome card */
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-card h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 0;
        }
        
        /* Kategori chips */
        .kategori-chip {
            display: inline-block;
            padding: 8px 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            margin-right: 10px;
            margin-bottom: 10px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .kategori-chip:hover {
            background: #6f42c1;
            color: white;
            border-color: #6f42c1;
            transform: translateY(-2px);
        }
        
        .kategori-chip i {
            margin-right: 5px;
        }
        
        /* Product card */
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            border: 1px solid #f0f0f0;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 200px;
            overflow: hidden;
            position: relative;
            background: #f8f9fa;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #6f42c1;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .product-author {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .product-category {
            display: inline-block;
            background: #f0e6ff;
            color: #6f42c1;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .product-seller {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .product-seller i {
            color: #6f42c1;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .product-stock {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .btn-add-cart {
            width: 100%;
            background: #6f42c1;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-add-cart:hover {
            background: #5a32a3;
            transform: translateY(-2px);
        }
        
        .btn-add-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Section title */
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: #6f42c1;
            border-radius: 2px;
        }
        
        /* Loading animation */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-loading:after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            margin: auto;
            border: 3px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 15px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div>
            <h5 class="mb-0"><i class="bi bi-house-door me-2 text-purple"></i> Beranda</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="position-relative">
                <input type="text" class="search-box" id="searchInput" placeholder="Cari buku atau alat tulis...">
                <i class="bi bi-search position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); color: #aaa;"></i>
            </div>
            <a href="keranjang.php" class="text-dark position-relative">
                <i class="bi bi-cart3 fs-5"></i>
                <?php if($jumlahKeranjang > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                    <?= $jumlahKeranjang ?>
                </span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-8">
                <h1>Selamat Datang, <?= htmlspecialchars($namaPembeli) ?>! 👋</h1>
                <p>Temukan berbagai macam buku dan alat tulis dari penjual terpercaya di BOOKIE</p>
            </div>
            <div class="col-4 text-end">
                <i class="bi bi-shop" style="font-size: 5rem; opacity: 0.5;"></i>
            </div>
        </div>
    </div>

    <!-- Kategori Filter -->
    <?php if(!empty($kategoriList)): ?>
    <div class="mb-4">
        <h5 class="mb-3"><i class="bi bi-tags me-2 text-purple"></i> Jelajahi Kategori</h5>
        <div>
            <a href="produk.php" class="kategori-chip"><i class="bi bi-grid"></i> Semua</a>
            <?php foreach($kategoriList as $kategori): ?>
            <a href="produk.php?kategori=<?= $kategori['id'] ?>" class="kategori-chip">
                <i class="bi bi-bookmark"></i> <?= htmlspecialchars($kategori['nama_kategori']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Produk Terbaru dari Semua Penjual -->
    <div class="mb-4">
        <h2 class="section-title">Produk Terbaru</h2>
        <p class="text-muted mb-4">Temukan produk-produk terbaru dari berbagai penjual</p>
        
        <?php if(count($produk) > 0): ?>
        <div class="row g-4">
            <?php foreach($produk as $item): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= !empty($item['gambar']) ? '../uploads/'.$item['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                             alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                             onerror="this.src='../assets/img/book-placeholder.png'">
                        <?php if($item['stok'] < 5 && $item['stok'] > 0): ?>
                        <span class="product-badge">Stok Terbatas</span>
                        <?php elseif($item['stok'] == 0): ?>
                        <span class="product-badge" style="background: #dc3545;">Habis</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h6 class="product-title"><?= htmlspecialchars(mb_strimwidth($item['nama_produk'], 0, 40, '...')) ?></h6>
                        <p class="product-author"><?= htmlspecialchars($item['pengarang'] ?? 'Tanpa Pengarang') ?></p>
                        
                        <?php if(isset($item['kategori'])): ?>
                        <span class="product-category"><?= htmlspecialchars($item['kategori']) ?></span>
                        <?php endif; ?>
                        
                        <p class="product-seller">
                            <i class="bi bi-shop me-1"></i> <?= htmlspecialchars($item['nama_penjual'] ?? 'Toko BOOKIE') ?>
                        </p>
                        
                        <div class="product-price">Rp <?= number_format($item['harga'], 0, ',', '.') ?></div>
                        
                        <div class="product-stock">
                            <i class="bi bi-box me-1"></i> Stok: <?= $item['stok'] ?>
                        </div>
                        
                        <button class="btn-add-cart add-to-cart" 
                                data-id="<?= $item['id'] ?>" 
                                data-nama="<?= htmlspecialchars($item['nama_produk']) ?>"
                                <?= $item['stok'] == 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus me-2"></i> 
                            <?= $item['stok'] == 0 ? 'Stok Habis' : 'Tambah ke Keranjang' ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Lihat Semua Link -->
        <div class="text-center mt-4">
            <a href="produk.php" class="btn btn-outline-purple px-5 py-2">
                Lihat Semua Produk <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-emoji-frown"></i>
            <h5>Belum Ada Produk</h5>
            <p class="text-muted">Belum ada produk yang tersedia saat ini.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `produk.php?search=${encodeURIComponent(query)}`;
            }
        }
    });
}

// Add to cart functionality
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.dataset.id;
        const productName = this.dataset.nama;
        const originalText = this.innerHTML;
        
        // Loading state
        this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Menambahkan...';
        this.classList.add('btn-loading');
        this.disabled = true;
        
        // Kirim request AJAX
        fetch('ajax/add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&qty=1`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update button
                this.innerHTML = '<i class="bi bi-check-circle me-2"></i> Ditambahkan!';
                this.style.background = '#28a745';
                
                // Update badge keranjang
                const cartBadge = document.querySelector('a[href="keranjang.php"] .badge');
                if (cartBadge) {
                    const currentCount = parseInt(cartBadge.textContent) || 0;
                    cartBadge.textContent = currentCount + 1;
                } else {
                    // Buat badge baru
                    const cartLink = document.querySelector('a[href="keranjang.php"]');
                    const badge = document.createElement('span');
                    badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    badge.style.fontSize = '0.6rem';
                    badge.textContent = '1';
                    cartLink.appendChild(badge);
                }
                
                // Reset button setelah 2 detik
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.background = '#6f42c1';
                    this.classList.remove('btn-loading');
                    this.disabled = false;
                }, 2000);
            } else {
                alert(data.message || 'Gagal menambahkan ke keranjang');
                this.innerHTML = originalText;
                this.style.background = '#6f42c1';
                this.classList.remove('btn-loading');
                this.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menambahkan ke keranjang');
            this.innerHTML = originalText;
            this.style.background = '#6f42c1';
            this.classList.remove('btn-loading');
            this.disabled = false;
        });
    });
});
</script>

<style>
.text-purple {
    color: #6f42c1;
}

.btn-outline-purple {
    border: 2px solid #6f42c1;
    color: #6f42c1;
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-outline-purple:hover {
    background: #6f42c1;
    color: white;
    transform: translateY(-2px);
}
</style>

</body>
</html>