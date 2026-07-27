# Keuangan — Pembukuan

> **Status:** ✅ Berjalan · **Peran:** admin, accountant · **Sejak:** Jun 2026
> **Terkait:** [keuangan-ar-ap.md](keuangan-ar-ap.md), [keuangan-aset-fiskal-pinjaman.md](keuangan-aset-fiskal-pinjaman.md), [rekening-bank.md](rekening-bank.md)

## 1. Ringkasan

Lapisan pembukuan di atas AR/AP — satu tabel `fin_transactions` (diisi manual oleh akuntan atau otomatis dari tiap pembayaran invoice/bill) jadi sumber tunggal untuk Arus Kas, Jurnal, Buku Besar, dan Rekap, ditambah dua laporan posisi keuangan (Neraca dan Laba Rugi) yang menggabungkan data akrual invoice/bill dengan Aset Tetap & Pinjaman. Modul ini melampaui cakupan MVP awal, yang semula menyatakan tidak akan membangun general ledger. Dipakai oleh admin & accountant.

## 2. Cara kerja (as-built)

### Dua controller (bukan satu)
Sejak kode saat ini, modul dilayani **dua** controller berbeda: `FinanceLedgerController` menangani CRUD Transaksi manual, Kategori, dan Akun Kas; `FinanceReportController` menangani seluruh halaman laporan (Arus Kas, Jurnal, Buku Besar, Rekap, Saldo per Akun, Neraca, Laba Rugi) beserta versi PDF-nya. Keduanya berada dalam satu grup route `role:admin,accountant` yang sama (`routes/web.php` ±227–306).

### Kategori pembukuan (`fin_categories`)
Pos pendapatan/pengeluaran bebas, tiap kategori bertipe `income` atau `expense`. Dua kategori **bawaan sistem** (`is_system = true`): **"Penjualan Tour"** (income) dan **"Biaya Supplier"** (expense) — dipakai `LedgerSync` untuk memposting pembayaran invoice/bill otomatis, dicari lewat `FinCategory::where('name', ...)->where('is_system', true)`, **bukan** lewat ID tetap. Kategori bawaan tidak bisa dihapus (`destroyCategory` menolak 403), kategori mana pun tidak bisa dihapus kalau masih dipakai transaksi (422). Transaksi manual harus selaras arah kategorinya (`in` → `income`, `out` → `expense`, divalidasi di `validateTransaction()`).

### Akun Kas (`cash_accounts`) — bukan Rekening
`cash_accounts` (tiap baris punya `opening_balance`) adalah akun **internal pembukuan** — dipilih akuntan di tiap transaksi/pembayaran, basis semua saldo di laporan modul ini. Tabel ini **berbeda** dari `bank_accounts` ([rekening-bank.md](rekening-bank.md), rekening yang tampil di PDF invoice customer). `invoice_payments`/`bill_payments` punya kolom `cash_account_id` sendiri (dipilih eksplisit saat mencatat pembayaran di AR/AP); fallback lama `LedgerSync::accountForMethod()` (menebak dari teks metode — mengandung "cash"/"tunai" → akun kas pertama, selain itu → akun bank pertama) hanya dipakai untuk baris lama tanpa `cash_account_id`. Akun kas tidak bisa dihapus kalau masih dipakai transaksi.

### Transaksi manual
`Finance/Transactions.vue` — catat pendapatan/pengeluaran per bulan, kartu ringkas bulan berjalan, panel "Kelola Kategori & Akun Kas" (CRUD ringan di halaman yang sama). Hanya transaksi `source = manual` yang bisa diedit/dihapus dari sini (`abort_unless`); transaksi `source = invoice`/`bill` (hasil sinkronisasi) dikunci — harus diubah lewat pembayaran aslinya di AR/AP.

### Sinkronisasi otomatis
Tiap pembayaran invoice (`invoice_payments`) atau bill (`bill_payments`) disalin jadi satu baris `fin_transactions` lewat `App\Support\LedgerSync`, dipicu Eloquent Observer (`InvoicePaymentObserver`/`BillPaymentObserver`, event `saved`/`deleted`) — bukan dipanggil manual dari controller AR/AP. Invoice non-IDR memakai `amount_idr` (bukan `amount`); buku besar selalu IDR.

### Arus Kas, Jurnal, Buku Besar, Rekap
- **Arus Kas** (`cashFlow`) — grafik tahunan pemasukan vs pengeluaran per bulan, saldo berjalan kumulatif (dari total `opening_balance` semua akun + akumulasi sebelum 1 Januari tahun terpilih), breakdown per kategori, saldo terkini per akun kas.
- **Jurnal** (`journal`) — tiap `fin_transactions` bulan terpilih sebagai baris debit/kredit lewat `FinTransaction::journalLines()`: transaksi `in` → Debit Akun Kas, Kredit Kategori; `out` sebaliknya. `ref` menunjukkan asal (`Manual`/`AR`/`AP`).
- **Buku Besar** (`ledger`) — mengelompokkan `fin_transactions` (per bulan atau setahun) jadi akun: tiap akun kas = grup `aset`, tiap kategori = grup `pendapatan`/`beban`. Saldo normal akuntansi (`aset`/`beban` = debit−kredit; `pendapatan` = kredit−debit). Grup pendapatan dikurangi beban = **"Laba (Rugi) Akuntansi"** — cash-basis murni dari periode terpilih.
- **Rekap** (`recap`) — Bulanan (setahun penuh) atau Mingguan (`ceil(tanggal/7)`, bukan minggu kalender asli; minimal 4 baris minggu per bulan).

### Saldo per Akun (`accountBalances`)
Total saldo Kas & Bank, **Piutang (AR)** = `Σ Invoice.total_idr − Σ InvoicePayment.amount_idr`, **Hutang (AP)** = `Σ Bill.amount − Σ BillPayment.amount` — **semua** invoice/bill, tanpa filter tanggal maupun status approval.

### Neraca (`balanceSheet`)
Posisi per 31 Desember tahun terpilih: **Aset** = Kas & Bank per akun + Piutang (AR, s/d akhir tahun) + nilai buku bersih Aset Tetap (`FixedAsset::accumulatedAsOf()`/`bookValueAsOf()`, lihat [keuangan-aset-fiskal-pinjaman.md](keuangan-aset-fiskal-pinjaman.md)). **Kewajiban** = Hutang (AP) + saldo pinjaman aktif (`Loan.outstanding_balance`, per `loan_type`). **Ekuitas** = Modal Disetor (`FinanceSetting::get('modal_disetor')`) + Laba Ditahan akrual (`(Σ Invoice.total_idr + manual in) − (Σ Bill.amount + manual out) − akumulasi penyusutan`), semuanya s/d akhir tahun. Indikator "Seimbang" dicek `abs(Aset − (Kewajiban+Ekuitas)) < 1` (toleransi 1 rupiah).

### Laba Rugi (`incomeStatement`)
Laporan akrual tahunan (tanggal dokumen, bukan tanggal pembayaran): **Penjualan & HPP per Lini Bisnis** (dikelompokkan via `businessLine()` dari tipe tour: `tour` arah `outbound`→"Tur Outbound"/lainnya→"Tur Inbound", `rental`→"Transport", `guide`→"Tur Inbound", `mice`→"MICE / Event", lainnya→"Lainnya"; Penjualan = `Σ Invoice.total_idr`, HPP = `Σ Bill.amount`). **Biaya Operasional** — `fin_transactions` `source=manual` arah keluar saja (transaksi otomatis dari AP dikecualikan, sudah terhitung sebagai HPP). **Beban Penyusutan** — non-kas, dari `depreciationForYear()`. **Pendapatan Lain-lain** — `fin_transactions` manual arah masuk. **Laba Bersih** = Laba Kotor − Opex − Penyusutan + Pendapatan Lain-lain.

### Ekspor PDF
Semua laporan (kecuali dashboard AR/AP) punya versi PDF lewat `App\Support\Pdf::stream()` ke template Blade `resources/views/finance/*.blade.php` — data dihitung sekali di method privat (mis. `journalData()`, `ledgerData()`, `fiscalData()`) lalu dipakai bersama oleh halaman Inertia dan endpoint PDF, supaya angka layar dan PDF selalu konsisten.

## 3. Keterkaitan

- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — tiap pembayaran invoice/bill jadi input otomatis `fin_transactions` lewat observer.
- **Keuangan — Aset Tetap, Fiskal & Pinjaman** ([keuangan-aset-fiskal-pinjaman.md](keuangan-aset-fiskal-pinjaman.md)) — nilai buku aset & saldo pinjaman dikonsumsi Neraca; penyusutan dikonsumsi Laba Rugi.
- **Rekening** ([rekening-bank.md](rekening-bank.md)) — `cash_accounts` (modul ini) berbeda tabel dari `bank_accounts`.

## 4. Batasan & jebakan ⚠️

- **AR/AP di laporan modul ini tidak difilter status approval invoice** — `accountBalancesData()`, `balanceSheetData()`, `incomeStatementData()` menjumlah **semua** `Invoice` sesuai tanggal, tanpa scope `->approved()`. Berbeda dari dashboard AR/AP utama ([keuangan-ar-ap.md](keuangan-ar-ap.md)) yang eksplisit hanya `Invoice::approved()`. Karena invoice IDR sudah dapat `total_idr` sejak proforma, invoice yang belum disetujui bisa ikut masuk ke angka Piutang/Penjualan di sini — beda dari yang tampil di dashboard AR/AP.
- **"Prinsip Keseimbangan" di Jurnal selalu benar secara matematis** — debit dan kredit sama-sama dijumlah dari kolom `amount` yang sama, karena tiap transaksi memang didesain menghasilkan tepat satu baris debit dan satu kredit senilai sama. Indikator ini ilustratif untuk konsep double-entry, bukan validasi integritas data.
- **Dua "laba" berbeda konsep** — Buku Besar menghitung "Laba Akuntansi" cash-basis murni dari `fin_transactions` (bisa per bulan), Laba Rugi menghitung laba akrual dari tanggal dokumen Invoice/Bill + opex manual + penyusutan (hanya per tahun). Tidak dirancang untuk sama nilainya — jangan disandingkan sebagai cross-check.
- **Mengganti nama kategori sistem berisiko mematikan sinkronisasi otomatis secara diam-diam** — `updateCategory` tidak melarang mengubah `name` kategori `is_system=true`, padahal `LedgerSync` mencari "Penjualan Tour"/"Biaya Supplier" **berdasarkan nama**. Kalau nama diganti, `LedgerSync::upsert()` tidak menemukan kategori dan diam-diam berhenti memposting transaksi baru — tanpa error yang terlihat akuntan.
- Transaksi & saldo otomatis dari AR/AP tidak bisa diedit/dihapus langsung dari halaman Transaksi — perubahan harus lewat pembayaran invoice/bill aslinya.
- Rumus rekonsiliasi keuangan lintas-fitur (Profit riil, Piutang, Hutang) ada di [ikhtisar-proyek.md §6](../ikhtisar-proyek.md#6-keuangan) — jangan disalin ulang di sini.

## 5. Status & yang belum

Berjalan penuh di production sejak akhir Juni 2026.

## 6. Dokumen terkait

- [keuangan-ar-ap.md](keuangan-ar-ap.md) — sumber pembayaran yang mendasari `fin_transactions` otomatis
- [keuangan-aset-fiskal-pinjaman.md](keuangan-aset-fiskal-pinjaman.md) — data Aset Tetap & Pinjaman yang dikonsumsi Neraca/Laba Rugi
- [rekening-bank.md](rekening-bank.md) — beda `cash_accounts` vs `bank_accounts`
- [ikhtisar-proyek.md §3 & §6](../ikhtisar-proyek.md) — invarian lintas-fitur & rumus keuangan
- [referensi/pola-ui-desain.md](../referensi/pola-ui-desain.md) — pola UI/warna bersama
