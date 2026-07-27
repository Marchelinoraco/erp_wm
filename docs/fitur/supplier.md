# Supplier

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [produk.md](produk.md), [booking.md](booking.md), [channel-manager-agent.md](channel-manager-agent.md), [customer.md](customer.md)

## 1. Ringkasan

Data master pemasok — sumber `supplier_id` untuk produk, titik kumpul Booking dan Bill per pemasok, sekaligus tempat mengaktifkan akun Travel Agent eksternal (checkbox di form Supplier yang membuat akun login `role=travel_agent` untuk mengelola produk & harganya sendiri lewat Channel Manager). Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

### CRUD tanpa halaman Show

Route `suppliers` adalah resource yang **mengecualikan `show`** (`->except(['show'])`) — tidak ada halaman detail terpisah seperti `Customers/Show.vue`; semua interaksi lewat Index (list + aksi) dan Form (create/edit).

### Kategori `type` vs flag `is_travel_agent` — dua hal berbeda

- **`type`** (string, nullable) — kategori bisnis pemasok: `hotel`, `transport`, `guide`, `restaurant`, `attraction`, `agent`, `other`. Sekadar label kategori, tidak berefek fungsional lain.
- **`is_travel_agent`** (boolean, default `false`, kolom ditambahkan lewat migration terpisah) — flag fungsional: kalau `true`, supplier ini boleh login ke sistem dan mengelola produknya sendiri.

Keduanya independen — supplier bisa `type = 'agent'` tanpa `is_travel_agent`, atau sebaliknya. Dropdown filter tipe di `Suppliers/Index.vue` **tidak** punya opsi `agent`, dan peta `TYPE_LABELS`/`TYPE_COLORS` di file yang sama juga tidak mencakupnya — kalau tersimpan, badge di tabel tampil teks mentah `"agent"` warna abu-abu fallback, bukan label rapi seperti kategori lain.

### Mengaktifkan Travel Agent (akun login)

Saat form `is_travel_agent` dicentang, `account_email`/`account_password` wajib diisi (`required_if:is_travel_agent,true`, email unik di `users`). `SupplierController::store`/`update` membuat/mengubah baris `User` dengan `role = 'travel_agent'` dan `supplier_id` diarahkan ke supplier tsb (`Supplier::user()` → `hasOne(User::class)`).

- **Create**: `is_travel_agent = true` → buat `User` baru langsung.
- **Update**: checkbox tetap dicentang → update user yang ada (password hanya diganti kalau diisi ulang) atau buat baru kalau belum ada; checkbox **dimatikan** dan sebelumnya sudah ada akun → akun `User` tsb **langsung dihapus** (`$supplier->user->delete()`), tidak ada state "suspended".
- **Destroy** supplier: akun user terkait dihapus dulu (`$supplier->user?->delete()`) sebelum supplier-nya sendiri dihapus. Baik `Supplier` maupun `User` di sini **bukan** soft delete — hard delete langsung.

Akun travel agent ini login ke menu **"Produk Saya"** (`role:travel_agent`, `AgentProductController`) — hanya bisa mengelola produk milik supplier mereka sendiri, dijaga eksplisit: `abort_unless($product->supplier_id === $request->user()->supplier_id, 403)`.

### Channel Manager — review harga dari travel agent

`ChannelManagerController::index` hanya menampilkan supplier dengan `is_travel_agent = true` beserta produk & harga periodenya — tempat admin/sales menyetujui/menolak harga modal yang diajukan travel agent. Detail penuh alur harga produk ada di [produk.md](produk.md) dan [channel-manager-agent.md](channel-manager-agent.md); supplier di sini hanya jadi filter "siapa yang punya akses portal".

### Titik temu dengan modul lain

- `Product::supplier()` — tiap produk terikat satu supplier (nullable, `nullOnDelete`).
- `TourBooking::supplier()` — tugas Booking otomatis dikelompokkan per `supplier_id` dari item tour saat status tour jadi `confirmed`. Lihat [booking.md](booking.md).
- `Bill::supplier()` — Bill (AP) tercatat per supplier, baik lewat `Bill::createMissingFromInvoice()` maupun Cost Request yang di-approve akuntan. Lihat [keuangan-ar-ap.md](keuangan-ar-ap.md).

### Model data & route

Tabel `suppliers`: `name` wajib, `type` (nullable, index), `is_travel_agent` (boolean default `false`), `contact_person`/`phone`/`email`/`notes` (nullable). `Supplier` (`$guarded = []`, cast `is_travel_agent` → `boolean`) — relasi `products()` (`hasMany`), `user()` (`hasOne`). `SupplierController`, route `Route::resource('suppliers', ...)->except(['show'])`, `middleware('role:admin,sales')`. `index` paginasi 20/halaman, filter `search` (nama) + `type` (exact), `withCount('products')`, eager-load `user:id,name,email,supplier_id`.

## 3. Keterkaitan

- **Produk** ([produk.md](produk.md)) — sumber `supplier_id`; approval harga travel agent lewat Channel Manager.
- **Booking** ([booking.md](booking.md)) — dikelompokkan per supplier saat tour `confirmed`.
- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — `Bill` tercatat per supplier.
- **Channel Manager & Produk Agent** ([channel-manager-agent.md](channel-manager-agent.md)) — gerbang akses portal "Produk Saya".
- **Customer** ([customer.md](customer.md)) — konsep "travel agent" yang berlawanan arah, lihat [customer.md §2](customer.md#2-cara-kerja-as-built).

## 4. Batasan & jebakan ⚠️

- **`type = 'agent'` (kategori) vs `is_travel_agent = true` (akun login) sering rancu** — keduanya independen secara data. Juga jangan disamakan dengan `Customer.type = 'buyer'` ([customer.md](customer.md)): supplier travel agent **menjual** produk ke WM, customer buyer **membeli** tour dari WM — arah bisnisnya berlawanan meski sama-sama disebut "travel agent".
- **Dropdown filter & badge tipe di `Suppliers/Index.vue` tidak mencakup `agent`** — lihat §2; supplier dengan `type = 'agent'` tampil tanpa styling kategori dan tidak bisa difilter eksplisit lewat dropdown.
- **Menonaktifkan `is_travel_agent` saat edit langsung menghapus akun `User`** — bukan menonaktifkan, akun hilang permanen begitu checkbox dimatikan.
- **Tidak ada halaman Show/riwayat untuk supplier** (beda dari Customer) — histori transaksi per supplier harus dilihat lewat modul Booking/Bill/Produk masing-masing.
- **Supplier & akun `User` travel agent-nya hard delete, bukan soft delete** — berbeda dari `Customer` yang sudah pakai `SoftDeletes` (lihat [customer.md §2](customer.md#2-cara-kerja-as-built)); menghapus supplier tidak bisa dipulihkan lewat DB, dan `products.supplier_id` yang masih menunjuk ke supplier terhapus jadi `null` (`nullOnDelete`).

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP.

## 6. Dokumen terkait

- [produk.md](produk.md) — relasi `supplier_id` & approval harga
- [booking.md](booking.md) — pengelompokan booking per supplier
- [keuangan-ar-ap.md](keuangan-ar-ap.md) — `Bill` per supplier
- [channel-manager-agent.md](channel-manager-agent.md) — portal & approval travel agent
- [customer.md](customer.md) — bedakan Supplier `is_travel_agent` vs Customer `buyer`
