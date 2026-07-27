# Booking

> **Status:** ✅ Berjalan · **Peran:** admin, sales, operation · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [supplier.md](supplier.md), [keuangan-ar-ap.md](keuangan-ar-ap.md), [rencana/tour-ownership.md](../rencana/tour-ownership.md)

## 1. Ringkasan

Jembatan operasional antara Penjualan (tour yang sudah `confirmed`) dan Keuangan (utang/AP ke supplier) — satu baris `tour_bookings` per tugas "eksekusi pemesanan ke satu supplier", dibuat otomatis saat tour berubah status jadi confirmed (dikelompokkan dari item tour per supplier). Tim operation mengeksekusi tiap baris (isi harga deal, nomor konfirmasi), yang otomatis membuat/menyinkronkan Bill terkait. Dipakai oleh admin, sales, dan operation — untuk operation ini satu-satunya halaman yang bisa diakses.

## 2. Cara kerja (as-built)

### Pembuatan otomatis saat tour confirmed

`TourController::update` memanggil `generateBookings()` (privat) hanya ketika **status berubah** menjadi `confirmed` **dan** tour belum punya booking sama sekali (`$tour->bookings()->count() === 0` — guard anti-duplikat berbasis hitungan baris, bukan flag "sudah pernah generate"; bila semua booking hasil auto-generate dihapus manual lalu status keluar-masuk `confirmed` lagi, booking akan dibuat ulang). Logikanya (`TourController.php:358-404`):
- `tour_items` (dimuat dengan relasi `product`) dikelompokkan per `product.supplier_id` (item tanpa supplier masuk grup `0`).
- Tiap grup jadi satu baris booking: `description` = nama supplier (atau `"Tanpa supplier (cek manual)"` untuk grup `0`), `category` = tipe produk terbanyak dalam grup — dibatasi ke daftar tetap `hotel, transport, guide, restaurant, attraction, other` (**`agent` tidak termasuk** di sini, beda dari opsi manual di bawah), `est_cost` = jumlah `line_cost` (sisi **modal**, bukan jual) item dalam grup, `notes` = daftar item sebagai bullet (`qty× deskripsi (n malam)`).
- Semua baris dibuat berstatus `pending`. Satu catatan riwayat ditambahkan ke `tour_histories`: "`N` tugas booking supplier dibuat otomatis. Operation eksekusi di menu Booking."

### Eksekusi oleh operation: pending → booked

Tim `operation` membuka baris `pending`, mengisi **harga deal** (`actual_cost`, default estimasi kalau kosong), **nomor konfirmasi/ref supplier** (`booking_ref`), dan catatan. `BookingController::update` men-set `status='booked'`, `booked_at=now()`, `booked_by` = nama user login (teks bebas, bukan `user_id`), lalu memanggil `syncBill()` privat:
- Booking belum punya `bill_id` → membuat `Bill` (AP) baru lewat `$tour->bills()->create(...)`: `due_date` = `tour.start_date` (atau `+7 hari` kalau kosong), `status='unpaid'`, `notes` menyebut asal "Auto dari booking operasional" + ref bila ada.
- Booking sudah punya Bill (mis. edit ulang) → nominal/`supplier_id`/`category` di-update **hanya jika Bill itu belum ada pembayaran sama sekali** (`bill->payments()->count() === 0`) — supaya tagihan yang sudah (sebagian) dibayar akuntan tidak berubah diam-diam.

### Batalkan / kembalikan

Set status ke `pending` (revert, `booked_at`/`booked_by` direset null) atau `cancelled` lewat `update()` — keduanya memanggil `detachBill()`: Bill terkait **dihapus** kalau belum ada pembayaran, demi menjaga Keuangan tetap bersih dari tagihan yatim; kalau sudah ada pembayaran, Bill dibiarkan apa adanya. Menghapus baris booking (`destroy`) melakukan hal sama sebelum baris dihapus permanen.

### Tambah booking manual

`BookingController::store` menerima tambahan booking manual ke tour mana pun — **tidak ada pengecekan `tour.status === 'confirmed'` di backend**, hanya dibatasi UI (tombol "+ Booking manual" cuma muncul di kartu tour yang memang tampil di halaman ini, yaitu tour `confirmed`). Berguna untuk supplier tambahan yang tidak berasal dari `tour_items`. Kategori manual **termasuk `agent`** (beda dari hasil auto-generate di atas).

### Kepemilikan tour per sales (lihat §4)

`BookingController::index` men-scope daftar tour+booking untuk sales — hanya tour yang `created_by` miliknya atau tour lama tanpa pemilik (`created_by` null) yang muncul; admin dan **operation tetap melihat semua** (operation butuh semua tour confirmed untuk eksekusi). Statistik (`stats`: jumlah pending/booked/cancelled, total estimasi/deal) ikut di-scope sama untuk sales lewat `whereHas('tour', ...)`.

### Model data

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `tour_bookings` | `belongsTo Tour`, `belongsTo Supplier` (nullable), `belongsTo Bill` (nullable) | `est_cost`/`actual_cost` decimal; `booked_at` datetime; index komposit `[tour_id, status]` |

`TourBooking::STATUSES`: `pending` → "Belum di-booking", `booked` → "Sudah di-booking", `cancelled` → "Batal".

### Route & Controller

Semua route di bawah `role:admin,sales,operation` (`routes/web.php:206-210`):

| Route | Method | Keterangan |
|---|---|---|
| `bookings.index` | GET `/bookings` | Daftar tour `confirmed` + booking-nya (scoped per sales, lihat di atas) |
| `bookings.store` | POST `/tours/{tour}/bookings` | Tambah booking manual |
| `bookings.update` | PATCH `/bookings/{booking}` | Ubah status/harga deal/ref/catatan |
| `bookings.destroy` | DELETE `/bookings/{booking}` | Hapus baris booking |

### Halaman & Komponen (UI)

`Bookings/Index.vue` — kartu per tour (bukan tabel datar): header (kode/link ke `tours.edit`, badge tipe, judul/customer/pax/tanggal mulai, tombol "+ Booking manual") lalu baris-baris booking di dalamnya. Kartu statistik 4 kolom (pending, booked, estimasi total, total di-deal). Badge kategori berwarna per kategori (`hotel` biru, `transport` amber, `guide` emerald, `restaurant` rose, `attraction` violet, `agent` cyan, `other` abu) dan badge status per baris (`pending` oranye, `booked` hijau, `cancelled` abu-redup) — lihat [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola bersama. Baris yang sudah punya `bill_id` menampilkan indikator "✓ Tagihan dibuat". Dialog "Konfirmasi Booking Supplier" (Harga Deal, No. Konfirmasi/Ref, Catatan) secara eksplisit memperingatkan bahwa aksi ini otomatis membuat tagihan (AP) di Keuangan.

Jumlah booking `pending` di seluruh sistem dibagikan lewat Inertia shared prop `pendingBookings` (`HandleInertiaRequests::share`, dihitung untuk role `admin`/`sales`/`operation`) — muncul sebagai badge di menu sidebar "Booking" dan sebagai banner "X supplier belum di-booking" di Dashboard (lihat §4 untuk catatan scoping-nya).

## 3. Keterkaitan

- **Penjualan Tour** ([penjualan-tour.md](penjualan-tour.md)) — booking dibuat otomatis dari `tour_items` saat tour berubah status ke `confirmed` (`TourController::generateBookings`); satu baris booking selalu menempel ke satu `Tour`.
- **Supplier** ([supplier.md](supplier.md)) — booking dikelompokkan per `supplier_id` produk; memilih supplier di dialog manual otomatis mengisi deskripsi/kategori dari data supplier.
- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — menandai booking `booked` otomatis membuat/menyinkronkan `Bill` (AP); Bill ini lalu dikelola akuntan (nominal, pembayaran) di Keuangan seperti Bill lain.
- **Dashboard** ([dashboard.md](dashboard.md)) — banner "supplier belum di-booking" memakai `pendingBookings` yang sama.

## 4. Batasan & jebakan ⚠️

- **Kepemilikan tour per sales sudah aktif di kode, tapi migrasinya belum jalan di production.** `BookingController::index` sudah men-scope tour+stats untuk sales via `created_by`; sampai kolom `tours.created_by` dimigrasikan ke production, semua tour lama dianggap "tanpa pemilik" sehingga tetap terbuka untuk semua sales. Operation tidak pernah di-scope (selalu lihat semua). Detail: [rencana/tour-ownership.md](../rencana/tour-ownership.md) dan [penjualan-tour.md §4](penjualan-tour.md#4-batasan--jebakan-️).
- **Badge `pendingBookings` (sidebar & banner Dashboard) TIDAK ikut di-scope per sales** — `HandleInertiaRequests::share` menghitungnya sebagai `TourBooking::where('status','pending')->count()` global untuk role `admin`/`sales`/`operation`, terlepas dari filter kepemilikan yang berlaku di halaman Booking itu sendiri. Sales bisa melihat angka badge lebih besar dari jumlah baris yang sebenarnya muncul saat ia membuka halaman Booking.
- **Kategori `agent` tidak pernah dihasilkan otomatis** — hanya bisa muncul lewat tambah booking manual, karena daftar kategori yang diizinkan di `generateBookings()` tidak menyertakannya.
- **`est_cost` berbasis `line_cost`** (sisi modal `tour_items`), bukan `line_sell` — murni referensi biaya ke supplier, terpisah dari kalkulasi profit di panel Invoice/Costing (lihat [penjualan-tour.md §2](penjualan-tour.md#2-cara-kerja-as-built)).
- **Guard Bill selalu cek `payments()->count() === 0`** sebelum mengubah/menghapus Bill terkait (`syncBill`/`detachBill`) — data Bill yang sudah tersentuh pembayaran di Keuangan tidak pernah berubah/hilang diam-diam dari sisi Booking.
- **`bookings.store` (tambah manual) tidak memvalidasi status tour di backend** — dipanggil langsung ke tour non-`confirmed` tetap berhasil; hanya UI yang praktis membatasi lewat tour mana saja yang ditampilkan di halaman ini.

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP. Pembatasan tampilan booking per akun sales (mengikuti kepemilikan tour) sudah ada di kode tapi belum jalan di production — lihat catatan §5 di [penjualan-tour.md](penjualan-tour.md) dan [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

- [pola-ui-desain.md](../referensi/pola-ui-desain.md) — fondasi UI/warna/layout bersama
- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — invarian lintas-fitur (profit riil, pipeline status)
- [penjualan-tour.md](penjualan-tour.md) — sumber pembuatan otomatis booking saat tour confirmed
- [supplier.md](supplier.md), [keuangan-ar-ap.md](keuangan-ar-ap.md), [dashboard.md](dashboard.md) — fitur terkait (lihat §3)
- [rencana/tour-ownership.md](../rencana/tour-ownership.md) — rencana & status implementasi kepemilikan tour per sales
