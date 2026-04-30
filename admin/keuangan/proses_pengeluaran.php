<?php
session_start();
require_once '../../includes/config.php';

// Pastikan hanya Owner (1) yang bisa akses
checkAuth([1]);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = connectDB();
    $action = $_POST['action'] ?? '';

    // Ambil data (Logika penangkapan ganda untuk mendukung form tambah & edit)
    $tanggal    = $_POST['tanggal'] ?? $_POST['tanggal_pengeluaran'] ?? date('Y-m-d');
    $id_outlet  = $_POST['id_outlet'] ?? $_POST['outlet_id'] ?? 0;
    $id_kat     = $_POST['id_kategori'] ?? $_POST['kategori_id'] ?? 0;
    $keterangan = trim($_POST['deskripsi'] ?? $_POST['keterangan'] ?? '');
    $nominal    = $_POST['jumlah'] ?? $_POST['nominal'] ?? 0;
    $id_user    = $_SESSION['id_user']; 

    try {
        // Validasi Dasar
        if (($action == 'tambah_pengeluaran' || $action == 'edit') && (empty($keterangan) || $nominal <= 0)) {
            throw new Exception("Jumlah atau Deskripsi tidak boleh kosong.");
        }

        if ($action == 'tambah_pengeluaran') {
            $sql = "INSERT INTO pengeluaran (id_outlet, id_user, id_kategori, nominal, keterangan, tanggal) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiidss", $id_outlet, $id_user, $id_kat, $nominal, $keterangan, $tanggal);

        } elseif ($action == 'edit') {
            $id_pengeluaran = $_POST['pengeluaran_id'];
            $sql = "UPDATE pengeluaran SET 
                        id_kategori = ?, 
                        id_outlet = ?, 
                        tanggal = ?, 
                        nominal = ?, 
                        keterangan = ? 
                    WHERE id_pengeluaran = ?"; // Pakai id_pengeluaran
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisdsi", $id_kat, $id_outlet, $tanggal, $nominal, $keterangan, $id_pengeluaran);

        } elseif ($action == 'hapus') {
            $id_pengeluaran = $_POST['pengeluaran_id'];
            $sql = "DELETE FROM pengeluaran WHERE id_pengeluaran = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id_pengeluaran);
        }

        if ($stmt->execute()) {
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Data pengeluaran berhasil diproses!';
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal: ' . $e->getMessage();
    }

    $conn->close();
    header("Location: pengeluaran.php");
    exit;
}