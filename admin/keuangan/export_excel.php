<?php
session_start();
require_once '../../includes/config.php';
$conn = connectDB();

$range = $_GET['range'] ?? 'bulan';
$outlet_id = $_GET['outlet_id'] ?? 0;
$filename = "Laporan_Keuangan_" . date('Y-m-d') . ".xls";

// Logika Query Berdasarkan Range
$sql = "SELECT t.*, o.nama_outlet FROM transaksi t 
        JOIN outlets o ON t.id_outlet = o.id_outlet 
        WHERE t.status_pembayaran = 'Lunas'";

if ($outlet_id > 0) $sql .= " AND t.id_outlet = '$outlet_id'";

if ($range == 'minggu') {
    $sql .= " AND t.tgl_masuk >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($range == 'bulan') {
    $sql .= " AND MONTH(t.tgl_masuk) = MONTH(CURDATE()) AND YEAR(t.tgl_masuk) = YEAR(CURDATE())";
} elseif ($range == 'tahun') {
    $sql .= " AND YEAR(t.tgl_masuk) = YEAR(CURDATE())";
} elseif ($range == 'custom') {
    $start = $_GET['start'];
    $end = $_GET['end'];
    $sql .= " AND DATE(t.tgl_masuk) BETWEEN '$start' AND '$end'";
}

$sql .= " ORDER BY t.tgl_masuk DESC";
$res = $conn->query($sql);

// Header HTTP untuk memaksa download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename");
header("Pragma: no-cache");
header("Expires: 0");
?>

<table border="1">
    <tr>
        <th colspan="7" style="background-color: #4e73df; color: white;">LAPORAN KEUANGAN MENDING LAUNDRY</th>
    </tr>
    <tr>
        <th>Tanggal</th>
        <th>No. Invoice</th>
        <th>Cabang</th>
        <th>Keterangan (Pelanggan)</th>
        <th>Metode Bayar</th>
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
        <td><?php echo $row['no_invoice']; ?></td>
        <td><?php echo $row['nama_outlet']; ?></td>
        <td><?php echo $row['catatan'] ?? 'Umum'; ?></td>
        <td><?php echo $row['metode_pembayaran']; ?></td>
        <td><?php echo $row['status_laundry']; ?></td>
        <td align="right"><?php echo $row['grand_total']; ?></td>
    </tr>
    <?php endwhile; ?>
    <tr>
        <th colspan="6" align="right">TOTAL PENDAPATAN</th>
        <th align="right"><?php echo $total_omzet; ?></th>
    </tr>
</table>