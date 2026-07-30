<?php
session_start();
require_once '../../includes/config.php';

// PERBAIKAN: Tambahkan auth check — hanya Owner yang bisa export
checkAuth([1, 2, 4]);

$conn = connectDB();

$range = $_GET['range'] ?? 'bulan';
$outlet_id = (int)($_GET['outlet_id'] ?? 0);
$filename = "Laporan_Keuangan_" . date('Y-m-d') . ".xls";

// PERBAIKAN: Gunakan prepared statement untuk semua parameter
$sql = "SELECT t.*, o.nama_outlet, p.nama_pelanggan 
        FROM transaksi t 
        JOIN outlets o ON t.id_outlet = o.id_outlet 
        LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
        WHERE t.status_pembayaran = 'Lunas'";
$params = [];
$types = "";

if ($outlet_id > 0) {
    $sql .= " AND t.id_outlet = ?";
    $params[] = $outlet_id;
    $types .= "i";
}

if ($range == 'minggu') {
    $sql .= " AND t.tgl_masuk >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($range == 'bulan') {
    $sql .= " AND MONTH(t.tgl_masuk) = MONTH(CURDATE()) AND YEAR(t.tgl_masuk) = YEAR(CURDATE())";
} elseif ($range == 'tahun') {
    $sql .= " AND YEAR(t.tgl_masuk) = YEAR(CURDATE())";
} elseif ($range == 'custom') {
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    // Validasi format tanggal
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $sql .= " AND DATE(t.tgl_masuk) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    }
}

$sql .= " ORDER BY t.tgl_masuk DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Header HTTP untuk memaksa download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <tr>
        <th colspan="7" style="background-color: #4e73df; color: white;">LAPORAN KEUANGAN MENDING LAUNDRY</th>
        <th colspan="9" style="background-color: #4e73df; color: white;">LAPORAN KEUANGAN MENDING LAUNDRY</th>
    </tr>
    <tr>
        <th>Tanggal</th>
        <th>No. Invoice</th>
        <th>Cabang</th>
        <th>Keterangan (Pelanggan)</th>
        <th>Total Harga</th>
        <th>Diskon</th>
        <th>Grand Total</th>
        <th>Status Laundry</th>
        <th>Total (Rp)</th>
    </tr>
    <?php 
    $total_omzet = 0;
    while($row = $res->fetch_assoc()): 
        $total_omzet += $row['grand_total'];
    ?>
    <tr>
        <td><?php echo date('d/m/Y H:i', strtotime($row['tgl_masuk'])); ?></td>
        <td><?php echo htmlspecialchars($row['no_invoice']); ?></td>
        <td><?php echo htmlspecialchars($row['nama_outlet']); ?></td>
        <td><?php echo htmlspecialchars($row['nama_pelanggan'] ?? 'Umum'); ?></td>
        <td align="right"><?php echo $row['total_harga']; ?></td>
        <td align="right"><?php echo $row['diskon'] ?? 0; ?></td>
        <td align="right"><?php echo $row['grand_total']; ?></td>
        <td><?php echo htmlspecialchars($row['status_laundry']); ?></td>
        <td align="right"><?php echo $row['grand_total']; ?></td>
    </tr>
    <?php endwhile; ?>
    <tr>
        <th colspan="8" align="right">TOTAL PENDAPATAN</th>
        <th align="right"><?php echo $total_omzet; ?></th>
    </tr>
</table>
<?php
$stmt->close();
$conn->close();