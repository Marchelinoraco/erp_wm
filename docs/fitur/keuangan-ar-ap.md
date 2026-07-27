# Keuangan — AR/AP

> **Status:** ✅ Berjalan · **Peran:** admin, accountant · **Sejak:** Jun 2026
> **Terkait:** [invoice.md](invoice.md), [penjualan-tour.md](penjualan-tour.md), [rekening-bank.md](rekening-bank.md), [supplier.md](supplier.md), [keuangan-pembukuan.md](keuangan-pembukuan.md)

## 1. Ringkasan

Sisi akuntan dari siklus penjualan — begitu sales menyetujui invoice, tour tsb "masuk Keuangan" dan tercatat sebagai AR (piutang, dilunasi lewat `invoice_payments`) dan AP (hutang/`bills`, sebagian dibuat otomatis dari Rincian Profit invoice, dilunasi lewat `bill_payments`). Dua halaman inti: dashboard `Finance/Index.vue` (rekap AR/AP seluruh perusahaan) dan `Finance/Tour.vue` (tempat akuntan bekerja per tour — Budget vs Actual, catat pembayaran, kelola bill, review biaya tambahan). Akuntan **tidak membuat/mengedit invoice dari nol** — invoice tetap milik sales; akuntan hanya mengelola status/tanggal/catatan dan pembayarannya. Dipakai oleh admin & accountant.

## 2. Cara kerja (as-built)

### Invoice masuk Keuangan
`FinanceController::index()` & `::tour()` selalu memfilter lewat scope `Invoice::approved()` (`whereNotNull('approved_at')`) — invoice yang masih `baseline`/`detail` tidak pernah muncul di Keuangan. Karena satu tour hanya boleh punya satu invoice, `tour.invoices` yang dikirim ke `Finance/Tour.vue` praktis maksimal satu baris.

### Auto-create Bill draft dari Rincian Profit
Saat sales menekan **Approve** pada invoice (`InvoiceController::approve`), `Bill::createMissingFromInvoice($invoice)` dipanggil otomatis (`app/Models/Bill.php`):
- Iterasi tiap `invoice->items` (baris Rincian Profit); baris yang produknya **tidak** punya `supplier_id` dilewati.
- Baris bersupplier di-`firstOrCreate` dengan kunci unik `invoice_item_id` — **idempotent**, aman dipanggil ulang.
- Bill baru dibuat dengan **`amount = 0`**, `status = 'unpaid'` — nominal riil diisi akuntan belakangan. `category` dipetakan dari `product_type` item lewat `Bill::PRODUCT_TYPE_TO_CATEGORY` (`hotel/transport/guide/restaurant/attraction`; tipe lain seperti `venue`/`equipment` jatuh ke `other`, karena konstanta ini hanya memetakan lima tipe).
- Ditandai di UI dengan badge kuning "⚠ Perlu diisi nominal" (murni cek `Number(bill.amount) === 0` di frontend, bukan kolom status) dan link balik "📋 Dari Rincian Profit `<nomor>`".

### Pencatatan pembayaran AR (multi-kurs)
Dialog "Catat Penerimaan" (`InvoicePaymentController::store`). Field wajib: tanggal, jumlah (mata uang invoice), metode (`transfer/cash/other`), **akun kas tujuan** (`cash_account_id`, dari `CashAccount::active()`), dan **kurs saat diterima** (`exchange_rate`) — wajib kalau `invoice.currency !== 'IDR'`, boleh berbeda dari kurs invoice maupun pembayaran sebelumnya (DP dan pelunasan bisa pakai kurs berbeda; lihat detail penuh di [invoice.md](invoice.md) §2). Setelah simpan, `paid = SUM(payments.amount)` dihitung ulang lalu `invoice.status` jadi `paid` (`paid >= total`) atau `partial`; hapus pembayaran turun ke `sent` kalau `paid <= 0`.

Route ini (`invoice-payments.store/destroy`, grup `role:admin,accountant`) terpisah dari `invoice-deposits.store/destroy` (controller sama, route beda) yang dipakai sales dari panel tour (`role:admin,sales`) untuk DP/cicilan sebelum atau sesudah approve.

### Kelola Bill & pembayaran AP
Akuntan bisa menambah bill manual (`BillController::store`, untuk biaya di luar Rincian Profit), mengedit (termasuk isi nominal bill draft), atau menghapus. Pembayaran AP (`BillPaymentController::store`) lebih sederhana — tidak ada kurs (bill selalu IDR): tanggal, jumlah, metode, akun kas. Status bill dihitung ulang otomatis sama seperti invoice (`unpaid → partial → paid`).

### Review Biaya Tambahan (Cost Request)
Sales mengajukan (lihat [invoice.md](invoice.md) §2); akuntan di `Finance/Tour.vue` melihat panel "Permintaan Biaya Tambahan" (`cost_requests` tour, badge 🟡/🟢/🔴). Untuk `pending`, dua aksi (`CostRequestController`, grup `role:admin,accountant`):
- **Setujui** (`approve`) — nominal final (boleh beda dari perkiraan sales), tanggal, jatuh tempo. Selalu membuat `Bill` baru. Checkbox opsional **"Tagihkan ke customer"** (`bill_customer`) hanya aktif kalau tour punya invoice approved ber-mata-uang IDR (`canBillCustomer` di Vue, divalidasi ulang di backend) — kalau dicentang, `appendAdditionalCharge()` menambah satu baris `{label: 'Additional', ...}` ke `invoice.description_lines` dan menambah `invoice.total`/`total_idr` langsung — invoice yang sama, bukan invoice baru.
- **Tolak** (`reject`) — wajib isi `review_notes`.

Kedua aksi menolak kalau `cost_request.status !== 'pending'`.

### Budget vs Actual per tour
6 kartu di `Finance/Tour.vue`, dari accessor `Tour`: Budget Cost (`total_cost`), Actual Cost (`actual_cost` = `SUM(bills.amount)`), Variance (`cost_variance` = `actual_cost − total_cost`, + = boros), Revenue (`total_sell`), Diterima (`received`) + Sisa (`receivable`), dan **Profit Riil** (`actual_profit` = `total_sell − actual_cost`). Untuk tipe `tour` yang sudah punya invoice approved, `total_cost`/`total_sell` beralih sumber ke item invoice (Rincian Profit) dan `total_idr` invoice, bukan lagi `tour_items` — lihat [penjualan-tour.md](penjualan-tour.md) §2.

### Dashboard (`Finance/Index.vue`)
4 kartu statistik (Total Invoice, Piutang/AR, Total Bill, Hutang/AP — dari `ar_total`/`ap_total` di `FinanceController::index`, dihitung dari **semua** invoice approved & bill lintas tour tanpa filter periode), tabel "Tour Confirmed" (pintu masuk pencatatan keuangan per tour), tabel "Invoice Belum Lunas"/"Invoice Lunas" (terpisah, `orderBy('number')`), tabel "Bill Belum Dibayar".

### Akun kas & PDF
`cash_account_id` dipilih eksplisit di tiap form pembayaran AR/AP — akun ini (`cash_accounts`) berbeda dari `bank_accounts` (rekening yang tampil di PDF invoice ke customer, lihat [rekening-bank.md](rekening-bank.md)); detail penuh mekanisme akun kas & sinkronisasi ke pembukuan ada di [keuangan-pembukuan.md](keuangan-pembukuan.md) §2. Tiga PDF invoice bisa diakses akuntan (`role:admin,sales,accountant`): 👁 Preview/⬇ Unduh (invoice resmi) dan 📊 Rincian Profit PDF (laporan internal modal-vs-jual, hanya bisa diakses setelah `approved_at` terisi).

## 3. Keterkaitan

- **Invoice** ([invoice.md](invoice.md)) — approved invoice = gerbang masuk AR; akuntan hanya ubah tanggal/status/catatan, bukan nominal.
- **Bill** — dibuat otomatis dari item Rincian Profit bersupplier saat approve, atau dari Cost Request approve.
- **Tour** ([penjualan-tour.md](penjualan-tour.md)) — sumber accessor Budget vs Actual & profit riil.
- **Rekening** ([rekening-bank.md](rekening-bank.md)) — `cash_account_id` dipilih di tiap pembayaran; tabel berbeda dari `bank_accounts`.
- **Supplier** ([supplier.md](supplier.md)) — `supplier_id` produk menentukan Bill mana yang dibuat otomatis.
- **Keuangan — Pembukuan** ([keuangan-pembukuan.md](keuangan-pembukuan.md)) — tiap pembayaran AR/AP disalin otomatis ke `fin_transactions` lewat `LedgerSync` (observer), jadi dasar Jurnal/Buku Besar/Cashflow.

## 4. Batasan & jebakan ⚠️

- **Profit riil = `total_sell − SUM(bills.amount)`** (accessor `Tour::actual_profit`) — rumus resmi lintas-fitur, lihat [ikhtisar-proyek.md §6](../ikhtisar-proyek.md#6-keuangan).
- **Tombol "Hapus" invoice di `Finance/Tour.vue` memakai route `invoices.destroy`, middleware-nya `role:admin,sales` — bukan `admin,accountant`.** Akuntan yang menekan tombol ini dapat 403 kecuali dia juga `admin`; tombolnya tetap tampil karena Vue tidak mengecek role di sisi klien.
- Rincian Profit di panel AR bersifat **read-only** bagi akuntan — item invoice terkunci pasca-approve, lihat [invoice.md §4](invoice.md#4-batasan--jebakan-️).
- Bill auto-create bernominal 0 **tidak diberi status khusus** — statusnya tetap `unpaid` normal; badge "⚠ Perlu diisi nominal" murni dihitung dari `amount === 0` di frontend.
- Kategori bill (`hotel/transport/guide/restaurant/attraction/agent/other`, 7 nilai) tidak persis sama dengan kategori cost request (6 nilai, tanpa `agent`); `Bill::PRODUCT_TYPE_TO_CATEGORY` juga tidak memetakan semua tipe produk (`venue`/`equipment` dari MICE jatuh ke `other`).
- `appendAdditionalCharge` hanya didukung untuk invoice ber-mata-uang IDR (dicek backend & frontend) — tour dengan invoice USD/asing tidak bisa menagih biaya tambahan otomatis lewat jalur ini.
- Dashboard `finance.index` menghitung `ar_total`/`ap_total` tanpa filter tanggal/periode — untuk laporan periodik lihat [keuangan-pembukuan.md](keuangan-pembukuan.md), bukan halaman ini.

## 5. Status & yang belum

Berjalan penuh di production sejak M6.

## 6. Dokumen terkait

- [invoice.md](invoice.md) — alur pembuatan/approval invoice sisi sales
- [penjualan-tour.md](penjualan-tour.md) — Tour builder & accessor profit
- [rekening-bank.md](rekening-bank.md) — akun kas vs rekening bank
- [supplier.md](supplier.md) — sumber `supplier_id` untuk auto-create Bill
- [keuangan-pembukuan.md](keuangan-pembukuan.md) — lapisan pembukuan di atas AR/AP ini
- [ikhtisar-proyek.md §3 & §6](../ikhtisar-proyek.md) — invarian lintas-fitur & rumus keuangan
- [referensi/pola-ui-desain.md](../referensi/pola-ui-desain.md) — pola UI/warna bersama
