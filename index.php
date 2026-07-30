<?php
require_once 'includes/config.php';

if (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) {
    $conn = connectDB();
    $current_user_id = $_SESSION['id_user'];
    $stmt_user = $conn->prepare("SELECT id_role FROM users WHERE id_user = ?");
    $stmt_user->bind_param("i", $current_user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();

        if ($user_data['id_role'] == 4) {
            // User adalah owner, redirect ke keuangan
            header("Location: admin/keuangan/transaksi.php");
        } else {
            // User lain, redirect ke dashboard biasa
            header("Location: admin/dashboard.php");
        }
    } else {
        // User tidak ditemukan, logout
        session_destroy();
        header("Location: admin/login.php");
    }
    exit;
} else {
    header("Location: admin/login.php");
    exit;
}
