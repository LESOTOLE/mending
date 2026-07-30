<?php
require_once '../includes/config.php';

// OTORISASI: Semua user yang sudah login boleh akses dashboard
checkAuth([]);

$conn = connectDB();

// Proteksi Sesi & Identitas
$role_id      = $_SESSION['id_role'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'User';
$outlet_id    = (int)($_SESSION['id_outlet'] ?? 0);

// Set Nama Role
$roles = [1 => "Owner", 2 => "HRD", 4 => "Admin Outlet"];
$role_name = $roles[$role_id] ?? "Karyawan";

$id_user = $_SESSION['id_user'];
$hari_ini = date('Y-m-d');
$bulan_ini = date('m');
$tahun_ini = date('Y');

// 1. Cek Status Absen Hari Ini (PREPARED STATEMENT)
$stmt_absen = $conn->prepare("SELECT waktu_masuk FROM absensi WHERE id_user = ? AND tanggal = ?");
$stmt_absen->bind_param("is", $id_user, $hari_ini);
$stmt_absen->execute();
$cek_absen = $stmt_absen->get_result();
$sudah_absen = ($cek_absen->num_rows > 0);
$waktu_masuk_hari_ini = $sudah_absen ? $cek_absen->fetch_assoc()['waktu_masuk'] : '--:--';
$stmt_absen->close();

// 2. Hitung Total Hadir Bulan Ini (PREPARED STATEMENT)
$stmt_hadir = $conn->prepare("SELECT COUNT(id_absensi) as total FROM absensi WHERE id_user = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt_hadir->bind_param("iss", $id_user, $bulan_ini, $tahun_ini);
$stmt_hadir->execute();
$total_hadir = $stmt_hadir->get_result()->fetch_assoc()['total'];
$stmt_hadir->close();

// 3. Ambil 5 Riwayat Absen Terakhir (PREPARED STATEMENT)
$stmt_riwayat = $conn->prepare("SELECT tanggal, waktu_masuk FROM absensi WHERE id_user = ? ORDER BY tanggal DESC LIMIT 5");
$stmt_riwayat->bind_param("i", $id_user);
$stmt_riwayat->execute();
$q_riwayat = $stmt_riwayat->get_result();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Dashboard <?php echo $role_name; ?></h1>
    </div>

    <?php if ($role_id != 3): // Karyawan (3) has a custom banner ?>
    <div class="alert alert-white shadow-sm border-start border-primary border-4 bg-white py-3 mb-4">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0 ms-2"><i class="fas fa-user-circle fa-2x text-primary"></i></div>
            <div class="flex-grow-1 ms-3">
                <div class="small text-muted text-uppercase fw-bold">Selamat Datang Kembali</div>
                <div class="h5 mb-0 fw-bold text-dark"><?php echo htmlspecialchars($nama_lengkap); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    // Include komponen dashboard sesuai role
    if ($role_id == 1 || $role_id == 4) {
        require_once 'dashboard/owner.php';
    } elseif ($role_id == 2) {
        require_once 'dashboard/hrd.php';
    } elseif ($role_id == 3) {
        require_once 'dashboard/karyawan.php';
    }
    ?>

    <div id="accordionSidebar" style="display:none;"></div>
    <button id="sidebarToggle" style="display:none;"></button>
</div>

<?php 
$conn->close();
require_once '../includes/footer.php'; 
?>