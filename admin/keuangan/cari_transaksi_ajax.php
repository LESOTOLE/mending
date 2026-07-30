<?php
require_once '../../includes/config.php';

// PERBAIKAN: Tambahkan auth check
checkAuth([1, 4]);

// Pastikan ada request POST dari AJAX
if (!isset($_POST['keyword']) || !isset($_POST['outlet_id'])) {
    exit;
}

$conn = connectDB();
$keyword = trim($_POST['keyword']);
$outlet_id = (int)$_POST['outlet_id'];

if (empty($keyword)) {
    // 1. JIKA KOSONG: Tampilkan List Default (Cucian yang Belum Diambil)
    $sql = "SELECT t.*, p.nama_pelanggan, p.no_hp 
            FROM transaksi t
            JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
            WHERE t.id_outlet = ? AND t.status_laundry != 'Diambil'
            ORDER BY t.tgl_masuk DESC LIMIT 15";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $outlet_id);
} else {
    // 2. JIKA ADA KEYWORD: Lakukan pencarian spesifik
    $keyword_param = '%' . $keyword . '%';
    $sql = "SELECT t.*, p.nama_pelanggan, p.no_hp 
            FROM transaksi t
            JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
            WHERE t.id_outlet = ? 
            AND (t.no_invoice LIKE ? OR p.nama_pelanggan LIKE ? OR p.no_hp LIKE ?)
            ORDER BY t.tgl_masuk DESC LIMIT 15";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $outlet_id, $keyword_param, $keyword_param, $keyword_param);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger text-center shadow-sm mt-3">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
            Tidak ada nota atau pelanggan yang cocok.
          </div>';
    exit;
}
?>

<div class="list-group shadow-sm mt-2">
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">

            <div>
                <h6 class="mb-1 fw-bold text-primary">
                    <i class="fas fa-receipt me-1"></i> <?php echo htmlspecialchars($row['no_invoice']); ?>
                </h6>
                <small class="text-dark fw-bold">
                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($row['nama_pelanggan']); ?>
                    <span class="text-muted">(<?php echo htmlspecialchars($row['no_hp'] ?? '-'); ?>)</span>
                </small><br>

                <small class="text-muted">Total: Rp <?php echo number_format($row['grand_total'], 0, ',', '.'); ?></small><br>

                <div class="mt-1">
                    <span class="badge bg-<?php echo $row['status_laundry'] == 'Selesai' || $row['status_laundry'] == 'Diambil' ? 'success' : 'warning text-dark'; ?>">
                        <?php echo $row['status_laundry']; ?>
                    </span>
                    <span class="badge bg-<?php echo $row['status_pembayaran'] == 'Lunas' ? 'success' : 'danger'; ?>">
                        <?php echo $row['status_pembayaran']; ?>
                    </span>
                    <?php if (!empty($row['lokasi_rak'])): ?>
                        <span class="badge bg-info text-dark"><i class="fas fa-box"></i> Rak: <?php echo htmlspecialchars($row['lokasi_rak']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-end" style="min-width: 100px;">
                <!-- KONDISI A: Cucian Belum Selesai -->
                <?php if (in_array($row['status_laundry'], ['Baru', 'Dicuci', 'Diproses'])): ?>
                    <button class="btn btn-sm btn-warning mb-1 w-100 fw-bold shadow-sm text-dark"
                        onclick="window.aksiAturRak(<?php echo $row['id_transaksi']; ?>, '<?php echo addslashes($row['nama_pelanggan']); ?>')">
                        <i class="fas fa-check-circle"></i> Selesai & Rak
                    </button>
                <?php endif; ?>

                <!-- KONDISI B: Cucian Selesai, Belum Lunas -->
                <?php if ($row['status_laundry'] == 'Selesai' && $row['status_pembayaran'] != 'Lunas'): ?>
                    <?php $sisa_bayar = $row['grand_total'] - $row['bayar']; ?>
                    <button class="btn btn-sm btn-danger mb-1 w-100 fw-bold shadow-sm"
                        onclick="window.aksiLunasi(<?php echo $row['id_transaksi']; ?>, '<?php echo addslashes($row['nama_pelanggan']); ?>', <?php echo $sisa_bayar; ?>)">
                        <i class="fas fa-money-bill-wave"></i> Lunasi
                    </button>
                <?php endif; ?>

                <!-- KONDISI C: Cucian Selesai, Sudah Lunas -->
                <?php if ($row['status_laundry'] == 'Selesai' && $row['status_pembayaran'] == 'Lunas'): ?>
                    <button class="btn btn-sm btn-success mb-1 w-100 fw-bold shadow-sm"
                        onclick="window.aksiAmbil(<?php echo $row['id_transaksi']; ?>, '<?php echo addslashes($row['nama_pelanggan']); ?>')">
                        <i class="fas fa-box-open"></i> Ambil
                    </button>
                <?php endif; ?>

                <!-- TOMBOL STRUK (Hanya Muncul Jika Sudah Diambil) -->
                <?php if ($row['status_laundry'] == 'Diambil'): ?>
                    <button class="btn btn-sm btn-outline-primary w-100 fw-bold"
                        onclick="window.open('cetak_struk.php?id=<?php echo $row['id_transaksi']; ?>', '_blank')">
                        <i class="fas fa-print"></i> Struk
                    </button>
                <?php endif; ?>
            </div>

        </div>
    <?php endwhile; ?>
</div>