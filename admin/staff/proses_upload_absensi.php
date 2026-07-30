<?php
session_start();
require_once '../../includes/config.php';

// 1. CEK LOGIN (Keamanan)
if (!isset($_SESSION['is_login'])) {
    header("Location: ../../admin/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = connectDB();

    $id_user = (int)$_SESSION['id_user'];
    $action  = $_POST['action_type']; // 'masuk' atau 'pulang'
    $today   = date('Y-m-d');

    // PERUBAHAN 1: Gunakan format lengkap Tanggal + Jam karena tipe data DATETIME
    $now     = date('Y-m-d H:i:s');

    // ==========================================================
    // 2. VALIDASI LOGIKA (Mencegah Duplikat)
    // ==========================================================

    // PERBAIKAN: Gunakan prepared statement
    $stmt_cek = $conn->prepare("SELECT * FROM absensi WHERE id_user = ? AND tanggal = ?");
    $stmt_cek->bind_param("is", $id_user, $today);
    $stmt_cek->execute();
    $cek = $stmt_cek->get_result();
    $data_ada = $cek->fetch_assoc();

    if ($action == 'masuk') {
        if ($cek->num_rows > 0) {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Anda sudah melakukan absen masuk hari ini!';
            header("Location: absensi.php");
            exit;
        }
    } elseif ($action == 'pulang') {
        if ($cek->num_rows == 0) {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Anda belum absen masuk, tidak bisa absen pulang!';
            header("Location: absensi.php");
            exit;
        }
        // Cek kolom waktu_pulang (sekarang DATETIME, jika NULL berarti belum pulang)
        if ($data_ada['waktu_pulang'] != NULL) {
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Anda sudah absen pulang sebelumnya!';
            header("Location: absensi.php");
            exit;
        }
    } else {
        header("Location: absensi.php");
        exit;
    }

    // ==========================================================
    // 3. PROSES UPLOAD FOTO
    // ==========================================================

    if (!isset($_FILES['foto_absensi']) || $_FILES['foto_absensi']['error'] != 0) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal mengupload foto (File Error).';
        header("Location: absensi.php");
        exit;
    }

    // PERBAIKAN: Validasi ukuran file (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($_FILES['foto_absensi']['size'] > $max_size) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Ukuran foto maksimal 5MB.';
        header("Location: absensi.php");
        exit;
    }

    $target_dir = "../../uploads/absensi/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true); // PERBAIKAN: 0755 bukan 0777
    }

    $file_ext = strtolower(pathinfo($_FILES["foto_absensi"]["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];

    if (!in_array($file_ext, $allowed)) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Format foto harus JPG atau PNG.';
        header("Location: absensi.php");
        exit;
    }

    // PERBAIKAN: Validasi MIME type sebenarnya
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES['foto_absensi']['tmp_name']);
    finfo_close($finfo);
    $allowed_mimes = ['image/jpeg', 'image/png'];
    if (!in_array($mime_type, $allowed_mimes)) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Tipe file tidak valid. Hanya JPG/PNG yang diperbolehkan.';
        header("Location: absensi.php");
        exit;
    }

    // Nama file: absen_TIPE_ID_TIMESTAMP.jpg
    $new_filename = "absen_" . $action . "_" . $id_user . "_" . time() . "." . $file_ext;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES["foto_absensi"]["tmp_name"], $target_file)) {

        // ==========================================================
        // 4. SIMPAN KE DATABASE (DISESUAIKAN DENGAN GAMBAR)
        // ==========================================================
        $success = false;

        if ($action == 'masuk') {
            // PERUBAHAN 2: Hapus kolom 'status' karena tidak ada di tabel.
            // Gunakan $now (DATETIME) untuk waktu_masuk
            $sql = "INSERT INTO absensi (id_user, tanggal, waktu_masuk, foto_masuk) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $id_user, $today, $now, $new_filename);

            if ($stmt->execute()) $success = true;
        } elseif ($action == 'pulang') {
            // UPDATE untuk Absen Pulang
            // Gunakan $now (DATETIME) untuk waktu_pulang
            $sql = "UPDATE absensi SET waktu_pulang = ?, foto_pulang = ? WHERE id_user = ? AND tanggal = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssis", $now, $new_filename, $id_user, $today);

            if ($stmt->execute()) $success = true;
        }

        if ($success) {
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Berhasil melakukan absensi ' . strtoupper($action);
        } else {
            // Cleanup jika gagal insert DB
            if (file_exists($target_file)) unlink($target_file);

            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Database Error: ' . $stmt->error;
        }
    } else {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal memindahkan file ke folder upload.';
    }

    $conn->close();
    header("Location: absensi.php");
    exit;
}
