# Penugasan Lapangan

> **Status:** ✅ Berjalan · **Peran:** admin, sales, guide, driver, tour_leader · **Sejak:** Jun 2026
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [peran-akses.md](peran-akses.md), [referensi/roles-permissions.md](../referensi/roles-permissions.md)

## 1. Ringkasan

Penugasan guide/driver/tour_leader ke tour (`assignments`, opsional ditautkan ke akun sistem lewat `user_id`) yang menentukan tampilan di My Jobs (halaman terautentikasi untuk tim lapangan) dan Manifest publik (halaman tanpa login via signed URL yang dibagikan lewat WhatsApp) — keduanya versi "program tanpa harga" dari tour yang sama, dengan data buyer/tamu di-masking sesuai kebutuhan. Dipakai oleh admin & sales (menugaskan) serta guide/driver/tour_leader (melihat jadwal sendiri).

## 2. Cara kerja (as-built)

Tiga bagian yang saling menyambung tapi tidak membentuk tabel baru selain `assignments`: pembuatan tugas (Sales), My Jobs (tim lapangan login), dan Manifest publik (tanpa login).

### Membuat assignment

Sales membuat `Assignment` dari panel Operasional di halaman edit tour (`OperasionalPanel.vue` → `AssignmentController::store`, `POST /tours/{tour}/assignments`). Field: `role` (wajib, salah satu `guide`/`driver`/`tour_leader`), `person_name`, `phone`, `vehicle`, `pickup_time`, `notes`, dan **`user_id` opsional** — dropdown "Link ke Akun" berisi `fieldUsers` (semua `User` dengan `role` `guide`/`driver`/`tour_leader`, dikirim `TourController::edit` via `User::whereIn('role', [...])->orderBy('name')->get(['id','name','role'])`). Memilih salah satu akun mengisi `user_id`+`person_name`+`role`; dikosongkan, assignment tetap tersimpan hanya dengan `person_name`/`phone` bebas tanpa akun sistem terhubung. `AssignmentController` (store/update/destroy) tidak melakukan pengecekan otorisasi tambahan di luar middleware role level route.

### My Jobs (`/my-jobs`) — halaman terautentikasi tim lapangan

`user_id` assignment menentukan tampil-tidaknya tour di My Jobs:
- `MyJobsController::index` memfilter `Tour::whereHas('assignments', fn ($q) => $q->where('user_id', $user->id))`, eager-load `customer:id,name,country` dan `assignments` yang **difilter hanya milik user login**, urut `orderBy('start_date')`, lalu `->each->maskCustomerForField()`. Assignment yang tidak ditautkan ke akun (`user_id` null) **tidak pernah muncul** di My Jobs siapa pun.
- `MyJobsController::show` mengecek otorisasi eksplisit: `abort_unless($tour->assignments()->where('user_id', $user->id)->exists(), 403)` — user field yang tidak ditugaskan ke tour tsb kena 403 meski tahu ID tour. Eager-load penuh: `customer`, `assignments` (**semua** assignment tour, bukan cuma milik user — supaya guide bisa lihat kontak driver rekannya), `items`, `itineraryDays`, `itineraryHours`.
- Kedua method memanggil `Tour::maskCustomerForField()` (`app/Models/Tour.php:152`) sebelum render — kalau `tour.guest_name` terisi (customer tipe Buyer/travel agent), relasi `customer` diganti objek `Customer` baru berisi `guest_name`/`guest_phone` saja; kalau kosong, data `Customer` asli tetap dikirim apa adanya. Lihat [customer.md](customer.md) dan [penjualan-tour.md](penjualan-tour.md#guest-namephone-customer-tipe-buyer).
- `Tour::fieldSchedule()` (`app/Models/Tour.php:219`) merakit seksi "Jadwal Layanan" dari item **semua invoice tour** (baseline/detail/approved, tidak dibedakan) yang punya `start_date` — sengaja hanya field aman (`product_type`, `description`, `qty`, `nights`, tanggal), **tanpa** `unit_cost`/`unit_sell`/`line_cost`/`line_sell`.

### Manifest publik (`/manifest/{tour}`) — tanpa login

`ManifestController::show` **tidak melakukan pengecekan otorisasi apa pun** di controller — akses murni dikendalikan lewat `middleware('signed')` di level route (route ini ditaruh di `routes/web.php:310`, di luar grup `auth`/`verified`). URL harus mengandung signature valid dari `URL::signedRoute('manifest', ['tour' => $tour->id])`, dipanggil di `TourController::edit` (`TourController.php:155`) dan dikirim sebagai prop `manifestUrl` ke `Tours/Edit.vue` — tombol "Salin Link" di panel Operasional menyalinnya ke clipboard untuk dibagikan sales lewat WhatsApp. Signed route ini dibuat **tanpa waktu kedaluwarsa** (`URL::signedRoute`, bukan `temporarySignedRoute`) — begitu link dibuat, ia berlaku selamanya kecuali `APP_KEY` diganti. `manifestUrl` dihasilkan tiap kali halaman `Tours/Edit.vue` dibuka, pada **status tour apa pun** (tidak digerbangi `confirmed`); tour tanpa `tour_items`/itinerary menampilkan "Program belum diisi." Controller memuat `customer`, `items` (`orderBy('day_number')->orderBy('sort_order')`), `assignments` (semua), `itineraryDays`, `itineraryHours`, lalu memanggil `maskCustomerForField()` dan mengirim `fieldSchedule()` yang sama seperti My Jobs.

### Halaman & Komponen (UI)

- **`MyJobs/Index.vue`** — grid kartu (1 kolom mobile, 2 desktop): `tour.code`, badge status (palet sama `Tours/Index.vue`), judul, rentang tanggal, nama customer (sudah termasuk masking), badge role ungu per assignment milik user. Tanpa pencarian/filter/paginasi. Dibungkus `AuthenticatedLayout`.
- **`MyJobs/Show.vue`** — dua kolom desktop: kiri "Program Tour" (render `itinerary_days`/`itinerary_hours` kalau sudah disusun, fallback ke `tour.items` dikelompokkan per hari, atau "Program belum diisi."); kanan (sidebar) kartu "Tim Lapangan" (semua assignment — avatar warna per role, tombol `wa.me` ke nomor rekan, kendaraan, jam pickup), "Jadwal Layanan" (dari prop `schedule`), "Informasi Tamu" (hasil masking), "Catatan" (kalau `tour.notes` diisi).
- **`Manifest.vue`** — halaman **berdiri sendiri, tidak memakai `AuthenticatedLayout`** (tanpa sidebar/top bar, konsisten sebagai halaman publik yang dibuka dari link WhatsApp) — header custom gradasi biru tua dengan branding "Welcome Manado · Tour Manifest". Isi kontennya (Program Tour, Tim Lapangan, Jadwal Layanan, Informasi Tamu, Catatan) **hampir identik markup-nya** dengan `MyJobs/Show.vue` — dua file `.vue` terpisah berisi salinan struktur/fungsi yang sama, bukan komponen bersama (lihat §4).

### Route & Controller

| Route | Controller | Middleware | Catatan |
|---|---|---|---|
| `assignments.store/update/destroy` | `AssignmentController` | `role:admin,sales` | Dibuat/diubah dari panel Operasional tour |
| `GET /my-jobs` → `my-jobs` | `MyJobsController::index` | `auth`, `verified`, `role:guide,driver,tour_leader` | Daftar tour milik user login |
| `GET /my-jobs/{tour}` → `my-jobs.show` | `MyJobsController::show` | sda | Detail + `abort_unless` kepemilikan assignment |
| `GET /manifest/{tour}` → `manifest` | `ManifestController::show` | `signed` saja, **di luar** grup `auth` | Publik, tanpa login |

`role:guide,driver,tour_leader` ditegakkan `EnsureUserHasRole` — user login role lain yang membuka `/my-jobs` di-redirect ke `homePath()`-nya (403 hanya untuk request `expectsJson()`).

## 3. Keterkaitan

- **Penjualan Tour** ([penjualan-tour.md](penjualan-tour.md)) — `assignments` adalah relasi milik `Tour`; My Jobs & Manifest murni membaca `tours`, `tour_items`, `invoice_items` yang sudah ada, tidak ada tabel baru.
- **Peran & Akses** ([peran-akses.md](peran-akses.md)) — role `guide`/`driver`/`tour_leader` (grup "field", tanpa cost/profit), dropdown "Link ke Akun" bersumber dari `users` dengan role tsb. Detail hak akses per role: [referensi/roles-permissions.md](../referensi/roles-permissions.md).
- **Customer** ([customer.md](customer.md)) — `maskCustomerForField()` hanya aktif untuk customer tipe Buyer (`tour.guest_name` terisi); customer langsung dikirim apa adanya.
- **Invoice** ([invoice.md](invoice.md)) — `fieldSchedule()` mengambil item dari seluruh invoice tour (semua tahap), sumber "Jadwal Layanan".

## 4. Batasan & jebakan ⚠️

- **Field (guide/driver/tour_leader) hanya melihat jadwal miliknya sendiri, tanpa cost/profit** — ini bagian dari model akses per-role, bukan hal khusus modul ini. Sumber kebenaran lengkap hak akses tiap role: [peran-akses.md](peran-akses.md) dan [referensi/roles-permissions.md](../referensi/roles-permissions.md).
- **Signed URL manifest tidak kedaluwarsa dan tidak bisa dicabut per-tour** — `URL::signedRoute()` dipakai tanpa parameter waktu; link yang pernah dibagikan (mis. lewat grup WhatsApp) tetap valid selamanya, termasuk kalau diteruskan ke orang di luar tim lapangan yang dituju. Tidak ada tombol "regenerate link" — satu-satunya cara mematikan seluruh link manifest sekaligus adalah mengganti `APP_KEY` aplikasi (memutus semua signed URL, bukan cuma satu tour).
- **Harga tidak sepenuhnya tersaring di level data, hanya di level tampilan.** Prop `schedule` (`fieldSchedule()`) memang dirakit tanpa field harga. Tapi prop `tour.items` (relasi `TourItem`, dimuat penuh) **tidak** melalui filter serupa — model `TourItem` tidak punya `$hidden`, jadi kolom harga snapshot (`unit_cost`/`unit_sell`/`line_cost`/`line_sell`) tetap ikut terkirim dalam payload JSON Inertia ke `MyJobs/Show.vue` maupun `Manifest.vue`, walau template Vue-nya cuma merender `description`/`qty`/`nights`. Siapa pun yang membuka DevTools/Network pada manifest publik bisa melihat harga modal & jual per item.
- **Masking customer hanya berlaku untuk tipe Buyer.** Untuk customer langsung, record `Customer` asli dikirim apa adanya — model `Customer` juga tidak punya `$hidden`, jadi seluruh kolomnya (bukan cuma nama/negara/telepon yang dirender UI) ikut ke payload.
- **`MyJobsController::index` tidak membatasi kolom `tours`** yang dikembalikan (tidak ada `->select()` di query utama) — atribut mentah seperti `notes`, `sales_person`, `budget`, `details` JSON ikut terkirim ke frontend walau halaman hanya menampilkan sebagian kecil field.
- **Tidak ada filter status** di `MyJobsController::index` — tour berstatus `cancelled` yang masih punya assignment ke user tetap muncul di daftar My Jobs.
- **`fieldSchedule()` tidak membedakan tahap invoice** — mengambil item dari *semua* invoice tour (baseline/detail/approved), bukan cuma yang sudah di-approve, jadi "Jadwal Layanan" bisa menampilkan jadwal yang secara harga/detail masih bisa berubah oleh sales. Lihat kunci invoice setelah approve di [invoice.md §4](invoice.md#4-batasan--jebakan-️).
- **`Manifest.vue` dan `MyJobs/Show.vue` adalah dua file terpisah dengan markup/fungsi hampir identik** (`itemsByDay`, `hoursForDay`, `fmtDate*`, dll. diduplikasi persis) — bukan komponen bersama, sehingga mesti diselaraskan manual kalau salah satu diubah.
- Assignment tanpa `user_id` (tidak ditautkan ke akun) **hanya bisa dilihat lewat link Manifest**, tidak akan pernah muncul di My Jobs siapa pun — penting diketahui kalau ada guide/driver mengeluh tournya "tidak muncul" padahal sudah ditugaskan.

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP (M4–M5).

## 6. Dokumen terkait

- [pola-ui-desain.md](../referensi/pola-ui-desain.md) — fondasi UI/warna/layout bersama
- [penjualan-tour.md](penjualan-tour.md) — modul induk (assignment sebagai relasi Tour, masking guest name/phone)
- [peran-akses.md](peran-akses.md), [referensi/roles-permissions.md](../referensi/roles-permissions.md) — sumber kebenaran hak akses per role
- [customer.md](customer.md), [invoice.md](invoice.md) — fitur terkait (lihat §3)
