<?php
require_once '../includes/config.php';
if (php_sapi_name() !== 'cli') {
    checkAuth([1]);
}

$conn = connectDB();
if ($conn) {
    echo "<h3 style='color:green;'>Koneksi ke database berhasil!</h3>";
} else {
    echo "<h3 style='color:red;'>Koneksi gagal. Periksa konfigurasi di config.php.</h3>";
}
$conn->close();