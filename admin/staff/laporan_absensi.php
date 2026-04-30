<?php
require_once '../../includes/config.php'; 

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh mengakses.
checkAuth([1, 2]); 

$page_title = "Laporan & Verifikasi Foto Absensi";
$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Pengguna';
$conn = connectDB();
$absensi_list = [];
$error = '';
$today = date('Y-m-d');
$filter_date = $_GET['date'] ?? $today;

try {
    // Ambil semua data absensi untuk hari ini/filter (JOIN untuk nama karyawan dan outlet)
    $sql = "SELECT a.*, u.nama_lengkap, o.nama_outlet
            FROM absensi a
            JOIN users u ON a.user_id = u.id_user
            LEFT JOIN outlets o ON u.outlet_id = o.id_outlet
            WHERE a.tanggal = ? ";
            
    $params = [$filter_date];
    $types = "s";
    
    if ($role_id == 2) {
        // HRD melihat semua karyawan, tetapi Owner/Admin melihat semua data secara global.
        // Jika HRD memang terikat ke outlet, kita perlu filter. Tapi karena HRD pegang banyak, filter outlet dihilangkan.
        // Cukup filter berdasarkan tanggal.
    }

    $sql .= " ORDER BY u.nama_lengkap ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $absensi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $error = "Gagal memuat data: " . $e->getMessage();
} finally {
    $conn->close();
}

include '../../includes/header.php';
?>

<h3 class="mb-4"><?php echo $page_title; ?></h3>

<?php 
// Logika SweetAlert notif dari sesi (tambah, edit, hapus, konfirmasi)
$status = $_SESSION['form_status'] ?? null;
$message = $_SESSION['form_message'] ?? null;
unset($_SESSION['form_status']);
unset($_SESSION['form_message']);
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="GET" action="laporan_absensi.php" class="mb-4">
    <div class="row">
        <div class="col-auto">
            <label for="date" class="col-form-label">Tampilkan Tanggal:</label>
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </div>
</form>

<div class="card shadow">
    <div class="card-header bg-warning text-dark">
        Data Absensi Tercatat (<?php echo date('d F Y', strtotime($filter_date)); ?>)
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Absensi baru dianggap sah dan masuk hitungan gaji jika foto telah **Dikonfirmasi** oleh HRD/Owner.
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered table-sm">
                <thead>
                    <tr>
                        <th rowspan="2">Karyawan</th>
                        <th rowspan="2">Outlet</th>
                        <th colspan="2" class="text-center">Absensi Masuk</th>
                        <th colspan="2" class="text-center">Absensi Pulang</th>
                    </tr>
                    <tr>
                        <th>Waktu & Foto</th>
                        <th>Konfirmasi</th>
                        <th>Waktu & Foto</th>
                        <th>Konfirmasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absensi_list)): ?>
                        <tr><td colspan="6" class="text-center">Tidak ada absensi tercatat untuk tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($absensi_list as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($a['nama_outlet'] ?? '-'); ?></td>
                            
                            <td>
                                <div><?php echo $a['waktu_masuk'] ?? '-'; ?></div>
                                <?php if ($a['foto_masuk']): ?>
    <button type="button" class="btn btn-sm btn-outline-primary mt-1 view-photo-btn" 
            data-bs-toggle="modal" data-bs-target="#photoModal" 
            data-photo-src="../../uploads/absensi/<?php echo $a['foto_masuk']; ?>"
            data-photo-title="Foto Absensi Masuk - <?php echo htmlspecialchars($a['nama_lengkap']); ?> (<?php echo date('d M Y', strtotime($filter_date)); ?>)">
        <i class="fas fa-camera"></i> Lihat Foto
    </button>
<?php else: ?>
    <span class="text-muted small">Waktu tercatat, foto belum ada.</span>
<?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($a['foto_masuk']): ?>
                                    <?php if ($a['is_masuk_confirmed']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Dikonfirmasi</span>
                                    <?php else: ?>
                                        <form method="POST" action="proses_konfirmasi_absensi.php" style="display:inline-block;">
                                            <input type="hidden" name="absensi_id" value="<?php echo $a['id']; ?>">
                                            <input type="hidden" name="action_type" value="masuk">
                                            <input type="hidden" name="filter_date" value="<?php echo $filter_date; ?>">
                                            <button type="submit" class="btn btn-sm btn-success confirm-button">
                                                <i class="fas fa-thumbs-up"></i> Konfirmasi
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div><?php echo $a['waktu_pulang'] ?? '-'; ?></div>
                                <?php if ($a['foto_pulang']): ?>
    <button type="button" class="btn btn-sm btn-outline-primary mt-1 view-photo-btn" 
            data-bs-toggle="modal" data-bs-target="#photoModal" 
            data-photo-src="../../uploads/absensi/<?php echo $a['foto_pulang']; ?>"
            data-photo-title="Foto Absensi Pulang - <?php echo htmlspecialchars($a['nama_lengkap']); ?> (<?php echo date('d M Y', strtotime($filter_date)); ?>)">
        <i class="fas fa-camera"></i> Lihat Foto
    </button>
<?php else: ?>
    <span class="text-muted small">Waktu tercatat, foto belum ada.</span>
<?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($a['foto_pulang']): ?>
                                    <?php if ($a['is_pulang_confirmed']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check"></i> Dikonfirmasi</span>
                                    <?php else: ?>
                                        <form method="POST" action="proses_konfirmasi_absensi.php" style="display:inline-block;">
                                            <input type="hidden" name="absensi_id" value="<?php echo $a['id']; ?>">
                                            <input type="hidden" name="action_type" value="pulang">
                                            <input type="hidden" name="filter_date" value="<?php echo $filter_date; ?>">
                                            <button type="submit" class="btn btn-sm btn-success confirm-button">
                                                <i class="fas fa-thumbs-up"></i> Konfirmasi
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="photoModalLabel">Detail Foto Absensi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="modalPhotoViewer" class="img-fluid rounded" alt="Foto Absensi">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tampilkan notifikasi SweetAlert
    const formStatus = '<?php echo $status; ?>';
    const formMessage = '<?php echo htmlspecialchars($message); ?>';

    if (formStatus && formMessage) {
        Swal.fire({
            icon: formStatus,
            title: (formStatus === 'success' ? 'Berhasil!' : 'Gagal!'),
            text: formMessage,
            showConfirmButton: false,
            timer: 3000
        });
    }

    // 2. Logika SweetAlert untuk Tombol Konfirmasi
    const confirmButtons = document.querySelectorAll('.confirm-button');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const actionType = form.querySelector('[name="action_type"]').value.toUpperCase();

            Swal.fire({
                title: `Konfirmasi Absensi ${actionType}?`,
                text: "Anda yakin foto ini valid dan absensi dapat disahkan?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Sahkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // ... (Kode SweetAlert yang sudah ada di sini) ...

    // Logika untuk menampilkan foto di modal
    const photoModal = document.getElementById('photoModal');
    photoModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // Tombol yang memicu modal
        const photoSrc = button.getAttribute('data-photo-src');
        const photoTitle = button.getAttribute('data-photo-title');

        const modalTitle = photoModal.querySelector('.modal-title');
        const modalPhoto = photoModal.querySelector('#modalPhotoViewer');

        modalTitle.textContent = photoTitle;
        modalPhoto.src = photoSrc;
    });
});
</script>

<?php 
include '../../includes/footer.php';
?>
</script>

<?php 
include '../../includes/footer.php';
?>