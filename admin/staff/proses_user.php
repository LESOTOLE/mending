<?php
require_once '../../includes/config.php';
checkAuth([1, 2]); // Hanya Owner & HRD

// PERBAIKAN: Menerima data dari POST (sesuai form di kelola_karyawan.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manajemen_user.php");
    exit;
}

// PERBAIKAN: Verifikasi CSRF token
if (!verifyCsrfToken()) {
    $_SESSION['form_status'] = 'error';
    $_SESSION['form_message'] = 'Sesi tidak valid. Silakan coba lagi.';
    header("Location: manajemen_user.php");
    exit;
}

$conn = connectDB();
// PERBAIKAN: Gunakan 'id_role' sesuai session key yang disimpan di login.php
$my_role_id = $_SESSION['id_role'] ?? 0;
// PERBAIKAN: Ambil dari POST, bukan GET
$action = $_POST['action'] ?? '';
$target_user_id = (int)($_POST['id_user'] ?? 0);

if ($action == 'delete' && $target_user_id > 0) {
    
    // 1. CEK ROLE TARGET (User yang mau dihapus itu role-nya apa?)
    $sql_cek = "SELECT u.id_role as role_id 
                FROM users u 
                WHERE u.id_user = ?";
    $stmt = $conn->prepare($sql_cek);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $target = $res->fetch_assoc();
    $stmt->close();
    
    if (!$target) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'User tidak ditemukan.';
        header("Location: manajemen_user.php");
        $conn->close();
        exit;
    }

    $target_role = $target['role_id'];

    // 2. LOGIKA KEAMANAN HAPUS
    $is_allowed = false;

    if ($my_role_id == 1) {
        // OWNER: Boleh hapus HRD (2), Karyawan (3), Toko (4)
        if (in_array($target_role, [2, 3, 4])) {
            $is_allowed = true;
        }
    } elseif ($my_role_id == 2) {
        // HRD: HANYA BOLEH hapus Karyawan (3)
        if ($target_role == 3) {
            $is_allowed = true;
        }
    }

    // 3. EKSEKUSI JIKA DIIZINKAN
    if ($is_allowed) {
        $conn->begin_transaction();
        try {
            // PERBAIKAN: Semua query menggunakan prepared statements
            $stmt1 = $conn->prepare("DELETE FROM karyawan WHERE id_user = ?");
            $stmt1->bind_param("i", $target_user_id);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $conn->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt2->bind_param("i", $target_user_id);
            $stmt2->execute();
            $stmt2->close();
            
            $conn->commit();
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = 'Akun berhasil dihapus.';
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = 'Gagal menghapus: Terjadi kesalahan database.';
        }
    } else {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Anda tidak memiliki izin menghapus akun level ini.';
    }
}

$conn->close();
// Redirect kembali berdasarkan role
if ($my_role_id == 2) {
    header("Location: ../kelola_karyawan.php");
} else {
    header("Location: manajemen_user.php");
}
exit;
?>