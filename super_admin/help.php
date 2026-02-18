<?php
session_start();

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Help & FAQ | BOOKIE</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Sidebar CSS -->
    <link rel="stylesheet" href="includes/sidebar.css">

    <style>
        .page-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .page-subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }
        .accordion-button {
            font-weight: 500;
        }
        .accordion-button:not(.collapsed) {
            background-color: #f1f3f5;
            color: #212529;
        }
        .accordion-body {
            color: #495057;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<?php include "includes/sidebar.php"; ?>

<!-- CONTENT -->
<div class="content-area">

    <h2 class="page-title">Help & FAQ</h2>
    <p class="page-subtitle">
        Pusat bantuan resmi <strong>BOOKIE</strong> — layanan jual beli buku & alat tulis.
    </p>

    <!-- =====================
         INFORMASI UMUM
    ====================== -->
    <h5 class="mb-3">Informasi Umum</h5>

    <div class="accordion mb-4" id="faqUmum">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq1">
                    Apa itu BOOKIE?
                </button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqUmum">
                <div class="accordion-body">
                    BOOKIE adalah platform jasa jual beli buku dan alat tulis yang
                    mempertemukan penjual dan pembeli secara online dengan sistem
                    transaksi yang aman dan terkelola.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq2">
                    Produk apa saja yang tersedia?
                </button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqUmum">
                <div class="accordion-body">
                    Produk yang tersedia meliputi buku pelajaran, buku bacaan,
                    novel, alat tulis sekolah, alat tulis kantor, serta perlengkapan belajar lainnya.
                </div>
            </div>
        </div>

    </div>

    <!-- =====================
         AKUN & KEAMANAN
    ====================== -->
    <h5 class="mb-3">Akun & Keamanan</h5>

    <div class="accordion mb-4" id="faqAkun">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq3">
                    Bagaimana cara mengubah data akun?
                </button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAkun">
                <div class="accordion-body">
                    Anda dapat mengubah data akun melalui menu <strong>Profil</strong>.
                    Perubahan meliputi nama, email, nomor telepon, dan alamat.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq4">
                    Bagaimana jika lupa password?
                </button>
            </h2>
            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAkun">
                <div class="accordion-body">
                    Gunakan fitur <strong>Lupa Password</strong> pada halaman login,
                    lalu ikuti instruksi reset password melalui email terdaftar.
                </div>
            </div>
        </div>

    </div>

    <!-- =====================
         TRANSAKSI
    ====================== -->
    <h5 class="mb-3">Transaksi</h5>

    <div class="accordion mb-4" id="faqTransaksi">

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq5">
                    Bagaimana alur transaksi di BOOKIE?
                </button>
            </h2>
            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqTransaksi">
                <div class="accordion-body">
                    Pembeli melakukan pemesanan, penjual memproses pesanan,
                    dan transaksi dicatat oleh sistem hingga selesai.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq6">
                    Apakah transaksi tercatat otomatis?
                </button>
            </h2>
            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqTransaksi">
                <div class="accordion-body">
                    Ya. Semua transaksi akan tercatat otomatis dan dapat
                    dipantau melalui menu <strong>Transaksi</strong>.
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
