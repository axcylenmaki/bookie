<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

$idPembeli = $_SESSION['user']['id'];
$namaPembeli = $_SESSION['user']['nama'];

// Ambil item dari keranjang
$query = "SELECT 
    k.jumlah as qty,
    p.*,
    u.nama as nama_penjual,
    u.id as penjual_id,
    (k.jumlah * p.harga) as subtotal
FROM keranjang k
JOIN produk p ON k.id_produk = p.id
JOIN users u ON p.id_penjual = u.id
WHERE k.id_user = '$idPembeli'";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error query: " . mysqli_error($conn));
}

$cart_items = [];
$total_price = 0;
$penjual_items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $cart_items[] = $row;
    $total_price += $row['subtotal'];
    
    $penjual_id = $row['penjual_id'];
    if (!isset($penjual_items[$penjual_id])) {
        // Get rekening penjual
        $qRekening = mysqli_query($conn, "
            SELECT * FROM rekening_penjual 
            WHERE id_penjual='$penjual_id'
        ");
        $rekening = mysqli_fetch_assoc($qRekening);
        
        $penjual_items[$penjual_id] = [
            'nama_penjual' => $row['nama_penjual'],
            'rekening' => $rekening,
            'items' => [],
            'subtotal' => 0,
            'total_bayar' => 0
        ];
    }
    $penjual_items[$penjual_id]['items'][] = $row;
    $penjual_items[$penjual_id]['subtotal'] += $row['subtotal'];
    $penjual_items[$penjual_id]['total_bayar'] = $penjual_items[$penjual_id]['subtotal'] + 10000; // ongkir
}

// Proses checkout jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($cart_items) > 0) {
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $kota = mysqli_real_escape_string($conn, $_POST['kota']);
    $kodepos = mysqli_real_escape_string($conn, $_POST['kodepos']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');
    
    $all_success = true;
    $created_transactions = [];
    
    // ID user sistem (pastikan user dengan ID ini ada di database)
    $system_user_id = 999;
    
    // Buat transaksi untuk setiap penjual
    foreach ($penjual_items as $penjual_id => $data) {
        $kode_transaksi = 'TRX' . date('Ymd') . str_pad($penjual_id, 3, '0', STR_PAD_LEFT) . str_pad($idPembeli, 3, '0', STR_PAD_LEFT) . rand(100, 999);
        $ongkir = 10000;
        $total_bayar = $data['subtotal'] + $ongkir;
        
        // Handle file upload untuk penjual ini
        $bukti_transfer = '';
        $file_key = 'bukti_transfer_' . $penjual_id;
        
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            $file_name = time() . '_' . $penjual_id . '_' . basename($_FILES[$file_key]['name']);
            $target_dir = "../uploads/bukti_transfer/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) {
                $bukti_transfer = $file_name;
            }
        }
        
        // Insert transaksi
        $insert_transaksi = "INSERT INTO transaksi (
            kode_transaksi, id_user, penjual_id, total, status,
            bukti_transfer, catatan, created_at
        ) VALUES (
            '$kode_transaksi', '$idPembeli', '$penjual_id', '$total_bayar',
            'pending', '$bukti_transfer', '$catatan', NOW()
        )";
        
        if (mysqli_query($conn, $insert_transaksi)) {
            $transaksi_id = mysqli_insert_id($conn);
            $created_transactions[] = $transaksi_id;
            
            // Insert alamat pengiriman
            $insert_alamat = "INSERT INTO alamat_pengiriman (
                transaksi_id, alamat, kota, kodepos, telepon, created_at
            ) VALUES (
                '$transaksi_id', '$alamat', '$kota', '$kodepos', '$telepon', NOW()
            )";
            mysqli_query($conn, $insert_alamat);
            
            // Insert transaksi detail
            foreach ($data['items'] as $item) {
                $insert_detail = "INSERT INTO transaksi_detail (
                    id_transaksi, id_produk, jumlah, harga
                ) VALUES (
                    '$transaksi_id', '{$item['id']}', '{$item['qty']}', '{$item['harga']}'
                )";
                
                if (mysqli_query($conn, $insert_detail)) {
                    // Kurangi stok
                    mysqli_query($conn, "UPDATE produk SET stok = stok - {$item['qty']} WHERE id = '{$item['id']}'");
                } else {
                    $all_success = false;
                }
            }
            
            // Buat notifikasi untuk penjual
            $notif_msg = "Pesanan baru dari $namaPembeli - $kode_transaksi - Total: Rp " . number_format($total_bayar, 0, ',', '.');
            mysqli_query($conn, "INSERT INTO notifikasi (id_user, pesan, status, created_at) 
                                 VALUES ('$penjual_id', '$notif_msg', 'unread', NOW())");
            
            // Buat room chat otomatis
            $checkRoom = mysqli_query($conn, "SELECT id FROM chat_rooms WHERE id_pembeli='$idPembeli' AND id_penjual='$penjual_id'");
            if (mysqli_num_rows($checkRoom) == 0) {
                mysqli_query($conn, "INSERT INTO chat_rooms (id_pembeli, id_penjual, created_at) VALUES ('$idPembeli', '$penjual_id', NOW())");
                $room_id = mysqli_insert_id($conn);
            } else {
                $room = mysqli_fetch_assoc($checkRoom);
                $room_id = $room['id'];
            }
            
            // ========== PERBAIKAN: Auto reply sistem di chat dengan ID user sistem ==========
            $pesan_sistem = "🛒 **PESANAN BARU** 🛒\n\n";
            $pesan_sistem .= "Kode Transaksi: #$kode_transaksi\n";
            $pesan_sistem .= "Dari: $namaPembeli\n";
            $pesan_sistem .= "Total: Rp " . number_format($total_bayar, 0, ',', '.') . "\n";
            $pesan_sistem .= "Status: Menunggu Pembayaran\n\n";
            $pesan_sistem .= "Silakan cek detail pesanan di menu Transaksi.";
            
            // Escape string untuk query
            $pesan_sistem_escaped = mysqli_real_escape_string($conn, $pesan_sistem);
            
            $insert_chat = "INSERT INTO chat (id_room, id_pengirim, penerima_id, pesan, created_at, dibaca)
                            VALUES ('$room_id', '$system_user_id', '$penjual_id', '$pesan_sistem_escaped', NOW(), 0)";
            
            if (!mysqli_query($conn, $insert_chat)) {
                // Log error tapi jangan gagalkan transaksi
                error_log("Gagal insert chat sistem: " . mysqli_error($conn));
            }
            // ========== AKHIR PERBAIKAN ==========
            
        } else {
            $all_success = false;
        }
    }
    
    if ($all_success) {
        // Hapus keranjang
        mysqli_query($conn, "DELETE FROM keranjang WHERE id_user = '$idPembeli'");
        
        // Redirect ke status transaksi
        header("Location: status.php?success=1");
        exit;
    } else {
        $error_msg = "Terjadi kesalahan saat memproses checkout. Silakan coba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BOOKIE</title>
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
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        
        .btn-purple {
            background: #6f42c1;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-purple:hover {
            background: #5a32a3;
            color: white;
        }
        
        .btn-outline-purple {
            border: 2px solid #6f42c1;
            color: #6f42c1;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            background: white;
            transition: all 0.3s;
        }
        
        .btn-outline-purple:hover {
            background: #6f42c1;
            color: white;
        }
        
        .penjual-section {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .penjual-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .penjual-header i {
            margin-right: 10px;
        }
        
        .rekening-info {
            background-color: #f0f7ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #6f42c1;
        }
        
        .rekening-info h6 {
            color: #6f42c1;
            margin-bottom: 10px;
        }
        
        .upload-section {
            border: 2px dashed #6f42c1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            background-color: #f0f0ff;
            border-color: #5a32a3;
        }
        
        .total-per-penjual {
            background-color: #d4edda;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 600;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        
        .sticky-summary {
            position: sticky;
            top: 20px;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-item {
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .product-thumb {
            width: 50px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .file-preview {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
<?php include "includes/sidebar.php"; ?>

<main class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1 fw-bold">Checkout</h3>
                <p class="text-muted mb-0">Lengkapi data pengiriman dan upload bukti transfer</p>
            </div>
            <a href="keranjang.php" class="btn-outline-purple">
                <i class="bi bi-arrow-left"></i> Kembali ke Keranjang
            </a>
        </div>
        
        <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= $error_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (count($cart_items) == 0): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="bi bi-cart-x" style="font-size: 4rem; color: #6c757d;"></i>
                <h4 class="mt-3">Keranjang Kosong</h4>
                <p class="text-muted">Tidak ada item yang bisa di-checkout</p>
                <a href="produk.php" class="btn btn-purple">
                    <i class="bi bi-shop me-2"></i>Belanja Sekarang
                </a>
            </div>
        </div>
        <?php else: ?>
        
        <form method="POST" enctype="multipart/form-data" id="checkoutForm">
            <div class="row">
                <div class="col-lg-7">
                    <!-- Data Pengiriman -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-truck me-2" style="color: #6f42c1;"></i>
                                Data Pengiriman
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="alamat" class="form-control" rows="3" required 
                                          placeholder="Jalan, nomor rumah, RT/RW, kelurahan/desa"></textarea>
                                <small class="text-muted">Pastikan alamat lengkap untuk memudahkan pengiriman</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Kota/Kabupaten <span class="text-danger">*</span></label>
                                    <input type="text" name="kota" class="form-control" required placeholder="Contoh: Jakarta Selatan">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Kode Pos <span class="text-danger">*</span></label>
                                    <input type="text" name="kodepos" class="form-control" required placeholder="12345" maxlength="5">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">No. Telepon/WhatsApp <span class="text-danger">*</span></label>
                                <input type="text" name="telepon" class="form-control" required placeholder="081234567890">
                                <small class="text-muted">Kurir akan menghubungi jika diperlukan</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Catatan untuk Penjual</label>
                                <textarea name="catatan" class="form-control" rows="2" 
                                          placeholder="Contoh: Warna bebas, tolong dibungkus bubble wrap, dll."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pembayaran per Penjual -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold">
                                <i class="bi bi-credit-card me-2" style="color: #6f42c1;"></i>
                                Pembayaran per Penjual
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="bi bi-info-circle fs-4 me-3"></i>
                                <div>
                                    <strong>Transfer ke masing-masing penjual</strong><br>
                                    Lakukan transfer sesuai total per penjual, lalu upload bukti transfer.
                                </div>
                            </div>
                            
                            <?php foreach($penjual_items as $penjual_id => $data): 
                                $rekening = $data['rekening'];
                            ?>
                            <div class="penjual-section">
                                <div class="penjual-header d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-shop"></i> <?= htmlspecialchars($data['nama_penjual']) ?>
                                    </span>
                                    <span class="badge bg-white text-purple">
                                        <?= count($data['items']) ?> item
                                    </span>
                                </div>
                                
                                <div class="p-4">
                                    <!-- Info Rekening -->
                                    <?php if($rekening): ?>
                                    <div class="rekening-info">
                                        <h6><i class="bi bi-bank me-2"></i> Transfer ke:</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">Bank</small>
                                                <strong><?= $rekening['bank'] ?></strong>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">No. Rekening</small>
                                                <strong><?= $rekening['no_rekening'] ?></strong>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <small class="text-muted d-block">Atas Nama</small>
                                                <strong><?= $rekening['nama_pemilik'] ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Penjual belum mengatur rekening. Silakan hubungi penjual untuk informasi pembayaran.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Daftar Produk -->
                                    <div class="mb-3">
                                        <small class="text-muted d-block mb-2">Produk yang dibeli:</small>
                                        <?php foreach($data['items'] as $item): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="<?= !empty($item['gambar']) ? '../uploads/'.$item['gambar'] : '../assets/img/book-placeholder.png' ?>" 
                                                 class="product-thumb me-2"
                                                 alt=""
                                                 onerror="this.src='../assets/img/book-placeholder.png'">
                                            <div class="flex-grow-1">
                                                <small class="d-block"><?= htmlspecialchars($item['nama_produk']) ?></small>
                                                <small class="text-muted"><?= $item['qty'] ?> x Rp <?= number_format($item['harga'], 0, ',', '.') ?></small>
                                            </div>
                                            <small class="fw-semibold">Rp <?= number_format($item['qty'] * $item['harga'], 0, ',', '.') ?></small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Total untuk Penjual Ini -->
                                    <div class="total-per-penjual">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Total Transfer ke <?= htmlspecialchars($data['nama_penjual']) ?>:</span>
                                            <h5 class="mb-0 text-success">Rp <?= number_format($data['total_bayar'], 0, ',', '.') ?></h5>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            (Subtotal: Rp <?= number_format($data['subtotal'], 0, ',', '.') ?> + Ongkir: Rp 10.000)
                                        </small>
                                    </div>
                                    
                                    <!-- Upload Bukti Transfer -->
                                    <div class="upload-section">
                                        <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #6f42c1;"></i>
                                        <h6 class="mt-2 fw-semibold">Upload Bukti Transfer</h6>
                                        <p class="text-muted small">
                                            Transfer <strong>Rp <?= number_format($data['total_bayar'], 0, ',', '.') ?></strong> 
                                            ke rekening <strong><?= $rekening['bank'] ?? 'Bank' ?></strong>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <input type="file" 
                                                   name="bukti_transfer_<?= $penjual_id ?>" 
                                                   class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg" 
                                                   required
                                                   data-penjual="<?= $penjual_id ?>"
                                                   data-total="<?= $data['total_bayar'] ?>">
                                            <small class="text-muted">Format: JPG, PNG. Max 2MB</small>
                                        </div>
                                        
                                        <div class="alert alert-light text-start">
                                            <small>
                                                <i class="bi bi-check-circle text-success me-1"></i>
                                                Pastikan bukti transfer jelas menunjukkan:
                                                <ul class="mb-0 mt-1 small">
                                                    <li>Nama pengirim</li>
                                                    <li>Jumlah: <strong>Rp <?= number_format($data['total_bayar'], 0, ',', '.') ?></strong></li>
                                                    <li>Tanggal transfer</li>
                                                    <li>Tujuan rekening</li>
                                                </ul>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-5">
                    <!-- Ringkasan Pesanan -->
                    <div class="summary-card sticky-summary">
                        <h5 class="fw-bold mb-3">
                            <i class="bi bi-receipt me-2" style="color: #6f42c1;"></i>
                            Ringkasan Pesanan
                        </h5>
                        
                        <!-- Daftar Penjual -->
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">Penjual:</small>
                            <?php foreach($penjual_items as $data): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <small><?= htmlspecialchars($data['nama_penjual']) ?></small>
                                <small class="text-muted"><?= count($data['items']) ?> item</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-item">
                            <small class="text-muted">Subtotal Produk</small>
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">Rp <?= number_format($total_price, 0, ',', '.') ?></span>
                                <small class="text-muted"><?= count($cart_items) ?> item</small>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <small class="text-muted">Total Ongkos Kirim</small>
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">Rp <?= number_format(count($penjual_items) * 10000, 0, ',', '.') ?></span>
                                <small class="text-muted"><?= count($penjual_items) ?> penjual × Rp 10.000</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Total per Penjual -->
                        <h6 class="mb-3">Total per Penjual:</h6>
                        <?php foreach($penjual_items as $data): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <small><?= htmlspecialchars($data['nama_penjual']) ?></small>
                            <small class="fw-semibold text-success">Rp <?= number_format($data['total_bayar'], 0, ',', '.') ?></small>
                        </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
                        <!-- Grand Total -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Total Semua</h5>
                            <h4 class="mb-0 text-success">
                                Rp <?= number_format($total_price + (count($penjual_items) * 10000), 0, ',', '.') ?>
                            </h4>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-purple btn-lg" id="submitBtn">
                                <i class="bi bi-check-circle me-2"></i>Konfirmasi Pembayaran
                            </button>
                        </div>
                        
                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Perhatian:</strong> Anda akan melakukan <?= count($penjual_items) ?> transfer terpisah ke masing-masing penjual.
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Dengan melanjutkan, Anda menyetujui <a href="#" class="text-decoration-none">Syarat & Ketentuan</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkoutForm');
    if (!form) return;
    
    // Preview file upload
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const penjualId = this.dataset.penjual;
            const total = parseInt(this.dataset.total);
            
            // Hapus preview lama
            const existingPreview = this.parentElement.querySelector('.file-preview');
            if (existingPreview) existingPreview.remove();
            
            if (file) {
                // Validasi ukuran (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB');
                    this.value = '';
                    return;
                }
                
                // Validasi tipe file
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Format file harus JPG atau PNG');
                    this.value = '';
                    return;
                }
                
                // Tampilkan preview sukses
                const preview = document.createElement('div');
                preview.className = 'file-preview mt-2';
                preview.innerHTML = `
                    <div class="alert alert-success py-2">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>${file.name}</strong> siap diupload
                        <br><small>Total: Rp ${total.toLocaleString('id-ID')}</small>
                    </div>
                `;
                this.parentElement.appendChild(preview);
            }
        });
    });
    
    // Validasi form sebelum submit
    form.addEventListener('submit', function(e) {
        const penjualCount = <?= count($penjual_items) ?>;
        const fileInputs = document.querySelectorAll('input[type="file"]');
        let allFilesUploaded = true;
        let uploadedCount = 0;
        
        fileInputs.forEach(input => {
            if (!input.files[0]) {
                allFilesUploaded = false;
            } else {
                uploadedCount++;
            }
        });
        
        if (!allFilesUploaded) {
            e.preventDefault();
            alert(`ERROR: Harap upload bukti transfer untuk SEMUA penjual!\n\nTerupload: ${uploadedCount} dari ${penjualCount} penjual`);
            return false;
        }
        
        // Konfirmasi final
        const totalTransfer = <?= count($penjual_items) ?>;
        const grandTotal = <?= $total_price + (count($penjual_items) * 10000) ?>;
        
        const confirmation = confirm(
            `Anda akan melakukan ${totalTransfer} transfer terpisah.\n\n` +
            `Total yang harus ditransfer: Rp ${grandTotal.toLocaleString('id-ID')}\n\n` +
            `Pastikan Anda sudah melakukan semua transfer ke rekening masing-masing penjual.\n\n` +
            `Lanjutkan?`
        );
        
        if (!confirmation) {
            e.preventDefault();
            return false;
        }
        
        // Tampilkan loading
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';
        submitBtn.disabled = true;
    });
    
    // Auto-format input kode pos (hanya angka)
    const kodeposInput = document.querySelector('input[name="kodepos"]');
    if (kodeposInput) {
        kodeposInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 5);
        });
    }
    
    // Auto-format telepon
    const teleponInput = document.querySelector('input[name="telepon"]');
    if (teleponInput) {
        teleponInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});
</script>
</body>
</html>