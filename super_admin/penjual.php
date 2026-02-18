<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   SIMPAN (TAMBAH / EDIT)
===================== */
if (isset($_POST['simpan'])) {
  $id     = $_POST['id'] ?? '';
  $nama   = mysqli_real_escape_string($conn, $_POST['nama']);
  $email  = mysqli_real_escape_string($conn, $_POST['email']);
  $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
  $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

  if ($id == '') {
    if (mysqli_num_rows(mysqli_query($conn,"SELECT id FROM users WHERE email='$email'")) > 0) {
      $_SESSION['error'] = 'Email sudah digunakan';
      header("Location: penjual.php");
      exit;
    }
    if ($no_hp && mysqli_num_rows(mysqli_query($conn,"SELECT id FROM users WHERE no_hp='$no_hp'")) > 0) {
      $_SESSION['error'] = 'No HP sudah digunakan';
      header("Location: penjual.php");
      exit;
    }

    $password = password_hash("123456", PASSWORD_DEFAULT);
    $query = mysqli_query($conn,"
      INSERT INTO users (nama,email,password,role,no_hp,alamat)
      VALUES ('$nama','$email','$password','penjual','$no_hp','$alamat')
    ");
    
    if ($query) {
      $_SESSION['success'] = 'Penjual berhasil ditambahkan';
    } else {
      $_SESSION['error'] = 'Gagal menambahkan penjual: ' . mysqli_error($conn);
    }
    
  } else {
    if (mysqli_num_rows(mysqli_query($conn,"
      SELECT id FROM users WHERE email='$email' AND id!='$id'
    ")) > 0) {
      $_SESSION['error'] = 'Email sudah digunakan';
      header("Location: penjual.php");
      exit;
    }
    if ($no_hp && mysqli_num_rows(mysqli_query($conn,"
      SELECT id FROM users WHERE no_hp='$no_hp' AND id!='$id'
    ")) > 0) {
      $_SESSION['error'] = 'No HP sudah digunakan';
      header("Location: penjual.php");
      exit;
    }

    $query = mysqli_query($conn,"
      UPDATE users SET
        nama='$nama',
        email='$email',
        no_hp='$no_hp',
        alamat='$alamat'
      WHERE id='$id' AND role='penjual'
    ");
    
    if ($query) {
      $_SESSION['success'] = 'Data penjual berhasil diperbarui';
    } else {
      $_SESSION['error'] = 'Gagal mengupdate penjual: ' . mysqli_error($conn);
    }
  }

  header("Location: penjual.php");
  exit;
}

/* =====================
   HAPUS
===================== */
if (isset($_GET['hapus'])) {
  $id = (int)$_GET['hapus'];
  $cek = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT status FROM users WHERE id='$id' AND role='penjual'
  "));
  
  if ($cek) {
    if ($cek['status'] == 'offline') {
      $query = mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
      if ($query) {
        $_SESSION['success'] = 'Penjual berhasil dihapus';
      } else {
        $_SESSION['error'] = 'Gagal menghapus penjual';
      }
    } else {
      $_SESSION['error'] = 'Tidak dapat menghapus penjual yang sedang online';
    }
  }
  
  header("Location: penjual.php");
  exit;
}

/* =====================
   DATA PENJUAL
===================== */
$data = mysqli_query($conn,"
  SELECT id, nama, email, no_hp, alamat, status, foto
  FROM users
  WHERE role='penjual'
  ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Penjual | BOOKIE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link href="includes/sidebar.css" rel="stylesheet">
<style>
  /* Reset */
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    overflow-x: hidden;
  }
  
  /* Layout utama */
  .main-container {
    display: flex;
    min-height: 100vh;
  }
  
  .main-content {
    flex: 1;
    margin-left: 250px;
    min-height: 100vh;
    background-color: #f8f9fa;
  }
  
  .content-wrapper {
    padding: 20px;
    width: 100%;
    max-width: calc(100vw - 250px);
  }
  
  /* Header */
  .page-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
  }
  
  .page-title {
    color: #1a237e;
    font-weight: 600;
    margin-bottom: 5px;
  }
  
  /* Table container */
  .table-container {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    width: 100%;
    overflow-x: auto;
  }
  
  /* Table styling */
  .data-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
  }
  
  .data-table thead {
    background-color: #1a237e;
  }
  
  .data-table th {
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    border: none;
    white-space: nowrap;
  }
  
  .data-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: middle;
  }
  
  .data-table tbody tr:hover {
    background-color: #f5f5f5;
  }
  
  /* Kolom spesifik */
  .col-no { width: 50px; }
  .col-foto { width: 70px; }
  .col-status { width: 100px; }
  .col-aksi { width: 180px; }
  
  /* Foto pengguna */
  .user-photo {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid #e0e0e0;
    display: block;
    margin: 0 auto;
  }
  
  /* Status badges */
  .status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    min-width: 80px;
    text-align: center;
  }
  
  .status-online {
    background-color: #d4edda;
    color: #155724;
  }
  
  .status-offline {
    background-color: #f8d7da;
    color: #721c24;
  }
  
  /* Action buttons */
  .action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
  }
  
  .btn-action {
    padding: 6px 12px;
    font-size: 0.85rem;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 70px;
    justify-content: center;
  }
  
  /* Alamat dengan ellipsis */
  .alamat-cell {
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  /* Email dengan ellipsis */
  .email-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  /* Alert messages */
  .alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    min-width: 300px;
    max-width: 400px;
  }
  
  /* Responsive */
  @media (max-width: 1024px) {
    .main-content {
      margin-left: 0;
    }
    
    .content-wrapper {
      max-width: 100vw;
      padding: 15px;
    }
  }
  
  @media (max-width: 768px) {
    .table-container {
      padding: 10px;
    }
    
    .action-buttons {
      flex-direction: column;
      gap: 5px;
    }
    
    .btn-action {
      width: 100%;
    }
    
    .page-header {
      padding: 15px;
    }
  }
  
  /* Modal styling */
  .modal-header {
    background-color: #1a237e;
    color: white;
  }
  
  .modal-title {
    font-weight: 600;
  }
</style>
</head>

<body>
<div class="main-container">
  <!-- ===== SIDEBAR ===== -->
  <?php include "includes/sidebar.php"; ?>
  
  <!-- ===== MAIN CONTENT ===== -->
  <div class="main-content">
    <div class="content-wrapper">
      <!-- Alert Messages -->
      <?php if(isset($_SESSION['success'])): ?>
      <div class="alert-container">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $_SESSION['success'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      </div>
      <?php unset($_SESSION['success']); endif; ?>
      
      <?php if(isset($_SESSION['error'])): ?>
      <div class="alert-container">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $_SESSION['error'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      </div>
      <?php unset($_SESSION['error']); endif; ?>
      
      <!-- Page Header -->
      <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4 class="page-title mb-0">Data Penjual</h4>
            <p class="text-muted mb-0">Kelola data penjual di sistem BOOKIE</p>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm">
            <i class="bi bi-plus-circle me-2"></i>Tambah Penjual
          </button>
        </div>
      </div>
      
      <!-- Data Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th class="col-no">No</th>
              <th class="col-foto">Foto</th>
              <th>Nama</th>
              <th>Email</th>
              <th>No HP</th>
              <th class="col-status">Status</th>
              <th>Alamat</th>
              <th class="col-aksi">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php 
          $no = 1; 
          while ($p = mysqli_fetch_assoc($data)):

  $serverFoto  = __DIR__ . "/../uploads/";
  $urlFoto     = "../uploads/";
  $defaultFoto = "../assets/img/user.png";

  $foto = $defaultFoto;

  if (!empty($p['foto'])) {
    $file = basename($p['foto']);
    if (file_exists($serverFoto . $file)) {
      $foto = $urlFoto . $file;
    }
  }
?>
            <tr>
              <td class="text-center"><?= $no++ ?></td>
              <td class="text-center">
                <img src="<?= $foto ?>"
     class="user-photo"
     loading="lazy"
     onerror="this.onerror=null;this.src='../assets/img/user.png';">

              </td>
              <td><?= htmlspecialchars($p['nama']) ?></td>
              <td class="email-cell">
                <a href="mailto:<?= htmlspecialchars($p['email']) ?>" class="text-decoration-none">
                  <?= htmlspecialchars($p['email']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($p['no_hp'] ?: '-') ?></td>
              <td class="text-center">
                <span class="status-badge <?= $p['status'] == 'online' ? 'status-online' : 'status-offline' ?>">
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
              <td class="alamat-cell"><?= htmlspecialchars($p['alamat'] ?: '-') ?></td>
              <td>
                <div class="action-buttons">
                  <button class="btn btn-warning btn-action"
                    data-bs-toggle="modal"
                    data-bs-target="#modalForm"
                    data-id="<?= $p['id'] ?>"
                    data-nama="<?= htmlspecialchars($p['nama']) ?>"
                    data-email="<?= htmlspecialchars($p['email']) ?>"
                    data-nohp="<?= htmlspecialchars($p['no_hp']) ?>"
                    data-alamat="<?= htmlspecialchars($p['alamat']) ?>">
                    <i class="bi bi-pencil"></i> Edit
                  </button>
                  
                  <?php if ($p['status'] == 'offline'): ?>
                    <a href="?hapus=<?= $p['id'] ?>"
                       onclick="return confirm('Yakin ingin menghapus penjual <?= htmlspecialchars($p['nama']) ?>?')"
                       class="btn btn-danger btn-action">
                      <i class="bi bi-trash"></i> Hapus
                    </a>
                  <?php else: ?>
                    <button class="btn btn-secondary btn-action" disabled title="Tidak dapat menghapus penjual online">
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          
          <?php if(mysqli_num_rows($data) == 0): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">
                <i class="bi bi-shop" style="font-size: 2rem;"></i>
                <p class="mt-2">Belum ada data penjual</p>
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODAL FORM ===== -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Form Penjual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="form-id">
        
        <div class="mb-3">
          <label for="form-nama" class="form-label">Nama Lengkap *</label>
          <input type="text" name="nama" id="form-nama" class="form-control" required>
        </div>
        
        <div class="mb-3">
          <label for="form-email" class="form-label">Email *</label>
          <input type="email" name="email" id="form-email" class="form-control" required>
        </div>
        
        <div class="mb-3">
          <label for="form-nohp" class="form-label">No. Handphone</label>
          <input type="tel" name="no_hp" id="form-nohp" class="form-control">
        </div>
        
        <div class="mb-3">
          <label for="form-alamat" class="form-label">Alamat</label>
          <textarea name="alamat" id="form-alamat" class="form-control" rows="3"></textarea>
        </div>
        
        <div class="alert alert-info">
          <small>
            <i class="bi bi-info-circle"></i> 
            Password default untuk penjual baru: <strong>123456</strong>
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" name="simpan" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Auto-hide alerts after 5 seconds
  setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    });
  }, 5000);

  // Modal form handler
  const modalForm = document.getElementById('modalForm');
  if (modalForm) {
    modalForm.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      // Reset form
      document.getElementById('form-id').value = '';
      document.getElementById('form-nama').value = '';
      document.getElementById('form-email').value = '';
      document.getElementById('form-nohp').value = '';
      document.getElementById('form-alamat').value = '';
      
      // Set modal title
      const modalTitle = modalForm.querySelector('.modal-title');
      
      // If button has data, it's edit mode
      if (button && button.hasAttribute('data-id')) {
        modalTitle.textContent = 'Edit Penjual';
        document.getElementById('form-id').value = button.dataset.id || '';
        document.getElementById('form-nama').value = button.dataset.nama || '';
        document.getElementById('form-email').value = button.dataset.email || '';
        document.getElementById('form-nohp').value = button.dataset.nohp || '';
        document.getElementById('form-alamat').value = button.dataset.alamat || '';
      } else {
        modalTitle.textContent = 'Tambah Penjual';
      }
    });
  }
</script>
</body>
</html>