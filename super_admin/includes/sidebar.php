<?php
// Start session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php"; // Include database untuk data user

// Ambil data user dari session
$idUser = $_SESSION['user']['id'] ?? 0;
$namaUser = $_SESSION['user']['nama'] ?? 'User';
$roleUser = $_SESSION['user']['role'] ?? 'super_admin';

// Ambil data lengkap user dari database untuk profil
$userData = [];
if ($idUser > 0) {
    $query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$idUser'");
    if ($query && mysqli_num_rows($query) > 0) {
        $userData = mysqli_fetch_assoc($query);
    }
}
?>
<!-- Sidebar Super Admin - SIMPLE VERSION -->
<div class="sidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <h2>BOOKIE</h2>
  </div>
  
  <!-- User Info -->
  <div class="user-info">
    <a href="profile.php" class="user-info-link">
      <div class="user-name"><?= htmlspecialchars($namaUser) ?></div>
      <div class="user-role"><?= ucfirst(str_replace('_', ' ', $roleUser)) ?></div>
    </a>
  </div>
  
  <!-- Navigation Menu -->
  <div class="sidebar-nav">
    <div class="menu-section">
      <div class="menu-section-title">Dashboard</div>
      <ul class="menu-items">
        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // Menu items untuk super admin
        $menuItems = [
            [
                'url' => 'dashboard.php',
                'label' => 'Dashboard',
                'active' => ($currentPage == 'dashboard.php') ? 'active' : ''
            ],
            [
                'url' => 'penjual.php',
                'label' => 'Penjual',
                'active' => ($currentPage == 'penjual.php') ? 'active' : ''
            ],
            [
                'url' => 'pembeli.php',
                'label' => 'Pembeli',
                'active' => ($currentPage == 'pembeli.php') ? 'active' : ''
            ],
            [
                'url' => 'kategori.php',
                'label' => 'Kategori',
                'active' => ($currentPage == 'kategori.php') ? 'active' : ''
            ],
            [
                'url' => 'profile.php',
                'label' => 'Profil',
                'active' => ($currentPage == 'profile.php') ? 'active' : ''
            ]
        ];
        
        foreach ($menuItems as $item):
        ?>
        <li>
          <a class="<?= $item['active'] ?>" href="<?= $item['url'] ?>">
            <?= $item['label'] ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  
  <!-- Logout Button -->
  <div class="logout-section">
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
  </div>
  <!-- Help Section -->
<div class="help-section">
  <a href="help.php" class="help-btn">
    Help & FAQ
  </a>
</div>

</div>