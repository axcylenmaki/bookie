<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'penjual') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";
$idPenjual = $_SESSION['user']['id'];

// Data penjual sudah diambil di sidebar.php
$namaPenjual = $_SESSION['user']['nama'] ?? 'Penjual';

// Data QnA
$qna_categories = [
  'pembayaran' => 'Pembayaran',
  'transaksi' => 'Transaksi',
  'pengiriman' => 'Pengiriman',
  'akun' => 'Akun & Keamanan',
  'lainnya' => 'Lainnya'
];

$qna_data = [
  'pembayaran' => [
    [
      'question' => 'Bagaimana cara pembeli melakukan pembayaran?',
      'answer' => 'Pembeli dapat melakukan pembayaran melalui transfer bank ke rekening yang telah Anda tentukan di profil toko. Setelah transfer, pembeli akan mengupload bukti transfer di halaman transaksi mereka.',
      'icon' => 'bi-cash-stack'
    ],
    [
      'question' => 'Berapa lama waktu yang diberikan untuk pembeli melakukan pembayaran?',
      'answer' => 'Pembeli memiliki waktu 24 jam sejak checkout untuk melakukan pembayaran. Jika melebihi waktu tersebut, transaksi akan otomatis dibatalkan.',
      'icon' => 'bi-clock'
    ],
    [
      'question' => 'Apa yang harus saya lakukan setelah pembeli mengupload bukti transfer?',
      'answer' => '1. Cek bukti transfer di halaman Transaksi<br>
                    2. Verifikasi jumlah transfer dan nama rekening<br>
                    3. Jika valid, klik tombol "Approve"<br>
                    4. Jika tidak valid, klik "Tolak" dan berikan alasan',
      'icon' => 'bi-check-circle'
    ],
    [
      'question' => 'Bank apa saja yang didukung untuk pembayaran?',
      'answer' => 'Sistem mendukung semua bank di Indonesia. Anda dapat menambahkan informasi rekening bank Anda di halaman Profil.',
      'icon' => 'bi-bank'
    ],
    [
      'question' => 'Apakah ada biaya transaksi yang dikenakan?',
      'answer' => 'BOOKIE tidak mengenakan biaya transaksi kepada penjual. Semua biaya transfer ditanggung oleh pembeli.',
      'icon' => 'bi-percent'
    ]
  ],
  
  'transaksi' => [
    [
      'question' => 'Bagaimana cara mengapprove pembayaran?',
      'answer' => '1. Buka menu "Transaksi"<br>
                    2. Cari transaksi dengan status "Menunggu"<br>
                    3. Klik tombol "Detail" untuk melihat bukti transfer<br>
                    4. Jika valid, klik "Approve"<br>
                    5. Sistem akan mengubah status menjadi "Diproses"',
      'icon' => 'bi-check-square'
    ],
    [
      'question' => 'Kapan saya harus menolak pembayaran?',
      'answer' => 'Tolak pembayaran jika:<br>
                    • Bukti transfer tidak jelas/tidak terbaca<br>
                    • Jumlah transfer tidak sesuai<br>
                    • Nama rekening pengirim tidak sesuai<br>
                    • Pembayaran terlambat (>24 jam)<br>
                    • Terindikasi penipuan',
      'icon' => 'bi-x-circle'
    ],
    [
      'question' => 'Apa yang terjadi setelah saya approve pembayaran?',
      'answer' => 'Setelah approve:<br>
                    1. Status transaksi berubah menjadi "Approved"<br>
                    2. Anda dapat menginput nomor resi pengiriman<br>
                    3. Pembeli akan mendapat notifikasi<br>
                    4. Anda harus segera mengirimkan barang',
      'icon' => 'bi-truck'
    ],
    [
      'question' => 'Bagaimana cara menghapus transaksi?',
      'answer' => 'Transaksi hanya dapat dihapus jika:<br>
                    • Status "Selesai" atau "Refund"<br>
                    • Sudah lewat 1 menit sejak transaksi selesai<br>
                    Tombol hapus akan aktif secara otomatis setelah waktu tersebut.',
      'icon' => 'bi-trash'
    ]
  ],
  
  'pengiriman' => [
    [
      'question' => 'Kapan saya harus menginput nomor resi?',
      'answer' => 'Input nomor resi setelah:<br>
                    1. Pembayaran sudah di-approve<br>
                    2. Barang sudah dikirimkan ke kurir<br>
                    3. Anda mendapatkan nomor resi dari kurir',
      'icon' => 'bi-box-seam'
    ],
    [
      'question' => 'Bagaimana cara input nomor resi?',
      'answer' => '1. Buka menu "Transaksi"<br>
                    2. Cari transaksi dengan status "Approved"<br>
                    3. Klik tombol "Input Resi"<br>
                    4. Masukkan nomor resi dan pilih kurir<br>
                    5. Klik "Simpan"',
      'icon' => 'bi-input-cursor'
    ],
    [
      'question' => 'Kurir apa saja yang didukung?',
      'answer' => 'Semua kurir pengiriman didukung:<br>
                    • JNE<br>
                    • J&T<br>
                    • TIKI<br>
                    • POS Indonesia<br>
                    • SiCepat<br>
                    • Ninja Xpress<br>
                    • Dan kurir lainnya',
      'icon' => 'bi-truck'
    ],
    [
      'question' => 'Bagaimana pembeli melacak paket?',
      'answer' => 'Setelah Anda input nomor resi:<br>
                    1. Pembeli bisa melihat nomor resi di transaksi mereka<br>
                    2. Klik nomor resi untuk otomatis redirect ke website kurir<br>
                    3. Pembeli dapat melacak status pengiriman langsung',
      'icon' => 'bi-geo-alt'
    ]
  ],
  
  'akun' => [
    [
      'question' => 'Bagaimana cara mengubah informasi rekening bank?',
      'answer' => '1. Buka menu "Profil"<br>
                    2. Scroll ke bagian "Informasi Bank"<br>
                    3. Edit data rekening<br>
                    4. Klik "Simpan Perubahan"',
      'icon' => 'bi-credit-card'
    ],
    [
      'question' => 'Apakah data saya aman di BOOKIE?',
      'answer' => 'Ya, BOOKIE menggunakan:<br>
                    • Enkripsi data sensitif<br>
                    • Proteksi terhadap SQL Injection<br>
                    • Sistem autentikasi yang aman<br>
                    • Backup data rutin',
      'icon' => 'bi-shield-check'
    ],
    [
      'question' => 'Bagaimana jika saya lupa password?',
      'answer' => '1. Klik "Lupa Password" di halaman login<br>
                    2. Masukkan email Anda<br>
                    3. Cek email untuk link reset password<br>
                    4. Buat password baru<br>
                    5. Login dengan password baru',
      'icon' => 'bi-key'
    ],
    [
      'question' => 'Bagaimana cara menghubungi admin?',
      'answer' => 'Anda dapat menghubungi admin melalui:<br>
                    • Email: ayu.syafira39@gmail.com<br>
                    • Telepon: 021-12345678<br>
                    • WhatsApp: 0856-9701-1994<br>
                    • Form kontak di halaman Help',
      'icon' => 'bi-headset'
    ]
  ],
  
  'lainnya' => [
    [
      'question' => 'Bagaimana cara menambah produk baru?',
      'answer' => '1. Buka menu "Produk"<br>
                    2. Klik tombol "+" (tambah)<br>
                    3. Isi semua informasi produk<br>
                    4. Upload gambar produk<br>
                    5. Klik "Simpan Produk"',
      'icon' => 'bi-plus-circle'
    ],
    [
      'question' => 'Bagaimana cara melihat laporan penjualan?',
      'answer' => '1. Buka menu "Laporan"<br>
                    2. Pilih bulan dan tahun<br>
                    3. Sistem akan menampilkan:<br>
                       - Statistik penjualan<br>
                       - Grafik pendapatan<br>
                       - Detail transaksi<br>
                    4. Anda bisa download PDF',
      'icon' => 'bi-bar-chart'
    ],
    [
      'question' => 'Apa itu status "Refund"?',
      'answer' => 'Status "Refund" berarti:<br>
                    • Transaksi dibatalkan<br>
                    • Uang dikembalikan ke pembeli<br>
                    • Biasanya karena pembayaran ditolak<br>
                    • Setelah 1 menit, transaksi bisa dihapus',
      'icon' => 'bi-arrow-counterclockwise'
    ],
    [
      'question' => 'Jam operasional layanan BOOKIE?',
      'answer' => 'BOOKIE tersedia 24/7. Untuk dukungan admin:<br>
                    • Senin-Jumat: 08:00-17:00 WIB<br>
                    • Sabtu: 08:00-12:00 WIB<br>
                    • Minggu & Hari Libur: Tutup',
      'icon' => 'bi-clock-history'
    ]
  ]
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Bantuan & QnA - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">
<style>
/* CSS khusus untuk halaman help */
.faq-category {
  background: #fff;
  border-radius: 10px;
  padding: 25px;
  margin-bottom: 25px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.08);
  border-left: 5px solid #3498db;
  transition: transform 0.3s, box-shadow 0.3s;
}
.faq-category:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.faq-category h4 {
  color: #2c3e50;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f1f1f1;
}
.faq-item {
  margin-bottom: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #3498db;
  transition: background 0.3s;
}
.faq-item:hover {
  background: #e9ecef;
}
.faq-question {
  color: #2c3e50;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
}
.faq-question .icon {
  color: #3498db;
  font-size: 1.2rem;
}
.faq-answer {
  color: #555;
  margin-top: 10px;
  padding-left: 30px;
  line-height: 1.6;
}
.search-box {
  position: relative;
  margin-bottom: 30px;
}
.search-box input {
  padding-right: 45px;
  border-radius: 25px;
  border: 2px solid #e0e0e0;
}
.search-box .bi-search {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #7f8c8d;
}
.contact-card {
  background: linear-gradient(135deg, #3498db, #2c3e50);
  color: white;
  border-radius: 15px;
  padding: 25px;
  margin-top: 30px;
}
.contact-card h4 {
  color: white;
  margin-bottom: 20px;
}
.contact-info {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}
.contact-info .icon {
  font-size: 1.5rem;
  background: rgba(255,255,255,0.2);
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.accordion-button:not(.collapsed) {
  background-color: #e3f2fd;
  color: #2c3e50;
}
.quick-links {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 25px;
}
.quick-link {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 20px;
  padding: 8px 20px;
  text-decoration: none;
  color: #495057;
  transition: all 0.3s;
}
.quick-link:hover {
  background: #3498db;
  color: white;
  border-color: #3498db;
  transform: translateY(-2px);
}
.tips-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  margin-top: 20px;
  border: 1px solid #e0e0e0;
}
.tips-card h5 {
  color: #2c3e50;
  margin-bottom: 15px;
}
mark {
  background-color: #ffeb3b;
  padding: 2px 4px;
  border-radius: 3px;
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
<h3 class="mb-3">Bantuan & Pertanyaan Umum (QnA)</h3>
<p class="text-muted mb-4">Temukan jawaban untuk pertanyaan umum tentang sistem pembayaran dan transaksi</p>

<!-- QUICK LINKS -->
<div class="quick-links">
<?php foreach($qna_categories as $key => $category): ?>
<a href="#<?= $key ?>" class="quick-link">
  <i class="bi bi-arrow-right-circle me-1"></i> <?= $category ?>
</a>
<?php endforeach; ?>
</div>

<!-- SEARCH BOX -->
<div class="search-box">
<input type="text" id="searchFaq" class="form-control" placeholder="Cari pertanyaan atau topik...">
<i class="bi bi-search"></i>
</div>

<!-- FAQ BY CATEGORY -->
<?php foreach($qna_categories as $cat_key => $cat_name): ?>
<div class="faq-category" id="<?= $cat_key ?>">
<h4><i class="bi bi-question-circle me-2"></i> <?= $cat_name ?></h4>
<div class="accordion" id="accordion<?= ucfirst($cat_key) ?>">
  
  <?php foreach($qna_data[$cat_key] as $index => $faq): 
    $faq_id = $cat_key . $index;
  ?>
  <div class="faq-item">
    <div class="faq-question" data-bs-toggle="collapse" data-bs-target="#collapse<?= $faq_id ?>">
      <span class="icon"><i class="bi <?= $faq['icon'] ?>"></i></span>
      <?= $faq['question'] ?>
    </div>
    <div id="collapse<?= $faq_id ?>" class="collapse" data-bs-parent="#accordion<?= ucfirst($cat_key) ?>">
      <div class="faq-answer">
        <?= $faq['answer'] ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  
</div>
</div>
<?php endforeach; ?>

<!-- CONTACT SUPPORT -->
<div class="contact-card">
<h4><i class="bi bi-headset me-2"></i> Butuh Bantuan Lebih Lanjut?</h4>
<p>Jika Anda tidak menemukan jawaban yang dicari, hubungi tim support kami:</p>
  
<div class="row">
<div class="col-md-4">
  <div class="contact-info">
    <div class="icon">
      <i class="bi bi-telephone"></i>
    </div>
    <div>
      <strong>Telepon</strong><br>
      <small>021-12345678</small>
    </div>
  </div>
</div>
<div class="col-md-4">
  <div class="contact-info">
    <div class="icon">
      <i class="bi bi-whatsapp"></i>
    </div>
    <div>
      <strong>WhatsApp</strong><br>
      <small>0856-9701-1994</small>
    </div>
  </div>
</div>
<div class="col-md-4">
  <div class="contact-info">
    <div class="icon">
      <i class="bi bi-envelope"></i>
    </div>
    <div>
      <strong>Email</strong><br>
      <small>ayu.syafira39@gmail.com</small>
    </div>
  </div>
</div>
</div>

<div class="mt-4">
  <h5><i class="bi bi-clock-history me-2"></i> Jam Operasional Support</h5>
  <p class="mb-1">Senin - Jumat: 08:00 - 17:00 WIB</p>
  <p class="mb-1">Sabtu: 08:00 - 12:00 WIB</p>
  <p class="mb-0">Minggu & Hari Libur: Tutup</p>
</div>
</div>

<!-- TIPS & TRIK -->
<div class="card mt-4">
<div class="card-header bg-primary text-white">
  <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i> Tips & Trik untuk Penjual</h5>
</div>
<div class="card-body">
<div class="row">
<div class="col-md-4">
  <div class="d-flex align-items-start mb-3">
    <i class="bi bi-check2-circle text-success me-3 fs-4"></i>
    <div>
      <h6>Verifikasi Cepat</h6>
      <small class="text-muted">Verifikasi pembayaran maksimal 6 jam setelah pembeli upload bukti transfer untuk meningkatkan kepercayaan.</small>
    </div>
  </div>
</div>
<div class="col-md-4">
  <div class="d-flex align-items-start mb-3">
    <i class="bi bi-chat-dots text-info me-3 fs-4"></i>
    <div>
      <h6>Komunikasi Baik</h6>
      <small class="text-muted">Jika menolak pembayaran, berikan alasan yang jelas melalui chat kepada pembeli.</small>
    </div>
  </div>
</div>
<div class="col-md-4">
  <div class="d-flex align-items-start mb-3">
    <i class="bi bi-truck text-warning me-3 fs-4"></i>
    <div>
      <h6>Input Resi Tepat Waktu</h6>
      <small class="text-muted">Input nomor resi segera setelah barang dikirim ke kurir untuk menghindari komplain.</small>
    </div>
  </div>
</div>
</div>
</div>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
document.getElementById('searchFaq').addEventListener('keyup', function() {
  const searchTerm = this.value.toLowerCase();
  const faqItems = document.querySelectorAll('.faq-item');
  
  faqItems.forEach(item => {
    const question = item.querySelector('.faq-question').textContent.toLowerCase();
    const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
    
    if (question.includes(searchTerm) || answer.includes(searchTerm)) {
      item.style.display = 'block';
      
      // Auto expand if search term found
      const collapseId = item.querySelector('.collapse').id;
      const collapseElement = new bootstrap.Collapse('#' + collapseId, {
        toggle: false
      });
      collapseElement.show();
      
      // Highlight search term
      highlightText(item, searchTerm);
    } else {
      item.style.display = 'none';
    }
  });
  
  // Show/hide categories based on visible items
  document.querySelectorAll('.faq-category').forEach(category => {
    const visibleItems = category.querySelectorAll('.faq-item[style="display: block"]').length;
    category.style.display = visibleItems > 0 ? 'block' : 'none';
  });
});

function highlightText(element, term) {
  if (!term) return;
  
  const walker = document.createTreeWalker(
    element,
    NodeFilter.SHOW_TEXT,
    null,
    false
  );
  
  const nodes = [];
  let node;
  while (node = walker.nextNode()) {
    nodes.push(node);
  }
  
  nodes.forEach(node => {
    const text = node.nodeValue;
    const regex = new RegExp(`(${term})`, 'gi');
    const newText = text.replace(regex, '<mark>$1</mark>');
    
    if (newText !== text) {
      const span = document.createElement('span');
      span.innerHTML = newText;
      node.parentNode.replaceChild(span, node);
    }
  });
}

// Auto expand first item in each category on page load
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.faq-category').forEach(category => {
    const firstItem = category.querySelector('.faq-item');
    if (firstItem) {
      const collapseId = firstItem.querySelector('.collapse').id;
      const collapse = new bootstrap.Collapse('#' + collapseId, {
        toggle: false
      });
      collapse.show();
    }
  });
});

// Smooth scroll for quick links
document.querySelectorAll('.quick-link').forEach(link => {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    const targetId = this.getAttribute('href').substring(1);
    const targetElement = document.getElementById(targetId);
    
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: 'smooth'
      });
    }
  });
});
</script>
</body>
</html>