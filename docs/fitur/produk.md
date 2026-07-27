# Produk

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [supplier.md](supplier.md), [channel-manager-agent.md](channel-manager-agent.md)

## 1. Ringkasan

Data master untuk seluruh item yang bisa dijual di dalam tour (hotel, transport, guide, restaurant, attraction, venue, equipment, dll) dalam satu tabel `products`, dengan harga dasar (`cost`/`sell`) yang dipakai lewat snapshot saat ditambahkan ke tour — bukan referensi live. Mendukung harga per periode (`product_prices`, murni referensi manual, tidak otomatis dipilih berdasarkan tanggal tour) dan varian produk (`group_label`+`grade`). Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

### Satu tabel untuk semua tipe produk

Pola sama seperti `tours`: satu model `Product`, kolom `type` yang membedakan (`hotel`/`transport`/`guide`/`restaurant`/`attraction`/`venue`/`equipment`/`agent`/`other` — 9 nilai, bukan DB enum, hanya dijaga lewat `in:...` di validasi `ProductController` + `SelectItem` di `Products/Form.vue`). `venue`/`equipment` ditambahkan belakangan untuk mendukung modul MICE.

### Harga dasar vs Periode Harga — snapshot, bukan referensi live

`cost`/`sell` di `products` adalah harga dasar. Saat sales menambahkan produk ke tour, `TourItem::fromProduct()` menyalin `unit_cost`/`unit_sell`/`currency`/`product_type` ke baris `tour_items` — **snapshot**, bukan referensi live. Lihat invarian ini di [ikhtisar-proyek.md §3.1](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).

Selain harga dasar, produk bisa punya beberapa baris `product_prices` (`hasMany`, urut `start_date`) — harga alternatif untuk rentang tanggal tertentu (mis. "High Season 2026"). **Periode ini murni referensi**: `TourItemController::store` selalu memakai `product.cost`/`product.sell` (harga dasar), tidak ada kode yang mencocokkan tanggal tour ke `product_prices` secara otomatis. Sales harus cek sendiri Periode Harga di halaman edit produk lalu menimpa `unit_cost`/`unit_sell` manual di panel Item tour kalau tanggal tour jatuh di periode khusus.

### Varian produk (`group_label` + `grade`)

Produk yang jadi "saudara" varian dari satu jenis layanan (mis. tiga pilihan menu makan siang: hemat/standar/premium) diberi `group_label` yang sama (nama grup bebas) dan `grade` berbeda (`hemat`/`standar`/`premium`). Murni pengelompokan tampilan, dipakai picker QItems (panel Quotation Items) untuk menampilkan pilihan varian berdampingan. Produk tanpa `group_label`/`grade` (null) bukan bagian grup varian mana pun.

### Produk dari travel agent eksternal (approval harga)

Selain diinput manual oleh admin/sales, produk juga bisa berasal dari travel agent eksternal lewat "Produk Saya" (`AgentProductController`/`AgentProductPriceController`, route `/agent/products*`, role `travel_agent`). Perubahan harga yang mereka ajukan tidak langsung aktif — tersimpan sementara di `pending_cost`/`price_status` (kolom di `products`) atau `product_prices.pending_cost`/`status` untuk periode baru, menunggu disetujui lewat Channel Manager (`ChannelManagerController::approve`/`reject` — memindahkan `pending_cost` jadi `cost` resmi dan mengaktifkan produk). Detail alur approval penuh ada di [channel-manager-agent.md](channel-manager-agent.md), bukan diulang di sini.

### Import massal via template CSV

Dua endpoint bantu di header `Products/Index.vue`: **Referensi Supplier** (`products.template.suppliers`, `ProductController::exportSuppliers`) mengekspor CSV daftar supplier aktif supaya sales tahu ejaan `supplier_name` yang persis harus dipakai di template; **Template Produk** (`products.template.download`) mengunduh file statis `public/templates/template_produk.csv`. Keduanya hanya endpoint unduhan — **tidak ada route upload/import** di controller ini; proses isi-ulang dari CSV tampaknya manual di luar aplikasi.

### Validasi input

`store`/`update` di `ProductController` memakai aturan identik (inline, tanpa `FormRequest`): `name` wajib maks 255, `type` wajib salah satu dari 9 nilai di atas, `supplier_id` opsional (`exists:suppliers,id`), `unit` wajib (`per_pax`/`per_unit`/`per_night`), `cost`/`sell` wajib numerik min 0 (produk gratis diperbolehkan), `currency` wajib tepat 3 karakter (form Vue membatasi ke IDR/USD/SGD/MYR, tapi backend menerima string 3-karakter apa pun), `group_label` opsional maks 255, `grade` opsional (`hemat`/`standar`/`premium`).

### Model data & route

`Product` (`$guarded = []`, cast `cost`/`sell`/`pending_cost` → `decimal:2`, `is_active` → `boolean`) — `belongsTo Supplier` (nullable), `hasMany ProductPrice`. `ProductPrice` pakai `$fillable` eksplisit (beda strategi mass-assignment dari `Product`). Route `products.*` resource **tanpa `show`**, `middleware('role:admin,sales')`; `product-prices.store/update/destroy` lewat `ProductPriceController` (role sama). Route template download didefinisikan sebelum `Route::resource('products', ...)` supaya `/products/template/download` tidak tertangkap route `products/{product}`.

## 3. Keterkaitan

- **Penjualan Tour** ([penjualan-tour.md](penjualan-tour.md)) — sumber snapshot `tour_items`/`quotation_items`.
- **Supplier** ([supplier.md](supplier.md)) — `Product::supplier()` (nullable, `nullOnDelete`).
- **Channel Manager & Produk Agent** ([channel-manager-agent.md](channel-manager-agent.md)) — approval harga dari travel agent eksternal.

## 4. Batasan & jebakan ⚠️

- **Snapshot, bukan referensi live** — mengubah harga produk tidak boleh mengubah total tour lama yang sudah dibuat. Lihat invarian di [ikhtisar-proyek.md §3.1](../ikhtisar-proyek.md#3-konsep-inti-wajib-dipahami--jangan-sampai-lupa).
- **Periode harga tidak otomatis dipakai** — tidak ada logika pencocokan tanggal di backend; sales wajib cek manual & override `unit_cost`/`unit_sell` di panel Item tour. Seasonal pricing terjadwal penuh belum dibangun — lihat [ikhtisar-proyek.md §9](../ikhtisar-proyek.md#9-di-luar-cakupan-belum--tidak-dibangun).
- **Tidak ada halaman "Show" produk** — resource route mengecualikan `show`; detail hanya lewat halaman Form (edit).
- **Tipe produk bukan DB enum** — nilai valid hanya dijaga di validasi controller PHP + `SelectItem` Vue, berpotensi tidak sinkron kalau salah satu diubah tanpa yang lain (pola sama dengan `Tour::DETAIL_LABELS` vs `inquiryTypes.js`, lihat [penjualan-tour.md §4](penjualan-tour.md#4-batasan--jebakan-️)).
- **Hapus produk langsung `delete()` tanpa cek pemakaian**, dan `Product` **tidak** memakai `SoftDeletes` (beda dari `Customer`, lihat [customer.md §2](customer.md#2-cara-kerja-as-built)) — jadi ini hard delete sungguhan. Karena `tour_items`/`invoice_items`/`quotation_items` menyimpan harga lewat snapshot (bukan bergantung ke produk), tour lama tetap aman menampilkan datanya, tapi relasi `belongsTo(Product)` di baris-baris itu akan menjadi `null` setelah produk dihapus.

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP. Harga periode masih perlu dicek & di-override manual oleh sales (tidak ada pencocokan tanggal otomatis); seasonal pricing terjadwal penuh belum dibangun (lihat [ikhtisar-proyek.md §9](../ikhtisar-proyek.md#9-di-luar-cakupan-belum--tidak-dibangun)).

## 6. Dokumen terkait

- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — invarian snapshot harga (§3) & cakupan seasonal pricing yang belum dibangun (§9)
- [penjualan-tour.md](penjualan-tour.md) — pemakaian snapshot di `tour_items`
- [supplier.md](supplier.md) — relasi `supplier_id`
- [channel-manager-agent.md](channel-manager-agent.md) — approval harga travel agent
