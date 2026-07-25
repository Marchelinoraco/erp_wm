# Modul: Channel Manager & Produk Agent (Travel Agent Eksternal)

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Dua halaman yang membentuk satu alur dua sisi di atas tabel data yang sama (`products` & `product_prices`):

- **Produk Saya** (`AgentProductController`, `AgentProductPriceController`) — dipakai oleh role `travel_agent`, akun eksternal (bukan staf internal) yang terhubung ke satu baris `suppliers` lewat `users.supplier_id`. Di sini agent mengelola katalog produk suppliernya sendiri (nama, tipe, satuan) dan mengajukan harga modal — baik harga dasar produk maupun harga per periode musiman.
- **Channel Manager** (`ChannelManagerController`) — dipakai oleh `admin`/`sales`, tempat me-review semua pengajuan harga dari travel agent: setujui (harga jadi live) atau tolak (kembali ke harga lama), atau langsung override harga tanpa menunggu pengajuan.

Akun `travel_agent` dibuat dari modul Suppliers (checkbox "Jadikan Travel Agent (eksternal)" di form Supplier) — mengisi `suppliers.is_travel_agent = true` dan membuat `User` terkait (`role = travel_agent`, `supplier_id` mengarah ke supplier itu). Relasinya 1:1 (`Supplier::user()` — `hasOne`), sehingga satu supplier travel agent = satu akun login.

**Sidebar**: karena `travel_agent` adalah salah satu role dengan menu sidebar penuh berbeda (lihat [pola-ui-desain.md §Kontrol Akses](../referensi/pola-ui-desain.md#kontrol-akses-di-level-ui)), akun ini hanya melihat satu item menu: "Produk Saya". Sebaliknya "Channel Manager" muncul di grup **Data Master** untuk admin/sales, di antara Produk, Suppliers, dan Rekening.

## Alur Bisnis

Ada **dua level pengajuan harga**, dengan pola approve/reject yang mirip tapi tidak identik. Status disimpan sebagai kolom `price_status`/`status` bertipe string nullable (bukan enum state machine) yang mengalir sederhana:

```
(tidak ada pengajuan, null) → agent submit → 'pending' → admin/sales approve → null (harga live terisi, is_active=true)
                                                        → admin/sales reject  → null (harga pending dibuang, harga live TIDAK berubah)
```

Internal juga bisa memotong alur ini kapan saja lewat "Ubah Harga" (override langsung, lihat di bawah) tanpa perlu menunggu ada pengajuan `pending` lebih dulu.

### 1. Harga dasar produk (kolom `products.pending_cost`/`price_status`)
- **Produk baru** (`agent.products.store`): agent mengisi nama/tipe/satuan/harga modal. Produk dibuat dengan `cost = 0`, `sell = 0`, `pending_cost = <input>`, `price_status = 'pending'`, `price_submitted_by = <nama user>`, `is_active = false` — produk belum aktif/terlihat sampai disetujui.
- **Edit produk existing** (`agent.products.update`): nama/tipe/satuan **selalu langsung tersimpan**, tanpa gating. Hanya kalau nilai `cost` berubah, perubahan masuk ke antrian (`pending_cost`, `price_status = 'pending'`) — harga lama tetap live sampai disetujui.
- Agent **tidak** bisa mengubah `sell` (harga jual ke customer) sama sekali — field itu murni domain internal.
- **Approve** (`ChannelManagerController::approve`): `cost = pending_cost`, bersihkan field pending, paksa `is_active = true`. **Catatan penting**: method ini **tidak mengubah `sell` sama sekali** — untuk produk baru yang baru pertama kali disetujui, `sell` tetap `0` sampai admin/sales membuka "Ubah Harga" secara manual.
- **Reject** (`ChannelManagerController::reject`): bersihkan field pending, `cost`/`is_active` **tidak disentuh** — untuk produk baru yang ditolak, itu berarti produk tetap `cost=0`, `is_active=false` selamanya (bukan dihapus) sampai agent mengedit ulang.
- Internal juga bisa **override langsung** (`updatePrice`) — isi `cost` & `sell` sekaligus, kapan saja, tanpa perlu ada pengajuan pending lebih dulu; ini otomatis membersihkan status pending & mengaktifkan produk.

### 2. Harga per periode (`product_prices`, mis. "High Season 2026")
- Agent mengajukan periode baru (`agent.product-prices.store`): label bebas, `start_date`/`end_date` (`end_date` wajib ≥ `start_date`), harga modal. Baris dibuat `cost=0`, `sell=0`, `pending_cost=<input>`, `status='pending'`, `is_active=false`.
- Agent hanya bisa membatalkan (`destroy`) periode **miliknya sendiri** dan **masih `pending`** — begitu diproses (approve/reject), tidak bisa dihapus lagi dari sisi agent.
- **Approve periode** (`ChannelManagerController::approvePrice`) **berbeda dari approve produk**: internal **wajib mengisi `sell`** sebagai bagian dari aksi approve itu sendiri (`sell` divalidasi `required`) — UI menampilkan input "Harga Jual" sebelum tombol Setujui bisa diklik. `cost = pending_cost`, `sell = <input>`, `is_active = true`.
- **Reject periode**: sama seperti level produk, bersihkan field pending, `cost`/`sell`/`is_active` tetap seperti sebelumnya (0/false untuk periode baru).
- Internal bisa override langsung kapan saja (`updatePeriodPrice`) — set `cost`/`sell`/`is_active` tanpa syarat status pending.

### Aturan lain yang teramati di kode
- **Kepemilikan**: `AgentProductController`/`AgentProductPriceController` mengecek `product->supplier_id === user->supplier_id` (`authorizeOwnership`) di tiap aksi update/destroy. Akun `travel_agent` tanpa `supplier_id` (belum ditautkan) mendapat `403` saat membuka Produk Saya.
- **Mata uang**: produk yang diajukan agent selalu dipaksa `currency = 'IDR'` saat dibuat — tidak ada pilihan mata uang di form Produk Saya (beda dari Produk master data internal yang mendukung mata uang lain).
- **Validasi enum**: `type` dibatasi `hotel|transport|guide|restaurant|attraction|agent|other`, `unit` dibatasi `per_pax|per_unit|per_night` (sama di kedua controller store/update).
- `ChannelManagerController::index` hanya menampilkan supplier dengan `is_travel_agent = true`. `pendingCount` di header dihitung global dari `products`/`product_prices` berstatus `pending` — tapi karena kolom `pending_cost`/`price_status`/`status` di kedua tabel **hanya pernah diisi lewat controller sisi agent** (tidak dipakai oleh `ProductController`/`ProductPriceController` internal biasa), angka ini secara praktis selalu berasal dari pengajuan travel agent.

## Model Data

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `products` | `belongsTo Supplier`, `hasMany ProductPrice` | `cost`/`sell` = harga live; `pending_cost`/`price_status`(`pending`\|`null`)/`price_submitted_by`/`price_updated_at` = antrian approval level dasar. `group_label`/`grade` (varian hemat/standar/premium, dari migration `2026_06_23`) ada di skema tapi **hanya dikelola lewat Produk master data internal** (`ProductController`) — tidak diekspos di Produk Saya maupun Channel Manager |
| `product_prices` | `belongsTo Product` | Skema paralel dengan `products`: `cost`/`sell` live, `pending_cost`/`status`/`submitted_by`/`submitted_at` = antrian approval periode. `is_active` menandai apakah periode ini sedang berlaku (di luar makna status pending). Kolom `notes` ada di tabel & form state (`priceForm.notes`/validasi backend) tapi **tidak dirender sebagai input** di kedua halaman Vue — praktis selalu kosong saat ini |
| `suppliers` | `hasMany Product`, `hasOne User` | `is_travel_agent` menentukan tampil-tidaknya di Channel Manager; `user` = akun login travel agent yang ditautkan (dibuat dari modul Suppliers) |
| `users` | `belongsTo Supplier` (nullable, `nullOnDelete`) | Role enum ditambah `travel_agent` (migration `2026_06_09_090000`); kalau supplier dihapus, akun tidak ikut terhapus tapi `supplier_id` jadi `null` → `abort 403` saat buka Produk Saya |

## Route & Controller

| Route | Controller | Role |
|---|---|---|
| `channel-manager.index` | `ChannelManagerController@index` | admin, sales |
| `channel-manager.approve` / `.reject` | `ChannelManagerController@approve` / `@reject` | admin, sales |
| `channel-manager.price` | `ChannelManagerController@updatePrice` | admin, sales |
| `channel-manager.price.approve` / `.price.reject` | `ChannelManagerController@approvePrice` / `@rejectPrice` | admin, sales |
| `channel-manager.period.price` | `ChannelManagerController@updatePeriodPrice` | admin, sales |
| `agent.products.index/store/update/destroy` | `AgentProductController` | travel_agent |
| `agent.product-prices.store` / `.destroy` | `AgentProductPriceController` | travel_agent |

## Halaman & Komponen (UI)

- **`ChannelManager/Index.vue`** — dikelompokkan per supplier: kartu accordion collapsible (`+`/`−`) per travel agent, header menampilkan jumlah produk + badge oranye jumlah "menunggu persetujuan" bila ada. Baris produk di dalamnya diberi latar `bg-orange-50/30` kalau `price_status === 'pending'`. Tidak pakai `<Dialog>` sama sekali — pola edit/approve **inline**: baris berganti jadi input Modal/Jual (Ubah Harga) atau tombol Setujui (hijau)/Tolak (outline) langsung di tempat. Tiap produk punya sub-panel collapsible "Periode Harga (n)" dengan pola sama, kecuali approve periode butuh isi "Harga Jual" dulu sebelum tombol Setujui bisa dipakai (beda dari approve produk yang satu klik). Badge status periode: **Menunggu** (oranye), **Aktif** (hijau), **Non-aktif** (abu).
- **`AgentProducts/Index.vue`** — daftar kartu per produk (bukan tabel). Banner info biru permanen di atas menjelaskan alur approval ke agent. Tombol "+ Tambah Produk" membuka form inline (bukan dialog) di dalam kartu. Tiap kartu produk: mode lihat menampilkan harga (dengan suffix oranye "(diajukan: ...)" kalau pending) + badge status **Menunggu Persetujuan** (oranye) / **Aktif** (hijau) / **Non-aktif** (abu), atau mode edit-inline (form menggantikan isi kartu) via tombol Edit/Hapus. Sub-panel "Periode Harga" per produk sama polanya: form tambah inline + daftar periode, tiap periode pending punya tombol "Batalkan" (destructive) yang hilang begitu sudah diproses.
- Kedua halaman konsisten menghindari `<Dialog>` untuk aksi approve/edit/tambah — semua lewat expand/collapse & swap-in-place form, berbeda dari pola dialog modal yang lebih umum di modul lain (lihat fondasi desain).
- **Tidak ada filter, pencarian, atau paginasi** di kedua halaman — `ChannelManager/Index.vue` merender semua supplier travel agent & seluruh produknya sekaligus (hanya collapse per supplier untuk meringkas tampilan), begitu juga `AgentProducts/Index.vue` merender seluruh produk milik supplier tsb tanpa batas jumlah. Wajar untuk skala saat ini (jumlah travel agent & produk per agent masih kecil) tapi bisa jadi berat kalau salah satu tumbuh besar.
- Warna oranye dipakai konsisten di kedua halaman sebagai penanda "menunggu persetujuan" (bukan kuning, yang di modul lain dipakai untuk status *partial/warning*); badge role `travel_agent` di top bar/daftar user memakai teal (`bg-teal-100 text-teal-700`, `AuthenticatedLayout.vue`).

## Yang Perlu Diperhatikan

- **`sell` bisa nyangkut di 0 setelah approve produk baru**: approve level-dasar hanya memindahkan `pending_cost → cost` dan langsung mengaktifkan produk (`is_active = true`), tapi tidak meminta/menyalin harga jual. Produk baru yang disetujui jadi "Aktif" dengan `sell = 0` sampai admin/sales sadar dan mengisi manual lewat "Ubah Harga". Ini kontras dengan approve periode yang mewajibkan input Harga Jual di titik approve yang sama.
- **Produk yang ditolak tidak bisa "dicoba lagi" tanpa jejak**: reject pada produk baru meninggalkannya permanen di `cost=0`, `is_active=false` (bukan dihapus) — agent harus mengedit produk itu lagi (memicu pengajuan baru) untuk mengulang, tidak ada catatan alasan penolakan yang tersimpan/ditampilkan (tidak seperti Cost Request di modul Penjualan yang punya `review_notes` wajib).
- Kolom `notes` di `product_prices` didukung backend tapi tidak punya input di UI manapun saat ini — dead field secara praktis.
- Tidak ada validasi tumpang-tindih tanggal antar periode harga (`product_prices`) — dua periode bisa saja overlap; logika pemilihan harga periode mana yang dipakai saat item tour dibuat tidak tercakup di controller yang dibaca untuk dokumen ini.
- Opsi tipe produk `agent` tervalidasi di backend (`AgentProductController`, `ProductController`) tapi tidak ada di `TYPE_LABELS` frontend manapun (hanya hotel/transport/guide/restaurant/attraction/other) — kalau nilai ini pernah tersimpan, akan tampil sebagai teks mentah, bukan label.
- Agent tidak bisa mengatur `currency` maupun `group_label`/`grade` (varian) — dua-duanya hanya bisa diisi lewat Produk master data internal setelah produk ada.
