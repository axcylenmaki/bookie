<?php
/* =====================
   SIDEBAR PENJUAL
   Include file ini di semua halaman penjual
   Contoh: include "includes/sidebar.php";
===================== */

// Pastikan session sudah dimulai di file utama
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

$idPenjual = $_SESSION['user']['id'];

// Ambil data penjual untuk sidebar
$qPenjual = mysqli_query($conn,"
    SELECT nama, foto, aktif, email, created_at
    FROM users
    WHERE id='$idPenjual'
    LIMIT 1
");

if (!$qPenjual) {
    die("Query error: " . mysqli_error($conn));
}

$dataPenjual = mysqli_fetch_assoc($qPenjual);

if (!$dataPenjual) {
    die("Data penjual tidak ditemukan!");
}

$namaPenjual = $dataPenjual['nama'] ?? 'Penjual';
$emailPenjual = $dataPenjual['email'] ?? '';
$tanggalGabung = date('d M Y', strtotime($dataPenjual['created_at'] ?? 'now'));

// Foto profil
$foto = (!empty($dataPenjual['foto']) && file_exists("../uploads/".$dataPenjual['foto']))
    ? "../uploads/".$dataPenjual['foto']
    : "../assets/img/user.png";

$statusAkun = $dataPenjual['aktif'] ?? 'tidak';
?>

<!-- SIDEBAR HTML -->
<div class="col-2 sidebar-dark p-0">
    <div class="p-4">
        <div class="sidebar-brand mb-5">
            <i class="bi bi-book-half"></i> BOOK<span style="color: #adb5bd;">IE</span>
        </div>

        <div class="text-center mb-4">
            <img src="<?= $foto ?>"
                 class="rounded-circle user-avatar mb-3"
                 style="width:90px;height:90px;object-fit:cover"
                 onerror="this.src='../assets/img/user.png'">
            <h6 class="fw-bold text-white mb-1"><?= htmlspecialchars($namaPenjual) ?></h6>
            <small class="text-white-50"><?= htmlspecialchars($emailPenjual) ?></small>
            <div class="mt-2">
                <span class="badge <?= $statusAkun=='ya'?'bg-success':'bg-danger' ?>">
                    <?= $statusAkun=='ya'?'Aktif':'Nonaktif' ?>
                </span>
            </div>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" 
                   href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'produk.php' ? 'active' : '' ?>" 
                   href="produk.php">
                    <i class="bi bi-box-seam"></i> Produk
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'transaksi.php' ? 'active' : '' ?>" 
                   href="transaksi.php">
                    <i class="bi bi-cart-check"></i> Transaksi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'active' : '' ?>" 
                   href="laporan.php">
                    <i class="bi bi-graph-up"></i> Laporan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>" 
                   href="chat.php">
                    <i class="bi bi-chat-dots"></i> Chat
                    <span class="badge bg-warning float-end" id="chat-badge">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" 
                   href="profile.php">
                    <i class="bi bi-person-circle"></i> Profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'help.php' ? 'active' : '' ?>" 
                   href="help.php">
                    <i class="bi bi-question-circle"></i> Bantuan
                </a>
            </li>
        </ul>
    </div>
    
    <div class="p-3 border-top border-white-10 mt-5">
        <small class="text-white-50 d-block mb-2">Bergabung sejak <?= $tanggalGabung ?></small>
        <a href="../auth/logout.php" class="btn btn-logout w-100">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>