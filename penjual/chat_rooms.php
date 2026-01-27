<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';

// Ambil daftar ruang chat dengan statistik
$qChatRooms = mysqli_query($conn, "
  SELECT 
    u.id as pembeli_id,
    u.nama as pembeli_nama,
    u.foto as pembeli_foto,
    u.email as pembeli_email,
    u.no_hp as pembeli_no_hp,
    u.status as pembeli_status,
    u.created_at as pembeli_join_date,
    
    -- Statistik chat
    COUNT(c.id) as total_messages,
    SUM(CASE WHEN c.pengirim_id = u.id AND c.status = 'terkirim' THEN 1 ELSE 0 END) as unread_count,
    
    -- Pesan terakhir
    MAX(c.created_at) as last_message_time,
    (SELECT pesan FROM chat 
     WHERE (pengirim_id = u.id AND penerima_id = '$idPenjual') 
        OR (pengirim_id = '$idPenjual' AND penerima_id = u.id) 
     ORDER BY created_at DESC LIMIT 1) as last_message,
    
    -- Statistik transaksi dengan pembeli ini
    (SELECT COUNT(*) FROM transaksi 
     WHERE pembeli_id = u.id AND penjual_id = '$idPenjual') as total_transaksi,
     
    (SELECT SUM(total) FROM transaksi 
     WHERE pembeli_id = u.id AND penjual_id = '$idPenjual' 
     AND status = 'selesai') as total_omzet
     
  FROM users u
  LEFT JOIN chat c ON (
    (c.pengirim_id = u.id AND c.penerima_id = '$idPenjual')
    OR (c.pengirim_id = '$idPenjual' AND c.penerima_id = u.id)
  )
  WHERE u.role = 'pembeli'
    AND u.id IN (
      SELECT DISTINCT 
        CASE 
          WHEN pengirim_id = '$idPenjual' THEN penerima_id
          WHEN penerima_id = '$idPenjual' THEN pengirim_id
        END
      FROM chat
      WHERE pengirim_id = '$idPenjual' OR penerima_id = '$idPenjual'
    )
  GROUP BY u.id
  ORDER BY last_message_time DESC, u.nama ASC
");

// Hitung total statistik
$totalChatRooms = mysqli_num_rows($qChatRooms);

// Hitung total unread messages
$qTotalUnread = mysqli_query($conn, "
  SELECT COUNT(*) as total_unread
  FROM chat
  WHERE penerima_id = '$idPenjual'
  AND status = 'terkirim'
");
$totalUnreadData = mysqli_fetch_assoc($qTotalUnread);
$totalUnread = $totalUnreadData['total_unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Percakapan - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">

<style>
.chat-room-card {
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  margin-bottom: 15px;
  transition: transform 0.2s, box-shadow 0.2s;
  background: white;
}
.chat-room-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}
.chat-room-card.active {
  border-left: 4px solid #3498db;
}
.chat-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #f8f9fa;
}
.last-message {
  font-size: 0.9rem;
  color: #6c757d;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.last-message-time {
  font-size: 0.8rem;
  color: #adb5bd;
}
.unread-badge {
  background-color: #dc3545;
  color: white;
  font-size: 0.75rem;
  min-width: 22px;
  height: 22px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 6px;
}
.online-status {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 5px;
}
.online-status.online {
  background-color: #28a745;
  box-shadow: 0 0 8px #28a745;
}
.online-status.offline {
  background-color: #6c757d;
}
.stats-badge {
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 10px;
  background-color: #e9ecef;
  color: #495057;
}
.empty-state {
  padding: 80px 20px;
  text-align: center;
  color: #6c757d;
}
.empty-state i {
  font-size: 4rem;
  opacity: 0.5;
  margin-bottom: 20px;
}
.search-box {
  position: relative;
}
.search-box .form-control {
  padding-left: 40px;
  border-radius: 20px;
}
.search-box .bi-search {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}
.filter-buttons .btn {
  border-radius: 20px;
  padding: 6px 15px;
  font-size: 0.9rem;
}
.chat-room-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 15px;
  margin-bottom: 20px;
  border-bottom: 2px solid #f1f1f1;
}
.new-chat-btn {
  background: linear-gradient(45deg, #3498db, #2980b9);
  color: white;
  border: none;
  border-radius: 8px;
  padding: 8px 20px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: transform 0.2s;
}
.new-chat-btn:hover {
  transform: translateY(-2px);
  color: white;
  box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}
.pembeli-info-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  margin-top: 10px;
  border-left: 4px solid #3498db;
}
.pembeli-info-item {
  display: flex;
  justify-content: space-between;
  padding: 5px 0;
  font-size: 0.9rem;
}
.pembeli-info-label {
  color: #6c757d;
}
.pembeli-info-value {
  font-weight: 500;
  color: #495057;
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
<div class="chat-room-header">
<div>
  <h3 class="mb-1">Daftar Percakapan</h3>
  <p class="text-muted mb-0">Kelola semua chat dengan pelanggan Anda</p>
</div>
<div class="d-flex align-items-center gap-3">
  <span class="badge bg-dark">
    <i class="bi bi-chat-dots me-1"></i> 
    <?= $totalChatRooms ?> percakapan
    <?php if($totalUnread > 0): ?>
      <span class="badge bg-danger ms-1"><?= $totalUnread ?> baru</span>
    <?php endif; ?>
  </span>
  <a href="chat.php" class="new-chat-btn">
    <i class="bi bi-plus-circle"></i> Chat Baru
  </a>
</div>
</div>

<!-- FILTER DAN PENCARIAN -->
<div class="card shadow-sm mb-4">
<div class="card-body">
<div class="row g-3">
<div class="col-md-8">
  <div class="search-box">
    <input type="text" id="searchInput" class="form-control" 
           placeholder="Cari nama pembeli atau pesan...">
    <i class="bi bi-search"></i>
  </div>
</div>
<div class="col-md-4">
  <div class="filter-buttons d-flex gap-2">
    <a href="?filter=all" class="btn btn-outline-primary active">Semua</a>
    <a href="?filter=unread" class="btn btn-outline-danger">
      Belum Dibaca
      <?php if($totalUnread > 0): ?>
      <span class="badge bg-danger ms-1"><?= $totalUnread ?></span>
      <?php endif; ?>
    </a>
    <a href="?filter=online" class="btn btn-outline-success">Online</a>
  </div>
</div>
</div>
</div>
</div>

<!-- DAFTAR CHAT ROOMS -->
<div class="row">
<div class="col-12">
  <?php if($totalChatRooms > 0): ?>
    <?php while($room = mysqli_fetch_assoc($qChatRooms)): 
      $lastMessageTime = $room['last_message_time'] ? strtotime($room['last_message_time']) : 0;
      $timeAgo = '';
      
      if ($lastMessageTime) {
        $diff = time() - $lastMessageTime;
        if ($diff < 60) {
          $timeAgo = 'Baru saja';
        } elseif ($diff < 3600) {
          $timeAgo = floor($diff / 60) . ' menit lalu';
        } elseif ($diff < 86400) {
          $timeAgo = floor($diff / 3600) . ' jam lalu';
        } elseif ($diff < 604800) {
          $timeAgo = floor($diff / 86400) . ' hari lalu';
        } else {
          $timeAgo = date('d/m/Y', $lastMessageTime);
        }
      }
      
      $isOnline = $room['pembeli_status'] == 'online';
      $hasUnread = $room['unread_count'] > 0;
    ?>
    <div class="chat-room-card p-3 <?= $hasUnread ? 'active' : '' ?>">
      <div class="row align-items-center">
        <div class="col-auto">
          <img src="../uploads/<?= $room['pembeli_foto'] ?: 'user.png' ?>" 
               class="chat-avatar"
               onerror="this.src='../assets/img/user.png'">
        </div>
        
        <div class="col">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-1">
                <span class="online-status <?= $isOnline ? 'online' : 'offline' ?>"></span>
                <?= htmlspecialchars($room['pembeli_nama']) ?>
                <?php if($room['total_transaksi'] > 0): ?>
                <span class="badge bg-success ms-2">
                  <i class="bi bi-star"></i> Pelanggan
                </span>
                <?php endif; ?>
              </h6>
              <p class="last-message mb-1">
                <?= $room['last_message'] 
                  ? htmlspecialchars(mb_strimwidth($room['last_message'], 0, 80, '...')) 
                  : 'Belum ada pesan' ?>
              </p>
              <div class="d-flex gap-3">
                <small class="last-message-time">
                  <i class="bi bi-clock me-1"></i> 
                  <?= $lastMessageTime ? $timeAgo : 'Belum ada chat' ?>
                </small>
                <?php if($room['total_messages'] > 0): ?>
                <small class="text-muted">
                  <i class="bi bi-chat-text me-1"></i> 
                  <?= $room['total_messages'] ?> pesan
                </small>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="text-end">
              <?php if($hasUnread): ?>
              <span class="unread-badge mb-2"><?= $room['unread_count'] ?></span>
              <?php endif; ?>
              
              <div class="d-flex gap-2 justify-content-end mt-2">
                <?php if($room['total_transaksi'] > 0): ?>
                <span class="stats-badge" title="Total transaksi">
                  <i class="bi bi-cart3 me-1"></i> <?= $room['total_transaksi'] ?> transaksi
                </span>
                <?php endif; ?>
                
                <?php if($room['total_omzet'] > 0): ?>
                <span class="stats-badge" title="Total omzet">
                  <i class="bi bi-cash-stack me-1"></i> Rp <?= number_format($room['total_omzet']) ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          
          <!-- INFO PEMBELI (bisa di-expand) -->
          <div class="pembeli-info-card mt-3">
            <div class="row">
              <div class="col-md-6">
                <div class="pembeli-info-item">
                  <span class="pembeli-info-label">Email:</span>
                  <span class="pembeli-info-value"><?= htmlspecialchars($room['pembeli_email']) ?></span>
                </div>
                <div class="pembeli-info-item">
                  <span class="pembeli-info-label">No. HP:</span>
                  <span class="pembeli-info-value"><?= $room['pembeli_no_hp'] ?: '-' ?></span>
                </div>
              </div>
              <div class="col-md-6">
                <div class="pembeli-info-item">
                  <span class="pembeli-info-label">Bergabung:</span>
                  <span class="pembeli-info-value"><?= date('d M Y', strtotime($room['pembeli_join_date'])) ?></span>
                </div>
                <div class="pembeli-info-item">
                  <span class="pembeli-info-label">Status:</span>
                  <span class="pembeli-info-value">
                    <span class="online-status <?= $isOnline ? 'online' : 'offline' ?>"></span>
                    <?= $isOnline ? 'Online' : 'Offline' ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- ACTION BUTTONS -->
          <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="chat.php?pembeli_id=<?= $room['pembeli_id'] ?>" 
               class="btn btn-sm btn-primary">
              <i class="bi bi-chat-left-text me-1"></i> Buka Chat
            </a>
            <a href="transaksi.php?pembeli_id=<?= $room['pembeli_id'] ?>" 
               class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-receipt me-1"></i> Lihat Transaksi
            </a>
            <a href="profile.php?user_id=<?= $room['pembeli_id'] ?>" 
               class="btn btn-sm btn-outline-info">
              <i class="bi bi-person me-1"></i> Profil
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-chat-left-dots"></i>
      <h5 class="text-muted mt-3">Belum ada percakapan</h5>
      <p>Mulai chat dengan pembeli pertama Anda</p>
      <a href="chat.php" class="btn btn-primary mt-3">
        <i class="bi bi-plus-circle me-1"></i> Mulai Chat Baru
      </a>
    </div>
  <?php endif; ?>
</div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pencarian real-time
document.getElementById('searchInput').addEventListener('input', function(e) {
  const searchTerm = this.value.toLowerCase();
  const chatRooms = document.querySelectorAll('.chat-room-card');
  
  chatRooms.forEach(room => {
    const roomText = room.textContent.toLowerCase();
    if (roomText.includes(searchTerm)) {
      room.style.display = 'block';
    } else {
      room.style.display = 'none';
    }
  });
});

// Filter berdasarkan status
document.querySelectorAll('.filter-buttons a').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    
    // Update active button
    document.querySelectorAll('.filter-buttons a').forEach(b => {
      b.classList.remove('active');
      b.classList.add('btn-outline-primary', 'btn-outline-danger', 'btn-outline-success');
    });
    this.classList.remove('btn-outline-primary', 'btn-outline-danger', 'btn-outline-success');
    this.classList.add('active', 'btn-primary');
    
    const filter = this.getAttribute('href').split('=')[1];
    filterChatRooms(filter);
  });
});

function filterChatRooms(filter) {
  const chatRooms = document.querySelectorAll('.chat-room-card');
  
  chatRooms.forEach(room => {
    const isOnline = room.querySelector('.online-status.online');
    const hasUnread = room.querySelector('.unread-badge');
    
    switch(filter) {
      case 'unread':
        room.style.display = hasUnread ? 'block' : 'none';
        break;
      case 'online':
        room.style.display = isOnline ? 'block' : 'none';
        break;
      default:
        room.style.display = 'block';
    }
  });
}

// Auto-refresh untuk update status online dan unread
setInterval(() => {
  fetch('chat_unread.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update total unread di header
        const totalUnreadBadge = document.querySelector('.badge.bg-dark .badge.bg-danger');
        if (data.total_unread > 0) {
          if (totalUnreadBadge) {
            totalUnreadBadge.textContent = data.total_unread;
          } else {
            const badgeContainer = document.querySelector('.badge.bg-dark');
            if (badgeContainer) {
              badgeContainer.innerHTML += ` <span class="badge bg-danger">${data.total_unread} baru</span>`;
            }
          }
        } else if (totalUnreadBadge) {
          totalUnreadBadge.remove();
        }
      }
    });
}, 10000); // Update setiap 10 detik

// Expand/collapse info pembeli
document.querySelectorAll('.pembeli-info-card').forEach(card => {
  card.style.display = 'none'; // Sembunyikan secara default
  
  const parentCard = card.closest('.chat-room-card');
  const toggleBtn = document.createElement('button');
  toggleBtn.className = 'btn btn-sm btn-link p-0 mt-2';
  toggleBtn.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Lihat info pembeli';
  toggleBtn.addEventListener('click', function() {
    if (card.style.display === 'none') {
      card.style.display = 'block';
      this.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Sembunyikan info';
    } else {
      card.style.display = 'none';
      this.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Lihat info pembeli';
    }
  });
  
  const actionButtons = parentCard.querySelector('.d-flex.justify-content-end');
  if (actionButtons) {
    actionButtons.parentNode.insertBefore(toggleBtn, actionButtons);
  }
});

// Highlight chat room dengan pesan baru
function highlightNewMessages() {
  const unreadBadges = document.querySelectorAll('.unread-badge');
  unreadBadges.forEach(badge => {
    const chatRoom = badge.closest('.chat-room-card');
    if (chatRoom) {
      chatRoom.classList.add('active');
      // Tambahkan animasi
      chatRoom.style.animation = 'pulse 2s infinite';
    }
  });
}

// CSS untuk animasi pulse
const style = document.createElement('style');
style.textContent = `
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
  100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
}
`;
document.head.appendChild(style);

// Inisialisasi saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
  highlightNewMessages();
});
</script>
</body>
</html>