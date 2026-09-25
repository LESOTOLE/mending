<?php
require_once '../../includes/config.php';

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh mengakses halaman ini.
checkAuth([1, 2]);

$page_title = "Tambah Akun Baru";
$conn = connectDB();

// AMBIL ROLE  (YANG SEDANG LOGIN)
$my_role_id = $_SESSION['id_role'];

// =========================================================================
// 1. LOGIKA HAK AKSES PEMBUATAN USER 
// =========================================================================
$allowed_role_ids = [];

if ($my_role_id == 1) {
    // OWNER: Boleh buat HRD (2), Karyawan (3), Admin Outlet (4)
    $query_role_filter = "WHERE id_role IN (2, 3, 4)";
} elseif ($my_role_id == 2) {
    // HRD: HANYA boleh buat Karyawan (3)
    $query_role_filter = "WHERE id_role = 3";
} else {
    // Jaga-jaga jika role lain masuk
    die("Akses ditolak.");
}

// Ambil Daftar Role Sesuai Izin di atas
$target_roles = [];
$res_roles = $conn->query("SELECT * FROM roles $query_role_filter ORDER BY id_role ASC");
while ($row = $res_roles->fetch_assoc()) {
    $target_roles[] = $row;
    $allowed_role_ids[] = $row['id_role'];
}

// Ambil Daftar Outlet
$outlet_list = [];
$res_outlets = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");
while ($row = $res_outlets->fetch_assoc()) {
    $outlet_list[] = $row;
}

$error = [];
$success = false;

// =========================================================================
// 2. PROSES SIMPAN DATA (POST)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Tangkap Input Akses Login
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];
    $pin          = $_POST['pin_transaksi'];
    $target_role  = (int)$_POST['role_id'];

    // Tangkap Data Karyawan Utama
    $nama_lengkap  = trim($_POST['nama_lengkap']);
    $id_outlet     = (int)($_POST['outlet_id'] ?? 0);
    $no_hp         = trim($_POST['no_telepon'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? ''); // Tangkap Jenis Kelamin
    $alamat        = trim($_POST['alamat'] ?? '');
    $tgl_gabung    = $_POST['tanggal_bergabung'] ?? date('Y-m-d');

    // Tangkap Data Administratif & Bank
    $nik_ktp      = trim($_POST['nik_ktp'] ?? '');
    $nama_bank    = trim($_POST['nama_bank'] ?? '');
    $no_rekening  = trim($_POST['no_rekening'] ?? '');

    try {
        // VALIDASI 1: Cek apakah User yang login BERHAK membuat role ini?
        if (!in_array($target_role, $allowed_role_ids)) {
            throw new Exception("PELANGGARAN AKSES: Anda tidak diizinkan membuat level pengguna ini.");
        }

        // Validasi Standar
        if (empty($username) || empty($password) || empty($nama_lengkap)) throw new Exception("Data wajib (Username, Password, Nama) harus diisi.");
        if (strlen($password) < 6) throw new Exception("Password minimal 6 karakter.");

        // Logika PIN: Jika HRD (Role ID = 2), kosongkan PIN. Jika bukan HRD, wajib 6 angka.
        if ($target_role == 2 || $target_role == 4) { // HRD atau Admin Outlet   
            $pin = "";
        } else {
            if (!preg_match('/^[0-9]{6}$/', $pin)) throw new Exception("PIN Transaksi harus 6 angka.");
        }
        // Cek Username Kembar (PERBAIKAN: Prepared Statement)
        $cek = $conn->prepare("SELECT id_user FROM users WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) throw new Exception("Username '$username' sudah digunakan. Pilih yang lain.");
        $cek->close();

        // --- MULAI TRANSAKSI DATABASE ---
        $conn->begin_transaction();

        // 1. INSERT users
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt1 = $conn->prepare("INSERT INTO users (username, password, pin, is_active, id_role) VALUES (?, ?, ?, 1, ?)");
        $stmt1->bind_param("sssi", $username, $pass_hash, $pin, $target_role);

        if (!$stmt1->execute()) throw new Exception("Gagal membuat user login: " . $stmt1->error);
        $new_id_user = $conn->insert_id;

        // JIKA ADMIN OUTLET, buat record outlet otomatis
        if ($target_role == 4) {
            $stmt_out = $conn->prepare("INSERT INTO outlets (nama_outlet) VALUES (?)");
            $stmt_out->bind_param("s", $nama_lengkap);
            if (!$stmt_out->execute()) throw new Exception("Gagal membuat cabang outlet: " . $stmt_out->error);
            $id_outlet = $conn->insert_id;
        }

        // 2. INSERT karyawan (TAMBAH jenis_kelamin ke query)
        $stmt2 = $conn->prepare("INSERT INTO karyawan (id_user, id_outlet, nama_lengkap, no_hp, jenis_kelamin, alamat, tanggal_bergabung, nik_ktp, nama_bank, no_rekening) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // i = integer, s = string (ada 2 int, 8 string = iissssssss)
        $stmt2->bind_param("iissssssss", $new_id_user, $id_outlet, $nama_lengkap, $no_hp, $jenis_kelamin, $alamat, $tgl_gabung, $nik_ktp, $nama_bank, $no_rekening);

        if (!$stmt2->execute()) throw new Exception("Gagal menyimpan profil karyawan: " . $stmt2->error);

        // --- SUKSES ---
        $conn->commit();
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = 'Akun baru berhasil dibuat.';
        $redirect_url = ($my_role_id == 2) ? '../kelola_karyawan.php' : 'manajemen_user.php';
        header("Location: $redirect_url");
        exit;
    } catch (Exception $e) {
        $conn->rollback(); // Batalkan semua jika error
        $error[] = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Tambah Akun Baru</h1>
        <a href="../kelola_karyawan.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Kembali
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <strong>Gagal Menyimpan:</strong>
            <ul class="mb-0 pl-3">
                <?php foreach ($error as $e) echo "<li>$e</li>"; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfField(); ?>
        <div class="row">
            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-primary text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-lock mr-2"></i>Akses Login</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Level Akses (Role)*</label>
                            <select name="role_id" id="role_id" class="form-control bg-light border-left-primary" required>
                                <?php if (empty($target_roles)): ?>
                                    <option value="">Tidak ada role tersedia</option>
                                <?php else: ?>
                                    <?php foreach ($target_roles as $r): ?>
                                        <option value="<?php echo $r['id_role']; ?>">
                                            <?php echo strtoupper($r['nama_role']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">
                                <?php if ($my_role_id == 2): ?>
                                    <i class="fas fa-info-circle"></i> Sebagai HRD, Anda hanya dapat menambah Karyawan.
                                <?php else: ?>
                                    <i class="fas fa-info-circle"></i> Sebagai Owner, Anda memiliki akses penuh.
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Username*</label>
                            <input type="text" name="username" class="form-control" required placeholder="Contoh: kasir_bandung">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Password*</label>
                            <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                        </div>

                        <div class="form-group" id="pin_group">
                            <label class="font-weight-bold">PIN Transaksi (POS)*</label>
                            <input type="text" name="pin_transaksi" id="pin_transaksi" class="form-control" maxlength="6" pattern="[0-9]{6}" required placeholder="123456">
                            <small class="text-danger">Wajib 6 Angka (Digunakan kasir untuk otorisasi).</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-success text-white">
                        <h6 class="m-0 font-weight-bold"><i class="fas fa-user-tie mr-2"></i>Biodata Pegawai</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Nama Lengkap / Nama Akun*</label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6" id="outlet_col">
                                <div class="form-group">
                                    <label class="font-weight-bold">Penempatan Outlet*</label>
                                    <select name="outlet_id" id="outlet_dropdown" class="form-control" required>
                                        <option value="">-- Pilih Cabang --</option>
                                        <?php foreach ($outlet_list as $o): ?>
                                            <option value="<?php echo $o['id_outlet']; ?>"><?php echo $o['nama_outlet']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="tgl_bergabung_col">
                                <div class="form-group">
                                    <label class="font-weight-bold">Tanggal Bergabung</label>
                                    <input type="date" name="tanggal_bergabung" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <div id="biodata_extra">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Nomor HP / WhatsApp</label>
                                    <input type="text" name="no_telepon" class="form-control" placeholder="08...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Jenis Kelamin</label>
                                    <select name="jenis_kelamin" class="form-control">
                                        <option value="">-- Pilih --</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Alamat Domisili</label>
                            <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap..."></textarea>
                        </div>

                        <h6 class="font-weight-bold text-success mt-4 border-bottom pb-2"><i class="fas fa-wallet mr-2"></i>Data Administratif & Bank</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">NIK KTP</label>
                                    <input type="text" class="form-control" name="nik_ktp" placeholder="16 Digit NIK" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Nama Bank</label>
                                    <select class="form-control" name="nama_bank">
                                        <option value="">-- Pilih Bank --</option>
                                        <option value="BCA">BCA</option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="BRI">BRI</option>
                                        <option value="BNI">BNI</option>
                                        <option value="BSI">BSI</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Nomor Rekening</label>
                                    <input type="text" class="form-control" name="no_rekening" placeholder="Contoh: 1234567890">
                                </div>
                            </div>
                        </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success btn-block btn-lg shadow mt-4">
                            <i class="fas fa-save mr-2"></i> SIMPAN DATA USER
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const roleSelect = document.getElementById('role_id');
        const pinGroup = document.getElementById('pin_group');
        const pinInput = document.getElementById('pin_transaksi');
        const tglBergabungCol = document.getElementById('tgl_bergabung_col');
        const biodataExtra = document.getElementById('biodata_extra');
        const outletCol = document.getElementById('outlet_col');
        const outletDropdown = document.getElementById('outlet_dropdown');

        function toggleVisibility() {
            // Asumsi ID Role untuk HRD adalah 2, Admin Outlet adalah 4
            if (roleSelect.value === "2" || roleSelect.value === "4") {
                // Sembunyikan bagian PIN dan matikan fungsi 'required'
                pinGroup.style.display = 'none';
                pinInput.removeAttribute('required');
                pinInput.value = ''; // Kosongkan isinya
            } else {
                // Munculkan kembali jika bukan HRD / Admin Outlet
                pinGroup.style.display = 'block';
                pinInput.setAttribute('required', 'required');
            }
            
            // Khusus Admin Outlet (4), sembunyikan Detail Biodata dan Outlet Dropdown
            if (roleSelect.value === "4") {
                if (tglBergabungCol) tglBergabungCol.style.display = 'none';
                if (biodataExtra) biodataExtra.style.display = 'none';
                if (outletCol) outletCol.style.display = 'none';
                if (outletDropdown) {
                    outletDropdown.removeAttribute('required');
                    outletDropdown.value = '';
                }
            } else {
                if (tglBergabungCol) tglBergabungCol.style.display = 'block';
                if (biodataExtra) biodataExtra.style.display = 'block';
                if (outletCol) outletCol.style.display = 'block';
                if (outletDropdown) outletDropdown.setAttribute('required', 'required');
            }
        }

        // Jalankan saat halaman pertama kali dimuat
        if (roleSelect) {
            toggleVisibility();

            // Pantau perubahan saat user memilih role lain
            roleSelect.addEventListener('change', toggleVisibility);
        }
    });
</script>
<?php include '../../includes/footer.php'; ?>