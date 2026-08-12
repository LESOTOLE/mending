<?php
require_once '../../includes/config.php';
checkAuth([3]); // Pastikan hanya role Karyawan yang bisa akses
header("Location: absen.php");
exit;

$id_user = $_SESSION['id_user'];
$today   = date('Y-m-d');
$page_title = "Absensi Harian"; // Untuk title di header

// 1. AMBIL STATUS ABSENSI HARI INI
$stmt = $conn->prepare("SELECT * FROM absensi WHERE id_user = ? AND tanggal = ?");
$stmt->bind_param("is", $id_user, $today);
$stmt->execute();
$result = $stmt->get_result();
$data_absen = $result->fetch_assoc();

// 2. TENTUKAN STATUS
$status_sekarang = '';
if (!$data_absen) {
    $status_sekarang = 'belum_masuk'; // Belum absen sama sekali
} elseif ($data_absen['waktu_pulang'] == NULL) {
    $status_sekarang = 'sudah_masuk'; // Sudah masuk, tapi belum pulang
} else {
    $status_sekarang = 'selesai';     // Sudah masuk dan sudah pulang
}

// Panggil header bawaan sistem (sidebar otomatis ikut terpanggil di sini)
include '../../includes/header.php';
?>

<style>
    .card-absen {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    }

    .circle-icon {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto 20px;
        font-size: 35px;
        color: white;
    }
</style>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Absensi Harian</h1>
    </div>

    <?php if (isset($_SESSION['form_status'])): ?>
        <div class="alert alert-<?php echo ($_SESSION['form_status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show shadow-sm">
            <?php echo $_SESSION['form_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['form_status'], $_SESSION['form_message']); ?>
    <?php endif; ?>

    <div class="row justify-content-center mt-3">
        <div class="col-md-8 col-lg-6">
            <div class="card card-absen p-5 text-center bg-white border-bottom-primary">

                <?php if ($status_sekarang == 'belum_masuk'): ?>
                    <div class="circle-icon bg-primary shadow-sm">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Absensi Masuk</h4>
                    <p class="text-muted mb-4">Silakan absen untuk memulai pekerjaan hari ini.</p>

                    <form action="proses_upload_absensi.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action_type" value="masuk">

                        <div class="mb-4 text-start">
                            <label class="form-label small fw-bold"><i class="fas fa-camera text-primary mr-1"></i> Foto Selfie Masuk</label>
                            <input type="file" name="foto_absensi" class="form-control form-control-lg" accept="image/*" capture="user" required>
                            <small class="text-muted">Pastikan wajah dan seragam terlihat jelas.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-fingerprint me-2"></i> ABSEN MASUK SEKARANG
                        </button>
                    </form>


                <?php elseif ($status_sekarang == 'sudah_masuk'): ?>
                    <div class="circle-icon bg-warning shadow-sm">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Sedang Bekerja</h4>

                    <div class="alert alert-light border mb-4">
                        Anda masuk pukul: <br>
                        <strong class="fs-3 text-primary">
                            <?php echo date('H:i', strtotime($data_absen['waktu_masuk'])); ?> WIB
                        </strong>
                    </div>
                    <p class="text-muted small mb-4">Sudah selesai bekerja? Silakan absen pulang.</p>

                    <form action="proses_upload_absensi.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action_type" value="pulang">

                        <div class="mb-4 text-start">
                            <label class="form-label small fw-bold"><i class="fas fa-camera text-danger mr-1"></i> Foto Selfie Pulang</label>
                            <input type="file" name="foto_absensi" class="form-control form-control-lg" accept="image/*" capture="user" required>
                        </div>

                        <button type="submit" class="btn btn-danger btn-lg w-100 py-3 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-sign-out-alt me-2"></i> ABSEN PULANG SEKARANG
                        </button>
                    </form>


                <?php else: ?>
                    <div class="circle-icon bg-success shadow-sm">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Absensi Selesai</h4>
                    <p class="text-muted">Terima kasih atas kerja keras Anda hari ini!</p>

                    <div class="row g-2 mt-4">
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-uppercase text-muted fw-bold" style="font-size:10px;">Masuk</small>
                                <div class="h5 mb-0 fw-bold text-primary">
                                    <?php echo date('H:i', strtotime($data_absen['waktu_masuk'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded bg-light">
                                <small class="text-uppercase text-muted fw-bold" style="font-size:10px;">Pulang</small>
                                <div class="h5 mb-0 fw-bold text-success">
                                    <?php echo date('H:i', strtotime($data_absen['waktu_pulang'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>