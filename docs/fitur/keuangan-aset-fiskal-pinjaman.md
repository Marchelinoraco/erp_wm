# Keuangan — Aset Tetap, Fiskal & Pinjaman

> **Status:** ✅ Berjalan · **Peran:** admin, accountant · **Sejak:** Jun 2026
> **Terkait:** [keuangan-pembukuan.md](keuangan-pembukuan.md), [keuangan-ar-ap.md](keuangan-ar-ap.md)

## 1. Ringkasan

Tiga halaman Keuangan lanjutan — Aset Tetap (registry + penyusutan garis lurus komersial & fiskal), Koreksi Fiskal (rekonsiliasi laba komersial → PKP → PPh Badan terutang), dan Hutang & Pinjaman (registry pinjaman + setting Modal Disetor) — yang datanya dikonsumsi ulang oleh Neraca dan Laporan Laba Rugi di modul Pembukuan. Dipakai oleh admin & accountant.

## 2. Cara kerja (as-built)

### Aset Tetap (`FixedAssetController`, tabel `fixed_assets`)
CRUD: nama, kategori (`vehicle`/`equipment`/`building`/`other`), tanggal & harga perolehan, masa manfaat (1–50 tahun), nilai sisa (default 0 kalau kosong, di-set di controller bukan migration), `fiscal_group` opsional (harus salah satu dari enam key `FixedAsset::FISCAL_GROUPS` bila diisi), catatan, status aktif. Aset **tidak bisa dihapus selagi aktif** (`destroy()` menolak 422) — harus dinonaktifkan lebih dulu.

Dua jalur penyusutan garis lurus dengan prorata bulan pertama (`(13 − bulan perolehan) / 12`), dihitung di model `FixedAsset`:
- **Komersial** (`annualDepreciation`/`depreciationForYear`/`accumulatedAsOf`/`bookValueAsOf`) — `(harga perolehan − nilai sisa) / masa manfaat`, akumulasi dibatasi maksimum sebesar basis penyusutan.
- **Fiskal** (`fiscalDepreciationForYear`) — hanya berjalan kalau `fiscal_group` terisi. Basis **harga perolehan penuh tanpa nilai sisa** dibagi jumlah tahun kelompok, sesuai enam kelompok PMK 96/2009: Kelompok 1 (4 th/25%), Kelompok 2 (8 th/12,5%), Kelompok 3 (16 th/6,25%), Kelompok 4 (20 th/5%), Bangunan Permanen (20 th/5%), Bangunan Tidak Permanen (10 th/10%).

`FixedAssetController::index` selalu menghitung untuk `now()->year`; kartu ringkasan (harga perolehan, akumulasi, nilai buku) hanya menjumlah aset `is_active`.

### Koreksi Fiskal (`FiscalController`)
Menjembatani laba akuntansi (komersial) ke Penghasilan Kena Pajak (PKP) yang jadi dasar PPh Badan. Semua dihitung ulang tiap request oleh `fiscalData()` (privat, dipakai bersama oleh `index()` dan `pdf()` supaya angka layar & PDF konsisten) untuk `year` dan `regime` (`badan_22` atau `pp23`) yang dipilih:

1. **Laba Komersial** — `Invoice::whereYear('date', $year)->sum('total')` (pendapatan) dikurangi `Bill::whereYear('date', $year)->sum('amount')` (HPP) = Laba Kotor; dikurangi total `FinTransaction` `source=manual` arah `out` (opex) dan penyusutan komersial semua aset aktif; ditambah `FinTransaction` manual arah `in` (pendapatan lain-lain). Filter `source='manual'` penting — `fin_transactions` juga berisi baris otomatis dari pembayaran invoice/bill, kalau ikut dijumlah akan double-count karena pendapatan/HPP sudah dihitung lewat `Invoice`/`Bill` di langkah yang sama.
2. **Selisih penyusutan** — total penyusutan komersial dikurangi total fiskal (`selisihDep`). Selisih positif otomatis jadi komponen **koreksi positif**; selisih negatif jadi **koreksi negatif**. Aset tanpa `fiscal_group` diberi peringatan "⚠ Belum diset" di UI.
3. **Koreksi fiskal manual** (`fiscal_corrections`, CRUD lewat `store/update/destroy`) — tiap baris: `year`, `name` (deskripsi bebas), `type` (`positive`/`negative`), `amount`, `notes` opsional, `sort_order`. Tampil terpisah Positif vs Negatif.
4. **PKP** = `max(0, laba komersial + total koreksi positif − total koreksi negatif)`, dibulatkan ke rupiah penuh.
5. **PPh Terutang** — tergantung `regime`: `badan_22` mengenakan **22%** dari PKP; `pp23` (WP omzet ≤ Rp 4,8 M/tahun) mengenakan **0,5%** langsung dari total omzet (`totalRevenue`), bukan dari PKP.

Dropdown "Tahun" dibatasi `availableYears()` — dari tahun `Invoice` tertua sampai tahun berjalan.

### Hutang & Pinjaman (`LoanController`, tabel `loans`)
CRUD murni, tanpa jadwal amortisasi otomatis: `name`, `lender` opsional, `loan_type` (`bank_loan`/`leasing`/`other`), `original_amount`, `start_date`, `tenor_months` (1–600), `monthly_installment`, dan **`outstanding_balance`** — diisi/diperbarui **manual** sesuai tagihan kreditur, bukan dihitung dari cicilan × waktu berjalan. UI menghitung `% terlunasi` murni di klien dari `(original_amount − outstanding_balance) / original_amount`, tidak disimpan sebagai kolom. `is_active` hanya divalidasi di `update` — pinjaman baru selalu aktif lewat default kolom migration.

Setting global **Modal Disetor** (`finance_settings`, key-value generik lewat `FinanceSetting`, hanya key `modal_disetor` yang dipakai) diedit lewat `LoanController::updateSetting` — komponen Ekuitas awal di Neraca.

Pinjaman dikelompokkan per `loan_type` di UI dengan subtotal per grup. Pinjaman non-aktif tetap tersimpan (soft-hide via `is_active`) tapi dikecualikan dari kartu ringkasan & Neraca.

### Konsumsi lintas modul
`FinanceReportController::balanceSheetData()`/`incomeStatementData()` (lihat [keuangan-pembukuan.md](keuangan-pembukuan.md)) memakai `FixedAsset::accumulatedAsOf()`/`bookValueAsOf()`/`depreciationForYear()` dan `Loan::outstanding_balance` serta `FinanceSetting::get('modal_disetor')` langsung lewat query — tidak ada relasi Eloquent eksplisit antar model-model ini.

## 3. Keterkaitan

- **Keuangan — Pembukuan** ([keuangan-pembukuan.md](keuangan-pembukuan.md)) — Neraca (nilai buku Aset Tetap ke sisi Aset, saldo pinjaman ke sisi Kewajiban, Modal Disetor+laba ditahan ke Ekuitas) dan Laba Rugi (beban penyusutan tahunan) mengonsumsi data ketiga fitur ini.
- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — Laba Komersial di Koreksi Fiskal dihitung dari `Invoice.total` & `Bill.amount`, sumber data yang sama dengan AR/AP.

## 4. Batasan & jebakan ⚠️

- **Penyusutan & koreksi fiskal tidak pernah disimpan sebagai snapshot** — dihitung ulang tiap request dari `acquisition_cost`/`useful_life_years`/`fiscal_group` aset **yang aktif saat itu**. Mengedit data aset di kemudian hari (mis. ganti `fiscal_group`) mengubah angka tahun-tahun lalu di Koreksi Fiskal juga — tidak ada pembekuan historis seperti pola snapshot harga di modul Penjualan, lihat [ikhtisar-proyek.md §3.1](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).
- **`outstanding_balance` pinjaman murni input manual** — sistem tidak menghitung sisa pokok dari `original_amount`/`tenor_months`/waktu berjalan; kalau lupa diperbarui, Neraca (Kewajiban) dan progress bar pelunasan akan salah tanpa peringatan otomatis.
- **Aset tanpa `fiscal_group`** dianggap penyusutan fiskalnya = 0 (bukan error), otomatis membesarkan `selisihDep` → menaikkan koreksi fiskal positif → menaikkan PKP. UI memberi peringatan visual, tapi tidak ada validasi yang memaksa pengisian saat aset dibuat.
- **PPh Badan hasil kalkulasi eksplisit disclaim sebagai estimasi** di halaman Koreksi Fiskal — perlu konsultasi konsultan pajak sebelum dipakai untuk SPT; rezim PP 23 (0,5%) dan tarif 22% adalah dua opsi tetap yang dipilih manual, sistem tidak mendeteksi otomatis mana yang berlaku.
- Tidak ada halaman/route riwayat perubahan pada `fixed_assets`, `loans`, atau `fiscal_corrections` — update menimpa baris langsung, tanpa log/audit trail.

## 5. Status & yang belum

Berjalan penuh di production sejak akhir Juni 2026.

## 6. Dokumen terkait

- [keuangan-pembukuan.md](keuangan-pembukuan.md) — Neraca & Laba Rugi yang mengonsumsi data ketiga fitur ini
- [keuangan-ar-ap.md](keuangan-ar-ap.md) — sumber Invoice/Bill untuk Laba Komersial
- [ikhtisar-proyek.md §3 & §6](../ikhtisar-proyek.md) — invarian lintas-fitur & rumus keuangan
- [referensi/pola-ui-desain.md](../referensi/pola-ui-desain.md) — pola UI/warna bersama (kartu, edit inline dalam baris tabel)
