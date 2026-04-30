<?php
require_once '../../includes/config.php'; 

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh mengakses.
checkAuth([1, 2]); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = connectDB();
    $absensi_id = (int)$_POST['absensi_id'] ?? 0;
    $action_type = $_POST['action_type'] ?? ''; // 'masuk' atau 'pulang'
    $filter_date = $_POST['filter_date'] ?? date('Y-m-d'); // Tanggal redirect
    
    try {
        if ($absensi_id <= 0 || !in_array($action_type, ['masuk', 'pulang'])) {
            throw new Exception("Data konfirmasi tidak valid.");
        }

        // Tentukan kolom yang akan di-update
        $column_to_update = ($action_type == 'masuk') ? 'is_masuk_confirmed' : 'is_pulang_confirmed';
        
        // Query UPDATE: Set status konfirmasi menjadi 1 (Dikonfirmasi)
        $sql = "UPDATE absensi SET {$column_to_update} = 1 WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $absensi_id);

        if (!$stmt->execute()) {
             throw new Exception("Gagal memperbarui status konfirmasi: " . $stmt->error);
        }
        $stmt->close();
        
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = "Verifikasi Absensi **" . strtoupper($action_type) . "** berhasil disahkan!";
        
    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Gagal Konfirmasi: " . $e->getMessage();
    } finally {
        $conn->close();
    }
    
    // Redirect kembali ke halaman laporan dengan filter tanggal yang sama
    header("Location: laporan_absensi.php?date={$filter_date}");
    exit;
} else {
    header("Location: laporan_absensi.php");
    exit;
}