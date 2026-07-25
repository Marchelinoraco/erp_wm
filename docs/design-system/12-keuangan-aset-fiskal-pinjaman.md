# Modul: Keuangan — Aset Tetap, Koreksi Fiskal & Hutang/Pinjaman

> Bagian dari sistem ERP Welcome Manado. Rujuk [00-fondasi-desain.md](00-fondasi-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Tiga halaman Keuangan lanjutan, masing-masing dengan model, controller, dan halaman Vue sendiri, tapi cukup ringkas untuk didokumentasikan bersama. Semuanya di bawah prefix `/finance/*` dan dijaga middleware `role:admin,accountant` (grup route yang sama dengan modul Keuangan lain — lihat blok route di `routes/web.php` sekitar baris 227).

- **Aset Tetap** (`/finance/fixed-assets`) — registry aset tetap perusahaan (kendaraan, peralatan, bangunan) + kalkulasi penyusutan komersial **dan** fiskal per aset.
- **Koreksi Fiskal** (`/finance/fiscal`) — halaman rekonsiliasi Laba Komersial → Penghasilan Kena Pajak (PKP) → PPh Badan terutang, ditambah pencatatan koreksi fiskal manual.
- **Hutang & Pinjaman** (`/finance/loans`) — registry pinjaman bank/leasing + satu setting global "Modal Disetor".

Ketiganya bukan sekadar halaman berdiri sendiri — datanya dikonsumsi ulang oleh `FinanceLedgerController` untuk Neraca (`balanceSheet`) dan Laporan Laba Rugi (`incomeStatement`): nilai buku aset tetap masuk ke sisi Aset, saldo outstanding pinjaman masuk ke sisi Kewajiban, Modal Disetor + laba ditahan masuk ke Ekuitas, dan beban penyusutan tahunan mengurangi laba di Laporan Laba Rugi. Baris ini diverifikasi langsung dari `FinanceLedgerController::balanceSheetData`/`incomeStatementData` (±baris 460-655), bukan cuma klaim di teks UI.

## Alur Bisnis

### Aset Tetap

CRUD sederhana (`FixedAssetController`) atas tabel `fixed_assets`: nama, kategori (`vehicle`/`equipment`/`building`/`other` — `FixedAsset::CATEGORIES`), tanggal perolehan, harga perolehan, masa manfaat (tahun), nilai sisa (default 0), catatan bebas, dan status aktif. Aset **tidak bisa dihapus selagi aktif** — `destroy()` menolak dengan HTTP 422 (`abort_if($fixedAsset->is_active, 422, ...)`), harus dinonaktifkan lebih dulu lewat form edit.

Validasi server (`store`/`update`) yang perlu diperhatikan kalau mengembangkan form ini:
- `useful_life_years` wajib 1–50 tahun (integer).
- `acquisition_cost` dan `residual_value` numerik ≥ 0; `residual_value` di-default `0` di controller kalau kosong (`$data['residual_value'] ??= 0`), bukan lewat migration default.
- `fiscal_group` opsional (`nullable`), tapi kalau diisi harus salah satu dari enam key `FixedAsset::FISCAL_GROUPS` — divalidasi dinamis lewat helper `validFiscalGroups()` supaya daftar kelompok tidak perlu disalin ulang ke rule validasi.

Dua jalur penyusutan dihitung paralel di model `FixedAsset`, keduanya metode **garis lurus** dengan **prorata bulan pertama** (bulan perolehan s/d Desember, formula `(13 - bulan) / 12`):

- **Penyusutan komersial** (`annualDepreciation`/`depreciationForYear`/`accumulatedAsOf`/`bookValueAsOf`) — pakai `(harga perolehan − nilai sisa) / masa manfaat`, akumulasi dibatasi maksimum sebesar basis penyusutan (`min(accumulated, cost - residual)`).
- **Penyusutan fiskal** (`fiscalDepreciationForYear`) — hanya berjalan kalau aset punya `fiscal_group` terisi (kolom ditambahkan lewat migration terpisah, nullable). Basisnya **harga perolehan penuh tanpa nilai sisa** dibagi jumlah tahun kelompok fiskal, sesuai enam kelompok PMK 96/2009 yang didefinisikan di `FixedAsset::FISCAL_GROUPS`: Kelompok 1 (4 th/25%), Kelompok 2 (8 th/12,5%), Kelompok 3 (16 th/6,25%), Kelompok 4 (20 th/5%), Bangunan Permanen (20 th/5%), Bangunan Tidak Permanen (10 th/10%).

Kedua angka ini (komersial vs fiskal) yang jadi dasar tabel "selisih penyusutan" di halaman Koreksi Fiskal.

`FixedAssetController::index` selalu menghitung untuk `now()->year` (tahun berjalan) — total kartu ringkasan (harga perolehan, akumulasi, nilai buku) hanya menjumlah aset yang `is_active`.

### Koreksi Fiskal (PPh Badan)

Tujuan bisnisnya: menjembatani **laba akuntansi (komersial)** ke **Penghasilan Kena Pajak (PKP)** yang jadi dasar PPh Badan, sesuai prinsip koreksi fiskal standar (biaya non-deductible / penghasilan yang diperlakukan beda antara akuntansi dan pajak). Semua dihitung ulang tiap request oleh `FiscalController::fiscalData` (tidak ada tabel snapshot laba — hanya `fiscal_corrections` yang tersimpan permanen), untuk `year` dan `regime` (`badan_22` atau `pp23`) yang dipilih lewat dropdown:

1. **Laba Komersial** — dihitung dari data lain yang sudah ada di sistem, bukan input manual: `Invoice::whereYear('date', $year)->sum('total')` (pendapatan) dikurangi `Bill::whereYear('date', $year)->sum('amount')` (HPP/COGS) = Laba Kotor; dikurangi total `FinTransaction` manual `direction=out` (biaya operasional) dan penyusutan komersial (`depreciationForYear` semua aset aktif); ditambah `FinTransaction` manual `direction=in` (pendapatan lain-lain). Filter `source='manual'` ini penting: `fin_transactions` juga berisi baris yang tergenerate otomatis dari pembayaran invoice/bill (lihat modul kas/`08-rekening`) — kalau ikut dijumlah di sini, pendapatan/HPP akan double-count karena sudah dihitung lewat `Invoice`/`Bill` di langkah yang sama.
2. **Selisih penyusutan** — total penyusutan komersial dikurangi total penyusutan fiskal (`selisihDep`). Selisih positif (komersial > fiskal) otomatis masuk sebagai komponen **koreksi positif**; selisih negatif masuk sebagai **koreksi negatif** — ini digabung dengan koreksi manual di langkah berikutnya. Aset yang belum punya `fiscal_group` diberi peringatan `⚠ Belum diset` di tabel dan banner merah yang menaut balik ke halaman Aset Tetap.
3. **Koreksi fiskal manual** (`fiscal_corrections` table, CRUD lewat `FiscalController::store/update/destroy`) — tiap baris punya `year`, `name` (deskripsi bebas, mis. "Biaya Entertainment tanpa Daftar Nominatif"), `type` (`positive`/`negative`), `amount`, `notes` opsional, dan `sort_order` (auto-increment per tahun). Dikelompokkan tampil terpisah: Koreksi Positif (menambah PKP) vs Koreksi Negatif (mengurangi PKP).
4. **PKP** = `max(0, laba komersial + total koreksi positif − total koreksi negatif)`, dibulatkan ke satuan rupiah penuh.
5. **PPh Terutang** — tergantung `regime` yang dipilih: rezim `badan_22` mengenakan **22%** dari PKP; rezim `pp23` (untuk WP omzet ≤ Rp 4,8 M/tahun, sesuai catatan di UI) mengenakan **0,5%** langsung dari total omzet (`totalRevenue`), bukan dari PKP.

Halaman juga punya tombol unduh PDF (`FiscalController::pdf`, template blade `resources/views/finance/fiscal_correction.blade.php` lewat helper `Pdf`) yang me-render data perhitungan yang sama — bukan komponen Vue terpisah, satu fungsi privat `fiscalData()` dipakai bersama oleh `index()` (Inertia) dan `pdf()` (mPDF) supaya angka di layar dan di PDF selalu konsisten.

Dropdown "Tahun" di header dibatasi oleh `availableYears()` — rentang dari tahun `Invoice` tertua di database sampai tahun berjalan (`now()->year`), bukan daftar tahun bebas.

### Hutang & Pinjaman

Registry pinjaman (`loans` table) — CRUD murni lewat `LoanController`, tanpa jadwal amortisasi otomatis: `name`, `lender` (kreditur, opsional), `loan_type` (`bank_loan`/`leasing`/`other` — `Loan::TYPES`), `original_amount` (pokok awal), `start_date`, `tenor_months`, `monthly_installment`, dan **`outstanding_balance`** — field kunci yang **diisi/diperbarui manual** oleh user sesuai tagihan kreditur (bukan dihitung dari cicilan × waktu berjalan; komentar migration eksplisit menyebut "saldo pokok terkini (manual)"). UI menghitung `% terlunasi` di sisi klien murni dari `(original_amount - outstanding_balance) / original_amount` untuk progress bar, tidak disimpan sebagai kolom.

Validasi server membatasi `tenor_months` 1–600 bulan dan `original_amount` minimal 1 (harus > 0), sementara `monthly_installment`/`outstanding_balance` boleh 0. Field `is_active` hanya divalidasi di `update` (tidak ada di `store` — pinjaman baru selalu dibuat aktif lewat default kolom migration).

Berdampingan dengan pinjaman ada satu setting global **Modal Disetor** (`finance_settings` table, key-value generik lewat model `FinanceSetting` — hanya satu baris `modal_disetor` yang dipakai saat ini) yang diedit lewat `LoanController::updateSetting` (route `PATCH /finance/settings`). Ini dipakai sebagai komponen Ekuitas awal di Neraca, digabung dengan laba ditahan akrual.

Daftar pinjaman dikelompokkan per `loan_type` di UI, masing-masing grup punya subtotal outstanding di footer. Pinjaman non-aktif tetap tersimpan (soft-hide lewat `is_active`, bukan dihapus) supaya riwayat tidak hilang, tapi dikecualikan dari kartu ringkasan total dan dari Neraca.

## Model Data

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `fixed_assets` | `category`, `acquisition_date`, `acquisition_cost`, `useful_life_years`, `residual_value`, `fiscal_group` (nullable, ditambah migration terpisah), `is_active` | Tidak ada relasi Eloquent eksplisit ke tabel lain; dipakai lintas-controller (Fiscal, Ledger) lewat query langsung |
| `fiscal_corrections` | `year`, `name`, `type` (positive/negative), `amount`, `notes`, `sort_order` | Index `(year, type)`; satu-satunya data yang benar-benar disimpan dari ketiga fitur — sisanya turunan/kalkulasi |
| `loans` | `loan_type`, `original_amount`, `tenor_months`, `monthly_installment`, `outstanding_balance` (manual), `is_active` | Tidak ada tabel cicilan/pembayaran terpisah — hanya saldo terkini |
| `finance_settings` | `key` (primary, string), `value`, `label`, `notes` | Key-value generik; baru satu key terpakai (`modal_disetor`), di-seed nilai 0 saat migration |

Semua tiga model (`FixedAsset`, `FiscalCorrection`, `Loan`) pakai `protected $guarded = []` (mass-assignment bebas, divalidasi di level controller lewat `$request->validate`) — pola yang konsisten dengan model Finance lain di app ini.

## Route & Controller

| Route | Controller@method | Catatan |
|---|---|---|
| `finance.fixed-assets` (GET `/finance/fixed-assets`) | `FixedAssetController@index` | Hitung penyusutan tahun berjalan untuk semua aset |
| `fixed-assets.store` / `.update` / `.destroy` | `FixedAssetController@store/update/destroy` | `destroy` menolak kalau `is_active` |
| `finance.fiscal` (GET `/finance/fiscal`) | `FiscalController@index` | Query param `year`, `regime` |
| `finance.fiscal.pdf` (GET `/finance/fiscal/pdf`) | `FiscalController@pdf` | Data sama persis dengan `index`, dirender ke PDF |
| `fiscal.corrections.store` / `.update` / `.destroy` | `FiscalController@store/update/destroy` | CRUD `fiscal_corrections` |
| `finance.loans` (GET `/finance/loans`) | `LoanController@index` | |
| `loans.store` / `.update` / `.destroy` | `LoanController@store/update/destroy` | |
| `finance.settings.update` (PATCH `/finance/settings`) | `LoanController@updateSetting` | Update-or-create `modal_disetor` |

Semua route di atas berada dalam grup `Route::middleware('role:admin,accountant')` bersama route Keuangan lain (`routes/web.php` baris ±227-257) — tidak ada pengecekan otorisasi tambahan di level controller.

## Halaman & Komponen (UI)

- **`Finance/FixedAssets.vue`** — kartu ringkasan 3-kolom (harga perolehan, akumulasi penyusutan, nilai buku bersih), form tambah yang bisa di-toggle, tabel dengan **baris edit inline** (klik "Edit" mengubah baris tabel jadi form, bukan modal dialog — pola berbeda dari dialog shadcn yang dipakai modul lain), badge warna per kategori (biru=kendaraan, ungu=peralatan, amber=bangunan, abu=lainnya), dan panel catatan statis di bawah menjelaskan metode penyusutan.
- **`Finance/FiscalCorrection.vue`** — lima kartu berurutan: Laba Rugi Komersial → tabel perbandingan penyusutan komersial vs fiskal (dengan peringatan aset tanpa kelompok fiskal) → daftar koreksi manual (dipisah Positif/Negatif, masing-masing dengan tambah/edit-inline/hapus) → ringkasan rekonsiliasi PKP → kartu PPh Badan Terutang (warna berubah amber kalau ada pajak terutang). Dropdown tahun & rezim pajak di header memicu navigasi ulang (`router.get` dengan `preserveState: false`) karena semua angka dihitung server-side.
- **`Finance/Loans.vue`** — kartu Modal Disetor (edit inline angka tunggal) di atas, tiga kartu ringkasan (pokok awal, outstanding, cicilan/bulan), form tambah, lalu daftar pinjaman **dikelompokkan per jenis** dengan progress bar pelunasan per baris dan subtotal per grup. Baris non-aktif ditandai badge abu "Non-aktif" tapi tetap tampil (tidak disembunyikan).

Ketiga halaman mengikuti pola layout & kartu standar di [00-fondasi-desain.md](00-fondasi-desain.md) (kartu `bg-white rounded-xl border shadow-sm`, `fmtRp` untuk uang) — tidak ada pola UI baru yang perlu didokumentasikan terpisah, kecuali kebiasaan **edit inline di dalam baris tabel** (bukan dialog modal) yang konsisten dipakai di ketiga halaman ini.

## Yang Perlu Diperhatikan

- **Penyusutan & koreksi fiskal tidak pernah disimpan sebagai snapshot** — semuanya dihitung ulang tiap request dari `acquisition_cost`/`useful_life_years`/`fiscal_group` aset yang aktif saat itu. Kalau data aset diedit di kemudian hari (mis. `fiscal_group` diganti), angka tahun-tahun lalu di halaman Koreksi Fiskal ikut berubah — tidak ada pembekuan historis seperti pola snapshot di modul Penjualan (`tour_items`/`invoice_items`).
- **`outstanding_balance` pinjaman murni input manual** — sistem tidak menghitung sisa pokok dari `original_amount`, `tenor_months`, dan waktu berjalan; kalau lupa diperbarui, Neraca (Kewajiban) dan progress bar pelunasan akan salah tanpa ada peringatan otomatis.
- **Aset tanpa `fiscal_group`** akan dianggap penyusutan fiskalnya = 0 (bukan error), yang otomatis membesarkan `selisihDep` → menaikkan koreksi fiskal positif → menaikkan PKP. UI sudah memberi peringatan visual, tapi tidak ada validasi yang memaksa pengisian `fiscal_group` saat aset dibuat.
- **PPh Badan hasil kalkulasi eksplisit disclaim sebagai estimasi** — teks di halaman Koreksi Fiskal sendiri menyarankan konsultasi ke konsultan pajak sebelum dipakai untuk SPT; rezim PP 23 (0,5%) dan tarif 22% adalah dua opsi tetap yang dipilih manual, sistem tidak mendeteksi otomatis mana yang berlaku untuk perusahaan.
- **Tidak ada halaman/route terpisah untuk melihat riwayat perubahan** pada `fixed_assets`, `loans`, atau `fiscal_corrections` — update langsung menimpa baris, tanpa log/audit trail seperti `tour_histories` di modul Penjualan.
