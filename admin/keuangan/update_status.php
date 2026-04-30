<?php
session_start();
require_once '../../includes/config.php';
checkAuth([1, 4]);

$conn = connectDB();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_transaksi = $_POST['id'];
    $aksi = $_POST['aksi'];
    $waktu_sekarang = date('Y-m-d H:i:s');

    try {
        if ($aksi == 'lunasi') {
            $bayar = $_POST['bayar_susulan'] ?? 0;
            // UPDATE: Set lunas, tgl_bayar, dan nominal bayar
            $sql = "UPDATE transaksi SET 
                    status_pembayaran = 'Lunas', 
                    tgl_bayar = ?, 
                    bayar = bayar + ?, 
                    kembalian = (bayar + ?) - grand_total 
                    WHERE id_transaksi = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sddi", $waktu_sekarang, $bayar, $bayar, $id_transaksi);
            $tipe_struk = "deposit"; // Tetap deposit karena hanya pelunasan nota lama
        } 
        elseif ($aksi == 'ambil') {
            // UPDATE: Set status laundry menjadi Diambil
            $sql = "UPDATE transaksi SET status_laundry = 'Diambil' WHERE id_transaksi = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_transaksi);
            $tipe_struk = "ambil"; // Memicu struk pengambilan
        }

        if ($stmt->execute()) {
            // Redirect kembali ke POS dengan parameter sukses dan tipe struk
            header("Location: transaksi.php?status=success&id=$id_transaksi&tipe=$tipe_struk");
            exit;
        }
    } catch (Exception $e) {
        header("Location: transaksi.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}