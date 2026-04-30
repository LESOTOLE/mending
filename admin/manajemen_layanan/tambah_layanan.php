<?php
require_once '../../includes/config.php'; 
checkAuth([1]);

$conn = connectDB();
$id = $_GET['id'] ?? null;
$is_edit = !empty($id);
$page_title = $is_edit ? "Edit Layanan" : "Tambah Layanan Baru";

// Inisialisasi Variabel
$nama_layanan = '';
$outlet_id = '';
$satuan = 'Kg';
$harga = '';
$estimasi = '';

// Jika Edit, ambil data lama
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM layanan WHERE id_layanan = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if ($data) {
        $nama_layanan = $data['nama_layanan'];
        $outlet_id = $data['id_outlet'];
        $satuan = $data['satuan'];
        $harga = $data['harga'];
        $estimasi = $data['estimasi_jam'];
    }
}

// Ambil Daftar Outlet
$outlets = [];
$res_out = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");
while($row = $res_out->fetch_assoc()) $outlets[] = $row;

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><?php echo $page_title; ?></h6>
                </div>
                <div class="card-body">
                    <form action="proses_layanan.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Berlaku di Cabang (Outlet)*</label>
                            <select class="form-select" name="outlet_id" required>
                                <option value="">-- Pilih Cabang --</option>
                                <?php foreach($outlets as $o): ?>
                                    <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($outlet_id == $o['id_outlet']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['nama_outlet']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Harga layanan bisa berbeda tiap cabang.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Layanan*</label>
                            <input type="text" class="form-control" name="nama_layanan" value="<?php echo htmlspecialchars($nama_layanan); ?>" placeholder="Contoh: Cuci Komplit, Bed Cover Besar, Cuci Sepatu" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Satuan Hitung*</label>
                                <select class="form-select" name="satuan" required>
                                    <option value="Kg" <?php echo ($satuan == 'Kg') ? 'selected' : ''; ?>>Kg (Kiloan)</option>
                                    <option value="Pcs" <?php echo ($satuan == 'Pcs') ? 'selected' : ''; ?>>Pcs (Satuan)</option>
                                    <option value="Meter" <?php echo ($satuan == 'Meter') ? 'selected' : ''; ?>>Meter (Karpet)</option>
                                    <option value="Set" <?php echo ($satuan == 'Set') ? 'selected' : ''; ?>>Set (Pasang)</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Harga per Satuan (Rp)*</label>
                                <input type="number" class="form-control" name="harga_per_satuan" value="<?php echo $harga; ?>" placeholder="Contoh: 7000" min="0" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estimasi Pengerjaan (Opsional)</label>
                            <input type="text" class="form-control" name="estimasi_durasi" value="<?php echo htmlspecialchars($estimasi); ?>" placeholder="Contoh: 2 Hari, 6 Jam, Kilat">
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="layanan.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Data</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>