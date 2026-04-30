<?php
// Pastikan kita memulai sesi agar bisa menghancurkannya
session_start();

// Hancurkan semua variabel sesi (Clear session data)
session_unset();

// Hancurkan sesi itu sendiri (Destroy the session)
session_destroy();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// dirname($_SERVER['PHP_SELF']) akan mengembalikan path folder tempat logout.php berada (yaitu /admin)
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Redirect ke Full URL
header("Location: $protocol://$host$path/login.php");
exit;
?>