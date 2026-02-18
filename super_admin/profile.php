<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

// Update last_activity admin setiap akses halaman
if (isset($_SESSION['user']['id'])) {
    mysqli_query($conn, "
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = '{$_SESSION['user']['id']}'
    ");
}

/* =====================
   AMBIL DATA USER
===================== */
$idUser = $_SESSION['user']['id'];

$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$idUser'");
$userData = mysqli_fetch_assoc($query);

if (!$userData) {
    die("Data user tidak ditemukan.");
}

/* =====================
   FUNGSI UPLOAD FOTO
===================== */
function uploadFoto($file, $oldFoto) {
    $targetDir = "../uploads/profile/";
    
    // Buat folder jika belum ada
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file["name"]);
    $targetFilePath = $targetDir . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Validasi file
    $allowTypes = array('jpg', 'jpeg', 'png', 'gif');
    if (!in_array(strtolower($fileType), $allowTypes)) {
        return ['error' => 'Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan.'];
    }
    
    // Validasi ukuran (max 2MB)
    if ($file["size"] > 2 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 2MB.'];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        // Hapus foto lama jika ada
        if (!empty($oldFoto) && file_exists($targetDir . $oldFoto)) {
            unlink($targetDir . $oldFoto);
        }
        return ['success' => $fileName];
    } else {
        return ['error' => 'Gagal mengupload file.'];
    }
}

/* =====================
   HAPUS FOTO
===================== */
if (isset($_GET['delete_photo'])) {
    if (!empty($userData['foto'])) {
        $targetDir = "../uploads/profile/";
        if (file_exists($targetDir . $userData['foto'])) {
            unlink($targetDir . $userData['foto']);
        }
        
        // Update database
        mysqli_query($conn, "UPDATE users SET foto = NULL WHERE id = '$idUser'");
        
        $_SESSION['success'] = 'Foto profil berhasil dihapus.';
    }
    header("Location: profile.php");
    exit;
}

/* =====================
   UPDATE PROFILE
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = mysqli_real_escape_string($conn, $_POST['nama']);
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp'] ?? '');
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat'] ?? '');
    
    // Handle upload foto
    $fotoFileName = $userData['foto']; // default ke foto lama
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $uploadResult = uploadFoto($_FILES['foto'], $userData['foto']);
        if (isset($uploadResult['error'])) {
            $_SESSION['error'] = $uploadResult['error'];
            header("Location: profile.php");
            exit;
        } else {
            $fotoFileName = $uploadResult['success'];
        }
    }

    // Cek duplikasi email (kecuali milik sendiri)
    $cekEmail = mysqli_query(
        $conn,
        "SELECT id FROM users 
         WHERE email = '$email' AND id != '$idUser'"
    );

    if (mysqli_num_rows($cekEmail) > 0) {
        $_SESSION['error'] = 'Email sudah digunakan oleh akun lain.';
    } 
    // Cek duplikasi no_hp (kecuali milik sendiri)
    elseif (!empty($no_hp)) {
        $cekHp = mysqli_query(
            $conn,
            "SELECT id FROM users 
             WHERE no_hp = '$no_hp' AND id != '$idUser'"
        );

        if (mysqli_num_rows($cekHp) > 0) {
            $_SESSION['error'] = 'Nomor telepon sudah digunakan oleh akun lain.';
        } else {
            // Lanjut update dengan foto
            doUpdate($fotoFileName);
        }
    } else {
        // no_hp kosong → tetap update dengan foto
        doUpdate($fotoFileName);
    }
    
    header("Location: profile.php");
    exit;
}

function doUpdate($fotoFileName) {
    global $conn, $idUser, $nama, $email, $no_hp, $alamat, $userData;

    $updateQuery = "
        UPDATE users SET
            nama = '$nama',
            email = '$email',
            foto = " . ($fotoFileName ? "'$fotoFileName'" : "NULL") . ",
            no_hp = " . (empty($no_hp) ? "NULL" : "'$no_hp'") . ",
            alamat = " . (empty($alamat) ? "NULL" : "'$alamat'") . "
        WHERE id = '$idUser'
    ";

    if (mysqli_query($conn, $updateQuery)) {
        $_SESSION['user']['nama']  = $nama;
        $_SESSION['user']['email'] = $email;
        $_SESSION['success'] = 'Profil berhasil diperbarui.';

        // Refresh user data
        $userData = mysqli_fetch_assoc(
            mysqli_query($conn, "SELECT * FROM users WHERE id = '$idUser'")
        );
    } else {
        $_SESSION['error'] = 'Gagal memperbarui profil: ' . mysqli_error($conn);
    }
}

// Session untuk notifikasi
$success_message = '';
$error_message = '';

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Super Admin | BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="includes/sidebar.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            background: #f5f7fb;
        }
        
        .content-wrapper {
            padding: 25px;
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }
        
        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #4361ee;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 20px;
        }
        
        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            font-size: 3.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 5px solid #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .avatar-actions {
            position: absolute;
            bottom: 0;
            right: 0;
            display: flex;
            gap: 5px;
        }
        
        .avatar-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: white;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4361ee;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .avatar-btn:hover {
            transform: scale(1.1);
            background: #4361ee;
            color: white;
        }
        
        .avatar-btn.delete-btn:hover {
            background: #dc3545;
            color: white;
        }
        
        .role-badge {
            display: inline-block;
            padding: 6px 15px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #4361ee;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .photo-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .photo-upload-area:hover {
            border-color: #4361ee;
            background: #f8f9fa;
        }
        
        .photo-upload-area i {
            font-size: 2.5rem;
            color: #4361ee;
            margin-bottom: 10px;
        }
        
        .photo-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 500;
        }
        
        .btn-danger {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include "includes/sidebar.php"; ?>

        <div class="main-content">
            <div class="content-wrapper">
                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-person-circle me-2"></i>Profil Super Admin</h4>
                            <p class="text-muted mb-0">Kelola data profil akun administrator Anda</p>
                        </div>
                        <div class="badge bg-primary rounded-pill p-2 px-3">
                            <i class="bi bi-shield-check me-1"></i> Super Admin
                        </div>
                    </div>
                </div>

                <!-- Notifikasi -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Card -->
                <div class="profile-card">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="avatar-container">
                            <?php if (!empty($userData['foto'])): ?>
                                <img src="../uploads/profile/<?= htmlspecialchars($userData['foto']) ?>" 
                                     alt="Foto Profil" class="avatar" id="profileAvatar">
                            <?php else: ?>
                                <div class="avatar-placeholder" id="profileAvatar">
                                    <?= strtoupper(substr($userData['nama'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="avatar-actions">
                                <label for="photoInput" class="avatar-btn" title="Upload Foto">
                                    <i class="bi bi-camera"></i>
                                </label>
                                <?php if (!empty($userData['foto'])): ?>
                                    <a href="?delete_photo=1" class="avatar-btn delete-btn" 
                                       title="Hapus Foto" 
                                       onclick="return confirm('Yakin ingin menghapus foto profil?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h3 class="mb-2"><?= htmlspecialchars($userData['nama']) ?></h3>
                        <p class="text-muted mb-3"><?= htmlspecialchars($userData['email']) ?></p>
                        <div class="role-badge">
                            <i class="bi bi-shield-check me-1"></i>
                            <?= strtoupper(str_replace('_', ' ', $userData['role'])) ?>
                        </div>
                    </div>

                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-label">ID Akun</div>
                            <div class="info-value">#<?= $userData['id'] ?></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Status Akun</div>
                            <div class="info-value">
                                <span class="status-badge <?= $userData['aktif'] == 'ya' ? 'status-active' : 'status-inactive' ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>
                                    <?= ucfirst($userData['aktif']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Status Login</div>
                            <div class="info-value">
                                <span class="status-badge <?= $userData['status'] == 'online' ? 'status-active' : 'status-inactive' ?>">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>
                                    <?= ucfirst($userData['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">Bergabung Sejak</div>
                            <div class="info-value">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d F Y', strtotime($userData['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form -->
                    <div class="form-section">
                        <h5 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Profil</h5>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Hidden file input untuk foto -->
                            <input type="file" name="foto" id="photoInput" 
                                   accept="image/jpeg,image/png,image/gif" style="display: none;">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-person"></i>
                                        </span>
                                        <input type="text" name="nama" class="form-control"
                                               value="<?= htmlspecialchars($userData['nama']) ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email" name="email" class="form-control"
                                               value="<?= htmlspecialchars($userData['email']) ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Nomor Telepon</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-phone"></i>
                                        </span>
                                        <input type="tel" name="no_hp" class="form-control"
                                               value="<?= htmlspecialchars($userData['no_hp'] ?? '') ?>"
                                               placeholder="08xxxxxxxxxx">
                                    </div>
                                    <small class="text-muted">Opsional, harus unik jika diisi</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Alamat</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-geo-alt"></i>
                                        </span>
                                        <input type="text" name="alamat" class="form-control"
                                               value="<?= htmlspecialchars($userData['alamat'] ?? '') ?>"
                                               placeholder="Alamat lengkap">
                                    </div>
                                    <small class="text-muted">Opsional</small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Note -->
                <div class="alert alert-info mt-4">
                    <div class="d-flex">
                        <i class="bi bi-info-circle fs-5 me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-2">Keamanan Akun</h6>
                            <p class="mb-0">Untuk keamanan akun Anda, pastikan email dan nomor telepon yang digunakan valid dan dapat diakses. 
                            Perubahan email akan mempengaruhi proses login dan reset password.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Preview foto sebelum upload
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validasi ukuran file
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB!');
                    this.value = '';
                    return;
                }
                
                // Validasi tipe file
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan!');
                    this.value = '';
                    return;
                }
                
                // Preview gambar
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarContainer = document.querySelector('.avatar-container');
                    const oldAvatar = document.querySelector('.avatar, .avatar-placeholder');
                    
                    // Buat elemen img baru
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Preview Foto';
                    img.className = 'avatar';
                    img.id = 'profileAvatar';
                    
                    // Ganti avatar lama
                    oldAvatar.replaceWith(img);
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const email = form.querySelector('input[name="email"]').value;
                const noHp = form.querySelector('input[name="no_hp"]').value;
                
                // Email validation
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    alert('Format email tidak valid!');
                    return;
                }
                
                // Phone validation (if filled)
                if (noHp && !isValidPhone(noHp)) {
                    e.preventDefault();
                    alert('Format nomor telepon tidak valid! Gunakan format 08xxxxxxxxxx');
                    return;
                }
            });
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function isValidPhone(phone) {
                const re = /^08[0-9]{8,11}$/;
                return re.test(phone);
            }
        });
    </script>
</body>
</html>