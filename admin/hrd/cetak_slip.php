<?php
require_once '../../includes/config.php';
// Hanya Owner (1) dan HRD (2) yang bisa mengakses cetak slip
checkAuth([1, 2]); 

$id_gaji = $_GET['id'] ?? 0;
$conn = connectDB();

// Mengambil data gaji dan informasi karyawan
$sql = "SELECT p.*, k.nama_lengkap, u.username, u.id_role, o.nama_outlet
        FROM penggajian p 
        JOIN users u ON p.id_user = u.id_user 
        JOIN karyawan k ON u.id_user = k.id_user
        JOIN outlets o ON k.id_outlet = o.id_outlet
        WHERE p.id_penggajian = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_gaji);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) { 
    die("<script>alert('Data gaji tidak ditemukan!'); window.close();</script>"); 
}

$periode = date('F Y', strtotime($data['bulan']));
$role_name = ($data['id_role'] == 3) ? 'Staff Operasional' : 'Admin Outlet';

// Membuat Kode Otentikasi Digital (Hash unik berdasarkan ID dan Bulan)
$auth_hash = strtoupper(substr(md5($data['id_penggajian'] . $data['bulan'] . $data['id_user']), 0, 8));
$auth_code = sprintf("MDGL-%04d-%s", $data['id_penggajian'], $auth_hash);

// Data QR Code (Menampilkan detail validasi saat di-scan)
$qr_data = urlencode("VERIFIED BY MENDING LAUNDRY\nRef: $auth_code\nNama: {$data['nama_lengkap']}\nPeriode: $periode");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - <?php echo $data['nama_lengkap']; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/mending/assets/vendor/css/all.min.css">
    
    <script src="/mending/assets/vendor/js/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --success: #1cc88a;
            --danger: #e74a3b;
            --dark: #202124;
            --text-muted: #858796;
            --bg-light: #f8f9fc;
            --border-color: #e3e6f0;
        }

        body { 
            background: #e9ecef; 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 20px; 
            color: var(--dark); 
        }

        /* --- STYLING TOMBOL AKSI --- */
        .action-bar {
            max-width: 21cm;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: center;
            gap: 12px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            color: white;
        }
        .btn-print { background: var(--dark); }
        .btn-pdf { background: var(--primary); }
        .btn-close { background: var(--danger); }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        /* --- STYLING KERTAS SLIP --- */
        .page {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto;
            padding: 40px 50px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Watermark Background */
        .page::before {
            content: 'CONFIDENTIAL';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 8rem;
            color: rgba(0, 0, 0, 0.03);
            font-weight: 800;
            z-index: 0;
            pointer-events: none;
        }

        .content-wrapper { position: relative; z-index: 1; }

        /* Header Profesional */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .brand h1 { margin: 0; color: var(--primary); font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .brand p { margin: 5px 0 0; color: var(--text-muted); font-size: 13px; font-weight: 500; }
        .brand span { color: var(--dark); font-weight: 700; }
        
        .title { text-align: right; }
        .title h2 { margin: 0; font-size: 24px; color: var(--dark); letter-spacing: 3px; font-weight: 800; }
        .title .id-slip { font-size: 13px; color: var(--text-muted); margin-top: 5px; display: block; }

        /* Detail Karyawan */
        .emp-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            background: var(--bg-light);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        .emp-item { display: flex; flex-direction: column; }
        .emp-item label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .emp-item span { font-size: 15px; font-weight: 600; color: var(--dark); }

        /* Tabel Gaji */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .salary-table th {
            background: var(--primary);
            color: white;
            padding: 14px 20px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .salary-table th.right { text-align: right; }
        .salary-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            color: #333;
        }
        .salary-table tr:last-child td { border-bottom: none; }
        
        .amount { text-align: right; font-family: 'Courier New', monospace; font-weight: 700; font-size: 15px; }
        .plus { color: var(--success); }
        .minus { color: var(--danger); }

        /* Box Take Home Pay */
        .thp-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            border: 2px solid var(--primary);
            padding: 20px 25px;
            border-radius: 8px;
            margin-bottom: 40px;
        }
        .thp-label h3 { margin: 0; font-size: 16px; color: var(--dark); }
        .thp-label p { margin: 5px 0 0; font-size: 12px; color: var(--text-muted); }
        .thp-amount { font-size: 26px; font-weight: 800; color: var(--primary); }

        /* Catatan */
        .notes {
            font-size: 13px;
            color: #666;
            background: #fff8e1;
            padding: 15px;
            border-left: 4px solid #f6c23e;
            border-radius: 4px;
            margin-bottom: 30px;
        }

        /* --- BUKTI KEASLIAN (MENGGANTIKAN TTD) --- */
        .authenticity {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--border-color);
        }
        .qr-code { width: 80px; height: 80px; border: 1px solid var(--border-color); padding: 5px; background: white; border-radius: 6px; }
        .auth-text h4 { margin: 0 0 5px 0; color: var(--success); font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .auth-text p { margin: 0 0 3px 0; font-size: 11px; color: var(--text-muted); line-height: 1.5; }
        .auth-text .ref-id { font-family: 'Courier New', monospace; font-weight: bold; color: var(--dark); }

        /* Pengaturan Cetak Browser */
        @media print {
            body { background: white; padding: 0; }
            .action-bar { display: none; }
            .page { width: 100%; min-height: auto; margin: 0; padding: 20px; box-shadow: none; border: none; }
            .thp-box { -webkit-print-color-adjust: exact; }
            .salary-table th { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="action-bar no-print">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> Cetak Printer</button>
        <button onclick="generatePDF()" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> Unduh File PDF</button>
        <button onclick="window.close()" class="btn btn-close"><i class="fas fa-times"></i> Tutup</button>
    </div>

    <div class="page" id="payslip-document">
        <div class="content-wrapper">
            
            <div class="header">
                <div class="brand">
                    <h1>MENDING LAUNDRY</h1>
                    <p>Outlet Penempatan: <span><?php echo $data['nama_outlet']; ?></span></p>
                    <p>Digital Human Resource Management</p>
                </div>
                <div class="title">
                    <h2>PAYSLIP</h2>
                    <span class="id-slip">REF: <?php echo sprintf("SLIP-%s-%04d", date('ym', strtotime($data['bulan'])), $data['id_penggajian']); ?></span>
                </div>
            </div>

            <div class="emp-details">
                <div class="emp-item">
                    <label>Nama Karyawan</label>
                    <span><?php echo strtoupper($data['nama_lengkap']); ?></span>
                </div>
                <div class="emp-item">
                    <label>Periode Penggajian</label>
                    <span><?php echo strtoupper($periode); ?></span>
                </div>
                <div class="emp-item">
                    <label>ID Sistem / Username</label>
                    <span><?php echo $data['username']; ?></span>
                </div>
                <div class="emp-item">
                    <label>Posisi / Jabatan</label>
                    <span><?php echo strtoupper($role_name); ?></span>
                </div>
            </div>

            <table class="salary-table">
                <thead>
                    <tr>
                        <th>Deskripsi Pendapatan & Potongan</th>
                        <th class="right">Nominal (IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Gaji Pokok Terdaftar</strong></td>
                        <td class="amount"><?php echo number_format($data['gaji_pokok'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>Tunjangan Kehadiran, Insentif & Bonus Lembur</td>
                        <td class="amount plus">+ <?php echo number_format($data['bonus'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>Potongan Indisipliner (Terlambat / Kasbon)</td>
                        <td class="amount minus">- <?php echo number_format($data['potongan'], 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="thp-box">
                <div class="thp-label">
                    <h3>TAKE HOME PAY</h3>
                    <p>Total gaji bersih yang ditransfer/dibayarkan</p>
                </div>
                <div class="thp-amount">
                    Rp <?php echo number_format($data['total_gaji'], 0, ',', '.'); ?>
                </div>
            </div>

            <?php if(!empty($data['catatan'])): ?>
            <div class="notes">
                <strong>Catatan HRD:</strong> <?php echo nl2br(htmlspecialchars($data['catatan'])); ?>
            </div>
            <?php endif; ?>

            <div class="authenticity">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo $qr_data; ?>" class="qr-code" onerror="this.style.display='none'" alt="QR Validasi">
                <div class="auth-text">
                    <h4><i class="fas fa-check-circle"></i> Dokumen Terverifikasi Digital</h4>
                    <p>Slip gaji ini diterbitkan secara resmi melalui sistem <strong>Mending Laundry</strong> dan sah digunakan sebagai bukti penerimaan gaji tanpa memerlukan cap atau tanda tangan basah HRD.</p>
                    <p>Digital Ref ID: <span class="ref-id"><?php echo $auth_code; ?></span> | Diterbitkan: <?php echo date('d M Y, H:i'); ?></p>
                </div>
            </div>

        </div>
    </div>

    <script>
        function generatePDF() {
            // Mengubah tombol menjadi loading state agar user tahu proses sedang berjalan
            const btn = document.querySelector('.btn-pdf');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses PDF...';
            btn.disabled = true;

            // Memilih area dokumen yang akan di-convert ke PDF
            const element = document.getElementById('payslip-document');
            
            // Konfigurasi Kualitas PDF
            const opt = {
                margin:       [0, 0, 0, 0], // Margin diatur dari CSS (.page padding)
                filename:     'SlipGaji_<?php echo str_replace(' ', '', $data['nama_lengkap']); ?>_<?php echo date('M_Y', strtotime($data['bulan'])); ?>.pdf',
                image:        { type: 'jpeg', quality: 1 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'cm', format: 'a4', orientation: 'portrait' }
            };

            // Menjalankan html2pdf
            html2pdf().set(opt).from(element).save().then(() => {
                // Mengembalikan tombol ke kondisi semula setelah selesai
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>