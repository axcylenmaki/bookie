<?php
class ChatSystem {
    private $conn;
    private $pembeli_id;
    private $pembeli_nama;
    
    public function __construct($connection, $pembeli_id, $pembeli_nama) {
        $this->conn = $connection;
        $this->pembeli_id = $pembeli_id;
        $this->pembeli_nama = $pembeli_nama;
    }
    
    /* =====================
       GET PENJUAL DATA
    ===================== */
    public function getPenjual($penjual_id) {
        if(!$penjual_id) return null;
        
        $query = "SELECT id, nama, foto, status FROM users 
                  WHERE id='$penjual_id' AND role='penjual'";
        $result = mysqli_query($this->conn, $query);
        return mysqli_fetch_assoc($result) ?: null;
    }
    
    /* =====================
       GET PENJUAL LIST - FIXED
    ===================== */
    public function getPenjualList() {
        // Ambil semua penjual yang pernah chat dengan pembeli ini
        $query = "SELECT DISTINCT 
                    u.id, 
                    u.nama, 
                    u.foto, 
                    u.status,
                    (
                        SELECT c.pesan 
                        FROM chat c 
                        WHERE c.id_room IN (
                            SELECT cr.id 
                            FROM chat_rooms cr 
                            WHERE cr.id_pembeli = '{$this->pembeli_id}' 
                              AND cr.id_penjual = u.id
                        )
                        ORDER BY c.created_at DESC 
                        LIMIT 1
                    ) as last_message,
                    (
                        SELECT c.created_at 
                        FROM chat c 
                        WHERE c.id_room IN (
                            SELECT cr.id 
                            FROM chat_rooms cr 
                            WHERE cr.id_pembeli = '{$this->pembeli_id}' 
                              AND cr.id_penjual = u.id
                        )
                        ORDER BY c.created_at DESC 
                        LIMIT 1
                    ) as last_message_at,
                    (
                        SELECT COUNT(*) 
                        FROM chat c 
                        WHERE c.id_room IN (
                            SELECT cr.id 
                            FROM chat_rooms cr 
                            WHERE cr.id_pembeli = '{$this->pembeli_id}' 
                              AND cr.id_penjual = u.id
                        )
                          AND c.id_pengirim = u.id 
                          AND c.dibaca = 0
                    ) as unread_count
                  FROM chat_rooms cr
                  JOIN users u ON cr.id_penjual = u.id
                  WHERE cr.id_pembeli = '{$this->pembeli_id}'
                  ORDER BY last_message_at DESC";
        
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return [];
        }
        
        $penjual_list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $penjual_list[] = $row;
        }
        
        return $penjual_list;
    }
    
    /* =====================
       GET ATAU BUAT ROOM
    ===================== */
    private function getOrCreateRoom($penjual_id) {
        // Cek apakah room sudah ada
        $check = mysqli_query($this->conn, "
            SELECT id FROM chat_rooms 
            WHERE id_pembeli = '{$this->pembeli_id}' 
              AND id_penjual = '$penjual_id'
        ");
        
        if (mysqli_num_rows($check) > 0) {
            $room = mysqli_fetch_assoc($check);
            return $room['id'];
        }
        
        // Buat room baru
        mysqli_query($this->conn, "
            INSERT INTO chat_rooms (id_pembeli, id_penjual, created_at)
            VALUES ('{$this->pembeli_id}', '$penjual_id', NOW())
        ");
        
        return mysqli_insert_id($this->conn);
    }
    
    /* =====================
       GET CHAT HISTORY - FIXED
    ===================== */
    public function getChatHistory($penjual_id) {
        if(!$penjual_id) return [];
        
        // Pastikan room ada
        $room_id = $this->getOrCreateRoom($penjual_id);
        
        // Mark pesan dari penjual sebagai sudah dibaca
        mysqli_query($this->conn, "
            UPDATE chat 
            SET dibaca = 1 
            WHERE id_room = '$room_id'
              AND id_pengirim = '$penjual_id' 
              AND penerima_id = '{$this->pembeli_id}' 
              AND dibaca = 0
        ");
        
        // Ambil history chat
        $query = "SELECT 
                    c.*,
                    CASE 
                        WHEN c.id_pengirim = '{$this->pembeli_id}' THEN 'pembeli'
                        ELSE 'penjual'
                    END as pengirim_role
                  FROM chat c
                  WHERE c.id_room = '$room_id'
                  ORDER BY c.created_at ASC";
        
        $result = mysqli_query($this->conn, $query);
        
        if (!$result) {
            return [];
        }
        
        $chat_history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $chat_history[] = $row;
        }
        return $chat_history;
    }
    
    /* =====================
       SEND MESSAGE - FIXED
    ===================== */
    public function sendMessage($penjual_id, $pesan) {
        if(!$penjual_id || empty(trim($pesan))) return false;
        
        $pesan = mysqli_real_escape_string($this->conn, trim($pesan));
        
        // Pastikan room ada
        $room_id = $this->getOrCreateRoom($penjual_id);
        
        // Insert chat
        $insert = mysqli_query($this->conn, "
            INSERT INTO chat (id_room, id_pengirim, penerima_id, pesan, created_at, dibaca)
            VALUES ('$room_id', '{$this->pembeli_id}', '$penjual_id', '$pesan', NOW(), 0)
        ");
        
        if (!$insert) {
            return false;
        }
        
        // Add notification for seller
        mysqli_query($this->conn, "
            INSERT INTO notifikasi (id_user, pesan, status, created_at)
            VALUES (
                '$penjual_id',
                'Pesan baru dari {$this->pembeli_nama}: " . substr($pesan, 0, 50) . "...',
                'unread',
                NOW()
            )
        ");
        
        return true;
    }
    
    /* =====================
       GET AUTO-REPLY SISTEM - FIXED (tanpa alasan_penolakan)
    ===================== */
    public function getAutoReplyMessage($penjual_id) {
        // Cek apakah ada transaksi dengan penjual ini
        $query = "SELECT id, status, bukti_transfer 
                  FROM transaksi 
                  WHERE id_user='{$this->pembeli_id}' 
                    AND penjual_id='$penjual_id'
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $result = mysqli_query($this->conn, $query);
        if(mysqli_num_rows($result) == 0) {
            return "Selamat datang! Silakan tanyakan produk yang Anda minati kepada penjual.";
        }
        
        $transaksi = mysqli_fetch_assoc($result);
        
        switch($transaksi['status']) {
            case 'pending':
                if(empty($transaksi['bukti_transfer'])) {
                    return "Anda memiliki pesanan yang menunggu pembayaran. Silakan upload bukti transfer di halaman Pesanan Saya.";
                } else {
                    return "Bukti transfer Anda sudah diupload. Menunggu konfirmasi dari penjual.";
                }
                
            case 'dibayar':
                return "Pesanan Anda telah dikonfirmasi. Penjual akan segera memproses pesanan Anda.";
                
            case 'dikirim':
                return "Pesanan Anda sedang dalam pengiriman. Anda bisa melacaknya di halaman Status.";
                
            case 'selesai':
                return "Pesanan Anda sudah selesai. Terima kasih telah berbelanja!";
                
            default:
                return "Selamat datang! Ada yang bisa kami bantu?";
        }
    }
    
    /* =====================
       GET UNREAD NOTIFICATION COUNT
    ===================== */
    public function getUnreadNotificationCount() {
        $query = "SELECT COUNT(*) as unread_count
                  FROM notifikasi 
                  WHERE id_user='{$this->pembeli_id}' AND status='unread'";
        $result = mysqli_query($this->conn, $query);
        $data = mysqli_fetch_assoc($result);
        return $data['unread_count'] ?? 0;
    }
    
    /* =====================
       GET TOTAL UNREAD CHAT COUNT
    ===================== */
    public function getTotalUnreadChatCount() {
        $query = "SELECT COUNT(*) as total_unread 
                  FROM chat c
                  JOIN chat_rooms cr ON c.id_room = cr.id
                  WHERE cr.id_pembeli = '{$this->pembeli_id}'
                    AND c.id_pengirim != '{$this->pembeli_id}'
                    AND c.dibaca = 0";
        $result = mysqli_query($this->conn, $query);
        $data = mysqli_fetch_assoc($result);
        return $data['total_unread'] ?? 0;
    }
    
    /* =====================
       MARK ALL AS READ
    ===================== */
    public function markAllAsRead($penjual_id = null) {
        if ($penjual_id) {
            // Mark specific penjual's messages as read
            $room_id = $this->getOrCreateRoom($penjual_id);
            mysqli_query($this->conn, "
                UPDATE chat 
                SET dibaca = 1 
                WHERE id_room = '$room_id' 
                  AND id_pengirim = '$penjual_id' 
                  AND dibaca = 0
            ");
        } else {
            // Mark all messages as read
            mysqli_query($this->conn, "
                UPDATE chat c
                JOIN chat_rooms cr ON c.id_room = cr.id
                SET c.dibaca = 1
                WHERE cr.id_pembeli = '{$this->pembeli_id}'
                  AND c.id_pengirim != '{$this->pembeli_id}'
                  AND c.dibaca = 0
            ");
        }
    }
    
    /* =====================
       GET PENJUAL AVATAR HTML
    ===================== */
    public function getPenjualAvatar($penjual) {
        if(!empty($penjual['foto'])) {
            // Foto disimpan di folder uploads/
            return '<img src="../../uploads/' . $penjual['foto'] . '" 
                     class="penjual-avatar me-3"
                     alt="' . htmlspecialchars($penjual['nama']) . '"
                     onerror="this.src=\'../../uploads/default-avatar.png\'">';
        } else {
            return '<div class="penjual-avatar bg-secondary me-3 d-flex align-items-center justify-content-center">
                      <i class="bi bi-shop text-white"></i>
                    </div>';
        }
    }
    
    /* =====================
       FORMAT WAKTU
    ===================== */
    public function formatTime($timestamp) {
        if (!$timestamp) return '';
        
        $now = time();
        $time = strtotime($timestamp);
        $diff = $now - $time;
        
        if ($diff < 60) {
            return "baru saja";
        } elseif ($diff < 3600) {
            $min = floor($diff / 60);
            return $min . " menit lalu";
        } elseif ($diff < 86400) {
            $hour = floor($diff / 3600);
            return $hour . " jam lalu";
        } elseif ($diff < 604800) {
            $day = floor($diff / 86400);
            return $day . " hari lalu";
        } else {
            return date("d M Y", $time);
        }
    }
    
    /* =====================
       CEK APAKAH ONLINE
    ===================== */
    public function isOnline($last_activity) {
        if (!$last_activity) return false;
        
        $last = strtotime($last_activity);
        $now = time();
        $diff = $now - $last;
        
        // Dianggap online jika last activity kurang dari 5 menit yang lalu
        return $diff < 300;
    }
}
?>