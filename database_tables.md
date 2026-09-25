# Struktur Tabel Database — Mending Laundry

---

## Tabel 4.1 Tabel `roles`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_role | INT | - | Primary Key, Auto Increment |
| 2 | nama_role | VARCHAR | 50 | Nama level akses |

---

## Tabel 4.2 Tabel `users`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_user | INT | - | Primary Key, Auto Increment |
| 2 | username | VARCHAR | 100 | Username login, Unique |
| 3 | password | VARCHAR | 255 | Password terenkripsi (bcrypt) |
| 4 | pin | VARCHAR | 10 | PIN 6 digit otorisasi kasir |
| 5 | is_active | TINYINT | 1 | Status akun (1=Aktif, 0=Nonaktif) |
| 6 | id_role | INT | - | Foreign Key → roles(id_role) |

---

## Tabel 4.3 Tabel `outlets`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_outlet | INT | - | Primary Key, Auto Increment |
| 2 | nama_outlet | VARCHAR | 100 | Nama cabang outlet |
| 3 | alamat | TEXT | - | Alamat lengkap outlet |
| 4 | no_telp | VARCHAR | 20 | Nomor telepon outlet |
| 5 | latitude | DOUBLE | - | Koordinat GPS lintang |
| 6 | longitude | DOUBLE | - | Koordinat GPS bujur |
| 7 | radius_meter | INT | - | Radius toleransi absensi (meter) |

---

## Tabel 4.4 Tabel `karyawan`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_karyawan | INT | - | Primary Key, Auto Increment |
| 2 | id_user | INT | - | Foreign Key → users(id_user), Unique |
| 3 | id_outlet | INT | - | Foreign Key → outlets(id_outlet) |
| 4 | nama_lengkap | VARCHAR | 150 | Nama lengkap karyawan |
| 5 | no_hp | VARCHAR | 20 | Nomor HP karyawan |
| 6 | jenis_kelamin | VARCHAR | 15 | Jenis kelamin karyawan |
| 7 | alamat | TEXT | - | Alamat tempat tinggal |
| 8 | tanggal_bergabung | DATE | - | Tanggal mulai bekerja |
| 9 | nik_ktp | VARCHAR | 30 | Nomor Induk Kependudukan |
| 10 | nama_bank | VARCHAR | 50 | Nama bank untuk transfer gaji |
| 11 | no_rekening | VARCHAR | 30 | Nomor rekening bank |
| 12 | foto_referensi | VARCHAR | 255 | Path foto referensi wajah |
| 13 | face_descriptor | LONGTEXT | - | Data vektor wajah (face recognition) |

---

## Tabel 4.5 Tabel `pelanggan`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_pelanggan | INT | - | Primary Key, Auto Increment |
| 2 | nama_pelanggan | VARCHAR | 150 | Nama lengkap pelanggan |
| 3 | no_hp | VARCHAR | 20 | Nomor HP pelanggan, Unique |

---

## Tabel 4.6 Tabel `layanan`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_layanan | INT | - | Primary Key, Auto Increment |
| 2 | id_outlet | INT | - | Foreign Key → outlets(id_outlet) |
| 3 | nama_layanan | VARCHAR | 150 | Nama layanan |
| 4 | kategori | VARCHAR | 100 | Kategori layanan |
| 5 | satuan | VARCHAR | 50 | Satuan layanan (kg, pcs, pasang) |
| 6 | harga | DECIMAL | 12,2 | Harga per satuan |
| 7 | estimasi_jam | INT | - | Estimasi waktu selesai (jam) |
| 8 | status | ENUM | - | Status layanan (Aktif/Nonaktif) |

---

## Tabel 4.7 Tabel `transaksi`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_transaksi | INT | - | Primary Key, Auto Increment |
| 2 | no_invoice | VARCHAR | 30 | Nomor nota unik, Unique |
| 3 | id_outlet | INT | - | Foreign Key → outlets(id_outlet) |
| 4 | id_pelanggan | INT | - | Foreign Key → pelanggan(id_pelanggan) |
| 5 | id_user | INT | - | Foreign Key → users(id_user) |
| 6 | tgl_masuk | DATETIME | - | Tanggal dan jam cucian masuk |
| 7 | estimasi_selesai | DATETIME | - | Estimasi waktu cucian selesai |
| 8 | total_harga | DECIMAL | 12,2 | Total harga sebelum diskon |
| 9 | diskon | DECIMAL | 12,2 | Nilai diskon |
| 10 | pembulatan | DECIMAL | 12,2 | Nilai pembulatan harga |
| 11 | grand_total | DECIMAL | 12,2 | Total akhir yang harus dibayar |
| 12 | bayar | DECIMAL | 12,2 | Jumlah uang yang dibayarkan |
| 13 | kembalian | DECIMAL | 12,2 | Uang kembalian pelanggan |
| 14 | metode_pembayaran | VARCHAR | 30 | Metode bayar (Tunai/Transfer/QRIS) |
| 15 | lokasi_rak | VARCHAR | 50 | Kode rak penyimpanan cucian |
| 16 | catatan | TEXT | - | Catatan tambahan transaksi |
| 17 | status_pembayaran | ENUM | - | Status bayar (Lunas/Belum Lunas) |
| 18 | status_laundry | ENUM | - | Status cucian (Proses/Selesai/Diambil) |

---

## Tabel 4.8 Tabel `transaksi_detail`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_detail | INT | - | Primary Key, Auto Increment |
| 2 | id_transaksi | INT | - | Foreign Key → transaksi(id_transaksi) |
| 3 | id_layanan | INT | - | Foreign Key → layanan(id_layanan) |
| 4 | qty | INT | - | Jumlah/kuantitas layanan |
| 5 | harga | DECIMAL | 12,2 | Harga satuan saat transaksi |
| 6 | subtotal | DECIMAL | 12,2 | Subtotal (qty × harga) |

---

## Tabel 4.9 Tabel `absensi`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_absensi | INT | - | Primary Key, Auto Increment |
| 2 | id_user | INT | - | Foreign Key → users(id_user) |
| 3 | tanggal | DATE | - | Tanggal absensi |
| 4 | waktu_masuk | DATETIME | - | Waktu check-in karyawan |
| 5 | waktu_pulang | DATETIME | - | Waktu check-out karyawan |
| 6 | foto_masuk | VARCHAR | 255 | Path foto selfie saat masuk |
| 7 | foto_pulang | VARCHAR | 255 | Path foto selfie saat pulang |
| 8 | lat_masuk | VARCHAR | 30 | Koordinat GPS lintang saat masuk |
| 9 | long_masuk | VARCHAR | 30 | Koordinat GPS bujur saat masuk |
| 10 | lat_pulang | VARCHAR | 30 | Koordinat GPS lintang saat pulang |
| 11 | long_pulang | VARCHAR | 30 | Koordinat GPS bujur saat pulang |
| 12 | status_verifikasi | ENUM | - | Status verifikasi (valid/invalid/pending) |

---

## Tabel 4.10 Tabel `penggajian`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_penggajian | INT | - | Primary Key, Auto Increment |
| 2 | id_user | INT | - | Foreign Key → users(id_user) |
| 3 | bulan | VARCHAR | 7 | Periode gaji format YYYY-MM |
| 4 | gaji_pokok | DECIMAL | 12,2 | Gaji pokok bulanan |
| 5 | bonus | DECIMAL | 12,2 | Total bonus karyawan |
| 6 | potongan | DECIMAL | 12,2 | Total potongan karyawan |
| 7 | total_gaji | DECIMAL | 12,2 | Total gaji bersih yang diterima |
| 8 | catatan | TEXT | - | Catatan penggajian |

---

## Tabel 4.11 Tabel `kategori_pengeluaran`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_kategori | INT | - | Primary Key, Auto Increment |
| 2 | nama_kategori | VARCHAR | 100 | Nama kategori pengeluaran |

---

## Tabel 4.12 Tabel `pengeluaran`

| No | Nama Field | Tipe Data | Panjang | Keterangan |
|----|-----------|-----------|---------|-----------|
| 1 | id_pengeluaran | INT | - | Primary Key, Auto Increment |
| 2 | id_outlet | INT | - | Foreign Key → outlets(id_outlet) |
| 3 | id_user | INT | - | Foreign Key → users(id_user) |
| 4 | id_kategori | INT | - | Foreign Key → kategori_pengeluaran(id_kategori) |
| 5 | nominal | DECIMAL | 12,2 | Jumlah biaya pengeluaran |
| 6 | keterangan | TEXT | - | Deskripsi pengeluaran |
| 7 | tanggal | DATE | - | Tanggal pengeluaran |
