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
    $sql = "SELECT 
                u.id_user, 
                u.username, 
                u.is_active,
                k.nama_lengkap, 
                k.foto_referensi,
                k.face_descriptor,
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
if (isset($_SESSION['form_status'])): 
    // Data akan diambil oleh SweetAlert script di footer
endif; 
?>

<div class="card shadow">
    <div class="card-header bg-warning text-dark">
        Daftar Karyawan (Role HRD/Owner dapat mencatat waktu absensi di sini) - Hari Ini: <?php echo date('d F Y'); ?>
    </div>
    <div class="d-flex justify-content-end mb-3 mt-3 mr-3 me-3">
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
                                <button type="button" 
                                        class="btn btn-sm btn-primary btn-daftar-wajah me-1 mb-1" 
                                        data-user-id="<?php echo $k['id_user']; ?>" 
                                        data-user-name="<?php echo htmlspecialchars($k['nama_lengkap']); ?>"
                                        data-has-face="<?php echo !empty($k['face_descriptor']) ? '1' : '0'; ?>">
                                    <i class="fas fa-camera me-1"></i> <?php echo !empty($k['face_descriptor']) ? 'Update Wajah' : 'Daftar Wajah'; ?>
                                </button>

                                <a href="staff/edit_user.php?id=<?php echo $k['id_user']; ?>&ref=kelola_karyawan" class="btn btn-sm btn-info text-white me-1 mb-1"><i class="fas fa-edit"></i></a>
                                
                                <form class="form-delete" method="POST" action="staff/proses_user.php" style="display:inline-block;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_user" value="<?php echo $k['id_user']; ?>">
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-danger btn-delete-confirm mb-1" 
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

<!-- Modal Registrasi Wajah -->
<div class="modal fade" id="modalDaftarWajah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalTitleWajah"><i class="fas fa-camera"></i> Registrasi Wajah Karyawan</h5>
                <button type="button" class="btn-close btn-close-white close text-white" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <form method="POST" action="proses_daftar_wajah.php" id="formDaftarWajah">
                <?php echo csrfField(); ?>
                <div class="modal-body bg-light text-center">
                    <input type="hidden" name="id_user" id="idUserWajah">
                    <input type="hidden" name="foto_base64" id="fotoBase64Wajah">
                    <input type="hidden" name="face_descriptor" id="descriptorWajah">

                    <div id="badgeStatusWajah" class="alert alert-info py-2 font-weight-bold mb-3">
                        <i class="fas fa-spinner fa-spin me-1"></i> Memuat Model Wajah...
                    </div>

                    <div style="position: relative; width: 100%; max-width: 360px; margin: 0 auto;">
                        <video id="videoWajah" width="100%" height="270" autoplay muted style="border-radius:12px; background:#000; object-fit: cover;"></video>
                        <canvas id="canvasOverlay" style="position: absolute; top:0; left:0; width:100%; height:100%;"></canvas>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSimpanWajah" class="btn btn-success font-weight-bold" disabled>
                        <i class="fas fa-save me-1"></i> Simpan Sample Wajah
                    </button>
                </div>
            </form>
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

<script src="../assets/vendor/face-api/face-api.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. NOTIFIKASI TAMBAH/EDIT/HAPUS ---
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
                    form.submit();
                }
            });
        });
    });

    // --- 3. LOGIKA REGISTRASI WAJAH (FACE-API.JS & WEBCAM) ---
    let modelsLoaded = false;
    let stream = null;
    let detectInterval = null;

    async function loadModels() {
        if (modelsLoaded) return true;
        try {
            const MODEL_URL = '../assets/vendor/face-api/models';
            await Promise.all([
                faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);
            modelsLoaded = true;
            return true;
        } catch (err) {
            console.error("Error loading face-api models:", err);
            return false;
        }
    }

    async function startWebcam() {
        const video = document.getElementById('videoWajah');
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { width: 360, height: 270 } });
            video.srcObject = stream;
            return true;
        } catch (err) {
            console.error("Webcam error:", err);
            const badge = document.getElementById('badgeStatusWajah');
            if (badge) {
                badge.className = 'alert alert-danger py-2 font-weight-bold mb-3';
                badge.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Kamera tidak diizinkan atau tidak ditemukan.';
            }
            return false;
        }
    }

    function stopWebcam() {
        if (detectInterval) {
            clearInterval(detectInterval);
            detectInterval = null;
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        const video = document.getElementById('videoWajah');
        if (video) video.srcObject = null;
        const btnSimpan = document.getElementById('btnSimpanWajah');
        if (btnSimpan) btnSimpan.disabled = true;
    }

    async function startFaceDetection() {
        const video = document.getElementById('videoWajah');
        const canvas = document.getElementById('canvasOverlay');
        const badge = document.getElementById('badgeStatusWajah');
        const btnSimpan = document.getElementById('btnSimpanWajah');
        const inputBase64 = document.getElementById('fotoBase64Wajah');
        const inputDescriptor = document.getElementById('descriptorWajah');

        if (!video || !canvas) return;

        const displaySize = { width: video.clientWidth || 360, height: video.clientHeight || 270 };
        faceapi.matchDimensions(canvas, displaySize);

        detectInterval = setInterval(async () => {
            if (!stream || video.paused || video.ended) return;

            try {
                const detection = await faceapi.detectSingleFace(video)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                if (detection) {
                    const resizedDetections = faceapi.resizeResults(detection, displaySize);
                    faceapi.draw.drawDetections(canvas, resizedDetections);
                    faceapi.draw.drawFaceLandmarks(canvas, resizedDetections);

                    if (badge) {
                        badge.className = 'alert alert-success py-2 font-weight-bold mb-3';
                        badge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Wajah Terdeteksi! Siap Disimpan.';
                    }
                    if (btnSimpan) btnSimpan.disabled = false;

                    if (inputDescriptor) {
                        inputDescriptor.value = JSON.stringify(Array.from(detection.descriptor));
                    }

                    if (inputBase64) {
                        const snapCanvas = document.createElement('canvas');
                        snapCanvas.width = video.videoWidth || 360;
                        snapCanvas.height = video.videoHeight || 270;
                        const snapCtx = snapCanvas.getContext('2d');
                        snapCtx.drawImage(video, 0, 0, snapCanvas.width, snapCanvas.height);
                        inputBase64.value = snapCanvas.toDataURL('image/jpeg', 0.8);
                    }
                } else {
                    if (badge) {
                        badge.className = 'alert alert-warning py-2 font-weight-bold mb-3';
                        badge.innerHTML = '<i class="fas fa-user-slash me-1"></i> Posisikan Wajah Anda di Depan Kamera...';
                    }
                    if (btnSimpan) btnSimpan.disabled = true;
                }
            } catch (err) {
                console.error("Detection error:", err);
            }
        }, 300);
    }

    const daftarWajahButtons = document.querySelectorAll('.btn-daftar-wajah');
    const modalEl = document.getElementById('modalDaftarWajah');

    daftarWajahButtons.forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');

            document.getElementById('idUserWajah').value = userId;
            document.getElementById('modalTitleWajah').innerHTML = '<i class="fas fa-camera me-1"></i> Registrasi Wajah: ' + userName;

            const badge = document.getElementById('badgeStatusWajah');
            if (badge) {
                badge.className = 'alert alert-info py-2 font-weight-bold mb-3';
                badge.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memuat Model Wajah...';
            }
            const btnSimpan = document.getElementById('btnSimpanWajah');
            if (btnSimpan) btnSimpan.disabled = true;

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                let bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                bsModal.show();
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#modalDaftarWajah').modal('show');
            }

            const loaded = await loadModels();
            if (loaded) {
                const camOk = await startWebcam();
                if (camOk) {
                    startFaceDetection();
                }
            } else {
                if (badge) {
                    badge.className = 'alert alert-danger py-2 font-weight-bold mb-3';
                    badge.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Gagal Memuat Model Wajah.';
                }
            }
        });
    });

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', stopWebcam);
        if (typeof $ !== 'undefined') {
            $(modalEl).on('hidden.bs.modal', stopWebcam);
        }
    }
});
</script>
<?php 
include '../includes/footer.php';
?>