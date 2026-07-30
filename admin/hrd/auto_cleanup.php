<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
    checkAuth([1, 2]);
}
$conn = connectDB();
$batas_hari = 30;
$tanggal_kadaluarsa = date('Y-m-d', strtotime("-$batas_hari days"));

$stmt_cari = $conn->prepare("SELECT id_absensi, foto_masuk FROM absensi 
    WHERE tanggal < ? AND foto_masuk IS NOT NULL AND foto_masuk != '' AND foto_masuk != 'kadaluarsa'");
$stmt_cari->bind_param("s", $tanggal_kadaluarsa);
$stmt_cari->execute();
$hasil_sampah = $stmt_cari->get_result();

if ($hasil_sampah && $hasil_sampah->num_rows > 0) {
    while ($row = $hasil_sampah->fetch_assoc()) {
        $id_absensi = $row['id_absensi'];
        $nama_file = $row['foto_masuk'];
        $path_file = dirname(__DIR__, 2) . "/uploads/absensi/" . $nama_file;

        if (file_exists($path_file)) {
            unlink($path_file);
        }

        $stmt_update = $conn->prepare("UPDATE absensi SET foto_masuk = 'kadaluarsa' WHERE id_absensi = ?");
        $stmt_update->bind_param("i", $id_absensi);
        $stmt_update->execute();
    }
}
