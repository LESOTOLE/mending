<?php
require_once '../../includes/config.php';
checkAuth([1, 2]); // Hanya Owner & HRD

$conn = connectDB();
$my_role_id = $_SESSION['role_id'];
$action = $_GET['action'] ?? '';
$target_user_id = $_GET['id'] ?? 0;

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
    
    if (!$target) {
        header("Location: manajemen_user.php?msg=User tidak ditemukan");
        exit;
    }

    $target_role = $target['role_id'];

    // 2. LOGIKA KEAMANAN HAPUS
    $is_allowed = false;

    if ($my_role_id == 1) {
        // OWNER: Boleh hapus HRD (2), Karyawan (3), Toko (4)
        // Tidak boleh hapus Owner lain (1) - opsional, biasanya Owner cuma 1
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
        // Hapus User (Tabel terkait akan ikut terhapus jika pakai ON DELETE CASCADE di database)
        // Jika tidak CASCADE manual, hapus child table dulu (user_roles, karyawan_details)
        
        $conn->begin_transaction();
        try {
            // Hapus Detail
            $conn->query("DELETE FROM karyawan_details WHERE user_id = $target_user_id");
            $conn->query("DELETE FROM karyawan WHERE id_user = $target_user_id");
            // Hapus User Utama
            $conn->query("DELETE FROM users WHERE id_user = $target_user_id");
            
            $conn->commit();
            header("Location: manajemen_user.php?msg=Akun berhasil dihapus.");
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: manajemen_user.php?msg=Gagal menghapus: " . $e->getMessage());
        }
    } else {
        // Jika HRD mencoba hapus Owner/HRD lain
        header("Location: manajemen_user.php?msg=Anda tidak memiliki izin menghapus akun level ini.");
    }

} else {
    header("Location: manajemen_user.php");
}
?>