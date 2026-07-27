# Penjualan Tour

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [rencana/tour-ownership.md](../rencana/tour-ownership.md), [quotation.md](quotation.md), [invoice.md](invoice.md), [mice-template.md](mice-template.md)

## 1. Ringkasan

Modul inti ERP — satu mesin (tabel `tours` + `TourController`) yang melayani tujuh jenis penjualan sekaligus: Tour, Rental, Jasa Guide, Visa/Paspor, Ticketing, MICE, dan Hotel, lewat satu halaman kerja `Tours/Edit.vue` berisi panel bertumpuk (item produk, itinerary, invoice, biaya tambahan, penugasan lapangan, riwayat). Tiap tour mengalir lewat pipeline status `inquiry → … → confirmed/cancelled`, dan harga produk di-snapshot ke `tour_items` saat ditambahkan sehingga perubahan harga produk di kemudian hari tidak mengubah tour lama. Dipakai sehari-hari oleh admin & sales sebagai titik masuk utama seluruh alur penjualan.

## 2. Cara kerja (as-built)

### Satu mesin, tujuh tipe penjualan

Sidebar menampilkan tujuh menu terpisah (Tour, Rental, Jasa Guide, Visa/Paspor, Ticketing, MICE/Event, Hotel), tapi semuanya menuju route yang sama `tours.index`/`tours.edit` dengan query `?type=`. Field yang spesifik per tipe (`vehicle`/`with_driver` untuk Rental, `route_from`/`airline`/`pnr` untuk Ticketing, `event_type`/`venue_name` untuk MICE, `hotel_name`/`check_in`/`check_out` untuk Hotel, dst.) tidak punya kolom sendiri — semua disimpan longgar di kolom `details` (JSON, cast `array`). Labelnya didefinisikan di `Tour::DETAIL_LABELS` (backend, dipakai untuk render PDF quotation) dan punya cermin di frontend `resources/js/lib/inquiryTypes.js` (`TYPE_FIELDS`) — **dua tempat ini harus disinkronkan manual**, tidak ada validasi otomatis yang menjaga keduanya tetap selaras.

### Kode tour & katalog paket (M7)

Kode tour dibangkitkan otomatis saat dibuat: `WM-<tahun>-<kode tipe>-NNNN`. Kode tipe berasal dari `Tour::resolveTypeCode()` — tipe `tour` dipecah menurut arah (`11` inbound, `12` outbound), tipe lain punya kode tetap di `Tour::TYPE_CODES` (`13` rental, `14` guide, `15` mice, `16` hotel, `17` document, `18` ticketing). Nomor invoice memakai kode tipe yang sama sehingga kode tour dan invoice selalu sejalan (lihat [invoice.md](invoice.md)).

Saat membuat inquiry baru (`Tours/Create.vue`), sales memilih **Sumber Inquiry** (`inquiry_source`: `website`/`external`). Kalau sumbernya `website` dan tipenya `tour`, sales bisa memilih dari **katalog paket aktif** (`tour_packages`, difilter per kategori Manado/Nasional/Internasional) — memilih paket hanya mengisi `package_id` dan judul tour secara otomatis, **bukan** item produk atau harga; sumber `external` mengosongkan pilihan paket.

### Pipeline status tour

Satu kolom `status` (bukan state machine terpisah per tipe) mengalir:

```
inquiry → quotation_draft → quotation_sent → follow_up → negotiation → confirmed
                                                                       ↘ cancelled (dari status manapun)
```

Setiap perubahan status otomatis dicatat ke `tour_histories`. Dua efek samping penting saat status berubah ke `confirmed`:
- Quotation item yang sudah disetujui customer otomatis dikonversi jadi `tour_items` riil (`TourController::convertApprovedQuotationItems` — lihat [quotation.md](quotation.md)).
- Tugas **Booking** otomatis dibuat satu per supplier (`TourController::generateBookings`) — item tour dikelompokkan lewat `tour_items.product.supplier_id`, kategori diambil dari tipe produk terbanyak dalam grup, `est_cost` dari `Σ line_cost`. Booking ini muncul di menu Operasional → Booking untuk dieksekusi tim `operation` — lihat [booking.md](booking.md).

Invoice **tidak** lagi dibuat otomatis saat confirmed — sales membuat invoice manual dari panel Invoice (lihat [invoice.md](invoice.md)).

Setiap tour baru otomatis dapat **reminder follow-up H+1** (`TourController::createAutoReminder`), dan tiap kali status berubah (kecuali ke `confirmed`/`cancelled`) reminder lama ditutup lalu reminder baru H+1 dibuat untuk pemilik tour (`handleStatusChangeReminder`) — detail penuh di [reminder-followup.md](reminder-followup.md).

### Item produk & snapshot harga

`tour_items` adalah baris produk (hotel/transport/guide/dll) yang di-**snapshot** dari `products` saat ditambahkan (`TourItem::fromProduct()` menyalin `unit_cost`/`unit_sell`/`currency`/`product_type` — bukan referensi live). `line_cost`/`line_sell` adalah **stored generated column** MySQL: `qty * nights * unit_cost` / `qty * nights * unit_sell` — dihitung database, bukan PHP/JS. Lihat invarian snapshot & generated column di [ikhtisar-proyek.md §3](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).

### Profit "perkiraan" vs "aktual"

Profit perkiraan tour (`Tour::profit`/`margin`, accessor — lihat invarian "profit = query" di [ikhtisar-proyek.md §3](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa)) punya dua rumus berbeda tergantung state (`Tour::usesInvoiceProfit()`):
- **Sebelum invoice disetujui** (atau tipe bukan `tour`): `total_sell = Σ tour_items.line_sell`, `total_cost = Σ tour_items.line_cost` → profit = selisih item.
- **Tipe `tour` dengan invoice yang sudah disetujui**: `total_sell` diganti jadi `Σ total_idr` invoice (tagihan riil ke customer), `total_cost` diganti jadi `Σ line_cost` dari item invoice (Rincian Profit — lihat [invoice.md](invoice.md)). Begitu ada invoice resmi, itulah angka jual yang benar, bukan estimasi awal.

Profit "aktual" (`actual_cost`/`actual_profit`/`cost_variance`) berbasis `bills` (AP riil yang sudah dicatat akuntan) vs `total_cost` — dipakai untuk membandingkan estimasi vs realisasi, lihat [keuangan-ar-ap.md](keuangan-ar-ap.md).

### Itinerary (tipe `tour`)

Dua bentuk paralel: `tour_itinerary_days` (rencana harian) dan `tour_itinerary_hours` (jadwal per-jam). Bisa diimpor dari teks bebas (`itinerary.import`) atau upload PDF siap pakai (`itinerary.pdf.upload`) sebagai pengganti input manual.

### Penugasan lapangan & riwayat

`assignments` — guide/driver/tour_leader yang ditugaskan ke tour (`role`, `person_name`, `phone`, `vehicle`, `pickup_time`). Ini yang menentukan siapa melihat tour di **My Jobs** — bukan lewat `users` langsung, field user dicocokkan lewat `person_name`/kontak, bukan `user_id` (assignment tidak selalu terhubung ke akun sistem). Detail lengkap di [penugasan-lapangan.md](penugasan-lapangan.md).

`tour_histories` — log semua perubahan status + catatan sistem (bill dibuat, invoice approved, dst.), ditampilkan sebagai timeline read-only di panel History, hanya `store`/`destroy` untuk catatan manual sales.

### Guest name/phone (customer tipe Buyer)

Kalau customer tour bertipe `buyer` (travel agent yang membeli tour, bukan wisatawan langsung), `tour.guest_name`/`guest_phone` wajib diisi saat pembuatan inquiry — ini nama tamu sebenarnya yang dilihat tim lapangan (guide/driver/My Jobs/Manifest publik), sementara identitas buyer (agent) tetap yang tertagih di invoice. `Tour::maskCustomerForField()` melakukan penggantian ini sebelum data sampai ke halaman lapangan — lihat [customer.md](customer.md) dan [penugasan-lapangan.md](penugasan-lapangan.md).

### Model data (ringkas)

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `tours` | `belongsTo Customer`, `belongsTo TourPackage`, `hasMany` (items, quotationItems, assignments, itineraryDays/Hours, histories, invoices, bookings, bills, costRequests, reminders) | `code` auto (`WM-<tahun>-<kode tipe>-NNNN`), `details`/`pricing` JSON, `budget` decimal, `created_by` (kepemilikan — lihat §4) |
| `tour_items` | `belongsTo Tour`, `belongsTo Product` | Snapshot harga; `line_cost`/`line_sell` generated |
| `quotation_items` | `belongsTo Tour`, `belongsTo Product` | `status`: proposed/approved/rejected — lihat [quotation.md](quotation.md) |
| `assignments` | `belongsTo Tour`, `belongsTo User` (opsional) | `role`: guide/driver/tour_leader |
| `tour_histories` | `belongsTo Tour` | append-only log |
| `tour_packages` | `hasMany Tour` | katalog paket aktif (M7), murni referensi judul/durasi |

Tabel `invoices`/`invoice_items`/`cost_requests` (Rincian Profit, biaya tambahan) ada di [invoice.md](invoice.md); `mice_templates` ada di [mice-template.md](mice-template.md).

### Route & Controller

| Route | Controller | Peran |
|---|---|---|
| `tours.index/store/edit/update/destroy` | `TourController` | admin, sales |
| `tours.email.send`, `tours.histories.*` | `TourEmailController`, `TourHistoryController` | admin, sales |
| `tours.itinerary.*` | `TourItineraryController` | admin, sales |
| `tour-items.*` | `TourItemController` | admin, sales |
| `assignments.*` | `AssignmentController` | admin, sales |

Route Quotation, Invoice, Cost Request, dan MICE Template ada masing-masing di berkas fitur terkait.

### Halaman & Komponen (UI)

- **`Tours/Index.vue`** — daftar per tipe (query `?type=`), filter status/tanggal/sales/pencarian bebas, paginasi 25/halaman. Mengikuti pola kartu+tabel standar — lihat [pola-ui-desain.md](../referensi/pola-ui-desain.md).
- **`Tours/Create.vue`** — form inquiry baru, field dinamis sesuai `type` (lewat `inquiryTypes.js`), pemilihan sumber inquiry & katalog paket.
- **`Tours/Edit.vue`** — halaman kerja utama, **satu halaman panjang** berisi panel bertumpuk (bukan tab terpisah), urutannya persis: `HeaderPanel` → `ItemsPanel` → `InvoicesPanel` (hanya kalau `status === 'confirmed'`) → `CostRequestsPanel` (idem) → `OperasionalPanel` (assignment + link manifest) → `ItineraryPanel` (hanya tipe `tour`) → `QuotationPanel` → `QItemsPanel` → `MiceTemplatePanel` (hanya tipe `mice`) → `HistoryPanel`, dengan `CostingPanel` (ringkasan profit) sebagai sidebar/panel terpisah di luar urutan utama.
- Komponen di `resources/js/Components/Tours/*.vue` masing-masing mengelola form/state sendiri lewat Inertia `useForm` + `router.patch(..., { preserveScroll: true })` — pola submit parsial khas app ini supaya tidak reload seluruh halaman panjang tsb.

## 3. Keterkaitan

Mengubah modul Tour berdampak luas — ini "pusat gravitasi" ERP:

- **Quotation** ([quotation.md](quotation.md)) — `quotation_items` menempel ke tour, item approved dikonversi jadi `tour_items` saat confirmed.
- **Invoice** ([invoice.md](invoice.md)) — satu tour maksimal satu invoice; tipe `tour` yang invoice-nya approved mengganti sumber angka profit tour.
- **Booking** ([booking.md](booking.md)) — dibuat otomatis dari `tour_items` saat tour confirmed.
- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — profit aktual & biaya tambahan (Bill) mengalir dari sini.
- **Penugasan Lapangan** ([penugasan-lapangan.md](penugasan-lapangan.md)) — `assignments` menentukan siapa melihat tour di My Jobs/Manifest.
- **Customer** ([customer.md](customer.md)) — sumber `customer_id`; tipe `buyer` memicu masking guest name/phone.
- **Produk** ([produk.md](produk.md)) — sumber snapshot `tour_items`/`quotation_items`.
- **Reminder & Follow-up** ([reminder-followup.md](reminder-followup.md)) — reminder H+1 otomatis mengikuti siklus hidup tour.
- **MICE Template** ([mice-template.md](mice-template.md)) — khusus tipe `mice`.

Mengubah `Tour::TYPES`/`TYPE_CODES`/`DETAIL_LABELS` berarti juga mengubah kode tour, kode invoice, dan `inquiryTypes.js` — periksa ketiganya sekaligus.

## 4. Batasan & jebakan ⚠️

- **Snapshot harga, bukan referensi live.** Mengubah harga produk tidak boleh mengubah total tour lama yang sudah dibuat — lihat invarian di [ikhtisar-proyek.md §3.1](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).
- **`line_cost`/`line_sell` adalah generated column MySQL** (`qty * nights * unit_*`) — jangan diisi manual dari PHP/JS, dan sistem mewajibkan **MySQL 8+**. Lihat [ikhtisar-proyek.md §3.3](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).
- **Kepemilikan tour per sales — kode ada, migrasi belum jalan di production.** `TourController::edit/update/destroy` sudah menjaga lewat `Tour::isAccessibleBy()` (admin selalu boleh; sales boleh kalau `created_by` miliknya atau `null`), tapi kolom `tours.created_by` **belum di-migrate ke production** — sampai saat itu semua tour lama otomatis dianggap "tanpa pemilik" (`created_by` null) sehingga tetap terbuka untuk semua sales. Jangan asumsikan pembatasan ini sudah aktif di production tanpa memverifikasi migrasi sudah jalan. Detail lengkap: [rencana/tour-ownership.md](../rencana/tour-ownership.md).
- **Guard kepemilikan hanya di entry-point `edit`/`update`/`destroy`.** Sub-route per-tour (items, invoice, itinerary, cost-requests, quotation-items, assignments, dll.) hanya dijaga middleware role `admin,sales` di level route group — **tidak** ada pengecekan `isAccessibleBy` sendiri di controller-controller itu. Ini disengaja sebagai batasan MVP (dicatat di rencana), bukan celah yang belum diketahui — tapi tetap berarti sales bisa mengubah sub-resource tour milik sales lain lewat request langsung ke sub-route meski tidak bisa membuka halaman edit-nya.
- **Field `details` per tipe harus disinkronkan manual** antara `Tour::DETAIL_LABELS` (PHP) dan `inquiryTypes.js` (Vue) — tidak ada validasi otomatis yang menjaga keduanya tetap selaras; field baru yang lupa ditambahkan ke salah satu sisi akan hilang diam-diam (tidak tampil di PDF, atau tidak tampil di form).

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP (M1–M7 selesai seluruhnya). Kepemilikan tour per akun sales (agar tiap sales hanya melihat tour miliknya, admin tetap melihat semua) sudah selesai & terverifikasi di kode tapi migrasinya **belum dijalankan di production** — sampai saat itu, semua sales masih melihat & bisa mengedit semua tour. Lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

- [pola-ui-desain.md](../referensi/pola-ui-desain.md) — fondasi UI/warna/layout bersama
- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — invarian lintas-fitur (snapshot harga, profit = query, generated column, pipeline status)
- [rencana/tour-ownership.md](../rencana/tour-ownership.md) — rencana & status implementasi kepemilikan tour per sales
- [quotation.md](quotation.md), [invoice.md](invoice.md), [mice-template.md](mice-template.md) — sub-fitur yang menempel ke tour
- [booking.md](booking.md), [penugasan-lapangan.md](penugasan-lapangan.md), [reminder-followup.md](reminder-followup.md), [customer.md](customer.md), [produk.md](produk.md), [keuangan-ar-ap.md](keuangan-ar-ap.md) — fitur terkait (lihat §3)
