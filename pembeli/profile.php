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

/* =====================
   AMBIL DATA PROFILE LENGKAP
===================== */
$query = "SELECT * FROM users WHERE id = ? AND role = 'pembeli'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $idPembeli);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// DEBUG: Cek isi array user (hapus setelah selesai)
// echo "<pre>"; print_r($user); echo "</pre>";

// Hitung jumlah item di keranjang untuk badge
$qKeranjang = mysqli_query($conn, "SELECT SUM(jumlah) AS total FROM keranjang WHERE id_user='$idPembeli'");
$jumlahKeranjang = 0;
if ($qKeranjang && mysqli_num_rows($qKeranjang) > 0) {
    $keranjang = mysqli_fetch_assoc($qKeranjang);
    $jumlahKeranjang = $keranjang['total'] ?? 0;
}

// Hitung statistik transaksi berdasarkan id_user (pembeli)
$qTransaksi = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE id_user='$idPembeli'");
$totalTransaksi = 0;
if ($qTransaksi && mysqli_num_rows($qTransaksi) > 0) {
    $transaksi = mysqli_fetch_assoc($qTransaksi);
    $totalTransaksi = $transaksi['total'] ?? 0;
}

// Hitung pesanan selesai
$qSelesai = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE id_user='$idPembeli' AND status='selesai'");
$totalSelesai = 0;
if ($qSelesai && mysqli_num_rows($qSelesai) > 0) {
    $selesai = mysqli_fetch_assoc($qSelesai);
    $totalSelesai = $selesai['total'] ?? 0;
}

// Hitung pesanan pending/dalam proses
$qProses = mysqli_query($conn, "SELECT COUNT(*) AS total FROM transaksi WHERE id_user='$idPembeli' AND status IN ('pending', 'dibayar', 'dikirim')");
$totalProses = 0;
if ($qProses && mysqli_num_rows($qProses) > 0) {
    $proses = mysqli_fetch_assoc($qProses);
    $totalProses = $proses['total'] ?? 0;
}

// Ambil 5 transaksi terakhir untuk ditampilkan
$qRecent = mysqli_query($conn, "
    SELECT t.*, 
           DATE_FORMAT(t.created_at, '%d %b %Y %H:%i') as tgl_transaksi
    FROM transaksi t 
    WHERE t.id_user='$idPembeli' 
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$recentTransaksi = [];
if ($qRecent && mysqli_num_rows($qRecent) > 0) {
    while ($row = mysqli_fetch_assoc($qRecent)) {
        $recentTransaksi[] = $row;
    }
}

// Format tanggal
$tanggalBergabung = date('d F Y', strtotime($user['created_at']));
$lastActivity = !empty($user['last_activity']) ? date('d M Y H:i', strtotime($user['last_activity'])) : 'Belum pernah';

// Default foto profile
$fotoProfile = '../assets/img/default-avatar.png';
if (!empty($user['foto'])) {
    $fotoPath = "../uploads/profile/" . $user['foto'];
    if (file_exists($fotoPath)) {
        $fotoProfile = $fotoPath;
    }
}

// Tentukan status dengan benar
$statusAktif = ($user['aktif'] == 'ya') ? 'Aktif' : 'Tidak Aktif';
$statusAktifClass = ($user['aktif'] == 'ya') ? 'success' : 'danger';
$statusOnline = ($user['status'] == 'online') ? 'Online' : 'Offline';
$statusOnlineClass = ($user['status'] == 'online') ? 'success' : 'secondary';

// Fungsi untuk cek apakah field benar-benar kosong (termasuk trim)
function isFieldEmpty($value) {
    return $value === null || $value === '' || trim($value) === '';
}

// Ambil nilai dengan trim untuk menghilangkan spasi tersembunyi
$no_hp_value = isset($user['no_hp']) ? trim($user['no_hp']) : '';
$alamat_value = isset($user['alamat']) ? trim($user['alamat']) : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOKIE - Profil Saya</title>
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
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .profile-email {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .profile-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            display: inline-block;
            margin-right: 10px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #6f42c1;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .info-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .info-title i {
            color: #6f42c1;
            margin-right: 10px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px dashed #f0f0f0;
        }
        
        .info-label {
            width: 150px;
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }
        
        .info-value i {
            color: #28a745;
            margin-right: 5px;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }
        
        .transaction-item:hover {
            background: #f8f9fa;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: #f0e6ff;
            color: #6f42c1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .transaction-content {
            flex: 1;
        }
        
        .transaction-code {
            font-weight: 600;
            margin-bottom: 3px;
            color: #333;
        }
        
        .transaction-meta {
            font-size: 0.85rem;
            color: #999;
        }
        
        .transaction-status {
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 15px;
            font-weight: 600;
        }
        
        .btn-edit {
            background: #6f42c1;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-edit:hover {
            background: #5a32a3;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-view-all {
            color: #6f42c1;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .btn-view-all:hover {
            text-decoration: underline;
        }
        
        .search-box {
            border-radius: 25px;
            border: 2px solid #e0e0e0;
            padding: 8px 20px;
            width: 300px;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
            outline: none;
        }
        
        .text-purple {
            color: #6f42c1;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        /* Debug border - hapus nanti */
        .debug {
            border: 1px solid red;
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
            <h5 class="mb-0"><i class="bi bi-person-circle me-2 text-purple"></i> Profil Saya</h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="position-relative">
                <input type="text" class="form-control search-box" id="searchInput" placeholder="Cari buku atau alat tulis...">
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

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-4">
                    <img src="<?= $fotoProfile ?>" alt="Profile" class="profile-avatar">
                    <div>
                        <h1 class="profile-name"><?= htmlspecialchars($user['nama']) ?></h1>
                        <div class="profile-email">
                            <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?>
                        </div>
                        <div>
                            <span class="profile-badge">
                                <i class="bi bi-person-badge me-1"></i>Pembeli
                            </span>
                            <span class="profile-badge">
                                <i class="bi bi-calendar me-1"></i>Bergabung <?= $tanggalBergabung ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <a href="edit_profile.php" class="btn-edit">
                    <i class="bi bi-pencil-square me-2"></i>Edit Profil
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <i class="bi bi-cart-check fs-1 text-purple"></i>
                <div class="stat-value"><?= $totalTransaksi ?></div>
                <div class="stat-label">Total Transaksi</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="bi bi-clock-history fs-1 text-warning"></i>
                <div class="stat-value"><?= $totalProses ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <div class="stat-value"><?= $totalSelesai ?></div>
                <div class="stat-label">Pesanan Selesai</div>
            </div>
        </div>
    </div>

    <!-- Informasi Pribadi & Transaksi Terakhir -->
    <div class="row">
        <!-- Informasi Pribadi -->
        <div class="col-md-6">
            <div class="info-card">
                <h5 class="info-title">
                    <i class="bi bi-person-vcard"></i> Informasi Pribadi
                </h5>
                
                <div class="info-item">
                    <div class="info-label">Nama Lengkap</div>
                    <div class="info-value">: <?= htmlspecialchars($user['nama']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">: <?= htmlspecialchars($user['email']) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Nomor HP</div>
                    <div class="info-value">: 
                        <?php 
                        if (!isFieldEmpty($no_hp_value)) {
                            echo htmlspecialchars($no_hp_value);
                        } else {
                            echo '<span class="text-muted">Belum diisi</span>';
                        }
                        ?>
                        <!-- DEBUG: Hapus setelah selesai -->
                        <!-- <small class="text-danger d-block">(Debug: "<?= $no_hp_value ?>")</small> -->
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Alamat</div>
                    <div class="info-value">: 
                        <?php 
                        if (!isFieldEmpty($alamat_value)) {
                            echo nl2br(htmlspecialchars($alamat_value));
                        } else {
                            echo '<span class="text-muted">Belum diisi</span>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Foto Profile</div>
                    <div class="info-value">: 
                        <?php if(!empty($user['foto'])): ?>
                            <span class="text-success"><i class="bi bi-check-circle"></i> Ada</span>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-x-circle"></i> Belum upload</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informasi Akun -->
        <div class="col-md-6">
            <div class="info-card">
                <h5 class="info-title">
                    <i class="bi bi-shield-lock"></i> Informasi Akun
                </h5>
                
                <div class="info-item">
                    <div class="info-label">ID Akun</div>
                    <div class="info-value">: #<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value">: 
                        <span class="badge bg-info text-white">Pembeli</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status Akun</div>
                    <div class="info-value">: 
                        <span class="badge bg-<?= $statusAktifClass ?>"><?= $statusAktif ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status Online</div>
                    <div class="info-value">: 
                        <span class="badge bg-<?= $statusOnlineClass ?>"><?= $statusOnline ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Terakhir Online</div>
                    <div class="info-value">: <?= $lastActivity ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Tanggal Bergabung</div>
                    <div class="info-value">: <?= $tanggalBergabung ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaksi Terakhir -->
    <?php if(!empty($recentTransaksi)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="info-title mb-0">
                        <i class="bi bi-clock-history"></i> Transaksi Terakhir
                    </h5>
                    <a href="pesanan.php" class="btn-view-all">
                        Lihat Semua <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                
                <?php foreach($recentTransaksi as $trx): ?>
                <div class="transaction-item">
                    <div class="transaction-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="transaction-content">
                        <div class="transaction-code"><?= htmlspecialchars($trx['kode_transaksi']) ?></div>
                        <div class="transaction-meta">
                            <?= $trx['tgl_transaksi'] ?> • Total: Rp <?= number_format($trx['total'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div>
                        <?php 
                        $statusClass = '';
                        $statusText = '';
                        switch($trx['status']) {
                            case 'pending':
                                $statusClass = 'bg-warning text-dark';
                                $statusText = 'Pending';
                                break;
                            case 'dibayar':
                                $statusClass = 'bg-info text-white';
                                $statusText = 'Dibayar';
                                break;
                            case 'dikirim':
                                $statusClass = 'bg-primary text-white';
                                $statusText = 'Dikirim';
                                break;
                            case 'selesai':
                                $statusClass = 'bg-success text-white';
                                $statusText = 'Selesai';
                                break;
                            default:
                                $statusClass = 'bg-secondary text-white';
                                $statusText = $trx['status'];
                        }
                        ?>
                        <span class="badge <?= $statusClass ?> transaction-status"><?= $statusText ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tombol Aksi -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="info-card d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-gear fs-4 text-purple me-2"></i>
                    <span class="fw-bold">Pengaturan Akun</span>
                </div>
                <div>
                    <a href="dashboard.php" class="btn-back me-2">
                        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <a href="ubah_password.php" class="btn-back me-2">
                        <i class="bi bi-key"></i> Ubah Password
                    </a>
                    <a href="edit_profile.php" class="btn-edit">
                        <i class="bi bi-pencil-square"></i> Edit Profil
                    </a>
                </div>
            </div>
        </div>
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
</script>

</body>
</html>