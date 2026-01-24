<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

if (isset($_POST['simpan'])) {
  $id     = $_POST['id'] ?? '';
  $nama   = mysqli_real_escape_string($conn, $_POST['nama']);
  $email  = mysqli_real_escape_string($conn, $_POST['email']);
  $no_hp  = mysqli_real_escape_string($conn, $_POST['no_hp']);
  $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

  /* =====================
     MODE TAMBAH
  ===================== */
  if ($id == '') {

    // cek email
    $cekEmail = mysqli_query($conn,"
      SELECT id FROM users WHERE email='$email'
    ");
    if (mysqli_num_rows($cekEmail) > 0) {
      echo "<script>alert('Email sudah digunakan');window.location='penjual.php';</script>";
      exit;
    }

    // cek no hp (kalau diisi)
    if ($no_hp != '') {
      $cekHp = mysqli_query($conn,"
        SELECT id FROM users WHERE no_hp='$no_hp'
      ");
      if (mysqli_num_rows($cekHp) > 0) {
        echo "<script>alert('No HP sudah digunakan');window.location='penjual.php';</script>";
        exit;
      }
    }

    $password = password_hash("123456", PASSWORD_DEFAULT);
    mysqli_query($conn,"
      INSERT INTO users (nama,email,password,role,no_hp,alamat)
      VALUES ('$nama','$email','$password','penjual','$no_hp','$alamat')
    ");

  } 
  /* =====================
     MODE EDIT
  ===================== */
  else {

    // cek email kecuali diri sendiri
    $cekEmail = mysqli_query($conn,"
      SELECT id FROM users 
      WHERE email='$email' AND id!='$id'
    ");
    if (mysqli_num_rows($cekEmail) > 0) {
      echo "<script>alert('Email sudah digunakan');window.location='penjual.php';</script>";
      exit;
    }

    // cek no hp kecuali diri sendiri
    if ($no_hp != '') {
      $cekHp = mysqli_query($conn,"
        SELECT id FROM users 
        WHERE no_hp='$no_hp' AND id!='$id'
      ");
      if (mysqli_num_rows($cekHp) > 0) {
        echo "<script>alert('No HP sudah digunakan');window.location='penjual.php';</script>";
        exit;
      }
    }

    mysqli_query($conn,"
      UPDATE users SET
        nama='$nama',
        email='$email',
        no_hp='$no_hp',
        alamat='$alamat'
      WHERE id='$id' AND role='penjual'
    ");
  }

  header("Location: penjual.php");
  exit;
}


/* =====================
   HAPUS
===================== */
if (isset($_GET['hapus'])) {
  $id = (int)$_GET['hapus'];

  // cek status
  $cek = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT status FROM users WHERE id='$id' AND role='penjual'")
  );

  if ($cek && $cek['status'] == 'offline') {
    mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
  }

  header("Location: penjual.php");
  exit;
}


/* =====================
   DATA PENJUAL
===================== */
$data = mysqli_query($conn, "
  SELECT id,nama,email,no_hp,alamat,status,foto 
  FROM users 
  WHERE role='penjual' 
  ORDER BY id DESC
");

/* DATA USER LOGIN */
$idUser = $_SESSION['user']['id'];
$user = mysqli_fetch_assoc(mysqli_query($conn,"SELECT nama,email,foto FROM users WHERE id='$idUser'"));
$foto = (!empty($user['foto']) && file_exists("../uploads/profile/".$user['foto']))
  ? "../uploads/profile/".$user['foto']
  : "../assets/img/user.png";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Penjual</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
  <div class="row">

    <!-- SIDEBAR -->
    <div class="col-2 bg-dark text-white min-vh-100 p-3">
      <h4 class="text-center mb-4">BOOKIE</h4>

      <div class="text-center mb-4">
        <img src="<?= $foto ?>" class="rounded-circle mb-2" width="80">
        <div class="fw-semibold"><?= $_SESSION['user']['nama'] ?></div>
        <small class="text-secondary"><?= $_SESSION['user']['email'] ?></small>
      </div>

      <ul class="nav flex-column gap-1">
        <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold bg-secondary rounded" href="penjual.php">Penjual</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="pembeli.php">Pembeli</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="kategori.php">Kategori</a></li>
      </ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Data Penjual</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm">
          + Tambah Penjual
        </button>
      </div>

      <div class="card shadow-sm">
        <div class="card-body table-responsive">
          <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark text-center">
<tr>
  <th>No</th>
  <th>Foto</th>
  <th>Nama</th>
  <th>Email</th>
  <th>No HP</th>
  <th>Status</th>
  <th>Alamat</th>
  <th width="140">Aksi</th>
</tr>

            </thead>
<tbody>
<?php $no=1; while($p=mysqli_fetch_assoc($data)): ?>

<?php
  $fotoPenjual = (!empty($p['foto']) && file_exists("../uploads/profile/".$p['foto']))
    ? "../uploads/profile/".$p['foto']
    : "../assets/img/user.png";
?>

<tr>
  <td class="text-center"><?= $no++ ?></td>

  <!-- FOTO -->
  <td class="text-center">
    <img src="<?= $fotoPenjual ?>" width="45" height="45"
         class="rounded-circle border">
  </td>

  <td><?= htmlspecialchars($p['nama']) ?></td>
  <td><?= htmlspecialchars($p['email']) ?></td>
  <td><?= htmlspecialchars($p['no_hp']) ?></td>

  <!-- STATUS -->
  <td class="text-center">
    <?php if ($p['status'] == 'online'): ?>
      <span class="badge bg-success">Online</span>
    <?php else: ?>
      <span class="badge bg-danger">Offline</span>
    <?php endif; ?>
  </td>

  <td><?= htmlspecialchars($p['alamat']) ?></td>

  <!-- AKSI -->
  <td class="text-center">

    <!-- ✅ TOMBOL DETAIL (INI YANG LU CARI) -->
    <button
      class="btn btn-sm btn-info text-white"
      data-bs-toggle="modal"
      data-bs-target="#modalDetail"
      data-nama="<?= htmlspecialchars($p['nama']) ?>"
      data-email="<?= htmlspecialchars($p['email']) ?>"
      data-nohp="<?= htmlspecialchars($p['no_hp']) ?>"
      data-alamat="<?= htmlspecialchars($p['alamat']) ?>"
      data-status="<?= $p['status'] ?>"
      data-foto="<?= $fotoPenjual ?>"
    >
      Detail
    </button>

    <!-- EDIT -->
    <button
      class="btn btn-sm btn-warning"
      data-bs-toggle="modal"
      data-bs-target="#modalForm"
      data-id="<?= $p['id'] ?>"
      data-nama="<?= htmlspecialchars($p['nama']) ?>"
      data-email="<?= htmlspecialchars($p['email']) ?>"
      data-nohp="<?= htmlspecialchars($p['no_hp']) ?>"
      data-alamat="<?= htmlspecialchars($p['alamat']) ?>"
    >
      Edit
    </button>

    <!-- HAPUS -->
    <?php if ($p['status'] == 'online'): ?>
      <button class="btn btn-sm btn-danger" disabled
              title="Penjual sedang online">
        Hapus
      </button>
    <?php else: ?>
      <a href="?hapus=<?= $p['id'] ?>"
         class="btn btn-sm btn-danger"
         onclick="return confirm('Hapus penjual ini?')">
        Hapus
      </a>
    <?php endif; ?>

  </td>
</tr>

<?php endwhile; ?>
</tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- MODAL FORM -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Form Penjual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="id">

        <div class="mb-2">
          <label>Nama</label>
          <input type="text" name="nama" id="nama" class="form-control" required>
        </div>

        <div class="mb-2">
          <label>Email</label>
          <input type="email" name="email" id="email" class="form-control" required>
        </div>

        <div class="mb-2">
          <label>No HP</label>
          <input type="text" name="no_hp" id="no_hp" class="form-control">
        </div>

        <div class="mb-2">
          <label>Alamat</label>
          <textarea name="alamat" id="alamat" class="form-control"></textarea>
        </div>

        <small class="text-muted">
          Password default penjual: <b>123456</b>
        </small>
      </div>

      <div class="modal-footer">
        <button type="submit" name="simpan" class="btn btn-success">Simpan</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>
<!-- MODAL DETAIL PENJUAL -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Profil Penjual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body text-center">

        <img id="d_foto" class="rounded-circle mb-3 border"
             width="100" height="100">

        <h5 id="d_nama"></h5>
        <span id="d_status" class="badge"></span>

        <hr>

        <p><b>Email:</b><br><span id="d_email"></span></p>
        <p><b>No HP:</b><br><span id="d_nohp"></span></p>
        <p><b>Alamat:</b><br><span id="d_alamat"></span></p>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = document.getElementById('modalForm');
modal.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;

  document.getElementById('id').value     = btn.getAttribute('data-id') || '';
  document.getElementById('nama').value   = btn.getAttribute('data-nama') || '';
  document.getElementById('email').value  = btn.getAttribute('data-email') || '';
  document.getElementById('no_hp').value  = btn.getAttribute('data-nohp') || '';
  document.getElementById('alamat').value = btn.getAttribute('data-alamat') || '';
});
</script>
<script>
const modalDetail = document.getElementById('modalDetail');
modalDetail.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;

  document.getElementById('d_foto').src    = btn.getAttribute('data-foto');
  document.getElementById('d_nama').innerText   = btn.getAttribute('data-nama');
  document.getElementById('d_email').innerText  = btn.getAttribute('data-email');
  document.getElementById('d_nohp').innerText   = btn.getAttribute('data-nohp') || '-';
  document.getElementById('d_alamat').innerText = btn.getAttribute('data-alamat') || '-';

  const status = btn.getAttribute('data-status');
  const badge  = document.getElementById('d_status');
  badge.innerText = status.toUpperCase();
  badge.className = status === 'online'
    ? 'badge bg-success'
    : 'badge bg-danger';
});
</script>


</body>
</html>
