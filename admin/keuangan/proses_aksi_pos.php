<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/fonnte.php'; // Integrasi Fonnte

// Deteksi apakah ini request AJAX yang mengharapkan JSON
$is_ajax = isset($_POST['items_data']) || (isset($_POST['aksi']) && in_array($_POST['aksi'], ['selesai_rak', 'lunasi', 'ambil']));

if ($is_ajax) {
    if (!isset($_SESSION['id_user']) || !in_array($_SESSION['id_role'] ?? 0, [1, 4])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'pesan' => 'Sesi berakhir atau akses ditolak. Silakan muat ulang halaman.']);
        exit;
    }
} else {
    // Pastikan hanya admin/owner/kasir yang bisa akses (Non-AJAX)
    checkAuth([1, 4]);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken()) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'pesan' => 'Token CSRF tidak valid. Silakan muat ulang halaman.']);
            exit;
        } else {
            $_SESSION['pos_status'] = 'error';
            $_SESSION['pos_msg'] = 'Token CSRF tidak valid.';
            header("Location: transaksi.php");
            exit;
        }
    }

    $conn = connectDB();
    if (!$conn) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'pesan' => 'Gagal terhubung ke database.']);
            exit;
        } else {
            $_SESSION['pos_status'] = 'error';
            $_SESSION['pos_msg'] = 'Gagal terhubung ke database.';
            header("Location: transaksi.php");
            exit;
        }
    }

    $aksi = $_POST['aksi'] ?? '';
    // Deteksi transaksi baru dari kasir (AJAX)
    if (isset($_POST['items_data'])) {
        $aksi = 'transaksi_baru';
    }

    $id_transaksi = isset($_POST['id_transaksi']) ? (int)$_POST['id_transaksi'] : 0;

    try {
        
        if ($aksi == 'transaksi_baru') {
            $id_user = (int)($_POST['id_user_kasir'] ?? 0);
            $auth_pin = trim($_POST['auth_pin'] ?? '');

            if ($id_user === 0 || empty($auth_pin)) {
                throw new Exception("PIC Kasir dan PIN wajib diisi.");
            }

            // Validasi PIN KASIR
            $stmtPin = $conn->prepare("SELECT pin FROM users WHERE id_user = ?");
            $stmtPin->bind_param("i", $id_user);
            $stmtPin->execute();
            $resPin = $stmtPin->get_result();
            if ($rowPin = $resPin->fetch_assoc()) {
                if ((string)$rowPin['pin'] !== (string)$auth_pin) {
                    throw new Exception("PIN KASIR SALAH! Transaksi dibatalkan.");
                }
            } else {
                throw new Exception("Data Kasir tidak ditemukan.");
            }

            $conn->begin_transaction();

            $id_outlet = (int)($_POST['outlet_id'] ?? 0);
            $id_pelanggan = (int)($_POST['id_pelanggan_lama'] ?? 0);
            
            // JIKA PELANGGAN BARU, SIMPAN KE DATABASE
            $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
            $no_hp_pelanggan = trim($_POST['no_hp_pelanggan'] ?? '');
            
            if ($id_pelanggan === 0 && !empty($nama_pelanggan)) {
                // Cek apakah nomor HP sudah ada
                if (!empty($no_hp_pelanggan)) {
                    $cek = $conn->prepare("SELECT id_pelanggan FROM pelanggan WHERE no_hp = ?");
                    $cek->bind_param("s", $no_hp_pelanggan);
                    $cek->execute();
                    $res_cek = $cek->get_result();
                    if ($row_cek = $res_cek->fetch_assoc()) {
                        $id_pelanggan = $row_cek['id_pelanggan'];
                    }
                }
                
                // Jika masih 0, insert baru
                if ($id_pelanggan === 0) {
                    $stmtPel = $conn->prepare("INSERT INTO pelanggan (nama_pelanggan, no_hp) VALUES (?, ?)");
                    $stmtPel->bind_param("ss", $nama_pelanggan, $no_hp_pelanggan);
                    $stmtPel->execute();
                    $id_pelanggan = $conn->insert_id;
                }
            }

            $total_harga = (float)($_POST['total_harga'] ?? 0);
            $diskon = (float)($_POST['diskon'] ?? 0);
            $grand_total = $total_harga - $diskon;
            if ($grand_total < 0) $grand_total = 0;
            
            $bayar = (float)($_POST['jumlah_bayar'] ?? 0);
            $kembalian = (float)($_POST['kembalian'] ?? 0);
            $status_pembayaran = $_POST['status_pembayaran'] ?? 'Belum Lunas';
            $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'Tunai';
            $lokasi_rak = $_POST['lokasi_rak'] ?? '';
            $catatan = trim($_POST['catatan'] ?? '');
            $tgl_masuk = date('Y-m-d H:i:s');
            $no_invoice = 'INV-' . date('YmdHis') . rand(10, 99);

            $stmt = $conn->prepare("INSERT INTO transaksi (no_invoice, id_outlet, id_pelanggan, id_user, tgl_masuk, total_harga, diskon, grand_total, bayar, kembalian, metode_pembayaran, lokasi_rak, catatan, status_pembayaran) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("siiisdddddssss", $no_invoice, $id_outlet, $id_pelanggan, $id_user, $tgl_masuk, $total_harga, $diskon, $grand_total, $bayar, $kembalian, $metode_pembayaran, $lokasi_rak, $catatan, $status_pembayaran);

            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan data transaksi utama: " . $stmt->error);
            }
            $id_transaksi_baru = $conn->insert_id;

            $items = json_decode($_POST['items_data'] ?? '[]', true);
            if (!is_array($items)) $items = [];
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

            // JIKA PELANGGAN LAMA, AMBIL DATA DARI DATABASE UNTUK WA
            if ($id_pelanggan > 0 && empty($no_hp_pelanggan)) {
                $stmtGetPel = $conn->prepare("SELECT nama_pelanggan, no_hp FROM pelanggan WHERE id_pelanggan = ?");
                $stmtGetPel->bind_param("i", $id_pelanggan);
                $stmtGetPel->execute();
                $resGetPel = $stmtGetPel->get_result();
                if ($rowPel = $resGetPel->fetch_assoc()) {
                    $nama_pelanggan = $rowPel['nama_pelanggan'];
                    $no_hp_pelanggan = $rowPel['no_hp'];
                }
            }

            // KIRIM NOTIFIKASI WHATSAPP FONNTE JIKA ADA NO HP
            if (!empty($no_hp_pelanggan)) {
                $pesan_wa = "Halo *$nama_pelanggan*,\n\nTerima kasih telah menggunakan jasa *Mending Laundry*!\n\nNota: *$no_invoice*\nTotal: *Rp " . number_format($total_harga, 0, ',', '.') . "*\nStatus Pembayaran: *$status_pembayaran*\n\nCucian Anda sedang kami proses. Kami akan memberitahu Anda jika sudah selesai.\n\nTerima kasih!";
                sendWhatsAppFonnte($no_hp_pelanggan, $pesan_wa);
            }

            $conn->close();

            // Balas dengan JSON
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'pesan' => 'Transaksi Berhasil Disimpan!', 'id_transaksi' => $id_transaksi_baru]);
            exit;

           
        } elseif ($aksi == 'selesai_rak') {
            $lokasi_rak = trim($_POST['lokasi_rak'] ?? '');
            
            $stmt = $conn->prepare("UPDATE transaksi SET status_laundry = 'Selesai', lokasi_rak = ? WHERE id_transaksi = ?");
            $stmt->bind_param("si", $lokasi_rak, $id_transaksi);

            if ($stmt->execute()) {
                
                // === TAMBAHAN NOTIFIKASI WA ===
                $stmt_hp = $conn->prepare("SELECT p.no_hp, p.nama_pelanggan, t.no_invoice, t.grand_total, t.bayar, t.status_pembayaran FROM transaksi t JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan WHERE t.id_transaksi = ?");
                $stmt_hp->bind_param("i", $id_transaksi);
                $stmt_hp->execute();
                $cek_hp = $stmt_hp->get_result();
                if ($cek_hp && $row_hp = $cek_hp->fetch_assoc()) {
                    if (!empty($row_hp['no_hp'])) {
                        $info_bayar = ($row_hp['status_pembayaran'] == 'Lunas') ? "*Sudah Lunas*" : "*Belum Lunas* (Sisa: Rp " . number_format($row_hp['grand_total'] - $row_hp['bayar'], 0, ',', '.') . ")";
                        $pesan_wa = "Halo *" . $row_hp['nama_pelanggan'] . "*,\n\nKabar gembira! Cucian Anda dengan nota *" . $row_hp['no_invoice'] . "* telah *Selesai* dan siap untuk diambil.\n\nStatus Pembayaran: $info_bayar\n\nSilakan datang ke outlet *Mending Laundry* untuk mengambil cucian Anda. Terima kasih!";
                        sendWhatsAppFonnte($row_hp['no_hp'], $pesan_wa);
                    }
                }
                // === AKHIR NOTIFIKASI WA ===

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'pesan' => 'Status berhasil diubah menjadi Selesai.']);
                exit;
            } else {
                throw new Exception("Gagal menyimpan lokasi rak.");
            }
        } elseif ($aksi == 'lunasi') {
            $bayar_susulan = (float)$_POST['bayar_susulan'];
            $stmt = $conn->prepare("UPDATE transaksi SET bayar = bayar + ?, status_pembayaran = 'Lunas' WHERE id_transaksi = ?");
            $stmt->bind_param("di", $bayar_susulan, $id_transaksi);

            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'pesan' => 'Pembayaran berhasil.']);
                exit;
            } else {
                throw new Exception("Gagal melunasi tagihan.");
            }
        } elseif ($aksi == 'ambil') {
            $stmt = $conn->prepare("UPDATE transaksi SET status_laundry = 'Diambil' WHERE id_transaksi = ?");
            $stmt->bind_param("i", $id_transaksi);

            if ($stmt->execute()) {
                // Ambil No HP untuk WA
                $stmt_hp = $conn->prepare("SELECT p.no_hp, p.nama_pelanggan, t.no_invoice FROM transaksi t JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan WHERE t.id_transaksi = ?");
                $stmt_hp->bind_param("i", $id_transaksi);
                $stmt_hp->execute();
                $cek_hp = $stmt_hp->get_result();
                if ($cek_hp && $row_hp = $cek_hp->fetch_assoc()) {
                    if (!empty($row_hp['no_hp'])) {
                        $pesan_wa = "Halo *" . $row_hp['nama_pelanggan'] . "*,\n\nCucian Anda dengan nota *" . $row_hp['no_invoice'] . "* telah *Selesai* dan *Sudah Diambil*.\n\nTerima kasih atas kepercayaannya pada *Mending Laundry*! Ditunggu kedatangannya kembali.";
                        sendWhatsAppFonnte($row_hp['no_hp'], $pesan_wa);
                    }
                }

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'pesan' => 'Status berhasil diubah menjadi Diambil.']);
                exit;
            } else {
                throw new Exception("Gagal update status pengambilan.");
            }
        }
    } catch (Exception $e) {
        if ($aksi == 'transaksi_baru' && isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }

        // Cara penanganan Error-nya juga dibagi 2
        if ($is_ajax) {
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
