<?php
require_once '../../includes/config.php';

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh masuk sini
checkAuth([1, 2]);

$page_title = "Manajemen Akun Pengguna";
$conn = connectDB();

$my_role_id = $_SESSION['id_role']; // Ambil role_id dari session
$my_user_id = $_SESSION['id_user']; // Ambil id_user dari session

// =========================================================================
// LOGIKA FILTER BERDASARKAN ROLE (UPDATED FOR NEW DB SCHEMA)
// =========================================================================

if ($my_role_id == 1) {
    // --- SKENARIO OWNER ---
    // Melihat: HRD (2), Karyawan (3), dan Akun Toko (4)
    // Update: Mengambil nama_lengkap & outlet dari tabel 'karyawan'
    $sql = "SELECT u.id_user, u.username, u.is_active, r.nama_role, 
            k.nama_lengkap, o.nama_outlet 
            FROM users u
            JOIN user_roles ur ON u.id_user = ur.id_user
            JOIN roles r ON ur.id_role = r.id_role
            LEFT JOIN karyawan k ON u.id_user = k.id_user
            LEFT JOIN outlets o ON k.id_outlet = o.id_outlet
            WHERE u.id_user != ? AND ur.id_role IN (2, 3, 4)
            ORDER BY ur.id_role ASC, k.nama_lengkap ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_user_id);
} elseif ($my_role_id == 2) {
    // --- SKENARIO HRD ---
    // Melihat: HANYA Karyawan (3)
    // Update: Join ke tabel karyawan & outlets
    $sql = "SELECT u.id_user, u.username, u.is_active, r.nama_role, 
            k.nama_lengkap, o.nama_outlet 
            FROM users u
            JOIN user_roles ur ON u.id_user = ur.id_user
            JOIN roles r ON ur.id_role = r.id_role
            LEFT JOIN karyawan k ON u.id_user = k.id_user
            LEFT JOIN outlets o ON k.id_outlet = o.id_outlet
            WHERE ur.id_role = 3
            ORDER BY k.nama_lengkap ASC";

    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users-cog text-primary"></i> Manajemen Akun</h1>

        <a href="tambah_user.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-user-plus fa-sm text-white-50"></i> Tambah Akun Baru
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pengguna Sistem</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Lengkap</th>
                            <th>Username</th>
                            <th>Role (Peran)</th>
                            <th>Cabang</th>
                            <th width="10%">Status</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $no = 1;
                            while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center"><?php echo $no++; ?></td>
                                    <td class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($row['nama_lengkap'] ?? 'User Tanpa Profil'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td>
                                        <?php
                                        $badge_color = 'bg-secondary';
                                        if ($row['nama_role'] == 'HRD') $badge_color = 'bg-info text-dark';
                                        elseif ($row['nama_role'] == 'Karyawan') $badge_color = 'bg-success';
                                        elseif ($row['nama_role'] == 'Akun Toko') $badge_color = 'bg-warning text-dark';
                                        elseif ($row['nama_role'] == 'Owner') $badge_color = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_color; ?>"><?php echo $row['nama_role']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['nama_outlet'] ?? '-'); ?></td>
                                    <td class="text-center">
                                        <?php if ($row['is_active'] == 1): ?>
                                            <span class="badge rounded-pill bg-primary">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger">Non-Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="detail_user.php?id=<?php echo $row['id_user']; ?>" class="btn btn-info btn-sm btn-circle" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <a href="edit_user.php?id=<?php echo $row['id_user']; ?>" class="btn btn-warning btn-sm btn-circle" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <a href="proses_user.php?action=delete&id=<?php echo $row['id_user']; ?>" ...>
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data user yang sesuai hak akses Anda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['form_status'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['form_status']; ?>',
                title: '<?php echo ($_SESSION['form_status'] == 'success') ? 'Berhasil!' : 'Gagal!'; ?>',
                text: '<?php echo $_SESSION['form_message']; ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['form_status'], $_SESSION['form_message']); ?>
        <?php endif; ?>
    });
</script>
<?php
$stmt->close();

include '../../includes/footer.php';
?>