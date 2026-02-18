<?php
session_start();
require_once "../../config/database.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pembeli') {
    echo "<script>alert('Unauthorized'); window.location.href='../auth/login.php';</script>";
    exit;
}

$idPembeli = $_SESSION['user']['id'];
$transaksi_id = $_POST['transaksi_id'] ?? 0;

// Validasi input
if (!$transaksi_id) {
    echo "<script>alert('ID Transaksi tidak valid'); window.history.back();</script>";
    exit;
}

// Cek transaksi milik pembeli
$check = mysqli_query($conn, "
    SELECT id, penjual_id, status 
    FROM transaksi 
    WHERE id = '$transaksi_id' AND id_user = '$idPembeli'
");

if (mysqli_num_rows($check) == 0) {
    echo "<script>alert('Transaksi tidak ditemukan atau bukan milik Anda'); window.location.href='../status.php';</script>";
    exit;
}

$transaksi = mysqli_fetch_assoc($check);

// Cek status transaksi - hanya pending yang bisa upload
if ($transaksi['status'] != 'pending') {
    echo "<script>alert('Transaksi sudah diproses, tidak bisa upload ulang'); window.location.href='../status.php?id=$transaksi_id';</script>";
    exit;
}

// Upload file
if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
    $file = $_FILES['bukti_transfer'];
    $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($file['name']));
    $targetDir = "../../uploads/bukti_transfer/";
    
    // Buat direktori jika belum ada
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetFile = $targetDir . $fileName;
    
    // Validasi tipe file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        echo "<script>alert('Format file harus JPG/PNG'); window.history.back();</script>";
        exit;
    }
    
    // Validasi ukuran file (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        echo "<script>alert('Ukuran file maksimal 2MB'); window.history.back();</script>";
        exit;
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // PERBAIKAN: Update database dengan status 'dibayar'
        $update = mysqli_query($conn, "
            UPDATE transaksi 
            SET bukti_transfer = '$fileName', 
                status = 'dibayar', 
                updated_at = NOW() 
            WHERE id = '$transaksi_id'
        ");
        
        if ($update) {
            // Tambah notifikasi untuk penjual
            if ($transaksi['penjual_id']) {
                $notifQuery = "
                    INSERT INTO notifikasi (id_user, pesan, status, created_at)
                    VALUES (
                        '{$transaksi['penjual_id']}',
                        'Pembeli telah mengupload bukti transfer untuk transaksi #$transaksi_id. Silakan konfirmasi pembayaran.',
                        'unread',
                        NOW()
                    )
                ";
                mysqli_query($conn, $notifQuery);
            }
            
            echo "<script>
                alert('Bukti transfer berhasil diupload! Status pesanan sekarang: Menunggu Konfirmasi Penjual');
                window.location.href='../status.php?id=$transaksi_id';
            </script>";
        } else {
            echo "<script>
                alert('Gagal update database: " . mysqli_error($conn) . "');
                window.history.back();
            </script>";
        }
    } else {
        echo "<script>alert('Gagal upload file. Coba lagi.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Tidak ada file yang diupload'); window.history.back();</script>";
}
?>