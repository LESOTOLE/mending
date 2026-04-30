<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/header.php';

$conn = connectDB();

// Proteksi Sesi & Identitas
$role_id      = $_SESSION['id_role'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'User';
$outlet_id    = $_SESSION['id_outlet'] ?? 0;

// Set Nama Role
$roles = [1 => "Owner", 2 => "HRD", 4 => "Admin Outlet"];
$role_name = $roles[$role_id] ?? "Karyawan";

// Inisialisasi variabel statistik
$jum_baru = $jum_cuci = $jum_siap = $jum_hutang = 0;
$chart_labels = [];
$chart_data = [];
$id_user = $_SESSION['id_user'];
$hari_ini = date('Y-m-d');
$bulan_ini = date('m');
$tahun_ini = date('Y');

// 1. Cek Status Absen Hari Ini
$cek_absen = $conn->query("SELECT waktu_masuk FROM absensi WHERE id_user = $id_user AND tanggal = '$hari_ini'");
$sudah_absen = ($cek_absen->num_rows > 0);
$waktu_masuk_hari_ini = $sudah_absen ? $cek_absen->fetch_assoc()['waktu_masuk'] : '--:--';

// 2. Hitung Total Hadir Bulan Ini
$q_total_hadir = $conn->query("SELECT COUNT(id_absensi) as total FROM absensi WHERE id_user = $id_user AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini'");
$total_hadir = $q_total_hadir->fetch_assoc()['total'];

// 3. Ambil 5 Riwayat Absen Terakhir
$q_riwayat = $conn->query("SELECT tanggal, waktu_masuk FROM absensi WHERE id_user = $id_user ORDER BY tanggal DESC LIMIT 5");

// =================================================================================
// LOGIKA 1: DASHBOARD OPERASIONAL (Owner & Admin Outlet)
// =================================================================================
if ($role_id == 1 || $role_id == 4) {
    $where_outlet = ($role_id == 1) ? "WHERE 1=1" : "WHERE id_outlet = " . (int)$outlet_id;

    // 1. Ambil Angka Statistik
    $jum_baru = $conn->query("SELECT COUNT(*) as t FROM transaksi $where_outlet AND status_laundry = 'Baru'")->fetch_assoc()['t'] ?? 0;
    $jum_cuci = $conn->query("SELECT COUNT(*) as t FROM transaksi $where_outlet AND status_laundry = 'Dicuci'")->fetch_assoc()['t'] ?? 0;
    $jum_siap = $conn->query("SELECT COUNT(*) as t FROM transaksi $where_outlet AND (status_laundry = 'Selesai' OR status_laundry = 'Siap Diambil')")->fetch_assoc()['t'] ?? 0;
    $jum_hutang = $conn->query("SELECT COUNT(*) as t FROM transaksi $where_outlet AND status_pembayaran = 'Belum Lunas'")->fetch_assoc()['t'] ?? 0;

    // 2. Transaksi Terakhir (Tanpa JOIN pelanggan, ambil dari catatan)
    $res_recent = $conn->query("SELECT no_invoice, status_laundry, grand_total FROM transaksi $where_outlet ORDER BY id_transaksi DESC LIMIT 5");

    // 3. Data Pendapatan 7 Hari Terakhir
    $sql_chart = "SELECT DATE(tgl_masuk) as tgl, SUM(grand_total) as total
FROM transaksi $where_outlet
AND tgl_masuk >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(tgl_masuk) ORDER BY tgl ASC";
    $res_chart = $conn->query($sql_chart);
    while ($row = $res_chart->fetch_assoc()) {
        $chart_labels[] = date('d/m', strtotime($row['tgl']));
        $chart_data[] = (int)$row['total'];
    }
}

// =================================================================================
// LOGIKA 2: DASHBOARD HRD (Role 2)
// =================================================================================
if ($role_id == 2) {
    $tgl_hari_ini = date('Y-m-d');

    // 1. Total Staf Aktif (Role 3 & 4) - Menggunakan id_user sebagai kunci
    $sql_count = "SELECT COUNT(DISTINCT k.id_user) as t
FROM karyawan k
JOIN users u ON k.id_user = u.id_user
JOIN user_roles ur ON u.id_user = ur.id_user
WHERE u.is_active = 1 AND ur.id_role IN (3, 4)";
    $total_staff = $conn->query($sql_count)->fetch_assoc()['t'] ?? 0;

    // 2. Total Hadir (Gunakan id_user atau kolom yang benar di tabel absensi)
    $sql_absen = "SELECT COUNT(DISTINCT id_user) as t FROM absensi WHERE DATE(waktu_masuk) = '$tgl_hari_ini'";
    $total_hadir = $conn->query($sql_absen)->fetch_assoc()['t'] ?? 0;

    // 3. List Staf Terbaru
    $sql_staff = "SELECT k.nama_lengkap, o.nama_outlet, r.nama_role as role_name
FROM karyawan k
JOIN outlets o ON k.id_outlet = o.id_outlet
JOIN users u ON k.id_user = u.id_user
JOIN user_roles ur ON u.id_user = ur.id_user
JOIN roles r ON ur.id_role = r.id_role
WHERE r.id_role IN (3, 4)
ORDER BY k.id_user DESC LIMIT 5";
    $res_staff = $conn->query($sql_staff);
    // --- DATA GRAFIK GAJI (6 BULAN TERAKHIR) ---
    // --- LOGIKA GRAFIK GAJI HRD ---
    $chart_gaji_labels = [];
    $chart_gaji_data = [];

    // Gunakan 'deskripsi' sebagai kolom standar pengeluaran
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
        // Data default jika belum ada input gaji
        $chart_gaji_labels = [date('M Y')];
        $chart_gaji_data = [0];
    }

    $json_gaji_labels = json_encode($chart_gaji_labels);
    $json_gaji_data = json_encode($chart_gaji_data);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Dashboard <?php echo $role_name; ?></h1>
    </div>

    <div class="alert alert-white shadow-sm border-start border-primary border-4 bg-white py-3 mb-4">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0 ms-2"><i class="fas fa-user-circle fa-2x text-primary"></i></div>
            <div class="flex-grow-1 ms-3">
                <div class="small text-muted text-uppercase fw-bold">Selamat Datang Kembali</div>
                <div class="h5 mb-0 fw-bold text-dark"><?php echo htmlspecialchars($nama_lengkap); ?></div>
            </div>
        </div>
    </div>

    <?php if ($role_id == 1 || $role_id == 4): ?>
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
                                            <td class="ps-3 fw-bold text-dark"><?php echo $row['no_invoice']; ?></td>
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
    <?php endif; ?>

    <?php if ($role_id == 2): ?>
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
                                <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $total_hadir; ?> / <?php echo $total_staff; ?> Staf</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
    <?php endif; ?>

    <?php if ($role_id == 3): ?>

        <style>
            .welcome-banner {
                background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
                border-radius: 15px;
                color: white;
            }

            .welcome-icon {
                font-size: 5rem;
                opacity: 0.2;
                position: absolute;
                right: 30px;
                bottom: -10px;
            }

            .card-absen {
                transition: transform 0.2s;
            }

            .card-absen:hover {
                transform: translateY(-5px);
            }
        </style>

        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Dashboard Karyawan</h1>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow border-0 welcome-banner position-relative overflow-hidden">
                        <div class="card-body p-4">
                            <h2 class="font-weight-bold mb-1">Halo, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Karyawan'); ?>! 👋</h2>
                            <p class="mb-0 opacity-75">Selamat datang di panel kerja Anda. Jangan lupa untuk selalu memberikan pelayanan terbaik hari ini!</p>
                            <i class="fas fa-tshirt welcome-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-5 col-lg-5 mb-4">
                    <div class="card shadow h-100 border-bottom-primary card-absen">
                        <div class="card-header py-3 bg-white text-center border-0">
                            <h6 class="m-0 font-weight-bold text-primary text-uppercase">Status Kehadiran Hari Ini</h6>
                            <small class="text-muted"><?php echo date('d F Y'); ?></small>
                        </div>
                        <div class="card-body text-center d-flex flex-column justify-content-center">

                            <?php if ($sudah_absen): ?>
                                <div class="mb-4">
                                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 80px; height: 80px;">
                                        <i class="fas fa-check fa-3x"></i>
                                    </div>
                                    <h4 class="text-success font-weight-bold">SUDAH ABSEN</h4>
                                    <p class="text-muted mb-0">Jam Masuk: <span class="badge bg-primary text-white p-2"><?php echo $waktu_masuk_hari_ini; ?></span></p>
                                </div>
                                <button class="btn btn-light border btn-block text-muted" disabled>
                                    <i class="fas fa-check-circle mr-2"></i> Kehadiran Tercatat
                                </button>
                            <?php else: ?>
                                <div class="mb-4">
                                    <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 80px; height: 80px;">
                                        <i class="fas fa-fingerprint fa-3x"></i>
                                    </div>
                                    <h4 class="text-warning font-weight-bold">BELUM ABSEN</h4>
                                    <p class="text-muted mb-0">Silakan lakukan absensi kamera.</p>
                                </div>
                                <a href="staff/absensi.php" class="btn btn-primary btn-lg btn-block shadow-sm font-weight-bold rounded-pill">
                                    <i class="fas fa-camera mr-2"></i> ABSEN SEKARANG
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

                <div class="col-xl-7 col-lg-7 mb-4">
                    <div class="card border-left-success shadow py-2 mb-4">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Hadir (Bulan Ini)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $total_hadir; ?> <span class="h6 font-weight-normal text-muted">Hari</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-success opacity-25"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header py-3 bg-white d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-history mr-2 text-primary"></i>5 Riwayat Kehadiran Terakhir</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0 text-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-3">Tanggal</th>
                                            <th>Jam Masuk</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($q_riwayat->num_rows > 0): ?>
                                            <?php while ($row = $q_riwayat->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="ps-3 font-weight-bold text-dark"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                                    <td><?php echo $row['waktu_masuk']; ?></td>
                                                    <td><span class="badge bg-success text-white"><i class="fas fa-check mr-1"></i>Hadir</span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-3 text-muted">Belum ada riwayat kehadiran.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="accordionSidebar" style="display:none;"></div>
    <button id="sidebarToggle" style="display:none;"></button>
</div>

<?php if (in_array($role_id, [1, 2, 4])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // ==========================================
        // SCRIPT GRAFIK UNTUK OWNER & ADMIN OUTLET
        // ==========================================
        <?php if ($role_id == 1 || $role_id == 4): ?>
            const ctx = document.getElementById('revenueChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'Pendapatan',
                            data: <?php echo json_encode($chart_data); ?>,
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#4e73df'
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
                                    color: '#f8f9fc'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        <?php endif; ?>

        // ==========================================
        // SCRIPT GRAFIK UNTUK HRD
        // ==========================================
        <?php if ($role_id == 2): ?>
            const ctxGaji = document.getElementById('salaryChart');
            if (ctxGaji) {
                new Chart(ctxGaji, {
                    type: 'bar',
                    data: {
                        labels: <?php echo $json_gaji_labels; ?>,
                        datasets: [{
                            label: "Total Gaji",
                            backgroundColor: "#1cc88a", // Ubah jadi hijau biar beda dengan owner
                            hoverBackgroundColor: "#17a673",
                            borderColor: "#1cc88a",
                            data: <?php echo $json_gaji_data; ?>,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
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
        <?php endif; ?>
    </script>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>