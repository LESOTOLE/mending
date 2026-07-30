<?php
if ($role_id != 3) exit;
?>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, rgba(112,0,255,0.4) 0%, rgba(0,240,255,0.4) 100%) !important;
        border-radius: 20px !important;
        color: white !important;
        border: 1px solid rgba(255,255,255,0.2) !important;
    }

    .welcome-icon {
        font-size: 5rem;
        opacity: 0.2;
        position: absolute;
        right: 30px;
        bottom: -10px;
        color: #00f0ff !important;
    }

    .card-absen {
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .card-absen:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 240, 255, 0.4) !important;
    }
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow border-0 welcome-banner position-relative overflow-hidden">
            <div class="card-body p-4">
                <h2 class="font-weight-bold mb-1">Halo, <?php echo htmlspecialchars($nama_lengkap); ?>! 👋</h2>
                <p class="mb-0 opacity-75">Selamat datang di panel kerja Anda. Jangan lupa untuk selalu memberikan pelayanan terbaik hari ini!</p>
                <i class="fas fa-tshirt welcome-icon"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-5 col-lg-5 mb-4">
        <div class="card shadow h-100 border-bottom-primary card-absen">
            <div class="card-header py-3 bg-white text-center border-0">
                <h6 class="m-0 font-weight-bold text-primary text-uppercase">Status Kehadiran Hari Ini</h6>
                <small class="text-muted"><?php echo date('d F Y'); ?></small>
            </div>
            <div class="card-body text-center d-flex flex-column justify-content-center">

                <?php if ($sudah_absen): ?>
                    <div class="mb-4">
                        <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 80px; height: 80px;">
                            <i class="fas fa-check fa-3x"></i>
                        </div>
                        <h4 class="text-success font-weight-bold">SUDAH ABSEN</h4>
                        <p class="text-muted mb-0">Jam Masuk: <span class="badge bg-primary text-white p-2"><?php echo $waktu_masuk_hari_ini; ?></span></p>
                    </div>
                    <button class="btn btn-light border btn-block text-muted" disabled>
                        <i class="fas fa-check-circle mr-2"></i> Kehadiran Tercatat
                    </button>
                <?php else: ?>
                    <div class="mb-4">
                        <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 80px; height: 80px;">
                            <i class="fas fa-fingerprint fa-3x"></i>
                        </div>
                        <h4 class="text-warning font-weight-bold">BELUM ABSEN</h4>
                        <p class="text-muted mb-0">Silakan lakukan absensi kamera.</p>
                    </div>
                    <a href="staff/absensi.php" class="btn btn-primary btn-lg btn-block shadow-sm font-weight-bold rounded-pill">
                        <i class="fas fa-camera mr-2"></i> ABSEN SEKARANG
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <div class="col-xl-7 col-lg-7 mb-4">
        <div class="card border-left-success shadow py-2 mb-4">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Hadir (Bulan Ini)</div>
                        <div class="h3 mb-0 font-weight-bold text-gray-800">
                            <?php echo $total_hadir; ?> <span class="h6 font-weight-normal text-muted">Hari</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 bg-white d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-history mr-2 text-primary"></i>5 Riwayat Kehadiran Terakhir</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 text-sm">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Tanggal</th>
                                <th>Jam Masuk</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($q_riwayat->num_rows > 0): ?>
                                <?php while ($row = $q_riwayat->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3 font-weight-bold text-dark"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                        <td><?php echo $row['waktu_masuk']; ?></td>
                                        <td><span class="badge bg-success text-white"><i class="fas fa-check mr-1"></i>Hadir</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">Belum ada riwayat kehadiran.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
