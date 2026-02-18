<?php
session_start();
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
   SIMPAN (TAMBAH / EDIT)
===================== */
if (isset($_POST['simpan'])) {
    $id   = $_POST['id'] ?? '';
    $nama = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
    $desk = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    // CEK NAMA KATEGORI
    if ($id == '') {
        // mode tambah
        $cek = mysqli_query($conn, "
            SELECT id FROM kategori WHERE nama_kategori = '$nama'
        ");
    } else {
        // mode edit (kecuali dirinya sendiri)
        $cek = mysqli_query($conn, "
            SELECT id FROM kategori 
            WHERE nama_kategori = '$nama' AND id != '$id'
        ");
    }

    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = "Nama kategori '$nama' sudah ada!";
        header("Location: kategori.php");
        exit;
    }

    // UPLOAD FOTO
    $fotoName = '';
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            $_SESSION['error'] = "Format file tidak didukung! Gunakan JPG, PNG, GIF, atau WebP.";
            header("Location: kategori.php");
            exit;
        }
        
        $fotoName = 'kategori_' . time() . '.' . $ext;
        $upload_path = "../uploads/kategori/" . $fotoName;
        
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            header("Location: kategori.php");
            exit;
        }
    }

    // SIMPAN DATA
    if ($id == '') {
        $result = mysqli_query($conn, "
            INSERT INTO kategori (nama_kategori, deskripsi, foto)
            VALUES ('$nama', '$desk', '$fotoName')
        ");
        if ($result) {
            $_SESSION['success'] = "Kategori berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan kategori: " . mysqli_error($conn);
        }
    } else {
        if ($fotoName != '') {
            // Hapus foto lama jika ada
            $old_foto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM kategori WHERE id = '$id'"));
            if ($old_foto && $old_foto['foto'] && file_exists("../uploads/kategori/" . $old_foto['foto'])) {
                unlink("../uploads/kategori/" . $old_foto['foto']);
            }
            
            $result = mysqli_query($conn, "
                UPDATE kategori SET
                    nama_kategori = '$nama',
                    deskripsi = '$desk',
                    foto = '$fotoName'
                WHERE id = '$id'
            ");
        } else {
            $result = mysqli_query($conn, "
                UPDATE kategori SET
                    nama_kategori = '$nama',
                    deskripsi = '$desk'
                WHERE id = '$id'
            ");
        }
        
        if ($result) {
            $_SESSION['success'] = "Kategori berhasil diperbarui!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui kategori: " . mysqli_error($conn);
        }
    }

    header("Location: kategori.php");
    exit;
}

/* =====================
   HAPUS
===================== */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];

    // Cek apakah kategori memiliki produk
    $cek_produk = mysqli_query($conn, "
        SELECT COUNT(*) as jumlah_produk 
        FROM produk 
        WHERE kategori_id = '$id'
    ");
    $data_produk = mysqli_fetch_assoc($cek_produk);
    
    if ($data_produk['jumlah_produk'] > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus kategori yang masih memiliki produk!";
        header("Location: kategori.php");
        exit;
    }

    // Hapus foto jika ada
    $q = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM kategori WHERE id = '$id'"));
    if ($q && $q['foto'] && file_exists("../uploads/kategori/" . $q['foto'])) {
        unlink("../uploads/kategori/" . $q['foto']);
    }

    mysqli_query($conn, "DELETE FROM kategori WHERE id = '$id'");
    $_SESSION['success'] = "Kategori berhasil dihapus!";
    header("Location: kategori.php");
    exit;
}

/* =====================
   DATA KATEGORI dengan search
===================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$query = "SELECT 
            k.id,
            k.nama_kategori,
            k.deskripsi,
            k.foto,
            COUNT(p.id) AS jumlah_produk
          FROM kategori k
          LEFT JOIN produk p ON p.kategori_id = k.id";

if (!empty($search)) {
    $query .= " WHERE k.nama_kategori LIKE '%$search%' OR k.deskripsi LIKE '%$search%'";
}

$query .= " GROUP BY k.id
            ORDER BY k.id DESC";

$data = mysqli_query($conn, $query);
$total_kategori = mysqli_num_rows($data);

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
    <title>Data Kategori | BOOKIE</title>
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
        }
        
        .page-header {
            background: #fff;
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #4361ee;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-top: 4px solid;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-total {
            border-color: #3b82f6;
        }
        
        .stats-with-products {
            border-color: #10b981;
        }
        
        .stats-empty {
            border-color: #ef4444;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .category-photo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        
        .badge-products {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-with-products {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-no-products {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }
        
        .btn-edit:hover {
            background: #4361ee;
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .btn-delete:hover {
            background: #ef4444;
            color: white;
        }
        
        .btn-disabled {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
            cursor: not-allowed;
        }
        
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .table thead th {
            background-color: #4361ee;
            color: white;
            font-weight: 600;
            padding: 16px 12px;
            border: none;
        }
        
        .table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-color: #f3f4f6;
        }
        
        .table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .no-data {
            text-align: center;
            padding: 50px 20px;
            color: #6b7280;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #d1d5db;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .content-wrapper {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include "includes/sidebar.php"; ?>

        <div class="main-content">
            <div class="content-wrapper">
                <!-- Notifikasi -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1"><i class="bi bi-tags-fill me-2"></i>Data Kategori</h4>
                            <p class="text-muted mb-0">Kelola kategori produk untuk sistem BOOKIE</p>
                        </div>
                        <div class="badge bg-primary rounded-pill p-2 px-3">
                            <i class="bi bi-tag-fill me-1"></i> <?= $total_kategori ?> Kategori
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-total">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Kategori</h6>
                                    <h3 class="mb-0"><?= $total_kategori ?></h3>
                                </div>
                                <div class="icon-circle bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                    <i class="bi bi-tags fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    // Hitung statistik
                    $stats_with_products = 0;
                    $stats_empty = 0;
                    
                    if (mysqli_num_rows($data) > 0) {
                        mysqli_data_seek($data, 0); // Reset pointer
                        while ($k = mysqli_fetch_assoc($data)) {
                            if ($k['jumlah_produk'] > 0) {
                                $stats_with_products++;
                            } else {
                                $stats_empty++;
                            }
                        }
                        mysqli_data_seek($data, 0); // Reset lagi untuk tampilan
                    }
                    ?>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-with-products">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Memiliki Produk</h6>
                                    <h3 class="mb-0 text-success"><?= $stats_with_products ?></h3>
                                </div>
                                <div class="icon-circle bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                    <i class="bi bi-box-seam fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-empty">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Kosong</h6>
                                    <h3 class="mb-0 text-danger"><?= $stats_empty ?></h3>
                                </div>
                                <div class="icon-circle bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                                    <i class="bi bi-box fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Add Button -->
                <div class="search-container">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <form method="GET" class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" name="search" 
                                       placeholder="Cari nama kategori atau deskripsi..." 
                                       value="<?= htmlspecialchars($search) ?>">
                                <?php if (!empty($search)): ?>
                                    <a href="kategori.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalForm">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th width="80">Foto</th>
                                        <th>Nama Kategori</th>
                                        <th>Deskripsi</th>
                                        <th width="120">Jumlah Produk</th>
                                        <th width="120" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($data) > 0): ?>
                                        <?php $no = 1; while ($k = mysqli_fetch_assoc($data)):
                                            $img = (!empty($k['foto']) && file_exists("../uploads/kategori/" . $k['foto']))
                                                ? "../uploads/kategori/" . $k['foto']
                                                : "../assets/img/kategori.png";
                                            
                                            $has_products = $k['jumlah_produk'] > 0;
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="text-center">
                                                    <img src="<?= $img ?>" class="category-photo" 
                                                         alt="<?= htmlspecialchars($k['nama_kategori']) ?>">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($k['nama_kategori']) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($k['deskripsi']): ?>
                                                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($k['deskripsi']) ?>">
                                                            <?= strlen($k['deskripsi']) > 50 ? substr($k['deskripsi'], 0, 50) . '...' : $k['deskripsi'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge-products <?= $has_products ? 'badge-with-products' : 'badge-no-products' ?>">
                                                        <?= $k['jumlah_produk'] ?> Produk
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-action btn-edit" 
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalForm"
                                                                data-id="<?= $k['id'] ?>"
                                                                data-nama="<?= htmlspecialchars($k['nama_kategori']) ?>"
                                                                data-deskripsi="<?= htmlspecialchars($k['deskripsi']) ?>"
                                                                title="Edit Kategori">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <?php if (!$has_products): ?>
                                                            <a href="?hapus=<?= $k['id'] ?>" 
                                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kategori <?= htmlspecialchars($k['nama_kategori']) ?>?')"
                                                               class="btn-action btn-delete"
                                                               title="Hapus Kategori">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn-action btn-disabled" disabled
                                                                    title="Tidak dapat menghapus kategori yang memiliki produk">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="no-data">
                                                    <i class="bi bi-tags"></i>
                                                    <h5 class="mt-3">Tidak ada data kategori</h5>
                                                    <p class="text-muted">Belum ada kategori terdaftar atau data tidak ditemukan</p>
                                                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalForm">
                                                        <i class="bi bi-plus-circle me-1"></i> Tambah Kategori Pertama
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FORM -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-tag-fill me-2"></i>Form Kategori</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="f-id">
                    
                    <div class="mb-3">
                        <label for="f-nama" class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="f-nama" name="nama_kategori" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="f-deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="f-deskripsi" name="deskripsi" rows="3" 
                                  placeholder="Masukkan deskripsi kategori..."></textarea>
                    </div>
                    
                    <div class="mb-0">
                        <label for="f-foto" class="form-label">Foto Kategori</label>
                        <input type="file" class="form-control" id="f-foto" name="foto" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP. Maksimal 2MB. (Opsional)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary" name="simpan">
                        <i class="bi bi-check-circle me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal initialization
        const modalForm = document.getElementById('modalForm');
        modalForm.addEventListener('show.bs.modal', function(e) {
            const button = e.relatedTarget;
            
            // Reset form untuk tambah baru
            if (!button || !button.dataset.id) {
                document.getElementById('f-id').value = '';
                document.getElementById('f-nama').value = '';
                document.getElementById('f-deskripsi').value = '';
                document.getElementById('f-foto').value = '';
                return;
            }
            
            // Set values untuk edit
            document.getElementById('f-id').value = button.getAttribute('data-id') || '';
            document.getElementById('f-nama').value = button.getAttribute('data-nama') || '';
            document.getElementById('f-deskripsi').value = button.getAttribute('data-deskripsi') || '';
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto focus search on page load
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                setTimeout(() => {
                    searchInput.focus();
                }, 300);
            }
        });
    </script>
</body>
</html>