<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- KONFIGURASI DATABASE ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'mending');
define('APP_NAME', 'MENDING LAUNDRY');
define('APP_ADDRESS', 'Jl. Kebahagiaan ');
define('APP_PHONE', '08xx-xxxx-xxxx');
define('BASE_URL', 'http://localhost/mending/admin/');

/**
 * Fungsi untuk membuat koneksi ke database MySQL.
 * @return mysqli Koneksi database yang sudah dibuat.
 */
function connectDB()
{
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

/**
 * Fungsi untuk menghasilkan hash (enkripsi) password.
 * Gunakan ini saat mendaftarkan user baru.
 * @param string $password Password plain text.
 * @return string Password yang sudah di-hash.
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}


// --- FUNGSI OTORISASI ---

/**
 * Fungsi untuk memeriksa apakah user sudah login dan memiliki role yang diizinkan.
 * Jika tidak login atau role tidak sesuai, user akan dialihkan ke halaman lain.
 * @param array $allowed_roles Array dari role_id yang diizinkan (e.g., [1, 2] untuk Owner dan HRD).
 */
function checkAuth($allowed_roles = [])
{
    if (!isset($_SESSION['id_user'])) {
        header("Location: login.php");
        exit;
    }

    if (!empty($allowed_roles)) {
        $user_role = $_SESSION['id_role'] ?? 0;
        $_SESSION['outlet_id'] = $_SESSION['id_outlet'] ?? 0; // 0 atau NULL untuk Super Admin

        if (!in_array($user_role, $allowed_roles)) {
            header("Location: dashboard.php");
            exit;
        }
    }
}
