<?php
require_once '../../includes/config.php';

checkAuth([1, 2]); // Owner & HRD

$page_title = "Sistem Penggajian (Payroll)";

require_once '../../includes/header.php';

$conn = connectDB();

// =========================================================
// [CONFIG] TARIF GAJI (Bisa Diubah Sesuai Kebijakan)

$RATE_BONUS_HARIAN  = 15000;     // Uang makan per hari hadir
$RATE_DENDA_TELAT   = 10000;     // Potongan per telat
$BATAS_JAM_MASUK    = '08:15:00'; // Karyawan telat jika absen lewat jam ini


$bulan_filter = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $bulan_filter)) {
    $bulan_filter = date('Y-m');
}
$tahun = (int)date('Y', strtotime($bulan_filter));
$bulan = (int)date('m', strtotime($bulan_filter));

// --- PROSES SIMPAN DATA (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_gaji'])) {
    if (!verifyCsrfToken()) {
        echo "<script>Swal.fire('Error', 'Sesi telah kedaluwarsa atau request tidak valid. Silakan coba lagi.', 'error');</script>";
    } else {
    $id_user  = (int)$_POST['id_user'];
    $gapok    = (float)$_POST['gaji_pokok'];
    $bonus    = (float)$_POST['bonus'];
    $potongan = (float)$_POST['potongan'];
    $catatan  = trim($_POST['catatan']);
    $total    = ($gapok + $bonus) - $potongan;

    // Cek apakah data gaji bulan ini sudah ada?
    $stmt_cek = $conn->prepare("SELECT id_penggajian FROM penggajian WHERE id_user = ? AND bulan = ?");
    $stmt_cek->bind_param("is", $id_user, $bulan_filter);
    $stmt_cek->execute();
    $cek = $stmt_cek->get_result();

    if ($cek->num_rows > 0) {
        // UPDATE: Sesuaikan nama kolom
        $stmt = $conn->prepare("UPDATE penggajian SET gaji_pokok=?, bonus=?, potongan=?, total_gaji=?, catatan=? WHERE id_user=? AND bulan=?");
        // Tipe: d=double/decimal, i=integer, s=string
        $stmt->bind_param("ddddsis", $gapok, $bonus, $potongan, $total, $catatan, $id_user, $bulan_filter);
    } else {
        // INSERT: Sesuaikan nama kolom
        $stmt = $conn->prepare("INSERT INTO penggajian (id_user, bulan, gaji_pokok, bonus, potongan, total_gaji, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdddds", $id_user, $bulan_filter, $gapok, $bonus, $potongan, $total, $catatan);
    }

    if ($stmt->execute()) {
        echo "<script>Swal.fire('Berhasil', 'Data Gaji Tersimpan!', 'success');</script>";
    } else {
        echo "<script>Swal.fire('Error', 'Gagal menyimpan data: " . $stmt->error . "', 'error');</script>";
    }
}
}

$sql = "SELECT 
            u.id_user, 
            k.nama_lengkap, 
            u.id_role,
            COALESCE(a.total_hadir, 0) as total_hadir,
            COALESCE(a.total_telat, 0) as total_telat,
            p.gaji_pokok, p.bonus, p.potongan, p.total_gaji, p.catatan, 
            p.id_penggajian as id_gaji
        FROM users u 
        JOIN karyawan k ON u.id_user = k.id_user 
        LEFT JOIN (
            SELECT id_user, 
                   COUNT(*) as total_hadir,
                   SUM(CASE WHEN TIME(waktu_masuk) > ? THEN 1 ELSE 0 END) as total_telat
            FROM absensi 
            WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
            GROUP BY id_user
        ) a ON u.id_user = a.id_user
        LEFT JOIN penggajian p ON u.id_user = p.id_user AND p.bulan = ?
        WHERE u.id_role = 3 AND u.is_active = 1
        ORDER BY k.nama_lengkap ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("siis", $BATAS_JAM_MASUK, $bulan, $tahun, $bulan_filter);
$stmt->execute();
$result = $stmt->get_result();
?>
<style>
    .badge-sudah {
        background-color: #28a745 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        padding: 8px 15px !important;
        border-radius: 5px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        display: inline-block !important;
        opacity: 1 !important;
    }

    .badge-belum {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
        padding: 8px 15px !important;
        border-radius: 5px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        display: inline-block !important;
        opacity: 1 !important;
    }

    /* Memastikan sel tabel tidak memotong konten */
    .align-middle {
        vertical-align: middle !important;
        overflow: visible !important;
    }
</style>
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Payroll System</h1>
        <form method="GET" class="form-inline bg-white p-2 rounded shadow-sm border">
            <label class="mr-2 font-weight-bold text-primary">Periode Gaji:</label>
            <input type="month" name="bulan" class="form-control form-control-sm font-weight-bold" value="<?php echo $bulan_filter; ?>" onchange="this.form.submit()">
        </form>
    </div>

    <div class="alert alert-info shadow-sm border-left-info">
        <div class="row align-items-center">
            <div class="col-md-8">
                <i class="fas fa-robot mr-2"></i> <strong>Sistem Hitung Otomatis:</strong><br>
                <small>
                    (+) Bonus Kehadiran: <b>Rp <?php echo number_format($RATE_BONUS_HARIAN); ?> /hari</b><br>
                    (-) Denda Keterlambatan: <b>Rp <?php echo number_format($RATE_DENDA_TELAT); ?> /kejadian</b>
                </small>
            </div>
            <div class="col-md-4 text-right">
                <small class="text-muted">*Dapat diedit manual saat input.</small>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white border-bottom-primary">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Karyawan (Role: Staff)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%">
                    <thead class="thead-light text-center">
                        <tr>
                            <th width="25%">Nama Karyawan</th>
                            <th>Statistik Absensi</th>
                            <th width="15%">Status Input</th>
                            <th>Total Gaji (THP)</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>

                                <?php
                                // Logika Hitung Otomatis (Preview)
                                $auto_bonus = $row['total_hadir'] * $RATE_BONUS_HARIAN;
                                $auto_potongan = $row['total_telat'] * $RATE_DENDA_TELAT;

                                // Jika di DB lebih besar (misal diedit manual), pakai DB. Jika tidak, selalu tawarkan Auto terbaru.
                                $display_bonus    = ($row['id_gaji'] && $row['bonus'] > $auto_bonus) ? $row['bonus'] : $auto_bonus;
                                $display_potongan = ($row['id_gaji'] && $row['potongan'] > $auto_potongan) ? $row['potongan'] : $auto_potongan;
                                $display_gapok    = ($row['id_gaji']) ? $row['gaji_pokok'] : 0;
                                ?>

                                <tr>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark" style="font-size: 1.1em;"><?php echo $row['nama_lengkap']; ?></div>
                                        <small class="text-muted"><i class="fas fa-id-badge"></i> Staff Operasional</small>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex justify-content-between px-3">
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Hadir: <b><?php echo $row['total_hadir']; ?></b></span>
                                            <span class="text-danger border-left pl-3"><i class="fas fa-exclamation-circle"></i> Telat: <b><?php echo $row['total_telat']; ?></b></span>
                                        </div>
                                    </td>

                                    <td class="align-middle text-center">
                                        <?php if ($row['id_gaji']): ?>
                                            <div class="badge-sudah">
                                                <i class="fas fa-check-circle mr-1"></i> SUDAH INPUT
                                            </div>
                                            <small class="text-success d-block mt-1 font-weight-bold">Data Tersimpan</small>
                                        <?php else: ?>
                                            <div class="badge-belum">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> BELUM INPUT
                                            </div>
                                            <small class="text-danger d-block mt-1 font-weight-bold">Wajib Hitung!</small>
                                        <?php endif; ?>
                                    </td>

                                    <td class="align-middle text-right font-weight-bold text-primary" style="font-size: 1.1em;">
                                        <?php echo $row['total_gaji'] ? 'Rp ' . number_format($row['total_gaji'], 0, ',', '.') : '-'; ?>
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-primary mb-1 w-100 font-weight-bold btn-hitung"
                                            data-id="<?php echo $row['id_user']; ?>"
                                            data-nama="<?php echo htmlspecialchars($row['nama_lengkap'], ENT_QUOTES); ?>"
                                            data-hadir="<?php echo $row['total_hadir']; ?>"
                                            data-telat="<?php echo $row['total_telat']; ?>"
                                            data-gapok="<?php echo $display_gapok; ?>"
                                            data-bonus="<?php echo $display_bonus; ?>"
                                            data-potongan="<?php echo $display_potongan; ?>"
                                            data-catatan="<?php echo htmlspecialchars($row['catatan'] ?? '', ENT_QUOTES); ?>">
                                            <i class="fas fa-calculator mr-1"></i> HITUNG
                                        </button>

                                        <?php if ($row['id_gaji']): ?>
                                            <a href="cetak_slip.php?id=<?php echo $row['id_gaji']; ?>" target="_blank" class="btn btn-sm btn-dark w-100">
                                                <i class="fas fa-print mr-1"></i> CETAK SLIP
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Tidak ada data karyawan aktif pada periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGaji" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-money-check-alt mr-2"></i>Hitung Gaji Karyawan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <div class="modal-body bg-light">
                    <input type="hidden" name="simpan_gaji" value="1">
                    <input type="hidden" name="id_user" id="modal_user_id">

                    <div class="bg-white p-3 rounded mb-3 border text-center shadow-sm">
                        <h5 id="modal_nama" class="font-weight-bold mb-1 text-dark">Nama Karyawan</h5>
                        <div class="d-flex justify-content-center gap-3">
                            <span class="badge badge-success px-2">Hadir: <span id="text_hadir">0</span></span>
                            <span class="badge badge-danger px-2">Telat: <span id="text_telat">0</span></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small font-weight-bold text-dark">Gaji Pokok (Rp)</label>
                        <input type="number" name="gaji_pokok" id="modal_gapok" class="form-control form-control-lg font-weight-bold border-primary" placeholder="0" required oninput="hitungTotal()">
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold text-success">
                                    Bonus (Auto: Rp <?php echo number_format($RATE_BONUS_HARIAN / 1000) . 'k'; ?>/hari)
                                </label>
                                <input type="number" name="bonus" id="modal_bonus" class="form-control border-success font-weight-bold text-success" oninput="hitungTotal()">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold text-danger">
                                    Potongan (Auto: Rp <?php echo number_format($RATE_DENDA_TELAT / 1000) . 'k'; ?>/telat)
                                </label>
                                <input type="number" name="potongan" id="modal_potongan" class="form-control border-danger font-weight-bold text-danger" oninput="hitungTotal()">
                            </div>
                        </div>
                    </div>

                    <div class="form-group bg-primary text-white p-3 rounded text-center shadow">
                        <label class="small font-weight-bold mb-0 text-white-50">TOTAL TAKE HOME PAY</label>
                        <h2 class="font-weight-bold m-0" id="text_total">Rp 0</h2>
                    </div>

                    <div class="form-group mt-3">
                        <label class="small font-weight-bold">Catatan Slip Gaji</label>
                        <textarea name="catatan" id="modal_catatan" class="form-control" rows="2" placeholder="Cth: Potongan kasbon, Bonus lembur..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" onclick="$('#modalGaji').modal('hide')">Batal</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4 shadow-sm">
                        <i class="fas fa-save mr-2"></i> Simpan & Finalisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.btn-hitung').on('click', function() {
            let id = $(this).data('id');
            let nama = $(this).data('nama');
            let hadir = $(this).data('hadir');
            let telat = $(this).data('telat');
            let gapok = parseFloat($(this).data('gapok')) || 0;
            let bonus = parseFloat($(this).data('bonus')) || 0;
            let pot = parseFloat($(this).data('potongan')) || 0;
            let cat = $(this).data('catatan');

            $('#modal_user_id').val(id);
            $('#modal_nama').text(nama);
            $('#text_hadir').text(hadir);
            $('#text_telat').text(telat);

            $('#modal_gapok').val(gapok);
            $('#modal_bonus').val(bonus);
            $('#modal_potongan').val(pot);
            $('#modal_catatan').val(cat);

            hitungTotal();
            $('#modalGaji').modal('show');
        });
    });

    function hitungTotal() {
        let gapok = parseFloat($('#modal_gapok').val()) || 0;
        let bonus = parseFloat($('#modal_bonus').val()) || 0;
        let pot = parseFloat($('#modal_potongan').val()) || 0;

        let total = gapok + bonus - pot;
        $('#text_total').text('Rp ' + total.toLocaleString('id-ID'));
    }
</script>