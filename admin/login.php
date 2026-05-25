<?php
session_start(); // Pastikan session dimulai paling atas
require_once '../includes/config.php'; // Sesuaikan path ini jika perlu

$error = '';

// 1. Cek jika user sudah login, langsung lempar ke dashboard
if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = connectDB();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi.";
    } else {
        // 2. QUERY JOIN (USERS + ROLES + KARYAWAN)
        // PERBAIKAN: Menggunakan tanda '?' untuk Prepared Statement yang benar
        // PERBAIKAN: Menambahkan LEFT JOIN ke karyawan untuk mengambil nama & outlet
        $sql = "SELECT users.*, roles.nama_role, karyawan.nama_lengkap, karyawan.id_outlet 
              FROM users 
              JOIN roles ON users.id_role = roles.id_role 
              LEFT JOIN karyawan ON users.id_user = karyawan.id_user
              WHERE users.username = ? AND users.is_active = 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 3. Verifikasi Password (menggunakan password_hash)
            if (password_verify($password, $user['password'])) {

                // 4. SET SESSION
                $_SESSION['is_login']     = true;
                $_SESSION['id_user']      = $user['id_user'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['id_role']      = $user['id_role'];

                // Data ini sekarang akan terisi karena kita sudah JOIN tabel karyawan
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
                $_SESSION['id_outlet']    = $user['id_outlet'] ?? 0;

                // Set Nama Role langsung dari tabel roles (lebih dinamis)
                $_SESSION['role_name']    = $user['nama_role'];

                // 5. REDIRECT SESUAI ROLE
                if ($user['id_role'] == 4) {
                    // Admin Outlet langsung ke Transaksi
                    header("Location: keuangan/transaksi.php");
                } elseif ($user['id_role'] == 3) {
                    // Karyawan
                    header("Location: dashboard.php");
                } else {
                    // Owner & HRD
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                $error = "Password salah.";
            }
        } else {
            $error = "Username tidak ditemukan atau akun tidak aktif.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mending Laundry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/web3.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Latar belakang biru soft elegan untuk halaman login */
            background: linear-gradient(135deg, #f0f4f8 0%, #dbeafe 100%);
        }
        .card-login {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
            border-radius: 16px !important;
            border: 1px solid rgba(0,0,0,0.05) !important;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 35px;
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
        }
        .card-title {
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>

<body>

    <div class="card card-login">
        <div class="card-header">
            <div class="icon-circle">
                <i class="fas fa-tshirt"></i>
            </div>
            <h4 class="card-title">MENDING LAUNDRY</h4>
            <p class="text-muted small mb-0">Silakan login untuk memulai</p>
        </div>

        <div class="card-body p-4">

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 text-center small rounded-3 border bg-danger text-white mb-4">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-4 input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control with-icon" name="username" placeholder="Username" required autofocus autocomplete="off">
                </div>
                <div class="mb-5 input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control with-icon" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    MASUK SEKARANG <i class="fas fa-sign-in-alt ms-2"></i>
                </button>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">Lupa password? Hubungi Admin.</small>
            </div>
        </div>
    </div>

</body>

</html>