<?php
require_once '../includes/config.php';
checkAuth([1, 2]);

$id_user = (int)($_GET['id_user'] ?? 0);
$bulan = date('m');
$tahun = date('Y');

if (empty($id_user)) {
    echo '<div class="alert alert-danger">ID User tidak valid.</div>';
    exit;
}

$conn = connectDB();
$sql = "SELECT a.*, k.nama_lengkap 
        FROM absensi a
        JOIN karyawan k ON a.id_user = k.id_user
        WHERE a.id_user = ? AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
        ORDER BY a.tanggal DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $id_user, $bulan, $tahun);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

if (empty($list)) {
    echo '<div class="alert alert-info text-center py-3">Belum ada data absensi untuk karyawan ini pada periode berjalan.</div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="bg-light">
            <tr>
                <th>Tanggal</th>
                <th>Masuk & Foto GPS</th>
                <th>Pulang & Foto GPS</th>
                <th>Status Audit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $row): ?>
            <tr>
                <td class="align-middle font-weight-bold"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                <td>
                    <div><small class="text-success font-weight-bold"><i class="fas fa-clock"></i> <?php echo $row['waktu_masuk'] ? date('H:i:s', strtotime($row['waktu_masuk'])) : '-'; ?></small></div>
                    <?php if (!empty($row['foto_masuk'])): ?>
                        <a href="../<?php echo htmlspecialchars($row['foto_masuk']); ?>" target="_blank">
                            <img src="../<?php echo htmlspecialchars($row['foto_masuk']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;" class="mt-1 shadow-sm">
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($row['lat_masuk'])): ?>
                        <div><small class="text-muted"><i class="fas fa-map-marker-alt text-danger"></i> <?php echo $row['lat_masuk'] . ', ' . $row['long_masuk']; ?></small></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div><small class="text-warning font-weight-bold"><i class="fas fa-clock"></i> <?php echo $row['waktu_pulang'] ? date('H:i:s', strtotime($row['waktu_pulang'])) : '-'; ?></small></div>
                    <?php if (!empty($row['foto_pulang'])): ?>
                        <a href="../<?php echo htmlspecialchars($row['foto_pulang']); ?>" target="_blank">
                            <img src="../<?php echo htmlspecialchars($row['foto_pulang']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px;" class="mt-1 shadow-sm">
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($row['lat_pulang'])): ?>
                        <div><small class="text-muted"><i class="fas fa-map-marker-alt text-danger"></i> <?php echo $row['lat_pulang'] . ', ' . $row['long_pulang']; ?></small></div>
                    <?php endif; ?>
                </td>
                <td class="align-middle">
                    <span class="badge bg-<?php echo ($row['status_verifikasi'] == 'valid') ? 'success' : 'danger'; ?>">
                        <?php echo strtoupper($row['status_verifikasi']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
