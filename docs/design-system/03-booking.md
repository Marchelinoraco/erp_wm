# Modul: Booking Operasional

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Booking Operasional adalah jembatan antara Penjualan (tour yang sudah `confirmed`) dan Keuangan (utang/AP ke supplier). Satu baris `tour_bookings` = satu tugas "eksekusi pemesanan ke satu supplier" untuk sebuah tour. Baris-baris ini **dibuat otomatis** saat tour berubah status jadi `confirmed` (lihat `TourController::generateBookings`, dibahas di [../fitur/penjualan-tour.md §2](../fitur/penjualan-tour.md)) — modul ini murni halaman kerja untuk **mengeksekusi** tugas tersebut, bukan tempat entri awal. Dipakai oleh **admin, sales, dan operation** — dan untuk role `operation`, ini satu-satunya halaman yang bisa diakses sama sekali (lihat `AuthenticatedLayout.vue`, cabang `role === 'operation'` hanya berisi menu "Booking").

## Alur Bisnis

### 1. Pembuatan otomatis saat tour confirmed
`TourController::update` memanggil `generateBookings($tour)` hanya ketika **status berubah** menjadi `confirmed` **dan** tour belum punya booking sama sekali (`$tour->bookings()->count() === 0` — guard anti-duplikat). Logikanya:
- `tour_items` (sudah di-load dengan `product`) dikelompokkan per `product.supplier_id` (item tanpa supplier masuk grup `0`).
- Tiap grup jadi satu baris booking: `description` = nama supplier (atau `"Tanpa supplier (cek manual)"` bila grup `0`), `category` = tipe produk terbanyak dalam grup (dibatasi ke `hotel|transport|guide|restaurant|attraction|other` — **`agent` tidak termasuk** di daftar ini, beda dengan opsi manual di bawah), `est_cost` = jumlah `line_cost` (sisi **modal**, bukan jual) item dalam grup, `notes` = daftar item dalam grup sebagai bullet list (`qty× deskripsi (n malam)`).
- Semua baris dibuat berstatus `pending`. Satu catatan riwayat ditambahkan ke `tour_histories` tour tsb: "`N` tugas booking supplier dibuat otomatis...".
- Karena guard-nya berbasis hitungan baris (bukan flag "sudah pernah generate"), bila semua booking hasil auto-generate dihapus manual lalu tour dipindah status keluar-masuk `confirmed` lagi, booking akan digenerate ulang.

### 2. Eksekusi oleh operation: pending → booked
Tim `operation` membuka tiap baris `pending`, mengisi **harga deal** (`actual_cost`, default terisi estimasi), **nomor konfirmasi/ref supplier** (`booking_ref`), dan catatan, lalu simpan. `BookingController::update` men-set `status='booked'`, `booked_at=now()`, `booked_by=nama user login`, lalu memanggil `syncBill()`:
- Kalau booking belum punya `bill_id` → membuat `Bill` (AP) baru lewat `$tour->bills()->create(...)`: `due_date` diambil dari `tour.start_date` (atau +7 hari kalau kosong), `status='unpaid'`, `notes` menyebut asal "Auto dari booking operasional" + ref bila ada.
- Kalau sudah punya Bill (mis. saat edit ulang) → nominal/`supplier_id`/`category` di-update **hanya jika Bill itu belum ada pembayaran sama sekali** (`bill->payments()->count() === 0`) — supaya tagihan yang sudah (sebagian) dibayar akuntan tidak berubah diam-diam.

### 3. Batalkan / kembalikan
- **Kembalikan ke pending** (revert) atau **tandai batal** (`cancelled`) — keduanya lewat `update()` cabang non-`booked`. Untuk `pending`, `booked_at`/`booked_by` direset ke null. Keduanya memanggil `detachBill()`: Bill terkait **dihapus** kalau belum ada pembayaran (`payments()->count() === 0`), demi menjaga Keuangan tetap bersih dari tagihan yatim; kalau sudah ada pembayaran, Bill dibiarkan (tidak dilepas dari relasi maupun dihapus).
- **Hapus baris booking** (`destroy`) — sama, `detachBill()` dulu baru baris booking dihapus permanen.

### 4. Tambah booking manual
`BookingController::store` menerima tambahan booking manual ke tour mana pun (tidak ada pengecekan `tour.status === 'confirmed'` di controller — hanya dibatasi UI, tombol "+ Booking manual" cuma muncul di kartu tour yang memang sudah tampil di halaman ini, yaitu tour `confirmed`). Berguna untuk kebutuhan supplier tambahan yang tidak berasal dari `tour_items`, mis. biaya di luar item produk standar. Kategori manual **termasuk `agent`** (beda dengan hasil auto-generate, lihat §1).

## Model Data

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `tour_bookings` | `belongsTo Tour`, `belongsTo Supplier` (nullable), `belongsTo Bill` (nullable) | `est_cost`/`actual_cost` decimal cast; `booked_at` datetime cast |

Kolom migrasi (`2026_06_11_100000_create_tour_bookings_table.php`, judul komentar migrasi: *"jembatan operasional antara Sales (confirmed) dan Keuangan (AP)"*): `tour_id` (cascade delete), `supplier_id` (null on delete), `description`, `category` (default `other`), `est_cost`, `actual_cost` (nullable), `status` (default `pending`, index), `booking_ref`, `booked_at`, `booked_by` (nama teks bebas, bukan `user_id`), `bill_id` (null on delete), `notes`. Index komposit `[tour_id, status]`.

`TourBooking::STATUSES` (dipakai untuk label & untuk render di UI): `pending` → "Belum di-booking", `booked` → "Sudah di-booking", `cancelled` → "Batal".

## Route & Controller

Semua route di bawah `role:admin,sales,operation` (`routes/web.php` baris ~193-199):

| Route | Method | Keterangan |
|---|---|---|
| `bookings.index` | GET `/bookings` | Daftar semua tour `confirmed` + booking-nya |
| `bookings.store` | POST `/tours/{tour}/bookings` | Tambah booking manual |
| `bookings.update` | PATCH `/bookings/{booking}` | Ubah status/harga deal/ref/catatan |
| `bookings.destroy` | DELETE `/bookings/{booking}` | Hapus baris booking |

`BookingController::index` mengambil semua `Tour::where('status', 'confirmed')` beserta booking-nya (urut `FIELD(status,'pending','booked','cancelled')` lalu `id`), dan tour diurut lagi (`sortByDesc`) supaya yang punya booking tampil duluan — tapi tour tanpa booking tetap disertakan (untuk keperluan tambah manual). `stats` dihitung lintas semua `TourBooking` di sistem (bukan per tour): jumlah `pending`/`booked`/`cancelled`, `est_total` (jumlah `est_cost` untuk status pending+booked), `actual_total` (jumlah `actual_cost` untuk status booked).

## Halaman & Komponen (UI)

- **`Bookings/Index.vue`** — layout `max-w-5xl`, mengikuti pola kartu+tabel standar (lihat fondasi desain) tapi disusun sebagai **kartu per tour** (bukan satu tabel datar): tiap tour jadi satu card berisi header (kode/link ke `tours.edit`, badge tipe, judul/customer/pax/tanggal mulai, tombol "+ Booking manual") lalu baris-baris booking di dalamnya (dipisah `divide-y`).
- **Kartu statistik** (4 kolom): jumlah pending (oranye), jumlah booked (hijau), estimasi biaya total (`IDR ...`), total yang sudah di-deal (`IDR ...`, biru).
- **Badge kategori** (per baris booking): `hotel` biru, `transport` amber, `guide` emerald, `restaurant` rose, `attraction` violet, `agent` cyan, `other` abu — pola warna-per-kategori khas modul ini, terpisah dari palet status/role di fondasi desain.
- **Badge status baris**: `pending` oranye, `booked` hijau, `cancelled` abu-redup.
- **Indikator tagihan**: baris yang sudah punya `bill_id` menampilkan "✓ Tagihan dibuat (status bill)" berwarna hijau.
- **Aksi per baris** tergantung status: `pending` → tombol "Booking" (buka dialog) + ikon batal; `booked` → tombol "Edit" (buka dialog yang sama) + ikon kembalikan-ke-pending; `cancelled` → tombol "Aktifkan" (langsung `patch` ke `pending` tanpa dialog). Ikon hapus selalu ada (lewat `confirm()` dari `@/lib/confirm`).
- **Dialog "Konfirmasi Booking Supplier"** — input Harga Deal (IDR, wajib, dengan teks bantu estimasi dari item), No. Konfirmasi/Ref Supplier, Catatan; deskripsi dialog secara eksplisit memperingatkan "otomatis dibuat tagihan (AP) di menu Keuangan".
- **Dialog "Tambah Booking Manual"** — pilih supplier opsional (memilih supplier otomatis mengisi deskripsi & kategori dari data supplier via `onSupplierPick`), deskripsi wajib, kategori (select dari daftar kategori termasuk `agent`), estimasi.
- **Empty state**: kalau tidak ada tour `confirmed` sama sekali → pesan bahwa booking otomatis muncul saat inquiry dikonfirmasi. Kalau ada tour `confirmed` tapi tak satupun punya booking → banner kuning terpisah menyarankan tambah manual.
- **Badge sidebar & banner lintas halaman**: jumlah booking `pending` di seluruh sistem dibagikan lewat Inertia shared prop `pendingBookings` (`HandleInertiaRequests::share`, hanya dihitung untuk role `admin`/`sales`/`operation`) — muncul sebagai angka badge di item menu "Booking" pada sidebar (`AuthenticatedLayout.vue`), dan sebagai banner oranye "X supplier belum di-booking" yang bisa diklik menuju `bookings.index` di `Dashboard.vue` maupun di halaman [Reminder](04-reminder.md).

## Yang Perlu Diperhatikan

- **Tidak ada pengecekan kepemilikan** di level controller — sama seperti modul Tour (lihat [../fitur/penjualan-tour.md §4](../fitur/penjualan-tour.md)), hanya dijaga middleware role di level route group.
- **Kategori `agent` tidak pernah dihasilkan otomatis** — hanya bisa muncul lewat tambah booking manual, karena daftar `$allowed` di `generateBookings()` tidak menyertakannya.
- **`est_cost` berbasis `line_cost`** (sisi modal `tour_items`), bukan `line_sell` — angka ini murni referensi biaya ke supplier, terpisah dari kalkulasi profit di panel Invoice/Costing (lihat [../fitur/penjualan-tour.md §2](../fitur/penjualan-tour.md)).
- **Guard Bill selalu cek `payments()->count() === 0`** sebelum mengubah/menghapus Bill terkait — baik saat `syncBill` (update nominal) maupun `detachBill` (hapus) — supaya data Bill yang sudah tersentuh pembayaran di Keuangan tidak pernah berubah/hilang diam-diam dari sisi Booking.
- `bookings.store` (tambah manual) tidak memvalidasi status tour di backend — kalau dipanggil langsung ke tour non-`confirmed`, tetap akan berhasil; hanya UI yang secara praktis membatasi lewat tour mana saja yang ditampilkan di halaman ini.
