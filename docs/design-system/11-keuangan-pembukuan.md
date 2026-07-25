# Modul: Keuangan — Pembukuan & Laporan

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama, dan [10-keuangan-ar-ap.md](10-keuangan-ar-ap.md) untuk pencatatan pembayaran invoice/bill yang mendasari sebagian angka di laporan ini. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Modul ini adalah **lapisan pembukuan** di atas AR (invoice) dan AP (bill) — satu tabel `fin_transactions` menjadi **sumber tunggal** untuk Arus Kas, Jurnal, Buku Besar, dan Rekap. Isinya berasal dari dua jalur:
1. **Manual** — akuntan mencatat langsung lewat halaman Transaksi (pendapatan/pengeluaran di luar invoice/bill, mis. gaji, sewa, pendapatan lain-lain).
2. **Otomatis** — setiap pembayaran invoice (`invoice_payments`) atau pembayaran bill (`bill_payments`) di modul AR/AP disalin jadi satu baris `fin_transactions` lewat `App\Support\LedgerSync`, dipicu Eloquent Observer (`InvoicePaymentObserver`, `BillPaymentObserver`) — bukan dipanggil manual dari controller AR/AP.

Selain itu ada dua laporan **posisi keuangan** (Neraca, Laba Rugi) yang tidak murni dari `fin_transactions` — keduanya menggabungkan data `Invoice`/`Bill` (akrual, per tanggal dokumen) dengan `fin_transactions` (kas manual) dan data Aset Tetap/Pinjaman dari modul lain. Semuanya dilayani **satu controller**, `FinanceLedgerController`, dan sekelompok halaman `resources/js/Pages/Finance/*.vue`. Seluruh route ada di grup middleware `role:admin,accountant`.

## Alur Bisnis

### Kategori pembukuan (`fin_categories`)
Pos pendapatan/pengeluaran bebas, tiap kategori bertipe `income` atau `expense`. Dua kategori **bawaan sistem** (`is_system = true`) dibuat saat migrasi: **"Penjualan Tour"** (income) dan **"Biaya Supplier"** (expense) — inilah kategori yang dipakai `LedgerSync` untuk memposting pembayaran invoice/bill secara otomatis (dicari lewat `FinCategory::where('name', ...)->where('is_system', true)`, bukan lewat ID tetap). Kategori bawaan tidak bisa **dihapus** (`destroyCategory` menolak dengan 403), dan kategori apa pun tidak bisa dihapus kalau masih dipakai transaksi (422). Saat mencatat transaksi manual, kategori yang dipilih harus selaras arahnya (`in` → kategori `income`, `out` → kategori `expense`), divalidasi di `validateTransaction()`.

### Akun Kas (`cash_accounts`) — bukan Rekening (modul 08)
`cash_accounts` (Kas/Bank, tiap baris punya `opening_balance`) adalah akun **internal pembukuan** — dipilih akuntan sebagai sisi debit/kredit tiap transaksi/pembayaran, dan menjadi basis semua saldo di laporan modul ini. Ini **tabel berbeda** dari `bank_accounts` yang dibahas di [08-rekening.md](08-rekening.md) (rekening yang tampil di PDF invoice untuk customer transfer) — dua konsep yang mudah tertukar namanya tapi independen. Sejak migrasi `2026_07_15_000000_add_cash_account_id_to_payments`, `invoice_payments`/`bill_payments` punya kolom `cash_account_id` sendiri (dipilih eksplisit saat mencatat pembayaran di AR/AP), menggantikan tebakan lama `LedgerSync::accountForMethod()` yang cuma menebak dari teks metode pembayaran (mengandung "cash"/"tunai" → akun kas pertama, selain itu → akun bank pertama; masih dipakai sebagai fallback untuk baris lama yang belum punya `cash_account_id`). Akun kas tidak bisa dihapus kalau masih dipakai transaksi.

### Transaksi manual
Halaman **Transaksi** (`Finance/Transactions.vue`) — catat pendapatan/pengeluaran per bulan, dengan kartu ringkas (pemasukan/pengeluaran/selisih bulan berjalan) dan panel "Kelola Kategori & Akun Kas" (CRUD ringan langsung di halaman yang sama, bukan halaman terpisah). Hanya transaksi ber-`source = manual` yang bisa diedit/dihapus dari sini — transaksi hasil sinkronisasi AR/AP (`source = invoice`/`bill`) dikunci (`abort_unless`), harus diubah lewat pembayaran aslinya di modul AR/AP.

### Arus Kas (`cashFlow`)
Grafik tahunan: pemasukan vs pengeluaran per bulan, tren **saldo berjalan kumulatif** (dimulai dari `balanceBefore()` — total `opening_balance` semua akun kas + akumulasi transaksi sebelum 1 Januari tahun terpilih), breakdown pengeluaran per kategori (donut), dan saldo terkini per akun kas (`opening_balance + Σin − Σout`, tanpa filter tanggal — selalu saldo "sekarang").

### Jurnal (`journal`)
Menampilkan tiap `fin_transactions` bulan terpilih sebagai baris **debit/kredit** lewat `FinTransaction::journalLines()` — pola tetap: transaksi `in` → Debit Akun Kas, Kredit Kategori; transaksi `out` → Debit Kategori, Kredit Akun Kas. `ref` baris menunjukkan asal (`Manual`/`AR`/`AP` dari kolom `source`). Total debit dan kredit selalu dijumlah dari kolom `amount` yang sama (`$total = $txns->sum('amount')`), jadi keduanya **selalu identik secara matematis** — lihat catatan di §Yang Perlu Diperhatikan.

### Buku Besar (`ledger`)
Mengelompokkan ulang seluruh `fin_transactions` (bisa difilter per bulan atau setahun penuh) jadi akun-akun: tiap akun kas jadi satu akun (grup `aset`), tiap kategori jadi satu akun (grup `pendapatan` atau `beban`). Saldo dihitung sesuai saldo normal akuntansi (`aset`/`beban` normal-debit: `debit − kredit`; `pendapatan` normal-kredit: `kredit − debit`). Total grup `pendapatan` dikurangi grup `beban` ditampilkan sebagai **"Laba (Rugi) Akuntansi"** — ini murni cash-basis dari `fin_transactions` pada periode terpilih, **berbeda konsep** dari "Laba Bersih" di Laporan Laba Rugi (lihat catatan di bawah). Tiap akun bisa di-*expand* untuk lihat detail posting per tanggal.

### Rekap (`recap`)
Ringkasan pemasukan/pengeluaran/net dalam dua mode: **Bulanan** (per bulan, setahun penuh) atau **Mingguan** (per minggu dalam satu bulan — minggu dihitung `ceil(tanggal / 7)`, jadi bukan minggu kalender asli; selalu tampil minimal 4 baris minggu, bisa 5 kalau bulan itu punya tanggal 29–31).

### Saldo per Akun / Saldo Akun Kas (`accountBalances`)
Ringkasan tiga angka: total saldo Kas & Bank (sama seperti di Arus Kas), **Piutang Usaha (AR)** = `Σ Invoice.total_idr − Σ InvoicePayment.amount_idr` (semua invoice, tanpa filter tanggal), dan **Hutang Usaha (AP)** = `Σ Bill.amount − Σ BillPayment.amount`. Per akun kas ditampilkan juga saldo awal, jumlah masuk/keluar, dan jumlah transaksi.

### Neraca / Balance Sheet (`balanceSheet`)
Posisi per **31 Desember** tahun terpilih:
- **Aset** = saldo Kas & Bank per akun (s/d akhir tahun) + Piutang (AR, s/d akhir tahun) + nilai buku bersih Aset Tetap (dari `FixedAsset::accumulatedAsOf()`/`bookValueAsOf()`, garis lurus — didetailkan di modul Aset Tetap, di luar cakupan dokumen ini).
- **Kewajiban** = Hutang (AP, s/d akhir tahun) + saldo pinjaman aktif (`Loan.outstanding_balance`, dikelompokkan per `loan_type` — didetailkan di modul Pinjaman).
- **Ekuitas** = Modal Disetor (`FinanceSetting::get('modal_disetor')`, angka yang diinput manual di modul Pinjaman/Setting) + **Laba Ditahan** akrual: `(Σ Invoice.total_idr + Σ transaksi manual masuk) − (Σ Bill.amount + Σ transaksi manual keluar) − akumulasi penyusutan Aset Tetap`, semuanya dihitung s/d akhir tahun terpilih.
- Halaman menampilkan indikator "Seimbang" — dicek `abs(Aset − (Kewajiban + Ekuitas)) < 1` (toleransi 1 rupiah untuk pembulatan desimal, bukan kesamaan eksak).

### Laba Rugi / Income Statement (`incomeStatement`)
Laporan **akrual tahunan** (berbasis tanggal dokumen `Invoice`/`Bill`, bukan tanggal pembayaran):
- **Penjualan & HPP per Lini Bisnis** — tiap `Invoice`/`Bill` dikelompokkan lewat `businessLine()` berdasarkan tipe tour terkait: `tour` (arah `outbound` → "Tur Outbound", selain itu → "Tur Inbound"), `rental` → "Transport", `guide` → "Tur Inbound", `mice` → "MICE / Event", tipe lain (`visa`, `ticketing`, `hotel`, atau tanpa tour) → "Lainnya". Penjualan = `Σ Invoice.total_idr`, HPP = `Σ Bill.amount`, margin kotor = `(Penjualan − HPP) / Penjualan`.
- **Biaya Operasional** — khusus `fin_transactions` ber-`source = manual` dan arah keluar (transaksi otomatis dari AP sengaja **dikecualikan** karena sudah terhitung sebagai HPP di atas — menghindari hitung ganda), dikelompokkan per kategori.
- **Beban Penyusutan Aset Tetap** — non-kas, dihitung otomatis per tahun dari master Aset Tetap (`depreciationForYear()`).
- **Pendapatan Lain-lain** — `fin_transactions` manual arah masuk (dengan alasan yang sama, mengecualikan transaksi otomatis dari AR).
- **Laba Bersih** = Laba Kotor − Biaya Operasional − Penyusutan + Pendapatan Lain-lain.

## Model Data

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `cash_accounts` | `name`, `type` (cash/bank), `opening_balance`, `is_active`, `sort_order` | `hasMany` `fin_transactions`; direferensikan juga oleh `invoice_payments.cash_account_id` & `bill_payments.cash_account_id` (modul AR/AP) |
| `fin_categories` | `name`, `type` (income/expense), `is_system`, `is_active`, `sort_order` | `is_system=true` untuk "Penjualan Tour" & "Biaya Supplier" — dicari **by name**, bukan ID, oleh `LedgerSync` |
| `fin_transactions` | `date`, `direction` (in/out), `fin_category_id`, `cash_account_id`, `amount`, `description`, `source` (manual/invoice/bill), `source_id`, `created_by` | Sumber tunggal Arus Kas/Jurnal/Buku Besar/Rekap; `source_id` menunjuk ke `invoice_payments.id` atau `bill_payments.id` untuk baris otomatis; index `(date)` dan `(source, source_id)` |

Model: `App\Models\CashAccount`, `App\Models\FinCategory`, `App\Models\FinTransaction` (method `journalLines()` menghasilkan pasangan baris debit/kredit). Sinkronisasi otomatis ada di `App\Support\LedgerSync` (dipanggil dari `App\Observers\InvoicePaymentObserver`/`BillPaymentObserver` pada event `saved`/`deleted` model `InvoicePayment`/`BillPayment` — bukan dipanggil eksplisit dari controller AR/AP). Neraca & Laba Rugi juga membaca `Invoice`, `InvoicePayment`, `Bill`, `BillPayment` (modul AR/AP), `FixedAsset` dan `Loan` (modul Aset Tetap/Pinjaman, di luar cakupan dokumen ini), dan `FinanceSetting` (key-value, dipakai untuk `modal_disetor`).

## Route & Controller

Semua route berikut ada di grup `Route::middleware('role:admin,accountant')` (`routes/web.php` baris ±227–276), dilayani `FinanceLedgerController`.

| Laporan / Fitur | Route | Method Controller |
|---|---|---|
| Arus Kas (halaman) | `GET /finance/cash-flow` (`finance.cashflow`) | `cashFlow` |
| Jurnal (halaman) | `GET /finance/journal` (`finance.journal`) | `journal` |
| Jurnal (PDF) | `GET /finance/journal/pdf` (`finance.journal.pdf`) | `journalPdf` |
| Buku Besar (halaman) | `GET /finance/ledger` (`finance.ledger`) | `ledger` |
| Buku Besar (PDF) | `GET /finance/ledger/pdf` (`finance.ledger.pdf`) | `ledgerPdf` |
| Rekap (halaman) | `GET /finance/recap` (`finance.recap`) | `recap` |
| Rekap (PDF) | `GET /finance/recap/pdf` (`finance.recap.pdf`) | `recapPdf` |
| Saldo per Akun (halaman) | `GET /finance/account-balances` (`finance.account-balances`) | `accountBalances` |
| Saldo per Akun (PDF) | `GET /finance/account-balances/pdf` (`finance.account-balances.pdf`) | `accountBalancesPdf` |
| Neraca (halaman) | `GET /finance/balance-sheet` (`finance.balance-sheet`) | `balanceSheet` |
| Neraca (PDF) | `GET /finance/balance-sheet/pdf` (`finance.balance-sheet.pdf`) | `balanceSheetPdf` |
| Laba Rugi (halaman) | `GET /finance/income-statement` (`finance.income-statement`) | `incomeStatement` |
| Laba Rugi (PDF) | `GET /finance/income-statement/pdf` (`finance.income-statement.pdf`) | `incomeStatementPdf` |
| Transaksi — daftar | `GET /finance/transactions` (`finance.transactions`) | `transactions` |
| Transaksi — tambah | `POST /finance/transactions` (`finance.transactions.store`) | `storeTransaction` |
| Transaksi — ubah | `PATCH /finance/transactions/{finTransaction}` (`finance.transactions.update`) | `updateTransaction` |
| Transaksi — hapus | `DELETE /finance/transactions/{finTransaction}` (`finance.transactions.destroy`) | `destroyTransaction` |
| Kategori — tambah | `POST /finance/categories` (`finance.categories.store`) | `storeCategory` |
| Kategori — ubah | `PATCH /finance/categories/{finCategory}` (`finance.categories.update`) | `updateCategory` |
| Kategori — hapus | `DELETE /finance/categories/{finCategory}` (`finance.categories.destroy`) | `destroyCategory` |
| Akun Kas — tambah | `POST /finance/cash-accounts` (`finance.cash-accounts.store`) | `storeCashAccount` |
| Akun Kas — ubah | `PATCH /finance/cash-accounts/{cashAccount}` (`finance.cash-accounts.update`) | `updateCashAccount` |
| Akun Kas — hapus | `DELETE /finance/cash-accounts/{cashAccount}` (`finance.cash-accounts.destroy`) | `destroyCashAccount` |

Semua halaman PDF dirender lewat `App\Support\Pdf::stream()` ke template Blade di `resources/views/finance/*.blade.php` (`journal`, `ledger`, `recap`, `account_balances`, `balance_sheet`, `income_statement`).

## Halaman & Komponen (UI)

Modul ini lebih **berat-grafik** dibanding modul lain — hampir tiap halaman punya minimal satu `<apexchart>` (bar/donut/area/line) di samping kartu ringkasan & tabel standar (lihat fondasi desain untuk pola kartu/tabel bersama). Warna chart dipilih manual per halaman (hijau `#16a34a` untuk pemasukan, merah `#dc2626` untuk pengeluaran — konsisten dengan makna warna di §Warna fondasi desain), formatter tooltip selalu pakai `fmtRp`.

- **`Finance/CashFlow.vue`** — 4 kartu ringkas, bar chart pemasukan vs pengeluaran per bulan, donut pengeluaran per kategori, area chart tren saldo kumulatif, daftar saldo per akun kas. Filter tahun (select, reload penuh).
- **`Finance/Journal.vue`** — kartu "Prinsip Keseimbangan" (debit vs kredit), tabel jurnal per entri dengan baris debit menonjol (font-medium) dan baris kredit terindentasi, keterangan+referensi (Manual/AR/AP) di bawah tiap entri. Filter bulan (`<input type="month">`), tombol unduh PDF.
- **`Finance/Ledger.vue`** — kartu "Laba (Rugi) Akuntansi" dengan bar chart horizontal, daftar akun dikelompokkan 3 grup (Kas & Bank / Pendapatan / Beban) sebagai **accordion** (klik untuk expand detail posting per akun). Filter bulan opsional + tahun.
- **`Finance/Recap.vue`** — toggle mode Bulanan/Mingguan (segmented button `bg-primary`/`text-muted-foreground`, pola dari fondasi desain), combo chart (bar + line), tabel rekap per baris periode.
- **`Finance/BalanceSheet.vue`** — kartu status seimbang, dua kolom (Aset biru vs Kewajiban+Ekuitas kuning), rincian Aset Tetap & Pinjaman ditampilkan bersarang per kategori, donut komposisi aset.
- **`Finance/IncomeStatement.vue`** — 4 kartu ringkas, tabel per lini bisnis dengan badge margin berwarna (hijau ≥20%, kuning ≥10%, merah <10% — pola badge warna dari fondasi desain), bar chart penjualan vs HPP, blok Biaya Operasional & Penyusutan, kartu "Ringkasan Laba Rugi" penutup.
- **`Finance/Transactions.vue`** — kartu ringkas bulan berjalan, dialog tambah/edit transaksi (`Dialog` shadcn) dengan toggle arah Pemasukan/Pengeluaran, dropdown kategori yang otomatis tersaring sesuai arah + opsi tambah kategori baru inline tanpa tutup dialog, panel collapsible "Kelola Kategori & Akun Kas" untuk CRUD ringan. Baris transaksi menampilkan badge sumber (`auto: AR`/`auto: AP` vs tanpa badge untuk manual); aksi edit/hapus hanya muncul untuk baris manual.
- **`Finance/AccountBalances.vue`** — 3 kartu ringkas (Kas&Bank/AR/AP), daftar per akun dengan rincian saldo awal/masuk/keluar, bar chart horizontal perbandingan saldo antar akun.

## Yang Perlu Diperhatikan

- **"Prinsip Keseimbangan" di Jurnal selalu benar secara matematis** — total debit dan kredit sama-sama dijumlah dari kolom `amount` yang sama (`journalData()`), karena tiap transaksi memang didesain menghasilkan tepat satu baris debit dan satu baris kredit senilai sama. Jadi indikator "Debit = Kredit" ini ilustratif untuk konsep double-entry, bukan validasi integritas data yang bisa gagal.
- **AR/AP di laporan modul ini tidak difilter status approval invoice** — `accountBalancesData()`, `balanceSheetData()`, dan `incomeStatementData()` menjumlah **semua** `Invoice` sesuai tanggal (`Invoice::sum('total_idr')` / `whereYear('date', ...)`), tanpa scope `->approved()`. Ini berbeda dari dashboard AR/AP utama (`FinanceController::index`, modul 10) yang eksplisit hanya menghitung `Invoice::approved()`. Karena invoice ber-mata-uang IDR sudah mendapat `total_idr` sejak tahap proforma (lewat `Invoice::syncProformaTotal()`, jauh sebelum di-approve sales), invoice yang belum disetujui bisa ikut masuk ke angka Piutang/Penjualan di Saldo Akun Kas, Neraca, dan Laba Rugi — berbeda dari yang tampil di dashboard AR/AP.
- **Dua "laba" berbeda konsep di modul yang sama** — Buku Besar menghitung "Laba Akuntansi" **cash-basis** murni dari `fin_transactions` pada periode terpilih (bisa per bulan), sementara Laporan Laba Rugi menghitung laba **akrual** dari tanggal dokumen `Invoice`/`Bill` ditambah opex manual & penyusutan, hanya bisa per tahun. Keduanya tidak dirancang untuk sama nilainya — jangan disandingkan sebagai cross-check.
- **Mengganti nama kategori sistem berisiko mematikan sinkronisasi otomatis secara diam-diam** — `updateCategory` tidak melarang mengubah `name` kategori `is_system=true`, padahal `LedgerSync` mencari kategori "Penjualan Tour"/"Biaya Supplier" **berdasarkan nama**, bukan ID. Kalau nama diganti, `LedgerSync::upsert()` tidak menemukan kategori (`$category` null) dan diam-diam **berhenti memposting** pembayaran invoice/bill baru ke `fin_transactions` — tanpa error yang terlihat akuntan.
- **`cash_accounts` (Akun Kas, modul ini) ≠ `bank_accounts`** (Rekening, [08-rekening.md](08-rekening.md)) — dua tabel independen dengan tujuan berbeda meski namanya mirip: yang pertama untuk pembukuan internal (sisi debit/kredit jurnal), yang kedua untuk info rekening yang tampil di PDF invoice ke customer.
- Transaksi & saldo otomatis dari AR/AP **tidak bisa diedit/dihapus langsung** dari halaman Transaksi (`abort_unless($finTransaction->source === 'manual', ...)`) — perubahan harus lewat pembayaran invoice/bill aslinya di modul AR/AP, yang lalu memicu `LedgerSync` ulang via observer.
- Rincian Aset Tetap (penyusutan, nilai buku) dan Pinjaman yang tampil di Neraca/Laba Rugi hanya dikonsumsi di sini — pengelolaan CRUD-nya (kategori, metode penyusutan, dsb.) ada di modul terpisah, tidak dibahas dokumen ini.
