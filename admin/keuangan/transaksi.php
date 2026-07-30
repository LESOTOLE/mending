<?php
require_once '../../includes/config.php';

// 1. OTORISASI (Owner & Admin Outlet)
checkAuth([1, 4]);
$page_title = "Kasir POS";
$conn       = connectDB();
// PERBAIKAN: Gunakan 'id_user' sesuai yang disimpan di login.php
$user_id    = $_SESSION['id_user'] ?? 0;

// 2. LOGIKA OUTLET
$user_outlet_id = $_SESSION['id_outlet'] ?? 0;
if ($user_outlet_id == 0) {
    $sql_first = "SELECT id_outlet, nama_outlet FROM outlets ORDER BY nama_outlet ASC LIMIT 1";
    $res_first = $conn->query($sql_first);
    if ($row_first = $res_first->fetch_assoc()) {
        $user_outlet_id = $row_first['id_outlet'];
        $_SESSION['id_outlet'] = $user_outlet_id;
        $_SESSION['outlet_name'] = $row_first['nama_outlet'];
    }
}
$outlet_name = $_SESSION['outlet_name'] ?? "Outlet Utama";

// 3. AMBIL DATA LAYANAN
$layanan_list = [];
$sql_layanan = "SELECT * FROM layanan WHERE id_outlet = ? AND status = 'Aktif' ORDER BY nama_layanan ASC";
$stmt = $conn->prepare($sql_layanan);
$stmt->bind_param("i", $user_outlet_id);
$stmt->execute();
$result_layanan = $stmt->get_result();
while ($row = $result_layanan->fetch_assoc()) {
    $layanan_list[] = $row;
}

// 4. AMBIL DATA KARYAWAN (PIC KASIR)
$karyawan_list = [];
$sql_karyawan = "SELECT u.id_user, k.nama_lengkap 
                 FROM users u
                 JOIN karyawan k ON u.id_user = k.id_user
                 WHERE u.id_role IN (3, 4) AND k.id_outlet = ? AND u.is_active = 1 
                 ORDER BY k.nama_lengkap ASC";
$stmt = $conn->prepare($sql_karyawan);
$stmt->bind_param("i", $user_outlet_id);
$stmt->execute();
$res_karyawan = $stmt->get_result();
while ($row = $res_karyawan->fetch_assoc()) {
    $karyawan_list[] = $row;
}

// 5. AMBIL DATA PELANGGAN LAMA
$pelanggan_list = [];
$res_pelanggan = $conn->query("SELECT id_pelanggan, nama_pelanggan, no_hp FROM pelanggan ORDER BY nama_pelanggan ASC");
while ($row = $res_pelanggan->fetch_assoc()) {
    $pelanggan_list[] = $row;
}

$json_layanan = json_encode($layanan_list) ?: '[]';

include '../../includes/header_pos.php';
?>

<style>
    body {
        background: #f4f6f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .pos-layout {
        height: calc(100vh - 70px);
    }

    .menu-area {
        overflow-y: auto;
        padding-bottom: 80px;
        height: 100%;
    }

    .cart-area {
        background: white;
        border-left: 1px solid #e3e6f0;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .cart-items-scroll {
        flex-grow: 1;
        overflow-y: auto;
        background: #f8f9fc;
        padding: 15px;
    }

    .checkout-footer {
        background: white;
        padding: 20px;
        border-top: 1px solid #e3e6f0;
        flex-shrink: 0;
        box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05);
        z-index: 10;
    }

    .service-card {
        cursor: pointer;
        border-radius: 15px;
        border: 2px solid transparent;
        transition: all 0.2s ease;
        background: white;
    }

    .service-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
        border-color: #4e73df;
    }

    .service-card:active {
        transform: scale(0.95);
        background: #eef2ff;
    }

    button,
    input,
    select {
        touch-action: manipulation;
        /* Hindari delay tap di tablet */
    }

    .qty-input-box {
        width: 75px;
        text-align: center;
        font-size: 1.2rem;
        font-weight: bold;
        border: 2px solid #4e73df;
        border-radius: 8px;
        color: #4e73df;
        background: #f8f9fc;
    }

    .qty-input-box:focus {
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        background: white;
    }

    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>

<div class="container-fluid p-0 pos-layout">
    <div class="row g-0 h-100">

        <div class="col-lg-7 col-md-7 p-4 menu-area bg-light">
            <div class="d-flex justify-content-between align-items-center mb-4 sticky-top bg-light py-3" style="top:-25px; z-index:5; border-bottom: 1px solid #e3e6f0;">
                <div class="d-flex align-items-center">
                    <h4 class="fw-bold m-0 text-primary"><i class="fas fa-store-alt me-2"></i> <?php echo htmlspecialchars($outlet_name); ?></h4>
                    <span class="badge bg-secondary ms-3 px-3 py-2" id="liveClock" style="font-size: 0.9em; letter-spacing: 1px;">00:00:00</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success fw-bold shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalRekapKasir">
                        <i class="fas fa-cash-register me-1"></i> Rekap Kasir
                    </button>
                    <button class="btn btn-warning fw-bold shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalCariOrder">
                        <i class="fas fa-search me-1"></i> Ambil / Cari Nota
                    </button>
                    <div class="input-group" style="width: 250px;">
                        <span class="input-group-text bg-white border-primary border-end-0"><i class="fas fa-search text-primary"></i></span>
                        <input type="text" id="searchService" class="form-control border-primary border-start-0" placeholder="Cari layanan...">
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <?php foreach ($layanan_list as $l): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 service-item-col" data-name="<?php echo strtolower($l['nama_layanan']); ?>">
                        <div class="card service-card shadow-sm p-4 text-center h-100" onclick="window.addToCart(<?php echo $l['id_layanan']; ?>)">
                            <div class="mb-3 text-primary mt-2">
                                <?php
                                $n = strtolower($l['nama_layanan']);
                                $icon = 'fa-tshirt';
                                if (strpos($n, 'bed') !== false || strpos($n, 'selimut') !== false) $icon = 'fa-bed';
                                if (strpos($n, 'sepatu') !== false) $icon = 'fa-shoe-prints';
                                if (strpos($n, 'helm') !== false) $icon = 'fa-hard-hat';
                                if (strpos($n, 'karpet') !== false) $icon = 'fa-scroll';
                                if (strpos($n, 'satuan') !== false) $icon = 'fa-user-tie';
                                ?>
                                <i class="fas <?php echo $icon; ?> fa-3x"></i>
                            </div>
                            <h6 class="fw-bold text-dark text-truncate small mb-2"><?php echo htmlspecialchars($l['nama_layanan']); ?></h6>
                            <span class="badge bg-primary rounded-pill px-3 py-2">Rp <?php echo number_format($l['harga'], 0, ',', '.'); ?> / <?php echo $l['satuan']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-5 col-md-5 cart-area shadow-lg">
            <div class="p-3 bg-primary text-white d-flex justify-content-between align-items-center flex-shrink-0">
                <h5 class="mb-0 fw-bold"><i class="fas fa-shopping-basket me-2"></i> Keranjang Cucian</h5>
                <span class="badge bg-white text-primary rounded-pill fs-6" id="cartCountBadge">0 Item</span>
            </div>

            <div class="cart-items-scroll" id="keranjangList">
                <div class="text-center mt-5 pt-5 text-muted" id="emptyCartState">
                    <i class="fas fa-shopping-cart fa-4x mb-3 text-gray-300 opacity-50"></i>
                    <p class="fw-bold">Keranjang Masih Kosong</p>
                    <small>Silakan klik layanan di sebelah kiri untuk menambahkan ke nota.</small>
                </div>
            </div>

            <div class="checkout-footer">
                <form id="formCheckout" action="proses_aksi_pos.php" method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="outlet_id" value="<?php echo $user_outlet_id; ?>">
                    <input type="hidden" name="items_data" id="itemsDataInput">
                    <input type="hidden" name="total_harga" id="totalHargaInput">
                    <input type="hidden" name="id_pelanggan_lama" id="id_pelanggan_lama" value="">

                    <div class="bg-light p-3 rounded border mb-3">
                        <label class="small fw-bold text-primary mb-2"><i class="fas fa-user me-1"></i> Data Pelanggan</label>
                        <div class="row g-2">
                            <div class="col-7">
                                <input class="form-control form-control-lg fs-6 fw-bold border-primary shadow-sm" list="pelangganOptions" id="inputNamaPelanggan" name="nama_pelanggan" placeholder="Nama / Ketik Baru..." required autocomplete="off">
                                <datalist id="pelangganOptions">
                                    <?php foreach ($pelanggan_list as $p): ?>
                                        <option value="<?php echo $p['nama_pelanggan']; ?>" data-id="<?php echo $p['id_pelanggan']; ?>" data-hp="<?php echo $p['no_hp']; ?>">
                                        <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-5">
                                <input type="text" class="form-control form-control-lg fs-6 shadow-sm" id="inputHpPelanggan" name="no_hp_pelanggan" placeholder="No HP/WA">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="small fw-bold text-gray-600 mb-1">Status</label>
                            <select class="form-select form-select-lg fs-6 fw-bold border-danger text-danger shadow-sm" name="status_pembayaran" id="status_pembayaran">
                                <option value="Belum Lunas">Belum Lunas</option>
                                <option value="Lunas" class="text-success">Lunas</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small fw-bold text-gray-600 mb-1">Metode</label>
                            <select class="form-select form-select-lg fs-6 fw-bold shadow-sm" name="metode_pembayaran" id="metode_pembayaran">
                                <option value="Tunai">Tunai</option>
                                <option value="Transfer">Transfer</option>
                                <option value="QRIS">QRIS</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small fw-bold text-gray-600 mb-1">Lokasi Rak</label>
                            <input type="text" class="form-control form-control-lg fs-6 shadow-sm" name="lokasi_rak" placeholder="Cth: A-1">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="small fw-bold text-gray-600 mb-1">Catatan Khusus</label>
                            <input type="text" class="form-control form-control-lg fs-6 shadow-sm" name="catatan" placeholder="Cth: Noda luntur, jangan disetrika panas">
                        </div>
                        <div class="col-5">
                            <label class="small fw-bold text-gray-600 mb-1">Diskon</label>
                            <div class="input-group input-group-lg shadow-sm">
                                <select class="form-select bg-light fw-bold border-danger text-danger fs-6" id="tipe_diskon" style="max-width: 75px;">
                                    <option value="rp">Rp</option>
                                    <option value="persen">%</option>
                                </select>
                                <input type="number" class="form-control text-danger fw-bold border-danger fs-6" id="input_diskon_raw" placeholder="0" min="0" step="0.01">
                            </div>
                            <input type="hidden" name="diskon" id="input_diskon" value="0">
                            <div class="text-danger fw-bold mt-1" id="label_nominal_diskon" style="display:none; font-size: 0.85rem;"></div>
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded border border-primary mb-3 shadow-sm" id="boxKalkulasi" style="display:none;">
                        <div class="row g-2 align-items-center">
                            <div class="col-6">
                                <label class="small fw-bold text-success mb-2">Terima Uang (Rp)</label>
                                <input type="number" class="form-control form-control-lg fs-5 fw-bold text-success border-success" name="jumlah_bayar" id="input_bayar" placeholder="0">
                            </div>
                            <div class="col-6 text-end">
                                <label class="small fw-bold text-primary mb-2">Kembalian</label>
                                <div class="h4 fw-bold text-primary m-0" id="input_kembalian">Rp 0</div>
                                <input type="hidden" name="kembalian" id="kembalian_asli" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <select class="form-select form-select-lg fs-6 fw-bold bg-light shadow-sm" name="id_user_kasir" required>
                                <option value="">-- Pilih PIC --</option>
                                <?php foreach ($karyawan_list as $k): ?>
                                    <option value="<?php echo $k['id_user']; ?>"><?php echo explode(' ', $k['nama_lengkap'])[0]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-7">
                            <input type="password" class="form-control form-control-lg text-center fw-bold letter-spacing-2 shadow-sm" name="auth_pin" placeholder="PIN KASIR (6 DIGIT)" maxlength="6" pattern="[0-9]{6}" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold d-flex justify-content-between align-items-center px-4 rounded-pill shadow" id="btnCheckout" disabled>
                        <span><i class="fas fa-check-circle me-2"></i> PROSES TRANSAKSI</span>
                        <span id="grandTotalDisplay" class="fs-5 bg-white text-primary px-3 py-1 rounded-pill">Rp 0</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pencarian & Pengambilan -->
<div class="modal fade" id="modalCariOrder" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-search me-2"></i> Cari Nota / Pengambilan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="input-group input-group-lg mb-3 shadow-sm">
                    <span class="input-group-text bg-white"><i class="fas fa-barcode"></i></span>
                    <input type="text" id="inputCariOrder" class="form-control" placeholder="Ketik No Invoice, Nama, atau No HP..." autocomplete="off">
                </div>
                <div id="hasilPencarian" class="bg-white rounded p-2" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                    <!-- Hasil AJAX akan masuk ke sini -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Rekap Kasir (Shift) -->
<div class="modal fade" id="modalRekapKasir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Laporan Tutup Kasir</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white" id="hasilRekapKasir">
                <div class="text-center my-5">
                    <i class="fas fa-spinner fa-spin fa-3x text-success mb-3"></i>
                    <p class="text-muted">Menghitung uang kasir...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atur Rak -->
<div class="modal fade" id="modalAturRak" tabindex="-1">
    <div class="modal-dialog">
        <form id="formAturRak" method="POST">
            <?php echo csrfField(); ?>
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-check-circle me-2"></i> Konfirmasi Selesai & Rak</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-white">
                    <p class="mb-3">Nota Atas Nama: <strong class="text-primary" id="rak_nama_pelanggan"></strong></p>
                    <input type="hidden" name="aksi" value="selesai_rak">
                    <input type="hidden" name="id_transaksi" id="rak_id_transaksi">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Lokasi Rak / Penyimpanan</label>
                        <input type="text" class="form-control" name="lokasi_rak" placeholder="Contoh: Rak A-1" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fas fa-save me-1"></i> Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="/mending/assets/vendor/js/jquery-3.6.0.min.js"></script>
<script src="/mending/assets/vendor/js/sweetalert2.all.min.js"></script>

<script>
    const layananData = <?php echo $json_layanan; ?>;
    let keranjang = [];
    const formatRupiah = (n) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(n);

    // --- FUNGSI KERANJANG & UI (Sama seperti sebelumnya) ---
    window.addToCart = function(id) {
        const item = layananData.find(i => i.id_layanan == id);
        if (!item) return;
        const exist = keranjang.find(i => i.id == id);
        if (exist) {
            exist.qty += 1;
        } else {
            keranjang.push({
                id: item.id_layanan,
                nama: item.nama_layanan,
                harga: parseFloat(item.harga),
                satuan: item.satuan,
                qty: 1
            });
        }
        renderCart();
    };

    window.updateQtyManual = function(index, value) {
        let val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
        keranjang[index].qty = val;
        renderCart();
    };

    window.removeItem = function(index) {
        keranjang.splice(index, 1);
        renderCart();
    };

    function renderCart() {
        const container = document.getElementById('keranjangList');
        const btn = document.getElementById('btnCheckout');
        container.innerHTML = '';

        if (keranjang.length === 0) {
            container.innerHTML = document.getElementById('emptyCartState').outerHTML;
            container.querySelector('#emptyCartState').style.display = 'block';
            btn.disabled = true;
            document.getElementById('grandTotalDisplay').innerText = 'Rp 0';
            document.getElementById('cartCountBadge').innerText = '0 Item';
            return;
        }

        btn.disabled = false;
        let total = 0;
        let totalQty = 0;
        keranjang.forEach((item, i) => {
            let sub = item.qty * item.harga;
            total += sub;
            totalQty += parseFloat(item.qty);
            container.innerHTML += `
            <div class="card mb-3 border-0 shadow-sm p-3 rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div style="width: 40%">
                        <div class="fw-bold text-dark fs-6">${item.nama}</div>
                        <span class="text-primary fw-bold">${formatRupiah(item.harga)}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary px-3 py-2 shadow-sm rounded" onclick="window.updateQtyManual(${i}, ${item.qty - 1})"><i class="fas fa-minus"></i></button>
                        <input type="number" step="0.01" class="qty-input-box py-2" value="${item.qty}" onchange="window.updateQtyManual(${i}, this.value)" onclick="this.select()">
                        <button type="button" class="btn btn-outline-secondary px-3 py-2 shadow-sm rounded" onclick="window.updateQtyManual(${i}, ${item.qty + 1})"><i class="fas fa-plus"></i></button>
                        <small class="fw-bold text-muted ms-2">${item.satuan}</small>
                    </div>
                    <div class="text-end" style="width: 20%">
                        <div class="fw-bold text-dark fs-6">${formatRupiah(sub)}</div>
                    </div>
                    <button type="button" onclick="window.removeItem(${i})" class="btn text-danger px-3 py-2 shadow-sm rounded-circle"><i class="fas fa-times fa-lg"></i></button>
                </div>
            </div>`;
        });

        document.getElementById('totalHargaInput').value = total;
        document.getElementById('cartCountBadge').innerText = totalQty + ' Pcs/Kg';
        document.getElementById('itemsDataInput').value = JSON.stringify(keranjang);
        hitungKembalian();
    }

    // Hitung ulang jika diskon berubah
    $('#input_diskon_raw, #tipe_diskon').on('input change', hitungKembalian);

    // --- FUNGSI JAM DIGITAL LIVE ---
    function updateClock() {
        const now = new Date();
        const clockEl = document.getElementById('liveClock');
        if (clockEl) {
            clockEl.innerText = now.toLocaleTimeString('id-ID', {
                hour12: false
            });
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    function hitungKembalian() {
        let t = parseFloat(document.getElementById('totalHargaInput').value) || 0;

        let rawDiskon = parseFloat(document.getElementById('input_diskon_raw').value) || 0;
        let tipeDiskon = document.getElementById('tipe_diskon').value;
        let nominalDiskon = 0;

        if (tipeDiskon === 'persen') {
            nominalDiskon = (rawDiskon / 100) * t;
            document.getElementById('label_nominal_diskon').innerText = '- ' + formatRupiah(nominalDiskon);
            document.getElementById('label_nominal_diskon').style.display = 'block';
        } else {
            nominalDiskon = rawDiskon;
            document.getElementById('label_nominal_diskon').style.display = 'none';
        }

        document.getElementById('input_diskon').value = nominalDiskon;

        let grandTotal = t - nominalDiskon;
        if (grandTotal < 0) grandTotal = 0;

        document.getElementById('grandTotalDisplay').innerText = formatRupiah(grandTotal);

        let b = parseFloat(document.getElementById('input_bayar').value) || 0;
        let k = b - grandTotal;
        let el = document.getElementById('input_kembalian');

        if (k < 0) {
            el.className = "h5 fw-bold text-danger m-0";
            el.innerText = "Kurang " + formatRupiah(Math.abs(k));
        } else {
            el.className = "h5 fw-bold text-primary m-0";
            el.innerText = formatRupiah(k);
        }
        document.getElementById('kembalian_asli').value = k;
    }

    $(document).ready(function() {
        // --- JQUERY EVENT LISTENER ---
        $('#formCheckout').on('submit', function(e) {
            e.preventDefault();
            let formData = $(this).serialize();
            let btn = $('#btnCheckout');
            let originalBtnHtml = btn.html();

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> MEMPROSES...');

            $.ajax({
                url: 'proses_aksi_pos.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Transaksi Berhasil!',
                            text: 'Nota telah diterbitkan.',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-print"></i> Cetak Struk',
                            cancelButtonText: 'Order Baru',
                            confirmButtonColor: '#4e73df'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('../keuangan/cetak_struk.php?id=' + response.id_transaksi, '_blank');
                            }
                            keranjang = [];
                            renderCart();
                            $('#formCheckout')[0].reset();
                            $('#id_pelanggan_lama').val('');
                            $('#input_diskon_raw').val('');
                            $('#input_diskon').val('0');
                            $('#label_nominal_diskon').hide();
                            $('#boxKalkulasi').hide();
                            $('#input_kembalian').text('Rp 0');
                            $('#status_pembayaran').removeClass('text-success border-success').addClass('text-danger border-danger');
                        });
                    } else {
                        Swal.fire('Gagal!', response.pesan || 'Terjadi kesalahan tidak diketahui', 'error');
                        $('input[name="auth_pin"]').val('').focus();
                    }
                },
                error: function() {
                    Swal.fire('Error Sistem!', 'Tidak dapat terhubung ke server.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        });

        $('#inputNamaPelanggan').on('input', function() {
            var val = $(this).val();
            var selectedOption = $('#pelangganOptions option').filter(function() {
                return this.value === val;
            });
            if (selectedOption.length) {
                $('#id_pelanggan_lama').val(selectedOption.data('id'));
                $('#inputHpPelanggan').val(selectedOption.data('hp')).prop('readonly', true).addClass('bg-light');
            } else {
                $('#id_pelanggan_lama').val('');
                $('#inputHpPelanggan').val('').prop('readonly', false).removeClass('bg-light');
            }
        });

        $('#status_pembayaran').on('change', function() {
            if ($(this).val() === 'Lunas') {
                $('#boxKalkulasi').slideDown();
                $(this).removeClass('border-danger text-danger').addClass('border-success text-success');
                if ($('#metode_pembayaran').val() === 'Tunai') {
                    $('#input_bayar').prop('readonly', false).focus();
                }
            } else {
                $('#boxKalkulasi').slideUp();
                $(this).removeClass('border-success text-success').addClass('border-danger text-danger');
                $('#input_bayar').val('');
            }
            hitungKembalian();
        });

        $('#input_bayar').on('input', hitungKembalian);

        $('#metode_pembayaran').on('change', function() {
            let m = $(this).val();
            let t = parseFloat($('#totalHargaInput').val()) || 0;
            if (m === 'Tunai') {
                $('#input_bayar').prop('readonly', false).val('').focus();
            } else {
                $('#input_bayar').prop('readonly', true).val(t);
            }
            hitungKembalian();
        });

        $('#searchService').on('keyup', function() {
            let val = $(this).val().toLowerCase();
            $('.service-item-col').each(function() {
                $(this).toggle($(this).data('name').indexOf(val) > -1);
            });
        });

        // ==========================================
        // FITUR MODAL PENCARIAN & AJAX BARU
        // ==========================================

        // Fungsi untuk mengambil data ke dalam Modal
        window.loadDataNota = function(keyword) {
            $('#hasilPencarian').html('<div class="text-center my-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
            $.post('cari_transaksi_ajax.php', {
                keyword: keyword,
                outlet_id: "<?php echo $user_outlet_id; ?>",
                csrf_token: "<?php echo generateCsrfToken(); ?>"
            }, function(data) {
                $('#hasilPencarian').html(data);
            });
        };

        // Event saat modal pertama kali dibuka (Auto Load Default List)
        $('#modalCariOrder').on('shown.bs.modal', function() {
            $('#inputCariOrder').val(''); // Pastikan kosong
            window.loadDataNota(''); // Kirim string kosong untuk trigger default list
            $('#inputCariOrder').focus();
        });

        // Fitur Pencarian Real-time Modal
        $('#inputCariOrder').on('keyup', function() {
            window.loadDataNota($(this).val());
        });

        // ==========================================
        // FITUR REKAP KASIR (TUTUP SHIFT)
        // ==========================================
        $('#modalRekapKasir').on('shown.bs.modal', function() {
            $('#hasilRekapKasir').html('<div class="text-center my-5"><i class="fas fa-spinner fa-spin fa-3x text-success mb-3"></i><p class="text-muted">Menghitung uang kasir...</p></div>');
            $.post('rekap_kasir_ajax.php', {
                outlet_id: "<?php echo $user_outlet_id; ?>",
                csrf_token: "<?php echo generateCsrfToken(); ?>"
            }, function(data) {
                $('#hasilRekapKasir').html(data);
            }).fail(function() {
                $('#hasilRekapKasir').html('<div class="alert alert-danger">Gagal memuat rekap. Periksa koneksi internet.</div>');
            });
        });
    });

    // ==========================================
    // FITUR SELESAI & ATUR RAK
    // ==========================================
    window.aksiAturRak = (id, nama) => {
        $('#rak_id_transaksi').val(id);
        $('#rak_nama_pelanggan').text(nama);
        $('#formAturRak')[0].reset();
        $('#modalAturRak').modal('show');
    };

    $('#formAturRak').on('submit', function(e) {
        e.preventDefault();
        let btn = $(this).find('button[type="submit"]');
        let originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>');

        $.post('proses_aksi_pos.php', $(this).serialize())
            .done(function(response) {
                $('#modalAturRak').modal('hide');
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Cucian telah diupdate ke status Selesai dan lokasi rak tersimpan.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    window.loadDataNota($('#inputCariOrder').val());
                } else {
                    Swal.fire('Error', response.pesan || 'Gagal memproses data.', 'error');
                }
            }).fail(function() {
                Swal.fire('Error', 'Gagal memproses data. Coba lagi.', 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalHtml);
            });
    });

    // --- FUNGSI LUNASI & AMBIL (Tanpa Refresh Layar / Modal Tetap Buka) ---
    window.aksiLunasi = (id, nama, total) => {
        Swal.fire({
            title: 'Pelunasan Tagihan',
            html: `Atas Nama: <b class="text-primary">${nama}</b><br>Sisa Tagihan: <b class="text-danger">Rp ${formatRupiah(total)}</b><br><br><small class="text-muted">Masukkan nominal bayar di bawah ini:</small>`,
            input: 'number',
            inputValue: total,
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            cancelButtonColor: '#858796',
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Konfirmasi Pelunasan',
            cancelButtonText: 'Batal'
        }).then(r => {
            if (r.isConfirmed) {
                if (r.value < total) {
                    Swal.fire('Oops!', 'Nominal bayar tidak boleh kurang dari sisa tagihan.', 'warning');
                    return;
                }

                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Eksekusi PHP di belakang layar via AJAX
                $.post('proses_aksi_pos.php', {
                    aksi: 'lunasi',
                    id_transaksi: id,
                    bayar_susulan: r.value,
                    csrf_token: "<?php echo generateCsrfToken(); ?>"
                }).done(function() {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Tagihan berhasil dilunasi.',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Refresh isi Modal TANPA menutupnya
                    window.loadDataNota($('#inputCariOrder').val());
                }).fail(function() {
                    Swal.fire('Error', 'Gagal memproses data.', 'error');
                });
            }
        });
    };

    window.aksiAmbil = (id, nama) => {
        Swal.fire({
            title: 'Serahkan Cucian?',
            html: `Nota atas nama <b class="text-primary">${nama}</b> akan ditandai sebagai selesai dan diserahkan.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#4e73df',
            cancelButtonColor: '#858796',
            confirmButtonText: '<i class="fas fa-box-open me-1"></i> Ya, Serahkan',
            cancelButtonText: 'Batal'
        }).then(r => {
            if (r.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Eksekusi PHP di belakang layar via AJAX
                $.post('proses_aksi_pos.php', {
                    aksi: 'ambil',
                    id_transaksi: id,
                    csrf_token: "<?php echo generateCsrfToken(); ?>"
                }).done(function() {
                    Swal.fire({
                        title: 'Berhasil Diserahkan!',
                        text: 'Cucian telah diserahkan ke pelanggan. Cetak struk pengambilan?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-print"></i> Cetak Struk Pengambilan',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#4e73df'
                    }).then((res) => {
                        if (res.isConfirmed) {
                            window.open('../keuangan/cetak_struk.php?id=' + id + '&tipe=ambil', '_blank');
                        }
                    });
                    // Refresh isi Modal TANPA menutupnya
                    window.loadDataNota($('#inputCariOrder').val());
                }).fail(function() {
                    Swal.fire('Error', 'Gagal memproses data.', 'error');
                });
            }
        });
    };
</script>