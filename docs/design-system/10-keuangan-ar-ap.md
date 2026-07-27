# Modul: Keuangan — Piutang (AR) & Hutang (AP)

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama, dan [fitur/invoice.md](../fitur/invoice.md) untuk alur pembuatan/approval invoice dari sisi sales (skema penomoran, alur 2-tahap Patokan → Rincian → Approve, Rincian Profit). Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Modul Keuangan adalah **sisi akuntan** dari siklus penjualan: setelah sales menyetujui invoice (`Invoice::approve`), tour tsb "masuk Keuangan" dan baru terlihat di sini. Dua halaman inti — dashboard `Finance/Index.vue` (rekap AR/AP seluruh perusahaan) dan detail per tour `Finance/Tour.vue` (tempat akuntan bekerja: catat pembayaran, kelola bill, review biaya tambahan). Diakses lewat role `admin, accountant` (route group `role:admin,accountant` di `routes/web.php`).

Konsep kunci: **AR (piutang)** = invoice yang sudah disetujui sales, ditagih ke customer, dilunasi lewat `invoice_payments`. **AP (hutang)** = `bills` ke supplier, sebagian dibuat otomatis dari Rincian Profit saat invoice disetujui, dilunasi lewat `bill_payments`. Akuntan **tidak membuat/mengedit invoice dari nol** — invoice adalah milik sales; akuntan hanya mengelola status/tanggal/catatan dan pembayarannya (`InvoiceController::update` dibatasi ke field `date`, `due_date`, `status`, `notes`).

Di `AuthenticatedLayout.vue` (`navGroups`), role `accountant` **hanya** mendapat grup menu "Keuangan" (Keuangan, Arus Kas, Saldo Akun, Transaksi, dst) — berbeda dari role `admin/sales` yang mendapat menu Penjualan+Operasional+Data Master lengkap. Praktis akuntan tidak melihat menu Tour/Invoice sama sekali di sidebar; satu-satunya jalan masuk ke data invoice/bill per tour adalah lewat dashboard Keuangan ini.

## Alur Bisnis

### 1. Invoice masuk Keuangan
`FinanceController::index()` & `::tour()` selalu memfilter invoice lewat scope `Invoice::approved()` (`whereNotNull('approved_at')`) — invoice yang masih `baseline`/`detail` (belum disetujui sales) tidak pernah muncul di Keuangan sama sekali, baik di dashboard maupun halaman per tour. Karena satu tour hanya boleh punya satu invoice, `tour.invoices` yang dikirim ke `Finance/Tour.vue` praktis berisi maksimal satu baris.

### 2. Auto-create Bill draft dari Rincian Profit
Saat sales menekan **Approve** pada invoice (`InvoiceController::approve`, dalam transaksi DB yang sama dengan penguncian nomor keuangan), `Bill::createMissingFromInvoice($invoice)` dipanggil otomatis. Logikanya (`app/Models/Bill.php`):
- Iterasi semua `invoice->items` (baris Rincian Profit).
- Baris yang produknya **tidak** punya `supplier_id` dilewati (tidak dibuatkan bill — biasanya biaya tanpa vendor jelas).
- Baris bersupplier di-`firstOrCreate` dengan kunci unik `invoice_item_id` — jadi **idempotent**: aman dipanggil ulang (dipakai juga oleh command `BackfillSupplierBills` dan `MatchInvoiceItemsToProducts` untuk invoice lama).
- Bill baru dibuat dengan **`amount = 0`** dan `status = 'unpaid'` — nominal riil sengaja dikosongkan, akuntan yang mengisinya belakangan setelah tahu tagihan asli dari supplier. `description` & `date` disalin dari item invoice/invoice induk; `category` dipetakan dari `product_type` item lewat tabel `PRODUCT_TYPE_TO_CATEGORY` (`hotel/transport/guide/restaurant/attraction` — tipe produk lain seperti `venue`/`equipment` jatuh ke `other`, karena enum kategori Bill tidak punya kategori khusus untuk itu).
- Bill hasil auto-create ini ditandai di UI dengan badge kuning **"⚠ Perlu diisi nominal"** (muncul kalau `Number(bill.amount) === 0`) dan menampilkan link balik "📋 Dari Rincian Profit `<nomor invoice>`" (lewat relasi `bill.invoiceItem.invoice`).

### 3. Pencatatan pembayaran AR (Invoice)
Akuntan menambah pembayaran lewat dialog "Catat Penerimaan" di `Finance/Tour.vue` (`InvoicePaymentController::store`). Field wajib: tanggal, jumlah (dalam mata uang invoice), metode (`transfer/cash/other`), **akun kas tujuan** (`cash_account_id`, dropdown dari `CashAccount::active()`), dan **kurs saat diterima** (`exchange_rate`) — wajib diisi kalau `invoice.currency !== 'IDR'`, boleh berbeda dari kurs invoice atau pembayaran sebelumnya (lihat detail mekanisme kurs-per-pembayaran di [fitur/invoice.md](../fitur/invoice.md) §2). Setelah simpan, `InvoicePaymentController::store` menghitung ulang `paid = SUM(payments.amount)` lalu set `invoice.status` jadi `paid` (kalau `paid >= total`) atau `partial`. Hapus pembayaran (`destroy`) menghitung ulang serupa, turun ke `sent` kalau `paid <= 0`.

Route pembayaran AR ini **khusus akuntan**: `invoice-payments.store/destroy` ada di grup `role:admin,accountant`, terpisah dari `invoice-deposits.store/destroy` (route berbeda tapi controller sama, `InvoicePaymentController`) yang dipakai sales dari panel tour (`role:admin,sales`) untuk DP/cicilan sebelum atau sesudah approve.

### 4. Kelola Bill & pembayaran AP
Akuntan bisa menambah bill manual (`BillController::store`, tombol "+ Bill" — untuk biaya yang tidak berasal dari Rincian Profit), mengedit bill (termasuk mengisi nominal bill draft hasil auto-create, dan mengubah `status` manual), atau menghapusnya. Pembayaran AP (`BillPaymentController::store`) polanya sama seperti AR tapi lebih sederhana — tidak ada kurs (bill selalu IDR): tanggal, jumlah, metode, akun kas. Status bill dihitung ulang otomatis sama seperti invoice (`unpaid → partial → paid` berdasar `SUM(payments.amount)` vs `bill.amount`).

### 5. Review Biaya Tambahan (Cost Request) oleh akuntan
Sisi sales mengajukan (lihat [fitur/invoice.md](../fitur/invoice.md) §2); sisi akuntan di `Finance/Tour.vue` melihat panel **"Permintaan Biaya Tambahan"** yang menampilkan seluruh `cost_requests` tour (pending & histori, badge kuning 🟡/hijau 🟢/merah 🔴). Untuk yang `pending`, dua aksi (`CostRequestController`, route grup `role:admin,accountant`):
- **Setujui** (`approve`) — dialog minta nominal final (boleh beda dari perkiraan sales), tanggal, jatuh tempo. Selalu membuat `Bill` baru ke tour (`category`/`description`/`supplier_id` disalin dari cost request). Checkbox opsional **"Tagihkan ke customer"** (`bill_customer`) hanya aktif kalau tour punya invoice approved ber-mata-uang IDR (`canBillCustomer` computed di Vue, divalidasi ulang di backend) — kalau dicentang, isi nominal jual terpisah (`sell_amount`, boleh beda dari nominal biaya) dan `appendAdditionalCharge()` menambahkan satu baris `{label: 'Additional', date, detail, amount}` ke `invoice.description_lines`, lalu menambah `invoice.total` & `invoice.total_idr` — invoice yang sama, bukan invoice baru, nomor/kurs/pembayaran lama tetap konsisten.
- **Tolak** (`reject`) — wajib isi `review_notes` (alasan, ditampilkan di histori permintaan).

Kedua aksi menolak kalau `cost_request.status !== 'pending'` (`ensurePending`) — tidak bisa direview dua kali.

### 6. PDF untuk akuntan
Tiga PDF invoice bisa diakses akuntan (route grup `role:admin,sales,accountant`), tombol-tombolnya ada di tiap baris invoice pada `Finance/Tour.vue`:
- **👁 PDF** / **⬇ Unduh** (`invoices.preview`/`invoices.download`) — invoice resmi ke customer, sama seperti yang dipakai sales.
- **📊 Rincian Profit PDF** (`invoices.profit-pdf`) — laporan internal modal-vs-jual, hanya muncul kalau invoice punya `items` dan **hanya bisa diakses setelah `approved_at` terisi** (`profitPdf` di `InvoiceController` melempar 403 kalau belum). Isi: total cost item, total jual (untuk tipe `tour` dipakai `total_idr` invoice; tipe lain pakai `Σ line_sell` item), profit & margin — rumus sama persis dengan yang dihitung di UI (`invProfit`/`invMargin` di `Finance/Tour.vue` dan `CostingPanel` sisi sales).

## Model Data

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `bills` | `belongsTo Tour`, `belongsTo Supplier`, `belongsTo InvoiceItem` (asal-usul bila auto-create), `hasMany BillPayment` | `category` enum: hotel/transport/guide/restaurant/attraction/agent/other; `status`: unpaid/partial/paid; accessor `paid`/`outstanding` (dihitung dari relasi `payments`, bukan kolom) |
| `bill_payments` | `belongsTo Bill`, `belongsTo CashAccount` | Selalu IDR, tanpa kurs |
| `invoice_payments` | `belongsTo Invoice`, `belongsTo CashAccount` | `exchange_rate` & `amount_idr` di-set otomatis lewat event `saving` model — kalau `exchange_rate` kosong, fallback ke kurs invoice (kompatibel data lama/invoice IDR yang kursnya selalu 1); `amount_idr = amount × exchange_rate` per pembayaran, **bukan** re-konversi total |
| `cost_requests` | `belongsTo Tour`, `belongsTo Supplier`, `belongsTo Bill`, `belongsTo Invoice`, `belongsTo User` (requested_by/reviewed_by) | Field `invoice_id` hanya terisi kalau `bill_customer` dicentang saat approve; lihat detail penuh di [fitur/invoice.md](../fitur/invoice.md) |
| `invoices` | (lihat [fitur/invoice.md](../fitur/invoice.md)) | Di Keuangan selalu difilter `scopeApproved()` |

`Bill::PRODUCT_TYPE_TO_CATEGORY` (konstanta private di model) adalah satu-satunya tempat pemetaan tipe produk → kategori bill — kalau ada tipe produk baru yang perlu kategori bill spesifik, di sinilah diedit.

## Route & Controller

| Route | Controller | Middleware |
|---|---|---|
| `finance.index` | `FinanceController::index` | `role:admin,accountant` |
| `finance.tour` | `FinanceController::tour` | `role:admin,accountant` |
| `invoices.update` | `InvoiceController::update` | `role:admin,accountant` — field terbatas: date/due_date/status/notes |
| `invoice-payments.store/destroy` | `InvoicePaymentController` | `role:admin,accountant` |
| `bills.store/update/destroy` | `BillController` | `role:admin,accountant` |
| `bill-payments.store/destroy` | `BillPaymentController` | `role:admin,accountant` |
| `cost-requests.approve/reject` | `CostRequestController` | `role:admin,accountant` |
| `invoices.preview/download/profit-pdf` | `InvoiceController` | `role:admin,sales,accountant` |

Semua route AR/AP akuntan berada di satu grup besar `role:admin,accountant` di `routes/web.php` (~baris 227-294), bersanding dengan modul Keuangan lain (buku kas, jurnal, neraca, aset tetap, koreksi fiskal, pinjaman — di luar cakupan dokumen ini).

## Halaman & Komponen (UI)

### `Finance/Index.vue` — Dashboard
Mengikuti pola kartu+tabel standar (lihat fondasi desain). Isi dari atas ke bawah:
- **4 kartu statistik**: Total Invoice (`ar_total` = Σ `total_idr` invoice approved), Piutang/AR (`ar_total - ar_received`, oranye kalau > 0), Total Bill (`ap_total` = Σ `bills.amount`), Hutang/AP (`ap_total - ap_paid`, merah kalau > 0).
- **Tabel "Tour Confirmed"** — pintu masuk ke pencatatan keuangan per tour: semua tour berstatus `confirmed` (bukan hanya yang sudah ada invoice/bill), menampilkan estimasi profit item (`est_sell - est_cost`), badge total invoice/bill (abu "Belum ada" kalau belum ada), link "Catat Keuangan →" (kalau belum ada invoice/bill) atau "Detail" (kalau sudah) menuju `finance.tour`.
- **Tabel "Invoice Belum Lunas"** (`status in [sent, partial]`) dan **"Invoice Lunas"** (`status = paid`) — masing-masing tabel terpisah (ditambahkan lewat commit terbaru "tampilkan daftar invoice yang sudah lunas"), keduanya diurutkan `orderBy('number')` (bukan tanggal — urut nomor invoice dari sales, konsisten dengan pola penomoran per-tipe). Kolom "Sisa" menghitung `total - Σ payments.amount`, ditambah baris kecil konversi IDR untuk invoice non-IDR.
- **Tabel "Bill Belum Dibayar"** (`status in [unpaid, partial]`), `latest('date')`.

### `Finance/Tour.vue` — Detail per tour
Halaman kerja utama akuntan, urut dari atas: 6 kartu **Budget vs Actual** (Budget Cost, Actual Cost, Variance, Revenue, Diterima+Sisa, Profit Riil — dari accessor `Tour::total_cost/actual_cost/cost_variance/total_sell/received/receivable/actual_profit`) → panel **AR — Invoice ke Customer** (kartu per invoice: header status+jumlah, daftar pembayaran dengan tombol hapus `×`, sub-panel collapsible **Rincian Profit** read-only untuk akuntan berisi tabel item modal-vs-jual, tombol aksi PDF/Bayar/Edit/Hapus) → panel **Permintaan Biaya Tambahan** (hanya render kalau ada `cost_requests`) → panel **AP — Bill ke Supplier** (kartu per bill: badge kategori+status+"perlu diisi nominal", daftar pembayaran, tombol Bayar/Edit/Hapus).

Pola submit sama seperti modul Penjualan: tiap dialog pakai Inertia `useForm` + `router.patch/post(..., { preserveScroll: true, only: ['tour'] })` — hanya prop `tour` yang di-refresh, bukan reload halaman penuh.

### Badge status (khusus modul ini)
Mengikuti pola badge pill dari fondasi desain, tiga peta warna dipakai berulang di `Finance/Index.vue` & `Finance/Tour.vue`:
- **Invoice** (`INV_STATUS`): `draft` abu, `sent` biru "Dikirim", `partial` kuning, `paid` hijau "Lunas".
- **Bill** (`BILL_STATUS`): `unpaid` merah "Belum Bayar", `partial` kuning, `paid` hijau "Lunas".
- **Cost Request** (`CR_STATUS_BADGE`, hanya di `Finance/Tour.vue`): `pending` kuning "🟡 Menunggu", `approved` hijau "🟢 Disetujui", `rejected` merah "🔴 Ditolak" — satu-satunya badge di modul ini yang menambahkan emoji titik warna di depan label, bukan hanya warna latar.

Ikon akun kas (`ACCOUNT_ICON`) dipakai konsisten di kedua form pembayaran (AR & AP) dan di daftar histori pembayaran: 🏦 untuk `type: bank`, 💵 untuk `type: cash`.

### Default nilai form pembayaran
Dialog "Catat Penerimaan"/"Catat Pembayaran" selalu mem-pre-fill tanggal ke hari ini, metode ke `transfer`, dan `cash_account_id` ke akun kas pertama (`cashAccounts[0]?.id`) — akuntan tinggal ganti kalau perlu. Khusus form invoice non-IDR, `exchange_rate` di-pre-fill dari kurs pembayaran terakhir invoice tsb (`inv.payments.at(-1)?.exchange_rate`) atau, kalau belum ada pembayaran sama sekali, dari `inv.exchange_rate` (kurs saat approve) — titik awal yang masuk akal tapi tetap bisa diubah manual per pembayaran.

## Yang Perlu Diperhatikan

- **Tombol "Hapus" invoice di `Finance/Tour.vue` memakai route `invoices.destroy`, yang middleware-nya `role:admin,sales` — bukan `admin,accountant`.** Akuntan yang menekan tombol ini akan mendapat 403 (kecuali dia juga `admin`). Tombolnya tetap muncul di UI karena Vue tidak mengecek role di sisi klien.
- Rincian Profit yang ditampilkan di panel AR bersifat **read-only** bagi akuntan (tidak ada form edit di `Finance/Tour.vue`) — kalau item Rincian Profit perlu dikoreksi setelah approve, itu di luar kewenangan akuntan lewat halaman ini (item invoice terkunci pasca-approve, lihat [fitur/invoice.md](../fitur/invoice.md) §4).
- Bill auto-create bernominal 0 **tidak otomatis diberi status khusus** — statusnya tetap `unpaid` seperti bill normal, jadi tanpa badge "⚠ Perlu diisi nominal" (dihitung murni dari `amount === 0` di frontend) akan sulit dibedakan dari bill kecil yang memang belum sempat dibayar.
- Kategori bill (`hotel/transport/guide/restaurant/attraction/agent/other`) tidak persis sama dengan kategori cost request (`hotel/transport/guide/restaurant/attraction/other` — tanpa `agent`); `Bill::PRODUCT_TYPE_TO_CATEGORY` juga tidak memetakan semua tipe produk (mis. `venue`, `equipment` dari modul MICE jatuh ke `other`).
- `appendAdditionalCharge` (dipicu dari approve cost request) hanya didukung untuk invoice ber-mata-uang IDR — dicek di backend (`CostRequestController::approve`) maupun frontend (`canBillCustomer` computed), jadi tour dengan invoice USD/mata uang asing tidak bisa menagih biaya tambahan otomatis lewat jalur ini.
- Dashboard `finance.index` menghitung `ar_total`/`ar_received` dari **semua** invoice approved lintas tour tanpa filter tanggal/periode — untuk laporan periodik (bulanan dsb) lihat modul Buku Kas/Ledger terpisah, bukan halaman ini.
