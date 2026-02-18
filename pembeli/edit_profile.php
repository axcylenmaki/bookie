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
   AMBIL DATA PROFILE
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

/* =====================
   PROSES UPDATE PROFILE
===================== */
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $no_hp = mysqli_real_escape_string($conn, trim($_POST['no_hp']));
    $alamat = mysqli_real_escape_string($conn, trim($_POST['alamat']));
    
    // Validasi
    $errors = [];
    if (empty($nama)) {
        $errors[] = "Nama tidak boleh kosong";
    }
    
    // Validasi nomor HP (opsional, tapi jika diisi harus valid)
    if (!empty($no_hp) && !preg_match('/^[0-9+\-\s]+$/', $no_hp)) {
        $errors[] = "Format nomor HP tidak valid";
    }
    
    // Upload foto jika ada
    $foto = $user['foto']; // Default foto lama
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['foto']['name'];
        $filesize = $_FILES['foto']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validasi ekstensi
        if (!in_array($ext, $allowed)) {
            $errors[] = "Format file harus JPG, JPEG, PNG, atau GIF";
        }
        
        // Validasi ukuran (max 2MB)
        if ($filesize > 2 * 1024 * 1024) {
            $errors[] = "Ukuran file maksimal 2MB";
        }
        
        if (empty($errors)) {
            // Generate nama file unik
            $new_filename = "pembeli_" . $idPembeli . "_" . time() . "." . $ext;
            $upload_path = "../uploads/profile/" . $new_filename;
            
            // Buat direktori jika belum ada
            if (!file_exists("../uploads/profile")) {
                mkdir("../uploads/profile", 0777, true);
            }
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                // Hapus foto lama jika ada
                if (!empty($user['foto']) && file_exists("../uploads/profile/" . $user['foto'])) {
                    unlink("../uploads/profile/" . $user['foto']);
                }
                $foto = $new_filename;
            } else {
                $errors[] = "Gagal mengupload file";
            }
        }
    }
    
    // Update ke database
    if (empty($errors)) {
        $update_query = "UPDATE users SET nama = ?, no_hp = ?, alamat = ?, foto = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "ssssi", $nama, $no_hp, $alamat, $foto, $idPembeli);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Update session
            $_SESSION['user']['nama'] = $nama;
            
            $success_message = "Profil berhasil diperbarui!";
            
            // Refresh data user
            $query = "SELECT * FROM users WHERE id = ? AND role = 'pembeli'";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $idPembeli);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
        } else {
            $errors[] = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}

// Hitung jumlah item di keranjang untuk badge
$qKeranjang = mysqli_query($conn, "SELECT SUM(jumlah) AS total FROM keranjang WHERE id_user='$idPembeli'");
$jumlahKeranjang = 0;
if ($qKeranjang && mysqli_num_rows($qKeranjang) > 0) {
    $keranjang = mysqli_fetch_assoc($qKeranjang);
    $jumlahKeranjang = $keranjang['total'] ?? 0;
}

// Default foto profile
$fotoProfile = '../assets/img/default-avatar.png';
if (!empty($user['foto'])) {
    $fotoPath = "../uploads/profile/" . $user['foto'];
    if (file_exists($fotoPath)) {
        $fotoProfile = $fotoPath;
    }
}

// Fungsi untuk cek apakah field benar-benar kosong
function isFieldEmpty($value) {
    return $value === null || $value === '' || trim($value) === '';
}

// Ambil nilai dengan trim
$no_hp_value = isset($user['no_hp']) ? trim($user['no_hp']) : '';
$alamat_value = isset($user['alamat']) ? trim($user['alamat']) : '';

// Format tanggal dengan aman
$tanggalBergabung = !empty($user['created_at']) ? date('d F Y', strtotime($user['created_at'])) : '-';
$lastActivity = !empty($user['last_activity']) ? date('d M Y H:i', strtotime($user['last_activity'])) : 'Belum pernah';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOKIE - Edit Profil</title>
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
        
        .edit-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .edit-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .edit-header h3 {
            font-weight: 700;
            color: #333;
        }
        
        .edit-header p {
            color: #666;
            margin-bottom: 0;
        }
        
        .avatar-upload {
            position: relative;
            max-width: 200px;
            margin: 0 auto 30px;
        }
        
        .avatar-upload .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 4px solid #6f42c1;
            box-shadow: 0 5px 15px rgba(111, 66, 193, 0.2);
            object-fit: cover;
            margin: 0 auto;
            display: block;
            background: #f8f9fa;
        }
        
        .avatar-upload .upload-btn {
            position: absolute;
            bottom: 10px;
            right: 30px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6f42c1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s;
        }
        
        .avatar-upload .upload-btn:hover {
            background: #5a32a3;
            transform: scale(1.1);
        }
        
        #foto {
            display: none;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
            outline: none;
        }
        
        .form-control:read-only, .form-control:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-save {
            background: #6f42c1;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-save:hover {
            background: #5a32a3;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            background: #bb2d3b;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
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
        
        .text-muted-info {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }
        
        hr {
            opacity: 0.1;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box-title {
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
        }
        
        .file-info {
            font-size: 0.85rem;
            color: #28a745;
            margin-top: 5px;
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
            <h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-purple"></i> Edit Profil</h5>
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

    <!-- Edit Profile Form -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Alert Messages -->
            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Form Edit Profil -->
            <div class="edit-card">
                <div class="edit-header">
                    <h3><i class="bi bi-person-circle me-2 text-purple"></i> Edit Profil</h3>
                    <p class="text-muted">Perbarui informasi profil Anda. Field dengan tanda * wajib diisi.</p>
                </div>
                
                <form action="" method="POST" enctype="multipart/form-data" id="editProfileForm">
                    <!-- Avatar Upload -->
                    <div class="avatar-upload">
                        <img src="<?= $fotoProfile ?>" alt="Avatar" class="avatar-preview" id="avatarPreview">
                        <label for="foto" class="upload-btn" data-bs-toggle="tooltip" title="Upload foto profil">
                            <i class="bi bi-camera"></i>
                        </label>
                        <input type="file" name="foto" id="foto" accept="image/jpeg,image/png,image/gif">
                        <div class="text-center text-muted-info">
                            <i class="bi bi-info-circle"></i> Format: JPG, PNG, GIF (Max 2MB)
                        </div>
                        <?php if(!empty($user['foto'])): ?>
                        <div class="text-center file-info">
                            <i class="bi bi-check-circle"></i> File saat ini: <?= htmlspecialchars($user['foto']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Form Fields -->
                    <div class="row g-4">
                        <!-- Informasi Pribadi -->
                        <div class="col-12">
                            <div class="info-box">
                                <div class="info-box-title">
                                    <i class="bi bi-person-vcard text-purple me-2"></i>Informasi Pribadi
                                </div>
                            </div>
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="col-md-12">
                            <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nama" 
                                   name="nama" 
                                   value="<?= htmlspecialchars($user['nama']) ?>" 
                                   required 
                                   placeholder="Masukkan nama lengkap Anda">
                        </div>
                        
                        <!-- Email (Read Only) -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" 
                                   readonly 
                                   disabled>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Email tidak dapat diubah
                            </small>
                        </div>
                        
                        <!-- Nomor HP -->
                        <div class="col-md-6">
                            <label for="no_hp" class="form-label">Nomor HP</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="no_hp" 
                                   name="no_hp" 
                                   value="<?= htmlspecialchars($no_hp_value) ?>" 
                                   placeholder="Contoh: 081234567890">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Format: 08xxxxxxxxxx
                            </small>
                        </div>
                        
                        <!-- Alamat Lengkap -->
                        <div class="col-12">
                            <label for="alamat" class="form-label">Alamat Lengkap</label>
                            <textarea class="form-control" 
                                      id="alamat" 
                                      name="alamat" 
                                      rows="4" 
                                      placeholder="Masukkan alamat lengkap Anda (jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota, kode pos)"><?= htmlspecialchars($alamat_value) ?></textarea>
                        </div>

                        <!-- Informasi Akun (Read Only) -->
                        <div class="col-12 mt-4">
                            <div class="info-box">
                                <div class="info-box-title">
                                    <i class="bi bi-shield-lock text-purple me-2"></i>Informasi Akun (Tidak Dapat Diubah)
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="id_akun" class="form-label">ID Akun</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="id_akun" 
                                   value="#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?>" 
                                   readonly 
                                   disabled>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="role" class="form-label">Role</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="Pembeli" 
                                   readonly 
                                   disabled>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="status_akun" class="form-label">Status Akun</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="status_akun" 
                                   value="<?= (isset($user['aktif']) && $user['aktif'] == 'ya') ? 'Aktif' : 'Tidak Aktif' ?>" 
                                   readonly 
                                   disabled>
                        </div>

                        <div class="col-md-6">
                            <label for="tanggal_bergabung" class="form-label">Tanggal Bergabung</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="tanggal_bergabung" 
                                   value="<?= $tanggalBergabung ?>" 
                                   readonly 
                                   disabled>
                        </div>

                        <div class="col-md-6">
                            <label for="last_login" class="form-label">Terakhir Online</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="last_login" 
                                   value="<?= $lastActivity ?>" 
                                   readonly 
                                   disabled>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Buttons -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <a href="profile.php" class="btn-cancel">
                                <i class="bi bi-arrow-left me-2"></i>Kembali ke Profil
                            </a>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-cancel" id="resetBtn">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn-save" id="saveBtn">
                                <i class="bi bi-check-circle me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="edit-card border border-danger">
                <div class="d-flex align-items-center text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                    <h5 class="mb-0 fw-bold">Danger Zone</h5>
                </div>
                <p class="text-muted mb-3">Aksi ini tidak dapat dibatalkan. Harap berhati-hati.</p>
                <div class="d-flex gap-3">
                    <button type="button" class="btn-danger" data-bs-toggle="modal" data-bs-target="#ubahPasswordModal">
                        <i class="bi bi-key me-2"></i>Ubah Password
                    </button>
                    <button type="button" class="btn-danger" data-bs-toggle="modal" data-bs-target="#hapusAkunModal">
                        <i class="bi bi-trash me-2"></i>Hapus Akun
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Ubah Password -->
<div class="modal fade" id="ubahPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-key text-purple me-2"></i>Ubah Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="ubah_password.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Password Lama</label>
                        <input type="password" class="form-control" name="password_lama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="password_baru" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="konfirmasi_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-save">Ubah Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Akun -->
<div class="modal fade" id="hapusAkunModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Akun
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold text-danger">Peringatan!</p>
                <p>Menghapus akun akan:</p>
                <ul class="text-muted">
                    <li>Menghapus semua data profil Anda</li>
                    <li>Menghapus riwayat transaksi</li>
                    <li>Menghapus data keranjang belanja</li>
                    <li>Aksi ini tidak dapat dibatalkan</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Ketik "HAPUS" untuk konfirmasi</label>
                    <input type="text" class="form-control" id="konfirmasiHapus" placeholder="HAPUS">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn-danger" id="confirmHapusBtn" disabled>Hapus Akun</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

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

// Preview foto sebelum upload
document.getElementById('foto').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validasi ukuran file client-side
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file maksimal 2MB');
            this.value = '';
            return;
        }
        
        // Validasi tipe file
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Format file harus JPG, PNG, atau GIF');
            this.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// Reset button functionality
document.getElementById('resetBtn').addEventListener('click', function(e) {
    e.preventDefault();
    if (confirm('Reset akan mengembalikan form ke nilai awal. Lanjutkan?')) {
        document.getElementById('editProfileForm').reset();
        // Reset gambar ke awal
        document.getElementById('avatarPreview').src = '<?= $fotoProfile ?>';
        formChanged = false;
    }
});

// Confirm before leaving if form is dirty
let formChanged = false;
const form = document.getElementById('editProfileForm');
const formInputs = form.querySelectorAll('input:not([disabled]):not([type="file"]), textarea:not([disabled]), select:not([disabled])');

formInputs.forEach(input => {
    // Track perubahan dengan event change
    input.addEventListener('change', () => {
        formChanged = true;
    });
    
    // Untuk input text, track perubahan dengan keyup
    if (input.type === 'text' || input.type === 'tel' || input.tagName === 'TEXTAREA') {
        input.addEventListener('keyup', () => {
            formChanged = true;
        });
    }
});

// File input change
document.getElementById('foto').addEventListener('change', () => {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Anda memiliki perubahan yang belum disimpan. Yakin ingin meninggalkan halaman ini?';
    }
});

form.addEventListener('submit', function() {
    formChanged = false;
});

// Konfirmasi hapus akun
const konfirmasiInput = document.getElementById('konfirmasiHapus');
const confirmHapusBtn = document.getElementById('confirmHapusBtn');

if (konfirmasiInput && confirmHapusBtn) {
    konfirmasiInput.addEventListener('input', function() {
        confirmHapusBtn.disabled = this.value !== 'HAPUS';
    });
    
    confirmHapusBtn.addEventListener('click', function() {
        if (confirm('PERINGATAN TERAKHIR: Semua data Anda akan hilang! Lanjutkan?')) {
            window.location.href = 'hapus_akun.php';
        }
    });
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        let bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>