<?php
require_once '../includes/config.php';
// Hanya HRD/Owner yang bisa memproses absensi
checkAuth([1, 2]); 

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $conn = connectDB();
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'] ?? 0;
    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');
    $success_msg = "Absensi berhasil dicatat.";
    
    try {
        if ($action == 'masuk') {
            // Cek apakah sudah absensi masuk hari ini
            $sql_check = "SELECT id FROM absensi WHERE user_id = ? AND tanggal = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $user_id, $tanggal);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("Karyawan sudah absensi masuk hari ini.");
            }
            $stmt_check->close();

            // INSERT Absensi Masuk
            $sql = "INSERT INTO absensi (user_id, tanggal, waktu_masuk) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $tanggal, $waktu);
            
        } elseif ($action == 'pulang') {
            // UPDATE Absensi Pulang
            // Pastikan dia sudah masuk hari ini
            $sql = "UPDATE absensi SET waktu_pulang = ? WHERE user_id = ? AND tanggal = ? AND waktu_pulang IS NULL";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sis", $waktu, $user_id, $tanggal);
            $success_msg = "Absensi pulang berhasil dicatat.";
            
        } else {
            throw new Exception("Aksi tidak valid.");
        }
        
        if ($stmt && !$stmt->execute()) {
             throw new Exception("Gagal eksekusi database: " . $stmt->error);
        }
        $stmt->close();
        
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = $success_msg;
        
    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Gagal: " . $e->getMessage();
    } finally {
        $conn->close();
    }
    
    // Redirect kembali ke halaman daftar karyawan
    header("Location: kelola_karyawan.php");
    exit;
}