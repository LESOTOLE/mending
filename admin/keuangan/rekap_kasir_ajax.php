<?php
session_start();
require_once '../../includes/config.php';

// Pastikan hanya admin/owner/kasir yang bisa akses
checkAuth([1, 4]);

$conn = connectDB();
$outlet_id = isset($_POST['outlet_id']) ? (int)$_POST['outlet_id'] : 0;
$today = date('Y-m-d');

// 1. Ambil nama outlet
$outlet_name = "Semua Outlet";
if ($outlet_id > 0) {
    $stmt_outlet = $conn->prepare("SELECT nama_outlet FROM outlets WHERE id_outlet = ?");
    $stmt_outlet->bind_param("i", $outlet_id);
    $stmt_outlet->execute();
    $res_outlet = $stmt_outlet->get_result();
    if ($row_out = $res_outlet->fetch_assoc()) $outlet_name = $row_out['nama_outlet'];
}

// 2. Hitung Rekap Transaksi Hari Ini berdasarkan metode_pembayaran
$stmt_tunai = $conn->prepare("SELECT SUM(bayar) as total FROM transaksi WHERE id_outlet = ? AND metode_pembayaran = 'Tunai' AND DATE(tgl_masuk) = ?");
$stmt_tunai->bind_param("is", $outlet_id, $today);
$stmt_tunai->execute();
$tunai = $stmt_tunai->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_transfer = $conn->prepare("SELECT SUM(bayar) as total FROM transaksi WHERE id_outlet = ? AND metode_pembayaran = 'Transfer' AND DATE(tgl_masuk) = ?");
$stmt_transfer->bind_param("is", $outlet_id, $today);
$stmt_transfer->execute();
$transfer = $stmt_transfer->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_qris = $conn->prepare("SELECT SUM(bayar) as total FROM transaksi WHERE id_outlet = ? AND metode_pembayaran = 'QRIS' AND DATE(tgl_masuk) = ?");
$stmt_qris->bind_param("is", $outlet_id, $today);
$stmt_qris->execute();
$qris = $stmt_qris->get_result()->fetch_assoc()['total'] ?? 0;

$stmt_count = $conn->prepare("SELECT COUNT(*) as jml_transaksi FROM transaksi WHERE id_outlet = ? AND DATE(tgl_masuk) = ?");
$stmt_count->bind_param("is", $outlet_id, $today);
$stmt_count->execute();
$jml_trx = $stmt_count->get_result()->fetch_assoc()['jml_transaksi'] ?? 0;

$total_semua = $tunai + $transfer + $qris;

$conn->close();

function rp($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}
?>

<div class="text-center mb-4">
    <h5 class="fw-bold text-primary mb-1">Rekap Shift Kasir</h5>
    <div class="text-muted small">Outlet: <b><?php echo htmlspecialchars($outlet_name); ?></b></div>
    <div class="text-muted small">Tanggal: <b><?php echo date('d M Y'); ?></b></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6">
        <div class="border rounded p-3 text-center bg-light">
            <h6 class="text-muted mb-1 small">Total Transaksi</h6>
            <h4 class="fw-bold text-dark m-0"><?php echo $jml_trx; ?></h4>
        </div>
    </div>
    <div class="col-6">
        <div class="border rounded p-3 text-center bg-light">
            <h6 class="text-muted mb-1 small">Total Pendapatan</h6>
            <h4 class="fw-bold text-success m-0"><?php echo rp($total_semua); ?></h4>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-bold text-primary py-3">
        <i class="fas fa-money-bill-wave me-2"></i> Rincian Metode Pembayaran
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <tbody>
                <tr>
                    <td class="ps-3 py-3 align-middle"><i class="fas fa-qrcode text-warning fa-fw me-2"></i> QRIS</td>
                    <td class="text-end pe-3 py-3 fw-bold"><?php echo rp($qris); ?></td>
                </tr>
                <tr>
                    <td class="ps-3 py-3 align-middle"><i class="fas fa-exchange-alt text-info fa-fw me-2"></i> Transfer Bank</td>
                    <td class="text-end pe-3 py-3 fw-bold"><?php echo rp($transfer); ?></td>
                </tr>
                <tr class="table-primary">
                    <td class="ps-3 py-3 align-middle">
                        <i class="fas fa-wallet text-primary fa-fw me-2"></i> 
                        <span class="fw-bold text-primary">UANG TUNAI (CASH) LACI</span>
                    </td>
                    <td class="text-end pe-3 py-3 text-primary h5 fw-bold mb-0"><?php echo rp($tunai); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-warning d-flex align-items-center" role="alert">
    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
    <div>
        <strong>Perhatian Kasir!</strong><br>
        Silakan hitung uang fisik di laci kasir. Uang tunai harus berjumlah tepat <b><?php echo rp($tunai); ?></b> (ditambah dengan saldo modal awal jika ada).
    </div>
</div>
