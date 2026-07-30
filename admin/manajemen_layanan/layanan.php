<?php
session_start();
require_once '../../includes/config.php';
checkAuth([1]); // Hanya Owner

$conn = connectDB();
$page_title = "Manajemen Layanan";

// Ambil data semua outlet untuk Filter & Modal
$outlets = [];
$res_outlets = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC");
while ($row = $res_outlets->fetch_assoc()) {
    $outlets[] = $row;
}

// ---------------------------------------------------------
// FITUR FILTER CABANG
// ---------------------------------------------------------
$filter_outlet = isset($_GET['filter_outlet']) ? $_GET['filter_outlet'] : '';
$where_clause = "";

if ($filter_outlet != '') {
    // Jika dropdown cabang dipilih, saring datanya!
    $id_filter = (int)$filter_outlet;
    $where_clause = "WHERE l.id_outlet = $id_filter";
}

// Ambil data layanan sesuai filter
$sql = "SELECT l.*, o.nama_outlet 
        FROM layanan l 
        JOIN outlets o ON l.id_outlet = o.id_outlet 
        $where_clause
        ORDER BY o.nama_outlet ASC, l.nama_layanan ASC";
$result = $conn->query($sql);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tags me-2"></i>Daftar Layanan & Harga</h1>

        <div>
            <button class="btn btn-success shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#modalSalin">
                <i class="fas fa-copy fa-sm text-white-50 me-1"></i> Salin Layanan
            </button>

            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalLayanan" onclick="prepareModal('create')">
                <i class="fas fa-plus fa-sm text-white-50 me-1"></i> Tambah Layanan
            </button>
        </div>
    </div>

    <div class="card shadow mb-4 border-left-info">
        <div class="card-body py-3">
            <form method="GET" action="">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <label class="fw-bold mb-0">Tampilkan Cabang:</label>
                    </div>
                    <div class="col-md-4">
                        <select name="filter_outlet" class="form-select shadow-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Cabang (Gabungan) --</option>
                            <?php foreach ($outlets as $o): ?>
                                <option value="<?php echo $o['id_outlet']; ?>" <?php echo ($filter_outlet == $o['id_outlet']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['nama_outlet']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th>Cabang</th>
                            <th>Nama Layanan</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Estimasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada layanan di cabang ini.</td>
                            </tr>
                        <?php endif; ?>

                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nama_outlet']); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['nama_layanan']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo $row['satuan']; ?></span></td>
                                <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                <td><?php echo $row['estimasi_jam']; ?> Jam</td>
                                <td>
                                    <button class="btn btn-sm btn-warning shadow-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalLayanan"
                                        onclick="prepareModal('update', <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger shadow-sm"
                                        onclick="confirmDelete(<?php echo $row['id_layanan']; ?>, '<?php echo $row['nama_layanan']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLayanan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form action="proses_layanan.php" method="POST" id="formLayanan">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Layanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="layananId">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-gray-800">Pilih Cabang/Outlet*</label>
                        <select name="outlet_id" id="f_outlet" class="form-select shadow-sm" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($outlets as $o): ?>
                                <option value="<?php echo $o['id_outlet']; ?>"><?php echo htmlspecialchars($o['nama_outlet']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-gray-800">Nama Layanan*</label>
                        <input type="text" name="nama_layanan" id="f_nama" class="form-control shadow-sm" required placeholder="Contoh: Cuci Komplit">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-gray-800">Satuan*</label>
                            <select name="satuan" id="f_satuan" class="form-select shadow-sm">
                                <option value="kg">Kg</option>
                                <option value="pcs">Pcs</option>
                                <option value="meter">Meter</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-gray-800">Estimasi Selesai (Jam)*</label>
                            <input type="number" name="estimasi_durasi" id="f_estimasi" class="form-control shadow-sm" value="24" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-gray-800">Harga per Satuan (Rp)*</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="number" name="harga_per_satuan" id="f_harga" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary shadow px-4"><i class="fas fa-save me-2"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSalin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <form action="proses_layanan.php" method="POST">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-copy me-2"></i> Salin Data Layanan Massal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="copy_services">

                    <div class="alert alert-warning small border-0 shadow-sm">
                        <i class="fas fa-info-circle me-1"></i> Fitur ini akan menyalin (menduplikasi) <b>SEMUA</b> layanan dari Cabang Sumber ke Cabang Tujuan. Sangat berguna saat membuka cabang baru!
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-success">1. Salin DARI Cabang (Sumber Data)</label>
                        <select name="source_outlet" class="form-select border-success shadow-sm" required>
                            <option value="">-- Pilih Cabang Sumber --</option>
                            <?php foreach ($outlets as $o): ?>
                                <option value="<?php echo $o['id_outlet']; ?>"><?php echo htmlspecialchars($o['nama_outlet']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="text-center mb-3 text-muted">
                        <i class="fas fa-arrow-down fa-2x"></i>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">2. Salin KE Cabang (Tujuan)</label>
                        <select name="target_outlet" class="form-select border-primary shadow-sm" required>
                            <option value="">-- Pilih Cabang Tujuan --</option>
                            <?php foreach ($outlets as $o): ?>
                                <option value="<?php echo $o['id_outlet']; ?>"><?php echo htmlspecialchars($o['nama_outlet']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-white border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success shadow px-4"><i class="fas fa-magic me-2"></i> Eksekusi Salin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/mending/assets/vendor/js/sweetalert2.all.min.js"></script>
<script>
    function prepareModal(action, data = null) {
        const title = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');

        if (action === 'create') {
            title.innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Layanan Baru';
            formAction.value = 'create';
            document.getElementById('formLayanan').reset();
        } else {
            title.innerHTML = '<i class="fas fa-edit me-2"></i> Edit Layanan';
            formAction.value = 'update';
            document.getElementById('layananId').value = data.id_layanan;
            document.getElementById('f_outlet').value = data.id_outlet;
            document.getElementById('f_nama').value = data.nama_layanan;
            document.getElementById('f_satuan').value = data.satuan;
            document.getElementById('f_harga').value = data.harga;
            document.getElementById('f_estimasi').value = data.estimasi_jam;
        }
    }

    function confirmDelete(id, nama) {
        Swal.fire({
            title: 'Hapus Layanan?',
            text: `Apakah Anda yakin ingin menghapus layanan "${nama}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `proses_layanan.php?action=delete&id=${id}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['form_status'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['form_status']; ?>',
                title: '<?php echo ($_SESSION['form_status'] == 'success') ? 'Berhasil!' : 'Gagal!'; ?>',
                text: '<?php echo $_SESSION['form_message']; ?>',
                showConfirmButton: false,
                timer: 2500
            });
            <?php unset($_SESSION['form_status'], $_SESSION['form_message']); ?>
        <?php endif; ?>
    });
</script>

<?php include '../../includes/footer.php'; ?>