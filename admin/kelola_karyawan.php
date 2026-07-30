<?php
require_once '../includes/config.php'; 

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh mengakses.
checkAuth([1, 2]); 

$page_title = "Manajemen Absensi Karyawan";
$role_id = $_SESSION['id_role'] ?? 0;
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$conn = connectDB();
$karyawan_list = [];
$error = '';
$today = date('Y-m-d');

try {
    // 1. Ambil SEMUA akun Karyawan (Role ID 3)
    // PERBAIKAN: Join ke tabel 'karyawan' untuk ambil nama & outlet, serta gunakan 'id_user'
    $sql = "SELECT 
                u.id_user, 
                u.username, 
                u.is_active,
                k.nama_lengkap, 
                o.nama_outlet
            FROM users u
            JOIN karyawan k ON u.id_user = k.id_user
            LEFT JOIN outlets o ON k.id_outlet = o.id_outlet 
            WHERE u.id_role = 3
            ORDER BY o.nama_outlet ASC, k.nama_lengkap ASC"; 
    
    $stmt = $conn->prepare($sql);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        // Loop untuk mengambil data absensi real-time per karyawan
        while ($karyawan = $result->fetch_assoc()) {
            // Cek status absensi hari ini per karyawan
            // PERBAIKAN: Gunakan 'id_user'
            $sql_status = "SELECT waktu_masuk, waktu_pulang FROM absensi WHERE id_user = ? AND tanggal = ?";
            $stmt_status = $conn->prepare($sql_status);
            $stmt_status->bind_param("is", $karyawan['id_user'], $today);
            $stmt_status->execute();
            $status = $stmt_status->get_result()->fetch_assoc();
            $stmt_status->close();
            
            $karyawan['status'] = $status ? $status : ['waktu_masuk' => NULL, 'waktu_pulang' => NULL];
            $karyawan_list[] = $karyawan;
        }
    } else {
        $error = "Gagal memuat data karyawan: " . $stmt->error;
    }
    $stmt->close();

} catch (Exception $e) {
    $error = "Terjadi kesalahan: " . $e->getMessage();
} finally {
    $conn->close();
}

include '../includes/header.php'; 
?>

<h3 class="mb-4">Manajemen Absensi Karyawan (Manual Check-in)</h3>

<?php 
// Logika notifikasi SweetAlert sudah dimuat di footer/header secara global.
if (isset($_SESSION['form_status'])): 
    // Data akan diambil oleh SweetAlert script di footer
endif; 
?>

<div class="card shadow">
    <div class="card-header bg-warning text-dark">
        Daftar Karyawan (Role HRD/Owner dapat mencatat waktu absensi di sini) - Hari Ini: <?php echo date('d F Y'); ?>
    </div>
    <div class="d-flex justify-content-end mb-3 mt-3 mr-3">
        <a href="staff/tambah_user.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Tambah Karyawan Baru
        </a>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th>Nama Karyawan</th>
                        <th>Outlet</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Pulang</th>
                        <th>Status Hari Ini</th>
                        <th>Aksi Absensi</th>
                        <th>Aksi Akun</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($karyawan_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Belum ada data karyawan yang terdaftar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($karyawan_list as $k): 
                            $masuk = $k['status']['waktu_masuk'];
                            $pulang = $k['status']['waktu_pulang'];
                            
                            $status_badge = 'Absen Masuk';
                            $status_color = 'primary';
                            
                            if (is_null($masuk)) {
                                $status_badge = 'Belum Masuk';
                                $status_color = 'danger';
                            } elseif (is_null($pulang)) {
                                $status_badge = 'Sedang Bekerja';
                                $status_color = 'success';
                            } else {
                                $status_badge = 'Sudah Selesai';
                                $status_color = 'secondary';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($k['nama_lengkap']); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($k['nama_outlet'] ?? 'N/A'); ?></span></td>
                            <td><?php echo $masuk ? date('H:i:s', strtotime($masuk)) : '-'; ?></td>
                            <td><?php echo $pulang ? date('H:i:s', strtotime($pulang)) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_badge; ?></span>
                            </td>
                            <td>
                                <form method="POST" action="proses_absensi.php" style="display:inline-block;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id_user" value="<?php echo $k['id_user']; ?>">
                                    
                                    <?php if (is_null($masuk)): ?>
                                        <input type="hidden" name="action" value="masuk">
                                        <button type="submit" class="btn btn-sm btn-success">Masuk Sekarang</button>
                                    <?php elseif (is_null($pulang)): ?>
                                        <input type="hidden" name="action" value="pulang">
                                        <button type="submit" class="btn btn-sm btn-warning">Pulang Sekarang</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled>Hari Selesai</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            
                            <td>
                                <a href="staff/edit_user.php?id=<?php echo $k['id_user']; ?>&ref=kelola_karyawan" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i></a>
                                
                                <form class="form-delete" method="POST" action="staff/proses_user.php" style="display:inline-block;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_user" value="<?php echo $k['id_user']; ?>">
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-danger btn-delete-confirm" 
                                            data-user-id="<?php echo $k['id_user']; ?>"
                                            data-user-name="<?php echo htmlspecialchars($k['nama_lengkap']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php 
$status = $_SESSION['form_status'] ?? null;
$message = $_SESSION['form_message'] ?? null;

// Hapus variabel sesi segera agar tidak muncul lagi
unset($_SESSION['form_status']);
unset($_SESSION['form_message']);
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. NOTIFIKASI TAMBAH/EDIT/HAPUS ---
    // PERBAIKAN: Escape $status untuk mencegah XSS
    const formStatus = '<?php echo safeJsString($status); ?>';
    const formMessage = '<?php echo safeJsString($message); ?>';

    if (formStatus && formMessage) {
        Swal.fire({
            icon: formStatus,
            title: (formStatus === 'success' ? 'Berhasil!' : 'Gagal!'),
            text: formMessage,
            showConfirmButton: false,
            timer: 3000
        });
    }

    // --- 2. LOGIKA SWEETALERT UNTUK HAPUS ---
    const deleteButtons = document.querySelectorAll('.btn-delete-confirm');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const form = this.closest('.form-delete');
            const userName = this.getAttribute('data-user-name');
            
            Swal.fire({
                title: `Hapus Akun ${userName}?`,
                text: "Anda akan menghapus akun karyawan ini secara permanen. Lanjutkan?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Jika dikonfirmasi, kirim formulir POST ke proses_user.php
                    form.submit();
                }
            });
        });
    });
});
</script>
<?php 
include '../includes/footer.php';
?>