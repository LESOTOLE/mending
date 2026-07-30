<?php
// Path ke config dari admin/keuangan/
require_once '../../includes/config.php'; 

// OTORISASI: Hanya Owner (1) yang boleh mengelola pengeluaran.
checkAuth([1]); 

$page_title = "Manajemen Pengeluaran";
$id_user_login = $_SESSION['id_user']; // ID User yang sedang login
$conn = connectDB();

$pengeluaran_list = [];
$outlets = [];
$kategori_list = [];
$error = '';
$total_tahunan = 0;
$total_bulanan = 0;

try {
    // 1. Ambil Daftar Kategori dan Outlet
    $outlets_result = $conn->query("SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet");
    while ($row = $outlets_result->fetch_assoc()) {
        $outlets[] = $row;
    }

    $kategori_result = $conn->query("SELECT id_kategori, nama_kategori FROM kategori_pengeluaran ORDER BY nama_kategori");
    while ($row = $kategori_result->fetch_assoc()) {
        $kategori_list[] = $row;
    }
    
    // 2. Ambil Daftar Pengeluaran
    $sql = "SELECT p.*, 
                   k.nama_kategori, 
                   o.nama_outlet, 
                   kar.nama_lengkap as pencatat 
            FROM pengeluaran p
            JOIN kategori_pengeluaran k ON p.id_kategori = k.id_kategori
            JOIN outlets o ON p.id_outlet = o.id_outlet
            LEFT JOIN karyawan kar ON p.id_user = kar.id_user
            ORDER BY p.tanggal DESC";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pengeluaran_list[] = $row;
        }
    }

    // 3. Ringkasan Biaya Tahun Berjalan
    $sql_ringkas_tahun = "SELECT SUM(nominal) as total FROM pengeluaran WHERE YEAR(tanggal) = YEAR(CURDATE())";
    $res_tahun = $conn->query($sql_ringkas_tahun)->fetch_assoc();
    $total_tahunan = $res_tahun['total'] ?? 0;

    // 4. Ringkasan Biaya Bulan Berjalan (FITUR BARU)
    $sql_ringkas_bulan = "SELECT SUM(nominal) as total FROM pengeluaran WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
    $res_bulan = $conn->query($sql_ringkas_bulan)->fetch_assoc();
    $total_bulanan = $res_bulan['total'] ?? 0;

} catch (Exception $e) {
    $error = "Gagal memuat data: " . $e->getMessage();
}

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

include '../../includes/header.php'; 
?>

<link href="/mending/assets/vendor/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid">
    <h3 class="mb-4 text-primary fw-bold"><i class="fas fa-hand-holding-usd me-2"></i>Manajemen Pengeluaran</h3>

    <?php 
    $status = $_SESSION['form_status'] ?? null;
    $message = $_SESSION['form_message'] ?? null;
    unset($_SESSION['form_status']);
    unset($_SESSION['form_message']);

    if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card text-white bg-warning shadow h-100 border-0" style="border-radius: 10px;">
                <div class="card-body">
                    <h6 class="card-title text-uppercase fw-bold"><i class="fas fa-calendar-alt me-2"></i> Total Biaya Bulan Ini</h6>
                    <p class="card-text fs-2 fw-bold mb-0"><?php echo formatRupiah($total_bulanan); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card text-white bg-danger shadow h-100 border-0" style="border-radius: 10px;">
                <div class="card-body">
                    <h6 class="card-title text-uppercase fw-bold"><i class="fas fa-calendar me-2"></i> Total Biaya Tahun Ini</h6>
                    <p class="card-text fs-2 fw-bold mb-0"><?php echo formatRupiah($total_tahunan); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col">
            <button type="button" class="btn btn-danger shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalTambahPengeluaran">
                <i class="fas fa-plus me-1"></i> Catat Pengeluaran Baru
            </button>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-white py-3 border-bottom-danger">
            <h6 class="m-0 font-weight-bold text-danger">Riwayat Pengeluaran</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tabelPengeluaran" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Cabang</th>
                            <th>Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pengeluaran_list)): ?>
                            <?php foreach ($pengeluaran_list as $p): ?>
                            <tr>
                                <td>
                                    <span style="display:none;"><?php echo $p['tanggal']; ?></span>
                                    <?php echo date('d-m-Y', strtotime($p['tanggal'])); ?>
                                </td>
                                <td><span class="badge bg-info rounded-pill"><?php echo htmlspecialchars($p['nama_outlet']); ?></span></td>
                                <td><?php echo htmlspecialchars($p['nama_kategori']); ?></td>
                                <td><?php echo htmlspecialchars($p['keterangan']); ?></td>
                                <td class="text-danger fw-bold"><?php echo formatRupiah($p['nominal']); ?></td>
                                <td>
                                    <button type="button" 
                                        class="btn btn-sm btn-outline-primary btn-edit-pengeluaran shadow-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEditPengeluaran"
                                        data-id="<?php echo $p['id_pengeluaran']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <form class="form-delete" method="POST" action="proses_pengeluaran.php" style="display:inline-block;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="pengeluaran_id" value="<?php echo $p['id_pengeluaran']; ?>">
                                        
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger btn-delete-confirm shadow-sm" 
                                                data-id="<?php echo $p['id_pengeluaran']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
include 'modal_edit_pengeluaran.php'; 
include 'modal_tambah_pengeluaran.php'; 
include '../../includes/footer.php'; 
?>

<script src="/mending/assets/vendor/js/jquery.dataTables.min.js"></script>
<script src="/mending/assets/vendor/js/dataTables.bootstrap5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // INISIALISASI DATATABLES
    $('#tabelPengeluaran').DataTable({
        "language": {
            "search": "Cari data:",
            "lengthMenu": "Tampilkan _MENU_ data per halaman",
            "zeroRecords": "Tidak ada riwayat pengeluaran yang ditemukan",
            "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
            "infoEmpty": "Tidak ada data tersedia",
            "infoFiltered": "(difilter dari _MAX_ total data)",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        },
        "order": [[0, "desc"]] // Urutkan berdasarkan kolom tanggal paling baru
    });

    // FUNGSI BUILD FORM EDIT
    function buildEditForm(data) {
    const outlets = <?php echo json_encode($outlets); ?>;
    const categories = <?php echo json_encode($kategori_list); ?>;
    
    let formHtml = `<form action="proses_pengeluaran.php" method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="pengeluaran_id" value="${data.id_pengeluaran}">
        
        <div class="mb-3">
            <label class="form-label small fw-bold">Tanggal Pengeluaran*</label>
            <input type="date" class="form-control" name="tanggal_pengeluaran" value="${data.tanggal}" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label small fw-bold">Cabang*</label>
            <select class="form-select" name="outlet_id" required>`;
            
    outlets.forEach(o => {
        const selected = (o.id_outlet == data.id_outlet) ? 'selected' : '';
        formHtml += `<option value="${o.id_outlet}" ${selected}>${o.nama_outlet}</option>`;
    });

    formHtml += `</select></div>
        
        <div class="mb-3">
            <label class="form-label small fw-bold">Kategori Biaya*</label>
            <select class="form-select" name="kategori_id" required>`;
    
    categories.forEach(k => {
        const selected = (k.id_kategori == data.id_kategori) ? 'selected' : '';
        formHtml += `<option value="${k.id_kategori}" ${selected}>${k.nama_kategori}</option>`;
    });

    formHtml += `</select></div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Deskripsi/Keterangan*</label>
            <input type="text" class="form-control" name="deskripsi" value="${data.keterangan || ''}" required>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold">Jumlah (Rp)*</label>
            <input type="number" class="form-control" name="jumlah" value="${parseFloat(data.nominal)}" required min="1">
        </div>

        <button type="submit" class="btn btn-primary w-100 shadow-sm fw-bold">
            <i class="fas fa-save me-2"></i>Simpan Perubahan
        </button>
    </form>`;
    
    return formHtml;
    }

    const modalEdit = document.getElementById('modalEditPengeluaran');
    
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; 
            const pengeluaranId = button.getAttribute('data-id');
            const modalBody = document.getElementById('editPengeluaranFormContainer');
            
            if (!modalBody) return; 

            modalBody.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Memuat data...</p></div>';

            fetch(`fetch_pengeluaran.php?id=${pengeluaranId}`)
                .then(response => {
                    if (!response.ok) throw new Error(`Jaringan gagal: ${response.status}`);
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        modalBody.innerHTML = buildEditForm(result.data);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-warning">Gagal memuat data: ${result.message}</div>`;
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                });
        });
    }

    // EVENT LISTENER HAPUS
    // Karena DataTables me-render ulang DOM saat pindah halaman/search,
    // kita gunakan Event Delegation pada body tabel agar tombol hapus tetap jalan
    $('#tabelPengeluaran tbody').on('click', '.btn-delete-confirm', function() {
        const form = this.closest('.form-delete');
        
        Swal.fire({
            title: 'Yakin Menghapus?',
            text: "Data pengeluaran ini akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
    
    const status = '<?php echo safeJsString($status); ?>';
    const message = '<?php echo safeJsString($message); ?>';

    if (status && message) {
        Swal.fire({
            icon: status,
            title: (status === 'success' ? 'Berhasil!' : 'Gagal!'),
            text: message,
            showConfirmButton: false,
            timer: 3000
        });
    }
});
</script>