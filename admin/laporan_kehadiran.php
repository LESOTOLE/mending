<?php
require_once '../includes/config.php'; 

// OTORISASI: Owner (1) dan HRD (2) yang boleh mengakses.
checkAuth([1, 2]); 

$page_title = "Laporan Kehadiran";
$role_id = $_SESSION['id_role'] ?? 0;

$conn = connectDB();
$bulan_sekarang = date('m');
$tahun_sekarang = date('Y');
$kehadiran_list = [];
$error = '';

// Target hari kerja dalam sebulan
$target_kehadiran = 25; 

try {
    // QUERY PERBAIKAN: Hanya menghitung absensi valid (status_verifikasi = 'valid')
    $sql_kehadiran = "
        SELECT 
            u.id_user, 
            k.nama_lengkap,  -- Ambil dari tabel karyawan
            o.nama_outlet,   -- Ambil dari tabel outlets (via karyawan)
            (SELECT COUNT(a.id_absensi) 
             FROM absensi a 
             WHERE a.id_user = u.id_user 
             AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
             AND a.status_verifikasi = 'valid') AS total_hadir
        FROM users u
        JOIN karyawan k ON u.id_user = k.id_user  -- JOIN WAJIB
        LEFT JOIN outlets o ON k.id_outlet = o.id_outlet -- Link outlet dari karyawan
        WHERE u.id_role = 3  -- Hanya Role Karyawan
        ORDER BY o.nama_outlet ASC, k.nama_lengkap ASC
    ";
    
    $stmt = $conn->prepare($sql_kehadiran);
    $stmt->bind_param("ss", $bulan_sekarang, $tahun_sekarang); // Gunakan 'ss' karena date() mengembalikan string
    $stmt->execute();
    $kehadiran_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $error = "Gagal memuat data kehadiran: " . $e->getMessage();
} finally {
    $conn->close();
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <h3 class="mb-4 text-gray-800">Laporan Kehadiran Karyawan</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">Periode: <?php echo date('F Y'); ?></h6>
            <span class="badge badge-light text-primary">Target: <?php echo $target_kehadiran; ?> Hari</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th>Nama Karyawan</th>
                            <th>Outlet Penempatan</th>
                            <th>Total Hadir</th>
                            <th width="30%">Performa Kehadiran</th>
                            <th width="15%">Audit Presensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kehadiran_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data absensi pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kehadiran_list as $k): 
                                // Hitung Persentase
                                $total_hadir = $k['total_hadir'];
                                $persen_hadir = ($target_kehadiran > 0) ? round(($total_hadir / $target_kehadiran) * 100) : 0;
                                
                                // Logika Warna Bar
                                $bar_color = 'bg-danger';
                                if ($persen_hadir >= 50) $bar_color = 'bg-warning';
                                if ($persen_hadir >= 80) $bar_color = 'bg-info';
                                if ($persen_hadir >= 100) $bar_color = 'bg-success';
                                
                                // Batasi lebar bar max 100%
                                $progress_width = min(100, $persen_hadir); 
                            ?>
                            <tr>
                                <td class="align-middle font-weight-bold text-dark">
                                    <?php echo htmlspecialchars($k['nama_lengkap']); ?>
                                </td>
                                <td class="align-middle">
                                    <i class="fas fa-store text-muted mr-1"></i>
                                    <?php echo htmlspecialchars($k['nama_outlet'] ?? 'Belum ada outlet'); ?>
                                </td>
                                <td class="align-middle">
                                    <span class="font-weight-bold"><?php echo $total_hadir; ?></span> 
                                    <small class="text-muted">/ <?php echo $target_kehadiran; ?> hari</small>
                                </td>
                                <td class="align-middle">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php echo $bar_color; ?> progress-bar-striped progress-bar-animated" 
                                             role="progressbar" 
                                             style="width: <?php echo $progress_width; ?>%;" 
                                             aria-valuenow="<?php echo $progress_width; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $persen_hadir; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-audit-absensi" data-user-id="<?php echo $k['id_user']; ?>" data-user-name="<?php echo htmlspecialchars($k['nama_lengkap']); ?>">
                                        <i class="fas fa-camera me-1"></i> Audit Presensi
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Audit Presensi -->
<div class="modal fade" id="modalAuditAbsensi" tabindex="-1" aria-labelledby="modalAuditAbsensiLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalAuditAbsensiLabel"><i class="fas fa-camera mr-2"></i> Audit Detail Presensi</h5>
                <button type="button" class="close text-white btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body bg-light" id="modalAuditBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Memuat data absensi...</p>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const auditButtons = document.querySelectorAll('.btn-audit-absensi');
    const modalBody = document.getElementById('modalAuditBody');
    const modalTitle = document.getElementById('modalAuditAbsensiLabel');

    auditButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            
            if (modalTitle) {
                modalTitle.innerHTML = '<i class="fas fa-camera mr-2"></i> Audit Detail Presensi: ' + userName;
            }
            if (modalBody) {
                modalBody.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Memuat data absensi...</p></div>';
            }

            const modalEl = document.getElementById('modalAuditAbsensi');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                bsModal.show();
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#modalAuditAbsensi').modal('show');
            }

            fetch('get_detail_absensi.php?id_user=' + userId)
                .then(response => response.text())
                .then(html => {
                    if (modalBody) {
                        modalBody.innerHTML = html;
                    }
                })
                .catch(err => {
                    if (modalBody) {
                        modalBody.innerHTML = '<div class="alert alert-danger">Gagal memuat data detail absensi.</div>';
                    }
                });
        });
    });
});
</script>

<?php 
include '../includes/footer.php';
?>