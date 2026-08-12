# Spesifikasi Desain: Fitur Face Recognition & Geolocation Absensi Karyawan

**Tanggal:** 12 Agustus 2026  
**Status:** Approved  
**Sistem:** Mending Laundry (PHP Native + MySQL)  

---

## 1. Ringkasan Fitur
Menambahkan fitur presensi mandiri karyawan berbasis **Face Recognition** (pencocokan wajah real-time) dan **Geolocation GPS** (pencocokan radius outlet) menggunakan smartphone masing-masing. Fitur ini dirancang untuk memastikan keabsahan data kehadiran karyawan yang berdampak langsung pada perhitungan penggajian (*payroll*).

---

## 2. Arsitektur & Perubahan Database (MySQL)

### 2.1 Perubahan Tabel Database

#### A. Tabel `outlets`
Menambahkan kolom koordinat dan batas radius penempatan karyawan:
- `latitude`: `DECIMAL(10, 8) NULL` (misal: `-7.25044500`)
- `longitude`: `DECIMAL(11, 8) NULL` (misal: `112.76884500`)
- `radius_meter`: `INT NOT NULL DEFAULT 50` (radius batas absen dalam meter)

#### B. Tabel `karyawan`
Menambahkan kolom data referensi sampel wajah yang didaftarkan oleh HRD:
- `foto_referensi`: `VARCHAR(255) NULL` (path file foto referensi di `uploads/karyawan/`)
- `face_descriptor`: `TEXT NULL` (string JSON penampung 128 float vector ekstraksi wajah)

#### C. Tabel `absensi`
Menambahkan kolom bukti audit snapshot foto selfie & titik GPS lokasi saat absen:
- `foto_masuk`: `VARCHAR(255) NULL` (path snapshot saat absen masuk)
- `foto_pulang`: `VARCHAR(255) NULL` (path snapshot saat absen pulang)
- `lat_masuk`: `DECIMAL(10, 8) NULL`
- `long_masuk`: `DECIMAL(11, 8) NULL`
- `lat_pulang`: `DECIMAL(10, 8) NULL`
- `long_pulang`: `DECIMAL(11, 8) NULL`
- `status_verifikasi`: `ENUM('valid', 'invalid') NOT NULL DEFAULT 'valid'`

---

## 3. Komponen Sistem & Workflow

### 3.1 Pendaftaran Wajah Referensi (HRD / Admin)
- **Halaman:** `admin/kelola_karyawan.php`
- **Aksi:** HRD dapat mengambil foto langsung dari webcam/kamera HP atau memilih foto file karyawan.
- **Proses JS:** `face-api.js` mengekstrak vector descriptor (128 angka float) di client browser, lalu mengirimkan foto referensi + JSON string descriptor ke server untuk disimpan di tabel `karyawan`.

### 3.2 Presensi Mandiri Karyawan (Mobile Web)
- **Halaman Baru:** `admin/staff/absen.php`
- **Izin Browser:** Meminta izin akses Kamera WebRTC (`navigator.mediaDevices.getUserMedia`) & GPS Location (`navigator.geolocation`).
- **Verifikasi Real-time:**
  1. **GPS Check:** Menghitung jarak lokasi HP karyawan ke titik outlet menggunakan rumus Haversine. Jika `jarak > radius_meter`, status menampilkan alert kuning/merah dan mengunci tombol absen.
  2. **Face Match Check:** `face-api.js` membandingkan live video stream dengan `face_descriptor` referensi milik karyawan. Jika `euclidean_distance <= 0.6`, status berubah hijau "Terverifikasi".
- **Eksekusi:** Karyawan mengeklik tombol "Kirim Absensi Masuk / Pulang". Snapshot video frame ditangkap dalam bentuk Base64 JPEG dan dikirim via AJAX / POST ke `proses_absensi.php`.

### 3.3 Penanganan Jika Invalid / Gagal Match
- Karyawan langsung mendapat notifikasi/alert di layar kamera tanpa perlu reload halaman.
- Tombol absen tetap dikunci sampai posisi wajah & lokasi GPS memenuhi syarat valid.

### 3.4 Laporan Kehadiran & Payroll
- **Halaman:** `admin/laporan_kehadiran.php`
- **Aturan Payroll:** Hanya absensi dengan status `valid` yang terhitung sebagai total hari kerja karyawan pada periode bulan berjalan.
- **Fitur Audit:** HRD/Owner dapat melihat popup preview foto snapshot & koordinat GPS saat karyawan absen masuk dan pulang.

---

## 4. Spesifikasi Teknis & Keamanan

### 4.1 Library & Aset
- Menambahkan library client-side: `assets/vendor/face-api/face-api.min.js`
- Menambahkan folder weights model: `assets/vendor/face-api/models/` (`ssd_mobilenetv1_model`, `face_landmark_68_model`, `face_recognition_model`)

### 4.2 Rumus Jarak GPS (Haversine Formula)
Digunakan di JavaScript (client) untuk respon cepat dan di PHP (server) untuk verifikasi ulang:

$$d = 2R \cdot \arcsin\left(\sqrt{\sin^2\left(\frac{\Delta\phi}{2}\right) + \cos(\phi_1)\cos(\phi_2)\sin^2\left(\frac{\Delta\lambda}{2}\right)}\right)$$
*di mana $R = 6.371.000$ meter.*

### 4.3 Safeguard Server-side (`proses_absensi.php`)
- Memvalidasi token CSRF session.
- Mengubah string Base64 snapshot menjadi file image `.jpg` terkompresi (max 640px) di directory `uploads/absensi/YYYY-MM/`.
- Memvalidasi ulang rumus Haversine jarak GPS di server PHP sebelum menyimpan record ke database.

---

## 5. Rencana Pengujian
1. **Pengujian Pendaftaran HRD:** Memastikan foto referensi & 128-float face descriptor tersimpan dengan benar di DB.
2. **Pengujian Radius GPS:** Karyawan mencoba absen di luar radius outlet (gagal) vs di dalam radius (berhasil).
3. **Pengujian Wajah:** Karyawan mencoba absen dengan wajah orang lain (gagal) vs wajah sendiri (berhasil).
4. **Pengujian Laporan Payroll:** Memastikan hanya absensi valid yang dihitung di halaman `laporan_kehadiran.php`.
