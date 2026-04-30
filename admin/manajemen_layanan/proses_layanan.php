<?php
session_start();
require_once '../../includes/config.php';
checkAuth([1]); // Hanya Owner

$conn = connectDB();

// ==========================================================
// 1. TANGKAP REQUEST DARI FORM (METODE POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // --- A. PROSES TAMBAH LAYANAN ---
    if ($action == 'create') {
        $id_outlet = (int)$_POST['outlet_id'];
        $nama      = trim($_POST['nama_layanan']);
        $satuan    = trim($_POST['satuan']);
        $harga     = $_POST['harga_per_satuan'];
        $estimasi  = (int)$_POST['estimasi_durasi'];

        $stmt = $conn->prepare("INSERT INTO layanan (id_outlet, nama_layanan, satuan, harga, estimasi_jam) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issdi", $id_outlet, $nama, $satuan, $harga, $estimasi);

        if ($stmt->execute()) {
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Layanan baru berhasil ditambahkan.';
        } else {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Gagal menambah layanan: ' . $conn->error;
        }
        header("Location: layanan.php");
        exit;

        // --- B. PROSES EDIT LAYANAN ---
    } elseif ($action == 'update') {
        $id        = (int)$_POST['id'];
        $id_outlet = (int)$_POST['outlet_id'];
        $nama      = trim($_POST['nama_layanan']);
        $satuan    = trim($_POST['satuan']);
        $harga     = $_POST['harga_per_satuan'];
        $estimasi  = (int)$_POST['estimasi_durasi'];

        $stmt = $conn->prepare("UPDATE layanan SET id_outlet=?, nama_layanan=?, satuan=?, harga=?, estimasi_jam=? WHERE id_layanan=?");
        $stmt->bind_param("issdii", $id_outlet, $nama, $satuan, $harga, $estimasi, $id);

        if ($stmt->execute()) {
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Data layanan berhasil diperbarui.';
        } else {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Gagal memperbarui layanan: ' . $conn->error;
        }
        header("Location: layanan.php");
        exit;

        // --- C. PROSES SALIN LAYANAN MASSAL ---
    } elseif ($action == 'copy_services') {
        $source = (int)$_POST['source_outlet'];
        $target = (int)$_POST['target_outlet'];

        if ($source === $target) {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Cabang Sumber dan Tujuan tidak boleh sama!';
        } else {
            // Query untuk menjiplak data dari cabang sumber ke cabang tujuan
            $sql_copy = "INSERT INTO layanan (id_outlet, nama_layanan, satuan, harga, estimasi_jam)
                         SELECT $target, nama_layanan, satuan, harga, estimasi_jam 
                         FROM layanan 
                         WHERE id_outlet = $source";

            if ($conn->query($sql_copy)) {
                $_SESSION['form_status'] = 'success';
                $_SESSION['form_message'] = "Semua layanan berhasil disalin!";
            } else {
                $_SESSION['form_status'] = 'error';
                $_SESSION['form_message'] = "Gagal menyalin: " . $conn->error;
            }
        }
        header("Location: layanan.php");
        exit;
    }
}

// ==========================================================
// 2. TANGKAP REQUEST DARI URL (METODE GET) UNTUK HAPUS
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = (int)$_GET['id'];

    try {
        $stmt = $conn->prepare("DELETE FROM layanan WHERE id_layanan = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Layanan berhasil dihapus.';
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal dihapus! Layanan ini mungkin sudah tercatat di nota transaksi.';
    }
    header("Location: layanan.php");
    exit;
}

// Jika ada yang mengakses file ini langsung tanpa lewat form, kembalikan ke halaman layanan
header("Location: layanan.php");
exit;
