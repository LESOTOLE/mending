<?php
if (!in_array($role_id, [1, 4])) exit;

$where_outlet = ($role_id == 1) ? "WHERE 1=1" : "WHERE id_outlet = " . (int)$outlet_id;

// 1. Ambil Angka Statistik (Dioptimasi dengan 1 query)
$sql_stats = "SELECT 
    SUM(CASE WHEN status_laundry = 'Baru' THEN 1 ELSE 0 END) as j_baru,
    SUM(CASE WHEN status_laundry = 'Dicuci' THEN 1 ELSE 0 END) as j_cuci,
    SUM(CASE WHEN status_laundry IN ('Selesai', 'Siap Diambil') THEN 1 ELSE 0 END) as j_siap,
    SUM(CASE WHEN status_pembayaran = 'Belum Lunas' THEN 1 ELSE 0 END) as j_hutang
FROM transaksi $where_outlet";
$row_stats = $conn->query($sql_stats)->fetch_assoc();
$jum_baru = $row_stats['j_baru'] ?? 0;
$jum_cuci = $row_stats['j_cuci'] ?? 0;
$jum_siap = $row_stats['j_siap'] ?? 0;
$jum_hutang = $row_stats['j_hutang'] ?? 0;

// 2. Transaksi Terakhir (Join dengan pelanggan untuk nama)
$res_recent = $conn->query("SELECT t.no_invoice, t.status_laundry, t.grand_total, p.nama_pelanggan FROM transaksi t LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan $where_outlet ORDER BY t.id_transaksi DESC LIMIT 5");

// 3. Data Pendapatan 7 Hari Terakhir
$chart_labels = [];
$chart_data = [];
$sql_chart = "SELECT DATE(tgl_masuk) as tgl, SUM(grand_total) as total
FROM transaksi $where_outlet
AND tgl_masuk >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(tgl_masuk) ORDER BY tgl ASC";
$res_chart = $conn->query($sql_chart);
while ($row = $res_chart->fetch_assoc()) {
    $chart_labels[] = date('d/m', strtotime($row['tgl']));
    $chart_data[] = (int)$row['total'];
}
?>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 border-bottom border-warning border-4 shadow-sm h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Antrian Baru</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $jum_baru; ?> <small class="text-muted" style="font-size: 11px;">Nota</small></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-200"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 border-bottom border-info border-4 shadow-sm h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Sedang Dicuci</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $jum_cuci; ?> <small class="text-muted" style="font-size: 11px;">Nota</small></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-soap fa-2x text-gray-200"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 border-bottom border-success border-4 shadow-sm h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Siap Diambil</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $jum_siap; ?> <small class="text-muted" style="font-size: 11px;">Nota</small></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-check-double fa-2x text-gray-200"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-0 border-bottom border-danger border-4 shadow-sm h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Belum Lunas</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $jum_hutang; ?> <small class="text-muted" style="font-size: 11px;">Nota</small></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-circle fa-2x text-gray-200"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Grafik Pendapatan</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px;"><canvas id="revenueChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Order Terbaru</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 13px;">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Invoice</th>
                                <th>Pelanggan</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $res_recent->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark"><?php echo htmlspecialchars($row['no_invoice']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_pelanggan'] ?? 'Umum'); ?></td>
                                    <td class="text-end pe-3">Rp<?php echo number_format($row['grand_total'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= ASSETS_URL ?>vendor/js/chart.js"></script>
<script>
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Pendapatan',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#00f0ff',
                    backgroundColor: 'rgba(0, 240, 255, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#00f0ff',
                    pointBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: { color: '#64748b' }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: { color: '#64748b' }
                    }
                }
            }
        });
    }
</script>
