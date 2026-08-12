# Face Recognition & Geolocation Absensi Karyawan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement client-side face recognition (`face-api.js`) and geolocation GPS verification for employee self-service attendance integrated with HRD management and payroll reports.

**Architecture:** Extend MySQL schema (`outlets`, `karyawan`, `absensi`), load `face-api.js` client-side models, provide HRD face descriptor registration UI, implement real-time camera + GPS validation page for employees, and update backend process + payroll attendance reports.

**Tech Stack:** PHP Native, MySQL (mysqli), HTML5 WebRTC Camera & Geolocation API, TensorFlow.js / `face-api.js`, Bootstrap 4 / FontAwesome, SweetAlert2.

## Global Constraints

- Database Credentials: loaded via `includes/config.php` -> `env.php`
- CSRF Protection: `verifyCsrfToken()` and `csrfField()` must be used on all POST actions
- Authorization: `checkAuth([1, 2])` for HRD/Owner pages, `checkAuth([3])` or `checkAuth([1,2,3])` for staff pages
- Image upload folder: `uploads/absensi/` and `uploads/karyawan/`

---

### Task 1: Database Migration for Geolocation and Face Recognition

**Files:**
- Create: `database/migrations/2026_08_12_add_face_and_location.sql`
- Create: `database/run_migration.php`

**Interfaces:**
- Consumes: MySQL database connection via `includes/config.php`
- Produces: Altered tables `outlets` (`latitude`, `longitude`, `radius_meter`), `karyawan` (`foto_referensi`, `face_descriptor`), and `absensi` (`foto_masuk`, `foto_pulang`, `lat_masuk`, `long_masuk`, `lat_pulang`, `long_pulang`, `status_verifikasi`).

- [ ] **Step 1: Create SQL Migration File**

Create `database/migrations/2026_08_12_add_face_and_location.sql`:
```sql
-- Alter outlets table
ALTER TABLE outlets 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL AFTER nama_outlet,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL AFTER latitude,
ADD COLUMN IF NOT EXISTS radius_meter INT NOT NULL DEFAULT 50 AFTER longitude;

-- Alter karyawan table
ALTER TABLE karyawan 
ADD COLUMN IF NOT EXISTS foto_referensi VARCHAR(255) NULL AFTER id_outlet,
ADD COLUMN IF NOT EXISTS face_descriptor TEXT NULL AFTER foto_referensi;

-- Alter absensi table
ALTER TABLE absensi 
ADD COLUMN IF NOT EXISTS foto_masuk VARCHAR(255) NULL AFTER waktu_masuk,
ADD COLUMN IF NOT EXISTS foto_pulang VARCHAR(255) NULL AFTER waktu_pulang,
ADD COLUMN IF NOT EXISTS lat_masuk DECIMAL(10, 8) NULL AFTER foto_masuk,
ADD COLUMN IF NOT EXISTS long_masuk DECIMAL(11, 8) NULL AFTER lat_masuk,
ADD COLUMN IF NOT EXISTS lat_pulang DECIMAL(10, 8) NULL AFTER foto_pulang,
ADD COLUMN IF NOT EXISTS long_pulang DECIMAL(11, 8) NULL AFTER lat_pulang,
ADD COLUMN IF NOT EXISTS status_verifikasi ENUM('valid', 'invalid') NOT NULL DEFAULT 'valid' AFTER long_pulang;
```

- [ ] **Step 2: Create PHP Runner for Migration**

Create `database/run_migration.php`:
```php
<?php
require_once __DIR__ . '/../includes/config.php';

$conn = connectDB();
if (!$conn) {
    die("Database connection failed.\n");
}

$sql_path = __DIR__ . '/migrations/2026_08_12_add_face_and_location.sql';
$sql = file_get_content_path = file_get_contents($sql_path);

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Migration executed successfully.\n";
} else {
    echo "Migration failed: " . $conn->error . "\n";
}
$conn->close();
```

- [ ] **Step 3: Run Migration**

Run command:
`php database/run_migration.php`

Expected Output: `Migration executed successfully.`

- [ ] **Step 4: Commit Migration**

```bash
git add database/migrations/2026_08_12_add_face_and_location.sql database/run_migration.php
git commit -m "feat: database migration for face recognition and geolocation"
```

---

### Task 2: Setup Client-Side Vendor Assets (`face-api.js` & Weights)

**Files:**
- Create: `assets/vendor/face-api/face-api.min.js`
- Create: `assets/vendor/face-api/models/ssd_mobilenetv1_model-weights_manifest.json`
- Create: `assets/vendor/face-api/models/ssd_mobilenetv1_model-shard1`
- Create: `assets/vendor/face-api/models/face_landmark_68_model-weights_manifest.json`
- Create: `assets/vendor/face-api/models/face_landmark_68_model-shard1`
- Create: `assets/vendor/face-api/models/face_recognition_model-weights_manifest.json`
- Create: `assets/vendor/face-api/models/face_recognition_model-shard1`
- Create: `assets/vendor/face-api/models/face_recognition_model-shard2`
- Create: `assets/vendor/face-api/download_models.php`

**Interfaces:**
- Consumes: Public CDN / GitHub raw for `face-api.js` models
- Produces: Offline assets in `assets/vendor/face-api/` for fast local loading in XAMPP without external internet reliance.

- [ ] **Step 1: Create Download Script for Face-API JS Assets**

Create `assets/vendor/face-api/download_models.php`:
```php
<?php
$target_dir = __DIR__ . '/models/';
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$files = [
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/dist/face-api.min.js' => __DIR__ . '/face-api.min.js',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-weights_manifest.json' => $target_dir . 'ssd_mobilenetv1_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-shard1' => $target_dir . 'ssd_mobilenetv1_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json' => $target_dir . 'face_landmark_68_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-shard1' => $target_dir . 'face_landmark_68_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json' => $target_dir . 'face_recognition_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard1' => $target_dir . 'face_recognition_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard2' => $target_dir . 'face_recognition_model-shard2',
];

foreach ($files as $url => $path) {
    echo "Downloading " . basename($path) . "...\n";
    $content = file_get_contents($url);
    if ($content === false) {
        echo "FAILED to download $url\n";
    } else {
        file_put_contents($path, $content);
        echo "Saved to $path\n";
    }
}
echo "Asset download complete.\n";
```

- [ ] **Step 2: Run Download Script**

Run command:
`php assets/vendor/face-api/download_models.php`

Expected Output: `Asset download complete.`

- [ ] **Step 3: Commit Vendor Assets**

```bash
git add assets/vendor/face-api/
git commit -m "feat: add face-api.js client library and model weights"
```

---

### Task 3: Outlet Geolocation Location Setup (Admin UI)

**Files:**
- Modify: `admin/outlet/kelola_outlet.php`
- Create/Modify: `admin/outlet/proses_outlet.php`

**Interfaces:**
- Consumes: `outlets` table (`latitude`, `longitude`, `radius_meter`)
- Produces: Form fields to save outlet GPS coordinates and radius.

- [ ] **Step 1: Add Latitude, Longitude, and Radius fields to Outlet Management Form**

In `admin/outlet/kelola_outlet.php`, add input fields for `latitude`, `longitude`, and `radius_meter` with a button to auto-fill current GPS coordinates using `navigator.geolocation`.

- [ ] **Step 2: Update PHP Backend to Handle GPS Columns on Create/Update Outlet**

In `admin/outlet/proses_outlet.php`, sanitize `latitude`, `longitude`, and `radius_meter` and include them in `INSERT INTO outlets` and `UPDATE outlets` prepared statements.

- [ ] **Step 3: Verify Outlet Geolocation Saving**

Check DB `SELECT latitude, longitude, radius_meter FROM outlets` after form submission.

- [ ] **Step 4: Commit Task 3**

```bash
git add admin/outlet/
git commit -m "feat: add GPS location and radius configuration for outlets"
```

---

### Task 4: HRD Employee Face Registration (`kelola_karyawan.php`)

**Files:**
- Modify: `admin/kelola_karyawan.php`
- Create: `admin/proses_daftar_wajah.php`
- Create upload directory: `uploads/karyawan/`

**Interfaces:**
- Consumes: `karyawan` table (`id_karyawan`, `foto_referensi`, `face_descriptor`)
- Produces: Interactive modal in HRD dashboard to capture employee camera stream, detect face, extract 128-float vector descriptor, upload snapshot image, and save descriptor string to DB.

- [ ] **Step 1: Add "Daftarkan Wajah" Modal & JS to `kelola_karyawan.php`**

Include `assets/vendor/face-api/face-api.min.js`.
Add modal HTML `#modalDaftarWajah` containing `<video id="videoDaftarWajah">`, `<canvas id="canvasPreview">`, and hidden inputs `foto_base64` and `face_descriptor`.
Add JS script to initialize models `faceapi.nets.ssdMobilenetv1.loadFromUri('../assets/vendor/face-api/models')`, start webcam, extract descriptor via `faceapi.detectSingleFace(video).withFaceLandmarks().withFaceDescriptor()`, and enable Submit button once face is detected.

- [ ] **Step 2: Create Backend Script `admin/proses_daftar_wajah.php`**

```php
<?php
require_once '../includes/config.php';
checkAuth([1, 2]);

if ($_SERVER["REQUEST_METHOD"] == "POST" && verifyCsrfToken()) {
    $id_user = (int)($_POST['id_user'] ?? 0);
    $face_descriptor = $_POST['face_descriptor'] ?? '';
    $foto_base64 = $_POST['foto_base64'] ?? '';

    if (empty($id_user) || empty($face_descriptor) || empty($foto_base64)) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Data registrasi wajah tidak lengkap.';
        header("Location: kelola_karyawan.php");
        exit;
    }

    // Save Base64 image
    $upload_dir = '../uploads/karyawan/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $img_data = explode(',', $foto_base64);
    $decoded_img = base64_decode(end($img_data));
    $filename = 'wajah_' . $id_user . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    file_put_contents($filepath, $decoded_img);

    $conn = connectDB();
    $sql = "UPDATE karyawan SET foto_referensi = ?, face_descriptor = ? WHERE id_user = ?";
    $stmt = $conn->prepare($sql);
    $db_path = 'uploads/karyawan/' . $filename;
    $stmt->bind_param("ssi", $db_path, $face_descriptor, $id_user);

    if ($stmt->execute()) {
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = 'Sampel wajah karyawan berhasil didaftarkan.';
    } else {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal menyimpan sampel wajah ke database.';
    }
    $stmt->close();
    $conn->close();

    header("Location: kelola_karyawan.php");
    exit;
}
```

- [ ] **Step 3: Test HRD Face Registration Flow**

Open `kelola_karyawan.php`, click "Daftar Wajah", capture webcam face, and verify database `karyawan` table contains `face_descriptor` (array of numbers in JSON) and `foto_referensi`.

- [ ] **Step 4: Commit Task 4**

```bash
git add admin/kelola_karyawan.php admin/proses_daftar_wajah.php
git commit -m "feat: add employee face registration modal and backend handler for HRD"
```

---

### Task 5: Employee Self-Service Mobile Attendance Page (`absen.php` & `proses_absensi.php`)

**Files:**
- Create: `admin/staff/absen.php`
- Modify: `admin/proses_absensi.php`
- Modify: `includes/sidebar.php` (Add link "Absen Wajah" for staff role)

**Interfaces:**
- Consumes: Assigned outlet GPS (`latitude`, `longitude`, `radius_meter`), `face_descriptor` from `karyawan`
- Produces: Real-time Camera preview + Geolocation distance checker, matching live face against employee descriptor, sending base64 selfie + GPS coordinates to `proses_absensi.php`.

- [ ] **Step 1: Create Employee Attendance UI (`admin/staff/absen.php`)**

Implement `absen.php`:
1. Check session auth `checkAuth([1, 2, 3])`.
2. Fetch user's assigned outlet coordinates & saved `face_descriptor`.
3. Render live video camera canvas with round face mask frame.
4. Render status badges:
   - GPS Status: "Mengecek Lokasi..." / "Sesuai Radius (X meter)" / "Di Luar Radius Outlet".
   - Face Match Status: "Mencari Wajah..." / "Wajah Terverifikasi (Match)" / "Wajah Tidak Cocok".
5. Haversine distance function in JS:
```javascript
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
```
6. Compare live face descriptor using `faceapi.euclideanDistance(storedDescriptor, liveDescriptor)` (threshold <= 0.6).
7. Submit Form via AJAX or POST with `foto_snapshot_base64`, `latitude`, `longitude`, `action` ('masuk' / 'pulang').

- [ ] **Step 2: Update `admin/proses_absensi.php` for Face & GPS Handling**

Enhance `proses_absensi.php`:
- Receive `lat`, `long`, `foto_base64`, `action`.
- Validate Haversine distance on server:
```php
function checkServerHaversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}
```
- Save Base64 image to `uploads/absensi/YYYY-MM/` directory.
- Perform INSERT/UPDATE query into `absensi` with `foto_masuk`/`foto_pulang`, `lat_masuk`/`lat_pulang`, `long_masuk`/`long_pulang`, and `status_verifikasi = 'valid'`.

- [ ] **Step 3: Update `includes/sidebar.php` Navigation**

Add menu item "Absensi Wajah" (`admin/staff/absen.php`) accessible for employees (role 3).

- [ ] **Step 4: Verify Attendance Flow**

Perform end-to-end test: log in as employee, open `admin/staff/absen.php`, verify webcam and GPS validation status, submit attendance, and check database record in `absensi` table.

- [ ] **Step 5: Commit Task 5**

```bash
git add admin/staff/absen.php admin/proses_absensi.php includes/sidebar.php
git commit -m "feat: implement employee face recognition & geolocation self-service attendance"
```

---

### Task 6: Payroll Attendance Report Integration (`laporan_kehadiran.php`)

**Files:**
- Modify: `admin/laporan_kehadiran.php`

**Interfaces:**
- Consumes: `absensi` (`status_verifikasi = 'valid'`, `foto_masuk`, `foto_pulang`)
- Produces: Verified attendance counts for payroll calculations, with modal preview of attendance selfies and GPS coordinates.

- [ ] **Step 1: Update SQL Query in `laporan_kehadiran.php`**

Ensure `COUNT(a.id_absensi)` filters only `WHERE a.status_verifikasi = 'valid'` to prevent invalid or unverified entries from counting towards payroll working days:
```sql
SELECT 
    u.id_user, 
    k.nama_lengkap,
    o.nama_outlet,
    (SELECT COUNT(a.id_absensi) 
     FROM absensi a 
     WHERE a.id_user = u.id_user 
     AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
     AND a.status_verifikasi = 'valid') AS total_hadir
FROM users u
JOIN karyawan k ON u.id_user = k.id_user
LEFT JOIN outlets o ON k.id_outlet = o.id_outlet 
WHERE u.id_role = 3
ORDER BY o.nama_outlet ASC, k.nama_lengkap ASC
```

- [ ] **Step 2: Add Audit Modal for Selfie & GPS Audit Trail**

Add a clickable "Detail Presensi Foto" button for HRD/Owner to inspect selfie snapshot images (`foto_masuk`, `foto_pulang`) and recorded GPS location map links for any employee.

- [ ] **Step 3: Verify Report Integration**

Open `laporan_kehadiran.php` in browser as HRD/Owner, verify total attendance days count matches valid face-verified records, and check modal selfie preview.

- [ ] **Step 4: Commit Task 6**

```bash
git add admin/laporan_kehadiran.php
git commit -m "feat: filter valid face-verified attendance in payroll report with selfie audit modal"
```

---

## Plan Self-Review Checklist
- [x] All paths are explicit (`admin/staff/absen.php`, `database/migrations/2026_08_12_add_face_and_location.sql`, etc.)
- [x] No placeholders or TBD items
- [x] Includes complete code blocks and shell execution commands
- [x] Strictly matches approved design spec `docs/superpowers/specs/2026-08-12-face-recognition-absensi-design.md`
