<?php
session_start();
require_once '../../includes/config.php';

checkAuth([1]);

require_once '../../includes/header.php';

$conn = connectDB();

// --- PROSES CRUD (Create, Update, Delete) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken()) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Error', 'Sesi telah kedaluwarsa atau request tidak valid. Silakan coba lagi.', 'error'); });</script>";
    } else {
    $aksi = $_POST['aksi'] ?? '';
    
    if ($aksi == 'tambah') {
        $nama = trim($_POST['nama_outlet']);
        $alamat = trim($_POST['alamat']);
        $telp = trim($_POST['no_telp']);
        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $long = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $radius = !empty($_POST['radius_meter']) ? (int)$_POST['radius_meter'] : 50;
        
        $stmt = $conn->prepare("INSERT INTO outlets (nama_outlet, alamat, no_telp, latitude, longitude, radius_meter) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddi", $nama, $alamat, $telp, $lat, $long, $radius);
        
        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Berhasil!', 'Outlet baru berhasil ditambahkan.', 'success');
                });
            </script>";
        }
    } 
    elseif ($aksi == 'edit') {
        $id = (int)$_POST['id_outlet'];
        $nama = trim($_POST['nama_outlet']);
        $alamat = trim($_POST['alamat']);
        $telp = trim($_POST['no_telp']);
        $lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $long = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $radius = !empty($_POST['radius_meter']) ? (int)$_POST['radius_meter'] : 50;
        
        $stmt = $conn->prepare("UPDATE outlets SET nama_outlet=?, alamat=?, no_telp=?, latitude=?, longitude=?, radius_meter=? WHERE id_outlet=?");
        $stmt->bind_param("sssddii", $nama, $alamat, $telp, $lat, $long, $radius, $id);
        
        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Tersimpan!', 'Data outlet berhasil diperbarui.', 'success');
                });
            </script>";
        }
    }
    elseif ($aksi == 'hapus') {
        $id = (int)$_POST['id_outlet'];
        
        // Pengecekan: Jangan izinkan hapus jika ada transaksi yang nyangkut di outlet ini
        $stmt_cek = $conn->prepare("SELECT id_transaksi FROM transaksi WHERE id_outlet = ? LIMIT 1");
        $stmt_cek->bind_param("i", $id);
        $stmt_cek->execute();
        $cek = $stmt_cek->get_result();
        if ($cek->num_rows > 0) {
             echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Gagal!', 'Tidak bisa menghapus outlet yang sudah memiliki riwayat transaksi!', 'error');
                });
            </script>";
        } else {
            $stmt = $conn->prepare("DELETE FROM outlets WHERE id_outlet=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Terhapus!', 'Outlet berhasil dihapus.', 'success');
                    });
                </script>";
            }
        }
        }
    }
}

// --- AMBIL DATA OUTLET ---
$sql = "SELECT o.*, 
               (SELECT COUNT(*) FROM karyawan k WHERE k.id_outlet = o.id_outlet) as total_karyawan,
               (SELECT COUNT(*) FROM transaksi t WHERE t.id_outlet = o.id_outlet) as total_transaksi
        FROM outlets o 
        ORDER BY o.id_outlet ASC";
$result = $conn->query($sql);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-store-alt text-primary me-2"></i> Manajemen Outlet</h1>
        <button class="btn btn-primary shadow-sm" onclick="bukaModalTambah()">
            <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Tambah Outlet Baru
        </button>
    </div>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Outlet / Cabang
                            </div>
                            <h5 class="h5 mb-0 font-weight-bold text-gray-800"><?php echo htmlspecialchars($row['nama_outlet']); ?></h5>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-store fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="small text-dark mb-1"><i class="fas fa-map-marker-alt text-danger w-15px"></i> <?php echo htmlspecialchars($row['alamat']); ?></div>
                    <div class="small text-dark mb-1"><i class="fas fa-phone text-success w-15px"></i> <?php echo htmlspecialchars($row['no_telp']); ?></div>
                    <div class="small text-dark mb-3"><i class="fas fa-crosshairs text-info w-15px"></i> GPS: <?php echo ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== '') ? htmlspecialchars($row['latitude'] . ', ' . $row['longitude']) : 'Belum set'; ?> (Radius: <?php echo (int)($row['radius_meter'] ?? 50); ?>m)</div>
                    
                    <div class="d-flex justify-content-between text-muted small mb-3 border-top pt-2">
                        <span><i class="fas fa-users"></i> <?php echo $row['total_karyawan']; ?> Staf</span>
                        <span><i class="fas fa-receipt"></i> <?php echo $row['total_transaksi']; ?> Transaksi</span>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <a href="manage_layanan.php?id_outlet=<?php echo $row['id_outlet']; ?>" class="btn btn-info btn-sm text-white font-weight-bold shadow-sm w-100 mb-2">
                            <i class="fas fa-tags me-1"></i> Atur Layanan & Harga
                        </a>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-fill" 
                                    onclick="bukaModalEdit(<?php echo $row['id_outlet']; ?>, '<?php echo addslashes($row['nama_outlet']); ?>', '<?php echo addslashes($row['alamat']); ?>', '<?php echo addslashes($row['no_telp']); ?>', '<?php echo addslashes($row['latitude'] ?? ''); ?>', '<?php echo addslashes($row['longitude'] ?? ''); ?>', <?php echo (int)($row['radius_meter'] ?? 50); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="hapusOutlet(<?php echo $row['id_outlet']; ?>, '<?php echo addslashes($row['nama_outlet']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="modal fade" id="modalOutlet" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-store me-2"></i>Tambah Outlet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="aksi" id="aksiOutlet" value="tambah">
                <input type="hidden" name="id_outlet" id="idOutlet">
                <input type="hidden" name="latitude" id="latOutlet">
                <input type="hidden" name="longitude" id="longOutlet">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Outlet / Cabang <span class="text-danger">*</span></label>
                            <input type="text" name="nama_outlet" id="namaOutlet" class="form-control" required placeholder="Contoh: Mending Pusat">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Telepon / WA</label>
                            <input type="text" name="no_telp" id="telpOutlet" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Alamat Lengkap</label>
                            <textarea name="alamat" id="alamatOutlet" class="form-control" rows="2" placeholder="Jl. Contoh No. 123, Kelurahan, Kecamatan..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Radius Absen (Meter)</label>
                            <input type="number" name="radius_meter" id="radiusOutlet" class="form-control" value="50" min="5" max="500">
                            <div class="form-text">Radius batas area absen karyawan.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Koordinat GPS Terpilih</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-crosshairs text-info"></i></span>
                                <input type="text" id="displayLatLng" class="form-control bg-light" readonly placeholder="Pilih titik di peta...">
                                <button type="button" class="btn btn-outline-secondary" onclick="getLokasiSaatIni()" title="Gunakan lokasi saya">
                                    <i class="fas fa-location-arrow"></i>
                                </button>
                            </div>
                            <div class="form-text">Klik pada peta untuk menentukan lokasi outlet.</div>
                        </div>

                        <!-- Peta Picker -->
                        <div class="col-12">
                            <label class="form-label fw-bold d-flex align-items-center gap-2">
                                <i class="fas fa-map-marked-alt text-danger"></i> Pilih Lokasi di Peta
                                <span class="badge bg-info fw-normal">Klik untuk menentukan titik</span>
                            </label>
                            <div id="mapPicker" style="height: 300px; border-radius: 10px; border: 2px solid #dee2e6; z-index: 0;"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formHapus" method="POST" style="display:none;">
    <?php echo csrfField(); ?>
    <input type="hidden" name="aksi" value="hapus">
    <input type="hidden" name="id_outlet" id="idHapus">
</form>

<?php require_once '../../includes/footer.php'; ?>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    var mapInstance = null;
    var mapMarker = null;

    // Inisialisasi peta saat modal terbuka
    document.getElementById('modalOutlet').addEventListener('shown.bs.modal', function() {
        setTimeout(function() {
            // Jika peta sudah ada, hapus dan buat ulang (fix Leaflet di modal)
            if (mapInstance) {
                mapInstance.remove();
                mapInstance = null;
                mapMarker = null;
            }

            var savedLat = parseFloat($('#latOutlet').val());
            var savedLng = parseFloat($('#longOutlet').val());
            var startLat = (!isNaN(savedLat) && savedLat !== 0) ? savedLat : -6.200000;
            var startLng = (!isNaN(savedLng) && savedLng !== 0) ? savedLng : 106.816666;
            var startZoom = (!isNaN(savedLat) && savedLat !== 0) ? 16 : 12;

            mapInstance = L.map('mapPicker').setView([startLat, startLng], startZoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(mapInstance);

            // Jika sudah ada koordinat tersimpan, taruh marker
            if (!isNaN(savedLat) && savedLat !== 0) {
                mapMarker = L.marker([savedLat, savedLng], { draggable: true }).addTo(mapInstance);
                mapMarker.bindPopup('Lokasi Outlet').openPopup();
                updateKoordinatDisplay(savedLat, savedLng);
                mapMarker.on('dragend', function(e) {
                    var pos = e.target.getLatLng();
                    simpanKoordinat(pos.lat, pos.lng);
                });
            }

            // Klik di peta → set/pindah marker
            mapInstance.on('click', function(e) {
                var lat = e.latlng.lat;
                var lng = e.latlng.lng;

                if (mapMarker) {
                    mapMarker.setLatLng([lat, lng]);
                } else {
                    mapMarker = L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
                    mapMarker.bindPopup('Lokasi Outlet');
                    mapMarker.on('dragend', function(ev) {
                        var pos = ev.target.getLatLng();
                        simpanKoordinat(pos.lat, pos.lng);
                    });
                }
                simpanKoordinat(lat, lng);
            });
        }, 300); // tunggu modal selesai render
    });

    // Hancurkan peta saat modal ditutup
    document.getElementById('modalOutlet').addEventListener('hidden.bs.modal', function() {
        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
            mapMarker = null;
        }
    });

    function simpanKoordinat(lat, lng) {
        $('#latOutlet').val(lat.toFixed(8));
        $('#longOutlet').val(lng.toFixed(8));
        updateKoordinatDisplay(lat, lng);
    }

    function updateKoordinatDisplay(lat, lng) {
        $('#displayLatLng').val(lat.toFixed(6) + ', ' + lng.toFixed(6));
    }

    function getLokasiSaatIni() {
        if (!navigator.geolocation) {
            Swal.fire('Error', 'Browser tidak mendukung Geolocation.', 'error');
            return;
        }
        Swal.fire({ title: 'Mengambil lokasi...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
        navigator.geolocation.getCurrentPosition(function(position) {
            Swal.close();
            var lat = position.coords.latitude;
            var lng = position.coords.longitude;
            simpanKoordinat(lat, lng);
            if (mapInstance) {
                mapInstance.setView([lat, lng], 17);
                if (mapMarker) {
                    mapMarker.setLatLng([lat, lng]);
                } else {
                    mapMarker = L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
                    mapMarker.bindPopup('Lokasi Anda').openPopup();
                    mapMarker.on('dragend', function(e) {
                        var pos = e.target.getLatLng();
                        simpanKoordinat(pos.lat, pos.lng);
                    });
                }
            }
        }, function() {
            Swal.fire('Gagal', 'Tidak bisa mengambil lokasi GPS. Pastikan izin lokasi aktif di browser.', 'error');
        });
    }

    function bukaModalTambah() {
        $('#modalTitle').html('<i class="fas fa-store me-2"></i>Tambah Outlet Baru');
        $('#aksiOutlet').val('tambah');
        $('#idOutlet').val('');
        $('#namaOutlet').val('');
        $('#telpOutlet').val('');
        $('#alamatOutlet').val('');
        $('#latOutlet').val('');
        $('#longOutlet').val('');
        $('#displayLatLng').val('');
        $('#radiusOutlet').val('50');
        $('#modalOutlet').modal('show');
    }

    function bukaModalEdit(id, nama, alamat, telp, lat, long, radius) {
        $('#modalTitle').html('<i class="fas fa-edit me-2"></i>Edit Data Outlet');
        $('#aksiOutlet').val('edit');
        $('#idOutlet').val(id);
        $('#namaOutlet').val(nama);
        $('#alamatOutlet').val(alamat);
        $('#telpOutlet').val(telp);
        $('#latOutlet').val(lat || '');
        $('#longOutlet').val(long || '');
        $('#displayLatLng').val(lat && long ? parseFloat(lat).toFixed(6) + ', ' + parseFloat(long).toFixed(6) : '');
        $('#radiusOutlet').val(radius || 50);
        $('#modalOutlet').modal('show');
    }

    function hapusOutlet(id, nama) {
        Swal.fire({
            title: 'Hapus Outlet?',
            html: 'Anda yakin ingin menghapus <b>' + nama + '</b>?<br><small class="text-danger">Aksi ini tidak bisa dibatalkan jika belum ada transaksi.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                $('#idHapus').val(id);
                $('#formHapus').submit();
            }
        });
    }
</script>