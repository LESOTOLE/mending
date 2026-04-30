<?php
require_once '../../includes/config.php';

// Pastikan ada request POST dari AJAX
if (!isset($_POST['keyword']) || !isset($_POST['outlet_id'])) {
    exit;
}

$conn = connectDB();
$keyword = '%' . trim($_POST['keyword']) . '%';
$outlet_id = (int)$_POST['outlet_id'];

// Query mencari di tabel transaksi & pelanggan (Berdasarkan No Invoice, Nama, atau No HP)
$sql = "SELECT t.*, p.nama_pelanggan, p.no_hp 
        FROM transaksi t
        JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
        WHERE t.id_outlet = ? 
        AND (t.no_invoice LIKE ? OR p.nama_pelanggan LIKE ? OR p.no_hp LIKE ?)
        ORDER BY t.tgl_masuk DESC LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $outlet_id, $keyword, $keyword, $keyword);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger text-center shadow-sm">
            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
            Tidak ada nota atau pelanggan yang cocok dengan pencarian Anda.
          </div>';
    exit;
}
?>

<div class="list-group shadow-sm">
    <?php while($row = $result->fetch_assoc()): ?>
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            
            <div>
                <h6 class="mb-1 fw-bold text-primary">
                    <i class="fas fa-receipt me-1"></i> <?php echo $row['no_invoice']; ?>
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
                    <?php if(!empty($row['lokasi_rak'])): ?>
                        <span class="badge bg-info text-dark"><i class="fas fa-box"></i> Rak: <?php echo $row['lokasi_rak']; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-end" style="min-width: 100px;">
                <?php if($row['status_pembayaran'] != 'Lunas'): ?>
                    <?php $sisa_bayar = $row['grand_total'] - $row['bayar']; ?>
                    <button class="btn btn-sm btn-danger mb-1 w-100 fw-bold shadow-sm" 
                            onclick="window.aksiLunasi(<?php echo $row['id_transaksi']; ?>, '<?php echo addslashes($row['nama_pelanggan']); ?>', <?php echo $sisa_bayar; ?>)">
                        <i class="fas fa-money-bill-wave"></i> Lunasi
                    </button>
                <?php endif; ?>
                
                <?php if($row['status_laundry'] != 'Diambil'): ?>
                    <button class="btn btn-sm btn-success mb-1 w-100 fw-bold shadow-sm" 
                            onclick="window.aksiAmbil(<?php echo $row['id_transaksi']; ?>, '<?php echo addslashes($row['nama_pelanggan']); ?>')">
                        <i class="fas fa-box-open"></i> Ambil
                    </button>
                <?php endif; ?>
                
                <?php if($row['status_laundry'] == 'Diambil'): ?>
                    <button class="btn btn-sm btn-outline-primary w-100 fw-bold" 
                            onclick="window.open('cetak_struk.php?id=<?php echo $row['id_transaksi']; ?>', '_blank')">
                        <i class="fas fa-print"></i> Struk
                    </button>
                <?php endif; ?>
            </div>
            
        </div>
    <?php endwhile; ?>
</div>