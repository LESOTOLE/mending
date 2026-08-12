<?php
session_start();
require_once '../../includes/config.php';

checkAuth([1]);

require_once '../../includes/header.php';

$conn = connectDB();

// --- PROSES CRUD (Create, Update, Delete) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Error', 'Sesi telah kedaluwarsa atau request tidak valid. Silakan coba lagi.', 'error'); });</script>";
    } else {
    $aksi = $_POST['aksi'] ?? '';
    
    if ($aksi == 'tambah') {
        $nama = trim($_POST['nama_outlet']);
        $alamat = trim($_POST['alamat']);
        $telp = trim($_POST['no_telp']);
        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $long = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $radius = !empty($_POST['radius_meter']) ? (int)$_POST['radius_meter'] : 50;
        
        $stmt = $conn->prepare("INSERT INTO outlets (nama_outlet, alamat, no_telp, latitude, longitude, radius_meter) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddi", $nama, $alamat, $telp, $lat, $long, $radius);
        
        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Berhasil!', 'Outlet baru berhasil ditambahkan.', 'success');
                });
            </script>";
        }
    } 
    elseif ($aksi == 'edit') {
        $id = (int)$_POST['id_outlet'];
        $nama = trim($_POST['nama_outlet']);
        $alamat = trim($_POST['alamat']);
        $telp = trim($_POST['no_telp']);
        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $long = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $radius = !empty($_POST['radius_meter']) ? (int)$_POST['radius_meter'] : 50;
        
        $stmt = $conn->prepare("UPDATE outlets SET nama_outlet=?, alamat=?, no_telp=?, latitude=?, longitude=?, radius_meter=? WHERE id_outlet=?");
        $stmt->bind_param("sssddii", $nama, $alamat, $telp, $lat, $long, $radius, $id);
        
        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Tersimpan!', 'Data outlet berhasil diperbarui.', 'success');
                });
            </script>";
        }
    }
    elseif ($aksi == 'hapus') {
        $id = (int)$_POST['id_outlet'];
        
        // Pengecekan: Jangan izinkan hapus jika ada transaksi yang nyangkut di outlet ini
        $stmt_cek = $conn->prepare("SELECT id_transaksi FROM transaksi WHERE id_outlet = ? LIMIT 1");
        $stmt_cek->bind_param("i", $id);
        $stmt_cek->execute();
        $cek = $stmt_cek->get_result();
        if ($cek->num_rows > 0) {
             echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Gagal!', 'Tidak bisa menghapus outlet yang sudah memiliki riwayat transaksi!', 'error');
                });
            </script>";
        } else {
            $stmt = $conn->prepare("DELETE FROM outlets WHERE id_outlet=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Terhapus!', 'Outlet berhasil dihapus.', 'success');
                    });
                </script>";
            }
        }
        }
    }
}

// --- AMBIL DATA OUTLET ---
$sql = "SELECT o.*, 
               (SELECT COUNT(*) FROM karyawan k WHERE k.id_outlet = o.id_outlet) as total_karyawan,
               (SELECT COUNT(*) FROM transaksi t WHERE t.id_outlet = o.id_outlet) as total_transaksi
        FROM outlets o 
        ORDER BY o.id_outlet ASC";
$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-store-alt text-primary me-2"></i> Manajemen Outlet</h1>
        <button class="btn btn-primary shadow-sm" onclick="bukaModalTambah()">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Tambah Outlet Baru
        </button>
    </div>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Outlet / Cabang
                            </div>
                            <h5 class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($row['nama_outlet']); ?></h5>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="small text-dark mb-1"><i class="fas fa-map-marker-alt text-danger w-15px"></i> <?php echo htmlspecialchars($row['alamat']); ?></div>
                    <div class="small text-dark mb-1"><i class="fas fa-phone text-success w-15px"></i> <?php echo htmlspecialchars($row['no_telp']); ?></div>
                    <div class="small text-dark mb-3"><i class="fas fa-crosshairs text-info w-15px"></i> GPS: <?php echo ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== '') ? htmlspecialchars($row['latitude'] . ', ' . $row['longitude']) : 'Belum set'; ?> (Radius: <?php echo (int)($row['radius_meter'] ?? 50); ?>m)</div>
                    
                    <div class="d-flex justify-content-between text-muted small mb-3 border-top pt-2">
                        <span><i class="fas fa-users"></i> <?php echo $row['total_karyawan']; ?> Staf</span>
                        <span><i class="fas fa-receipt"></i> <?php echo $row['total_transaksi']; ?> Transaksi</span>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a href="manage_layanan.php?id_outlet=<?php echo $row['id_outlet']; ?>" class="btn btn-info btn-sm text-white font-weight-bold shadow-sm w-100 mb-2">
                            <i class="fas fa-tags me-1"></i> Atur Layanan & Harga
                        </a>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-fill" 
                                    onclick="bukaModalEdit(<?php echo $row['id_outlet']; ?>, '<?php echo addslashes($row['nama_outlet']); ?>', '<?php echo addslashes($row['alamat']); ?>', '<?php echo addslashes($row['no_telp']); ?>', '<?php echo addslashes($row['latitude'] ?? ''); ?>', '<?php echo addslashes($row['longitude'] ?? ''); ?>', <?php echo (int)($row['radius_meter'] ?? 50); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="hapusOutlet(<?php echo $row['id_outlet']; ?>, '<?php echo addslashes($row['nama_outlet']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div class="modal fade" id="modalOutlet" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalTitle"><i class="fas fa-store"></i> Tambah Outlet</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="modal-body bg-light">
                    <input type="hidden" name="aksi" id="aksiOutlet" value="tambah">
                    <input type="hidden" name="id_outlet" id="idOutlet">
                    
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Outlet / Cabang <span class="text-danger">*</span></label>
                        <input type="text" name="nama_outlet" id="namaOutlet" class="form-control" required placeholder="Contoh: LaundryPro Pusat">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Nomor Telepon / WA</label>
                        <input type="text" name="no_telp" id="telpOutlet" class="form-control" placeholder="Akan tercetak di nota kasir">
                    </div>
                    <div class="form-group mb-3">
                        <label class="font-weight-bold">Alamat Lengkap</label>
                        <textarea name="alamat" id="alamatOutlet" class="form-control" rows="3" placeholder="Alamat lengkap outlet..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Latitude (GPS)</label>
                            <input type="text" name="latitude" id="latOutlet" class="form-control" placeholder="-7.250445">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Longitude (GPS)</label>
                            <input type="text" name="longitude" id="longOutlet" class="form-control" placeholder="112.768845">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Radius Batas Absen (Meter)</label>
                        <input type="number" name="radius_meter" id="radiusOutlet" class="form-control" value="50" min="5" max="500">
                        <small class="text-muted">Karyawan wajib berada dalam radius ini dari titik GPS outlet saat absen.</small>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-info btn-sm w-100" onclick="getLokasiSaatIni()">
                            <i class="fas fa-crosshairs me-1"></i> Gunakan Lokasi GPS Saya Saat Ini
                        </button>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary font-weight-bold shadow-sm"><i class="fas fa-save me-1"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formHapus" method="POST" style="display:none;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="aksi" value="hapus">
    <input type="hidden" name="id_outlet" id="idHapus">
</form>

<?php require_once '../../includes/footer.php'; ?>

<script>
    function getLokasiSaatIni() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                $('#latOutlet').val(position.coords.latitude.toFixed(8));
                $('#longOutlet').val(position.coords.longitude.toFixed(8));
                Swal.fire({
                    icon: 'success',
                    title: 'Koordinat Diambil',
                    text: `Lat: ${position.coords.latitude}, Long: ${position.coords.longitude}`,
                    timer: 2000,
                    showConfirmButton: false
                });
            }, function(error) {
                Swal.fire('Gagal GPS', 'Tidak bisa mengambil lokasi GPS. Pastikan izin lokasi aktif.', 'error');
            });
        } else {
            Swal.fire('Error', 'Browser tidak mendukung Geolocation.', 'error');
        }
    }

    function bukaModalTambah() {
        $('#modalTitle').html('<i class="fas fa-store"></i> Tambah Outlet Baru');
        $('#aksiOutlet').val('tambah');
        $('#idOutlet').val('');
        $('#namaOutlet').val('');
        $('#telpOutlet').val('');
        $('#alamatOutlet').val('');
        $('#latOutlet').val('');
        $('#longOutlet').val('');
        $('#radiusOutlet').val('50');
        $('#modalOutlet').modal('show');
    }

    function bukaModalEdit(id, nama, alamat, telp, lat, long, radius) {
        $('#modalTitle').html('<i class="fas fa-edit"></i> Edit Data Outlet');
        $('#aksiOutlet').val('edit');
        $('#idOutlet').val(id);
        $('#namaOutlet').val(nama);
        $('#alamatOutlet').val(alamat);
        $('#telpOutlet').val(telp);
        $('#latOutlet').val(lat || '');
        $('#longOutlet').val(long || '');
        $('#radiusOutlet').val(radius || 50);
        $('#modalOutlet').modal('show');
    }

    function hapusOutlet(id, nama) {
        Swal.fire({
            title: 'Hapus Outlet?',
            html: `Anda yakin ingin menghapus <b>${nama}</b>?<br><small class="text-danger">Aksi ini tidak bisa dibatalkan jika belum ada transaksi.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#idHapus').val(id);
                $('#formHapus').submit();
            }
        });
    }
</script>