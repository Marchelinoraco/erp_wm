# Modul: Produk & Harga Periode

> Bagian dari sistem ERP Welcome Manado. Rujuk [00-fondasi-desain.md](00-fondasi-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Data master untuk seluruh item yang bisa dijual di dalam tour/inquiry — hotel, transport, guide, restaurant, attraction, venue, equipment, produk agent, dan lainnya. Satu tabel `products` melayani semua tipe (mirip pola `tours` di modul Penjualan — satu model, kolom `type` yang membedakan). Dikelola oleh **admin & sales** lewat menu Data Master → Produk.

Produk dipakai lewat **snapshot**, bukan referensi live: saat sales menambahkan item ke tour, `TourItem::fromProduct()` menyalin `unit_cost`/`unit_sell`/`currency` dari produk ke baris `tour_items` (lihat [01-penjualan-tour.md](01-penjualan-tour.md) §2) — jadi perubahan harga produk di kemudian hari tidak mengubah tour yang sudah dibuat.

## Alur Bisnis

### 1. Harga dasar vs Periode Harga
Tiap produk punya harga dasar (`cost`/`sell` di tabel `products`) yang dipakai sebagai default saat produk ditambahkan ke tour. Selain itu produk bisa punya beberapa baris `product_prices` (relasi `hasMany`, diurutkan `start_date`) — harga alternatif untuk rentang tanggal tertentu, mis. "High Season 2026" dengan `cost`/`sell` berbeda dari harga dasar.

**Penting**: periode harga ini **murni referensi** — tidak ada kode yang otomatis memilih `product_prices` berdasarkan tanggal tour saat item ditambahkan. `TourItemController::store` selalu memakai `product.cost`/`product.sell` (harga dasar), bukan mencari periode yang cocok. Sales harus mengecek sendiri Periode Harga di halaman edit produk lalu menimpa `unit_cost`/`unit_sell` manual di panel Item tour kalau tanggal tour jatuh di periode khusus.

### 2. Varian produk (`group_label` + `grade`)
Produk yang merupakan "saudara" varian dari satu jenis layanan (mis. tiga pilihan menu makan siang: hemat/standar/premium) diberi `group_label` yang sama (nama grup bebas, mis. "Lunch Prasmanan") dan `grade` berbeda (`hemat`/`standar`/`premium`). Ini murni pengelompokan tampilan — dipakai picker QItems (panel Quotation Items di modul Penjualan) untuk menampilkan pilihan varian berdampingan. Produk tanpa `group_label`/`grade` (null) berarti bukan bagian dari grup varian mana pun.

### 3. Produk dari travel agent eksternal (approval harga)
Selain diinput manual oleh admin/sales, produk juga bisa berasal dari travel agent eksternal lewat menu "Produk Saya" (role `travel_agent`, `AgentProductController`/`AgentProductPriceController` — route terpisah `/agent/products*`). Perubahan harga yang mereka ajukan tidak langsung aktif: tersimpan sementara di `pending_cost`/`price_status` (kolom di `products`, ditambahkan lewat migration `add_price_approval_to_products`) atau di `product_prices.pending_cost`/`status` untuk periode baru, menunggu disetujui internal. Lihat [02-channel-manager-produk-agent.md](02-channel-manager-produk-agent.md) untuk alur approval harga dari travel agent secara lengkap — tidak dibahas ulang di sini.

### 4. Import massal via template CSV
Dua endpoint bantu di halaman Produk (tombol di header `Products/Index.vue`):
- **Referensi Supplier** (`products.template.suppliers`, `ProductController::exportSuppliers`) — mengekspor CSV daftar supplier aktif (`name`, `type`, kontak) selalu up-to-date dari tabel `suppliers`, supaya sales tahu ejaan `supplier_name` yang persis harus dipakai di template.
- **Template Produk** (`products.template.download`, `ProductController::downloadTemplate`) — mengunduh file statis `public/templates/template_produk.csv` (template kosong siap isi, bukan hasil generate dari data).

Kedua endpoint hanya menyediakan file unduhan — **tidak ada route upload/import** yang ditemukan di controller ini; proses isi-ulang dari CSV yang sudah diisi tampaknya dilakukan manual (di luar aplikasi) atau lewat fitur lain yang belum ada di controller ini.

### 5. Validasi input
`store`/`update` di `ProductController` memakai aturan validasi yang identik persis (tidak ada `FormRequest` terpisah, ditulis inline di kedua method):
- `name` wajib, maks 255 karakter.
- `type` wajib, harus salah satu dari 9 nilai tetap (lihat §Model Data).
- `supplier_id` opsional, harus ID supplier yang ada (`exists:suppliers,id`).
- `unit` wajib, salah satu `per_pax`/`per_unit`/`per_night`.
- `cost`/`sell` wajib, numerik, minimal 0 (produk gratis/0 diperbolehkan).
- `currency` wajib, tepat 3 karakter (kode ISO, tapi tidak divalidasi terhadap daftar tetap — form Vue membatasi pilihan ke IDR/USD/SGD/MYR lewat `Select`, namun backend menerima string 3-karakter apa pun).
- `group_label` opsional (maks 255), `grade` opsional (`hemat`/`standar`/`premium`).

## Model Data

| Tabel | Relasi kunci | Kolom penting |
|---|---|---|
| `products` | `belongsTo Supplier` (nullable), `hasMany ProductPrice` (`prices`, urut `start_date`) | `type` (hotel/transport/guide/restaurant/attraction/venue/equipment/agent/other, string bebas — bukan DB enum), `unit` (per_pax/per_unit/per_night), `cost`/`sell` decimal, `currency` char(3) default IDR, `is_active`, `group_label`+`grade` (varian), `pending_cost`/`price_status`/`price_submitted_by`/`price_updated_at` (approval agent) |
| `product_prices` | `belongsTo Product` | `label`, `start_date`/`end_date`, `cost`/`sell`, `pending_cost`/`status` (approval agent untuk periode), `is_active`, `notes` |

Kolom `type` produk **tidak** dibatasi enum di level database (`string` biasa) — daftar nilai yang valid hanya dijaga lewat validasi controller (`in:hotel,transport,guide,restaurant,attraction,venue,equipment,agent,other`) dan `SelectItem` di form Vue. `venue`/`equipment` ditambahkan belakangan untuk mendukung modul MICE.

`Product` (`$guarded = []`) dan `ProductPrice` (`$fillable` eksplisit) memakai strategi mass-assignment yang berbeda — `Product` mengizinkan semua kolom lewat `create()`/`update()` (aman di sini karena data selalu lewat `$request->validate()` dulu di controller), sementara `ProductPrice` membatasi eksplisit ke kolom yang relevan untuk periode harga.

## Route & Controller

| Route | Controller | Role |
|---|---|---|
| `products.index/create/store/edit/update/destroy` (resource, tanpa `show`) | `ProductController` | admin, sales |
| `products.template.download`, `products.template.suppliers` | `ProductController` | admin, sales |
| `product-prices.store/update/destroy` | `ProductPriceController` | admin, sales |
| `agent.products.*`, `agent.product-prices.*` | `AgentProductController`, `AgentProductPriceController` | travel_agent (lihat [02-channel-manager-produk-agent.md](02-channel-manager-produk-agent.md)) |
| `channel-manager.price.approve/reject`, `channel-manager.period.price` | `ChannelManagerController` | admin/sales (internal, approval harga agent) |

Route template download didefinisikan **sebelum** `Route::resource('products', ...)` di `routes/web.php` (~baris 96) — supaya path `/products/template/download` tidak tertangkap oleh route resource `products/{product}`.

## Halaman & Komponen (UI)

- **`Products/Index.vue`** — daftar produk dengan filter tipe (`Select`, 9 pilihan + "Semua tipe") dan pencarian nama (debounce 400ms lewat `watch`, `router.get(..., { preserveState: true, replace: true })`), tabel kolom Nama (+`group_label` sebagai subtext kecil), Tipe (badge), Supplier, Unit, Cost/Sell (format `fmtRp`), Status aktif (badge), Varian (badge warna per grade: hemat=emerald, standar=blue, premium=amber — lihat `GRADE_CFG`), aksi Edit/Hapus lewat `RowActions` dropdown. Paginasi 20/halaman (`ProductController::index`, `paginate(20)`). Ikut pola tabel standar di fondasi desain, tapi memakai komponen tabel shadcn (`Table`/`TableRow`/dst) — bukan div manual seperti sebagian modul lama. Hapus produk dikonfirmasi lewat helper `confirm()` (`@/lib/confirm`) sebelum `router.delete`.
- **`Products/Form.vue`** — satu halaman dipakai untuk tambah & edit (`isEdit = !!props.product`). Berisi form data dasar produk plus blok terpisah "Varian Harga" (dashed border, `bg-muted/30`) untuk `group_label`/`grade`. **Panel "Periode Harga" hanya muncul di mode edit** (produk baru harus disimpan dulu sebelum bisa menambah periode) — daftar periode `divide-y`, tiap baris bisa masuk mode edit inline (form muncul menggantikan tampilan baris) atau dihapus langsung, dengan form tambah periode baru bisa di-toggle tampil/sembunyi di atas daftar. Sentinel value `'none'` dipakai untuk `supplier_id`/`grade` di `Select` (shadcn tidak mengizinkan value kosong), dikonversi balik ke string kosong sebelum submit.

Catatan pencarian: `ProductController::index` hanya mem-filter `name like %search%` — tidak mencari di kolom `notes`, nama supplier, atau `group_label`.

## Yang Perlu Diperhatikan

- **Periode harga tidak otomatis dipakai** — sudah dijelaskan di §1: sales wajib cek manual dan override `unit_cost`/`unit_sell` di panel Item tour kalau tanggal tour jatuh di periode khusus. Tidak ada logika pencocokan tanggal otomatis di backend.
- **Tidak ada halaman "Show" produk** — resource route mengecualikan `show`; detail hanya bisa dilihat/diedit lewat halaman Form (edit).
- **Tipe produk bukan DB enum** — nilai valid hanya dijaga di dua tempat (validasi controller PHP + `SelectItem` Vue), mirip pola `Tour::DETAIL_LABELS` vs `inquiryTypes.js` di modul Penjualan — berpotensi tidak sinkron kalau salah satu diubah tanpa yang lain.
- **Hapus produk** (`ProductController::destroy`) langsung `delete()` tanpa pengecekan apakah produk sudah dipakai di `tour_items`/`invoice_items`/`quotation_items` — karena field-field itu snapshot (bukan foreign key yang wajib), tour lama tetap aman menampilkan datanya, tapi kalau ada tempat lain yang query balik lewat `product_id` (mis. relasi `belongsTo Product` di `tour_items`), relasi tersebut akan jadi null setelah produk dihapus.
