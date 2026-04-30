<?php
// FILE INI DI-INCLUDE DI DALAM pengeluaran.php
// Logika POST untuk memproses data

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'tambah_pengeluaran') {
    
    require_once '../../includes/config.php';
    $conn = connectDB();
    
    try {
        // --- 1. Ambil & Sanitasi Data (Disesuaikan dengan Nama Input Form di bawah) ---
        $user_id     = $_SESSION['id_user'] ?? 0; 
        $outlet_id   = (int)$_POST['id_outlet'];
        $kategori_id = (int)$_POST['id_kategori'];
        $tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
        $keterangan  = trim(htmlspecialchars($_POST['deskripsi'] ?? '')); // database pakai 'keterangan'
        $nominal     = filter_var($_POST['jumlah'] ?? 0, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE); // database pakai 'nominal'
        
        // Validasi
        if ($nominal === NULL || $nominal <= 0) {
             throw new Exception("Jumlah nominal harus valid.");
        }
        if (empty($keterangan) || $outlet_id <= 0 || $kategori_id <= 0) {
            throw new Exception("Semua field wajib diisi.");
        }

        // --- 2. INSERT ke Tabel pengeluaran (Urutan sesuai database) ---
        // Kolom: id_outlet, id_user, id_kategori, nominal, keterangan, tanggal
        $sql_insert = "INSERT INTO pengeluaran (id_outlet, id_user, id_kategori, nominal, keterangan, tanggal) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_insert);
        
        // Bind Param: i (int), d (double/float), s (string)
        // Urutan: outlet(i), user(i), kategori(i), nominal(d), keterangan(s), tanggal(s)
        $stmt->bind_param("iiidss", $outlet_id, $user_id, $kategori_id, $nominal, $keterangan, $tanggal);
        
        if (!$stmt->execute()) {
             throw new Exception("Gagal simpan: " . $stmt->error);
        }

        $_SESSION['form_status'] = 'success';
        $_SESSION['form_message'] = "Pengeluaran berhasil dicatat!";
        header("Location: pengeluaran.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['form_status'] = 'error';
        $_SESSION['form_message'] = "Gagal: " . $e->getMessage();
        header("Location: pengeluaran.php");
        exit;
    } finally {
        $conn->close();
    }
}
?>

<div class="modal fade" id="modalTambahPengeluaran" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-minus-circle me-2"></i>Catat Pengeluaran</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      
      <div class="modal-body p-4">
        <form action="modal_tambah_pengeluaran.php" method="post">
            <input type="hidden" name="action" value="tambah_pengeluaran">
          
            <div class="mb-3">
                <label class="form-label fw-bold small">Tanggal*</label>
                <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small">Cabang/Outlet*</label>
                <select class="form-select" name="id_outlet" required>
                    <option value="">Pilih Cabang</option>
                    <?php foreach ($outlets as $o): ?>
                        <option value="<?php echo $o['id_outlet']; ?>"><?php echo htmlspecialchars($o['nama_outlet']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold small">Kategori Biaya*</label>
                <select class="form-select" name="id_kategori" required>
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?php echo $k['id_kategori']; ?>"><?php echo htmlspecialchars($k['nama_kategori']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold small">Deskripsi/Keterangan*</label>
                <input type="text" class="form-control" name="deskripsi" placeholder="Contoh: Beli Deterjen 5kg" required>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small">Jumlah (Rp)*</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control" name="jumlah" required min="1" placeholder="0">
                </div>
            </div>
          
            <div class="modal-footer px-0 pb-0 border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger px-4 shadow-sm">
                    <i class="fas fa-save me-2"></i>Simpan Biaya
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>