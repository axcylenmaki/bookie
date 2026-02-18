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
   SIMPAN EDIT PEMBELI
===================== */
if (isset($_POST['simpan'])) {
    $id     = (int)$_POST['id'];
    $nama   = mysqli_real_escape_string($conn, $_POST['nama']);
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    // CEK 1: Jika no_hp tidak kosong, cek duplikat di SEMUA USER (semua role)
    if (!empty($no_hp)) {
        $cek_no_hp = mysqli_query($conn, "
            SELECT id, nama, role FROM users 
            WHERE no_hp = '$no_hp' 
            AND id != '$id'
            LIMIT 1
        ");
        
        if (mysqli_num_rows($cek_no_hp) > 0) {
            $user_dup = mysqli_fetch_assoc($cek_no_hp);
            $_SESSION['error'] = "Nomor HP '$no_hp' sudah digunakan oleh {$user_dup['role']}: {$user_dup['nama']}!";
            header("Location: pembeli.php");
            exit;
        }
    }
    
    // CEK 2: Email harus unik di SEMUA USER (semua role)
    $cek_email = mysqli_query($conn, "
        SELECT id, nama, role FROM users 
        WHERE email = '$email' 
        AND id != '$id'
        LIMIT 1
    ");
    
    if (mysqli_num_rows($cek_email) > 0) {
        $user_dup = mysqli_fetch_assoc($cek_email);
        $_SESSION['error'] = "Email '$email' sudah digunakan oleh {$user_dup['role']}: {$user_dup['nama']}!";
        header("Location: pembeli.php");
        exit;
    }

    // JIKA VALIDASI BERHASIL, LAKUKAN UPDATE
    // Handle no_hp kosong -> set NULL
    if (empty($no_hp)) {
        $update_query = "
            UPDATE users SET
                nama = '$nama',
                email = '$email',
                no_hp = NULL,
                alamat = '$alamat'
            WHERE id = '$id' AND role = 'pembeli'
        ";
    } else {
        $update_query = "
            UPDATE users SET
                nama = '$nama',
                email = '$email',
                no_hp = '$no_hp',
                alamat = '$alamat'
            WHERE id = '$id' AND role = 'pembeli'
        ";
    }
    
    $result = mysqli_query($conn, $update_query);
    
    if ($result) {
        $_SESSION['success'] = "Data pembeli berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui data: " . mysqli_error($conn);
    }

    header("Location: pembeli.php");
    exit;
}

/* =====================
   HAPUS (OFFLINE ONLY)
===================== */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    
    // Cek apakah user offline (tidak aktif dalam 5 menit terakhir)
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT 
            id,
            CASE 
                WHEN last_activity IS NULL THEN 'offline'
                WHEN TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 'online'
                ELSE 'offline'
            END as current_status
        FROM users 
        WHERE id = '$id' AND role = 'pembeli'
    "));

    if ($cek && $cek['current_status'] == 'offline') {
        mysqli_query($conn, "DELETE FROM users WHERE id = '$id'");
    }

    header("Location: pembeli.php");
    exit;
}

/* =====================
   DATA PEMBELI dengan search REALTIME
===================== */
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Query dengan status realtime - FIXED untuk handle NULL
$query = "SELECT 
            id, nama, email, no_hp, alamat, foto, last_activity,
            CASE 
                WHEN last_activity IS NULL THEN 'offline'
                WHEN TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 'online'
                ELSE 'offline'
            END as current_status,
            CASE
                WHEN last_activity IS NULL THEN NULL
                ELSE TIME_FORMAT(TIMEDIFF(NOW(), last_activity), '%H:%i:%s')
            END as last_seen
          FROM users
          WHERE role = 'pembeli'";

if (!empty($search)) {
    $query .= " AND (nama LIKE '%$search%' OR email LIKE '%$search%' OR no_hp LIKE '%$search%')";
}

if (!empty($status_filter) && in_array($status_filter, ['online', 'offline'])) {
    if ($status_filter == 'online') {
        $query .= " AND last_activity IS NOT NULL AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5";
    } else {
        $query .= " AND (last_activity IS NULL OR TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > 5)";
    }
}

$query .= " ORDER BY 
            CASE 
                WHEN last_activity IS NOT NULL AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 0
                ELSE 1
            END,
            last_activity DESC,
            id DESC";
            
$data = mysqli_query($conn, $query);

if (!$data) {
    // Debug error query
    die("Query error: " . mysqli_error($conn));
}

$total_pembeli = mysqli_num_rows($data);

// Hitung statistik realtime
$stats_result = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN last_activity IS NOT NULL AND TIMESTAMPDIFF(MINUTE, last_activity, NOW()) <= 5 THEN 1 ELSE 0 END) as online_count,
        SUM(CASE WHEN last_activity IS NULL OR TIMESTAMPDIFF(MINUTE, last_activity, NOW()) > 5 THEN 1 ELSE 0 END) as offline_count
    FROM users 
    WHERE role = 'pembeli'
");

if ($stats_result) {
    $stats_query = mysqli_fetch_assoc($stats_result);
} else {
    $stats_query = ['total' => 0, 'online_count' => 0, 'offline_count' => 0];
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
    <title>Data Pembeli | BOOKIE</title>
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
        
        .stats-online {
            border-color: #10b981;
        }
        
        .stats-offline {
            border-color: #ef4444;
        }
        
        .stats-total {
            border-color: #3b82f6;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .user-photo {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            min-width: 90px;
            text-align: center;
        }
        
        .status-online {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .status-offline {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .last-seen {
            font-size: 0.75rem;
            color: #6b7280;
            display: block;
            margin-top: 2px;
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
        
        .online-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .online {
            background-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .offline {
            background-color: #ef4444;
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
                            <h4 class="mb-1"><i class="bi bi-people-fill me-2"></i>Data Pembeli</h4>
                            <p class="text-muted mb-0">Status online/offline diperbarui secara realtime (5 menit terakhir)</p>
                        </div>
                        <div id="live-clock" class="badge bg-dark rounded-pill p-2 px-3">
                            <i class="bi bi-clock me-1"></i> <span id="current-time">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-total">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Pembeli</h6>
                                    <h3 class="mb-0"><?= $stats_query['total'] ?? 0 ?></h3>
                                </div>
                                <div class="icon-circle bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                    <i class="bi bi-people fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-online">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Online</h6>
                                    <h3 class="mb-0 text-success"><?= $stats_query['online_count'] ?? 0 ?></h3>
                                </div>
                                <div class="icon-circle bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                    <i class="bi bi-wifi fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card stats-offline">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Offline</h6>
                                    <h3 class="mb-0 text-danger"><?= $stats_query['offline_count'] ?? 0 ?></h3>
                                </div>
                                <div class="icon-circle bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                                    <i class="bi bi-wifi-off fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-container">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" name="search" 
                                       placeholder="Cari nama, email, atau no HP..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">Semua Status</option>
                                <option value="online" <?= $status_filter == 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="offline" <?= $status_filter == 'offline' ? 'selected' : '' ?>>Offline</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-funnel me-1"></i> Filter
                                </button>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <a href="pembeli.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th width="70">Foto</th>
                                        <th>Nama Pembeli</th>
                                        <th>Email</th>
                                        <th>No HP</th>
                                        <th width="120">Status</th>
                                        <th>Terakhir Aktif</th>
                                        <th>Alamat</th>
                                        <th width="120" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="user-table-body">
                                    <?php if (mysqli_num_rows($data) > 0): ?>
                                        <?php $no = 1; while ($p = mysqli_fetch_assoc($data)):
                                            $serverFoto = __DIR__ . "/../uploads/";
                                            $urlFoto = "../uploads/";
                                            $foto = (!empty($p['foto']) && file_exists($serverFoto . $p['foto']))
                                                ? $urlFoto . $p['foto']
                                                : "../assets/img/user.png";
                                            
                                            $is_online = $p['current_status'] == 'online';
                                            $last_activity = $p['last_activity'];
                                            $last_seen = $p['last_seen'];
                                            
                                            // Format last activity
                                            if ($last_activity) {
                                                $last_activity_formatted = date('d/m/Y H:i', strtotime($last_activity));
                                            } else {
                                                $last_activity_formatted = '-';
                                            }
                                            
                                            // Format untuk form modal
                                            $no_hp_value = $p['no_hp'] ?? '';
                                            $alamat_value = $p['alamat'] ?? '';
                                        ?>
                                            <tr data-user-id="<?= $p['id'] ?>" data-status="<?= $is_online ? 'online' : 'offline' ?>">
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="text-center">
                                                    <img src="<?= $foto ?>" class="user-photo" 
                                                         alt="<?= htmlspecialchars($p['nama']) ?>">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($p['nama']) ?></strong>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?= $p['email'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($p['email']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($p['no_hp']): ?>
                                                        <a href="tel:<?= $p['no_hp'] ?>" class="text-decoration-none">
                                                            <?= $p['no_hp'] ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= $is_online ? 'status-online' : 'status-offline' ?>">
                                                        <span class="online-indicator <?= $is_online ? 'online' : 'offline' ?>"></span>
                                                        <?= $is_online ? 'Online' : 'Offline' ?>
                                                    </span>
                                                    <?php if ($is_online && $last_seen): ?>
                                                        <span class="last-seen">
                                                            Aktif <span class="last-seen-time"><?= $last_seen ?></span> lalu
                                                        </span>
                                                    <?php elseif (!$is_online && $last_activity): ?>
                                                        <span class="last-seen">
                                                            Offline sejak <?= date('H:i', strtotime($last_activity)) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="last-seen">Belum pernah aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $last_activity_formatted ?>
                                                </td>
                                                <td>
                                                    <?php if ($p['alamat']): ?>
                                                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($p['alamat']) ?>">
                                                            <?= strlen($p['alamat']) > 30 ? substr($p['alamat'], 0, 30) . '...' : $p['alamat'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-action btn-edit" 
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalForm"
                                                                data-id="<?= $p['id'] ?>"
                                                                data-nama="<?= htmlspecialchars($p['nama']) ?>"
                                                                data-email="<?= htmlspecialchars($p['email']) ?>"
                                                                data-nohp="<?= htmlspecialchars($no_hp_value) ?>"
                                                                data-alamat="<?= htmlspecialchars($alamat_value) ?>"
                                                                title="Edit Data">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <?php if (!$is_online): ?>
                                                            <a href="?hapus=<?= $p['id'] ?>" 
                                                               onclick="return confirm('Apakah Anda yakin ingin menghapus pembeli <?= htmlspecialchars($p['nama']) ?>?')"
                                                               class="btn-action btn-delete"
                                                               title="Hapus Data">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn-action btn-disabled" disabled
                                                                    title="Tidak dapat menghapus pembeli online">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9">
                                                <div class="no-data">
                                                    <i class="bi bi-people"></i>
                                                    <h5 class="mt-3">Tidak ada data pembeli</h5>
                                                    <p class="text-muted">Belum ada pembeli terdaftar atau data tidak ditemukan</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Auto Refresh Info -->
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Data diperbarui setiap 30 detik | 
                        <span id="last-update">Terakhir update: <?= date('H:i:s') ?></span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-edit me-2"></i>Edit Data Pembeli</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="f-id">
                    
                    <div class="mb-3">
                        <label for="f-nama" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="f-nama" name="nama" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="f-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="f-email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="f-nohp" class="form-label">Nomor HP</label>
                        <input type="tel" class="form-control" id="f-nohp" name="no_hp" 
                               placeholder="Contoh: 081234567890">
                    </div>
                    
                    <div class="mb-0">
                        <label for="f-alamat" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="f-alamat" name="alamat" rows="3" 
                                  placeholder="Masukkan alamat lengkap..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary" name="simpan">
                        <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update waktu realtime
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Update waktu setiap detik
        setInterval(updateClock, 1000);
        updateClock();
        
        // Auto refresh data setiap 30 detik
        let refreshInterval = 30000; // 30 detik
        let lastUpdate = new Date();
        
        function updateLastSeenTimes() {
            document.querySelectorAll('.last-seen-time').forEach(element => {
                const timeStr = element.textContent.trim();
                if (timeStr && timeStr !== '-' && timeStr !== 'Belum pernah aktif') {
                    try {
                        // Parse HH:mm:ss
                        const parts = timeStr.split(':');
                        if (parts.length === 3) {
                            const hours = parseInt(parts[0]) || 0;
                            const minutes = parseInt(parts[1]) || 0;
                            const seconds = parseInt(parts[2]) || 0;
                            
                            // Add 30 seconds (refresh interval)
                            let totalSeconds = hours * 3600 + minutes * 60 + seconds;
                            totalSeconds += 30;
                            
                            // Format kembali
                            const newHours = Math.floor(totalSeconds / 3600);
                            const newMinutes = Math.floor((totalSeconds % 3600) / 60);
                            const newSeconds = totalSeconds % 60;
                            
                            let newTime = '';
                            if (newHours > 0) {
                                newTime = newHours.toString().padStart(2, '0') + ':' +
                                         newMinutes.toString().padStart(2, '0') + ':' +
                                         newSeconds.toString().padStart(2, '0');
                            } else {
                                newTime = newMinutes.toString().padStart(2, '0') + ':' +
                                         newSeconds.toString().padStart(2, '0');
                            }
                            
                            element.textContent = newTime;
                        }
                    } catch (e) {
                        console.error('Error parsing time:', e);
                    }
                }
            });
        }
        
        function refreshUserStatus() {
            fetch('pembeli_ajax.php?action=get_status')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update stats cards
                        if (document.querySelector('.stats-total h3')) {
                            document.querySelector('.stats-total h3').textContent = data.total;
                            document.querySelector('.stats-online h3').textContent = data.online;
                            document.querySelector('.stats-offline h3').textContent = data.offline;
                        }
                        
                        // Update last update time
                        lastUpdate = new Date();
                        document.getElementById('last-update').textContent = 
                            'Terakhir update: ' + lastUpdate.toLocaleTimeString('id-ID');
                        
                        // Update last seen times
                        updateLastSeenTimes();
                    }
                })
                .catch(error => {
                    console.error('Error refreshing status:', error);
                });
        }
        
        // Start auto-refresh
        setInterval(refreshUserStatus, refreshInterval);
        
        // Update last seen times every 30 seconds
        setInterval(updateLastSeenTimes, 30000);
        
        // Modal initialization
        const modalForm = document.getElementById('modalForm');
        modalForm.addEventListener('show.bs.modal', function(e) {
            const button = e.relatedTarget;
            
            // Debug log
            console.log('Button data:', {
                id: button.getAttribute('data-id'),
                nama: button.getAttribute('data-nama'),
                email: button.getAttribute('data-email'),
                nohp: button.getAttribute('data-nohp'),
                alamat: button.getAttribute('data-alamat')
            });
            
            // Set values
            document.getElementById('f-id').value = button.getAttribute('data-id') || '';
            document.getElementById('f-nama').value = button.getAttribute('data-nama') || '';
            document.getElementById('f-email').value = button.getAttribute('data-email') || '';
            document.getElementById('f-nohp').value = button.getAttribute('data-nohp') || '';
            document.getElementById('f-alamat').value = button.getAttribute('data-alamat') || '';
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
            
            // Initial refresh setelah 5 detik
            setTimeout(refreshUserStatus, 5000);
            
            // Ping untuk admin tetap online (setiap 1 menit)
            setInterval(function() {
                fetch('../includes/ping.php')
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            window.location.href = '../auth/login.php';
                        }
                    })
                    .catch(error => console.error('Ping error:', error));
            }, 60000);
        });
    </script>
</body>
</html>