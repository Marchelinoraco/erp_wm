# Modul: Reminder

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Reminder adalah pengingat follow-up sederhana **per akun** — daftar tugas bertanggal milik satu user (biasanya sales), opsional ditautkan ke satu tour yang masih berjalan di pipeline. Sepenuhnya manual: dibuat, diedit, dan dicentang selesai oleh user yang membuatnya sendiri; tidak ada mekanisme otomatis apa pun di kode saat ini yang menghasilkan reminder (tidak ada scheduled command/job terkait — dicek di `app/Console/Commands`, tidak ada yang menyentuh model `Reminder`). Dipakai oleh **admin & sales**.

## Alur Bisnis

### 1. Kepemilikan ketat per user
`ReminderController::index` memfilter `Reminder::where('user_id', $user->id)` — setiap user hanya melihat reminder miliknya sendiri, tidak ada reminder bersama/tim. Ini **berbeda** dari modul Tour yang belum punya pembatasan kepemilikan sama sekali (lihat [01-penjualan-tour.md §Yang Perlu Diperhatikan](01-penjualan-tour.md)): di sini setiap aksi ubah (`update`, `done`, `destroy`) dijaga eksplisit dengan `abort_unless($reminder->user_id === auth()->id(), 403)`.

### 2. Status turunan dari tanggal, bukan kolom
Selain `is_done` (kolom boolean, murni ditentukan user), model punya dua accessor computed:
- `is_overdue` — `!is_done && remind_at->isPast()` (mencakup hari ini juga secara literal, tapi lihat catatan di bawah tentang bagaimana controller memisahkannya dari "hari ini").
- `is_today` — `!is_done && remind_at->isToday()`.

Di `ReminderController::index`, empat statistik (`overdue`, `today`, `upcoming`, `done`) dihitung dari koleksi reminder milik user yang sudah di-fetch: `overdue` sengaja dihitung ulang dengan filter tambahan (`isPast() && !isToday()`) supaya tidak tumpang tindih dengan hitungan `today` — beda dari accessor `is_overdue` mentah di model yang menganggap hari-ini-lewat-tengah-malam sebagai "past" juga. `upcoming` = belum selesai & `remind_at` di masa depan.

### 3. Tautan opsional ke Tour — hanya tour yang masih aktif di pipeline
Dropdown "Link ke Tour" pada form tambah/edit diisi dari `Tour::whereNotIn('status', ['confirmed', 'cancelled'])` — begitu tour sudah `confirmed` atau `cancelled`, ia tidak lagi bisa **dipilih baru** sebagai target reminder (relevan karena reminder ini memang ditujukan untuk follow-up masa negosiasi/quotation, bukan tour yang sudah closing). Reminder yang **sudah** tertaut ke tour yang belakangan berubah jadi `confirmed`/`cancelled` tetap tampil apa adanya (relasi `tour` di-load lewat `with('tour:id,code,title,status')`, tidak diputus).

### 4. Aksi cepat "selesai"
Selain form edit lengkap, ada endpoint pintas `reminders.done` yang hanya men-set `is_done = true` tanpa membuka dialog — dipicu oleh klik lingkaran kosong di sisi kiri tiap baris reminder.

## Model Data

| Tabel | Relasi kunci | Catatan |
|---|---|---|
| `reminders` | `belongsTo User`, `belongsTo Tour` (nullable) | `remind_at` date cast, `is_done` boolean cast |

Kolom migrasi (`2026_06_07_040000_create_reminders_table.php`): `user_id` (cascade delete — reminder ikut terhapus kalau user dihapus), `tour_id` (nullable, null on delete), `title`, `notes` (nullable), `remind_at` (date), `is_done` (default `false`). Index komposit `[user_id, is_done, remind_at]` — sesuai pola query `index()` (filter user + sort `is_done`, `remind_at`).

## Route & Controller

Semua route di bawah `role:admin,sales` (`routes/web.php` baris ~184-191):

| Route | Method | Keterangan |
|---|---|---|
| `reminders.index` | GET `/reminders` | Daftar reminder milik user login + stats + daftar tour aktif untuk dropdown |
| `reminders.store` | POST `/reminders` | Buat reminder baru (otomatis milik `auth()->user()`) |
| `reminders.update` | PATCH `/reminders/{reminder}` | Edit (judul, catatan, tanggal, tour tertaut, `is_done`) — hanya pemilik |
| `reminders.done` | PATCH `/reminders/{reminder}/done` | Pintas tandai selesai — hanya pemilik |
| `reminders.destroy` | DELETE `/reminders/{reminder}` | Hapus — hanya pemilik |

Validasi `store`/`update`: `tour_id` nullable (harus ada di `tours` kalau diisi), `title` wajib (maks 255), `notes` opsional, `remind_at` wajib berupa tanggal valid; `update` tambahan menerima `is_done` boolean.

## Halaman & Komponen (UI)

- **`Reminders/Index.vue`** — layout `max-w-3xl` (lebih sempit dari halaman list biasa karena isinya daftar kartu vertikal, bukan tabel lebar).
- **Banner "booking pending"** di atas halaman — reuse pola yang sama dengan `Dashboard.vue`: tampil kalau `$page.props.pendingBookings > 0`, link ke `bookings.index` (lihat [03-booking.md](03-booking.md)). Menunjukkan dua modul ini saling terhubung di level shared-prop meski fitur intinya independen.
- **Kartu statistik** (4 kolom): Terlambat (merah/`destructive`), Hari ini (oranye), Akan datang (biru), Selesai (abu netral).
- **Daftar reminder** — tiap item kartu dengan **border-kiri berwarna sesuai status** (`statusClass`): selesai → border abu + `opacity-60` + judul dicoret; terlambat → border merah + latar merah tipis; hari ini → border oranye + latar oranye tipis; akan datang (default) → border biru + latar biru tipis. Badge label sejalan: "Selesai"/"Terlambat"/"Hari ini"/"Akan datang".
- **Checkbox lingkaran** di kiri tiap kartu (hanya tampil kalau belum selesai) — klik langsung memanggil `reminders.done` tanpa dialog konfirmasi. Item selesai menampilkan ikon centang statis sebagai gantinya.
- **Isi kartu**: judul, badge status, tanggal (`📅` + format `id-ID` lewat `toLocaleDateString`), tautan tour bila ada (`🔗` + link ke `tours.edit` menampilkan kode tour dan label pipeline lewat peta `PIPELINE_LABEL` — 7 status yang sama dengan pipeline di modul Tour), catatan (kalau diisi).
- **Aksi per kartu**: ikon edit (buka dialog edit) dan ikon hapus (lewat `confirm()` util, baru `router.delete`).
- **Dialog "Tambah Reminder"**: judul (wajib), tanggal remind (wajib, `<input type="date">`), link ke tour (select opsional, opsi `"none"` = tanpa tour, daftar dari prop `tours` yang sudah difilter status aktif), catatan (textarea opsional).
- **Dialog "Edit Reminder"**: field sama + checkbox manual "Tandai sebagai selesai" (jalur alternatif ke `is_done` selain tombol pintas di daftar).
- **Empty state**: pesan "Belum ada reminder..." kalau daftar kosong.

## Yang Perlu Diperhatikan

- **Reminder benar-benar privat per akun** — difilter di query (`index`) dan dijaga otorisasi di setiap mutasi (`update`/`done`/`destroy`). Ini pola pembatasan kepemilikan yang **sudah** ada, kontras dengan modul Tour yang belum menerapkannya sama sekali.
- **Tidak ada reminder otomatis/terjadwal** — murni entri manual; tidak ditemukan command/job di `app/Console` yang membuat baris `Reminder`.
- Dropdown tour di form hanya menyaring berdasarkan **status saat ini** (`whereNotIn(['confirmed','cancelled'])`), dievaluasi ulang tiap kali halaman index di-load — daftar pilihan tour bisa berubah dari satu kunjungan ke kunjungan berikutnya seiring tour lain berpindah status.
- Statistik `overdue`/`today`/`upcoming`/`done` dihitung di PHP dari collection yang sudah difilter per-user (bukan query agregat terpisah) — otomatis konsisten dengan isi daftar yang ditampilkan, tapi berarti scoping-nya sama ketat: tidak ada versi "lintas semua sales" di halaman ini.
