<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/config.php';
checkAuth([1, 2]); // Owner & HRD

$conn = connectDB();
$page_title = "Riwayat Transaksi";

// PARAMETER FILTER
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');
$filter_outlet = $_GET['outlet'] ?? 0; // 0 = Semua

// Ambil list outlet
$outlets = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");

// QUERY UTAMA (JOIN ke users, karyawan, outlets)
// PERBAIKAN: Menghapus koma terakhir sebelum FROM dan menghapus JOIN ke pelanggan
$sql = "SELECT t.*, 
               k.nama_lengkap as nama_pic, 
               o.nama_outlet
        FROM transaksi t 
        LEFT JOIN users u ON t.id_user = u.id_user 
        LEFT JOIN karyawan k ON u.id_user = k.id_user 
        LEFT JOIN outlets o ON t.id_outlet = o.id_outlet
        WHERE DATE(t.tgl_masuk) BETWEEN ? AND ?";

$types = "ss";
$params = [$start_date, $end_date];

if ($filter_outlet > 0) {
    $sql .= " AND t.id_outlet = ?";
    $types .= "i";
    $params[] = $filter_outlet;
}

$sql .= " ORDER BY t.tgl_masuk DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

include '../../includes/header.php';
?>

<style>
    .table-header-blue {
        background-color: #4e73df;
        color: white;
    }
</style>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-primary fw-bold"><i class="fas fa-history me-2"></i>Riwayat Transaksi</h1>

    <div class="card shadow mb-4 border-top-primary">
        <div class="card-body bg-white">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-primary">Dari Tanggal</label>
                    <input type="date" name="start" class="form-control border-primary" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-primary">Sampai Tanggal</label>
                    <input type="date" name="end" class="form-control border-primary" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-primary">Cabang / Outlet</label>
                    <select name="outlet" class="form-select border-primary">
                        <option value="0">-- SEMUA CABANG --</option>
                        <?php foreach ($outlets as $o): ?>
                            <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($filter_outlet == $o['id_outlet']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($o['nama_outlet']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="fas fa-filter me-1"></i> Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0" width="100%" cellspacing="0">
                    <thead class="table-header-blue text-center">
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Invoice</th>
                            <th>Cabang</th>
                            <th>PIC (Karyawan)</th>
                            <th>Total Tagihan</th>
                            <th>Status Pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center align-middle">
                                        <div class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($row['tgl_masuk'])); ?></div>
                                        <small class="text-muted"><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($row['tgl_masuk'])); ?></small>
                                    </td>

                                    <td class="align-middle text-center">
                                        <span class="fw-bold text-primary" style="font-family: monospace; font-size: 1.1em;"><?php echo $row['no_invoice']; ?></span>
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="badge bg-light text-primary border border-primary rounded-pill px-3 py-2">
                                            <i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($row['nama_outlet']); ?>
                                        </span>
                                    </td>

                                    <td class="text-center align-middle">
                                        <?php if (!empty($row['nama_pic'])): ?>
                                            <div class="d-flex align-items-center justify-content-center">
                                                <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 25px; height: 25px; font-size: 10px;">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <span class="small fw-bold text-dark"><?php echo explode(' ', $row['nama_pic'])[0]; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end align-middle px-3">
                                        <span class="fw-bold text-dark">Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></span>
                                    </td>

                                    <td class="text-center align-middle">
                                        <?php if ($row['status_pembayaran'] == 'Lunas'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="fas fa-check-circle me-1"></i> Lunas</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="fas fa-times-circle me-1"></i> Belum Lunas</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center align-middle">
                                        <button onclick="window.open('cetak_struk.php?id=<?php echo $row['id_transaksi']; ?>','_blank')" class="btn btn-sm btn-outline-primary shadow-sm" title="Cetak Struk">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted font-italic bg-light">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-gray-300"></i><br>
                                    Tidak ada data transaksi pada periode ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>