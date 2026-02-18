<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
    header("Location: ../auth/login.php");
    exit;
}

$namaUser   = $_SESSION['user']['nama'] ?? 'Penjual';
$fotoUser   = $_SESSION['user']['foto'] ?? '';

// PERBAIKAN: Mendeteksi halaman aktif dengan akurat
$currentScript = $_SERVER['SCRIPT_NAME']; // Contoh: /bookie/penjual/chat/chat.php
$baseDir = dirname($_SERVER['SCRIPT_NAME']); // Contoh: /bookie/penjual/chat

// Ekstrak nama file atau path relatif
$currentPage = basename($currentScript); // chat.php

// Untuk menentukan halaman aktif di menu, kita perlu logika khusus
$activePage = '';

// Tentukan halaman aktif berdasarkan path
if (strpos($currentScript, 'chat/chat.php') !== false || $currentPage == 'chat/chat.php') {
    $activePage = 'chat';
} elseif (strpos($currentScript, 'dashboard.php') !== false) {
    $activePage = 'dashboard';
} elseif (strpos($currentScript, 'profile.php') !== false) {
    $activePage = 'profile';
} elseif (strpos($currentScript, 'produk.php') !== false) {
    $activePage = 'produk';
} elseif (strpos($currentScript, 'pesanan.php') !== false) {
    $activePage = 'pesanan';
} elseif (strpos($currentScript, 'status.php') !== false) {
    $activePage = 'status';
} elseif (strpos($currentScript, 'laporan.php') !== false) {
    $activePage = 'laporan';
} elseif (strpos($currentScript, 'penjual_lain.php') !== false) {
    $activePage = 'penjual_lain';
} elseif (strpos($currentScript, 'help.php') !== false) {
    $activePage = 'help';
}

// Path foto - PERHATIKAN: sidebar.php ada di folder includes, jadi gunakan ../../
$fotoPath = '';
if (!empty($fotoUser)) {
    // Coba beberapa kemungkinan lokasi file
    $possiblePaths = [
        "../../uploads/" . $fotoUser,
        // "../../uploads/" . $fotoUser,
        // "../../../uploads/" . $fotoUser,
        // "uploads/profile/" . $fotoUser
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $fotoPath = $path;
            break;
        }
    }
}

// Jika tidak ditemukan, gunakan default
if (empty($fotoPath)) {
    $fotoPath = "../../assets/img/user.png";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
/* ===== RESET & BASE STYLES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa;
    overflow-x: hidden;
}

/* ===== SIDEBAR STYLES ===== */
.sidebar {
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    transition: all 0.3s ease;
    overflow: hidden;
}

/* ===== LOGO SECTION ===== */
.sidebar-logo {
    padding: 22px 20px;
    text-align: center;
    font-size: 26px;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    letter-spacing: 1.2px;
    color: #fff;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.sidebar-logo::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent);
    transition: left 0.7s ease;
}

.sidebar-logo:hover::before {
    left: 100%;
}

.sidebar-logo i {
    font-size: 28px;
    color: #3498db;
}

/* ===== PROFILE SECTION ===== */
.sidebar-profile {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-decoration: none;
    color: #fff;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.sidebar-profile:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.sidebar-profile::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    transition: width 0.3s ease;
}

.sidebar-profile:hover::after {
    width: 100%;
}

.sidebar-profile img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.sidebar-profile:hover img {
    border-color: #3498db;
    transform: scale(1.08);
    box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
}

.sidebar-profile .profile-info {
    flex: 1;
    min-width: 0;
}

.sidebar-profile .name {
    font-size: 16px;
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-profile .role {
    font-size: 13px;
    color: #95a5a6;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.role i {
    font-size: 12px;
}

/* ===== MENU SCROLLABLE AREA ===== */
.sidebar-menu {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: #3498db #2c3e50;
}

.sidebar-menu::-webkit-scrollbar {
    width: 6px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: #2c3e50;
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: linear-gradient(#3498db, #2980b9);
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(#2980b9, #1c5a7a);
}

/* ===== MENU ITEMS ===== */
.menu-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    color: #bdc3c7;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    margin: 4px 12px;
    border-radius: 10px;
    overflow: hidden;
}

.menu-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: #3498db;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    transform: translateX(8px);
}

.menu-item:hover::before {
    transform: scaleY(1);
}

.menu-item.active {
    background: linear-gradient(90deg, rgba(52, 152, 219, 0.2), rgba(41, 128, 185, 0.15));
    color: #3498db;
    font-weight: 600;
}

.menu-item.active::before {
    transform: scaleY(1);
    background: linear-gradient(to bottom, #3498db, #2ecc71);
}

.menu-item.active:hover {
    background: linear-gradient(90deg, rgba(52, 152, 219, 0.25), rgba(41, 128, 185, 0.2));
}

.menu-item i {
    font-size: 19px;
    width: 24px;
    text-align: center;
    transition: transform 0.3s ease;
    z-index: 1;
}

.menu-item:hover i {
    transform: scale(1.15);
}

.menu-item span {
    flex: 1;
    z-index: 1;
}

/* ===== BADGE STYLES ===== */
.menu-badge {
    margin-left: auto;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
    box-shadow: 0 3px 6px rgba(231, 76, 60, 0.2);
    z-index: 1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.menu-item.active .menu-badge {
    background: linear-gradient(135deg, #3498db, #2980b9);
}

/* ===== SIDEBAR FOOTER ===== */
.sidebar-footer {
    padding: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.footer-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 13px;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.3s ease;
    text-align: center;
    border: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.footer-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.footer-btn:hover::before {
    left: 100%;
}

.footer-btn i {
    font-size: 18px;
    transition: transform 0.3s ease;
}

.footer-btn:hover i {
    transform: translateX(3px);
}

.footer-btn.logout {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.25);
}

.footer-btn.logout:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(231, 76, 60, 0.35);
}

.footer-btn.help {
    background: transparent;
    border: 2px solid #3498db;
    color: #3498db;
}

.footer-btn.help:hover {
    background: rgba(52, 152, 219, 0.1);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
}

/* ===== CONTENT AREA ===== */
.content {
    margin-left: 260px;
    padding: 30px;
    min-height: 100vh;
    background: #f8f9fa;
    transition: margin-left 0.3s ease;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 992px) {
    .sidebar {
        width: 240px;
    }
    
    .content {
        margin-left: 240px;
        padding: 25px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 70px;
        overflow: visible;
    }
    
    .sidebar-logo span,
    .sidebar-profile .profile-info,
    .menu-item span,
    .menu-badge,
    .footer-btn span {
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .sidebar-logo {
        justify-content: center;
        padding: 22px 10px;
    }
    
    .sidebar-profile {
        padding: 20px 10px;
        justify-content: center;
    }
    
    .menu-item {
        padding: 15px 10px;
        justify-content: center;
        margin: 4px 8px;
    }
    
    .footer-btn {
        padding: 13px 10px;
        justify-content: center;
    }
    
    /* Hover state untuk mobile */
    .sidebar:hover {
        width: 260px;
    }
    
    .sidebar:hover .sidebar-logo span,
    .sidebar:hover .sidebar-profile .profile-info,
    .sidebar:hover .menu-item span,
    .sidebar:hover .menu-badge,
    .sidebar:hover .footer-btn span {
        opacity: 1;
        visibility: visible;
    }
    
    .sidebar:hover .sidebar-logo {
        justify-content: flex-start;
        padding-left: 20px;
    }
    
    .sidebar:hover .sidebar-profile {
        justify-content: flex-start;
        padding-left: 20px;
    }
    
    .sidebar:hover .menu-item {
        justify-content: flex-start;
        padding-left: 20px;
    }
    
    .sidebar:hover .footer-btn {
        justify-content: flex-start;
        padding-left: 20px;
    }
    
    .content {
        margin-left: 70px;
    }
    
    .sidebar:hover ~ .content {
        margin-left: 260px;
    }
}

@media (max-width: 480px) {
    .content {
        padding: 20px 15px;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.menu-item {
    animation: slideInRight 0.4s ease forwards;
    animation-delay: calc(var(--item-index) * 0.05s);
    opacity: 0;
}

/* ===== ACTIVE STATE INDICATOR ===== */
.menu-item.active {
    position: relative;
}

.menu-item.active::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background: #2ecc71;
    border-radius: 50%;
    box-shadow: 0 0 10px #2ecc71;
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>
</head>
<body>

<div class="sidebar">
    <!-- LOGO SECTION -->
    <div class="sidebar-logo">
        <i class="bi bi-book"></i>
        <span>BOOKIE STORE</span>
    </div>

    <!-- PROFILE SECTION -->
    <a href="profile.php" class="sidebar-profile">
        <img src="<?= htmlspecialchars($fotoPath) ?>" alt="Profile" onerror="this.src='../uploads/'">
        <div class="profile-info">
            <div class="name"><?= htmlspecialchars($namaPenjual) ?></div>
            <div class="role">
                <i class="bi bi-shop"></i>
                <span>Penjual</span>
            </div>
        </div>
    </a>

    <!-- MENU SECTION -->
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <a href="dashboard.php" class="menu-item <?= $activePage == 'dashboard' ? 'active' : '' ?>" style="--item-index: 1">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
            <span class="menu-badge" id="dashboard-badge">3</span>
        </a>
        
        <!-- Profile -->
        <a href="profile.php" class="menu-item <?= $activePage == 'profile' ? 'active' : '' ?>" style="--item-index: 2">
            <i class="bi bi-person-circle"></i>
            <span>Profil Saya</span>
        </a>
        
        <!-- Produk -->
        <a href="produk.php" class="menu-item <?= $activePage == 'produk' ? 'active' : '' ?>" style="--item-index: 3">
            <i class="bi bi-box-seam"></i>
            <span>Kelola Produk</span>
            <span class="menu-badge" id="produk-badge">12</span>
        </a>
        
        <!-- Chat - TOMBOL INI AKAN AKTIF DI chat.php -->
        <a href="chat/chat.php" class="menu-item <?= $activePage == 'chat' ? 'active' : '' ?>" style="--item-index: 4">
            <i class="bi bi-chat-dots"></i>
            <span>Pesan & Chat</span>
            <span class="menu-badge" id="chat-badge">5</span>
        </a>
        
        <!-- Pesanan -->
        <a href="pesanan.php" class="menu-item <?= $activePage == 'pesanan' ? 'active' : '' ?>" style="--item-index: 5">
            <i class="bi bi-cart-check"></i>
            <span>Pesanan</span>
            <span class="menu-badge" id="pesanan-badge">8</span>
        </a>
        
        <!-- Status -->
        <a href="status.php" class="menu-item <?= $activePage == 'status' ? 'active' : '' ?>" style="--item-index: 6">
            <i class="bi bi-graph-up"></i>
            <span>Status Toko</span>
        </a>
        
        <!-- Laporan -->
        <a href="laporan.php" class="menu-item <?= $activePage == 'laporan' ? 'active' : '' ?>" style="--item-index: 7">
            <i class="bi bi-file-earmark-bar-graph"></i>
            <span>Laporan</span>
        </a>
        
        <!-- Penjual Lain -->
        <a href="penjual_lain.php" class="menu-item <?= $activePage == 'penjual_lain' ? 'active' : '' ?>" style="--item-index: 8">
            <i class="bi bi-people-fill"></i>
            <span>Komunitas Penjual</span>
        </a>
    </div>

    <!-- FOOTER SECTION -->
    <div class="sidebar-footer">
        <a href="../../auth/logout.php" 
           class="footer-btn logout"
           onclick="return confirm('Apakah Anda yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar</span>
        </a>
        
        <a href="help.php" class="footer-btn help <?= $activePage == 'help' ? 'active' : '' ?>">
            <i class="bi bi-question-circle"></i>
            <span>Bantuan & FAQ</span>
        </a>
    </div>
</div>

<!-- MAIN CONTENT AREA -->
<div class="content" id="mainContent">
    <!-- Konten halaman akan dimuat di sini -->
</div>

<script>
// ===== SIDEBAR INTERACTIVITY =====
document.addEventListener('DOMContentLoaded', function() {
    // Set active menu item based on current page
    function setActiveMenuItem() {
        const currentPath = window.location.pathname;
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            item.classList.remove('active');
            const href = item.getAttribute('href');
            
            // Check if current path contains the href
            if (href && currentPath.includes(href)) {
                item.classList.add('active');
            }
            
            // Special handling for chat
            if (currentPath.includes('chat/') && href && href.includes('chat/chat.php')) {
                item.classList.add('active');
            }
        });
    }
    
    // Initialize
    setActiveMenuItem();
    
    // Update badge counts dynamically
    function updateBadgeCounts() {
        // Simulate fetching data (in real app, use AJAX)
        const badges = {
            'dashboard-badge': Math.floor(Math.random() * 5),
            'produk-badge': Math.floor(Math.random() * 20),
            'chat-badge': Math.floor(Math.random() * 10),
            'pesanan-badge': Math.floor(Math.random() * 15)
        };
        
        Object.keys(badges).forEach(badgeId => {
            const badge = document.getElementById(badgeId);
            if (badge) {
                const count = badges[badgeId];
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
                
                // Add animation for new notifications
                if (count > 0 && parseInt(badge.dataset.lastCount || 0) < count) {
                    badge.style.animation = 'pulse 0.5s 3';
                    setTimeout(() => {
                        badge.style.animation = '';
                    }, 1500);
                }
                
                badge.dataset.lastCount = count;
            }
        });
    }
    
    // Initial update
    updateBadgeCounts();
    
    // Update badges every 30 seconds
    setInterval(updateBadgeCounts, 30000);
    
    // Mobile menu toggle
    const sidebar = document.querySelector('.sidebar');
    const content = document.getElementById('mainContent');
    
    // Handle window resize
    function handleResize() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('mobile-collapsed');
        } else {
            sidebar.classList.remove('mobile-collapsed');
        }
    }
    
    // Initialize and add event listener
    handleResize();
    window.addEventListener('resize', handleResize);
    
    // Click outside to close sidebar on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && 
            !sidebar.contains(event.target) && 
            !sidebar.classList.contains('mobile-collapsed')) {
            sidebar.classList.add('mobile-collapsed');
        }
    });
    
    // Add smooth hover effects
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach((item, index) => {
        item.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(10px)';
            }
        });
        
        item.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateX(0)';
            }
        });
        
        // Set animation delay
        item.style.setProperty('--item-index', index + 1);
    });
    
    // Profile image error handling
    const profileImg = document.querySelector('.sidebar-profile img');
    if (profileImg) {
        profileImg.onerror = function() {
            this.src = '../../assets/img/user.png';
        };
    }
    
    // Add loading animation to logout button
    const logoutBtn = document.querySelector('.footer-btn.logout');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            if (!confirm('Anda akan keluar dari sistem. Lanjutkan?')) {
                e.preventDefault();
                return;
            }
            
            // Add loading state
            this.innerHTML = '<i class="bi bi-arrow-clockwise"></i><span>Memproses...</span>';
            this.classList.add('loading');
            
            // Small delay before actual logout
            setTimeout(() => {
                // Redirect will happen via href
            }, 500);
        });
    }
});

// ===== PERFORMANCE OPTIMIZATION =====
// Debounce resize handler
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        const event = new CustomEvent('optimizedResize');
        window.dispatchEvent(event);
    }, 250);
});

// ===== ACCESSIBILITY IMPROVEMENTS =====
document.addEventListener('keydown', function(e) {
    // Close sidebar with Escape key
    if (e.key === 'Escape' && window.innerWidth <= 768) {
        document.querySelector('.sidebar').classList.add('mobile-collapsed');
    }
    
    // Navigate menu with arrow keys
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const menuItems = document.querySelectorAll('.menu-item');
        const currentIndex = Array.from(menuItems).findIndex(item => 
            document.activeElement === item
        );
        
        if (currentIndex !== -1) {
            e.preventDefault();
            let nextIndex;
            
            if (e.key === 'ArrowDown') {
                nextIndex = (currentIndex + 1) % menuItems.length;
            } else {
                nextIndex = (currentIndex - 1 + menuItems.length) % menuItems.length;
            }
            
            menuItems[nextIndex].focus();
        }
    }
});
</script>

</body>
</html>