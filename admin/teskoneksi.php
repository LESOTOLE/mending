<?php
// check_connection.php

// Tampilkan error jika ada
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Coba panggil file konfigurasi
$config_path = '../includes/config.php';

if (file_exists($config_path)) {
    include($config_path);
    
    if (isset($conn) && $conn) {
        echo "<h3 style='color:green;'>✅ Koneksi ke database berhasil!</h3>";
    } else {
        echo "<h3 style='color:red;'>❌ Koneksi gagal. Periksa konfigurasi di config.php.</h3>";
    }

} else {
    echo "<h3 style='color:red;'>❌ File konfigurasi tidak ditemukan di: $config_path</h3>";
}
?>