# Modul: Customers

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Data master pelanggan — satu tabel `customers` sederhana (tanpa relasi ke tabel master lain) yang jadi sumber `customer_id` untuk tour di modul [penjualan-tour.md](../fitur/penjualan-tour.md). Dikelola lewat CRUD standar (`CustomerController`), ditambah satu halaman khusus **Riwayat** (`Customers/Show.vue`) yang merangkum semua inquiry/tour milik satu customer beserta timeline perubahan statusnya. Dipakai oleh **admin & sales**.

## Alur Bisnis

### 1. Empat tipe customer

Kolom `type` (string, default `direct`) dibatasi lewat validasi controller ke 4 nilai: `agent`, `corporate`, `direct`, `buyer`. Komentar di migration hanya menyebut tiga tipe pertama (`agent|corporate|direct`) — tipe `buyer` ditambahkan belakangan tanpa migration baru (kolom `type` cukup `string` biasa, tidak ada enum DB, jadi bisa diperluas lewat validasi PHP saja).

Label & warna badge (`Customers/Index.vue`, `Customers/Show.vue`):

| `type` | Label UI | Arti |
|---|---|---|
| `direct` | Direct | Wisatawan langsung (default) |
| `agent` | Agent | Agen perantara umum |
| `corporate` | Korporat | Klien korporat |
| `buyer` | Buyer (Travel Agent) | Travel agent yang **membeli** tour dari Welcome Manado |

### 2. Tipe `buyer` — jangan tertukar dengan Supplier travel agent

Ini konsep yang sering rancu karena namanya mirip fitur di modul [07-suppliers](07-suppliers.md), tapi arahnya **kebalikan**:

- **Customer tipe `buyer`** = travel agent yang jadi pelanggan WM (membeli paket tour). Dikelola di sini, hanya baris data biasa — tidak punya akun login.
- **Supplier dengan `is_travel_agent = true`** = travel agent eksternal yang jadi pemasok WM (menjual produk ke WM), dan **punya akun login** sendiri untuk portal "Produk Saya" (lihat modul 07).

Efek `type === 'buyer'` di kode (`TourController::store`/`update`, `HeaderPanel.vue`, `Tours/Create.vue`): saat customer tour yang dipilih bertipe `buyer`, field `tour.guest_name` menjadi **wajib diisi** (`Rule::requiredIf`) — ini nama tamu asli yang berangkat, bukan nama agen. `Tour::maskCustomerForField()` lalu mengganti relasi `customer` tour tsb dengan objek `Customer` sintetis berisi `guest_name`/`guest_phone` (mewarisi `country` dari customer asli) sebelum data dikirim ke halaman lapangan (My Jobs, manifest publik) — supaya tim lapangan hanya melihat nama tamu, sementara identitas buyer (agent) yang sebenarnya tertagih tetap yang tampil di invoice/panel sales.

### 3. Halaman Riwayat (`customers.show`)

Satu-satunya halaman non-CRUD di modul ini. Menampilkan:
- Info customer + tombol edit.
- 4 kartu statistik: total inquiries, jumlah confirmed, conversion rate (confirmed/total), total revenue estimasi (`Σ tour_items.line_sell` dari **semua** tour customer tsb — via `withSum('items as total_sell', 'line_sell')` di `CustomerController::show`, jadi ini estimasi item, bukan angka invoice riil).
- Tabel daftar tour milik customer (kode, judul, status, pax, tanggal) dengan tombol "Buka" ke `tours.edit`.
- Timeline riwayat (`tour_histories` gabungan dari semua tour customer tsb, diurutkan terbaru dulu), tiap entri menampilkan kode tour asal + badge status snapshot + ikon per jenis histori (status pipeline, `revision`, `note`, `call`, `meeting`, `email`).

### 4. Hapus customer

`CustomerController::destroy` hanya `delete()` langsung, tanpa cek relasi. Migration `tours.customer_id` didefinisikan `nullOnDelete()` — jadi tour yang sudah terlanjur dibuat **tidak ikut terhapus**, hanya `customer_id`-nya jadi `null` (tour tetap ada tapi kehilangan referensi ke customer).

## Model Data

Tabel `customers` (didefinisikan di `database/migrations/2026_06_06_000000_create_welcome_manado_core_tables.php`, tidak ada migration lanjutan yang menyentuh tabel ini):

| Kolom | Tipe | Catatan |
|---|---|---|
| `name` | string | wajib |
| `type` | string, default `direct`, index | lihat §1 |
| `country` | string, nullable, index | mis. Korea, Malaysia, Singapore, Indonesia |
| `contact_person` | string, nullable | |
| `phone` | string, nullable | |
| `email` | string, nullable | |
| `notes` | text, nullable | |

`App\Models\Customer`: `$guarded = []` (mass-assignable penuh), satu relasi `tours()` → `hasMany(Tour::class)`. Tidak ada cast, tidak ada soft delete.

## Route & Controller

`CustomerController` (`app/Http/Controllers/CustomerController.php`), route `Route::resource('customers', ...)` **lengkap** (tidak mengecualikan `show`, beda dari Suppliers/Products) di `routes/web.php`, dijaga `middleware('role:admin,sales')`.

| Method | Perilaku |
|---|---|
| `index` | Paginasi 20/halaman, pencarian bebas (`name` atau `contact_person`, `LIKE`), `withCount('tours')`, urut terbaru |
| `store`/`update` | Validasi `type` harus salah satu dari 4 nilai di §1 |
| `show` | Halaman Riwayat — lihat §3 |
| `destroy` | Hapus langsung, lihat §4 |

## Halaman & Komponen (UI)

- **`Customers/Index.vue`** — beda dari mayoritas index lain di app ini (yang pakai div biasa), halaman ini pakai komponen shadcn `<Table>` langsung. Search input dengan debounce 400ms. Badge tipe pakai shadcn `<Badge :variant="...">` (bukan kelas Tailwind literal `bg-*-100`), dengan pemetaan `TYPE_VARIANTS` (`agent`/`buyer` → `default`, `corporate` → `secondary`, `direct` → `outline`). Aksi per baris lewat `RowActions` dropdown: Riwayat / Edit / Hapus.
- **`Customers/Form.vue`** — satu form dipakai untuk create & edit (`isEdit = !!props.customer`), field: nama, tipe (select 4 opsi), negara, kontak, telepon, email, catatan.
- **`Customers/Show.vue`** — halaman Riwayat, lihat §3. Layout dua kolom: tabel tour (kiri, `lg:col-span-3`) + timeline vertikal (kanan, `lg:col-span-2`) dengan garis penghubung dan dot ikon per jenis histori.

## Yang Perlu Diperhatikan

- Konstanta `TYPE_LABELS`/`TYPE_VARIANTS` didefinisikan ulang secara terpisah di `Index.vue` dan `Show.vue` (tidak di-share lewat satu file util) — kalau menambah tipe baru, keduanya harus diubah manual.
- Tipe `buyer` di kolom `type` yang berbentuk `string` biasa (bukan DB enum) berarti valid/tidaknya nilai sepenuhnya bergantung pada validasi Laravel di controller — tidak ada constraint di level database.
- Total revenue di halaman Riwayat adalah estimasi dari `tour_items` (harga jual item), **bukan** angka invoice riil — beda dengan cara profit tour tipe `tour` dihitung setelah invoice approved (lihat §2 di [../fitur/penjualan-tour.md](../fitur/penjualan-tour.md)).
