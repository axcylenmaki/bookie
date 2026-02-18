<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit;
}

include "../config/database.php";

/* =====================
   DATA AKUN
===================== */
$namaUser   = $_SESSION['user']['nama'];
$emailUser  = $_SESSION['user']['email'] ?? '-';
$roleUser   = strtoupper($_SESSION['user']['role']);
$statusUser = 'Aktif';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Super Admin - BOOKIE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="includes/sidebar.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .welcome-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 8px rgba(0,0,0,.08);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .label {
            color: #6c757d;
        }
        .value {
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <!-- SIDEBAR -->
    <?php include "includes/sidebar.php"; ?>

    <!-- CONTENT -->
    <div class="content-area p-4">

        <!-- WELCOME -->
        <div class="welcome-box">
            <h2>Selamat Datang, <?= htmlspecialchars($namaUser) ?> 👋</h2>
            <p class="mb-0">
                Anda login sebagai <strong>Super Admin</strong> dan memiliki akses penuh ke sistem BOOKIE.
            </p>
        </div>

        <!-- INFO AKUN -->
<div class="info-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="bi bi-person-badge"></i> Informasi Akun
        </h5>
        <a href="profile.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil-square"></i> Edit Profil
        </a>
    </div>

    <div class="info-item">
        <span class="label">Nama</span>
        <span class="value"><?= htmlspecialchars($namaUser) ?></span>
    </div>

    <div class="info-item">
        <span class="label">Email</span>
        <span class="value"><?= htmlspecialchars($emailUser) ?></span>
    </div>

    <div class="info-item">
        <span class="label">Role</span>
        <span class="value text-primary"><?= $roleUser ?></span>
    </div>

    <div class="info-item">
        <span class="label">Status Akun</span>
        <span class="value text-success"><?= $statusUser ?></span>
    </div>

    <div class="info-item">
        <span class="label">Hak Akses</span>
        <span class="value">Manajemen Sistem</span>
    </div>
</div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
