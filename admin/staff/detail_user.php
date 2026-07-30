<?php
require_once '../../includes/config.php';

checkAuth([1, 2]);

require_once '../../includes/header.php';

$id_user = $_GET['id'] ?? 0;
$conn = connectDB();

// QUERY SUPER LENGKAP (Gabung 5 Tabel)
$sql = "SELECT u.username, u.is_active,
               k.*, -- Ambil semua data profil karyawan
               o.nama_outlet,
               r.nama_role
        FROM users u
        JOIN roles r ON u.id_role = r.id_role
        -- Gunakan INNER JOIN ke karyawan agar data profil wajib ada
        JOIN karyawan k ON u.id_user = k.id_user 
        LEFT JOIN outlets o ON k.id_outlet = o.id_outlet
        WHERE u.id_user = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<script>alert('Data karyawan tidak ditemukan!'); window.location='index.php';</script>";
    exit;
}
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Detail Profil Karyawan</h1>
        <a href="manajemen_user.php" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Kembali
        </a>
    </div>

    <div class="row">

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Akun Pengguna</h6>
                    <?php if ($data['is_active']): ?>
                        <span class="badge badge-success">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Non-Aktif</span>
                    <?php endif; ?>
                </div>
                <div class="card-body text-center">
                    <img class="img-profile rounded-circle mb-3" src="https://ui-avatars.com/api/?name=<?php echo urlencode($data['nama_lengkap']); ?>&background=random&size=128" onerror="this.onerror=null;this.src='/mending/assets/vendor/img/default-avatar.png';" style="width: 120px; height: 120px;">

                    <h4 class="font-weight-bold text-dark mb-1"><?php echo $data['nama_lengkap']; ?></h4>
                    <p class="text-muted mb-1"><?php echo $data['nama_role']; ?></p>
                    <p class="small text-primary"><i class="fas fa-store"></i> <?php echo $data['nama_outlet']; ?></p>

                    <hr>
                    <div class="text-left">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-secondary">Username Login</label>
                            <div class="h6"><?php echo $data['username']; ?></div>
                        </div>
                        
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="edit_user.php?id=<?php echo $id_user; ?>" class="btn btn-warning btn-block font-weight-bold">
                        <i class="fas fa-pen mr-2"></i> Edit Profil
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Biodata Lengkap</h6>
                </div>
                <div class="card-body">
                    <h5 class="text-dark font-weight-bold mb-3 border-bottom pb-2">Informasi Pribadi</h5>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">NIK (KTP)</label>
                            <p class="text-dark font-weight-bold"><?php echo $data['nik_ktp'] ?: '-'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Nomor HP / WhatsApp</label>
                            <p class="text-dark font-weight-bold"><?php echo $data['no_hp'] ?: '-'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Jenis Kelamin</label>
                            <p class="text-dark">
                                <?php
                                if ($data['jenis_kelamin'] == 'L') echo '<i class="fas fa-mars text-primary"></i> Laki-laki';
                                elseif ($data['jenis_kelamin'] == 'P') echo '<i class="fas fa-venus text-danger"></i> Perempuan';
                                else echo '-';
                                ?>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="small text-secondary font-weight-bold">Alamat Domisili</label>
                            <p class="text-dark bg-light p-2 rounded border"><?php echo $data['alamat'] ?: '-'; ?></p>
                        </div>
                    </div>

                    <h5 class="text-dark font-weight-bold mb-3 border-bottom pb-2">Data Kepegawaian & Payroll</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Tanggal Bergabung</label>
                            <p class="text-dark font-weight-bold"><?php echo $data['tanggal_bergabung'] ? date('d F Y', strtotime($data['tanggal_bergabung'])) : '-'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Masa Kerja</label>
                            <p class="text-success font-weight-bold">
                                <?php
                                if ($data['tanggal_bergabung']) {
                                    $awal  = new DateTime($data['tanggal_bergabung']);
                                    $akhir = new DateTime();
                                    $diff  = $awal->diff($akhir);
                                    echo $diff->y . " Tahun, " . $diff->m . " Bulan";
                                } else {
                                    echo "-";
                                }
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Bank Transfer</label>
                            <p class="text-dark"><?php echo $data['nama_bank'] ?: '-'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-secondary font-weight-bold">Nomor Rekening</label>
                            <p class="text-dark font-weight-bold" style="letter-spacing: 1px;"><?php echo $data['no_rekening'] ?: '-'; ?></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>