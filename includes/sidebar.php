<?php
// =======================================================================
// LOGIKA PENENTUAN PATH & MENU AKTIF
// =======================================================================

$current_page = basename($_SERVER['PHP_SELF']);
$current_uri  = $_SERVER['REQUEST_URI'];

// Deteksi posisi folder saat ini agar link tidak patah
$in_keuangan = strpos($current_uri, '/keuangan/') !== false;
$in_staff    = strpos($current_uri, '/staff/') !== false;
$in_layanan  = strpos($current_uri, '/manajemen_layanan/') !== false;
$in_outlet   = strpos($current_uri, '/outlet/') !== false; // Deteksi folder outlet
$in_hrd      = strpos($current_uri, '/hrd/') !== false;

// Set Base Path: Kembali ke root admin jika berada di subfolder
$base_path = ($in_keuangan || $in_staff || $in_layanan || $in_outlet || $in_hrd) ? '../' : '';

// Variabel Menu Aktif untuk Highlight
$is_dashboard   = ($current_page == 'dashboard.php');
$is_keuangan    = in_array($current_page, ['riwayat_transaksi.php', 'laporan_keuangan.php', 'pengeluaran.php']);
$is_outlet      = ($current_page == 'manajemen_outlet.php');
$is_layanan     = ($current_page == 'layanan.php');
$is_man_cabang    = ($current_page == 'outlet.php');
$is_user        = ($current_page == 'manajemen_user.php');
$is_pos         = ($current_page == 'transaksi.php');
$is_absen    = ($current_page == 'data_absensi.php');
$is_gaji    = ($current_page == 'gaji.php');
$is_riwayat_gaji = ($current_page == 'riwayat_gaji.php');
$is_ganti_pass  = ($current_page == 'ganti_password.php');

// Ambil data dari session
$role_id = $_SESSION['id_role'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'User';
?>

<style>
    /* Custom Scrollbar Sidebar */
    .no-scrollbar::-webkit-scrollbar {
        width: 5px;
    }

    .no-scrollbar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .no-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    .no-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    /* Nav Link Styling */
    .nav-link.active {
        font-weight: bold;
    }

    .dropdown-item.active {
        background: linear-gradient(90deg, var(--secondary), var(--primary)) !important;
        color: white !important;
    }

    .hover-bg:hover {
        background: rgba(255, 255, 255, 0.2) !important;
    }
</style>

<div class="sidebar p-3 d-flex flex-column text-dark shadow"
    style="height: 100vh; position: sticky; top: 0; z-index: 1000; width: 250px;">

    <a href="<?php echo $base_path; ?>dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none justify-content-center flex-shrink-0">
        <i class="fas fa-tshirt fa-2x me-2"></i>
        <span class="fs-5 fw-bold">MENDING LAUNDRY</span>
    </a>

    <hr>

    <div class="text-center mb-3 flex-shrink-0">
        <div class="small text-muted" style="font-size: 10px;">Login Sebagai:</div>
        <div class="fw-bold text-uppercase" style="font-size: 13px; color: #000000;"><?php echo $role_name; ?></div>
    </div>

    <ul class="nav flex-column mb-auto overflow-auto no-scrollbar" style="flex-grow: 1;">

        <li class="nav-item mb-1">
            <a href="<?php echo $base_path; ?>dashboard.php" class="nav-link text-dark <?php echo $is_dashboard ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
            </a>
        </li>

        <?php if (in_array($role_id, [4])): ?>
            <li class="nav-item mb-1">
                <a href="<?php echo $base_path; ?>keuangan/transaksi.php" class="nav-link text-dark <?php echo $is_pos ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-cash-register fa-fw me-2"></i> Kasir POS
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($role_id, [1, 2, 4])): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-dark <?php echo $is_keuangan ? 'active bg-white bg-opacity-25 rounded' : 'collapsed'; ?>"
                    href="#menuKeuangan" data-bs-toggle="collapse" aria-expanded="<?php echo $is_keuangan ? 'true' : 'false'; ?>">
                    <i class="fas fa-wallet fa-fw me-2"></i> Keuangan
                    <i class="fas fa-chevron-down float-end mt-1 small"></i>
                </a>
                <div class="collapse <?php echo $is_keuangan ? 'show' : ''; ?>" id="menuKeuangan">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-white border-opacity-25 ps-2">
                        <li class="nav-item">
                            <a href="<?php echo $base_path; ?>keuangan/riwayat_transaksi.php" class="nav-link text-dark py-1 small <?php echo ($current_page == 'riwayat_transaksi.php') ? 'fw-bold text-warning' : ''; ?>">
                                Riwayat Transaksi
                            </a>
                        </li>
                        <?php if ($role_id == 1): // Khusus Owner 
                        ?>
                            <li class="nav-item">
                                <a href="<?php echo $base_path; ?>keuangan/laporan_keuangan.php" class="nav-link text-dark py-1 small <?php echo ($current_page == 'laporan_keuangan.php') ? 'fw-bold text-warning' : ''; ?>">
                                    Laporan Laba/Rugi
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $base_path; ?>keuangan/pengeluaran.php" class="nav-link text-dark py-1 small <?php echo ($current_page == 'pengeluaran.php') ? 'fw-bold text-warning' : ''; ?>">
                                    Catat Pengeluaran
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
        <?php endif; ?>

        <?php if ($role_id == 1): ?>
            <li class="nav-item mt-2">
                <div class="small text-muted text-uppercase fw-bold px-3 mb-1" style="font-size: 10px;">Manajemen Sistem</div>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>outlet/outlet.php" class="nav-link text-dark <?php echo $is_man_cabang ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-store fa-fw me-2"></i> Manajemen Cabang
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>manajemen_layanan/layanan.php" class="nav-link text-dark <?php echo $is_layanan ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-tags fa-fw me-2"></i> Layanan & Harga
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>staff/manajemen_user.php" class="nav-link text-dark <?php echo $is_user ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-users-cog fa-fw me-2"></i> Akun Pengguna
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role_id == 2): ?>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>kelola_karyawan.php" class="nav-link text-dark <?php echo ($current_page == 'kelola_karyawan.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-user-tie fa-fw me-2"></i> Data Karyawan
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>hrd/data_absensi.php" class="nav-link text-dark <?php echo ($current_page == 'data_absensi.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-camera-retro fa-fw me-2"></i> Data Absensi
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>hrd/gaji.php" class="nav-link text-dark <?php echo ($current_page == 'gaji.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-money-check-alt fa-fw me-2"></i> Penggajian staff
                </a>
            </li>

        <?php endif; ?>

        <?php if ($role_id == 3): ?>
            <li class="nav-item mt-2">
                <a href="<?php echo $base_path; ?>staff/absensi.php" class="nav-link text-dark <?php echo ($current_page == 'absensi.php') ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-fingerprint fa-fw me-2"></i> Absensi Harian
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $base_path; ?>staff/riwayat_gaji.php" class="nav-link text-dark <?php echo isset($is_riwayat_gaji) && $is_riwayat_gaji ? 'active bg-white bg-opacity-25 rounded' : ''; ?>">
                    <i class="fas fa-wallet fa-fw me-2"></i> Riwayat Gaji
                </a>
            </li>
        <?php endif; ?>

        <li class="nav-item py-4"></li>
    </ul>

    <hr class="mt-0">

    <div class="dropdown flex-shrink-0">
        <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle p-2 rounded hover-bg" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255, 255, 255, 1);">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['nama_lengkap'] ?? 'U'); ?>&background=random&color=fff" alt="" width="32" height="32" class="rounded-circle me-2 border border-2 border-white">
            <div class="text-truncate" style="max-width: 100px;">
                <strong class="small"><?php echo explode(' ', $_SESSION['nama_lengkap'] ?? 'User')[0]; ?></strong>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-light text-small shadow" aria-labelledby="dropdownUser1" style="z-index: 1001;">
            <li>
                <a class="dropdown-item <?php echo $is_ganti_pass ? 'active' : ''; ?>" href="<?php echo $base_path; ?>ganti_password.php">
                    <i class="fas fa-key fa-fw me-2"></i> Ganti Password
                </a>
            </li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li>
                <a class="dropdown-item text-danger fw-bold" href="<?php echo $base_path; ?>logout.php">
                    <i class="fas fa-sign-out-alt fa-fw me-2"></i> Sign out
                </a>
            </li>
        </ul>
    </div>
</div>