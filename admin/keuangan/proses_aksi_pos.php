<?php
session_start();
require_once '../../includes/config.php';

// Pastikan hanya admin/owner/kasir yang bisa akses
checkAuth([1, 4]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = connectDB();

    $aksi = $_POST['aksi'] ?? '';
    // Deteksi transaksi baru dari kasir (AJAX)
    if (isset($_POST['items_data'])) {
        $aksi = 'transaksi_baru';
    }

    $id_transaksi = isset($_POST['id_transaksi']) ? (int)$_POST['id_transaksi'] : 0;

    try {
        // =========================================================
        // JALUR 1: TRANSAKSI BARU (DARI POS KASIR) -> PAKE AJAX/JSON
        // =========================================================
        if ($aksi == 'transaksi_baru') {
            $conn->begin_transaction();

            $id_outlet = $_POST['outlet_id'];
            $id_pelanggan = (int)$_POST['id_pelanggan_lama'];
            $total_harga = (float)$_POST['total_harga'];
            $bayar = (float)$_POST['jumlah_bayar'];
            $kembalian = (float)$_POST['kembalian'];
            $status_pembayaran = $_POST['status_pembayaran'];
            $metode_pembayaran = $_POST['metode_pembayaran'];
            $lokasi_rak = $_POST['lokasi_rak'];
            $id_user = (int)$_POST['id_user_kasir'];
            $tgl_masuk = date('Y-m-d H:i:s');
            $no_invoice = 'INV-' . date('YmdHis') . rand(10, 99);

            $stmt = $conn->prepare("INSERT INTO transaksi (no_invoice, id_outlet, id_pelanggan, id_user, tgl_masuk, total_harga, grand_total, bayar, kembalian, metode_pembayaran, lokasi_rak, status_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Perhatikan perubahannya: Tambah 4 huruf 'd' (Double/Float) di tengah untuk nominal uang
            $stmt->bind_param("siiisddddsss", $no_invoice, $id_outlet, $id_pelanggan, $id_user, $tgl_masuk, $total_harga, $total_harga, $bayar, $kembalian, $metode_pembayaran, $lokasi_rak, $status_pembayaran);

            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan data transaksi utama: " . $stmt->error);
            }
            $id_transaksi_baru = $conn->insert_id;

            $items = json_decode($_POST['items_data'], true);
            $stmtDetail = $conn->prepare("INSERT INTO transaksi_detail (id_transaksi, id_layanan, qty, harga, subtotal) VALUES (?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $id_layanan = (int)$item['id'];
                $harga = (float)$item['harga'];
                $qty = (int)$item['qty'];
                $subtotal = $harga * $qty;
                $stmtDetail->bind_param("iiddd", $id_transaksi_baru, $id_layanan, $qty, $harga, $subtotal);
                if (!$stmtDetail->execute()) throw new Exception("Gagal menyimpan detail layanan: " . $stmtDetail->error);
            }

            $conn->commit();
            $conn->close();

            // Balas dengan JSON
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'pesan' => 'Transaksi Berhasil Disimpan!', 'id_transaksi' => $id_transaksi_baru]);
            exit;

            // =========================================================
            // JALUR 2: LUNASI & AMBIL BUKAN AJAX -> PAKE REDIRECT
            // =========================================================
        } elseif ($aksi == 'lunasi') {
            $bayar_susulan = (float)$_POST['bayar_susulan'];
            $stmt = $conn->prepare("UPDATE transaksi SET bayar = bayar + ?, status_pembayaran = 'Lunas' WHERE id_transaksi = ?");
            $stmt->bind_param("di", $bayar_susulan, $id_transaksi);

            if ($stmt->execute()) {
                $_SESSION['pos_status'] = 'success';
                $_SESSION['pos_id_transaksi'] = $id_transaksi;
            } else {
                throw new Exception("Gagal melunasi tagihan.");
            }

            $conn->close();
            header("Location: transaksi.php");
            exit;
        } elseif ($aksi == 'ambil') {
            $stmt = $conn->prepare("UPDATE transaksi SET status_laundry = 'Diambil' WHERE id_transaksi = ?");
            $stmt->bind_param("i", $id_transaksi);

            if ($stmt->execute()) {
                $_SESSION['pos_status'] = 'success';
                $_SESSION['pos_id_transaksi'] = $id_transaksi;
            } else {
                throw new Exception("Gagal update status pengambilan.");
            }

            $conn->close();
            header("Location: transaksi.php");
            exit;
        }
    } catch (Exception $e) {
        if ($aksi == 'transaksi_baru' && isset($conn)) $conn->rollback();
        $conn->close();

        // Cara penanganan Error-nya juga dibagi 2
        if ($aksi == 'transaksi_baru') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
        } else {
            $_SESSION['pos_status'] = 'error';
            $_SESSION['pos_msg'] = $e->getMessage();
            header("Location: transaksi.php");
        }
        exit;
    }
}
