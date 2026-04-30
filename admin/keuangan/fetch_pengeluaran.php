<?php
require_once '../../includes/config.php';
checkAuth([1]); // Hanya Owner

header('Content-Type: application/json');

$conn = connectDB();

// PERBAIKAN 1: Ambil variabel 'id' sesuai dengan yang dikirim fetch() di JS
$id = (int)($_GET['id'] ?? 0); 

if ($id > 0) {
    // PERBAIKAN 2: Gunakan 'id_pengeluaran' sesuai struktur tabel
    $sql = "SELECT p.*, k.nama_kategori 
            FROM pengeluaran p
            JOIN kategori_pengeluaran k ON p.id_kategori = k.id_kategori
            WHERE p.id_pengeluaran = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan di database.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat data: ID (' . $id . ') tidak valid.']);
}

$conn->close();
?>