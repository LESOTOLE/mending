<?php
require_once '../../includes/config.php';
checkAuth([1, 4, 2]); // Hanya Owner & Admin Outlet

$conn = connectDB();
$id = (int)($_GET['id'] ?? 0);
$tipe = $_GET['tipe'] ?? 'deposit';
// 1. AMBIL DATA HEADER TRANSAKSI + OUTLET + KARYAWAN + PELANGGAN
$sql = "SELECT t.*, 
               o.nama_outlet, o.alamat,
               k.nama_lengkap as nama_kasir,
               p.nama_pelanggan
        FROM transaksi t
        JOIN outlets o ON t.id_outlet = o.id_outlet
        LEFT JOIN karyawan k ON t.id_user = k.id_user
        LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
        WHERE t.id_transaksi = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    die("Transaksi tidak ditemukan.");
}

// 2. AMBIL DATA ITEM BELANJA
// PERBAIKAN: Pastikan kolom 'id_layanan' dan 'id_transaksi' sesuai
$sql_detail = "SELECT td.*, l.nama_layanan 
               FROM transaksi_detail td
               JOIN layanan l ON td.id_layanan = l.id_layanan
               WHERE td.id_transaksi = ?";
$stmt_d = $conn->prepare($sql_detail);
$stmt_d->bind_param("i", $id);
$stmt_d->execute();
$items = $stmt_d->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Struk #<?php echo $trx['no_invoice']; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .container {
            width: 58mm;
            padding: 2px;
            margin: 0 auto;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .item-name {
            font-size: 10px;
            padding-top: 5px;
        }

        .item-price {
            font-size: 10px;
        }

        @media print {
            .no-print {
                display: none;
            }

            .container {
                width: 58mm;
                margin: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="container">

        <div class="text-center">
            <h3 style="margin: 2px 0; font-size: 14px;">MENDING LAUNDRY</h3>
            <span><?php echo htmlspecialchars($trx['nama_outlet']); ?></span><br>
            <span style="font-size: 9px;"><?php echo htmlspecialchars($trx['alamat']); ?></span><br>
        </div>
        <div class="text-center bold" style="margin-bottom: 5px;">
            <?php echo ($tipe == 'ambil') ? 'STRUK PENGAMBILAN' : 'STRUK MASUK / DEPOSIT'; ?>
        </div>
        <div class="divider"></div>

        <table style="font-size: 9px;">
            <tr>
                <td>Nota</td>
                <td class="text-right">: <?php echo $trx['no_invoice']; ?></td>
            </tr>
            <tr>
                <td>Tgl</td>
                <td class="text-right">: <?php echo date('d/m/y H:i', strtotime($trx['tgl_masuk'])); ?></td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td class="text-right">: <?php echo explode(' ', $trx['nama_kasir'])[0]; ?></td>
            </tr>
            <tr>
                <td>Cust</td>
                <td class="text-right">: <?php echo htmlspecialchars($trx['nama_pelanggan'] ?? 'Umum'); ?></td>
            </tr>
            <?php if (!empty($trx['catatan'])): ?>
            <tr>
                <td style="vertical-align: top;">Notes</td>
                <td class="text-right">: <?php echo nl2br(htmlspecialchars($trx['catatan'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="divider"></div>

        <table>
            <?php while ($row = $items->fetch_assoc()): ?>
                <tr>
                    <td colspan="2" class="item-name"><?php echo htmlspecialchars($row['nama_layanan']); ?></td>
                </tr>
                <tr>
                    <td class="item-price">
                        <?php echo (float)$row['qty']; ?> x <?php echo number_format($row['harga'], 0, ',', '.'); ?>
                    </td>
                    <td class="text-right item-price">
                        <?php echo number_format($row['subtotal'], 0, ',', '.'); ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <div class="divider"></div>

        <table>
            <tr>
                <td class="bold">TOTAL HARGA</td>
                <td class="text-right bold">Rp <?php echo number_format($trx['total_harga'], 0, ',', '.'); ?></td>
            </tr>
            <?php if ($trx['diskon'] > 0): ?>
            <tr>
                <td>Diskon</td>
                <td class="text-right">-Rp <?php echo number_format($trx['diskon'], 0, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="bold">GRAND TOTAL</td>
                <td class="text-right bold">
                    Rp <?php echo number_format($trx['grand_total'], 0, ',', '.'); ?>
                </td>
            </tr>

            <?php if ($trx['metode_pembayaran'] == 'Tunai'): ?>
                <tr>
                    <td>Bayar</td>
                    <td class="text-right">Rp <?php echo number_format($trx['bayar'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td>Kembali</td>
                    <td class="text-right">Rp <?php echo number_format($trx['kembalian'], 0, ',', '.'); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td>Metode</td>
                    <td class="text-right"><?php echo strtoupper($trx['metode_pembayaran']); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td style="padding-top:3px;">Status</td>
                <td class="text-right bold" style="padding-top:3px;">
                    <?php echo strtoupper($trx['status_pembayaran']); ?>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="text-center" style="margin-top: 15px;">
            <div class="bold">Terima Kasih!</div>

            <?php if ($trx['status_laundry'] == 'Diambil'): ?>
                <div style="font-size: 10px; margin-top: 5px;">
                    Cucian telah diserahkan kepada pelanggan.<br>
                    Semoga hari Anda menyenangkan!
                </div>

            <?php else: ?>
                <div style="font-size: 10px; margin-top: 5px;">
                    Harap bawa nota ini saat pengambilan barang.<br>
                    Cucian yang tidak diambil lebih dari 30 hari bukan tanggung jawab kami.
                </div>
            <?php endif; ?>
        </div>

        <br>
        <button class="no-print" onclick="window.print()" style="width: 100%;">Cetak</button>
    </div>
</body>

</html>