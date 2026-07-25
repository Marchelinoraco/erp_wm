# Modul: Kelola Akun

> Bagian dari sistem ERP Welcome Manado. Rujuk [00-fondasi-desain.md](00-fondasi-desain.md) untuk pola UI/warna bersama, dan [roles-permissions.md](../referensi/roles-permissions.md) untuk detail lengkap hak akses tiap role. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Modul admin-only untuk CRUD akun login sistem (tabel `users`) — nama, email, password, dan role. Satu halaman (`Users/Index.vue`) menangani semuanya lewat dialog Tambah/Edit; tidak ada halaman `show`/`create`/`edit` terpisah (`Route::resource('users', ...)->except(['show', 'create', 'edit'])`). Seluruh route dijaga middleware `role:admin` — role lain tidak bisa mengakses menu ini sama sekali (lihat [roles-permissions.md](../referensi/roles-permissions.md) untuk detail siapa boleh apa di seluruh app).

## Alur Bisnis

- **Tambah akun** (`UserController::store`): isi nama, email (harus unik), password (wajib, min. 8 karakter), dan role (default `sales` di form). Password di-hash lewat `Hash::make()` sebelum disimpan.
- **Edit akun** (`UserController::update`): field sama, tapi password **opsional** — kosongkan berarti password lama tidak berubah (`nullable|string|min:8`, hanya di-set kalau field diisi).
- **Hapus akun** (`UserController::destroy`): satu-satunya proteksi adalah **tidak bisa menghapus akun sendiri** — dicek dua kali, di controller (`$user->id === auth()->id()` → redirect dengan pesan error) dan di UI (tombol hapus disabled + abu-abu untuk baris akun yang sedang login). Tidak ada pengecekan lain (mis. mencegah admin terakhir dihapus).
- **Validasi role di backend** (`store`/`update`) memakai rule `'role' => 'required|in:admin,sales,accountant,operation,travel_agent,guide,driver,tour_leader'` — selaras dengan 8 pilihan di dropdown UI (§Halaman). Sempat hanya mengizinkan 6 role (tertinggal saat dropdown diperluas jadi 8) sehingga memilih Operation/Travel Agent gagal validasi 422 — sudah diperbaiki 17 Jul 2026.
- **`supplier_id`** (kolom di `users` yang menautkan akun `travel_agent` ke `Supplier` miliknya) **tidak dikelola di halaman ini** — form Tambah/Edit tidak punya field untuk itu. Akun `travel_agent` baru yang dibuat lewat Kelola Akun otomatis punya `supplier_id` kosong; penautannya harus dilakukan lewat cara lain di luar UI ini.

## Model Data

Tabel `users` (`app/Models/User.php`), dibentuk dari migration dasar Laravel plus 3 migration tambahan:
- Kolom inti: `id`, `name`, `email` (unique), `email_verified_at`, `password`, `remember_token`, `role`, `supplier_id`, timestamps.
- `role` adalah `enum` MySQL yang bertambah bertahap lewat migration: mulai `admin/sales/accountant/guide/driver/tour_leader` → `+travel_agent` (`2026_06_09_090000_add_travel_agent_role_and_supplier_to_users.php`, migration yang sama juga menambahkan `supplier_id`) → `+operation` (`2026_06_11_100100_add_operation_role_to_users.php`). Total 8 nilai final.
- Tidak ada konstanta/PHP-enum untuk daftar role di model — dicek langsung sebagai string lewat method boolean (`isAdmin()`, `isSales()`, `isAccountant()`, `isOperation()`, `isTravelAgent()`, `isField()` untuk grup guide/driver/tour_leader) dan `homePath()` yang menentukan redirect setelah login per role.
- Atribut massal memakai PHP attribute (`#[Fillable(['name', 'email', 'password', 'role', 'supplier_id'])]`) dan `#[Hidden(['password', 'remember_token'])]`, bukan properti `$fillable`/`$hidden` klasik.
- `password` bercast `'hashed'` di `casts()` — konsisten dengan `Hash::make()` eksplisit di controller.
- Relasi: `assignments()` (hasMany, guide/driver/tour_leader yang ditugaskan), `reminders()` (hasMany), `supplier()` (belongsTo, dipakai akun `travel_agent`).

## Route & Controller

| Route | Controller | Role |
|---|---|---|
| `users.index` (GET `/users`) | `UserController::index` | admin |
| `users.store` (POST `/users`) | `UserController::store` | admin |
| `users.update` (PUT/PATCH `/users/{user}`) | `UserController::update` | admin |
| `users.destroy` (DELETE `/users/{user}`) | `UserController::destroy` | admin |

`index()` mengambil semua user (`User::orderBy('role')->orderBy('name')->get(['id','name','email','role','created_at'])`, kolom `password` sengaja tidak di-select) dan mengirimnya sekaligus ke Inertia — tidak ada paginasi maupun query per-filter, seluruh filter/pencarian terjadi di frontend (lihat §Halaman).

## Halaman & Komponen (UI)

`resources/js/Pages/Users/Index.vue` — satu halaman, mengikuti pola kartu+tabel standar (lihat [00-fondasi-desain.md](00-fondasi-desain.md)) dengan tambahan khusus modul ini:

- **Kartu statistik per role, sekaligus filter** — grid 8 kartu (`grid-cols-2` di mobile s/d `lg:grid-cols-8`), tiap kartu menampilkan jumlah akun per role + dot warna + label. Kartu adalah `<button>` yang bisa **diklik untuk toggle filter** (`toggleFilter`) — klik ulang kartu yang sama membatalkan filter. Kartu aktif diberi `ring-2 ring-gray-400`.
- **8 role & warna badge** (didefinisikan di konstanta `ROLES`, harus tetap sinkron manual dengan enum `users.role` di database):

| Role | Label | Warna badge | Dot |
|---|---|---|---|
| `admin` | Admin | `bg-red-100 text-red-700` | merah |
| `sales` | Sales | `bg-blue-100 text-blue-700` | biru |
| `accountant` | Akuntansi | `bg-green-100 text-green-700` | hijau |
| `operation` | Operation | `bg-cyan-100 text-cyan-700` | cyan |
| `travel_agent` | Travel Agent | `bg-pink-100 text-pink-700` | pink |
| `guide` | Guide | `bg-purple-100 text-purple-700` | ungu |
| `driver` | Driver | `bg-yellow-100 text-yellow-700` | kuning |
| `tour_leader` | Tour Leader | `bg-orange-100 text-orange-700` | oranye |

- **Kolom pencarian** — satu `<Input>` (nama atau email, case-insensitive, client-side lewat computed `filteredUsers`), digabung dengan filter role aktif (keduanya AND). Ada chip "Filter: `<Role>`" dengan tombol X untuk menghapus filter role secara terpisah dari pencarian.
- **Tabel dikelompokkan per role** — bukan daftar datar; baris dikelompokkan lewat computed `grouped` (urutan tetap sesuai `GROUP_ORDER`/`ROLES`, grup dengan 0 hasil setelah filter disembunyikan). Tiap grup punya baris header abu-abu berisi dot warna + label role + jumlah anggota.
- Tiap baris user: avatar bulat berisi huruf pertama nama (warna = warna badge role), nama, badge "Kamu" kalau itu akun yang sedang login, email, badge role berwarna, tanggal dibuat (format `id-ID`, lihat fondasi desain), dan dua ikon aksi di kanan (edit = pensil, hapus = tempat sampah — pola ikon-saja sesuai fondasi desain).
- Tombol hapus khusus untuk baris akun sendiri: `disabled`, ikon pudar, kursor `not-allowed`, tooltip "Tidak bisa hapus akun sendiri".
- **Empty state** dua varian: "Belum ada akun." kalau memang tidak ada user sama sekali, atau "Tidak ada akun yang cocok." + tombol "Hapus pencarian & filter" kalau hasil filter/pencarian kosong tapi datanya ada.
- **Dialog Tambah** dan **Dialog Edit** — dua `<Dialog>` shadcn terpisah (tidak pernah dibuka bersamaan), form identik (nama, email, password, role via `<Select>` dari daftar `ROLES` yang sama dengan kartu statistik). Error validasi ditampilkan per field di bawah input (`addForm.errors.*`/`editForm.errors.*`). Konfirmasi hapus memakai helper `confirm()` dari `@/lib/confirm` (dialog kustom, bukan `window.confirm` bawaan browser).

## Yang Perlu Diperhatikan

- **`supplier_id` tidak dikelola di halaman ini** — penautan akun `travel_agent` ke `Supplier`-nya harus dilakukan di luar Kelola Akun (mis. lewat tinker/query manual), tidak ada field untuk itu di dialog Tambah/Edit.
- Filter & pencarian murni client-side di atas seluruh data user yang dikirim sekali oleh server (tidak ada paginasi/query ulang) — wajar untuk jumlah akun yang masih kecil, tapi perlu diperhatikan kalau jumlah user bertambah banyak.
- Tidak ada proteksi selain "tidak bisa hapus akun sendiri" — admin bisa menghapus admin lain tanpa pengecekan tambahan (mis. tidak ada cek "jangan sampai admin terakhir terhapus").
