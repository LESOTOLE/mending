<?php
require_once '../../includes/config.php';
// File ini hanya dipanggil via AJAX/Background
checkAuth([1, 2, 4]); 

if (isset($_POST['id'])) {
    $conn = connectDB();
    $trx_id = $_POST['id'];

    // Ambil Data Detail Barang (PERBAIKAN: Nama kolom disesuaikan dengan schema)
    $sql = "SELECT td.*, l.nama_layanan 
            FROM transaksi_detail td 
            JOIN layanan l ON td.id_layanan = l.id_layanan 
            WHERE td.id_transaksi = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $trx_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Buat Tabel HTML Sederhana untuk ditampilkan di Modal
    echo '<div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="bg-light">
                    <tr>
                        <th>Layanan</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';

    $total = 0;
    while ($row = $result->fetch_assoc()) {
        echo '<tr>
                <td>' . htmlspecialchars($row['nama_layanan']) . '</td>
                <td class="text-center">' . $row['qty'] . '</td>
                <td class="text-end">' . number_format($row['harga_satuan'], 0, ',', '.') . '</td>
                <td class="text-end fw-bold">' . number_format($row['subtotal'], 0, ',', '.') . '</td>
              </tr>';
        $total += $row['subtotal'];
    }

    echo '      </tbody>
                <tfoot>
                    <tr class="table-active">
                        <td colspan="3" class="fw-bold text-end">TOTAL TAGIHAN</td>
                        <td class="fw-bold text-end">Rp ' . number_format($total, 0, ',', '.') . '</td>
                    </tr>
                </tfoot>
            </table>
          </div>';
}
?>