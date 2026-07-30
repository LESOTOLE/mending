<?php
if ($role_id != 2) exit;

$tgl_hari_ini = date('Y-m-d');

// 1. Total Staf Aktif (Role 3 & 4) 
$sql_count = "SELECT COUNT(DISTINCT k.id_user) as t
FROM karyawan k
JOIN users u ON k.id_user = u.id_user
WHERE u.is_active = 1 AND u.id_role IN (3, 4)";
$total_staff = $conn->query($sql_count)->fetch_assoc()['t'] ?? 0;

// 2. Total Hadir
$sql_absen = "SELECT COUNT(DISTINCT id_user) as t FROM absensi WHERE DATE(waktu_masuk) = '$tgl_hari_ini'";
$total_hadir_hrd = $conn->query($sql_absen)->fetch_assoc()['t'] ?? 0;

// 3. List Staf Terbaru
$sql_staff = "SELECT k.nama_lengkap, o.nama_outlet, r.nama_role as role_name
FROM karyawan k
JOIN outlets o ON k.id_outlet = o.id_outlet
JOIN users u ON k.id_user = u.id_user
JOIN roles r ON u.id_role = r.id_role
WHERE u.id_role IN (3, 4)
ORDER BY k.id_user DESC LIMIT 5";
$res_staff = $conn->query($sql_staff);

// --- DATA GRAFIK GAJI (6 BULAN TERAKHIR) ---
$chart_gaji_labels = [];
$chart_gaji_data = [];

$sql_gaji = "SELECT DATE_FORMAT(tanggal, '%b %Y') as bulan, SUM(nominal) as total
FROM pengeluaran
WHERE keterangan LIKE '%Gaji%'
AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY MONTH(tanggal)
ORDER BY tanggal ASC";
$res_gaji = $conn->query($sql_gaji);

if ($res_gaji && $res_gaji->num_rows > 0) {
    while ($row_g = $res_gaji->fetch_assoc()) {
        $chart_gaji_labels[] = $row_g['bulan'];
        $chart_gaji_data[] = (int)$row_g['total'];
    }
} else {
    $chart_gaji_labels = [date('M Y')];
    $chart_gaji_data = [0];
}

$json_gaji_labels = json_encode($chart_gaji_labels);
$json_gaji_data = json_encode($chart_gaji_data);
?>

<div class="row">
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Karyawan Aktif</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total_staff; ?> Orang</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Kehadiran Hari Ini</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total_hadir_hrd; ?> / <?php echo $total_staff; ?> Staf</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-12 col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-white border-bottom-primary">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Tren Pengeluaran Gaji (6 Bulan Terakhir)</h6>
            </div>
            <div class="card-body">
                <div class="chart-bar" style="height: 300px;">
                    <canvas id="salaryChart"></canvas>
                </div>
                <hr>
                <small class="text-muted">Total pengeluaran gaji yang tercatat di sistem setiap bulannya.</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom-primary">
                <h6 class="m-0 font-weight-bold text-primary">Daftar Staf Operasional Terbaru</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Nama Lengkap</th>
                                <th>Outlet Penempatan</th>
                                <th>Jabatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_staff->num_rows > 0): ?>
                                <?php while ($s = $res_staff->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark"><?php echo htmlspecialchars($s['nama_lengkap']); ?></td>
                                        <td><span class="badge bg-info rounded-pill"><?php echo $s['nama_outlet']; ?></span></td>
                                        <td><?php echo $s['role_name']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Belum ada data staf.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/mending/assets/vendor/js/chart.js"></script>
<script>
    const ctxGaji = document.getElementById('salaryChart');
    if (ctxGaji) {
        new Chart(ctxGaji, {
            type: 'bar',
            data: {
                labels: <?php echo $json_gaji_labels; ?>,
                datasets: [{
                    label: "Total Gaji",
                    backgroundColor: "#00ff88", 
                    hoverBackgroundColor: "#00cc66",
                    borderColor: "#00ff88",
                    data: <?php echo $json_gaji_data; ?>,
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            color: '#64748b',
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
</script>
