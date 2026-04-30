<?php
// Catatan: File ini tidak perlu 'require config.php' 
// karena nanti akan kita 'include' ke dalam file yang sudah memanggil config.

// 1. Tentukan batas umur foto (Misal: 30 hari)
$batas_hari = 30;
$tanggal_kadaluarsa = date('Y-m-d', strtotime("-$batas_hari days"));

// 2. Cari semua absen yang tanggalnya lebih lama dari 30 hari DAN masih punya file foto
$sql_cari_sampah = "SELECT id_absensi, foto_masuk FROM absensi 
                    WHERE tanggal < '$tanggal_kadaluarsa' 
                    AND foto_masuk IS NOT NULL 
                    AND foto_masuk != '' 
                    AND foto_masuk != 'kadaluarsa'";

$hasil_sampah = $conn->query($sql_cari_sampah);

if ($hasil_sampah && $hasil_sampah->num_rows > 0) {
    while ($row = $hasil_sampah->fetch_assoc()) {

        $id_absensi = $row['id_absensi'];
        $nama_file = $row['foto_masuk'];
        $path_file = "../../uploads/absensi/" . $nama_file;

        // 3. Eksekusi Hapus File Fisik dari Folder Server (Gunakan fungsi unlink)
        if (file_exists($path_file)) {
            unlink($path_file); // <-- INI ADALAH FUNGSI PHP UNTUK MENGHAPUS FILE
        }

        // 4. Update database: Beri tanda bahwa foto sudah dibersihkan
        // (Agar script tidak mencari dan mencoba menghapus file ini lagi di kemudian hari)
        $conn->query("UPDATE absensi SET foto_masuk = 'kadaluarsa' WHERE id_absensi = $id_absensi");
    }
}
