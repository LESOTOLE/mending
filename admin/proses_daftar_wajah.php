<?php
require_once '../includes/config.php';
checkAuth([1, 2]);

if ($_SERVER["REQUEST_METHOD"] == "POST" && verifyCsrfToken()) {
    $id_user = (int)($_POST['id_user'] ?? 0);
    $face_descriptor = $_POST['face_descriptor'] ?? '';
    $foto_base64 = $_POST['foto_base64'] ?? '';

    if (empty($id_user) || empty($face_descriptor) || empty($foto_base64)) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Data registrasi wajah tidak lengkap.';
        header("Location: kelola_karyawan.php");
        exit;
    }

    $upload_dir = '../uploads/karyawan/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $img_parts = explode(',', $foto_base64);
    $decoded_img = base64_decode(end($img_parts));
    if ($decoded_img === false) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Format gambar base64 tidak valid.';
        header("Location: kelola_karyawan.php");
        exit;
    }

    $filename = 'wajah_' . $id_user . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    file_put_contents($filepath, $decoded_img);

    $db_path = 'uploads/karyawan/' . $filename;

    $conn = connectDB();
    $sql = "UPDATE karyawan SET foto_referensi = ?, face_descriptor = ? WHERE id_user = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $db_path, $face_descriptor, $id_user);

    if ($stmt->execute()) {
        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = 'Sampel wajah karyawan berhasil didaftarkan.';
    } else {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = 'Gagal menyimpan sampel wajah ke database: ' . $stmt->error;
    }
    $stmt->close();
    $conn->close();

    header("Location: kelola_karyawan.php");
    exit;
} else {
    $_SESSION['form_status'] = 'error';
    $_SESSION['form_message'] = 'Permintaan tidak valid atau token CSRF kedaluwarsa.';
    header("Location: kelola_karyawan.php");
    exit;
}
