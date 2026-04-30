<?php
require_once '../../includes/config.php';
checkAuth([3, 4]); // Akses untuk Karyawan dan Kasir

$conn = connectDB();
$id_user = $_SESSION['id_user'];
$page_title = "Riwayat Gaji & Slip";

// 1. AMBIL STATISTIK RINGKAS
// Gaji Terakhir
$q_last = $conn->query("SELECT total_gaji FROM penggajian WHERE id_user = $id_user ORDER BY bulan DESC, bulan DESC LIMIT 1");
$gaji_terakhir = $q_last->fetch_assoc()['total_gaji'] ?? 0;

// Total Gaji Tahun Ini
$tahun_ini = date('Y');
$q_total_tahun = $conn->query("SELECT SUM(total_gaji) as total FROM penggajian WHERE id_user = $id_user AND bulan = '$tahun_ini'");
$total_tahun_ini = $q_total_tahun->fetch_assoc()['total'] ?? 0;

// 2. AMBIL SEMUA RIWAYAT GAJI
$sql = "SELECT * FROM penggajian WHERE id_user = $id_user ORDER BY bulan DESC, bulan DESC";
$result = $conn->query($sql);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-wallet text-success mr-2"></i>Riwayat Gaji Anda</h1>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Gaji Terakhir Terbayar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($gaji_terakhir, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Pendapatan (Tahun <?php echo $tahun_ini; ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($total_tahun_ini, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Penerimaan Gaji Bulanan</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="bg-light text-center">
                        <tr>
                            <th>Bulan / Tahun</th>
                            <th>Gaji Pokok</th>
                            <th>Bonus/Tunjangan</th>
                            <th>Potongan Absen</th>
                            <th>Total Diterima</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="font-weight-bold text-dark text-center">
                                        <?php
                                        $nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                        echo $nama_bulan[$row['bulan']] . " " . $row['tahun'];
                                        ?>
                                    </td>
                                    <td>Rp <?php echo number_format($row['gaji_pokok'], 0, ',', '.'); ?></td>
                                    <td class="text-success">+ Rp <?php echo number_format($row['tunjangan'], 0, ',', '.'); ?></td>
                                    <td class="text-danger">- Rp <?php echo number_format($row['potongan'], 0, ',', '.'); ?></td>
                                    <td class="font-weight-bold text-primary">Rp <?php echo number_format($row['total_gaji'], 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-primary btn-sm shadow-sm" onclick='viewSlip(<?php echo json_encode($row); ?>)'>
                                            <i class="fas fa-file-invoice-dollar mr-1"></i> Lihat Slip
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data riwayat gaji.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSlip" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-receipt mr-2"></i>Slip Gaji Digital</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="contentSlip">
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
            </div>
        </div>
    </div>
</div>

<script>
    function viewSlip(data) {
        const namaBulan = [null, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const formattedDate = namaBulan[data.bulan] + " " + data.tahun;

        let html = `
        <div class="text-center mb-4">
            <h4 class="font-weight-bold text-dark mb-0">MENDING LAUNDRY</h4>
            <p class="small text-muted mb-0">Slip Gaji Periode ${formattedDate}</p>
            <hr class="border-primary">
        </div>
        
        <table class="table table-sm table-borderless">
            <tr><td class="text-muted">Nama Karyawan</td><td class="text-right font-weight-bold"><?php echo $_SESSION['nama_lengkap']; ?></td></tr>
            <tr><td class="text-muted">Status Pembayaran</td><td class="text-right text-success fw-bold">LUNAS</td></tr>
        </table>
        
        <h6 class="font-weight-bold border-bottom pb-1">PENDAPATAN</h6>
        <div class="d-flex justify-content-between mb-1"><span>Gaji Pokok</span><span>Rp ${number_format(data.gaji_pokok)}</span></div>
        <div class="d-flex justify-content-between mb-3 text-success"><span>Tunjangan/Bonus</span><span>+ Rp ${number_format(data.tunjangan)}</span></div>
        
        <h6 class="font-weight-bold border-bottom pb-1">POTONGAN</h6>
        <div class="d-flex justify-content-between mb-3 text-danger"><span>Denda Absensi</span><span>- Rp ${number_format(data.potongan)}</span></div>
        
        <div class="bg-primary text-white p-3 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">GAJI BERSIH</span>
                <h4 class="font-weight-bold mb-0">Rp ${number_format(data.total_gaji)}</h4>
            </div>
        </div>
    `;

        document.getElementById('contentSlip').innerHTML = html;
        var myModal = new bootstrap.Modal(document.getElementById('modalSlip'));
        myModal.show();
    }

    function number_format(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }
</script>

<?php include '../../includes/footer.php'; ?>