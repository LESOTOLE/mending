<?php
require_once '../includes/config.php'; 

// OTORISASI: Hanya Owner (1) dan HRD (2) yang boleh menambahkan.
checkAuth([1, 2]); 

$page_title = "Tambah Akun Karyawan Baru";
$conn = connectDB();
$outlet_list = [];
$error = '';

try {
    // Ambil daftar outlet untuk dropdown
    $sql_outlets = "SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC";
    $result_outlets = $conn->query($sql_outlets);
    if ($result_outlets) {
        while ($row = $result_outlets->fetch_assoc()) {
            $outlet_list[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Gagal memuat daftar outlet: " . $e->getMessage();
}

// Logika POST untuk menyimpan data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken()) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Sesi telah kedaluwarsa atau request tidak valid. Silakan coba lagi.";
        header("Location: tambah_karyawan.php");
        exit;
    }

    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $outlet_id = $_POST['outlet_id'] ?? null;
    $role_id_new = 3; // Karyawan (Fixed)

    if (empty($nama_lengkap) || empty($username) || empty($password) || empty($outlet_id)) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Semua field wajib diisi.";
    } else {
        try {
            // Hash Password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Mulai Transaksi
            $conn->begin_transaction();

            // 1. INSERT ke tabel users (PERBAIKAN: Hanya kolom yang ada di tabel users)
            $sql_user = "INSERT INTO users (username, password, is_active, id_role) 
                         VALUES (?, ?, 1, ?)";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("ssi", $username, $hashed_password, $role_id_new);

            if (!$stmt_user->execute()) {
                throw new Exception("Gagal menambahkan user: " . $stmt_user->error);
            }
            
            $new_id_user = $conn->insert_id;
            $stmt_user->close();

            // 2. INSERT ke tabel karyawan (PERBAIKAN: Data profil masuk ke tabel karyawan)
            $sql_karyawan = "INSERT INTO karyawan (id_user, id_outlet, nama_lengkap) VALUES (?, ?, ?)";
            $stmt_karyawan = $conn->prepare($sql_karyawan);
            $stmt_karyawan->bind_param("iis", $new_id_user, $outlet_id, $nama_lengkap);

            if (!$stmt_karyawan->execute()) {
                throw new Exception("Gagal menyimpan profil karyawan: " . $stmt_karyawan->error);
            }
            $stmt_karyawan->close();

            $conn->commit();
            $_SESSION['form_status'] = 'success';
            $_SESSION['form_message'] = "Akun karyawan {$nama_lengkap} berhasil ditambahkan.";
            header("Location: kelola_karyawan.php"); // Redirect ke halaman kelola
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['form_status'] = 'error';
            $_SESSION['form_message'] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

include '../includes/header.php'; 
?>

<h3 class="mb-4"><?php echo $page_title; ?></h3>

<div class="card shadow">
    <div class="card-header bg-success text-white">Formulir Pendaftaran Karyawan</div>
    <div class="card-body">
        <form method="POST" action="tambah_karyawan.php">
            <?php echo csrfField(); ?>
            
            <div class="mb-3">
                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
            </div>
            
            <div class="mb-3">
                <label for="outlet_id" class="form-label">Outlet Cabang</label>
                <select class="form-select" id="outlet_id" name="outlet_id" required>
                    <option value="">-- Pilih Outlet --</option>
                    <?php foreach ($outlet_list as $outlet): ?>
                        <option value="<?php echo $outlet['id_outlet']; ?>">
                            <?php echo htmlspecialchars($outlet['nama_outlet']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username (Login)</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password Awal</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <div class="form-text">Password akan di-enkripsi (hashed) sebelum disimpan.</div>
            </div>

            <button type="submit" class="btn btn-success"><i class="fas fa-user-plus"></i> Tambah Karyawan</button>
            <a href="kelola_karyawan.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php 
include '../includes/footer.php';
$conn->close();
?>