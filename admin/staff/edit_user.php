<?php
session_start();
require_once '../../includes/config.php';
checkAuth([1, 2]); // Hanya Owner & HRD

$conn = connectDB();
$id = $_GET['id'] ?? 0;
$my_role_id = $_SESSION['id_role'];

// 1. AMBIL DATA USER & KARYAWAN LENGKAP
$sql = "SELECT u.*, ur.id_role, k.*, k.id_outlet as outlet_id
        FROM users u 
        JOIN user_roles ur ON u.id_user = ur.id_user 
        LEFT JOIN karyawan k ON u.id_user = k.id_user
        WHERE u.id_user = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['form_status'] = 'error';
    $_SESSION['form_message'] = 'User tidak ditemukan.';
    header("Location: manajemen_user.php");
    exit;
}

// 2. PROTEKSI KEAMANAN (HRD tidak boleh edit Owner/HRD lain)
if ($my_role_id == 2 && in_array($user['id_role'], [1, 2])) {
    $_SESSION['form_status'] = 'error';
    $_SESSION['form_message'] = 'Anda tidak memiliki akses mengedit akun ini.';
    header("Location: manajemen_user.php");
    exit;
}

// 3. PROSES UPDATE (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Data Login
        $username     = trim($_POST['username']);
        $is_active    = $_POST['is_active'];
        $new_password = $_POST['password'];
        $new_pin      = $_POST['pin_transaksi'];

        // Data Karyawan
        $nama_lengkap  = trim($_POST['nama_lengkap']);
        $outlet_id     = $_POST['outlet_id'];
        $no_hp         = trim($_POST['no_telepon']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $alamat        = trim($_POST['alamat']);

        // Data Bank & NIK
        $nik_ktp       = trim($_POST['nik_ktp']);
        $nama_bank     = trim($_POST['nama_bank']);
        $no_rekening   = trim($_POST['no_rekening']);

        if (empty($nama_lengkap) || empty($username)) {
            throw new Exception("Nama Lengkap dan Username wajib diisi.");
        }

        $conn->begin_transaction();

        // A. UPDATE TABEL USERS
        $sql_u = "UPDATE users SET username=?, is_active=?";
        $params_u = [$username, $is_active];
        $types_u = "si";

        if (!empty($new_password)) {
            if (strlen($new_password) < 6) throw new Exception("Password baru minimal 6 karakter.");
            $sql_u .= ", password=?";
            $params_u[] = password_hash($new_password, PASSWORD_DEFAULT);
            $types_u .= "s";
        }

        if (!empty($new_pin)) {
            if (!preg_match('/^[0-9]{6}$/', $new_pin)) throw new Exception("PIN baru harus 6 angka.");
            $sql_u .= ", pin=?";
            $params_u[] = $new_pin;
            $types_u .= "s";
        }

        $sql_u .= " WHERE id_user=?";
        $params_u[] = $id;
        $types_u .= "i";

        $stmt_u = $conn->prepare($sql_u);
        $stmt_u->bind_param($types_u, ...$params_u);
        if (!$stmt_u->execute()) throw new Exception("Gagal update user login.");

        // B. UPDATE TABEL KARYAWAN
        $cek_k = $conn->query("SELECT id_karyawan FROM karyawan WHERE id_user = $id");

        if ($cek_k->num_rows > 0) {
            $stmt_k = $conn->prepare("UPDATE karyawan SET nama_lengkap=?, id_outlet=?, no_hp=?, jenis_kelamin=?, alamat=?, nik_ktp=?, nama_bank=?, no_rekening=? WHERE id_user=?");
            $stmt_k->bind_param("sissssssi", $nama_lengkap, $outlet_id, $no_hp, $jenis_kelamin, $alamat, $nik_ktp, $nama_bank, $no_rekening, $id);
        } else {
            $stmt_k = $conn->prepare("INSERT INTO karyawan (id_user, nama_lengkap, id_outlet, no_hp, jenis_kelamin, alamat, nik_ktp, nama_bank, no_rekening) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_k->bind_param("isissssss", $id, $nama_lengkap, $outlet_id, $no_hp, $jenis_kelamin, $alamat, $nik_ktp, $nama_bank, $no_rekening);
        }

        if (!$stmt_k->execute()) throw new Exception("Gagal update profil karyawan.");

        $conn->commit();
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = 'Data akun berhasil diperbarui.';
        header("Location: manajemen_user.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = $e->getMessage();
    }
}

// Data Outlet Dropdown
$outlets = [];
$res_out = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");
while ($row = $res_out->fetch_assoc()) $outlets[] = $row;

$page_title = "Edit User";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <a href="manajemen_user.php" class="btn btn-secondary mb-3 btn-sm shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger border-left-danger shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="card shadow mb-4 border-0">
                            <div class="card-header py-3 bg-primary text-white border-0">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-lock me-2"></i> Akses Login</h6>
                            </div>
                            <div class="card-body bg-light">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-gray-800 small">Username Login*</label>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-gray-800 small">Status Akun</label>
                                    <select class="form-select" name="is_active">
                                        <option value="1" <?php echo ($user['is_active'] == 1) ? 'selected' : ''; ?>>Aktif (Bisa Login)</option>
                                        <option value="0" <?php echo ($user['is_active'] == 0) ? 'selected' : ''; ?>>Non-Aktif (Dibekukan)</option>
                                    </select>
                                </div>
                                <hr>
                                <div class="alert alert-warning border small text-dark mb-3 p-2">
                                    <i class="fas fa-info-circle me-1"></i> Kosongkan jika tidak ingin mengubah <b>Password</b> / <b>PIN</b>.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-gray-800 small">Ganti Password</label>
                                    <input type="password" class="form-control" name="password" placeholder="Minimal 6 Karakter">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-gray-800 small">Ganti PIN (POS)</label>
                                    <input type="text" class="form-control" name="pin_transaksi" placeholder="6 Angka" maxlength="6" pattern="[0-9]{6}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card shadow mb-4 border-0">
                            <div class="card-header py-3 bg-success text-white border-0">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-user-tie me-2"></i> Biodata Pegawai</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Nama Lengkap (Sesuai KTP)*</label>
                                        <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Penempatan Cabang*</label>
                                        <select class="form-select" name="outlet_id" required>
                                            <option value="">-- Pilih Cabang --</option>
                                            <?php foreach ($outlets as $o): ?>
                                                <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($user['outlet_id'] == $o['id_outlet']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($o['nama_outlet']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Nomor HP / WhatsApp</label>
                                        <input type="text" class="form-control" name="no_telepon" value="<?php echo htmlspecialchars($user['no_hp'] ?? ''); ?>" placeholder="08...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Jenis Kelamin</label>
                                        <select class="form-select" name="jenis_kelamin">
                                            <option value="">-- Pilih --</option>
                                            <option value="L" <?php echo (isset($user['jenis_kelamin']) && $user['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="P" <?php echo (isset($user['jenis_kelamin']) && $user['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-gray-800 small">Alamat Domisili</label>
                                    <textarea class="form-control" name="alamat" rows="2" placeholder="Alamat lengkap..."><?php echo htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                                </div>

                                <h6 class="text-success fw-bold mt-4 mb-3 small text-uppercase border-bottom pb-2"><i class="fas fa-wallet me-2"></i>Data Administratif & Bank</h6>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">NIK KTP</label>
                                        <input type="text" class="form-control" name="nik_ktp" value="<?php echo htmlspecialchars($user['nik_ktp'] ?? ''); ?>" placeholder="16 Digit NIK" maxlength="20">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Nama Bank</label>
                                        <select class="form-select" name="nama_bank">
                                            <option value="">-- Pilih Bank --</option>
                                            <?php
                                            $banks = ['BCA', 'Mandiri', 'BRI', 'BNI', 'BSI'];
                                            foreach ($banks as $b) {
                                                $selected = (isset($user['nama_bank']) && $user['nama_bank'] == $b) ? 'selected' : '';
                                                echo "<option value=\"$b\" $selected>$b</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold text-gray-800 small">Nomor Rekening</label>
                                        <input type="text" class="form-control" name="no_rekening" value="<?php echo htmlspecialchars($user['no_rekening'] ?? ''); ?>" placeholder="Contoh: 1234567890">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-success px-5 fw-bold shadow">
                                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>