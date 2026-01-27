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

/* =====================
   TAMBAH PRODUK
===================== */
if (isset($_POST['simpan'])) {
  $nama = htmlspecialchars($_POST['nama']);
  $kategori = $_POST['kategori'];
  $stok = (int)$_POST['stok'];
  $harga = (int)$_POST['harga'];
  $modal = (int)$_POST['modal'];
  $deskripsi = htmlspecialchars($_POST['deskripsi']);
  $penulis = htmlspecialchars($_POST['penulis'] ?? '');
  $isbn = htmlspecialchars($_POST['isbn'] ?? '');

  $margin = $harga - $modal;
  $untung = $margin * $stok;

  $gambar = null;
  if (!empty($_FILES['gambar']['name'])) {
    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($ext, $allowed_ext)) {
      if ($_FILES['gambar']['size'] <= 2 * 1024 * 1024) { // 2MB max
        $gambar = "produk_".time()."_".rand(100,999).".".$ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], "../uploads/".$gambar);
      } else {
        $error = "Ukuran file terlalu besar. Maksimal 2MB.";
      }
    } else {
      $error = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
    }
  }

  // Hitung keuntungan per unit (bukan total)
  $keuntungan_per_unit = $margin;

  if (!isset($error)) {
    mysqli_query($conn,"INSERT INTO produk 
      (penjual_id, kategori_id, isbn, nama_buku, penulis, deskripsi, gambar, stok, harga, modal, margin, keuntungan, created_at)
      VALUES
      ('$idPenjual', '$kategori', '$isbn', '$nama', '$penulis', '$deskripsi', '$gambar', '$stok', '$harga', '$modal', '$margin', '$keuntungan_per_unit', NOW())
    ");

    header("Location: produk.php?success=1");
    exit;
  }
}

$qKategori = mysqli_query($conn,"SELECT * FROM kategori ORDER BY nama_kategori");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Tambah Produk Baru - BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Include CSS sidebar -->
<link rel="stylesheet" href="includes/sidebar.css">

<style>
/* CSS khusus untuk halaman tambah produk */
.form-container {
  max-width: 900px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.08);
  border: 1px solid #e0e0e0;
}
.form-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 8px;
}
.form-label.required:after {
  content: " *";
  color: #dc3545;
}
.calculation-card {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  border-left: 4px solid #3498db;
}
.preview-image {
  max-height: 200px;
  object-fit: cover;
  border-radius: 8px;
  border: 2px dashed #dee2e6;
  padding: 5px;
}
.back-btn {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 8px 20px;
  border-radius: 6px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: background-color 0.3s;
}
.back-btn:hover {
  background-color: #5a6268;
  color: white;
}
.alert-custom {
  border-radius: 8px;
  border: none;
}
.input-group-text-custom {
  background-color: #e9ecef;
  border: 1px solid #ced4da;
}
.form-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e9ecef;
}
.form-section:last-child {
  border-bottom: none;
}
.form-section h5 {
  color: #2c3e50;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f1f1f1;
}
.form-control:focus, .form-select:focus {
  border-color: #3498db;
  box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
}
.calculation-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e9ecef;
}
.calculation-item:last-child {
  border-bottom: none;
  font-weight: 600;
  color: #2c3e50;
  padding-top: 12px;
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
  <h3 class="mb-1">Tambah Produk Baru</h3>
  <p class="text-muted mb-0">Isi informasi produk buku yang akan dijual</p>
</div>
<a href="produk.php" class="back-btn">
  <i class="bi bi-arrow-left"></i> Kembali ke Daftar Produk
</a>
</div>

<?php if(isset($error)): ?>
<div class="alert alert-danger alert-custom mb-4">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <?= $error ?>
</div>
<?php endif; ?>

<?php if(isset($_GET['success'])): ?>
<div class="alert alert-success alert-custom mb-4">
  <i class="bi bi-check-circle me-2"></i>
  Produk berhasil ditambahkan!
</div>
<?php endif; ?>

<div class="form-container">
<form method="POST" enctype="multipart/form-data" id="productForm">
<div class="row g-4">
  
  <!-- INFORMASI PRODUK -->
  <div class="col-md-12 form-section">
    <h5><i class="bi bi-info-circle me-2"></i> Informasi Produk</h5>
    
    <div class="row g-3">
      <div class="col-md-12">
        <label class="form-label required">Nama Buku</label>
        <input type="text" name="nama" class="form-control" required 
               placeholder="Masukkan nama buku" maxlength="150">
        <small class="text-muted">Maksimal 150 karakter</small>
      </div>

      <div class="col-md-6">
        <label class="form-label">Penulis (Opsional)</label>
        <input type="text" name="penulis" class="form-control" 
               placeholder="Nama pengarang" maxlength="150">
      </div>

      <div class="col-md-6">
        <label class="form-label">ISBN (Opsional)</label>
        <input type="text" name="isbn" class="form-control" 
               placeholder="Nomor ISBN" maxlength="20">
      </div>

      <div class="col-md-6">
        <label class="form-label required">Kategori</label>
        <select name="kategori" class="form-select" required>
          <option value="">Pilih Kategori</option>
          <?php while($k = mysqli_fetch_assoc($qKategori)): ?>
          <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label required">Stok Awal</label>
        <div class="input-group">
          <input type="number" name="stok" class="form-control" min="0" required 
                 value="1" id="stokInput">
          <span class="input-group-text input-group-text-custom">unit</span>
        </div>
      </div>

      <div class="col-md-12">
        <label class="form-label">Deskripsi Produk</label>
        <textarea name="deskripsi" class="form-control" rows="4" 
                  placeholder="Deskripsikan produk buku Anda..."
                  maxlength="1000"></textarea>
        <small class="text-muted">Maksimal 1000 karakter</small>
      </div>
    </div>
  </div>

  <!-- GAMBAR PRODUK -->
  <div class="col-md-12 form-section">
    <h5><i class="bi bi-image me-2"></i> Gambar Produk</h5>
    
    <div class="row g-3">
      <div class="col-md-12">
        <label class="form-label">Upload Gambar (Opsional)</label>
        <input type="file" name="gambar" class="form-control" accept="image/*" id="imageInput">
        <small class="text-muted d-block mt-1">
          Format: JPG, PNG, GIF, WebP | Maksimal: 2MB
        </small>
      </div>
      
      <div class="col-md-12">
        <div id="imagePreview" class="d-none mt-3 text-center">
          <p class="text-muted mb-2">Pratinjau Gambar:</p>
          <img id="preview" class="preview-image" src="" alt="Preview">
        </div>
      </div>
    </div>
  </div>

  <!-- HARGA DAN KALKULASI -->
  <div class="col-md-12 form-section">
    <h5><i class="bi bi-cash-stack me-2"></i> Harga & Kalkulasi</h5>
    
    <div class="row g-4">
      <div class="col-md-6">
        <div class="mb-4">
          <label class="form-label required">Harga Modal (per unit)</label>
          <div class="input-group">
            <span class="input-group-text input-group-text-custom">Rp</span>
            <input type="number" name="modal" class="form-control" min="0" required 
                   value="0" id="modalInput">
          </div>
          <small class="text-muted">Harga beli produk dari supplier</small>
        </div>
        
        <div>
          <label class="form-label required">Harga Jual (per unit)</label>
          <div class="input-group">
            <span class="input-group-text input-group-text-custom">Rp</span>
            <input type="number" name="harga" class="form-control" min="0" required 
                   value="0" id="hargaInput">
          </div>
          <small class="text-muted">Harga yang akan dibayar pembeli</small>
        </div>
      </div>
      
      <div class="col-md-6">
        <div class="calculation-card">
          <h6 class="mb-3">Kalkulasi Keuntungan</h6>
          
          <div class="calculation-item">
            <span>Harga Modal:</span>
            <strong id="modalDisplay">Rp 0</strong>
          </div>
          
          <div class="calculation-item">
            <span>Harga Jual:</span>
            <strong id="hargaDisplay">Rp 0</strong>
          </div>
          
          <div class="calculation-item">
            <span>Margin per Unit:</span>
            <strong class="text-info" id="marginDisplay">Rp 0</strong>
          </div>
          
          <div class="calculation-item">
            <span>Stok:</span>
            <strong id="stokDisplay">1 unit</strong>
          </div>
          
          <div class="calculation-item">
            <span>Total Keuntungan Potensial:</span>
            <strong class="text-success" id="keuntunganDisplay">Rp 0</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TOMBOL AKSI -->
  <div class="col-md-12">
    <div class="d-grid gap-3 d-md-flex justify-content-md-end">
      <a href="produk.php" class="btn btn-outline-secondary px-4">
        <i class="bi bi-x-circle me-1"></i> Batal
      </a>
      <button type="submit" name="simpan" class="btn btn-primary px-4">
        <i class="bi bi-check-circle me-1"></i> Simpan Produk
      </button>
    </div>
  </div>

</div>
</form>
</div>

</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Kalkulasi keuntungan real-time
function calculateProfit() {
  const modal = parseInt(document.getElementById('modalInput').value) || 0;
  const harga = parseInt(document.getElementById('hargaInput').value) || 0;
  const stok = parseInt(document.getElementById('stokInput').value) || 0;
  
  const margin = harga - modal;
  const totalKeuntungan = margin * stok;
  
  // Update display
  document.getElementById('modalDisplay').textContent = 'Rp ' + modal.toLocaleString('id-ID');
  document.getElementById('hargaDisplay').textContent = 'Rp ' + harga.toLocaleString('id-ID');
  document.getElementById('marginDisplay').textContent = 'Rp ' + margin.toLocaleString('id-ID');
  document.getElementById('stokDisplay').textContent = stok + ' unit';
  document.getElementById('keuntunganDisplay').textContent = 'Rp ' + totalKeuntungan.toLocaleString('id-ID');
  
  // Warna margin
  const marginDisplay = document.getElementById('marginDisplay');
  if (margin > 0) {
    marginDisplay.className = 'text-success';
  } else if (margin < 0) {
    marginDisplay.className = 'text-danger';
  } else {
    marginDisplay.className = 'text-secondary';
  }
}

// Preview gambar
document.getElementById('imageInput').addEventListener('change', function(e) {
  const preview = document.getElementById('preview');
  const previewContainer = document.getElementById('imagePreview');
  const file = e.target.files[0];
  
  if (file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
      preview.src = e.target.result;
      previewContainer.classList.remove('d-none');
    }
    
    reader.readAsDataURL(file);
  } else {
    previewContainer.classList.add('d-none');
  }
});

// Validasi harga tidak boleh lebih rendah dari modal
document.getElementById('hargaInput').addEventListener('input', function() {
  const modal = parseInt(document.getElementById('modalInput').value) || 0;
  const harga = parseInt(this.value) || 0;
  
  if (harga < modal) {
    this.classList.add('is-invalid');
    document.querySelector('button[type="submit"]').disabled = true;
    
    // Tampilkan pesan error
    let errorMsg = this.nextElementSibling;
    if (!errorMsg || !errorMsg.classList.contains('invalid-feedback')) {
      errorMsg = document.createElement('div');
      errorMsg.className = 'invalid-feedback';
      this.parentNode.appendChild(errorMsg);
    }
    errorMsg.textContent = 'Harga jual tidak boleh lebih rendah dari harga modal';
  } else {
    this.classList.remove('is-invalid');
    document.querySelector('button[type="submit"]').disabled = false;
  }
  
  calculateProfit();
});

// Event listeners untuk kalkulasi
document.getElementById('modalInput').addEventListener('input', calculateProfit);
document.getElementById('hargaInput').addEventListener('input', calculateProfit);
document.getElementById('stokInput').addEventListener('input', calculateProfit);

// Validasi form sebelum submit
document.getElementById('productForm').addEventListener('submit', function(e) {
  const modal = parseInt(document.getElementById('modalInput').value) || 0;
  const harga = parseInt(document.getElementById('hargaInput').value) || 0;
  
  if (harga < modal) {
    e.preventDefault();
    alert('Harga jual tidak boleh lebih rendah dari harga modal!');
    document.getElementById('hargaInput').focus();
    return false;
  }
  
  if (harga <= 0 || modal < 0) {
    e.preventDefault();
    alert('Harga harus lebih dari 0 dan modal tidak boleh negatif!');
    return false;
  }
  
  // Konfirmasi sebelum submit
  if (!confirm('Simpan produk ini?')) {
    e.preventDefault();
    return false;
  }
});

// Initialize calculation on page load
document.addEventListener('DOMContentLoaded', calculateProfit);
</script>
</body>
</html>