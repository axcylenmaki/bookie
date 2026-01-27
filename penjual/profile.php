<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   DATA LOGIN
===================== */
$idPenjual = $_SESSION['user']['id'];

/* =====================
   DATA PENJUAL
===================== */
$qUser = mysqli_query($conn,"SELECT * FROM users WHERE id='$idPenjual' LIMIT 1");
$user  = mysqli_fetch_assoc($qUser);

if (!$user) {
  header("Location: ../auth/login.php");
  exit;
}

$namaPenjual = $user['nama'] ?? 'Penjual';
$emailPenjual = $user['email'] ?? '';
$noHpPenjual = $user['no_hp'] ?? '';
$alamatPenjual = $user['alamat'] ?? '';
$statusUser = $user['status'] ?? 'offline';
$aktifUser = $user['aktif'] ?? 'ya';
$tanggalGabung = date('d M Y', strtotime($user['created_at'] ?? 'now'));

/* =====================
   STATISTIK TOKO (MENGGUNAKAN TABEL YANG ADA)
===================== */
// Total produk
$qTotalProduk = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE penjual_id='$idPenjual'");
$totalProduk = mysqli_fetch_assoc($qTotalProduk)['total'] ?? 0;

// Total transaksi selesai
$qTotalTransaksi = mysqli_query($conn, "
  SELECT COUNT(*) as total FROM transaksi 
  WHERE penjual_id='$idPenjual' AND status='selesai'
");
$totalTransaksi = mysqli_fetch_assoc($qTotalTransaksi)['total'] ?? 0;

// Total omzet dari transaksi selesai
$qTotalOmzet = mysqli_query($conn, "
  SELECT SUM(total) as total FROM transaksi 
  WHERE penjual_id='$idPenjual' AND status='selesai'
");
$totalOmzet = mysqli_fetch_assoc($qTotalOmzet)['total'] ?? 0;

// Hitung rata-rata transaksi
$avgTransaksi = 0;
if ($totalTransaksi > 0 && $totalOmzet > 0) {
  $avgTransaksi = $totalOmzet / $totalTransaksi;
}

/* =====================
   UPDATE PROFILE
===================== */
if (isset($_POST['update'])) {
  $nama   = htmlspecialchars($_POST['nama']);
  $no_hp  = htmlspecialchars($_POST['no_hp']);
  $alamat = htmlspecialchars($_POST['alamat']);

  $fotoBaru = $user['foto'];

  if (!empty($_FILES['foto']['name'])) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($ext, $allowed_ext)) {
      if ($_FILES['foto']['size'] <= 2 * 1024 * 1024) { // 2MB max
        // Hapus foto lama jika bukan default
        if ($fotoBaru && $fotoBaru != 'user.png' && file_exists("../uploads/".$fotoBaru)) {
          @unlink("../uploads/".$fotoBaru);
        }
        
        $fotoBaru = "toko_".$idPenjual."_".time().".".$ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/".$fotoBaru);
      } else {
        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
      }
    } else {
      $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
    }
  }

  if (!isset($error)) {
    mysqli_query($conn,"
      UPDATE users SET
        nama='$nama',
        no_hp='$no_hp',
        alamat='$alamat',
        foto='$fotoBaru'
      WHERE id='$idPenjual'
    ");

    // Update session
    $_SESSION['user']['nama'] = $nama;
    $_SESSION['user']['foto'] = $fotoBaru;
    
    header("Location: profile.php?success=1");
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Profil Toko - BOOKIE</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Include CSS sidebar -->
  <link rel="stylesheet" href="includes/sidebar.css">
  
  <style>
    /* CSS khusus untuk halaman profil */
    .profile-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      border: 1px solid #e0e0e0;
      margin-bottom: 20px;
    }
    .profile-image {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      border: 5px solid #f8f9fa;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .stat-card {
      background: white;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 15px;
      border: 1px solid #e0e0e0;
      transition: transform 0.3s;
    }
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
      margin-right: 15px;
    }
    .stat-icon.bg-primary { background-color: #3498db !important; }
    .stat-icon.bg-success { background-color: #2ecc71 !important; }
    .stat-icon.bg-warning { background-color: #f39c12 !important; }
    .stat-icon.bg-info { background-color: #17a2b8 !important; }
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
    }
    .form-control:focus, .form-select:focus {
      border-color: #3498db;
      box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
    }
    .info-item {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #e9ecef;
    }
    .info-item:last-child {
      border-bottom: none;
    }
    .info-label {
      color: #6c757d;
    }
    .info-value {
      font-weight: 500;
      color: #495057;
    }
    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .preview-image {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 10px;
      border: 3px solid #dee2e6;
      margin-top: 10px;
    }
    .alert-custom {
      border-radius: 10px;
      border: none;
      box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    }
    .profile-header {
      padding-bottom: 20px;
      margin-bottom: 20px;
      border-bottom: 2px solid #f1f1f1;
    }
    .toko-info {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
    }
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }
    .dot-online {
      background-color: #2ecc71;
      box-shadow: 0 0 8px #2ecc71;
    }
    .dot-offline {
      background-color: #e74c3c;
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
          <h3 class="mb-1">Profil Toko</h3>
          <p class="text-muted mb-0">Kelola informasi toko dan profil Anda</p>
        </div>
        <span class="badge bg-dark">
          <i class="bi bi-shop me-1"></i> Penjual
        </span>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-custom mb-4">
          <i class="bi bi-check-circle me-2"></i>
          Profil berhasil diperbarui
        </div>
      <?php endif; ?>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-custom mb-4">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <?= $error ?>
        </div>
      <?php endif; ?>

      <div class="row">
        
        <!-- INFO TOKO & STATISTIK -->
        <div class="col-md-4">
          <div class="profile-card text-center">
            <div class="profile-header">
              <img src="<?= (!empty($user['foto']) && file_exists("../uploads/".$user['foto'])) 
                          ? "../uploads/".$user['foto'] 
                          : "../assets/img/user.png" ?>"
                   class="profile-image mb-3"
                   id="currentPhoto"
                   onerror="this.src='../assets/img/user.png'">
              <h4><?= htmlspecialchars($namaPenjual) ?></h4>
              <p class="text-muted mb-2"><?= htmlspecialchars($emailPenjual) ?></p>
              
              <div class="d-flex justify-content-center gap-3 mb-2">
                <span class="status-badge <?= $aktifUser == 'ya' ? 'bg-success' : 'bg-danger' ?>">
                  <?= $aktifUser == 'ya' ? 'Aktif' : 'Nonaktif' ?>
                </span>
                <span class="status-badge <?= $statusUser == 'online' ? 'bg-info' : 'bg-secondary' ?>">
                  <span class="status-indicator">
                    <span class="dot <?= $statusUser == 'online' ? 'dot-online' : 'dot-offline' ?>"></span>
                    <?= ucfirst($statusUser) ?>
                  </span>
                </span>
              </div>
            </div>
            
            <div class="info-section">
              <div class="info-item">
                <span class="info-label">Bergabung</span>
                <span class="info-value"><?= $tanggalGabung ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Telepon</span>
                <span class="info-value"><?= $noHpPenjual ?: '-' ?></span>
              </div>
              <div class="info-item">
                <span class="info-label">Alamat</span>
                <span class="info-value"><?= $alamatPenjual ? mb_strimwidth($alamatPenjual, 0, 30, '...') : '-' ?></span>
              </div>
            </div>
          </div>

          <!-- STATISTIK TOKO -->
          <div class="profile-card">
            <h5 class="mb-3"><i class="bi bi-bar-chart me-2"></i> Statistik Toko</h5>
            
            <div class="stat-card">
              <div class="d-flex align-items-center">
                <div class="stat-icon bg-primary">
                  <i class="bi bi-box"></i>
                </div>
                <div>
                  <h4 class="mb-0"><?= number_format($totalProduk) ?></h4>
                  <small class="text-muted">Total Produk</small>
                </div>
              </div>
            </div>
            
            <div class="stat-card">
              <div class="d-flex align-items-center">
                <div class="stat-icon bg-success">
                  <i class="bi bi-cart-check"></i>
                </div>
                <div>
                  <h4 class="mb-0"><?= number_format($totalTransaksi) ?></h4>
                  <small class="text-muted">Transaksi Selesai</small>
                </div>
              </div>
            </div>
            
            <div class="stat-card">
              <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning">
                  <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                  <h4 class="mb-0">Rp <?= number_format($totalOmzet) ?></h4>
                  <small class="text-muted">Total Omzet</small>
                </div>
              </div>
            </div>
            
            <div class="stat-card">
              <div class="d-flex align-items-center">
                <div class="stat-icon bg-info">
                  <i class="bi bi-graph-up"></i>
                </div>
                <div>
                  <h4 class="mb-0">Rp <?= number_format($avgTransaksi) ?></h4>
                  <small class="text-muted">Rata-rata Transaksi</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- FORM EDIT PROFIL -->
        <div class="col-md-8">
          <div class="profile-card">
            <h5 class="mb-4"><i class="bi bi-pencil-square me-2"></i> Edit Profil Toko</h5>
            
            <form method="POST" enctype="multipart/form-data" id="profileForm">
              <div class="row g-3">
                
                <div class="col-md-12">
                  <label class="form-label">Nama Toko</label>
                  <input type="text" name="nama" class="form-control" 
                         value="<?= htmlspecialchars($user['nama']) ?>" required
                         placeholder="Masukkan nama toko Anda">
                  <small class="text-muted">Nama toko akan ditampilkan kepada pembeli</small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <div class="input-group">
                    <span class="input-group-text">
                      <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control" 
                           value="<?= htmlspecialchars($user['email']) ?>" readonly>
                  </div>
                  <small class="text-muted">Email tidak dapat diubah</small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Nomor Telepon/HP</label>
                  <div class="input-group">
                    <span class="input-group-text">
                      <i class="bi bi-telephone"></i>
                    </span>
                    <input type="text" name="no_hp" class="form-control" 
                           value="<?= htmlspecialchars($user['no_hp']) ?>"
                           placeholder="Contoh: 081234567890">
                  </div>
                  <small class="text-muted">Untuk kontak dengan pembeli</small>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Alamat Toko</label>
                  <textarea name="alamat" rows="3" class="form-control"
                            placeholder="Masukkan alamat lengkap toko Anda"><?= htmlspecialchars($user['alamat']) ?></textarea>
                  <small class="text-muted">Alamat akan ditampilkan pada detail toko</small>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Foto / Logo Toko</label>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <input type="file" name="foto" class="form-control" 
                             accept="image/*" id="photoInput">
                      <small class="text-muted d-block mt-1">
                        Format: JPG, PNG, GIF, WebP | Maksimal: 2MB
                      </small>
                    </div>
                    <div class="col-md-6">
                      <div class="text-center">
                        <p class="text-muted mb-2">Pratinjau Gambar Baru:</p>
                        <img id="photoPreview" class="preview-image d-none" 
                             onerror="this.src='../assets/img/user.png'">
                        <div id="noPhotoPreview" class="text-muted">
                          <i class="bi bi-image" style="font-size: 3rem;"></i>
                          <p class="mt-2">Belum ada gambar baru</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- INFORMASI AKUN -->
                <div class="col-md-12">
                  <div class="toko-info">
                    <h6><i class="bi bi-shield-check me-2"></i> Keamanan Akun</h6>
                    <p class="text-muted mb-2">
                      Untuk mengubah password atau pengaturan keamanan lainnya, 
                      hubungi admin melalui halaman Bantuan.
                    </p>
                    <a href="help.php#akun" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-headset me-1"></i> Hubungi Admin
                    </a>
                  </div>
                </div>

                <div class="col-md-12 mt-4">
                  <div class="d-flex gap-3">
                    <button type="submit" name="update" class="btn btn-primary px-4">
                      <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary px-4">
                      <i class="bi bi-x-circle me-1"></i> Batal
                    </a>
                  </div>
                </div>

              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preview foto baru
document.getElementById('photoInput').addEventListener('change', function(e) {
  const preview = document.getElementById('photoPreview');
  const noPreview = document.getElementById('noPhotoPreview');
  const file = e.target.files[0];
  
  if (file) {
    // Validasi ukuran file
    if (file.size > 2 * 1024 * 1024) {
      alert('Ukuran file terlalu besar. Maksimal 2MB.');
      this.value = '';
      return;
    }
    
    const reader = new FileReader();
    
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.classList.remove('d-none');
      noPreview.classList.add('d-none');
    }
    
    reader.readAsDataURL(file);
  } else {
    preview.classList.add('d-none');
    noPreview.classList.remove('d-none');
  }
});

// Konfirmasi sebelum submit
document.getElementById('profileForm').addEventListener('submit', function(e) {
  const nama = document.querySelector('input[name="nama"]').value.trim();
  
  if (!nama) {
    e.preventDefault();
    alert('Nama toko tidak boleh kosong!');
    return false;
  }
  
  if (!confirm('Simpan perubahan pada profil toko?')) {
    e.preventDefault();
    return false;
  }
  
  return true;
});

// Auto-focus pada field nama jika kosong
document.addEventListener('DOMContentLoaded', function() {
  const namaField = document.querySelector('input[name="nama"]');
  if (namaField && !namaField.value.trim()) {
    namaField.focus();
  }
});
</script>
</body>
</html>