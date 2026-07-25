# Modul: Penjualan (Tour / Rental / Guide / Visa-Paspor / Ticketing / MICE / Hotel)

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Ini adalah **modul inti** ERP — satu mesin (`tours` table + `TourController`) yang melayani **7 jenis penjualan** sekaligus: Tour, Rental Mobil/Boat, Jasa Guide, Visa/Paspor, Ticketing, MICE/Event, Hotel. Ketujuhnya di sidebar terlihat sebagai menu terpisah (Tour, Rental, Jasa Guide, dst), tapi sebenarnya semua menuju route yang sama `tours.index`/`tours.edit` dengan query param `?type=`. Dipakai oleh **admin & sales**.

Satu baris `tours` = satu inquiry/order, dari awal masuk sampai selesai. Semua sub-fitur (item produk, itinerary, quotation PDF, invoice, biaya tambahan, penugasan guide/driver, riwayat) menempel ke tour lewat relasi — tidak ada halaman terpisah untuk masing-masing, semua muncul sebagai **panel bertumpuk secara vertikal** di satu halaman `Tours/Edit.vue`.

## Alur Bisnis

### 1. Pipeline status tour
Satu kolom `status` (bukan state machine terpisah per tipe) mengalir:
```
inquiry → quotation_draft → quotation_sent → follow_up → negotiation → confirmed
                                                                       ↘ cancelled (dari status manapun)
```
Setiap perubahan status otomatis dicatat ke `tour_histories` (lihat §Riwayat). Dua efek samping penting saat status berubah:
- **→ `confirmed`**: (a) quotation item yang sudah disetujui customer otomatis dikonversi jadi `tour_items` (`TourController::convertApprovedQuotationItems`), (b) tugas **Booking** otomatis dibuat satu per supplier (`generateBookings` — dikelompokkan dari `tour_items.product.supplier_id`, kategori diambil dari tipe produk terbanyak dalam grup). Booking ini yang muncul di menu Operasional → Booking untuk dieksekusi tim `operation`.
- Invoice **tidak** lagi dibuat otomatis saat confirmed — sejak alur 2-tahap (lihat §Invoice), sales membuat invoice manual dari panel Invoice.

### 2. Item Produk & kalkulasi profit "perkiraan"
`tour_items` adalah baris produk (hotel/transport/guide/dll) yang di-**snapshot** dari `products` saat ditambahkan (`unit_cost`/`unit_sell`/`currency` disalin, bukan referensi live — supaya harga produk berubah di kemudian hari tidak mengubah tour lama). `line_cost`/`line_sell` adalah **stored generated column** MySQL: `qty * nights * unit_cost` / `qty * nights * unit_sell` — dihitung database, bukan PHP/JS.

Profit "perkiraan" tour (`Tour::profit`/`margin`) punya dua rumus berbeda tergantung state:
- **Sebelum invoice disetujui** (atau tipe bukan `tour`): `total_sell = Σ tour_items.line_sell`, `total_cost = Σ tour_items.line_cost` → profit = selisih item.
- **Tipe `tour` dengan invoice yang sudah disetujui**: `total_sell` diganti jadi **`total_idr` invoice** (tagihan riil ke customer, bukan estimasi item), `total_cost` diganti jadi **`Σ line_cost` dari invoice items** (Rincian Profit — lihat §Invoice). Alasannya: begitu ada invoice resmi, itulah angka jual yang benar, bukan estimasi awal.

Ada juga **profit "aktual"** (`actual_cost`/`actual_profit`/`cost_variance`) berbasis `bills` (AP riil yang sudah dicatat akuntan) vs `total_cost` — dipakai untuk membandingkan estimasi vs realisasi.

### 3. Invoice — alur 2 tahap (Tahap 1: Patokan → Tahap 2: Rincian → Approve)
Satu tour hanya boleh punya **satu invoice** (`InvoiceController::store` menolak kalau sudah ada). Invoice tidak punya kolom status terpisah untuk tahap sales — tahapnya **diturunkan** (`Invoice::stage`, computed, bukan kolom):
- `baseline` — `baseline_total` masih 0, sales baru mengisi proforma (mata uang, `unit_price`, baris deskripsi bebas seperti "Hotel 3N", jumlah rekening bank yang ditampilkan di PDF).
- `detail` — `baseline_total` sudah dikunci (`lockBaseline`, snapshot dari `total = unit_price × pax` saat itu). Sales bisa "samakan patokan" ulang selama belum approve.
- `approved` — `approved_at` terisi lewat `InvoiceController::approve`. Titik ini **gerbang ke Keuangan**: nomor keuangan gapless dibuat (`Invoice::nextFinanceNumber()`, format `INV-<tahun>-NNNN`, terpisah dari nomor invoice biasa), dan untuk mata uang non-IDR **kurs wajib diisi saat approve** → `total_idr` dihitung & dibekukan (supaya laporan IDR akuntan tidak berubah-ubah kalau kurs pasar bergerak setelahnya). Setelah approved, invoice **terkunci** — proforma, baseline, due date tidak bisa diubah lagi lewat sisi sales (`ensureNotApproved` di tiap method).

Setelah approve, `Bill::createMissingFromInvoice()` otomatis membuat **Bill draft (nominal 0)** untuk tiap item Rincian Profit yang punya `product.supplier_id` — akuntan tinggal isi nominal riilnya di Keuangan, tidak perlu input ulang dari nol.

**Rincian Profit** (`invoice_items`, dikelola di panel `InvoicesPanel.vue`) adalah tabel modal-vs-jual internal per invoice, terpisah dari `tour_items` — snapshot serupa (`unit_cost`/`unit_sell`/`currency`, `line_cost`/`line_sell` juga stored generated column). Ini yang dipakai untuk PDF "Rincian Profit" (`profitPdf`, hanya bisa diakses setelah approved) dan untuk kalkulasi profit tour tipe `tour` (lihat §2). Baris di sini bisa banyak (multi-hotel, multi-transport) — UI-nya dioptimasi untuk input cepat: baris kompak, autosave per field (tanpa tombol Simpan per baris), popover untuk detail tanggal/catatan supaya tabel tidak melebar.

**Nomor invoice** (`Invoice::nextNumber`) formatnya `INV-<tahun>-<kode tipe>-NNNN` — kode tipe sama persis dengan `Tour::resolveTypeCode()` (11=Tour Inbound, 12=Outbound, 13=Rental, 14=Guide, 15=MICE, 16=Hotel, 17=Visa/Paspor, 18=Ticketing). **Urutan NNNN mengikuti kapan invoice dibuat**, bukan urutan kode tour — dua tour bisa dibuat berurutan tapi invoice-nya urut sesuai siapa yang lebih dulu bikin invoice. Nomor keuangan (`finance_number`) terpisah lagi, formatnya `INV-<tahun>-NNNN` (tanpa kode tipe) dan urut sesuai kapan **disetujui** (masuk Keuangan) — dua penomoran ini sengaja beda makna: satu untuk pelacakan sales per-tipe, satu untuk urutan masuk buku Keuangan.

**Pembayaran** (`invoice_payments`) dicatat langsung dari panel tour oleh sales (DP/cicilan) atau dari Keuangan oleh akuntan. Tiap pembayaran punya `cash_account_id` (rekening kas tujuan, dipilih dari dropdown — lihat modul [08-rekening](08-rekening.md)) dan `exchange_rate` sendiri (DP dan pelunasan boleh pakai kurs berbeda, masing-masing dikonversi ke `amount_idr` sendiri-sendiri lalu dijumlah untuk total diterima — bukan re-konversi total pakai satu kurs).

**Guest name/phone (customer tipe Buyer)**: kalau customer tour bertipe `buyer` (travel agent yang membeli tour, bukan wisatawan langsung), `tour.guest_name`/`guest_phone` wajib diisi — ini nama tamu sebenarnya yang dilihat tim lapangan (guide/driver/MyJobs/Manifest publik), sementara identitas buyer (agent) tetap yang tertagih di invoice. `Tour::maskCustomerForField()` yang melakukan penggantian ini sebelum data sampai ke halaman lapangan.

### 4. Biaya Tambahan (Cost Request) — jalur terpisah dari Rincian Profit
Untuk biaya tak terduga **setelah** tour berjalan (Rincian Profit sudah terkunci pasca-approve, jadi tidak bisa diedit lagi di sana). Alurnya:
1. Sales ajukan (`cost-requests.store`): kategori, deskripsi, nominal perkiraan, supplier opsional. Status `pending`.
2. Sales masih bisa batalkan sendiri selama `pending` (`cost-requests.destroy` menolak kalau sudah direview).
3. Akuntan **approve** (boleh sesuaikan nominal final + tanggal) atau **reject** (wajib isi alasan, `review_notes`). Approve → **selalu** membuat `Bill` baru (AP ke supplier). **Opsional** (`bill_customer` checkbox): sekaligus tagih ke customer — ini **menambahkan baris "Additional" ke invoice utama yang sudah approved** (`appendAdditionalCharge`), bukan membuat invoice baru terpisah. Invoice suplemen terpisah pernah jadi pendekatan lama dan sempat menyisakan data nyasar di produksi — sekarang API-nya sengaja tidak mendukung itu lagi. Hanya berlaku untuk invoice ber-mata-uang IDR.

### 5. Quotation (penawaran ke customer, sebelum confirmed)
`quotation_items` — daftar item yang diajukan ke customer sebelum deal, independen dari `tour_items`. Tiap item punya `status` (`proposed`/`approved`/`rejected`) — customer/sales menandai mana yang disetujui. Saat tour di-set `confirmed`, item berstatus `approved` **otomatis dikonversi** jadi `tour_items` riil (lihat §1). Quotation punya PDF & Word export tersendiri (`QuotationController::download/preview/word`) dengan field `included`/`excluded`/`child_policy`/`terms`/`price_validity` di tabel `tours` (customer-facing, tersimpan dalam bahasa Inggris — lihat `Tour::TYPES_EN`/`DETAIL_LABELS`).

### 6. Field khusus per tipe (`details` JSON)
Karena satu model melayani 7 tipe, field yang spesifik-tipe (mis. `vehicle`/`with_driver` untuk Rental, `route_from`/`airline`/`pnr` untuk Ticketing, `event_type`/`venue_name` untuk MICE, `hotel_name`/`check_in`/`check_out` untuk Hotel) disimpan longgar di kolom `details` (JSON, cast `array`), bukan kolom terpisah per tipe. Label tampilannya didefinisikan per tipe di `Tour::DETAIL_LABELS` (backend, untuk PDF) dan cermin frontend-nya di `resources/js/lib/inquiryTypes.js` (`TYPE_FIELDS`) — **dua tempat ini harus tetap selaras manual** kalau ada field baru.

### 7. MICE — sub-fitur khusus tipe `mice`
Tipe `mice` dapat panel tambahan `MiceTemplatePanel` (hanya render kalau `tour.type === 'mice'`): template paket MICE (`mice_templates`, kolom `items` JSON berisi daftar item siap pakai) bisa **diterapkan** ke tour (`mice-templates.apply`) untuk cepat isi item, atau sebaliknya tour yang sudah diisi bisa **disimpan sebagai template baru** (`save-as-mice-template`) untuk dipakai ulang di event serupa. Tour tipe MICE juga satu-satunya yang punya kolom `budget` (dibandingkan terhadap estimasi biaya — dipakai untuk gauge budget di UI).

### 8. Itinerary (hanya tipe `tour`)
Dua bentuk paralel: `tour_itinerary_days` (rencana harian, deskripsi per hari) dan `tour_itinerary_hours` (jadwal per-jam dalam satu hari). Bisa diimpor dari teks bebas (`itinerary.import`) atau upload PDF siap pakai (`itinerary.pdf.upload`) sebagai pengganti input manual.

### 9. Penugasan lapangan & Riwayat
`assignments` — guide/driver/tour_leader yang ditugaskan ke tour (`role`, `person_name`, `phone`, `vehicle`, `pickup_time`). Ini yang menentukan siapa yang melihat tour tsb di **My Jobs** (lihat modul [13](13-my-jobs-manifest.md)) — bukan lewat `users` langsung, field user dicocokkan lewat `person_name`/kontak, bukan `user_id` (jadi assignment tidak selalu terhubung ke akun sistem).

`tour_histories` — log semua perubahan status + catatan sistem (bill dibuat, invoice approved, dst) — ditampilkan sebagai timeline read-only di panel History, tidak pernah diedit user, hanya `store`/`destroy` untuk catatan manual sales.

## Model Data (ringkas)

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `tours` | `belongsTo Customer`, `belongsTo TourPackage`, `hasMany` (items, quotationItems, assignments, itineraryDays/Hours, histories, invoices, bookings, bills, costRequests) | `code` auto (`WM-<tahun>-<kode tipe>-NNNN`), `details`/`pricing` JSON, `budget` decimal |
| `tour_items` | `belongsTo Tour`, `belongsTo Product` | Snapshot harga; `line_cost`/`line_sell` generated |
| `invoices` | `belongsTo Tour`, `hasMany` (items, payments), `belongsTo User (approved_by)` | `description_lines`/`bank_account_ids` JSON; `number` vs `finance_number` beda skema (lihat §3) |
| `invoice_items` | `belongsTo Invoice`, `belongsTo Product` | = Rincian Profit; snapshot harga, generated columns sama pola dengan `tour_items` |
| `cost_requests` | `belongsTo Tour`, `belongsTo Supplier`, `belongsTo Bill`, `belongsTo Invoice`, `belongsTo User` (requested_by/reviewed_by) | `invoice_id` hanya terisi kalau `bill_customer` dicentang saat approve |
| `quotation_items` | `belongsTo Tour`, `belongsTo Product` | `status`: proposed/approved/rejected |
| `assignments` | `belongsTo Tour`, `belongsTo User` (opsional) | `role`: guide/driver/tour_leader |
| `tour_histories` | `belongsTo Tour` | append-only log |
| `mice_templates` | — | `items` JSON, dipakai lintas-tour |

## Route & Controller

| Route | Controller | Role |
|---|---|---|
| `tours.index/store/edit/update/destroy` | `TourController` | admin, sales |
| `tours.email.send`, `tours.histories.*`, `tours.itinerary.*`, `tour-items.*` | `TourEmailController`, `TourHistoryController`, `TourItineraryController`, `TourItemController` | admin, sales |
| `invoices.store/proforma/baseline/due-date/approve/destroy`, `invoice-items.*`, `invoice-deposits.*` | `InvoiceController`, `InvoiceItemController`, `InvoicePaymentController` | admin, sales (sisi pembuatan) |
| `invoices.preview/download/profit-pdf` | `InvoiceController` | admin, sales, **accountant** |
| `cost-requests.store/destroy` | `CostRequestController` | admin, sales (ajukan) |
| `cost-requests.approve/reject` | `CostRequestController` | admin, **accountant** (di grup route Keuangan) |
| `quotation.download/preview/word`, `quotation-items.*` | `QuotationController`, `QuotationItemController` | admin, sales |
| `mice-templates.*` | `MiceTemplateController` | admin, sales |
| `assignments.*` | `AssignmentController` | admin, sales |

## Halaman & Komponen (UI)

- **`Tours/Index.vue`** — daftar per tipe (query `?type=`), filter status/tanggal/sales/pencarian bebas, paginasi 25/halaman. Mengikuti pola kartu+tabel standar (lihat fondasi desain).
- **`Tours/Create.vue`** — form inquiry baru, field dinamis sesuai `type` (lewat `inquiryTypes.js`).
- **`Tours/Edit.vue`** — halaman kerja utama, **satu halaman panjang** berisi panel bertumpuk (bukan tab terpisah), urutannya persis: `HeaderPanel` → `ItemsPanel` → `InvoicesPanel` (hanya kalau `status === 'confirmed'`) → `CostRequestsPanel` (idem) → `OperasionalPanel` (assignment + link manifest) → `ItineraryPanel` (hanya tipe `tour`) → `QuotationPanel` → `QItemsPanel` → `MiceTemplatePanel` (hanya tipe `mice`) → `HistoryPanel`, dengan `CostingPanel` (ringkasan profit) sebagai sidebar/panel terpisah di luar urutan utama.
- Komponen di `resources/js/Components/Tours/*.vue` masing-masing mengelola form/state sendiri lewat Inertia `useForm` + `router.patch(..., { preserveScroll: true })` — pola submit parsial khas app ini supaya tidak reload seluruh halaman panjang tsb.
- **Rincian Profit (`InvoicesPanel.vue`)** — baris tabel kompak dengan **autosave per kolom** (tanpa tombol Simpan eksplisit tiap baris) dan **popover** untuk field sekunder (tanggal, catatan) agar tabel tetap ringkas walau item banyak — pola ini kontras dengan form dialog biasa di modul lain, sengaja dioptimalkan untuk input cepat berulang.
- **Catatan bug yang pernah terjadi**: dua `<Dialog>` terbuka bersamaan (konfirmasi approve di atas dialog approve) sempat membuat tombol tidak responsif karena overlay dialog pertama menghalangi klik — pola sekarang selalu tutup dialog pertama (`open.value=false; await nextTick()`) sebelum membuka dialog konfirmasi berikutnya.

## Yang Perlu Diperhatikan

- **Sales belum dibatasi kepemilikan** — semua sales melihat & bisa mengedit semua tour, siapa pun pembuatnya (`sales_person` cuma teks bebas, tidak dipakai untuk otorisasi). Rencana pembatasan "sales hanya lihat tour miliknya" sudah disetujui tapi ditunda — lihat [tour-ownership.md](../rencana/tour-ownership.md).
- Sub-route tour (items, invoice, itinerary, dll) hanya dijaga lewat middleware role `admin,sales` di level route group — **tidak ada** pengecekan kepemilikan per-tour di level controller saat ini.
- Field `details` per tipe harus disinkronkan manual antara `Tour::DETAIL_LABELS` (PHP) dan `inquiryTypes.js` (Vue) — tidak ada validasi otomatis yang menjaga keduanya tetap selaras.
