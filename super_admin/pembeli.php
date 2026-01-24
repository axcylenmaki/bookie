<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   HAPUS PEMBELI (OFFLINE ONLY)
===================== */
if (isset($_GET['hapus'])) {
  $id = (int)$_GET['hapus'];

  $cek = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT status FROM users WHERE id='$id' AND role='pembeli'")
  );

  if ($cek && $cek['status'] === 'offline') {
    mysqli_query($conn,"DELETE FROM users WHERE id='$id'");
  }

  header("Location: pembeli.php");
  exit;
}

/* =====================
   DATA PEMBELI
===================== */
$data = mysqli_query($conn,"
  SELECT id,nama,email,no_hp,alamat,status,foto
  FROM users
  WHERE role='pembeli'
  ORDER BY created_at DESC
");

/* USER LOGIN */
$idUser = $_SESSION['user']['id'];
$user = mysqli_fetch_assoc(mysqli_query($conn,"
  SELECT nama,email,foto FROM users WHERE id='$idUser'
"));

$fotoAdmin = (!empty($user['foto']) && file_exists("../uploads/profile/".$user['foto']))
  ? "../uploads/profile/".$user['foto']
  : "../assets/img/user.png";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Pembeli</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<div class="col-2 bg-dark text-white min-vh-100 p-3">
  <h4 class="text-center mb-4">BOOKIE</h4>

  <div class="text-center mb-4">
    <img src="<?= $fotoAdmin ?>" width="80" class="rounded-circle mb-2">
    <div><?= $_SESSION['user']['nama'] ?></div>
    <small class="text-secondary"><?= $_SESSION['user']['email'] ?></small>
  </div>

  <ul class="nav flex-column gap-1">
    <li><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
    <li><a class="nav-link text-white" href="penjual.php">Penjual</a></li>
    <li><a class="nav-link text-white fw-bold bg-secondary rounded" href="pembeli.php">Pembeli</a></li>
    <li><a class="nav-link text-white" href="kategori.php">Kategori</a></li>
  </ul>

  <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
</div>

<!-- CONTENT -->
<div class="col-10 p-4">

<h4 class="mb-3">Data Pembeli</h4>

<div class="card">
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
  <th>Aksi</th>
</tr>
</thead>
<tbody>

<?php $no=1; while($p=mysqli_fetch_assoc($data)): ?>
<?php
$fotoPembeli = (!empty($p['foto']) && file_exists("../uploads/profile/".$p['foto']))
  ? "../uploads/profile/".$p['foto']
  : "../assets/img/user.png";
?>
<tr>
<td class="text-center"><?= $no++ ?></td>
<td class="text-center">
  <img src="<?= $fotoPembeli ?>" width="45" class="rounded-circle border">
</td>
<td><?= htmlspecialchars($p['nama']) ?></td>
<td><?= htmlspecialchars($p['email']) ?></td>
<td><?= htmlspecialchars($p['no_hp']) ?: '-' ?></td>
<td class="text-center">
  <span class="badge <?= $p['status']=='online'?'bg-success':'bg-danger' ?>">
    <?= ucfirst($p['status']) ?>
  </span>
</td>
<td><?= htmlspecialchars($p['alamat']) ?: '-' ?></td>
<td class="text-center">

<button class="btn btn-sm btn-info text-white"
  data-bs-toggle="modal"
  data-bs-target="#modalDetail"
  data-nama="<?= $p['nama'] ?>"
  data-email="<?= $p['email'] ?>"
  data-nohp="<?= $p['no_hp'] ?>"
  data-alamat="<?= $p['alamat'] ?>"
  data-status="<?= $p['status'] ?>"
  data-foto="<?= $fotoPembeli ?>"
>Detail</button>

<?php if ($p['status']=='online'): ?>
<button class="btn btn-sm btn-danger" disabled>Hapus</button>
<?php else: ?>
<a href="?hapus=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
   onclick="return confirm('Hapus pembeli ini?')">Hapus</a>
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

<!-- MODAL DETAIL -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail Pembeli</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="d_foto" width="100" class="rounded-circle mb-3 border">
        <h5 id="d_nama"></h5>
        <span id="d_status" class="badge"></span>
        <hr>
        <p><b>Email:</b><br><span id="d_email"></span></p>
        <p><b>No HP:</b><br><span id="d_nohp"></span></p>
        <p><b>Alamat:</b><br><span id="d_alamat"></span></p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('modalDetail').addEventListener('show.bs.modal', e => {
  const b = e.relatedTarget;
  d_foto.src = b.dataset.foto;
  d_nama.innerText = b.dataset.nama;
  d_email.innerText = b.dataset.email;
  d_nohp.innerText = b.dataset.nohp || '-';
  d_alamat.innerText = b.dataset.alamat || '-';
  d_status.innerText = b.dataset.status.toUpperCase();
  d_status.className = b.dataset.status=='online'?'badge bg-success':'badge bg-danger';
});
</script>

</body>
</html>
