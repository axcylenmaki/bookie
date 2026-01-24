<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'super_admin') {
  header("Location: ../auth/login.php");
  exit;
}

include "../config/database.php";

/* =====================
   DATA ADMIN LOGIN
===================== */
$idAdmin = $_SESSION['user']['id'];
$admin = mysqli_fetch_assoc(
  mysqli_query($conn,"SELECT nama,email,foto FROM users WHERE id='$idAdmin'")
);

$fotoAdmin = (!empty($admin['foto']) && file_exists("../uploads/profile/".$admin['foto']))
  ? "../uploads/profile/".$admin['foto']
  : "../assets/img/user.png";

/* =====================
   SIMPAN (TAMBAH / EDIT)
===================== */
if (isset($_POST['simpan'])) {
  $id   = $_POST['id'] ?? '';
  $nama = mysqli_real_escape_string($conn, $_POST['nama_kategori']);
  $desk = mysqli_real_escape_string($conn, $_POST['deskripsi']);

  // =====================
  // CEK NAMA KATEGORI
  // =====================
  if ($id == '') {
    // mode tambah
    $cek = mysqli_query($conn,"
      SELECT id FROM kategori WHERE nama_kategori='$nama'
    ");
  } else {
    // mode edit (kecuali dirinya sendiri)
    $cek = mysqli_query($conn,"
      SELECT id FROM kategori 
      WHERE nama_kategori='$nama' AND id!='$id'
    ");
  }

  if (mysqli_num_rows($cek) > 0) {
    echo "<script>
      alert('Nama kategori sudah ada!');
      window.location='kategori.php';
    </script>";
    exit;
  }

  // =====================
  // UPLOAD FOTO
  // =====================
  $fotoName = '';
  if (!empty($_FILES['foto']['name'])) {
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $fotoName = 'kategori_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['foto']['tmp_name'], "../uploads/kategori/".$fotoName);
  }

  // =====================
  // SIMPAN DATA
  // =====================
  if ($id == '') {
    mysqli_query($conn,"
      INSERT INTO kategori (nama_kategori, deskripsi, foto)
      VALUES ('$nama','$desk','$fotoName')
    ");
  } else {
    if ($fotoName != '') {
      mysqli_query($conn,"
        UPDATE kategori SET
          nama_kategori='$nama',
          deskripsi='$desk',
          foto='$fotoName'
        WHERE id='$id'
      ");
    } else {
      mysqli_query($conn,"
        UPDATE kategori SET
          nama_kategori='$nama',
          deskripsi='$desk'
        WHERE id='$id'
      ");
    }
  }

  header("Location: kategori.php");
  exit;
}

/* =====================
   HAPUS
===================== */
if (isset($_GET['hapus'])) {
  $id = (int)$_GET['hapus'];

  $q = mysqli_fetch_assoc(mysqli_query($conn,"SELECT foto FROM kategori WHERE id='$id'"));
  if ($q && $q['foto'] && file_exists("../uploads/kategori/".$q['foto'])) {
    unlink("../uploads/kategori/".$q['foto']);
  }

  mysqli_query($conn,"DELETE FROM kategori WHERE id='$id'");
  header("Location: kategori.php");
  exit;
}

/* =====================
   DATA KATEGORI
===================== */
$data = mysqli_query($conn,"
  SELECT 
    k.id,
    k.nama_kategori,
    k.deskripsi,
    k.foto,
    COUNT(p.id) AS jumlah_produk
  FROM kategori k
  LEFT JOIN produk p ON p.kategori_id = k.id
  GROUP BY k.id
  ORDER BY k.id DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Kategori</title>
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
        <div class="fw-semibold"><?= $_SESSION['user']['nama'] ?></div>
        <small class="text-secondary"><?= $_SESSION['user']['email'] ?></small>
      </div>

      <ul class="nav flex-column gap-1">
        <li><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
        <li><a class="nav-link text-white" href="penjual.php">Penjual</a></li>
        <li><a class="nav-link text-white" href="pembeli.php">Pembeli</a></li>
        <li>
          <a class="nav-link text-white fw-bold bg-secondary rounded"
             href="kategori.php">Kategori</a>
        </li>
            </ul>

      <a href="../logout.php" class="btn btn-secondary w-100 mt-4">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="col-10 p-4">

      <div class="d-flex justify-content-between mb-3">
        <h4>Data Kategori</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm">
          + Tambah Kategori
        </button>
      </div>

      <div class="card shadow-sm">
        <div class="card-body table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th>No</th>
                <th>Foto</th>
                <th>Nama Kategori</th>
                <th>Deskripsi</th>
                <th>Jumlah Produk</th>
                <th width="140">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no=1; while($k=mysqli_fetch_assoc($data)): ?>
              <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td class="text-center">
                  <?php
                    $img = (!empty($k['foto']) && file_exists("../uploads/kategori/".$k['foto']))
                      ? "../uploads/kategori/".$k['foto']
                      : "../assets/img/kategori.png";
                  ?>
                  <img src="<?= $img ?>" width="60">
                </td>
                <td><?= htmlspecialchars($k['nama_kategori']) ?></td>
                <td><?= htmlspecialchars($k['deskripsi']) ?></td>
                <td class="text-center">
  <span class="badge bg-primary">
    <?= $k['jumlah_produk'] ?>
  </span>
</td>

                <td class="text-center">
                  <button class="btn btn-sm btn-warning"
                    data-bs-toggle="modal"
                    data-bs-target="#modalForm"
                    data-id="<?= $k['id'] ?>"
                    data-nama="<?= $k['nama_kategori'] ?>"
                    data-deskripsi="<?= $k['deskripsi'] ?>">
                    Edit
                  </button>
<?php if ($k['jumlah_produk'] > 0): ?>
  <button class="btn btn-sm btn-danger" disabled
          title="Kategori masih memiliki produk">
    Hapus
  </button>
<?php else: ?>
  <a href="?hapus=<?= $k['id'] ?>"
     class="btn btn-sm btn-danger"
     onclick="return confirm('Hapus kategori ini?')">
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

<!-- MODAL -->
<div class="modal fade" id="modalForm" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Form Kategori</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="id" id="id">

        <div class="mb-2">
          <label>Nama Kategori</label>
          <input type="text" name="nama_kategori" id="nama" class="form-control" required>
        </div>

        <div class="mb-2">
          <label>Deskripsi</label>
          <textarea name="deskripsi" id="deskripsi" class="form-control"></textarea>
        </div>

        <div class="mb-2">
          <label>Foto (opsional)</label>
          <input type="file" name="foto" class="form-control">
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" name="simpan" class="btn btn-success">Simpan</button>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = document.getElementById('modalForm');
modal.addEventListener('show.bs.modal', e => {
  const btn = e.relatedTarget;
  if (!btn) return;

  document.getElementById('id').value = btn.dataset.id || '';
  document.getElementById('nama').value = btn.dataset.nama || '';
  document.getElementById('deskripsi').value = btn.dataset.deskripsi || '';
});
</script>

</body>
</html>
