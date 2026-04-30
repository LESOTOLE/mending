<?php
require_once '../includes/config.php'; 

// OTORISASI: Semua user yang sudah login (Role 1, 2, atau 3) boleh mengakses.
checkAuth([]); 

$page_title = "Ganti Password Akun";
$user_id = $_SESSION['id_user']; // Ambil ID User dari Session untuk query nanti
$conn = connectDB();
$error = '';
$success = false;

// ... (Bagian Logika PHP ini persis sama dengan milik Anda) ...
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_baru = $_POST['konfirmasi_baru'] ?? '';
    
    try {
        // 1. Validasi Input
        if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_baru)) {
            throw new Exception("Semua field wajib diisi.");
        }
        if ($password_baru !== $konfirmasi_baru) {
            throw new Exception("Password baru dan konfirmasi tidak cocok.");
        }
        if (strlen($password_baru) < 6) {
            throw new Exception("Password baru minimal 6 karakter.");
        }

        // 2. Verifikasi Password Lama
        $sql_check = "SELECT password FROM users WHERE id_user = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $user = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if (!$user || !password_verify($password_lama, $user['password'])) {
            throw new Exception("Password lama yang Anda masukkan salah.");
        }

        // 3. Update Password Baru
        $hashed_password = hashPassword($password_baru); // Pastikan fungsi ini tersedia di config.php
        $sql_update = "UPDATE users SET password = ? WHERE id_user = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $hashed_password, $user_id);

        if (!$stmt_update->execute()) {
            throw new Exception("Gagal memperbarui password: " . $stmt_update->error);
        }
        $stmt_update->close();

        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = "Password berhasil diubah!";
        // Redirect ke halaman yang sama untuk menampilkan notifikasi dan mencegah resubmission
        header("Location: ganti_password.php"); 
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = $error;
        // Redirect juga jika error agar tidak ada form resubmission warning
        header("Location: ganti_password.php"); 
        exit;
    }
}

// Ambil notifikasi dari sesi (jika ada redirect dari POST)
$status = $_SESSION['form_status'] ?? null;
$message = $_SESSION['form_message'] ?? null;
unset($_SESSION['form_status'], $_SESSION['form_message']);

include '../includes/header.php'; 
?>

<style>
    .input-group-text { background-color: #f8f9fc; border-right: none; }
    .form-control { border-left: none; }
    .form-control:focus { box-shadow: none; border-color: #d1d3e2; }
    .input-group:focus-within .input-group-text, 
    .input-group:focus-within .form-control,
    .input-group:focus-within .btn-outline-secondary { border-color: #4e73df; }
    .btn-outline-secondary { border-left: none; border-color: #d1d3e2; color: #858796; }
    .btn-outline-secondary:hover { background-color: transparent; color: #4e73df; }
</style>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800 font-weight-bold"><?php echo $page_title; ?></h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-8 col-md-10">
            <div class="card shadow-sm border-0 rounded-lg mt-3">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="m-0 font-weight-bold"><i class="fas fa-user-shield mr-2"></i>Keamanan Akun</h5>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const status = '<?php echo $status; ?>';
                            const message = '<?php echo htmlspecialchars($message); ?>';
                            if (status && message) {
                                Swal.fire({
                                    icon: status,
                                    title: (status === 'success' ? 'Berhasil!' : 'Gagal!'),
                                    text: message,
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            }
                        });
                    </script>

                    <form method="POST" action="ganti_password.php" id="formGantiPassword">
                        
                        <div class="form-group mb-4">
                            <label for="password_lama" class="font-weight-bold text-dark small">Password Saat Ini <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
                                </div>
                                <input type="password" class="form-control" id="password_lama" name="password_lama" placeholder="Masukkan password lama" required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password_lama">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="mb-4">
                        
                        <div class="form-group mb-3">
                            <label for="password_baru" class="font-weight-bold text-dark small">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-lock text-primary"></i></span>
                                </div>
                                <input type="password" class="form-control" id="password_baru" name="password_baru" placeholder="Buat password baru" required minlength="6">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password_baru">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-2"><i class="fas fa-info-circle mr-1"></i>Password minimal terdiri dari 6 karakter.</small>
                        </div>
                        
                        <div class="form-group mb-5">
                            <label for="konfirmasi_baru" class="font-weight-bold text-dark small">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-check-double text-success"></i></span>
                                </div>
                                <input type="password" class="form-control" id="konfirmasi_baru" name="konfirmasi_baru" placeholder="Ulangi password baru" required minlength="6">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#konfirmasi_baru">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="password_match_msg" class="mt-2 small font-weight-bold"></div>
                        </div>

                        <button type="submit" id="btn_submit" class="btn btn-primary btn-block btn-lg shadow-sm font-weight-bold">
                            <i class="fas fa-sync-alt mr-2"></i> PERBARUI PASSWORD
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Logika Show/Hide Password
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const inputField = document.querySelector(targetId);
            const icon = this.querySelector('i');

            if (inputField.type === 'password') {
                inputField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // 2. Logika Validasi Real-Time (Password Match)
    const passBaru = document.getElementById('password_baru');
    const passConfirm = document.getElementById('konfirmasi_baru');
    const msgBox = document.getElementById('password_match_msg');
    const btnSubmit = document.getElementById('btn_submit');

    function checkPasswordMatch() {
        // Hanya cek jika kedua field sudah diisi
        if (passBaru.value.length > 0 && passConfirm.value.length > 0) {
            if (passBaru.value === passConfirm.value) {
                msgBox.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Password cocok.</span>';
                btnSubmit.disabled = false;
            } else {
                msgBox.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Password konfirmasi tidak cocok!</span>';
                btnSubmit.disabled = true;
            }
        } else {
            msgBox.innerHTML = ''; // Kosongkan pesan jika salah satu field kosong
            btnSubmit.disabled = false; // Biarkan HTML5 minlength validation yang bekerja
        }
    }

    // Jalankan pengecekan setiap kali user mengetik di kedua kolom tersebut
    passBaru.addEventListener('keyup', checkPasswordMatch);
    passConfirm.addEventListener('keyup', checkPasswordMatch);
});
</script>