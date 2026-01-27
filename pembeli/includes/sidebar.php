<?php
// sidebar.php di folder pembeli/includes/

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    return;
}

include "../config/database.php";
$idPembeli = $_SESSION['user']['id'] ?? 0;
$jumlahKeranjang = 0;

if ($idPembeli > 0) {
    $qKeranjang = mysqli_query($conn, "SELECT SUM(qty) AS jumlah FROM keranjang WHERE pembeli_id='$idPembeli'");
    if ($qKeranjang) {
        $keranjang = mysqli_fetch_assoc($qKeranjang);
        $jumlahKeranjang = $keranjang['jumlah'] ?? 0;
    }
}

$user = $_SESSION['user'] ?? ['nama' => 'Pembeli', 'username' => 'pembeli'];
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
?>
<nav class="sidebar" style="
    background-color: #343a40;
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    padding: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
">
    <div style="
        background-color: #495057;
        padding: 20px;
        text-align: center;
        flex-shrink: 0;
    ">
        <h4 class="mb-0"><i class="bi bi-book-half"></i> BOOKIE</h4>
        <small class="text-light">Dashboard Pembeli</small>
    </div>
    
    <div style="
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    ">
        <div style="
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        ">
            <div class="mb-3">
                <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
            </div>
            <h6><?= htmlspecialchars($user['nama']) ?></h6>
            <small class="text-light">@<?= htmlspecialchars($user['email']) ?></small>
        </div>
        
        <div style="
            flex: 1;
            overflow-y: auto;
            padding-bottom: 20px;
        ">
            <ul style="list-style: none; padding: 20px; margin: 0;">
                <li style="margin-bottom: 5px;">
                    <a href="dashboard.php" style="
                        color: <?= $currentPage == 'dashboard.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'dashboard.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'dashboard.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="pesanan.php" style="
                        color: <?= $currentPage == 'pesanan.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'pesanan.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'pesanan.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-cart-check me-2"></i> Pesanan
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="keranjang.php" style="
                        color: <?= $currentPage == 'keranjang.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'keranjang.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'keranjang.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-basket me-2"></i> Keranjang
                        <?php if($jumlahKeranjang > 0): ?>
                        <span style="
                            background-color: #dc3545;
                            color: white;
                            font-size: 0.7rem;
                            padding: 3px 6px;
                            border-radius: 10px;
                            float: right;
                        "><?= $jumlahKeranjang ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="status.php" style="
                        color: <?= $currentPage == 'status.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'status.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'status.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-clock-history me-2"></i> Status
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="chat.php" style="
                        color: <?= $currentPage == 'chat.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'chat.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'chat.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-chat-dots me-2"></i> Chat
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="buku.php" style="
                        color: <?= $currentPage == 'buku.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'buku.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'buku.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-book me-2"></i> Your Book
                    </a>
                </li>
                <li style="margin-bottom: 5px;">
                    <a href="laboratorium.php" style="
                        color: <?= $currentPage == 'laboratorium.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'laboratorium.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'laboratorium.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-building me-2"></i> Laboratory
                    </a>
                </li>
            </ul>
            
            <hr style="border-color: rgba(255,255,255,0.1); margin: 10px 20px;">
            
            <ul style="list-style: none; padding: 20px; margin: 0;">
                <li style="margin-bottom: 5px;">
                    <a href="profile.php" style="
                        color: <?= $currentPage == 'profile.php' ? 'white' : '#adb5bd' ?>;
                        padding: 12px 20px;
                        border-left: 3px solid <?= $currentPage == 'profile.php' ? '#6f42c1' : 'transparent' ?>;
                        text-decoration: none;
                        display: block;
                        background-color: <?= $currentPage == 'profile.php' ? 'rgba(255,255,255,0.1)' : 'transparent' ?>;
                    ">
                        <i class="bi bi-person me-2"></i> My Account
                    </a>
                </li>
                <li>
                    <a href="../auth/logout.php" style="
                        color: #dc3545;
                        padding: 12px 20px;
                        border-left: 3px solid transparent;
                        text-decoration: none;
                        display: block;
                    ">
                        <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div style="
        padding: 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
        background-color: #343a40;
    ">
        <small class="text-muted">BOOKIE &copy; 2024</small>
        <br>
        <small class="text-muted">Help & Support</small>
    </div>
</nav>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    /* Scrollbar styling untuk semua browser */
    .sidebar div[style*='overflow-y: auto']::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar div[style*='overflow-y: auto']::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.1);
        border-radius: 3px;
    }
    
    .sidebar div[style*='overflow-y: auto']::-webkit-scrollbar-thumb {
        background: #6f42c1;
        border-radius: 3px;
    }
    
    .sidebar div[style*='overflow-y: auto']::-webkit-scrollbar-thumb:hover {
        background: #5a32a3;
    }
    
    /* Firefox */
    .sidebar div[style*='overflow-y: auto'] {
        scrollbar-width: thin;
        scrollbar-color: #6f42c1 rgba(255,255,255,0.1);
    }
    
    /* Hover effect untuk semua link */
    .sidebar a:hover {
        background-color: rgba(255,255,255,0.1) !important;
        color: white !important;
    }
</style>