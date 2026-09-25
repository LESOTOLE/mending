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
$categories_available = [];
$sql_layanan = "SELECT * FROM layanan WHERE id_outlet = ? AND status = 'Aktif' ORDER BY kategori ASC, nama_layanan ASC";
$stmt = $conn->prepare($sql_layanan);
$stmt->bind_param("i", $user_outlet_id);
$stmt->execute();
$result_layanan = $stmt->get_result();
while ($row = $result_layanan->fetch_assoc()) {
    if (empty($row['kategori'])) {
        $row['kategori'] = 'Cuci Kiloan';
    }
    $layanan_list[] = $row;
    $cat = $row['kategori'];
    if (!isset($categories_available[$cat])) {
        $categories_available[$cat] = 0;
    }
    $categories_available[$cat]++;
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

<script>
    const layananData = <?php echo $json_layanan; ?>;
    let keranjang = [];
    window.currentSelectedCategory = 'all';

    const formatRupiah = (n) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(n);

    const emptyCartHtml = `
        <div class="text-center mt-5 pt-5 text-muted" id="emptyCartState">
            <i class="fas fa-shopping-cart fa-4x mb-3 text-gray-300 opacity-50"></i>
            <p class="fw-bold">Keranjang Masih Kosong</p>
            <small>Silakan klik layanan di sebelah kiri untuk menambahkan ke nota.</small>
        </div>`;

    window.addToCart = function(id) {
        const item = layananData.find(i => i.id_layanan == id);
        if (!item) {
            console.error("Layanan tidak ditemukan:", id);
            return;
        }
        const exist = keranjang.find(i => i.id == item.id_layanan);
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
        window.renderCart();

        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1000,
                timerProgressBar: false
            });
            Toast.fire({
                icon: 'success',
                title: item.nama_layanan + ' Ditambahkan'
            });
        }
    };

    window.updateQtyManual = function(index, value) {
        let val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;
        keranjang[index].qty = val;
        window.renderCart();
    };

    window.removeItem = function(index) {
        keranjang.splice(index, 1);
        window.renderCart();
    };

    window.renderCart = function() {
        const container = document.getElementById('keranjangList');
        const btn = document.getElementById('btnCheckout');
        if (!container) return;

        if (keranjang.length === 0) {
            container.innerHTML = emptyCartHtml;
            if (btn) btn.disabled = true;
            let gtd = document.getElementById('grandTotalDisplay');
            if (gtd) gtd.innerText = 'Rp 0';
            let ccb = document.getElementById('cartCountBadge');
            if (ccb) ccb.innerText = '0 Item';
            let thi = document.getElementById('totalHargaInput');
            if (thi) thi.value = '0';
            let idii = document.getElementById('itemsDataInput');
            if (idii) idii.value = '[]';
            if (typeof hitungKembalian === 'function') hitungKembalian();
            return;
        }

        if (btn) btn.disabled = false;
        let total = 0;
        let totalQty = 0;
        let html = '';
        keranjang.forEach((item, i) => {
            let sub = item.qty * item.harga;
            total += sub;
            totalQty += parseFloat(item.qty);
            html += `
            <div class="card mb-3 border-0 shadow-sm p-3 rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div style="width: 40%">
                        <div class="fw-bold text-dark fs-6">${item.nama}</div>
                        <span class="text-primary fw-bold">${formatRupiah(item.harga)}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <input type="number" step="0.01" min="0.01" class="form-control qty-input-box shadow-sm" value="${item.qty}" onchange="window.updateQtyManual(${i}, this.value)" onkeyup="window.updateQtyManual(${i}, this.value)">
                        <span class="small fw-bold text-muted">${item.satuan}</span>
                    </div>
                    <div class="text-end" style="width: 25%">
                        <div class="fw-bold text-dark fs-6">${formatRupiah(sub)}</div>
                        <button type="button" class="btn btn-link text-danger p-0 small text-decoration-none" onclick="window.removeItem(${i})">
                            <i class="fas fa-trash-alt me-1"></i> Hapus
                        </button>
                    </div>
                </div>
            </div>`;
        });

        container.innerHTML = html;
        let gtd = document.getElementById('grandTotalDisplay');
        if (gtd) gtd.innerText = formatRupiah(total);
        let ccb = document.getElementById('cartCountBadge');
        if (ccb) ccb.innerText = totalQty + ' Item';
        let thi = document.getElementById('totalHargaInput');
        if (thi) thi.value = total;
        let idii = document.getElementById('itemsDataInput');
        if (idii) idii.value = JSON.stringify(keranjang);
        if (typeof hitungKembalian === 'function') hitungKembalian();
    };

    window.filterByCategory = function(categoryName, element) {
        window.currentSelectedCategory = categoryName || 'all';

        if (typeof $ !== 'undefined') {
            $('.category-pill').removeClass('btn-primary active').addClass('btn-outline-primary');
            $('.category-pill').find('.badge').removeClass('bg-white text-primary').addClass('bg-primary text-white');

            if (element) {
                $(element).removeClass('btn-outline-primary').addClass('btn-primary active');
                $(element).find('.badge').removeClass('bg-primary text-white').addClass('bg-white text-primary');
            }
        }

        window.applyServiceFilters();
    };

    window.applyServiceFilters = function() {
        if (typeof $ === 'undefined') return;
        let searchVal = $('#searchService').val() ? $('#searchService').val().toLowerCase().trim() : '';
        let visibleCount = 0;
        let selCat = window.currentSelectedCategory || 'all';

        $('.service-item-col').each(function() {
            let name = ($(this).attr('data-name') || '').toString().toLowerCase();
            let category = ($(this).attr('data-category') || '').toString();

            let matchCategory = (selCat === 'all' || category === selCat);
            let matchSearch = (searchVal === '' || name.indexOf(searchVal) > -1 || category.toLowerCase().indexOf(searchVal) > -1);

            if (matchCategory && matchSearch) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });

        let noDataEl = $('#noServiceState');
        if (visibleCount === 0) {
            if (noDataEl.length === 0) {
                $('#serviceGrid').append(`
                    <div id="noServiceState" class="col-12 text-center py-5 text-muted">
                        <i class="fas fa-search-minus fa-3x mb-3 opacity-50 text-secondary"></i>
                        <h6 class="fw-bold text-secondary">Layanan Tidak Ditemukan</h6>
                        <small>Tidak ada layanan yang sesuai dengan kategori atau kata kunci ini.</small>
                    </div>
                `);
            } else {
                noDataEl.show();
            }
        } else if (noDataEl.length > 0) {
            noDataEl.hide();
        }
    };
</script>

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
                <div class="d-flex flex-wrap gap-2">
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

            <!-- NAVIGASI KATEGORI LAYANAN -->
            <div class="mb-4">
                <div class="d-flex align-items-center mb-2">
                    <label class="fw-bold text-dark small mb-0 me-2"><i class="fas fa-th-large text-primary me-1"></i> Kategori Layanan:</label>
                </div>
                <div class="d-flex gap-2 flex-wrap" id="categoryPillContainer">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold category-pill active" data-category-target="all" onclick="window.filterByCategory('all', this)">
                        Semua <span class="badge bg-white text-primary rounded-pill ms-1"><?php echo count($layanan_list); ?></span>
                    </button>
                    <?php foreach ($categories_available as $catName => $count): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold category-pill" data-category-target="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>" onclick="window.filterByCategory(this.getAttribute('data-category-target'), this)">
                            <?php echo htmlspecialchars($catName); ?> <span class="badge bg-primary text-white rounded-pill ms-1"><?php echo $count; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3" id="serviceGrid">
                <?php foreach ($layanan_list as $l): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 service-item-col" 
                         data-name="<?php echo htmlspecialchars(strtolower($l['nama_layanan']), ENT_QUOTES, 'UTF-8'); ?>"
                         data-category="<?php echo htmlspecialchars($l['kategori'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="card service-card shadow-sm p-3 text-center h-100" data-id="<?php echo $l['id_layanan']; ?>" onclick="window.addToCart(<?php echo $l['id_layanan']; ?>)" style="cursor: pointer;">
                            <div class="mb-2">
                                <span class="badge bg-light text-primary border border-primary-subtle rounded-pill px-2 py-1 small fw-normal"><?php echo htmlspecialchars($l['kategori']); ?></span>
                            </div>
                            <h5 class="fw-bold text-dark my-3 px-1" style="font-size: 1.15rem; line-height: 1.4; min-height: 2.8rem; display: flex; align-items: center; justify-content: center;" title="<?php echo htmlspecialchars($l['nama_layanan']); ?>">
                                <?php echo htmlspecialchars($l['nama_layanan']); ?>
                            </h5>
                            <span class="badge bg-primary rounded-pill px-3 py-2 mt-auto fs-6">Rp <?php echo number_format($l['harga'], 0, ',', '.'); ?> / <?php echo $l['satuan']; ?></span>
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
                <form id="formCheckout" action="proses_aksi_pos.php" method="POST" onsubmit="window.handleCheckoutSubmit(event); return false;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="outlet_id" value="<?php echo $user_outlet_id; ?>">
                    <input type="hidden" name="items_data" id="itemsDataInput">
                    <input type="hidden" name="total_harga" id="totalHargaInput">
                    <input type="hidden" name="id_pelanggan_lama" id="id_pelanggan_lama" value="">

                    <div class="alert alert-warning py-2 px-3 mb-3 d-flex align-items-start gap-2" style="font-size: 12px; border-left: 4px solid #f59e0b;">
                        <i class="fas fa-bolt text-warning mt-1"></i>
                        <span><strong>Tips Estimasi Akurat:</strong> Jika ada layanan <strong>Express</strong>, pisahkan menjadi transaksi tersendiri agar estimasi selesai di struk sesuai.</span>
                    </div>

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

                    <div class="bg-light p-2 rounded border mb-3 shadow-sm" id="boxPembulatanWrapper" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark"><i class="fas fa-coins text-warning me-1"></i> Pembulatan Cash (500 Terdekat):</span>
                            <span class="badge bg-secondary rounded-pill" id="badgeNominalPembulatan" style="font-size: 0.85rem;">Rp 0</span>
                        </div>
                        <input type="hidden" name="pembulatan" id="input_pembulatan" value="0">
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

<script>
    // --- JAM DIGITAL ---
    function updateClock() {
        var clockEl = document.getElementById('liveClock');
        if (clockEl) {
            clockEl.innerText = new Date().toLocaleTimeString('id-ID', { hour12: false });
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- HITUNG KEMBALIAN & PEMBULATAN ---
    function hitungKembalian() {
        var t = parseFloat(document.getElementById('totalHargaInput').value) || 0;

        var rawDiskon = parseFloat(document.getElementById('input_diskon_raw').value) || 0;
        var tipeDiskon = document.getElementById('tipe_diskon').value;
        var nominalDiskon = 0;

        if (tipeDiskon === 'persen') {
            nominalDiskon = (rawDiskon / 100) * t;
            document.getElementById('label_nominal_diskon').innerText = '- ' + formatRupiah(nominalDiskon);
            document.getElementById('label_nominal_diskon').style.display = 'block';
        } else {
            nominalDiskon = rawDiskon;
            document.getElementById('label_nominal_diskon').style.display = 'none';
        }
        document.getElementById('input_diskon').value = nominalDiskon;

        var preGrandTotal = t - nominalDiskon;
        if (preGrandTotal < 0) preGrandTotal = 0;

        var metodePembayaran = document.getElementById('metode_pembayaran') ? document.getElementById('metode_pembayaran').value : 'Tunai';
        var grandTotal = preGrandTotal;
        var nominalPembulatan = 0;

        if (metodePembayaran === 'Tunai' && preGrandTotal > 0) {
            grandTotal = Math.round(preGrandTotal / 500) * 500;
            nominalPembulatan = grandTotal - preGrandTotal;
        }

        document.getElementById('input_pembulatan').value = nominalPembulatan;

        var boxPembulatan = document.getElementById('boxPembulatanWrapper');
        var badgePembulatan = document.getElementById('badgeNominalPembulatan');

        if (metodePembayaran === 'Tunai' && nominalPembulatan !== 0) {
            if (boxPembulatan) boxPembulatan.style.display = 'block';
            if (badgePembulatan) {
                var sign = nominalPembulatan > 0 ? '+' : '';
                badgePembulatan.className = 'badge rounded-pill ' + (nominalPembulatan < 0 ? 'bg-success text-white' : 'bg-warning text-dark');
                badgePembulatan.innerText = sign + formatRupiah(nominalPembulatan);
            }
        } else {
            if (boxPembulatan) boxPembulatan.style.display = 'none';
        }

        document.getElementById('grandTotalDisplay').innerText = formatRupiah(grandTotal);

        var b = parseFloat(document.getElementById('input_bayar').value) || 0;
        var k = b - grandTotal;
        var el = document.getElementById('input_kembalian');
        if (el) {
            if (k < 0) {
                el.className = 'h5 fw-bold text-danger m-0';
                el.innerText = 'Kurang ' + formatRupiah(Math.abs(k));
            } else {
                el.className = 'h5 fw-bold text-primary m-0';
                el.innerText = formatRupiah(k);
            }
        }
        document.getElementById('kembalian_asli').value = k;
    }

    // --- SUBMIT CHECKOUT ---
    window.handleCheckoutSubmit = function(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }

        if (keranjang.length === 0) {
            Swal.fire('Keranjang Kosong', 'Silakan pilih minimal 1 layanan terlebih dahulu.', 'warning');
            return false;
        }

        var formData = $('#formCheckout').serialize();
        var btn = $('#btnCheckout');
        var originalBtnHtml = btn.html();
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
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.open('../keuangan/cetak_struk.php?id=' + response.id_transaksi, '_blank');
                        }
                        if (response.pelanggan_info) {
                            Swal.fire({ title: 'Info', text: response.pelanggan_info, icon: 'info', timer: 3500, showConfirmButton: false, toast: true, position: 'top-end' });
                        }
                        // Reset POS
                        keranjang = [];
                        window.renderCart();
                        $('#formCheckout')[0].reset();
                        $('#id_pelanggan_lama').val('');
                        $('#input_diskon_raw').val('');
                        $('#input_diskon').val('0');
                        $('#label_nominal_diskon').hide();
                        $('#boxKalkulasi').hide();
                        $('#boxPembulatanWrapper').hide();
                        var kemEl = document.getElementById('input_kembalian');
                        if (kemEl) kemEl.innerText = 'Rp 0';
                        $('#status_pembayaran').removeClass('text-success border-success').addClass('text-danger border-danger');
                    });
                } else {
                    Swal.fire('Gagal!', response.pesan || 'Terjadi kesalahan.', 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error Sistem!', 'Tidak dapat terhubung ke server. (HTTP ' + xhr.status + ')', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalBtnHtml);
            }
        });

        return false;
    };

    // --- DOCUMENT READY ---
    $(document).ready(function() {

        // Submit form checkout via AJAX
        $('#formCheckout').on('submit', function(e) {
            return window.handleCheckoutSubmit(e);
        });

        // Diskon
        $('#input_diskon_raw, #tipe_diskon').on('input change', hitungKembalian);

        // Auto-lookup nama pelanggan dari datalist
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
                if (!$('#inputHpPelanggan').is(':focus')) {
                    $('#inputHpPelanggan').prop('readonly', false).removeClass('bg-light');
                }
            }
        });

        // Auto-lookup dari No HP
        $('#inputHpPelanggan').on('blur', function() {
            var hp = $(this).val().trim();
            if (!hp) return;
            var found = null;
            $('#pelangganOptions option').each(function() {
                if ($(this).data('hp') == hp) { found = $(this); return false; }
            });
            if (found) {
                $('#inputNamaPelanggan').val(found.val());
                $('#id_pelanggan_lama').val(found.data('id'));
                $('#inputHpPelanggan').prop('readonly', true).addClass('bg-light');
                Swal.fire({ title: 'Pelanggan Ditemukan!', text: 'No HP terdaftar atas nama "' + found.val() + '".', icon: 'info', timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
            }
        });

        // Status pembayaran
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

        // Metode pembayaran
        $('#metode_pembayaran').on('change', function() {
            var m = $(this).val();
            var t = parseFloat($('#totalHargaInput').val()) || 0;
            if (m === 'Tunai') {
                $('#input_bayar').prop('readonly', false).val('').focus();
            } else {
                $('#input_bayar').prop('readonly', true).val(t);
            }
            hitungKembalian();
        });

        // Search layanan
        $('#searchService').on('keyup input', function() {
            window.applyServiceFilters();
        });

        // Modal cari order
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

        $('#modalCariOrder').on('shown.bs.modal', function() {
            $('#inputCariOrder').val('');
            window.loadDataNota('');
            $('#inputCariOrder').focus();
        });

        $('#inputCariOrder').on('keyup', function() {
            window.loadDataNota($(this).val());
        });

        // Rekap kasir
        $('#modalRekapKasir').on('shown.bs.modal', function() {
            $('#hasilRekapKasir').html('<div class="text-center my-5"><i class="fas fa-spinner fa-spin fa-3x text-success mb-3"></i><p class="text-muted">Menghitung...</p></div>');
            $.post('rekap_kasir_ajax.php', {
                outlet_id: "<?php echo $user_outlet_id; ?>",
                csrf_token: "<?php echo generateCsrfToken(); ?>"
            }, function(data) {
                $('#hasilRekapKasir').html(data);
            }).fail(function() {
                $('#hasilRekapKasir').html('<div class="alert alert-danger">Gagal memuat rekap.</div>');
            });
        });

    }); // end document.ready

    // --- AKSI ATUR RAK ---
    window.aksiAturRak = function(id, nama) {
        $('#rak_id_transaksi').val(id);
        $('#rak_nama_pelanggan').text(nama);
        $('#formAturRak')[0].reset();
        $('#modalAturRak').modal('show');
    };

    $('#formAturRak').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>');
        $.post('proses_aksi_pos.php', $(this).serialize())
            .done(function(response) {
                $('#modalAturRak').modal('hide');
                if (response.status === 'success') {
                    Swal.fire({ title: 'Berhasil!', text: 'Lokasi rak tersimpan.', icon: 'success', timer: 1500, showConfirmButton: false });
                    window.loadDataNota($('#inputCariOrder').val());
                } else {
                    Swal.fire('Error', response.pesan || 'Gagal.', 'error');
                }
            }).fail(function() {
                Swal.fire('Error', 'Gagal memproses data.', 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalHtml);
            });
    });

    // --- AKSI LUNASI ---
    window.aksiLunasi = function(id, nama, total) {
        Swal.fire({
            title: 'Pelunasan Tagihan',
            html: 'Atas Nama: <b class="text-primary">' + nama + '</b><br>Sisa: <b class="text-danger">' + formatRupiah(total) + '</b><br><small class="text-muted">Masukkan nominal bayar:</small>',
            input: 'number',
            inputValue: total,
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            cancelButtonColor: '#858796',
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> Konfirmasi',
            cancelButtonText: 'Batal'
        }).then(function(r) {
            if (r.isConfirmed) {
                if (parseFloat(r.value) < total) {
                    Swal.fire('Oops!', 'Nominal bayar tidak boleh kurang dari sisa tagihan.', 'warning');
                    return;
                }
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
                $.post('proses_aksi_pos.php', { aksi: 'lunasi', id_transaksi: id, bayar_susulan: r.value, csrf_token: "<?php echo generateCsrfToken(); ?>" })
                    .done(function() {
                        Swal.fire({ title: 'Berhasil!', text: 'Tagihan berhasil dilunasi.', icon: 'success', timer: 1500, showConfirmButton: false });
                        window.loadDataNota($('#inputCariOrder').val());
                    }).fail(function() { Swal.fire('Error', 'Gagal memproses data.', 'error'); });
            }
        });
    };

    // --- AKSI AMBIL ---
    window.aksiAmbil = function(id, nama) {
        Swal.fire({
            title: 'Serahkan Cucian?',
            html: 'Nota atas nama <b class="text-primary">' + nama + '</b> akan ditandai diserahkan.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#4e73df',
            cancelButtonColor: '#858796',
            confirmButtonText: '<i class="fas fa-box-open me-1"></i> Ya, Serahkan',
            cancelButtonText: 'Batal'
        }).then(function(r) {
            if (r.isConfirmed) {
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
                $.post('proses_aksi_pos.php', { aksi: 'ambil', id_transaksi: id, csrf_token: "<?php echo generateCsrfToken(); ?>" })
                    .done(function() {
                        Swal.fire({
                            title: 'Berhasil Diserahkan!',
                            text: 'Cetak struk pengambilan?',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-print"></i> Cetak Struk',
                            cancelButtonText: 'Tutup',
                            confirmButtonColor: '#4e73df'
                        }).then(function(res) {
                            if (res.isConfirmed) {
                                window.open('../keuangan/cetak_struk.php?id=' + id + '&tipe=ambil', '_blank');
                            }
                        });
                        window.loadDataNota($('#inputCariOrder').val());
                    }).fail(function() { Swal.fire('Error', 'Gagal memproses data.', 'error'); });
            }
        });
    };
</script>

<?php include '../../includes/footer.php'; ?>
