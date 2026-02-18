<?php
// sidebar.php di folder pembeli/includes/

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    return;
}

// Deteksi posisi file untuk path yang benar
$basePath = '';
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Jika sedang di folder chat, path perlu disesuaikan
if ($currentDir == 'chat') {
    $basePath = '../'; // Naik 1 level dari chat ke pembeli
} else {
    $basePath = ''; // Sudah di folder pembeli
}

// Include database dengan path yang benar
include dirname(__DIR__, 2) . "/config/database.php";

// Update last_activity untuk user
if (isset($_SESSION['user']['id']) && isset($conn)) {
    mysqli_query($conn, "
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = '{$_SESSION['user']['id']}'
    ");
}

$idPembeli = $_SESSION['user']['id'] ?? 0;
$jumlahKeranjang = 0;
$jumlahChatUnread = 0;
$jumlahNotifikasi = 0;

if ($idPembeli > 0 && isset($conn)) {
    // Hitung jumlah keranjang
    $qKeranjang = mysqli_query($conn, "SELECT SUM(jumlah) AS jumlah FROM keranjang WHERE id_user='$idPembeli'");
    if ($qKeranjang) {
        $keranjang = mysqli_fetch_assoc($qKeranjang);
        $jumlahKeranjang = $keranjang['jumlah'] ?? 0;
    }
    
    // Hitung chat unread
    $qRooms = mysqli_query($conn, "SELECT id FROM chat_rooms WHERE id_pembeli='$idPembeli'");
    if ($qRooms && mysqli_num_rows($qRooms) > 0) {
        $roomIds = [];
        while ($room = mysqli_fetch_assoc($qRooms)) {
            $roomIds[] = $room['id'];
        }
        $roomList = implode(',', $roomIds);
        
        $qChatUnread = mysqli_query($conn, "
            SELECT COUNT(*) as total_unread 
            FROM chat 
            WHERE id_room IN ($roomList) 
            AND penerima_id = '$idPembeli' 
            AND dibaca = 0
        ");
        
        if ($qChatUnread) {
            $chatUnread = mysqli_fetch_assoc($qChatUnread);
            $jumlahChatUnread = $chatUnread['total_unread'] ?? 0;
        }
    }
    
    // Hitung notifikasi unread
    $qNotif = mysqli_query($conn, "
        SELECT COUNT(*) as total 
        FROM notifikasi 
        WHERE id_user='$idPembeli' AND status='unread'
    ");
    if ($qNotif) {
        $notif = mysqli_fetch_assoc($qNotif);
        $jumlahNotifikasi = $notif['total'] ?? 0;
    }
}

$user = $_SESSION['user'] ?? ['nama' => 'Pembeli', 'email' => 'pembeli@example.com'];
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');

// Cek apakah sedang di halaman chat
$isChatPage = ($currentPage == 'index.php' && $currentDir == 'chat');
?>

<nav class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-book-half"></i> BOOKIE
        <small>Dashboard Pembeli</small>
    </div>
    
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <h6><?= htmlspecialchars($user['nama']) ?></h6>
        <small><?= htmlspecialchars($user['email']) ?></small>
    </div>
    
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <a href="<?= $basePath ?>dashboard.php" class="menu-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
            <?php if($jumlahNotifikasi > 0): ?>
                <span class="badge notif"><?= $jumlahNotifikasi ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Pesanan Saya -->
        <a href="<?= $basePath ?>pesanan.php" class="menu-item <?= $currentPage == 'pesanan.php' ? 'active' : '' ?>">
            <i class="bi bi-cart-check"></i>
            <span>Pesanan Saya</span>
            <?php 
            if (isset($conn)) {
                $qPending = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE id_user='$idPembeli' AND status='pending'");
                if ($qPending) {
                    $pending = mysqli_fetch_assoc($qPending);
                    if($pending['total'] > 0):
            ?>
                <span class="badge warning"><?= $pending['total'] ?></span>
            <?php 
                    endif;
                }
            }
            ?>
        </a>
        
        <!-- Keranjang -->
        <a href="<?= $basePath ?>keranjang.php" class="menu-item <?= $currentPage == 'keranjang.php' ? 'active' : '' ?>">
            <i class="bi bi-basket"></i>
            <span>Keranjang</span>
            <?php if($jumlahKeranjang > 0): ?>
                <span class="badge"><?= $jumlahKeranjang ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Chat -->
        <a href="<?= $basePath ?>chat/" class="menu-item <?= $isChatPage ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i>
            <span>Chat dengan Penjual</span>
            <?php if($jumlahChatUnread > 0): ?>
                <span class="badge"><?= $jumlahChatUnread ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Belanja / Produk -->
        <a href="<?= $basePath ?>produk.php" class="menu-item <?= $currentPage == 'produk.php' ? 'active' : '' ?>">
            <i class="bi bi-book"></i>
            <span>Belanja Buku</span>
        </a>
        
        <div class="menu-divider"></div>
        
        <!-- My Account -->
        <a href="<?= $basePath ?>profile.php" class="menu-item <?= $currentPage == 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person"></i>
            <span>My Account</span>
        </a>
        
        <!-- Sign Out -->
        <a href="<?= $basePath ?>../auth/logout.php" class="menu-item logout" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <small>BOOKIE &copy; <?= date('Y') ?></small>
        <small>Version 2.0.0</small>
    </div>
</nav>

<style>
/* Style sidebar sama seperti sebelumnya */
.sidebar {
    background-color: #1a1a2e;
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    padding: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.sidebar-brand {
    background: linear-gradient(135deg, #16213e, #0f3460);
    padding: 25px 20px;
    text-align: center;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-brand i {
    font-size: 1.5rem;
    margin-right: 5px;
}

.sidebar-brand h4 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.sidebar-brand small {
    display: block;
    color: #a0a0a0;
    font-size: 0.8rem;
    margin-top: 5px;
}

.sidebar-profile {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border: 3px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}

.sidebar-profile:hover .profile-avatar {
    transform: scale(1.05);
    border-color: #667eea;
}

.sidebar-profile h6 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 5px 0;
    color: white;
}

.sidebar-profile small {
    color: #a0a0a0;
    font-size: 0.8rem;
    word-break: break-all;
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding: 20px 15px;
}

.menu-item {
    color: #a0a0a0;
    padding: 12px 15px;
    border-radius: 8px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}

.menu-item i {
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.menu-item span {
    flex: 1;
    font-size: 0.95rem;
}

.menu-item .badge {
    background-color: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
}

.menu-item .badge.warning {
    background-color: #ffc107;
    color: #1a1a2e;
}

.menu-item .badge.notif {
    background-color: #6f42c1;
}

.menu-item:hover {
    background-color: rgba(102, 126, 234, 0.15);
    color: white;
    transform: translateX(5px);
}

.menu-item.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.menu-item.logout {
    color: #ff6b6b;
}

.menu-item.logout:hover {
    background-color: rgba(255, 107, 107, 0.1);
    color: #ff6b6b;
}

.menu-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 20px 0;
}

.sidebar-footer {
    padding: 15px 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
    background-color: #16213e;
    text-align: center;
}

.sidebar-footer small {
    display: block;
    color: #a0a0a0;
    font-size: 0.7rem;
    line-height: 1.5;
}

/* Scrollbar styling */
.sidebar-menu::-webkit-scrollbar {
    width: 5px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: #764ba2;
}

/* Firefox */
.sidebar-menu {
    scrollbar-width: thin;
    scrollbar-color: #667eea rgba(255,255,255,0.05);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 240px;
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .menu-item {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
    
    .profile-avatar {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }
}

/* Animation untuk badge */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.badge {
    animation: pulse 2s infinite;
}
</style>

<script>
// Toggle sidebar on mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('show');
}

// Auto close on mobile when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile && sidebar.classList.contains('show')) {
        const isClickInside = sidebar.contains(event.target);
        if (!isClickInside) {
            sidebar.classList.remove('show');
        }
    }
});

// Update notifikasi realtime
function updateNotifications() {
    fetch('<?= $basePath ?>ajax/get_notif_count.php')
        .then(response => response.json())
        .then(data => {
            // Update badge notifikasi di dashboard
            const dashboardLink = document.querySelector('a[href="<?= $basePath ?>dashboard.php"]');
            const existingBadge = dashboardLink?.querySelector('.badge.notif');
            
            if (data.unread_count > 0) {
                if (existingBadge) {
                    existingBadge.textContent = data.unread_count;
                } else if (dashboardLink) {
                    const badge = document.createElement('span');
                    badge.className = 'badge notif';
                    badge.textContent = data.unread_count;
                    dashboardLink.appendChild(badge);
                }
            } else if (existingBadge) {
                existingBadge.remove();
            }
            
            // Update badge chat
            const chatLink = document.querySelector('a[href="<?= $basePath ?>chat/"]');
            if (chatLink && data.chat_unread > 0) {
                let chatBadge = chatLink.querySelector('.badge');
                if (chatBadge) {
                    chatBadge.textContent = data.chat_unread;
                } else {
                    chatBadge = document.createElement('span');
                    chatBadge.className = 'badge';
                    chatBadge.textContent = data.chat_unread;
                    chatLink.appendChild(chatBadge);
                }
            }
        })
        .catch(err => console.log('Error fetching notifications:', err));
}

// Update setiap 30 detik
setInterval(updateNotifications, 30000);
</script>