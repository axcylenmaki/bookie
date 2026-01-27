<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';

// Ambil daftar pembeli yang pernah chat
$qChatPartners = mysqli_query($conn, "
  SELECT DISTINCT 
    u.id as pembeli_id,
    u.nama as pembeli_nama,
    u.foto as pembeli_foto,
    (SELECT COUNT(*) FROM chat 
     WHERE pengirim_id = u.id 
     AND penerima_id = '$idPenjual' 
     AND status = 'terkirim') as unread_count,
    (SELECT pesan FROM chat 
     WHERE (pengirim_id = u.id AND penerima_id = '$idPenjual') 
        OR (pengirim_id = '$idPenjual' AND penerima_id = u.id) 
     ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM chat 
     WHERE (pengirim_id = u.id AND penerima_id = '$idPenjual') 
        OR (pengirim_id = '$idPenjual' AND penerima_id = u.id) 
     ORDER BY created_at DESC LIMIT 1) as last_message_time
  FROM chat c
  JOIN users u ON (c.pengirim_id = u.id OR c.penerima_id = u.id)
  WHERE (c.pengirim_id = '$idPenjual' OR c.penerima_id = '$idPenjual')
    AND u.id != '$idPenjual'
    AND u.role = 'pembeli'
  GROUP BY u.id
  ORDER BY last_message_time DESC
");

// Ambil chat dengan pembeli tertentu
$pembeli_id = $_GET['pembeli_id'] ?? 0;
$pembeliData = null;
$chatMessages = [];

if ($pembeli_id) {
  // Ambil data pembeli
  $qPembeli = mysqli_query($conn, "SELECT * FROM users WHERE id='$pembeli_id' AND role='pembeli'");
  $pembeliData = mysqli_fetch_assoc($qPembeli);
  
  // Ambil pesan
  $qChat = mysqli_query($conn, "
    SELECT c.*, u.nama as pengirim_nama, u.foto as pengirim_foto
    FROM chat c
    JOIN users u ON c.pengirim_id = u.id
    WHERE (c.pengirim_id = '$idPenjual' AND c.penerima_id = '$pembeli_id')
       OR (c.pengirim_id = '$pembeli_id' AND c.penerima_id = '$idPenjual')
    ORDER BY c.created_at ASC
  ");
  
  while ($msg = mysqli_fetch_assoc($qChat)) {
    $chatMessages[] = $msg;
  }
  
  // Update status jadi dibaca
  mysqli_query($conn, "
    UPDATE chat SET status='dibaca' 
    WHERE penerima_id='$idPenjual' 
    AND pengirim_id='$pembeli_id'
    AND status='terkirim'
  ");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Chat dengan Pembeli - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">

<style>
.chat-container {
  height: calc(100vh - 150px);
  border: 1px solid #dee2e6;
  border-radius: 10px;
  overflow: hidden;
}
.chat-sidebar {
  width: 300px;
  border-right: 1px solid #dee2e6;
  background-color: #f8f9fa;
  overflow-y: auto;
}
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.chat-header {
  padding: 15px;
  border-bottom: 1px solid #dee2e6;
  background-color: white;
}
.chat-messages {
  flex: 1;
  padding: 15px;
  overflow-y: auto;
  background-color: #f5f7fb;
}
.chat-input {
  padding: 15px;
  border-top: 1px solid #dee2e6;
  background-color: white;
}
.message {
  max-width: 70%;
  margin-bottom: 15px;
  display: flex;
}
.message.sent {
  margin-left: auto;
  flex-direction: row-reverse;
}
.message.received {
  margin-right: auto;
}
.message-content {
  padding: 10px 15px;
  border-radius: 15px;
  position: relative;
  word-wrap: break-word;
}
.message.sent .message-content {
  background-color: #007bff;
  color: white;
  border-bottom-right-radius: 5px;
}
.message.received .message-content {
  background-color: white;
  color: #333;
  border: 1px solid #dee2e6;
  border-bottom-left-radius: 5px;
}
.message-time {
  font-size: 0.75rem;
  color: #6c757d;
  margin-top: 5px;
  text-align: right;
}
.message.sent .message-time {
  color: rgba(255,255,255,0.8);
}
.chat-user {
  padding: 10px;
  border-bottom: 1px solid #e9ecef;
  cursor: pointer;
  transition: background-color 0.2s;
}
.chat-user:hover, .chat-user.active {
  background-color: #e9ecef;
}
.chat-user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}
.unread-badge {
  background-color: #dc3545;
  color: white;
  font-size: 0.7rem;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.typing-indicator {
  display: inline-flex;
  align-items: center;
  color: #6c757d;
  font-style: italic;
}
.typing-dots {
  display: inline-flex;
  margin-left: 5px;
}
.typing-dots span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #6c757d;
  margin: 0 2px;
  animation: typing 1.4s infinite;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-5px); }
}
.empty-chat {
  text-align: center;
  padding: 50px 20px;
  color: #6c757d;
}
.empty-chat i {
  font-size: 4rem;
  opacity: 0.5;
  margin-bottom: 20px;
}
.online-status {
  width: 10px;
  height: 10px;
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
</style>
</head>

<body class="bg-light">
<div class="container-fluid">
<div class="row">
    
<!-- INCLUDE SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- CONTENT -->
<div class="col-10 p-4">
<div class="d-flex justify-content-between align-items-center mb-4">
<div>
  <h3 class="mb-1">Chat dengan Pembeli</h3>
  <p class="text-muted mb-0">Kelola komunikasi dengan pelanggan Anda</p>
</div>
<div>
  <span class="badge bg-dark" id="onlineCount">0 online</span>
</div>
</div>

<div class="chat-container d-flex">
  <!-- SIDEBAR: DAFTAR PEMBELI -->
  <div class="chat-sidebar">
    <div class="p-3 border-bottom">
      <h6 class="mb-0"><i class="bi bi-people me-2"></i> Daftar Pembeli</h6>
    </div>
    <div class="chat-users">
      <?php if(mysqli_num_rows($qChatPartners) > 0): ?>
        <?php while($partner = mysqli_fetch_assoc($qChatPartners)): 
          $isActive = $pembeli_id == $partner['pembeli_id'];
        ?>
        <div class="chat-user <?= $isActive ? 'active' : '' ?>" 
             data-pembeli-id="<?= $partner['pembeli_id'] ?>">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <img src="../uploads/<?= $partner['pembeli_foto'] ?: 'user.png' ?>" 
                   class="chat-user-avatar me-3"
                   onerror="this.src='../assets/img/user.png'">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($partner['pembeli_nama']) ?></div>
                <small class="text-muted text-truncate d-block" style="max-width: 150px;">
                  <?= $partner['last_message'] ? mb_strimwidth($partner['last_message'], 0, 30, '...') : 'Belum ada pesan' ?>
                </small>
              </div>
            </div>
            <?php if($partner['unread_count'] > 0): ?>
            <span class="unread-badge"><?= $partner['unread_count'] ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="text-center p-4">
          <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
          <p class="text-muted mt-2">Belum ada percakapan</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- AREA CHAT UTAMA -->
  <div class="chat-main">
    <?php if($pembeliData): ?>
    <!-- HEADER CHAT -->
    <div class="chat-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="../uploads/<?= $pembeliData['foto'] ?: 'user.png' ?>" 
             class="chat-user-avatar me-3"
             onerror="this.src='../assets/img/user.png'">
        <div>
          <h6 class="mb-0"><?= htmlspecialchars($pembeliData['nama']) ?></h6>
          <small class="text-muted">
            <span class="online-status <?= $pembeliData['status'] == 'online' ? 'online' : 'offline' ?>"></span>
            <?= $pembeliData['status'] == 'online' ? 'Online' : 'Offline' ?>
          </small>
        </div>
      </div>
      <div>
        <a href="transaksi.php" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-receipt me-1"></i> Lihat Transaksi
        </a>
      </div>
    </div>

    <!-- MESSAGES AREA -->
    <div class="chat-messages" id="chatMessages">
      <?php if(count($chatMessages) > 0): ?>
        <?php foreach($chatMessages as $msg): 
          $isSent = $msg['pengirim_id'] == $idPenjual;
        ?>
        <div class="message <?= $isSent ? 'sent' : 'received' ?>">
          <div class="message-content">
            <?= nl2br(htmlspecialchars($msg['pesan'])) ?>
            <div class="message-time">
              <?= date('H:i', strtotime($msg['created_at'])) ?>
              <?php if($isSent): ?>
                <i class="bi bi-check<?= $msg['status'] == 'dibaca' ? '2' : '' ?>-all ms-1"></i>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-chat">
          <i class="bi bi-chat-quote"></i>
          <h5 class="text-muted mt-3">Belum ada pesan</h5>
          <p>Mulai percakapan dengan <?= htmlspecialchars($pembeliData['nama']) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <!-- TYPING INDICATOR (akan muncul via JavaScript) -->
    <div id="typingIndicator" class="px-3 pb-2 d-none">
      <div class="typing-indicator">
        <?= htmlspecialchars($pembeliData['nama']) ?> sedang mengetik
        <div class="typing-dots">
          <span></span><span></span><span></span>
        </div>
      </div>
    </div>

    <!-- INPUT AREA -->
    <div class="chat-input">
      <form id="chatForm">
        <input type="hidden" name="penerima_id" value="<?= $pembeli_id ?>">
        <div class="input-group">
          <textarea name="pesan" class="form-control" 
                    id="messageInput" 
                    rows="1" 
                    placeholder="Ketik pesan..." 
                    style="resize: none;"
                    required></textarea>
          <button type="submit" class="btn btn-primary" id="sendButton">
            <i class="bi bi-send"></i>
          </button>
        </div>
      </form>
    </div>
    <?php else: ?>
    <!-- DEFAULT STATE (belum pilih pembeli) -->
    <div class="chat-main d-flex align-items-center justify-content-center">
      <div class="text-center">
        <i class="bi bi-chat-left-text" style="font-size: 4rem; color: #dee2e6;"></i>
        <h5 class="text-muted mt-3">Pilih pembeli untuk memulai chat</h5>
        <p>Klik salah satu pembeli di sidebar untuk melihat percakapan</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variabel global
let currentPembeliId = <?= $pembeli_id ?>;
let lastMessageId = 0;
let isTyping = false;
let typingTimeout = null;

// Inisialisasi
if (currentPembeliId > 0) {
  // Ambil ID pesan terakhir
  const messages = document.querySelectorAll('.message');
  if (messages.length > 0) {
    lastMessageId = messages[messages.length - 1].dataset.messageId || 0;
  }
  
  // Mulai polling untuk pesan baru
  startPolling();
}

// Fungsi untuk polling pesan baru
function startPolling() {
  setInterval(fetchNewMessages, 3000); // Poll setiap 3 detik
  
  // Juga poll untuk status online
  setInterval(updateOnlineStatus, 10000); // Poll setiap 10 detik
}

// Ambil pesan baru
function fetchNewMessages() {
  if (!currentPembeliId) return;
  
  fetch(`chat_get.php?pembeli_id=${currentPembeliId}&last_id=${lastMessageId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.messages.length > 0) {
        // Tambahkan pesan baru
        data.messages.forEach(msg => {
          addMessageToChat(msg);
          lastMessageId = Math.max(lastMessageId, msg.id);
        });
        
        // Scroll ke bawah
        scrollToBottom();
        
        // Update badge unread
        updateUnreadBadges();
      }
      
      // Update online status
      if (data.online_status !== undefined) {
        updateOnlineIndicator(data.online_status);
      }
    })
    .catch(error => console.error('Error fetching messages:', error));
}

// Tambahkan pesan ke chat
function addMessageToChat(msg) {
  const isSent = msg.pengirim_id == <?= $idPenjual ?>;
  const messageTime = new Date(msg.created_at).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit'
  });
  
  const messageHtml = `
    <div class="message ${isSent ? 'sent' : 'received'}">
      <div class="message-content">
        ${msg.pesan.replace(/\n/g, '<br>')}
        <div class="message-time">
          ${messageTime}
          ${isSent ? `<i class="bi bi-check${msg.status == 'dibaca' ? '2' : ''}-all ms-1"></i>` : ''}
        </div>
      </div>
    </div>
  `;
  
  const chatMessages = document.getElementById('chatMessages');
  const emptyState = chatMessages.querySelector('.empty-chat');
  if (emptyState) {
    emptyState.remove();
  }
  
  chatMessages.insertAdjacentHTML('beforeend', messageHtml);
}

// Kirim pesan
document.getElementById('chatForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  const messageInput = document.getElementById('messageInput');
  
  if (!formData.get('pesan').trim()) return;
  
  // Disable input sementara
  messageInput.disabled = true;
  
  fetch('chat_send.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Reset form
      messageInput.value = '';
      messageInput.style.height = 'auto';
      
      // Tambahkan pesan ke chat
      addMessageToChat(data.message);
      scrollToBottom();
    } else {
      alert('Gagal mengirim pesan: ' + (data.error || 'Unknown error'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Gagal mengirim pesan');
  })
  .finally(() => {
    messageInput.disabled = false;
    messageInput.focus();
  });
});

// Auto-resize textarea
document.getElementById('messageInput')?.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = (this.scrollHeight) + 'px';
  
  // Kirim typing indicator
  sendTypingIndicator();
});

// Kirim typing indicator
function sendTypingIndicator() {
  if (!currentPembeliId || isTyping) return;
  
  isTyping = true;
  
  fetch('chat_typing.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      pembeli_id: currentPembeliId,
      typing: true
    })
  });
  
  // Reset setelah 3 detik
  clearTimeout(typingTimeout);
  typingTimeout = setTimeout(() => {
    isTyping = false;
    fetch('chat_typing.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        pembeli_id: currentPembeliId,
        typing: false
      })
    });
  }, 3000);
}

// Update online status
function updateOnlineStatus() {
  if (!currentPembeliId) return;
  
  fetch(`chat_status.php?pembeli_id=${currentPembeliId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        updateOnlineIndicator(data.online);
      }
    });
}

function updateOnlineIndicator(isOnline) {
  const statusElement = document.querySelector('.online-status');
  const textElement = document.querySelector('.online-status').nextSibling;
  
  if (statusElement && textElement) {
    if (isOnline) {
      statusElement.className = 'online-status online';
      textElement.textContent = ' Online';
    } else {
      statusElement.className = 'online-status offline';
      textElement.textContent = ' Offline';
    }
  }
}

// Scroll ke bawah chat
function scrollToBottom() {
  const chatMessages = document.getElementById('chatMessages');
  if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }
}

// Update badge unread
function updateUnreadBadges() {
  fetch('chat_unread.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update total unread di navbar (jika ada)
        const chatBadge = document.querySelector('#chat-badge');
        if (chatBadge && data.total_unread > 0) {
          chatBadge.textContent = data.total_unread;
          chatBadge.classList.remove('d-none');
        } else if (chatBadge) {
          chatBadge.textContent = '0';
        }
      }
    });
}

// Event listener untuk klik pembeli di sidebar
document.querySelectorAll('.chat-user').forEach(user => {
  user.addEventListener('click', function() {
    const pembeliId = this.dataset.pembeliId;
    window.location.href = `chat.php?pembeli_id=${pembeliId}`;
  });
});

// Shortcut untuk submit dengan Ctrl+Enter
document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.key === 'Enter') {
    document.getElementById('chatForm').dispatchEvent(new Event('submit'));
  }
});

// Inisialisasi saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
  scrollToBottom();
  updateUnreadBadges();
  
  // Update online count
  fetch('chat_online_count.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('onlineCount').textContent = `${data.count} online`;
      }
    });
});
</script>
</body>
</html>