# Modul: Suppliers

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Data master pemasok — sumber `supplier_id` untuk `products` (modul Products), titik kumpul untuk **Booking** dan **Bill** per pemasok di modul Penjualan/Keuangan (lihat [01-penjualan-tour.md](01-penjualan-tour.md)), dan sekaligus tempat mengaktifkan akun **Travel Agent eksternal** — pemasok yang diberi login sendiri untuk mengelola produk & harganya lewat portal "Produk Saya", direview oleh admin/sales lewat Channel Manager. Dikelola lewat `SupplierController`. Dipakai oleh **admin & sales**.

## Alur Bisnis

### 1. CRUD tanpa halaman Show

Route `suppliers` adalah resource yang **mengecualikan `show`** (`Route::resource('suppliers', ...)->except(['show'])`) — tidak ada halaman detail terpisah seperti `Customers/Show.vue`; semua interaksi terjadi lewat Index (list + aksi) dan Form (create/edit).

### 2. Kategori `type` vs flag `is_travel_agent` — dua hal berbeda

Supplier punya dua atribut yang gampang tertukar:

- **`type`** (string, nullable) — kategori bisnis pemasok: `hotel`, `transport`, `guide`, `restaurant`, `attraction`, `agent`, `other`. Sekadar label kategori untuk pengelompokan produk, tidak berefek fungsional lain.
- **`is_travel_agent`** (boolean, default `false`, ditambahkan lewat migration terpisah `2026_06_09_090100_add_travel_agent_to_suppliers.php`) — flag fungsional: kalau `true`, supplier ini adalah **travel agent eksternal** yang boleh login ke sistem dan mengelola produknya sendiri.

Keduanya independen — sebuah supplier bisa saja `type = 'agent'` tanpa `is_travel_agent`, atau sebaliknya `is_travel_agent = true` dengan `type` apa pun (mis. `hotel`). **Catatan konsistensi UI**: opsi `type = 'agent'` ada di dropdown `Suppliers/Form.vue`, tapi peta label/warna `TYPE_LABELS`/`TYPE_COLORS` di `Suppliers/Index.vue` **tidak mencakup** `'agent'` — kalau tersimpan, badge di tabel akan menampilkan teks mentah `"agent"` dengan warna fallback abu (`TYPE_LABELS[s.type] ?? s.type`, `TYPE_COLORS[s.type] ?? 'bg-gray-100 text-gray-700'`), bukan label rapi seperti kategori lain.

### 3. Mengaktifkan Travel Agent (akun login)

Saat form `is_travel_agent` dicentang, field `account_email`/`account_password` wajib diisi (`required_if:is_travel_agent,true`, email harus unik di tabel `users`). `SupplierController::store`/`update` lalu membuat/mengubah baris `User` dengan `role = 'travel_agent'` dan `supplier_id` diarahkan ke supplier tsb (relasi `Supplier::user()` → `hasOne(User::class)`, FK `users.supplier_id` ditambahkan lewat migration `2026_06_09_090000_add_travel_agent_role_and_supplier_to_users.php` yang juga menambah `'travel_agent'` ke enum `role`).

- **Create**: `is_travel_agent = true` → buat `User` baru langsung.
- **Update**: kalau checkbox tetap dicentang → update user yang ada (password hanya diganti kalau diisi ulang, kosong = tidak berubah) atau buat baru kalau belum ada; kalau checkbox **dimatikan** dan sebelumnya sudah ada akun → akun user tsb **dihapus** (`$supplier->user->delete()`).
- **Destroy** supplier: akun user terkait dihapus dulu (`$supplier->user?->delete()`) sebelum supplier-nya sendiri dihapus.

Akun travel agent ini yang login ke menu **"Produk Saya"** (`role:travel_agent` middleware, `AgentProductController`) — di sana mereka hanya bisa mengelola produk milik supplier mereka sendiri, dijaga eksplisit di kode: `abort_unless($product->supplier_id === $request->user()->supplier_id, 403)`.

### 4. Channel Manager — review harga dari travel agent

`ChannelManagerController::index` hanya menampilkan supplier dengan `is_travel_agent = true` beserta produk & daftar harga periodenya. Ini tempat admin/sales **menyetujui atau menolak** harga modal (`cost`) yang diajukan travel agent lewat portal "Produk Saya" mereka — `approve` memindahkan `pending_cost` jadi `cost` resmi dan mengaktifkan produk (`is_active = true`); ada juga jalur approve/reject untuk harga per-periode (`ProductPrice`). Detail penuh alur harga produk ada di ranah modul Products, bukan di sini — supplier hanya jadi filter "siapa yang punya akses portal".

### 5. Titik temu dengan modul lain

Supplier tidak berdiri sendiri — ia jadi kunci pengelompokan di beberapa alur modul Penjualan/Keuangan (dijelaskan detail di [01-penjualan-tour.md](01-penjualan-tour.md), bukan diulang di sini):
- `Product::supplier()` — tiap produk terikat satu supplier (nullable, `nullOnDelete`).
- `TourBooking::supplier()` — tugas Booking otomatis dikelompokkan per `supplier_id` dari item tour saat status jadi `confirmed`.
- `Bill::supplier()` — Bill (AP) tercatat per supplier, baik lewat `Bill::createMissingFromInvoice()` (otomatis dari Rincian Profit invoice) maupun Cost Request yang di-approve akuntan.

## Model Data

Tabel `suppliers` (didefinisikan di `database/migrations/2026_06_06_000000_create_welcome_manado_core_tables.php`, kolom `is_travel_agent` ditambahkan lewat migration `2026_06_09_090100_add_travel_agent_to_suppliers.php`):

| Kolom | Tipe | Catatan |
|---|---|---|
| `name` | string | wajib |
| `type` | string, nullable, index | lihat §2 |
| `is_travel_agent` | boolean, default `false` | lihat §2 & §3 |
| `contact_person` | string, nullable | |
| `phone` | string, nullable | |
| `email` | string, nullable | |
| `notes` | text, nullable | |

`App\Models\Supplier`: `$guarded = []`, cast `is_travel_agent` → `boolean`. Relasi: `products()` → `hasMany(Product::class)`, `user()` → `hasOne(User::class)` (akun travel agent, lihat §3).

## Route & Controller

`SupplierController` (`app/Http/Controllers/SupplierController.php`), route `Route::resource('suppliers', ...)->except(['show'])`, `middleware('role:admin,sales')`.

| Method | Perilaku |
|---|---|
| `index` | Paginasi 20/halaman, filter `search` (nama, `LIKE`) + `type` (exact match), `withCount('products')`, eager-load `user:id,name,email,supplier_id` |
| `store`/`update` | Validasi `type` (7 nilai di §2), buat/kelola akun `User` bila `is_travel_agent` — lihat §3 |
| `edit` | Eager-load relasi `user` untuk pre-fill form akun |
| `destroy` | Hapus akun travel agent terkait lalu supplier, lihat §3 |

## Halaman & Komponen (UI)

- **`Suppliers/Index.vue`** — pola standar shadcn `<Table>`, filter ganda: search (debounce 400ms) + dropdown tipe (`Select`, opsi "Semua tipe" + 6 kategori — **tidak termasuk `agent`**, meski `agent` adalah opsi valid di Form, lihat §2). Badge tipe pakai kelas Tailwind literal via `TYPE_COLORS` map (pola umum app, bukan `<Badge>` shadcn). Kolom "Produk" menampilkan `products_count`. Aksi per baris (`RowActions`): Edit / Hapus — tidak ada "Riwayat" karena tidak ada halaman Show.
- **`Suppliers/Form.vue`** — satu form create & edit. Field dasar sama pola dengan Customers (nama, tipe, kontak, telepon, email, catatan), ditambah blok khusus **Travel Agent** (`rounded-lg border bg-muted/30`): checkbox `is_travel_agent` yang membuka sub-form email login + password saat dicentang (password wajib hanya saat create, opsional/kosongkan-jika-tidak-diubah saat edit).

## Yang Perlu Diperhatikan

- **`type = 'agent'` (kategori) vs `is_travel_agent = true` (akun login) sering rancu** — keduanya independen secara data, lihat §2. Juga jangan disamakan dengan `Customer.type = 'buyer'` di modul [05-customers](05-customers.md): supplier travel agent **menjual** produk ke WM, customer buyer **membeli** tour dari WM — arah bisnisnya berlawanan meski sama-sama disebut "travel agent".
- Dropdown filter tipe di `Suppliers/Index.vue` tidak punya opsi `agent`, dan peta label/warnanya juga tidak mencakup nilai itu — kalau ada supplier dengan `type = 'agent'`, tampilannya di tabel jadi teks mentah tanpa styling kategori (lihat §2), dan filter dropdown tidak menyediakan cara memilihnya secara eksplisit.
- Menonaktifkan `is_travel_agent` saat edit **langsung menghapus** akun `User` terkait (bukan sekadar menonaktifkan) — tidak ada state "suspended", begitu dimatikan akun hilang permanen.
- Tidak ada halaman Show/riwayat untuk supplier (beda dengan Customers) — untuk melihat histori transaksi per supplier, harus lewat modul Booking/Bill/Products masing-masing.
