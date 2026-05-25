<?php
// PATH KE CONFIG HARUS DISESUAIKAN (dari admin/staff/ ke includes/)
require_once '../../../includes/config.php'; 

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh mengakses formulir.
checkAuth([1, 2]); 

$user_role_id = $_SESSION['role_id'];
$conn = connectDB();

// --- Tentukan Role Mana yang Boleh Dibuat ---
$allowed_new_roles = ($user_role_id == 1) ? [2, 3] : [3]; // Owner: HRD/Karyawan | HRD: Karyawan
$target_roles = [];

if (!empty($allowed_new_roles)) {
    $role_ids_str = implode(',', $allowed_new_roles);
    $sql_roles = "SELECT id_role, nama_role FROM roles WHERE id_role IN ({$role_ids_str})";
    $result_roles = $conn->query($sql_roles);
    while ($row = $result_roles->fetch_assoc()) {
        $target_roles[] = $row;
    }
}

// --- PROSES FORM SUBMISSION (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'tambah_user') {
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $target_role_id = (int)$_POST['role_id']; 
    
    $conn->begin_transaction();
    
    try {
        // Validasi Otorisasi Keamanan (PENTING!)
        if (!in_array($target_role_id, $allowed_new_roles)) {
            throw new Exception("Izin ditolak untuk membuat peran ini.");
        }
        
        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            throw new Exception("Data wajib harus diisi.");
        }
        
        // Cek duplikasi Username
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->bind_result($user_count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($user_count > 0) {
            throw new Exception("Username ini sudah digunakan.");
        }
        
        // --- 1. INSERT ke Tabel users ---
        $hashed_password = hashPassword($password);
        $sql_user = "INSERT INTO users (username, password, nama_lengkap, id_role) VALUES (?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("sssi", $username, $hashed_password, $nama_lengkap, $target_role_id);
        $stmt_user->execute() or throw new Exception($stmt_user->error);
        $user_id = $conn->insert_id; 
        $stmt_user->close();
        
        // --- 3. (Opsional) INSERT ke Detail Karyawan jika Role = Karyawan (3) ---
        if ($target_role_id == 3) {
            $tanggal_bergabung = $_POST['tanggal_bergabung'] ?? date('Y-m-d');
            $no_telepon = trim($_POST['no_telepon'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            
            $sql_detail = "INSERT INTO karyawan_details (user_id, tanggal_bergabung, no_telepon, alamat) 
                           VALUES (?, ?, ?, ?)";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("isss", $user_id, $tanggal_bergabung, $no_telepon, $alamat);
            $stmt_detail->execute() or throw new Exception($stmt_detail->error);
            $stmt_detail->close();
        }

        $conn->commit();
        
        // Set pesan sukses di sesi dan redirect
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = "Akun berhasil dibuat!";
        header("Location: ../kelola_karyawan.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        // Set pesan error di sesi dan redirect
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Gagal: " . $e->getMessage();
        header("Location: ../kelola_karyawan.php");
        exit;
    }
}
$conn->close(); 
?>

<div class="modal fade" id="modalTambahUser" tabindex="-1" aria-labelledby="modalTambahUserLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalTambahUserLabel"><i class="fas fa-user-plus"></i> Tambah Akun Baru</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        
        <form action="staff/modal_tambah_user.php" method="post">
            <input type="hidden" name="action" value="tambah_user">
          
          <fieldset class="mb-4">
            <legend class="text-primary fs-6 fw-bold">Target Peran & Login</legend>
            
            <div class="mb-3">
                <label for="role_id" class="form-label">Pilih Peran Akun Baru*</label>
                
                <?php if ($user_role_id == 1 && count($target_roles) > 1): // OWNER bisa pilih HRD atau Karyawan ?>
                    <select class="form-select" id="role_id_modal" name="role_id" required>
                        <option value="">Pilih Peran</option>
                        <?php foreach ($target_roles as $role): ?>
                            <option value="<?php echo $role['id_role']; ?>">
                                <?php echo htmlspecialchars($role['nama_role']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($user_role_id == 2): // HRD hanya bisa tambah Karyawan ?>
                    <input type="hidden" name="role_id" value="3">
                    <input type="text" class="form-control" value="Karyawan" disabled>
                    <div class="form-text text-info">Anda hanya diizinkan membuat akun Karyawan.</div>
                <?php else: ?>
                    <div class="alert alert-warning">Tidak ada peran yang tersedia.</div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap*</label>
                <input type="text" class="form-control" id="nama_lengkap_modal" name="nama_lengkap" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username*</label>
                    <input type="text" class="form-control" id="username_modal" name="username" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password*</label>
                    <input type="password" class="form-control" id="password_modal" name="password" required>
                </div>
            </div>
          </fieldset>

          <fieldset class="mb-4 border p-3 rounded" id="detailKaryawanModal">
                <legend class="text-primary fs-6 fw-bold">Detail Karyawan</legend>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tanggal_bergabung" class="form-label">Tgl. Bergabung</label>
                        <input type="date" class="form-control" id="tanggal_bergabung_modal" name="tanggal_bergabung" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="no_telepon" class="form-label">Nomor Telepon</label>
                        <input type="text" class="form-control" id="no_telepon_modal" name="no_telepon">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat Lengkap</label>
                    <textarea class="form-control" id="alamat_modal" name="alamat" rows="2"></textarea>
                </div>
          </fieldset>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Akun</button>
          </div>
        </form>

      </div>
      
    </div>
  </div>
</div>