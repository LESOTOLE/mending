<?php
require_once '../../includes/config.php';
checkAuth([1, 2, 4]); // Owner, HRD, Admin Outlet

$conn = connectDB();
$role_id = $_SESSION['id_role'];
$user_outlet_id = $_SESSION['id_outlet'];

// --- 1. LOGIKA FILTER CABANG ---
$selected_outlet = 0;
$is_locked = true;

if (in_array($role_id, [1, 2])) {
    $is_locked = false;
    if (isset($_GET['outlet_filter'])) {
        $selected_outlet = $_GET['outlet_filter'];
    }
} else {
    $selected_outlet = $user_outlet_id;
}

// Ambil Daftar Outlet
$outlets = [];
if (!$is_locked) {
    $res_outlets = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY id_outlet ASC");
    while ($row = $res_outlets->fetch_assoc()) {
        $outlets[] = $row;
    }
}

// --- 2. LOGIKA FILTER PERIODE GRAFIK (BARU!) ---
$periode_grafik = isset($_GET['periode_filter']) ? $_GET['periode_filter'] : 'bulan_ini';

$date_filter_sql = "";
$select_date_chart = "";
$group_by_sql_chart = "";
$judul_grafik = "";

if ($periode_grafik == 'bulan_ini') {
    // Tampilkan data harian di bulan ini
    $date_filter_sql = " AND MONTH(tgl_masuk) = MONTH(CURRENT_DATE()) AND YEAR(tgl_masuk) = YEAR(CURRENT_DATE())";
    $select_date_chart = "DATE_FORMAT(tgl_masuk, '%d %b') as tgl"; // Contoh: 01 Nov
    $group_by_sql_chart = "GROUP BY DATE(tgl_masuk) ORDER BY DATE(tgl_masuk) ASC";
    $judul_grafik = "Grafik Pendapatan Harian (Bulan Ini)";
} elseif ($periode_grafik == 'tahun_ini') {
    // Tampilkan data bulanan di tahun ini
    $date_filter_sql = " AND YEAR(tgl_masuk) = YEAR(CURRENT_DATE())";
    $select_date_chart = "DATE_FORMAT(tgl_masuk, '%b %Y') as tgl"; // Contoh: Nov 2026
    $group_by_sql_chart = "GROUP BY MONTH(tgl_masuk) ORDER BY MONTH(tgl_masuk) ASC";
    $judul_grafik = "Grafik Pendapatan Bulanan (Tahun Ini)";
} else {
    // Tampilkan data tahunan (Semua Waktu)
    $date_filter_sql = "";
    $select_date_chart = "DATE_FORMAT(tgl_masuk, '%Y') as tgl"; // Contoh: 2026
    $group_by_sql_chart = "GROUP BY YEAR(tgl_masuk) ORDER BY YEAR(tgl_masuk) ASC";
    $judul_grafik = "Grafik Pendapatan Tahunan (Semua Waktu)";
}


// --- 3. FUNGSI HITUNG OMZET (KOTAK ATAS) ---
function hitungOmzet($conn, $periode, $outlet_id)
{
    $sql = "SELECT SUM(grand_total) as total FROM transaksi WHERE status_pembayaran = 'Lunas'";
    if ($outlet_id > 0) $sql .= " AND id_outlet = '$outlet_id'";

    if ($periode == 'hari_ini') $sql .= " AND DATE(tgl_masuk) = CURDATE()";
    elseif ($periode == 'bulan_ini') $sql .= " AND MONTH(tgl_masuk) = MONTH(CURRENT_DATE()) AND YEAR(tgl_masuk) = YEAR(CURRENT_DATE())";
    elseif ($periode == 'tahun_ini') $sql .= " AND YEAR(tgl_masuk) = YEAR(CURRENT_DATE())";

    $res = $conn->query($sql);
    return $res->fetch_assoc()['total'] ?? 0;
}

$pemasukan_hari  = hitungOmzet($conn, 'hari_ini', $selected_outlet);
$pemasukan_bulan = hitungOmzet($conn, 'bulan_ini', $selected_outlet);
$pemasukan_tahun = hitungOmzet($conn, 'tahun_ini', $selected_outlet);

// Hitung Piutang
$sql_piutang = "SELECT SUM(grand_total) as total FROM transaksi WHERE status_pembayaran = 'Belum Lunas'";
if ($selected_outlet > 0) $sql_piutang .= " AND id_outlet = '$selected_outlet'";
$total_piutang = $conn->query($sql_piutang)->fetch_assoc()['total'] ?? 0;


// --- 4. SIAPKAN DATA UNTUK GRAFIK GARIS (DINAMIS SESUAI FILTER) ---
$chart_labels = [];
$chart_data = [];

$sql_chart = "SELECT $select_date_chart, SUM(grand_total) as total 
              FROM transaksi 
              WHERE status_pembayaran = 'Lunas' $date_filter_sql";
if ($selected_outlet > 0) $sql_chart .= " AND id_outlet = '$selected_outlet'";
$sql_chart .= " " . $group_by_sql_chart;

$res_chart = $conn->query($sql_chart);
while ($row_c = $res_chart->fetch_assoc()) {
    $chart_labels[] = $row_c['tgl'];
    $chart_data[] = $row_c['total'];
}
$json_labels = json_encode($chart_labels);
$json_data = json_encode($chart_data);


// --- 5. SIAPKAN DATA PIE CHART (DINAMIS SESUAI FILTER) ---
$pie_labels = [];
$pie_data = [];
$sql_pie = "SELECT l.nama_layanan, SUM(td.qty) as total_qty 
            FROM transaksi_detail td 
            JOIN transaksi t ON td.id_transaksi = t.id_transaksi 
            JOIN layanan l ON td.id_layanan = l.id_layanan 
            WHERE t.status_pembayaran = 'Lunas' $date_filter_sql";
if ($selected_outlet > 0) $sql_pie .= " AND t.id_outlet = '$selected_outlet'";
$sql_pie .= " GROUP BY l.id_layanan ORDER BY total_qty DESC LIMIT 5";

$res_pie = $conn->query($sql_pie);
if ($res_pie) {
    while ($row_p = $res_pie->fetch_assoc()) {
        $pie_labels[] = $row_p['nama_layanan'];
        $pie_data[] = $row_p['total_qty'];
    }
}


// --- 6. DATA TABEL & METODE PEMBAYARAN ---
$sql_recent = "SELECT no_invoice, tgl_masuk, grand_total, metode_pembayaran
               FROM transaksi 
               WHERE status_pembayaran = 'Lunas'";
if ($selected_outlet > 0) $sql_recent .= " AND id_outlet = '$selected_outlet'";
$sql_recent .= " ORDER BY tgl_masuk DESC LIMIT 10";
$res_recent = $conn->query($sql_recent);

$sql_metode = "SELECT metode_pembayaran, COUNT(*) as jumlah 
               FROM transaksi 
               WHERE status_pembayaran = 'Lunas' $date_filter_sql";
if ($selected_outlet > 0) $sql_metode .= " AND id_outlet = '$selected_outlet'";
$sql_metode .= " GROUP BY metode_pembayaran";
$res_metode = $conn->query($sql_metode);

$page_title = "Laporan Keuangan";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-primary fw-bold"><i class="fas fa-chart-line me-2"></i>Laporan Keuangan</h1>

        <form method="GET" class="d-flex align-items-center bg-white p-2 rounded shadow-sm border border-primary gap-3">
            <div class="d-flex align-items-center">
                <i class="fas fa-store text-primary mr-2"></i>
                <select name="outlet_filter" class="form-select form-select-sm border-0 fw-bold text-primary"
                    onchange="this.form.submit()" style="outline:none; width:auto;"
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
                    <?php if (!$is_locked): ?>
                        <option value="0" <?php echo ($selected_outlet == 0) ? 'selected' : ''; ?>>Semua Cabang (Global)</option>
                        <?php foreach ($outlets as $o): ?>
                            <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($selected_outlet == $o['id_outlet']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['nama_outlet']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option selected>Cabang Anda</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="border-left pl-3 d-flex align-items-center">
                <i class="fas fa-calendar-alt text-success mr-2"></i>
                <select name="periode_filter" class="form-select form-select-sm border-0 fw-bold text-success"
                    onchange="this.form.submit()" style="outline:none; width:auto;">
                    <option value="bulan_ini" <?php echo ($periode_grafik == 'bulan_ini') ? 'selected' : ''; ?>>Bulan Ini (Harian)</option>
                    <option value="tahun_ini" <?php echo ($periode_grafik == 'tahun_ini') ? 'selected' : ''; ?>>Tahun Ini (Bulanan)</option>
                    <option value="semua" <?php echo ($periode_grafik == 'semua') ? 'selected' : ''; ?>>Semua Waktu (Tahunan)</option>
                </select>
            </div>
        </form>
    </div>

    <button type="button" class="btn btn-success shadow-sm fw-bold mb-4" data-bs-toggle="modal" data-bs-target="#modalExport">
        <i class="fas fa-file-excel me-1"></i> Export Excel
    </button>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($pemasukan_hari, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-day fa-2x text-primary opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Bulan Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($pemasukan_bulan, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-calendar-alt fa-2x text-info opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-bottom-primary shadow h-100 py-2" style="border-left: 4px solid #2c3e50;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Tahun Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($pemasukan_tahun, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-dark opacity-25"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Piutang (Belum Lunas)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">Rp <?php echo number_format($total_piutang, 0, ',', '.'); ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-danger opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo $judul_grafik; ?></h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 350px;">
                        <canvas id="myAreaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary">Rincian 10 Transaksi Terakhir (Lunas)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                            <thead class="bg-light text-primary">
                                <tr>
                                    <th class="ps-3">Invoice / Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Metode</th>
                                    <th class="text-end pe-3">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $res_recent->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark"><?php echo $row['no_invoice']; ?></div>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($row['tgl_masuk'])); ?></small>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo ($row['metode_pembayaran'] == 'Tunai') ? 'bg-secondary' : 'bg-info'; ?>">
                                                <?php echo $row['metode_pembayaran']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3 fw-bold text-success">Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary">Layanan Terlaris (Filter: <?php echo ucwords(str_replace('_', ' ', $periode_grafik)); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($pie_labels)): ?>
                        <div class="text-center text-muted py-4">Belum ada data detail cucian untuk periode ini.</div>
                    <?php else: ?>
                        <div class="chart-pie" style="height: 250px;">
                            <canvas id="myPieChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white border-bottom-primary">
                    <h6 class="m-0 font-weight-bold text-primary">Distribusi Pembayaran</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php
                        $total_all = 0;
                        $data_metode = [];
                        while ($m = $res_metode->fetch_assoc()) {
                            $total_all += $m['jumlah'];
                            $data_metode[] = $m;
                        }

                        foreach ($data_metode as $dm):
                            $persen = ($total_all > 0) ? round(($dm['jumlah'] / $total_all) * 100) : 0;
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                <div>
                                    <i class="fas fa-wallet me-2 text-primary"></i>
                                    <span class="fw-bold text-dark"><?php echo $dm['metode_pembayaran']; ?></span>
                                </div>
                                <span class="badge bg-primary rounded-pill"><?php echo $persen; ?>%</span>
                            </div>
                            <div class="progress mb-3" style="height: 5px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $persen; ?>%;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleFilter(val) {
        document.getElementById('customDate').style.display = (val === 'custom') ? 'block' : 'none';
    }

    Chart.defaults.font.family = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.color = '#858796';

    function number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(',', '').replace(' ', '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? '.' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? ',' : dec_point,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }

    // GRAFIK GARIS (PENDAPATAN)
    const ctx = document.getElementById('myAreaChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo $json_labels; ?>,
                datasets: [{
                    label: "Pendapatan",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "#2c3e50",
                    pointHoverBorderColor: "#2c3e50",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: <?php echo $json_data; ?>,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleColor: '#6e707e',
                        titleFont: {
                            size: 14
                        },
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'Rp ' + number_format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            callback: function(value) {
                                return 'Rp ' + number_format(value);
                            }
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    }
                }
            }
        });
    }

    // GRAFIK DOUGHNUT (LAYANAN TERLARIS)
    const ctxPie = document.getElementById("myPieChart");
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pie_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pie_data); ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: true,
                    }
                },
                cutout: '70%',
            },
        });
    }
</script>