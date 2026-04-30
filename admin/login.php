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
        // 2. QUERY JOIN (USERS + USER_ROLES + KARYAWAN)
        // Penting: Mengambil id_role dari tabel user_roles
        $sql = "SELECT 
                    u.id_user, 
                    u.password, 
                    u.username, 
                    ur.id_role, 
                    k.nama_lengkap, 
                    k.id_outlet
                FROM users u
                JOIN user_roles ur ON u.id_user = ur.id_user
                LEFT JOIN karyawan k ON u.id_user = k.id_user
                WHERE u.username = ? AND u.is_active = 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 3. Verifikasi Password
            // CATATAN: Jika password di database masih polosan (belum di-hash), 
            // ganti baris ini menjadi: if ($password == $user['password']) {
            if (password_verify($password, $user['password'])) {
                
                // 4. SET SESSION (SANGAT PENTING: NAMA HARUS SAMA DENGAN SIDEBAR)
                $_SESSION['is_login']     = true;
                $_SESSION['id_user']      = $user['id_user'];  // Konsisten pakai id_user
                $_SESSION['username']     = $user['username'];
                
                // PERBAIKAN UTAMA DISINI:
                // Gunakan 'id_role' bukan 'role_id' agar terbaca di sidebar.php
                $_SESSION['id_role']      = $user['id_role'];  
                
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
                $_SESSION['id_outlet']    = $user['id_outlet'] ?? 0; // Konsisten pakai id_outlet
                
                // Set Nama Role untuk Tampilan (Opsional)
                if ($user['id_role'] == 1) $r = "Owner";
                elseif ($user['id_role'] == 2) $r = "HRD";
                elseif ($user['id_role'] == 3) $r = "Karyawan";
                elseif ($user['id_role'] == 4) $r = "Admin Outlet";
                else $r = "User";
                $_SESSION['role_name'] = $r;

                // 5. REDIRECT SESUAI ROLE
                if ($user['id_role'] == 4) {
                    // Admin Outlet langsung ke Transaksi
                    header("Location: keuangan/transaksi.php");
                } elseif ($user['id_role'] == 3) {
                    // Karyawan bisa ke Absen atau Dashboard
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
    <style>
        body {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif;
        }
        .card-login {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: none;
            padding-top: 30px;
            text-align: center;
        }
        .icon-circle {
            width: 70px;
            height: 70px;
            background-color: #4e73df;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            border-radius: 50px;
            padding: 10px;
            font-weight: bold;
            transition: 0.3s;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            transform: translateY(-2px);
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
            background-color: #f8f9fc;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #4e73df;
        }
    </style>
</head>
<body>

    <div class="card card-login">
        <div class="card-header">
            <div class="icon-circle">
                <i class="fas fa-tshirt"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">MENDING LAUNDRY</h4>
            <p class="text-muted small">Silakan login untuk memulai</p>
        </div>
        
        <div class="card-body p-4">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 text-center small rounded-pill border-0 bg-danger text-white mb-4">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <input type="text" class="form-control" name="username" placeholder="Username" required autofocus autocomplete="off">
                </div>
                <div class="mb-4">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 shadow-sm">
                    MASUK SEKARANG
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">Lupa password? Hubungi Admin.</small>
            </div>
        </div>
    </div>

</body>
</html>