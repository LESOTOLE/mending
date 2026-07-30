<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Environment Variables (Database Credentials)
$env_path = __DIR__ . '/../env.php';
if (file_exists($env_path)) {
    require_once $env_path;
} else {
    die("File konfigurasi (env.php) tidak ditemukan. Sistem tidak bisa berjalan.");
}

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
        // Deteksi apakah file berada di subfolder (keuangan/, staff/, dll)
        $current_dir = basename(dirname($_SERVER['PHP_SELF']));
        $login_path = ($current_dir !== 'admin') ? '../login.php' : 'login.php';
        header("Location: $login_path");
        exit;
    }

    if (!empty($allowed_roles)) {
        $user_role = $_SESSION['id_role'] ?? 0;

        if (!in_array($user_role, $allowed_roles)) {
            $current_dir = basename(dirname($_SERVER['PHP_SELF']));
            $dash_path = ($current_dir !== 'admin') ? '../dashboard.php' : 'dashboard.php';
            header("Location: $dash_path");
            exit;
        }
    }
}

// --- FUNGSI CSRF PROTECTION ---

/**
 * Generate CSRF token dan simpan di session.
 * @return string Token CSRF.
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan hidden input HTML untuk CSRF token.
 * @return string HTML hidden input.
 */
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Verifikasi CSRF token dari form POST.
 * @return bool True jika valid.
 */
function verifyCsrfToken()
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sentinel value untuk mengecek apakah token WhatsApp sudah diubah dari default.
 */
define('FONNTE_TOKEN_DEFAULT', '__FONNTE_TOKEN_NOT_CONFIGURED__');

/**
 * Escape string agar aman dimasukkan ke dalam konteks JavaScript (single-quoted string).
 * @param string|null $str String yang akan di-escape.
 * @return string String yang sudah aman.
 */
function safeJsString($str)
{
    if ($str === null) return '';
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
