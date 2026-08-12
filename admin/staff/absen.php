<?php
require_once '../../includes/config.php';
checkAuth([1, 2, 3]);

$conn = connectDB();
$id_user = $_SESSION['id_user'];
$today = date('Y-m-d');
$page_title = "Absen Wajah & GPS";

// 1. Ambil data karyawan & outlet tempat bertugas
$sql_karyawan = "SELECT k.*, o.nama_outlet, o.latitude, o.longitude, o.radius_meter
                 FROM karyawan k
                 LEFT JOIN outlets o ON k.id_outlet = o.id_outlet
                 WHERE k.id_user = ?";
$stmt_karyawan = $conn->prepare($sql_karyawan);
$stmt_karyawan->bind_param("i", $id_user);
$stmt_karyawan->execute();
$karyawan = $stmt_karyawan->get_result()->fetch_assoc();
$stmt_karyawan->close();

// 2. Ambil data absensi hari ini
$sql_absen = "SELECT * FROM absensi WHERE id_user = ? AND tanggal = ?";
$stmt_absen = $conn->prepare($sql_absen);
$stmt_absen->bind_param("is", $id_user, $today);
$stmt_absen->execute();
$data_absen = $stmt_absen->get_result()->fetch_assoc();
$stmt_absen->close();

// 3. Tentukan status absensi hari ini
$status_absen = 'belum_masuk';
if ($data_absen) {
    if (is_null($data_absen['waktu_pulang'])) {
        $status_absen = 'sudah_masuk';
    } else {
        $status_absen = 'selesai';
    }
}

$conn->close();

include '../../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">

            <!-- Title & Outlet Card -->
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-body p-4 text-center">
                    <h4 class="fw-bold mb-1"><i class="fas fa-camera text-primary me-2"></i>Absen Wajah & GPS</h4>
                    <p class="text-muted small mb-2">Presensi Mandiri Real-Time Karyawan</p>
                    
                    <?php if ($karyawan && !empty($karyawan['nama_outlet'])): ?>
                        <div class="d-inline-flex align-items-center bg-light rounded-pill px-3 py-1 border">
                            <i class="fas fa-store text-info me-2"></i>
                            <span class="fw-semibold text-dark me-2"><?php echo htmlspecialchars($karyawan['nama_outlet']); ?></span>
                            <span class="badge bg-secondary">Radius: <?php echo (int)($karyawan['radius_meter'] ?? 50); ?>m</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-0 small">
                            <i class="fas fa-exclamation-triangle me-1"></i> Outlet penugasan belum diatur oleh HRD/Admin.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Session Alerts -->
            <?php if (isset($_SESSION['form_status'])): ?>
                <div class="alert alert-<?php echo ($_SESSION['form_status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible fade show shadow-sm">
                    <?php echo $_SESSION['form_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['form_status'], $_SESSION['form_message']); ?>
            <?php endif; ?>

            <?php if ($status_absen === 'selesai'): ?>
                <!-- Card Absensi Selesai -->
                <div class="card border-0 shadow-sm rounded-3 text-center p-4 bg-white">
                    <div class="circle-icon bg-success text-white mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width:70px; height:70px; font-size:32px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="fw-bold text-success mb-2">Absensi Hari Ini Selesai</h4>
                    <p class="text-muted mb-4">Anda telah melakukan absensi masuk & pulang untuk hari ini.</p>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size:11px;">Masuk</small>
                                <div class="h5 mb-0 fw-bold text-primary">
                                    <?php echo date('H:i', strtotime($data_absen['waktu_masuk'])); ?> WIB
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 border rounded-3 bg-light">
                                <small class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size:11px;">Pulang</small>
                                <div class="h5 mb-0 fw-bold text-success">
                                    <?php echo date('H:i', strtotime($data_absen['waktu_pulang'])); ?> WIB
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- Camera Container Card -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-3">
                    <div class="card-body p-3">
                        <div class="position-relative mx-auto rounded-3 overflow-hidden bg-dark shadow-sm" style="width: 100%; max-width: 400px; aspect-ratio: 4/3;">
                            <!-- Webcam Stream -->
                            <video id="webcam" autoplay muted playsinline class="w-100 h-100" style="object-fit: cover;"></video>
                            
                            <!-- Canvas Face Detection Overlay -->
                            <canvas id="faceCanvas" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none;"></canvas>
                            
                            <!-- Circular Face Guide Overlay -->
                            <div class="position-absolute top-50 start-50 translate-middle pointer-events-none d-flex align-items-center justify-content-center" style="width: 210px; height: 210px;">
                                <div id="guideRing" class="w-100 h-100 rounded-circle border border-3 border-white opacity-75" style="box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45); transition: border-color 0.3s ease;">
                                </div>
                            </div>
                        </div>

                        <!-- Status Badges Grid -->
                        <div class="row g-2 mt-3 text-center">
                            <div class="col-6">
                                <div id="badgeGpsContainer">
                                    <span id="badgeGps" class="badge bg-secondary w-100 p-2 text-wrap fw-normal" style="font-size: 12px;">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Memeriksa Lokasi GPS...
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div id="badgeFaceContainer">
                                    <span id="badgeFace" class="badge bg-secondary w-100 p-2 text-wrap fw-normal" style="font-size: 12px;">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Memuat Model Wajah...
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Live Status Message Banner -->
                        <div id="statusAlert" class="alert alert-info py-2 px-3 mt-3 mb-0 small text-center fw-semibold">
                            <i class="fas fa-info-circle me-1"></i> Posisikan wajah Anda di dalam lingkaran dan aktifkan GPS.
                        </div>
                    </div>
                </div>

                <!-- Form Action Button -->
                <form id="formAbsen" action="../proses_absensi.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo ($status_absen === 'belum_masuk') ? 'masuk' : 'pulang'; ?>">
                    <input type="hidden" name="id_user" value="<?php echo $id_user; ?>">
                    <input type="hidden" name="foto_base64" id="fotoBase64">
                    <input type="hidden" name="latitude" id="inputLat">
                    <input type="hidden" name="longitude" id="inputLong">

                    <?php if ($status_absen === 'belum_masuk'): ?>
                        <button type="submit" id="btnSubmitAbsen" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold shadow" disabled>
                            <i class="fas fa-sign-in-alt me-2"></i> ABSEN MASUK SEKARANG
                        </button>
                    <?php else: ?>
                        <button type="submit" id="btnSubmitAbsen" class="btn btn-danger btn-lg w-100 py-3 rounded-pill fw-bold shadow" disabled>
                            <i class="fas fa-sign-out-alt me-2"></i> ABSEN PULANG SEKARANG
                        </button>
                    <?php endif; ?>
                </form>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="../../assets/vendor/face-api/face-api.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($status_absen === 'selesai'): ?>
        return; // Tidak perlu menginisialisasi kamera/GPS jika sudah selesai
    <?php endif; ?>

    // Configuration & Data from PHP
    const outletLat = <?php echo json_encode($karyawan['latitude'] ? (float)$karyawan['latitude'] : null); ?>;
    const outletLon = <?php echo json_encode($karyawan['longitude'] ? (float)$karyawan['longitude'] : null); ?>;
    const radiusMeter = <?php echo json_encode($karyawan['radius_meter'] ? (int)$karyawan['radius_meter'] : 50); ?>;
    const rawDescriptor = '<?php echo safeJsString($karyawan['face_descriptor'] ?? ''); ?>';

    let storedDescriptor = null;
    try {
        if (rawDescriptor) {
            storedDescriptor = JSON.parse(rawDescriptor);
        }
    } catch (e) {
        console.error("Error parsing face descriptor:", e);
    }

    let isGpsValid = false;
    let isFaceValid = false;
    let userLat = null;
    let userLon = null;

    let modelsLoaded = false;
    let stream = null;
    let detectInterval = null;

    // --- 1. HAVERSINE DISTANCE CALCULATOR ---
    function getHaversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // meters
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    // --- 2. UPDATE UI BADGES & MESSAGES ---
    function updateGpsBadge(isValid, message) {
        const badge = document.getElementById('badgeGps');
        if (!badge) return;
        if (isValid) {
            badge.className = 'badge bg-success w-100 p-2 text-wrap fw-normal';
            badge.innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${message}`;
        } else {
            badge.className = 'badge bg-danger w-100 p-2 text-wrap fw-normal';
            badge.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> ${message}`;
        }
    }

    function updateFaceBadge(isValid, message) {
        const badge = document.getElementById('badgeFace');
        if (!badge) return;
        if (isValid) {
            badge.className = 'badge bg-success w-100 p-2 text-wrap fw-normal';
            badge.innerHTML = `<i class="fas fa-user-check me-1"></i> ${message}`;
        } else {
            badge.className = 'badge bg-warning text-dark w-100 p-2 text-wrap fw-normal';
            badge.innerHTML = `<i class="fas fa-user-times me-1"></i> ${message}`;
        }
    }

    function updateAlertMessage() {
        const alertBox = document.getElementById('statusAlert');
        if (!alertBox) return;

        if (isGpsValid && isFaceValid) {
            alertBox.className = 'alert alert-success py-2 px-3 mt-3 mb-0 small text-center fw-bold';
            alertBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> Verifikasi Berhasil! Silakan klik tombol absen.';
        } else if (!isGpsValid && !isFaceValid) {
            alertBox.className = 'alert alert-danger py-2 px-3 mt-3 mb-0 small text-center fw-semibold';
            alertBox.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Posisikan wajah di kamera dan pastikan Anda berada di lokasi outlet.';
        } else if (!isGpsValid) {
            alertBox.className = 'alert alert-danger py-2 px-3 mt-3 mb-0 small text-center fw-semibold';
            alertBox.innerHTML = '<i class="fas fa-map-marker-alt me-1"></i> Posisi GPS di luar radius outlet. Silakan mendekat ke outlet.';
        } else {
            alertBox.className = 'alert alert-warning py-2 px-3 mt-3 mb-0 small text-center fw-semibold';
            alertBox.innerHTML = '<i class="fas fa-user-clock me-1"></i> Wajah belum terverifikasi. Sesuaikan posisi wajah di dalam lingkaran.';
        }
    }

    function checkValidationState() {
        const btnSubmit = document.getElementById('btnSubmitAbsen');
        const guideRing = document.getElementById('guideRing');

        if (isGpsValid && isFaceValid) {
            if (btnSubmit) btnSubmit.disabled = false;
            if (guideRing) {
                guideRing.classList.remove('border-white', 'border-danger');
                guideRing.classList.add('border-success');
            }
        } else {
            if (btnSubmit) btnSubmit.disabled = true;
            if (guideRing) {
                guideRing.classList.remove('border-success');
                if (!isFaceValid) {
                    guideRing.classList.add('border-white');
                }
            }
        }
        updateAlertMessage();
    }

    // --- 3. GEOLOCATION TRACKING ---
    function initGeolocation() {
        if (!navigator.geolocation) {
            isGpsValid = false;
            updateGpsBadge(false, "GPS Tidak Didukung Browser");
            checkValidationState();
            return;
        }

        navigator.geolocation.watchPosition(
            (position) => {
                userLat = position.coords.latitude;
                userLon = position.coords.longitude;

                document.getElementById('inputLat').value = userLat;
                document.getElementById('inputLong').value = userLon;

                if (outletLat === null || outletLon === null) {
                    isGpsValid = false;
                    updateGpsBadge(false, "Koordinat Outlet Belum Set");
                } else {
                    const dist = getHaversineDistance(userLat, userLon, outletLat, outletLon);
                    const roundedDist = Math.round(dist);

                    if (dist <= radiusMeter) {
                        isGpsValid = true;
                        updateGpsBadge(true, `Lokasi Valid (${roundedDist}m dari outlet)`);
                    } else {
                        isGpsValid = false;
                        updateGpsBadge(false, `Luar Radius (${roundedDist}m > max ${radiusMeter}m)`);
                    }
                }
                checkValidationState();
            },
            (error) => {
                console.error("Geolocation error:", error);
                isGpsValid = false;
                updateGpsBadge(false, "GPS Tidak Diizinkan / Mati");
                checkValidationState();
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    // --- 4. FACE-API.JS & WEBCAM LOGIC ---
    async function loadModels() {
        if (modelsLoaded) return true;

        if (typeof faceapi === 'undefined') {
            updateFaceBadge(false, "Library face-api.min.js belum ter-load");
            return false;
        }

        const pathName = window.location.pathname;
        const adminIdx = pathName.indexOf('/admin');
        const baseDir = (adminIdx !== -1) ? pathName.substring(0, adminIdx) : '';
        const absoluteModelUrl = window.location.origin + baseDir + '/assets/vendor/face-api/models/';

        const pathsToTry = [
            absoluteModelUrl,
            window.location.origin + '/mending/assets/vendor/face-api/models/',
            '../../assets/vendor/face-api/models/',
            '../assets/vendor/face-api/models/'
        ];

        let lastErr = null;
        for (let MODEL_URL of pathsToTry) {
            try {
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                modelsLoaded = true;
                console.log("Face-API models loaded successfully from:", MODEL_URL);
                return true;
            } catch (err) {
                lastErr = err;
                console.warn("Failed loading models from " + MODEL_URL + ":", err);
            }
        }

        const errDetail = lastErr ? (lastErr.message || String(lastErr)) : 'Gagal fetch model';
        updateFaceBadge(false, "Gagal Memuat Model (" + errDetail + ")");
        return false;
    }

    async function startWebcam() {
        const video = document.getElementById('webcam');
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' } 
            });
            video.srcObject = stream;
            return true;
        } catch (err) {
            console.error("Webcam error:", err);
            let errMsg = "Kamera Tidak Diizinkan / Terblokir";
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                errMsg = "Akses via IP wajib Gunakan http://localhost atau HTTPS";
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                errMsg = "Kamera sedang dipakai aplikasi lain (Zoom/OBS)";
            } else if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                errMsg = "Izin Kamera Diblokir di Browser";
            }
            updateFaceBadge(false, errMsg);
            
            const alertBox = document.getElementById('statusAlert');
            if (alertBox) {
                alertBox.className = 'alert alert-danger py-2 px-3 mt-3 mb-0 small text-center fw-bold';
                alertBox.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Gagal Membuka Kamera: ${errMsg}. Periksa izin browser Anda.`;
            }
            return false;
        }
    }

    async function startFaceDetectionLoop() {
        const video = document.getElementById('webcam');
        const canvas = document.getElementById('faceCanvas');
        if (!video || !canvas) return;

        const displaySize = { width: video.clientWidth || 400, height: video.clientHeight || 300 };
        faceapi.matchDimensions(canvas, displaySize);

        if (!storedDescriptor || !Array.isArray(storedDescriptor) || storedDescriptor.length === 0) {
            updateFaceBadge(false, "Wajah Belum Terdaftar");
            isFaceValid = false;
            checkValidationState();
            return;
        }

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

                    // Hitung Euclidean Distance antara descriptor referensi dan live descriptor
                    const distance = faceapi.euclideanDistance(storedDescriptor, Array.from(detection.descriptor));

                    if (distance <= 0.6) {
                        isFaceValid = true;
                        updateFaceBadge(true, `Wajah Match (${distance.toFixed(2)})`);
                    } else {
                        isFaceValid = false;
                        updateFaceBadge(false, `Wajah Beda (${distance.toFixed(2)})`);
                    }
                } else {
                    isFaceValid = false;
                    updateFaceBadge(false, "Mencari Wajah...");
                }
                checkValidationState();
            } catch (err) {
                console.error("Face detection interval error:", err);
            }
        }, 300);
    }

    // --- 5. INITIALIZATION ---
    initGeolocation();

    loadModels().then(loaded => {
        if (loaded) {
            startWebcam().then(camOk => {
                if (camOk) {
                    startFaceDetectionLoop();
                }
            });
        }
    });

    // --- 6. CAPTURE SNAPSHOT BEFORE SUBMIT ---
    const formAbsen = document.getElementById('formAbsen');
    if (formAbsen) {
        formAbsen.addEventListener('submit', function(e) {
            const video = document.getElementById('webcam');
            const fotoInput = document.getElementById('fotoBase64');
            if (video && fotoInput) {
                const snapCanvas = document.createElement('canvas');
                snapCanvas.width = video.videoWidth || 640;
                snapCanvas.height = video.videoHeight || 480;
                const ctx = snapCanvas.getContext('2d');
                ctx.drawImage(video, 0, 0, snapCanvas.width, snapCanvas.height);
                fotoInput.value = snapCanvas.toDataURL('image/jpeg', 0.85);
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
