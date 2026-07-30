<?php
require_once '../../includes/config.php';
checkAuth([1, 2]); // Hanya Owner (1) dan HRD (2)

$conn = connectDB();
$page_title = "Monitoring Absensi Karyawan";
include 'auto_cleanup.php';
// --- LOGIKA FILTER ---
$tgl_awal  = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal)) $tgl_awal = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) $tgl_akhir = date('Y-m-d');
$id_outlet = isset($_GET['outlet_filter']) ? (int)$_GET['outlet_filter'] : 0;

// Ambil Daftar Outlet untuk Dropdown Filter
$outlets = [];
$res_o = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");
while ($row = $res_o->fetch_assoc()) {
    $outlets[] = $row;
}

// --- QUERY DATA ABSENSI ---
$sql = "SELECT a.*, k.nama_lengkap, o.nama_outlet 
        FROM absensi a
        JOIN users u ON a.id_user = u.id_user
        JOIN karyawan k ON u.id_user = k.id_user
        JOIN outlets o ON k.id_outlet = o.id_outlet
        WHERE a.tanggal BETWEEN ? AND ?";

if ($id_outlet > 0) {
    $sql .= " AND o.id_outlet = ?";
}

$sql .= " ORDER BY a.tanggal DESC, a.waktu_masuk DESC";
$stmt = $conn->prepare($sql);
if ($id_outlet > 0) {
    $stmt->bind_param("ssi", $tgl_awal, $tgl_akhir, $id_outlet);
} else {
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
}
$stmt->execute();
$result = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-camera-retro mr-2 text-primary"></i>Monitoring Absensi</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body bg-light">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Dari Tanggal:</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Sampai Tanggal:</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold">Filter Cabang:</label>
                    <select name="outlet_filter" class="form-control">
                        <option value="0">-- Semua Cabang --</option>
                        <?php foreach ($outlets as $o): ?>
                            <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($id_outlet == $o['id_outlet']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['nama_outlet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-search mr-1"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="bg-primary text-white text-center">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Nama Karyawan</th>
                            <th>Cabang</th>
                            <th>Bukti Selfie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td class="text-center"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                    <td class="text-center fw-bold text-primary"><?php echo $row['waktu_masuk']; ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_outlet']); ?></td>
                                    <td class="text-center">
                                        <?php if ($row['foto_masuk'] == 'kadaluarsa'): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1" title="Foto dihapus otomatis setelah 30 hari untuk menghemat server">
                                                <i class="fas fa-trash-alt mr-1"></i> Dihapus Sistem
                                            </span>

                                        <?php elseif (!empty($row['foto_masuk'])): ?>
                                            <button class="btn btn-info btn-sm shadow-sm"
                                                onclick="showSelfie('<?php echo $row['foto_masuk']; ?>', '<?php echo $row['nama_lengkap']; ?>', '<?php echo $row['waktu_masuk']; ?>')">
                                                <i class="fas fa-eye mr-1"></i> Lihat Foto
                                            </button>

                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white px-3 py-2">Tidak ada foto</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Data absensi tidak ditemukan untuk periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSelfie" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title" id="modalTitle">Bukti Absensi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center bg-light">
                <img id="imgSelfie" src="" class="img-fluid rounded shadow" alt="Selfie Karyawan">
                <div class="mt-3 p-2 bg-white rounded border">
                    <p class="mb-0 small text-muted">Absen atas nama:</p>
                    <h5 id="modalNama" class="font-weight-bold text-primary mb-0"></h5>
                    <p id="modalJam" class="small text-danger font-weight-bold"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSelfie(fotoPath, nama, jam) {
        // Sesuaikan path folder foto Anda di sini (misal: ../../uploads/absensi/)
        const fullPath = "../../uploads/absensi/" + fotoPath;

        document.getElementById('imgSelfie').src = fullPath;
        document.getElementById('modalNama').innerText = nama;
        document.getElementById('modalJam').innerText = "Jam Masuk: " + jam;

        // Tampilkan Modal Bootstrap 5
        var myModal = new bootstrap.Modal(document.getElementById('modalSelfie'));
        myModal.show();
    }
</script>

<?php include '../../includes/footer.php'; ?>