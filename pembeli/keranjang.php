<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

$idPembeli = $_SESSION['user']['id'];

// Query keranjang dengan struktur database yang benar
$query = "SELECT 
    k.id as keranjang_id,
    k.jumlah as qty,
    p.*,
    u.nama as nama_penjual,
    (k.jumlah * p.harga) as subtotal
FROM keranjang k
JOIN produk p ON k.id_produk = p.id
JOIN users u ON p.id_penjual = u.id
WHERE k.id_user = '$idPembeli'
ORDER BY k.id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error query: " . mysqli_error($conn));
}

$cart_items = [];
$total_price = 0;
$total_items = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $cart_items[] = $row;
    $total_price += $row['subtotal'];
    $total_items += $row['qty'];
}

// Ambil daftar penjual unik untuk grouping
$penjual_list = [];
foreach ($cart_items as $item) {
    if (!isset($penjual_list[$item['id_penjual']])) {
        $penjual_list[$item['id_penjual']] = [
            'nama' => $item['nama_penjual'],
            'items' => []
        ];
    }
    $penjual_list[$item['id_penjual']]['items'][] = $item;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Header */
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        
        .page-subtitle {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .btn-outline-purple {
            border: 2px solid #6f42c1;
            color: #6f42c1;
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-outline-purple:hover {
            background: #6f42c1;
            color: white;
        }
        
        .btn-purple {
            background: #6f42c1;
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-purple:hover {
            background: #5a32a3;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-danger {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
        }
        
        /* Seller Card */
        .seller-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .seller-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .seller-header i {
            color: #6f42c1;
            font-size: 1.2rem;
        }
        
        .seller-name {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .seller-checkbox {
            margin-right: 10px;
        }
        
        /* Cart Item */
        .cart-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item:hover {
            background: #fafafa;
        }
        
        .item-checkbox {
            width: 30px;
        }
        
        .item-image {
            width: 80px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex: 1;
            min-width: 200px;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .item-author {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: #28a745;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .item-stock {
            font-size: 0.8rem;
            color: #666;
        }
        
        .item-stock.warning {
            color: #dc3545;
            font-weight: 500;
        }
        
        /* Quantity Control */
        .qty-control {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 8px;
        }
        
        .qty-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .qty-btn:hover:not(:disabled) {
            background: #6f42c1;
            color: white;
            border-color: #6f42c1;
        }
        
        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #e9ecef;
        }
        
        .qty-input {
            width: 50px;
            height: 32px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            background: white;
            transition: all 0.2s ease;
        }
        
        .qty-input:focus {
            outline: none;
            border-color: #6f42c1;
            box-shadow: 0 0 0 2px rgba(111, 66, 193, 0.1);
        }
        
        .item-subtotal {
            min-width: 120px;
            text-align: right;
        }
        
        .subtotal-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 3px;
        }
        
        .subtotal-value {
            font-weight: 700;
            color: #28a745;
            font-size: 1.1rem;
        }
        
        .remove-item {
            color: #dc3545;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 5px 0;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .remove-item:hover {
            color: #c82333;
            transform: scale(1.05);
        }
        
        /* Cart Actions */
        .cart-actions {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Summary Card */
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            position: sticky;
            top: 20px;
        }
        
        .summary-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #666;
            font-size: 0.95rem;
        }
        
        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .summary-value.total {
            color: #28a745;
            font-size: 1.3rem;
        }
        
        .empty-cart {
            background: white;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart h4 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-cart p {
            margin-bottom: 20px;
        }
        
        /* Checkbox style */
        .cart-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #6f42c1;
        }
        
        /* Toast notification */
        .cart-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .cart-item .row {
                gap: 10px;
            }
            
            .item-subtotal {
                text-align: left;
                margin-top: 10px;
            }
            
            .qty-control {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="bi bi-cart3 me-2" style="color: #6f42c1;"></i> Keranjang Belanja
            </h1>
            <p class="page-subtitle">Kelola item yang akan Anda beli</p>
        </div>
        <div>
            <a href="produk.php" class="btn-outline-purple">
                <i class="bi bi-arrow-left me-2"></i>Lanjut Belanja
            </a>
        </div>
    </div>

    <?php if (count($cart_items) > 0): ?>
    <div class="row">
        <div class="col-lg-8">
            <!-- Cart Items by Seller -->
            <?php foreach ($penjual_list as $penjual_id => $penjual): ?>
            <div class="seller-card">
                <div class="seller-header">
                    <i class="bi bi-shop"></i>
                    <span class="seller-name"><?= htmlspecialchars($penjual['nama']) ?></span>
                    <input type="checkbox" class="cart-checkbox seller-checkbox" 
                           data-seller="<?= $penjual_id ?>" checked>
                </div>
                
                <?php foreach ($penjual['items'] as $item): ?>
                <div class="cart-item" data-cart-id="<?= $item['keranjang_id'] ?>" id="item-<?= $item['keranjang_id'] ?>">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <input type="checkbox" class="cart-checkbox item-checkbox" 
                                   data-id="<?= $item['keranjang_id'] ?>"
                                   data-seller="<?= $penjual_id ?>"
                                   data-price="<?= $item['harga'] ?>"
                                   data-qty="<?= $item['qty'] ?>" checked>
                        </div>
                        
                        <div class="col-auto">
                            <div class="item-image">
                                <img src="<?= !empty($item['gambar']) ? '../uploads/'.$item['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                                     alt="<?= htmlspecialchars($item['nama_produk']) ?>"
                                     onerror="this.src='../assets/img/book-placeholder.png'">
                            </div>
                        </div>
                        
                        <div class="col">
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                <div class="item-author"><?= htmlspecialchars($item['pengarang'] ?? '-') ?></div>
                                <div class="item-price">Rp <?= number_format($item['harga'], 0, ',', '.') ?></div>
                                <div class="item-stock <?= $item['stok'] < 5 ? 'warning' : '' ?>">
                                    <i class="bi bi-box"></i> Stok: <?= $item['stok'] ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-auto">
                            <div class="qty-control">
                                <button class="qty-btn qty-minus" 
                                        data-id="<?= $item['keranjang_id'] ?>"
                                        <?= $item['qty'] <= 1 ? 'disabled' : '' ?>>
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" 
                                       class="qty-input" 
                                       value="<?= $item['qty'] ?>" 
                                       min="1" 
                                       max="<?= min($item['stok'], 99) ?>"
                                       data-id="<?= $item['keranjang_id'] ?>"
                                       data-price="<?= $item['harga'] ?>"
                                       data-original-qty="<?= $item['qty'] ?>"
                                       readonly>
                                <button class="qty-btn qty-plus" 
                                        data-id="<?= $item['keranjang_id'] ?>"
                                        <?= $item['qty'] >= min($item['stok'], 99) ? 'disabled' : '' ?>>
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-auto">
                            <div class="item-subtotal">
                                <div class="subtotal-label">Subtotal</div>
                                <div class="subtotal-value" data-id="<?= $item['keranjang_id'] ?>">
                                    Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                </div>
                                <div class="remove-item" data-id="<?= $item['keranjang_id'] ?>">
                                    <i class="bi bi-trash"></i> Hapus
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- Cart Actions -->
            <div class="cart-actions">
                <div class="select-all">
                    <input type="checkbox" class="cart-checkbox" id="selectAll" checked>
                    <label for="selectAll">Pilih Semua Item</label>
                </div>
                <button class="btn-outline-danger" id="deleteSelected">
                    <i class="bi bi-trash"></i> Hapus Item Terpilih
                </button>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Order Summary -->
            <div class="summary-card">
                <div class="summary-title">Ringkasan Belanja</div>
                
                <div class="summary-row">
                    <span>Total Harga (<span id="totalItems"><?= $total_items ?></span> item)</span>
                    <span class="summary-value" id="totalHarga">Rp <?= number_format($total_price, 0, ',', '.') ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Biaya Pengiriman</span>
                    <span class="summary-value" id="ongkir">Rp 10.000</span>
                </div>
                
                <div class="summary-row total">
                    <span>Total Belanja</span>
                    <span class="summary-value total" id="totalBayar">
                        Rp <?= number_format($total_price + 10000, 0, ',', '.') ?>
                    </span>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="checkout.php" class="btn-purple" id="checkoutBtn">
                        <i class="bi bi-credit-card me-2"></i>Checkout (<?= $total_items ?>)
                    </a>
                </div>
                
                <div class="alert alert-info mt-4 mb-0 small">
                    <i class="bi bi-info-circle me-2"></i>
                    Pastikan stok produk cukup sebelum melakukan checkout.
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Empty Cart -->
    <div class="empty-cart">
        <i class="bi bi-cart-x"></i>
        <h4>Keranjang Belanja Kosong</h4>
        <p>Anda belum menambahkan produk apapun ke keranjang</p>
        <a href="produk.php" class="btn-purple">
            <i class="bi bi-shop me-2"></i>Mulai Belanja
        </a>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show cart-toast`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // Update quantity function - DENGAN URUTAN YANG BENAR
    function updateQuantity(cartId, newQty) {
        console.log('updateQuantity called with:', {cartId, newQty});
        
        // VALIDASI: Pastikan cartId valid
        if (!cartId || cartId <= 0) {
            console.error('Invalid cartId:', cartId);
            showToast('ID keranjang tidak valid', 'danger');
            return;
        }
        
        // VALIDASI: Parse newQty ke integer
        let parsedQty = parseInt(newQty);
        if (isNaN(parsedQty) || parsedQty < 1) {
            console.error('Invalid quantity:', newQty);
            showToast('Jumlah tidak valid', 'danger');
            
            // Rollback ke nilai sebelumnya
            const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
            if (input && input.dataset.originalQty) {
                input.value = input.dataset.originalQty;
            }
            return;
        }
        
        console.log('Sending update:', {cartId, parsedQty});
        
        fetch('ajax/update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${cartId}&qty=${parsedQty}`
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('HTTP error ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                // 1. Update subtotal item (UI kiri)
                const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
                if (!input) return;
                
                const price = parseFloat(input.dataset.price);
                const subtotal = price * parsedQty;
                const subtotalEl = document.querySelector(`.subtotal-value[data-id="${cartId}"]`);
                if (subtotalEl) {
                    subtotalEl.textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
                }
                
                // 2. Update button states
                const minusBtn = document.querySelector(`.qty-minus[data-id="${cartId}"]`);
                const plusBtn = document.querySelector(`.qty-plus[data-id="${cartId}"]`);
                const maxStock = parseInt(input?.max || 99);
                
                if (minusBtn) minusBtn.disabled = parsedQty <= 1;
                if (plusBtn) plusBtn.disabled = parsedQty >= maxStock;
                
                // 3. UPDATE CHECKBOX DATA TERLEBIH DAHULU (ini penting!)
                const checkbox = document.querySelector(`.item-checkbox[data-id="${cartId}"]`);
                if (checkbox) {
                    checkbox.dataset.qty = parsedQty;
                }
                
                // 4. Update original qty untuk rollback
                if (input) {
                    input.dataset.originalQty = parsedQty;
                }
                
                // 5. BARU update totals (karena baca dari checkbox.dataset.qty)
                updateTotals();
                
                showToast('Kuantitas berhasil diperbarui', 'success');
            } else {
                showToast(data.message || 'Gagal memperbarui kuantitas', 'danger');
                
                // Rollback ke nilai sebelumnya
                const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
                if (input && input.dataset.originalQty) {
                    input.value = input.dataset.originalQty;
                }
            }
        })
        .catch(err => {
            console.error('Error updating quantity:', err);
            showToast('Terjadi kesalahan: ' + err.message, 'danger');
            
            // Rollback ke nilai sebelumnya
            const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
            if (input && input.dataset.originalQty) {
                input.value = input.dataset.originalQty;
            }
        });
    }

    // Remove item function
    function removeItem(cartId) {
        if (!cartId || cartId <= 0) {
            showToast('ID keranjang tidak valid', 'danger');
            return;
        }
        
        if (!confirm('Hapus produk dari keranjang?')) return;
        
        fetch('ajax/delete_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${cartId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const item = document.getElementById(`item-${cartId}`);
                if (item) {
                    const sellerCard = item.closest('.seller-card');
                    item.remove();
                    
                    // Check if seller card is empty
                    if (sellerCard && sellerCard.querySelectorAll('.cart-item').length === 0) {
                        sellerCard.remove();
                    }
                    
                    updateTotals();
                    
                    // Check if cart is empty
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        setTimeout(() => location.reload(), 500);
                    }
                    
                    showToast('Item berhasil dihapus', 'success');
                }
            } else {
                showToast(data.message || 'Gagal menghapus item', 'danger');
            }
        })
        .catch(err => {
            console.error('Error removing item:', err);
            showToast('Terjadi kesalahan saat menghapus item', 'danger');
        });
    }

    // Update totals function
    function updateTotals() {
        let totalItems = 0;
        let totalHarga = 0;
        
        document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
            const qty = parseInt(checkbox.dataset.qty) || 0;
            const price = parseFloat(checkbox.dataset.price) || 0;
            totalItems += qty;
            totalHarga += qty * price;
        });
        
        const ongkir = 10000;
        const totalBayar = totalHarga + ongkir;
        
        // Update elemen
        const totalItemsEl = document.getElementById('totalItems');
        const totalHargaEl = document.getElementById('totalHarga');
        const totalBayarEl = document.getElementById('totalBayar');
        const checkoutBtn = document.getElementById('checkoutBtn');
        
        if (totalItemsEl) totalItemsEl.textContent = totalItems;
        if (totalHargaEl) totalHargaEl.textContent = `Rp ${totalHarga.toLocaleString('id-ID')}`;
        if (totalBayarEl) totalBayarEl.textContent = `Rp ${totalBayar.toLocaleString('id-ID')}`;
        if (checkoutBtn) checkoutBtn.innerHTML = `<i class="bi bi-credit-card me-2"></i>Checkout (${totalItems})`;
    }

    // Update sidebar badge
    function updateSidebarBadge() {
        const totalItems = document.querySelectorAll('.cart-item').length;
        const sidebarBadge = document.querySelector('.sidebar a[href="keranjang.php"] .badge');
        
        if (sidebarBadge) {
            if (totalItems > 0) {
                sidebarBadge.textContent = totalItems;
                sidebarBadge.style.display = 'inline-block';
            } else {
                sidebarBadge.style.display = 'none';
            }
        }
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Cart page loaded');
        
        // Minus button
        document.querySelectorAll('.qty-minus').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const cartId = this.dataset.id;
                console.log('Minus clicked - cartId:', cartId);
                
                const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
                if (!input) {
                    console.error('Input not found for cartId:', cartId);
                    return;
                }
                
                // Ambil nilai saat ini
                let currentQty = parseInt(input.value);
                if (isNaN(currentQty) || currentQty < 1) {
                    currentQty = 1;
                }
                
                // Simpan nilai asli untuk rollback
                input.dataset.originalQty = currentQty;
                
                let newQty = currentQty - 1;
                if (newQty < 1) newQty = 1;
                
                console.log('Minus calculation:', {cartId, currentQty, newQty});
                
                // Update UI
                input.value = newQty;
                
                // Kirim ke server
                updateQuantity(cartId, newQty);
            });
        });
        
        // Plus button
        document.querySelectorAll('.qty-plus').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const cartId = this.dataset.id;
                console.log('Plus clicked - cartId:', cartId);
                
                const input = document.querySelector(`.qty-input[data-id="${cartId}"]`);
                if (!input) {
                    console.error('Input not found for cartId:', cartId);
                    return;
                }
                
                // Ambil nilai saat ini
                let currentQty = parseInt(input.value);
                if (isNaN(currentQty) || currentQty < 1) {
                    currentQty = 1;
                }
                
                // Simpan nilai asli untuk rollback
                input.dataset.originalQty = currentQty;
                
                let newQty = currentQty + 1;
                const max = parseInt(input.max) || 99;
                if (newQty > max) newQty = max;
                
                console.log('Plus calculation:', {cartId, currentQty, newQty, max});
                
                // Update UI
                input.value = newQty;
                
                // Kirim ke server
                updateQuantity(cartId, newQty);
            });
        });
        
        // Remove button
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const cartId = this.dataset.id;
                removeItem(cartId);
            });
        });
        
        // Item checkbox
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const sellerId = this.dataset.seller;
                const sellerCheckbox = document.querySelector(`.seller-checkbox[data-seller="${sellerId}"]`);
                const sellerItems = document.querySelectorAll(`.item-checkbox[data-seller="${sellerId}"]`);
                const allChecked = Array.from(sellerItems).every(cb => cb.checked);
                
                if (sellerCheckbox) sellerCheckbox.checked = allChecked;
                
                const selectAll = document.getElementById('selectAll');
                const allItems = document.querySelectorAll('.item-checkbox');
                const allChecked2 = Array.from(allItems).every(cb => cb.checked);
                
                if (selectAll) selectAll.checked = allChecked2;
                
                updateTotals();
            });
        });
        
        // Seller checkbox
        document.querySelectorAll('.seller-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const sellerId = this.dataset.seller;
                const sellerItems = document.querySelectorAll(`.item-checkbox[data-seller="${sellerId}"]`);
                
                sellerItems.forEach(cb => {
                    cb.checked = checkbox.checked;
                });
                
                const selectAll = document.getElementById('selectAll');
                const allItems = document.querySelectorAll('.item-checkbox');
                const allChecked = Array.from(allItems).every(cb => cb.checked);
                
                if (selectAll) selectAll.checked = allChecked;
                
                updateTotals();
            });
        });
        
        // Select all
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const isChecked = this.checked;
            
            document.querySelectorAll('.item-checkbox, .seller-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            
            updateTotals();
        });
        
        // Delete selected
        document.getElementById('deleteSelected')?.addEventListener('click', function() {
            const selectedItems = document.querySelectorAll('.item-checkbox:checked');
            
            if (selectedItems.length === 0) {
                showToast('Pilih item yang akan dihapus', 'warning');
                return;
            }
            
            if (!confirm(`Hapus ${selectedItems.length} item dari keranjang?`)) return;
            
            // Hapus satu per satu
            selectedItems.forEach(checkbox => {
                removeItem(checkbox.dataset.id);
            });
        });
        
        // Initial update
        updateTotals();
    });
</script>

</body>
</html>