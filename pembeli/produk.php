<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

// Ambil parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$kategori_id = $_GET['kategori'] ?? 0;
$sort = $_GET['sort'] ?? 'terbaru';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Query produk dengan filter
$where = "WHERE p.stok > 0";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where .= " AND (p.nama_produk LIKE '%$search%' 
                    OR p.pengarang LIKE '%$search%' 
                    OR u.nama LIKE '%$search%')";
}
if ($kategori_id > 0) {
    $where .= " AND p.kategori_id = $kategori_id";
}

// Sorting
$order_by = "ORDER BY p.created_at DESC";
if ($sort == 'terlaris') {
    $order_by = "ORDER BY terjual DESC, p.created_at DESC";
} elseif ($sort == 'termahal') {
    $order_by = "ORDER BY p.harga DESC";
} elseif ($sort == 'termurah') {
    $order_by = "ORDER BY p.harga ASC";
}

// Query produk dengan SUM terjual
$query = "SELECT 
    p.*,
    k.nama_kategori,
    u.nama as nama_penjual,
    u.status as status_penjual,
    COALESCE(SUM(td.jumlah), 0) as terjual
FROM produk p
LEFT JOIN kategori k ON p.kategori_id = k.id
LEFT JOIN users u ON p.id_penjual = u.id
LEFT JOIN transaksi_detail td ON p.id = td.id_produk
LEFT JOIN transaksi t ON td.id_transaksi = t.id AND t.status = 'selesai'
$where
GROUP BY p.id
$order_by
LIMIT $offset, $per_page";

$result = mysqli_query($conn, $query);

// Debug jika error
if (!$result) {
    die("Error query: " . mysqli_error($conn));
}

$produk_list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $produk_list[] = $row;
}

// Total produk untuk pagination
$count_query = "SELECT COUNT(DISTINCT p.id) as total 
                FROM produk p
                LEFT JOIN users u ON p.id_penjual = u.id
                $where";
$count_result = mysqli_query($conn, $count_query);
$total_produk = mysqli_fetch_assoc($count_result)['total'] ?? 0;
$total_pages = ceil($total_produk / $per_page);

// Ambil kategori untuk dropdown
$kategori_result = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori");
$kategori_list = [];
while ($k = mysqli_fetch_assoc($kategori_result)) {
    $kategori_list[] = $k;
}

// Hitung keranjang untuk badge
$idPembeli = $_SESSION['user']['id'];
$qCart = mysqli_query($conn, "SELECT SUM(jumlah) as total FROM keranjang WHERE id_user='$idPembeli'");
$cart = mysqli_fetch_assoc($qCart);
$cart_count = $cart['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Produk - BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .product-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            background: white;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .product-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .product-title {
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
            height: 2.8rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .product-price {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
        }
        .stock-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 3px;
        }
        .seller-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .sort-btn.active {
            background-color: #6f42c1;
            color: white;
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .pagination .page-item.active .page-link {
            background-color: #6f42c1;
            border-color: #6f42c1;
        }
        .btn-purple {
            background-color: #6f42c1;
            color: white;
        }
        .btn-purple:hover {
            background-color: #5a32a3;
            color: white;
        }
        .btn-outline-purple {
            border-color: #6f42c1;
            color: #6f42c1;
        }
        .btn-outline-purple:hover {
            background-color: #6f42c1;
            color: white;
        }
        .badge-purple {
            background-color: #6f42c1;
            color: white;
        }
    </style>
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<main class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold">Semua Produk</h3>
                <p class="text-muted mb-0">Temukan buku yang Anda butuhkan</p>
            </div>
            <div>
                <a href="keranjang.php" class="btn btn-purple position-relative">
                    <i class="bi bi-cart"></i> Keranjang
                    <?php if ($cart_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $cart_count ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-card shadow-sm">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Cari judul buku, penulis, atau penjual..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-purple" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="kategori">
                        <option value="0">Semua Kategori</option>
                        <?php foreach($kategori_list as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= $kategori_id == $kat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="sort">
                        <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="terlaris" <?= $sort == 'terlaris' ? 'selected' : '' ?>>Terlaris</option>
                        <option value="termurah" <?= $sort == 'termurah' ? 'selected' : '' ?>>Termurah</option>
                        <option value="termahal" <?= $sort == 'termahal' ? 'selected' : '' ?>>Termahal</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Sorting Tabs -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="?sort=terbaru&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>" 
               class="btn btn-outline-purple sort-btn <?= $sort == 'terbaru' ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> Terbaru
            </a>
            <a href="?sort=terlaris&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>" 
               class="btn btn-outline-purple sort-btn <?= $sort == 'terlaris' ? 'active' : '' ?>">
                <i class="bi bi-fire"></i> Terlaris
            </a>
            <a href="?sort=termurah&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>" 
               class="btn btn-outline-purple sort-btn <?= $sort == 'termurah' ? 'active' : '' ?>">
                <i class="bi bi-arrow-down"></i> Termurah
            </a>
            <a href="?sort=termahal&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>" 
               class="btn btn-outline-purple sort-btn <?= $sort == 'termahal' ? 'active' : '' ?>">
                <i class="bi bi-arrow-up"></i> Termahal
            </a>
        </div>

        <!-- Produk Grid -->
        <?php if (count($produk_list) > 0): ?>
        <div class="row g-4">
            <?php foreach($produk_list as $produk): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="product-card shadow-sm">
                    <div class="position-relative">
                        <!-- Path gambar langsung ke uploads/ -->
                        <img src="../uploads/<?= !empty($produk['gambar']) ? $produk['gambar'] : 'default-book.png' ?>" 
                             class="product-img" 
                             alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                             onerror="this.src='../assets/img/book-placeholder.png'">
                        
                        <?php if($produk['stok'] < 5 && $produk['stok'] > 0): ?>
                        <span class="position-absolute top-0 end-0 m-2 stock-badge bg-warning text-dark">
                            <i class="bi bi-exclamation-triangle"></i> Stok <?= $produk['stok'] ?>
                        </span>
                        <?php elseif($produk['stok'] == 0): ?>
                        <span class="position-absolute top-0 end-0 m-2 stock-badge bg-danger text-white">
                            <i class="bi bi-x-circle"></i> Habis
                        </span>
                        <?php endif; ?>
                        
                        <?php if($produk['terjual'] > 10): ?>
                        <span class="position-absolute top-0 start-0 m-2 stock-badge bg-danger text-white">
                            <i class="bi bi-fire"></i> Terlaris
                        </span>
                        <?php endif; ?>
                        
                        <?php if($produk['margin'] > 30): ?>
                        <span class="position-absolute bottom-0 end-0 m-2 stock-badge bg-success text-white">
                            <i class="bi bi-tag"></i> Diskon
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3">
                        <h6 class="product-title mb-1">
                            <?= htmlspecialchars($produk['nama_produk']) ?>
                        </h6>
                        <p class="text-muted mb-1 small"><?= htmlspecialchars($produk['pengarang'] ?? '-') ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark">
                                <?= htmlspecialchars($produk['nama_kategori'] ?? 'Umum') ?>
                            </span>
                            <div class="seller-info">
                                <i class="bi bi-shop"></i> 
                                <?= htmlspecialchars($produk['nama_penjual']) ?>
                                <?php if($produk['status_penjual'] == 'online'): ?>
                                <span class="badge bg-success badge-sm">Online</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="product-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></div>
                            <div class="text-muted small">
                                <i class="bi bi-cart-check"></i> <?= $produk['terjual'] ?> terjual
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if($produk['stok'] > 0): ?>
                            <button class="btn btn-purple btn-sm add-to-cart" data-id="<?= $produk['id'] ?>">
                                <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                            </button>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="bi bi-slash-circle"></i> Stok Habis
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-purple btn-sm btn-detail" data-id="<?= $produk['id'] ?>">
                                <i class="bi bi-info-circle"></i> Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>&sort=<?= $sort ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>&sort=<?= $sort ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_id ?>&sort=<?= $sort ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-search" style="font-size: 4rem; color: #6c757d;"></i>
            <h4 class="mt-3">Produk tidak ditemukan</h4>
            <p class="text-muted">Coba gunakan kata kunci lain atau lihat kategori yang tersedia</p>
            <a href="produk.php" class="btn btn-purple">Lihat Semua Produk</a>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Detail Produk -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Base URL untuk AJAX (pastikan path benar)
const ajaxUrl = 'ajax/';

// Test AJAX connection
fetch(ajaxUrl + 'get_cart_count.php')
    .then(response => {
        console.log('AJAX test response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('AJAX connection OK:', data);
    })
    .catch(error => {
        console.error('AJAX connection failed:', error);
        showToast('Koneksi ke server bermasalah', 'error');
    });

// Add to cart
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.getAttribute('data-id');
        const originalText = this.innerHTML;
        const originalClass = this.className;
        
        console.log('Adding product ID:', productId);
        
        // Tampilkan loading
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
        this.disabled = true;
        
        // Kirim request
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('qty', 1);
        
        fetch(ajaxUrl + 'add_to_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                this.innerHTML = '<i class="bi bi-check-circle"></i> Ditambahkan!';
                this.className = 'btn btn-success btn-sm w-100';
                
                // Update cart count
                updateCartCount();
                
                // Reset button setelah 2 detik
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.className = originalClass;
                    this.disabled = false;
                }, 2000);
                
                showToast('Produk berhasil ditambahkan ke keranjang', 'success');
            } else {
                showToast('Error: ' + (data.message || 'Gagal menambahkan'), 'error');
                this.innerHTML = originalText;
                this.className = originalClass;
                this.disabled = false;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showToast('Terjadi kesalahan jaringan: ' + error.message, 'error');
            this.innerHTML = originalText;
            this.className = originalClass;
            this.disabled = false;
        });
    });
});

// Function untuk update cart count
function updateCartCount() {
    fetch(ajaxUrl + 'get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge di sidebar
                const sidebarBadge = document.querySelector('.sidebar a[href="keranjang.php"] .badge');
                if (sidebarBadge) {
                    if (data.count > 0) {
                        sidebarBadge.textContent = data.count;
                        sidebarBadge.style.display = 'inline-block';
                    } else {
                        sidebarBadge.style.display = 'none';
                    }
                }
                
                // Update badge di header
                const headerBadge = document.querySelector('a[href="keranjang.php"] .badge');
                if (headerBadge && headerBadge !== sidebarBadge) {
                    if (data.count > 0) {
                        headerBadge.textContent = data.count;
                        headerBadge.style.display = 'inline-block';
                    } else {
                        headerBadge.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => console.error('Cart count error:', error));
}

// Toast notification
function showToast(message, type = 'info') {
    const oldToast = document.getElementById('custom-toast');
    if (oldToast) oldToast.remove();
    
    const toast = document.createElement('div');
    toast.id = 'custom-toast';
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '250px';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
}

// Detail produk modal
document.querySelectorAll('.btn-detail').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.getAttribute('data-id');
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        
        document.getElementById('detailContent').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-purple" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Memuat detail produk...</p>
            </div>
        `;
        
        modal.show();
        
        fetch(ajaxUrl + 'get_product_detail.php?id=' + productId)
            .then(response => {
                console.log('Detail response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Detail data:', data);
                
                if (data.success && data.product) {
                    const p = data.product;
                    document.getElementById('detailContent').innerHTML = `
                        <div class="row">
                            <div class="col-md-5 text-center">
                                <img src="../uploads/${p.gambar || 'default-book.png'}" 
                                     class="img-fluid rounded mb-3" 
                                     style="max-height: 300px; object-fit: cover;"
                                     onerror="this.src='../assets/img/book-placeholder.png'">
                            </div>
                            <div class="col-md-7">
                                <h4>${escapeHtml(p.nama_produk)}</h4>
                                <p class="text-muted">${escapeHtml(p.pengarang || '-')}</p>
                                <hr>
                                <div class="mb-3">
                                    <strong>Kategori:</strong>
                                    <span class="badge bg-purple ms-2">${escapeHtml(p.nama_kategori || 'Umum')}</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Penjual:</strong> ${escapeHtml(p.nama_penjual)}
                                    ${p.status_penjual === 'online' ? 
                                        '<span class="badge bg-success ms-2">Online</span>' : ''}
                                </div>
                                <div class="mb-3">
                                    <strong>Harga:</strong>
                                    <h4 class="text-success">Rp ${parseInt(p.harga).toLocaleString('id-ID')}</h4>
                                </div>
                                <div class="mb-3">
                                    <strong>Stok:</strong>
                                    ${p.stok > 0 ? 
                                        `<span class="badge bg-success">${p.stok} tersedia</span>` :
                                        '<span class="badge bg-danger">Habis</span>'}
                                </div>
                                <div class="mb-3">
                                    <strong>Deskripsi:</strong>
                                    <p>${escapeHtml(p.deskripsi || 'Tidak ada deskripsi')}</p>
                                </div>
                                <div class="d-grid gap-2">
                                    ${p.stok > 0 ? 
                                        `<button class="btn btn-purple add-to-cart-modal" data-id="${p.id}">
                                            <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                                        </button>` :
                                        '<button class="btn btn-secondary" disabled>Stok Habis</button>'}
                                    <button class="btn btn-outline-purple" data-bs-dismiss="modal">
                                        <i class="bi bi-x"></i> Tutup
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Add event untuk tombol di modal
                    document.querySelector('.add-to-cart-modal')?.addEventListener('click', function() {
                        const modalProductId = this.getAttribute('data-id');
                        bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
                        const addButton = document.querySelector(`.add-to-cart[data-id="${modalProductId}"]`);
                        if (addButton) {
                            addButton.click();
                        } else {
                            window.location.href = 'produk.php';
                        }
                    });
                } else {
                    document.getElementById('detailContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> Gagal memuat detail produk
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('detailContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Terjadi kesalahan: ${error.message}
                    </div>
                `;
            });
    });
});

// Function untuk escape HTML (mencegah XSS)
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Filter auto-submit
document.querySelectorAll('select[name="kategori"], select[name="sort"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});

// Update cart count setiap 30 detik
setInterval(updateCartCount, 30000);
</script>
</body>
</html>