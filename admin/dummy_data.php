<?php
require_once '../includes/config.php';
$conn = connectDB();

echo "<h2>Mulai membuat data dummy dengan Lokasi Rak...</h2>";

// Konfigurasi Batch
$outlets = [1, 2];
$jumlah_per_outlet = 100;
$berhasil = 0;

// Daftar kemungkinan lokasi rak
$area_rak = ['A', 'B', 'C', 'D', 'E'];
$nomor_rak = range(1, 15);

foreach ($outlets as $id_outlet) {
    for ($i = 1; $i <= $jumlah_per_outlet; $i++) {

        $id_user = 1;
        $id_pelanggan = rand(1, 10);

        $no_invoice = "INV-" . date("Ymd") . "-" . str_pad(rand(1, 9999), 4, "0", STR_PAD_LEFT);

        $hari_mundur = rand(0, 365);
        $tgl_masuk = date("Y-m-d H:i:s", strtotime("-$hari_mundur days"));

        $grand_total = rand(15, 250) * 1000;
        $bayar = $grand_total;

        // Acak Status
        $arr_status_laundry = ['Baru', 'Dicuci', 'Selesai', 'Diambil'];
        $status_laundry = $arr_status_laundry[array_rand($arr_status_laundry)];

        // LOGIKA RAK: Hanya berikan rak jika statusnya 'Selesai' atau 'Diambil'
        // Jika masih 'Baru' atau 'Dicuci', rak biasanya NULL atau kosong.
        if (in_array($status_laundry, ['Selesai', 'Diambil'])) {
            $lokasi_rak = $area_rak[array_rand($area_rak)] . "-" . $nomor_rak[array_rand($nomor_rak)];
        } else {
            $lokasi_rak = "";
        }

        $status_pembayaran = 'Lunas';
        $arr_metode = ['Tunai', 'Transfer', 'QRIS'];
        $metode_pembayaran = $arr_metode[array_rand($arr_metode)];

        // Masukkan ke Database (Termasuk kolom lokasi_rak)
        $sql = "INSERT INTO transaksi 
                (id_outlet, id_user, id_pelanggan, no_invoice, tgl_masuk, grand_total, bayar, status_laundry, status_pembayaran, metode_pembayaran, lokasi_rak) 
                VALUES 
                ($id_outlet, $id_user, $id_pelanggan, '$no_invoice', '$tgl_masuk', $grand_total, $bayar, '$status_laundry', '$status_pembayaran', '$metode_pembayaran', '$lokasi_rak')";

        if ($conn->query($sql)) {
            $berhasil++;
        }
    }
}

echo "<h1 style='color: green;'>Sukses! $berhasil data transaksi (dengan Lokasi Rak) berhasil ditambahkan.</h1>";
echo "<a href='../admin/dashboard.php'>Cek Hasil di Dashboard</a>";
