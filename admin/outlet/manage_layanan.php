<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/header.php';

// HANYA OWNER YANG BOLEH AKSES
checkAuth([1]); 
$conn = connectDB();

// 1. TANGKAP ID CABANG DARI URL
$id_outlet = isset($_GET['id_outlet']) ? (int)$_GET['id_outlet'] : 0;

// Jika owner langsung tembak URL tanpa milih cabang, tendang balik ke halaman outlet!
if ($id_outlet == 0) {
    header("Location: outlet.php");
    exit;
}

// Ambil Nama Cabang untuk Judul Halaman
$cek_outlet = $conn->prepare("SELECT nama_outlet FROM outlets WHERE id_outlet = ?");
$cek_outlet->bind_param("i", $id_outlet);
$cek_outlet->execute();
$data_outlet = $cek_outlet->get_result()->fetch_assoc();

if (!$data_outlet) {
    echo "Cabang tidak ditemukan!"; exit;
}
$nama_cabang = $data_outlet['nama_outlet'];


// --- PROSES CRUD LAYANAN ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    
    if ($aksi == 'tambah') {
        $nama = trim($_POST['nama_layanan']);
        $harga = (float)$_POST['harga'];
        $satuan = trim($_POST['satuan']);
        
        $stmt = $conn->prepare("INSERT INTO layanan (id_outlet, nama_layanan, harga, satuan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $id_outlet, $nama, $harga, $satuan);
        
        if ($stmt->execute()) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Berhasil!', 'Layanan ditambahkan ke $nama_cabang.', 'success'); });</script>";
        }
    } 
    elseif ($aksi == 'edit') {
        $id_layanan = (int)$_POST['id_layanan'];
        $nama = trim($_POST['nama_layanan']);
        $harga = (float)$_POST['harga'];
        $satuan = trim($_POST['satuan']);
        
        $stmt = $conn->prepare("UPDATE layanan SET nama_layanan=?, harga=?, satuan=? WHERE id_layanan=? AND id_outlet=?");
        $stmt->bind_param("sdsii", $nama, $harga, $satuan, $id_layanan, $id_outlet);
        
        if ($stmt->execute()) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Tersimpan!', 'Layanan berhasil diupdate.', 'success'); });</script>";
        }
    }
    elseif ($aksi == 'hapus') {
        $id = (int)$_POST['id_layanan'];
        
        $cek = $conn->query("SELECT id_detail FROM transaksi_detail WHERE id_layanan = $id LIMIT 1");
        if ($cek->num_rows > 0) {
             echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Gagal!', 'Layanan ini sudah dipakai di transaksi kasir, tidak bisa dihapus!', 'error'); });</script>";
        } else {
            $stmt = $conn->prepare("DELETE FROM layanan WHERE id_layanan=? AND id_outlet=?");
            $stmt->bind_param("ii", $id, $id_outlet);
            if ($stmt->execute()) {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Terhapus!', 'Layanan dihapus.', 'success'); });</script>";
            }
        }
    }
    elseif ($aksi == 'toggle_status') {
        $id = (int)$_POST['id_layanan'];
        $status_baru = $_POST['status_baru'];
        
        $stmt = $conn->prepare("UPDATE layanan SET status=? WHERE id_layanan=? AND id_outlet=?");
        $stmt->bind_param("sii", $status_baru, $id, $id_outlet);
        if ($stmt->execute()) {
             // HAPUS DUA BARIS INI:
             // header("Location: manage_layanan.php?id_outlet=$id_outlet");
             // exit;
             
             // GANTI MENJADI SCRIPT JAVASCRIPT INI:
             echo "<script>window.location.href = 'manage_layanan.php?id_outlet=$id_outlet';</script>";
             exit;
        }
    }
}

// --- AMBIL DATA LAYANAN KHUSUS UNTUK CABANG INI SAJA ---
$sql_layanan = "SELECT * FROM layanan WHERE id_outlet = $id_outlet ORDER BY nama_layanan ASC";
$res_layanan = $conn->query($sql_layanan);
?>

<div class="container-fluid">
    <a href="outlet.php" class="btn btn-sm btn-secondary mb-3 shadow-sm">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Cabang
    </a>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tags text-info me-2"></i> Layanan <span class="text-primary font-weight-bold">"<?php echo htmlspecialchars($nama_cabang); ?>"</span>
        </h1>
        <button class="btn btn-primary shadow-sm" onclick="bukaModalTambah()">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Tambah Layanan Baru
        </button>
    </div>

    <div class="card shadow mb-4 border-bottom-info">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Layanan</th>
                            <th>Harga</th>
                            <th>Satuan</th>
                            <th class="text-center">Status</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($res_layanan->num_rows == 0) {
                            echo "<tr><td colspan='5' class='text-center text-danger font-weight-bold py-4'>Belum ada layanan di cabang ini. Silakan tambah baru!</td></tr>";
                        }
                        $no=1; 
                        while($row = $res_layanan->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td class="font-weight-bold"><?php echo htmlspecialchars($row['nama_layanan']); ?></td>
                            <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                            <td>/ <?php echo $row['satuan']; ?></td>
                            <td class="text-center">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="aksi" value="toggle_status">
                                    <input type="hidden" name="id_layanan" value="<?php echo $row['id_layanan']; ?>">
                                    <input type="hidden" name="status_baru" value="<?php echo $row['status'] == 'Aktif' ? 'Tidak Aktif' : 'Aktif'; ?>">
                                    
                                    <button type="submit" class="btn btn-sm shadow-sm font-weight-bold <?php echo $row['status'] == 'Aktif' ? 'btn-success' : 'btn-secondary'; ?>" style="border-radius: 20px; width: 85px;">
                                        <?php if($row['status'] == 'Aktif'): ?>
                                            <i class="fas fa-toggle-on"></i> ON
                                        <?php else: ?>
                                            <i class="fas fa-toggle-off"></i> OFF
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="bukaModalEdit(<?php echo $row['id_layanan']; ?>, '<?php echo addslashes($row['nama_layanan']); ?>', <?php echo $row['harga']; ?>, '<?php echo $row['satuan']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="hapusLayanan(<?php echo $row['id_layanan']; ?>, '<?php echo addslashes($row['nama_layanan']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLayanan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalTitle"><i class="fas fa-tag"></i> Form Layanan</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body bg-light">
                    <input type="hidden" name="aksi" id="aksiLayanan" value="tambah">
                    <input type="hidden" name="id_layanan" id="idLayanan">
                    
                    <div class="alert alert-info py-2" role="alert">
                        <i class="fas fa-info-circle me-1"></i> Data disimpan khusus untuk <b><?php echo htmlspecialchars($nama_cabang); ?></b>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Nama Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_layanan" id="namaLayanan" class="form-control" required placeholder="Contoh: Cuci Komplit Express">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="harga" id="hargaLayanan" class="form-control" required min="0" step="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Satuan <span class="text-danger">*</span></label>
                                <select name="satuan" id="satuanLayanan" class="form-control" required>
                                    <option value="kg">Kiloan (kg)</option>
                                    <option value="pcs">Potong (pcs)</option>
                                    <option value="meter">Meteran (m)</option>
                                    <option value="pasang">Pasang (Sepatu)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm"><i class="fas fa-save me-1"></i> Simpan Layanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formHapusLayanan" method="POST" style="display:none;">
    <input type="hidden" name="aksi" value="hapus">
    <input type="hidden" name="id_layanan" id="idHapusLayanan">
</form>

<?php require_once '../../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function bukaModalTambah() {
        $('#modalTitle').html('<i class="fas fa-plus-circle"></i> Tambah Layanan Baru');
        $('#aksiLayanan').val('tambah');
        $('#idLayanan').val('');
        $('#namaLayanan').val('');
        $('#hargaLayanan').val('');
        $('#satuanLayanan').val('kg');
        $('#modalLayanan').modal('show');
    }

    function bukaModalEdit(id_layanan, nama, harga, satuan) {
        $('#modalTitle').html('<i class="fas fa-edit"></i> Edit Layanan');
        $('#aksiLayanan').val('edit');
        $('#idLayanan').val(id_layanan);
        $('#namaLayanan').val(nama);
        $('#hargaLayanan').val(harga);
        $('#satuanLayanan').val(satuan);
        $('#modalLayanan').modal('show');
    }

    function hapusLayanan(id, nama) {
        Swal.fire({
            title: 'Hapus Layanan?',
            html: `Yakin ingin menghapus <b>${nama}</b>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#idHapusLayanan').val(id);
                $('#formHapusLayanan').submit();
            }
        });
    }
</script>