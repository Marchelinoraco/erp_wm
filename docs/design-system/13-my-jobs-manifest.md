# Modul: My Jobs (Tim Lapangan) & Manifest Publik

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama, dan [01-penjualan-tour.md](01-penjualan-tour.md) untuk konsep assignment, masking data buyer, dan jadwal lapangan. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Dua halaman "sisi lapangan" yang memakai mekanisme `assignments`/`maskCustomerForField()`/`fieldSchedule()` dari modul Penjualan Tour tanpa tabel baru:

- **My Jobs** (`/my-jobs`, `/my-jobs/{tour}`) — halaman terautentikasi untuk role `guide`/`driver`/`tour_leader`, menampilkan daftar tour yang **ditugaskan ke akun mereka** dan detail programnya.
- **Manifest publik** (`/manifest/{tour}`) — halaman **tanpa login**, diakses lewat signed URL, dibagikan sales ke guide/driver/tour leader (atau pihak eksternal) lewat WhatsApp, dari tombol "Salin Link" di panel Operasional (`Tours/Edit.vue`).

Kedua halaman menyajikan versi "program tanpa harga" dari tour yang sama — bukan modul terpisah dengan data sendiri.

## Alur Bisnis

1. **Sales membuat assignment** di panel Operasional (`AssignmentController::store`, dipicu dari `OperasionalPanel.vue`). Dialog assignment punya field opsional **"Link ke Akun"** — dropdown berisi `fieldUsers` (user dengan `role` `guide`/`driver`/`tour_leader`, dikirim dari `TourController::edit`). Memilih salah satu otomatis mengisi `user_id`, `person_name`, dan `role` dari akun tsb; kalau dikosongkan, assignment tetap tersimpan hanya dengan `person_name`/`phone` bebas (tanpa akun sistem terhubung).
2. **`user_id` inilah yang menentukan tampil-tidaknya tour di My Jobs** — `MyJobsController::index` memfilter `Tour::whereHas('assignments', fn ($q) => $q->where('user_id', $user->id))`. Assignment yang tidak ditautkan ke akun (`user_id` null) **tidak akan pernah muncul** di My Jobs siapa pun — orang tsb hanya bisa melihat tugasnya lewat link Manifest yang dibagikan manual.
3. **`MyJobsController::show`** mengecek otorisasi eksplisit: `abort_unless($tour->assignments()->where('user_id', $user->id)->exists(), 403)` — jadi meskipun tahu ID tour, user field yang tidak ditugaskan ke tour tsb akan kena 403.
4. **Manifest tidak melakukan pengecekan otorisasi apa pun** di controller — akses murni dikendalikan lewat `middleware('signed')` di level route: URL harus mengandung signature valid yang dibuat lewat `URL::signedRoute('manifest', ['tour' => $tour->id])` (dipanggil di `TourController::edit`, dikirim sebagai prop `manifestUrl`). Signed route ini **dibuat tanpa waktu kedaluwarsa** (bukan `temporarySignedRoute`) — begitu link dibuat, ia berlaku selamanya kecuali `APP_KEY` diganti.
5. Link manifest bisa dibuat/dibagikan **pada status tour apa pun** — `manifestUrl` dihasilkan tiap kali halaman `Tours/Edit.vue` dibuka, tidak digerbangi status `confirmed`. Untuk tour yang belum ada `tour_items`/`itinerary`, halaman manifest hanya menampilkan "Program belum diisi."
6. Kedua halaman memanggil `Tour::maskCustomerForField()` sebelum render, dan `Tour::fieldSchedule()` untuk seksi "Jadwal Layanan" — keduanya dijelaskan di [01-penjualan-tour.md](01-penjualan-tour.md#3-invoice--alur-2-tahap-tahap-1-patokan--tahap-2-rincian--approve) (bagian Guest name/phone) dan §9 (Penugasan lapangan).

## Model Data

Tidak ada tabel baru. Modul ini murni membaca `tours`, `assignments`, `tour_items`, dan (lewat `fieldSchedule()`) `invoice_items` yang sudah ada — lihat tabel model data di [01-penjualan-tour.md](01-penjualan-tour.md#model-data-ringkas). Yang baru hanya dua kolom eager-load berbeda per controller:

- `MyJobsController@index`: `customer:id,name,country` (dibatasi kolom) + `assignments` **difilter** hanya milik user login.
- `MyJobsController@show` & `ManifestController@show`: `customer`, `items`, `assignments` (**semua** assignment tour, bukan cuma milik user — supaya guide bisa lihat kontak driver rekannya dan sebaliknya), `itineraryDays`, `itineraryHours`. `ManifestController` menambahkan urutan eksplisit `orderBy('day_number')->orderBy('sort_order')` pada `items`.

## Route & Controller

| Route | Controller | Middleware | Catatan |
|---|---|---|---|
| `GET /my-jobs` → `my-jobs` | `MyJobsController::index` | `auth`, `verified`, `role:guide,driver,tour_leader` | Daftar tour milik user login |
| `GET /my-jobs/{tour}` → `my-jobs.show` | `MyJobsController::show` | sda | Detail + `abort_unless` kepemilikan assignment |
| `GET /manifest/{tour}` → `manifest` | `ManifestController::show` | `signed` saja, **di luar** grup `auth` | Publik, tanpa login |

`role:guide,driver,tour_leader` ditegakkan oleh `EnsureUserHasRole` — user login dengan role lain (mis. admin/sales) yang membuka `/my-jobs` langsung **di-redirect ke `homePath()`**-nya, bukan 403 (403 hanya untuk request yang `expectsJson()`). Route manifest sengaja ditaruh setelah blok `Route::middleware(['auth','verified'])->group(...)` ditutup di `routes/web.php` (baris ~297-300) supaya benar-benar tidak lewat pengecekan login.

## Halaman & Komponen (UI)

- **`MyJobs/Index.vue`** — grid kartu (1 kolom mobile, 2 kolom desktop) berisi `tour.code`, badge status (palet sama dengan `Tours/Index.vue`, mis. `confirmed` hijau, `negotiation` oranye, `cancelled` merah), judul, rentang tanggal, nama customer (sudah termasuk hasil masking), dan badge role (ungu, `bg-purple-100 text-purple-700`) per assignment milik user. Tidak ada pencarian/filter/paginasi — cuma `orderBy('start_date')` polos dari controller, wajar karena satu user field biasanya hanya punya beberapa tugas aktif. Dibungkus `AuthenticatedLayout` seperti halaman lain, klik kartu → `my-jobs.show`.
- **`MyJobs/Show.vue`** — juga dalam `AuthenticatedLayout` (ada tombol panah kembali ke `/my-jobs` di header), layout dua kolom di desktop (`lg:flex`):
  - Kolom kiri **"Program Tour"**: render `itinerary_days`/`itinerary_hours` kalau sudah disusun (judul per hari + jadwal jam dengan `activity`/`notes`), fallback ke `tour.items` dikelompokkan per `day_number` (ikon emoji per `product_type`: 🏨 hotel, 🚐 transport, 👤 guide, dst) kalau itinerary belum diisi, atau teks "Program belum diisi." kalau keduanya kosong.
  - Kolom kanan (sidebar `lg:w-80`): kartu **"Tim Lapangan"** (semua assignment tour — avatar bulat warna per role, tombol `wa.me` langsung ke nomor rekan kerja, kendaraan, jam pickup), **"Jadwal Layanan"** (dari prop `schedule`, badge per tipe produk dengan warna beda: hotel ungu, transport hijau, lainnya biru), **"Informasi Tamu"** (nama/negara/kontak/WA — hasil masking kalau Buyer), dan **"Catatan"** (kotak kuning `bg-amber-50`, tampil hanya kalau `tour.notes` diisi).
- **`Manifest.vue`** — **halaman berdiri sendiri, tidak memakai `AuthenticatedLayout`** (tidak ada sidebar/top bar aplikasi, tidak ada tombol kembali — konsisten dengan sifatnya sebagai halaman publik yang dibuka langsung dari link WhatsApp, bukan dinavigasi dari dalam app) — header custom gradasi biru tua (`#0f3460` → `#16213e`) dengan branding "Welcome Manado · Tour Manifest", badge status kuning-emas, dan pill info (pax, tanggal, customer). Isi kontennya (Program Tour, Tim Lapangan, Jadwal Layanan, Informasi Tamu, Catatan) **hampir identik markup-nya** dengan `MyJobs/Show.vue` — dua file `.vue` terpisah yang berisi salinan struktur/fungsi yang sama (`itemsByDay`, `hoursForDay`, `fmtDate*`, dll di-duplikasi persis), bukan komponen bersama, sehingga mesti diselaraskan manual kalau salah satu diubah. Ditutup footer disclaimer "Manifest ini hanya untuk penggunaan internal."
- Tombol **"Salin Link"** di panel Operasional memakai `navigator.clipboard.writeText` lalu menampilkan teks "✓ Disalin!" selama 2 detik; tombol "Buka" membuka manifest di tab baru (`target="_blank"`) untuk sales mengecek tampilannya sebelum dibagikan.

## Yang Perlu Diperhatikan

- **Signed URL manifest tidak kedaluwarsa dan tidak bisa dicabut per-tour** — `URL::signedRoute()` dipakai tanpa parameter waktu, jadi link yang pernah dibagikan (mis. lewat grup WhatsApp) tetap valid selamanya, termasuk kalau diteruskan ke orang di luar tim lapangan yang dituju. Tidak ada tombol "regenerate link" — satu-satunya cara mematikan seluruh link manifest sekaligus adalah mengganti `APP_KEY` aplikasi (memutus semua signed URL, bukan cuma satu tour).
- **Harga tidak sepenuhnya tersaring di level data, hanya di level tampilan.** Prop `schedule` (`fieldSchedule()`) memang sengaja dirakit tanpa field harga (`unit_cost`/`unit_sell`/`line_cost`/`line_sell` tidak ikut dipetakan). Tapi prop `tour.items` (relasi `TourItem`, dimuat penuh lewat `$tour->load(['items', ...])`) **tidak** melalui filter serupa — model `TourItem` tidak punya `$hidden`, jadi kolom harga snapshot tetap ikut terkirim dalam payload JSON Inertia ke `MyJobs/Show.vue` maupun `Manifest.vue`, walau template Vue-nya cuma merender `description`/`qty`/`nights`. Siapa pun yang membuka DevTools/Network pada manifest publik bisa melihat harga modal & jual per item.
- **Masking customer hanya berlaku untuk tipe Buyer.** `maskCustomerForField()` cuma mengganti relasi `customer` kalau `tour.guest_name` terisi (lihat [01-penjualan-tour.md](01-penjualan-tour.md)). Untuk customer langsung (bukan Buyer/travel agent), record `Customer` asli dikirim apa adanya — model `Customer` juga tidak punya `$hidden`, jadi seluruh kolomnya (bukan cuma nama/negara/telepon yang dirender UI) ikut ke payload.
- **`MyJobsController::index` tidak membatasi kolom `tours`** yang dikembalikan (tidak ada `->select()` di query utama) — atribut mentah seperti `notes`, `sales_person`, `budget`, `details` JSON ikut terkirim ke frontend walau halaman `Index.vue`/`Show.vue` hanya menampilkan sebagian kecil field.
- **Tidak ada filter status** di `MyJobsController::index` — tour berstatus `cancelled` yang masih punya assignment ke user tetap muncul di daftar Jadwal Saya.
- **`fieldSchedule()` tidak membedakan tahap invoice** — ia mengambil item dari *semua* invoice tour (baseline/detail/approved), bukan cuma yang sudah di-approve, jadi "Jadwal Layanan" bisa menampilkan jadwal yang secara harga/detail masih bisa berubah oleh sales.
- Assignment tanpa `user_id` (tidak ditautkan ke akun) **hanya bisa dilihat lewat link Manifest**, tidak akan pernah muncul di My Jobs siapa pun — penting diketahui kalau ada guide/driver mengeluh tournya "tidak muncul" padahal sudah ditugaskan.
