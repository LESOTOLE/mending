<?php
require_once '../includes/config.php';
// Memilih otorisasi untuk Owner (1), HRD (2), dan Staff/Karyawan (3)
checkAuth([1, 2, 3]);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $user_role = $_SESSION['id_role'] ?? 0;
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $is_staff_route = ($user_role == 3 || strpos($referer, 'staff/absen.php') !== false);
    $redirect_url = $is_staff_route ? "staff/absen.php" : "kelola_karyawan.php";

    // Verifikasi CSRF token
    if (!verifyCsrfToken()) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Sesi tidak valid. Silakan coba lagi.';
        header("Location: " . $redirect_url);
        exit;
    }

    $conn = connectDB();
    $action = $_POST['action'];

    // Jika staff (role 3), paksa id_user dari session demi keamanan; jika admin/HRD, dapat memakai $_POST['id_user']
    if ($user_role == 3) {
        $id_user = (int)$_SESSION['id_user'];
    } else {
        $id_user = (int)($_POST['id_user'] ?? $_SESSION['id_user']);
    }

    $foto_base64 = $_POST['foto_base64'] ?? '';
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

    $tanggal = date('Y-m-d');
    $waktu = date('Y-m-d H:i:s');
    $success_msg = "Absensi berhasil dicatat.";

    // Proses dekode Base64 foto jika ada
    $db_foto_path = null;
    if (!empty($foto_base64)) {
        $month_folder = date('Y-m');
        $upload_dir = '../uploads/absensi/' . $month_folder . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $img_parts = explode(',', $foto_base64);
        $decoded_img = base64_decode(end($img_parts));
        if ($decoded_img !== false) {
            $filename = 'absen_' . $action . '_' . $id_user . '_' . time() . '.jpg';
            $filepath = $upload_dir . $filename;
            file_put_contents($filepath, $decoded_img);
            $db_foto_path = 'uploads/absensi/' . $month_folder . '/' . $filename;
        }
    }

    // Format nilai koordinat untuk bind_param
    $lat_val = ($latitude !== null) ? (string)$latitude : null;
    $long_val = ($longitude !== null) ? (string)$longitude : null;

    try {
        if ($action == 'masuk') {
            // Cek apakah sudah absensi masuk hari ini
            $sql_check = "SELECT id_absensi FROM absensi WHERE id_user = ? AND tanggal = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $id_user, $tanggal);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("Karyawan sudah absensi masuk hari ini.");
            }
            $stmt_check->close();

            // INSERT Absensi Masuk dengan foto & titik GPS
            $sql = "INSERT INTO absensi (id_user, tanggal, waktu_masuk, foto_masuk, lat_masuk, long_masuk, status_verifikasi) 
                    VALUES (?, ?, ?, ?, ?, ?, 'valid')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $id_user, $tanggal, $waktu, $db_foto_path, $lat_val, $long_val);

        } elseif ($action == 'pulang') {
            // Pastikan karyawan sudah absen masuk hari ini dan belum absen pulang
            $sql_check = "SELECT id_absensi, waktu_pulang FROM absensi WHERE id_user = ? AND tanggal = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $id_user, $tanggal);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            if ($res_check->num_rows == 0) {
                throw new Exception("Karyawan belum absensi masuk hari ini.");
            }
            $row_absen = $res_check->fetch_assoc();
            if (!is_null($row_absen['waktu_pulang'])) {
                throw new Exception("Karyawan sudah absensi pulang hari ini.");
            }
            $stmt_check->close();

            // UPDATE Absensi Pulang dengan foto & titik GPS
            $sql = "UPDATE absensi 
                    SET waktu_pulang = ?, foto_pulang = ?, lat_pulang = ?, long_pulang = ?, status_verifikasi = 'valid' 
                    WHERE id_user = ? AND tanggal = ? AND waktu_pulang IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssis", $waktu, $db_foto_path, $lat_val, $long_val, $id_user, $tanggal);
            $success_msg = "Absensi pulang berhasil dicatat.";

        } else {
            throw new Exception("Aksi tidak valid.");
        }

        if ($stmt && !$stmt->execute()) {
            throw new Exception("Gagal eksekusi database: " . $stmt->error);
        }
        if ($stmt) {
            $stmt->close();
        }

        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = $success_msg;

    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Gagal: " . $e->getMessage();
    } finally {
        $conn->close();
    }

    header("Location: " . $redirect_url);
    exit;
} else {
    header("Location: dashboard.php");
    exit;
}