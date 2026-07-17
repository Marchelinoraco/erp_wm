# Kepemilikan Tour per Akun Sales + Reminder Otomatis

> **Status (15 Jul 2026):** Plan disetujui user, implementasi SEMPAT dimulai lalu DIBATALKAN bersih (migration di-rollback, kode di-revert) karena user ingin mengerjakan fitur lain dulu. Saat melanjutkan: kerjakan dari awal mengikuti plan di bawah — tidak ada sisa kode fitur ini di repo saat ini.

## Context

Sales harus hanya melihat & mengelola tour (semua tipe: tour/rental/visa/ticketing/MICE/hotel — semuanya baris `tours`) yang **dia buat sendiri**; admin tetap melihat semua. Saat ini satu-satunya penanda adalah `sales_person` (teks bebas, tidak reliabel) dan **tidak ada cek kepemilikan sama sekali** — sales bisa membuka/mengubah/menghapus tour siapa pun lewat URL langsung (TourController@edit/update/destroy tanpa guard).

Keputusan user (hasil diskusi):
- **A**: sales dibatasi miliknya; **admin melihat semua**.
- **B**: tour lama tanpa pemilik (`created_by` null) **tampil ke semua orang** — tidak ada backfill.
- **C**: daftar difilter + akses edit ditolak 403; **Dashboard & Booking ikut per-akun untuk sales** (operation & admin tetap lihat semua di Booking).
- **D**: reminder otomatis **H+1** saat inquiry dibuat, dan **reminder lanjutan H+1 setiap status berubah** (kecuali status akhir confirmed/cancelled). Reminder per-akun sudah jalan (ReminderController sudah scoped `user_id`).

## Perubahan

### 1. Migration — `tours.created_by`
`database/migrations/2026_07_17_..._add_created_by_to_tours.php`: `$table->foreignId('created_by')->nullable()->after('sales_person')->constrained('users')->nullOnDelete();` Nullable — baris lama tetap null (= milik bersama).

### 2. Model Tour — scope & helper akses (`app/Models/Tour.php`)
```php
// Sales hanya melihat tour miliknya + tour lama tanpa pemilik; admin semua.
public function scopeVisibleTo($query, User $user)
{
    return $user->isAdmin() ? $query
        : $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhereNull('created_by'));
}
public function isAccessibleBy(User $user): bool
{
    return $user->isAdmin() || $this->created_by === null || $this->created_by === $user->id;
}
```
Tambah relasi `creator()` → belongsTo(User::class, 'created_by'). Helper role `isAdmin()`/`isSales()` sudah ada di User model (User.php:42-43).

### 3. TourController (`app/Http/Controllers/TourController.php`)
- `store()`: set `$data['created_by'] = auth()->id();` dan bila `sales_person` kosong, isi otomatis `auth()->user()->name` (supaya filter per-sales admin tetap berguna). **Hook reminder**: setelah `Tour::create`, buat reminder otomatis (lihat §6).
- `index()` (baris ~28): `$query = Tour::visibleTo(auth()->user())->...`. Prop `salesPeople` tetap (dropdown disembunyikan sisi Vue bila hanya 1 opsi — lihat §7).
- `edit()`, `update()`, `destroy()`: baris pertama `abort_unless($tour->isAccessibleBy(auth()->user()), 403);`. (Sub-route per-tour lain — items, invoice, itinerary, email — hanya terjangkau dari halaman edit; guard entry-point ini cukup untuk MVP, dicatat sebagai batasan.)
- `update()` blok `if ($statusChanged)` (baris ~192-223, setelah pembuatan history): hook reminder lanjutan (§6).

### 4. Dashboard per-akun untuk sales (`app/Http/Controllers/DashboardController.php`)
Semua query tour discope; untuk sales tambahkan kondisi `created_by = id OR created_by IS NULL` (konsisten aturan B):
- `$countByStatus` (:23), `$recentTours` (:57), `$upcomingConfirmed` (:64), `totalTours` (:74) → pakai `Tour::visibleTo()` / kondisi where yang sama.
- Query `DB::table` join tours — `$confirmedSell` (:33), `$actualCost` (:39) → tambah `->when($user->isSales(), fn($q) => $q->where(fn($w) => $w->where('tours.created_by', $user->id)->orWhereNull('tours.created_by')))`.
- `arOutstanding`/`apOutstanding`/`cashInMonth` (:48-52) — saat ini agregat invoice/bill global tanpa join tours; untuk sales, tambahkan join/whereHas ke `tours` dengan filter yang sama supaya "YA dashboard per-akun" konsisten menyeluruh.
- Ekstrak closure filter jadi satu helper privat di controller agar tidak diulang 7×.

### 5. Booking per-akun untuk sales saja (`app/Http/Controllers/BookingController.php`)
- `index()` (:16): `Tour::where('status','confirmed')->when(auth()->user()->isSales(), fn($q) => ...filter sama...)`. Operation & admin tetap semua (operation butuh semua tour untuk eksekusi).
- `$stats`/`$allBookings` (:55-63): scope untuk sales via `whereHas('tour', ...)` dengan filter sama.

### 6. Reminder otomatis (per-akun, H+1)
Helper privat di TourController (atau method statis kecil `Reminder::autoFollowUp(Tour $tour, string $context)`):
- **Saat inquiry dibuat** (`store()`): buat `Reminder` untuk **pembuat** (`user_id = auth()->id()`), `tour_id`, `remind_at = now()->addDay()`, `title = 'Follow up ' . $tour->type_label . ' ' . $tour->code`, `notes = 'Dibuat otomatis saat inquiry dibuat.'`.
- **Saat status berubah** (`update()`, dalam blok `$statusChanged`): bila status baru **bukan** `confirmed`/`cancelled` → (1) tandai selesai reminder otomatis lama yang belum done untuk tour itu (cari via `tour_id` + notes prefix 'Dibuat otomatis' + `is_done=false`) supaya tidak menumpuk, (2) buat reminder baru H+1 untuk **pemilik tour** (`$tour->created_by ?? auth()->id()`), title `'Follow up ' . code . ' — status: ' . label baru`. Bila status baru confirmed/cancelled → hanya tandai selesai reminder otomatis lama (perjalanan follow-up selesai).
- ReminderController@index dropdown tour (:23-25): tambah `->visibleTo(auth()->user())` — sales hanya bisa mengaitkan reminder ke tour miliknya.

### 7. Frontend (minim)
- `resources/js/Pages/Tours/Index.vue` (:127-136): bungkus `<Select>` filter sales dengan `v-if="salesPeople.length > 1"` — untuk sales yang hanya melihat miliknya, dropdown tidak relevan.
- Tidak ada perubahan Vue lain — pembatasan terjadi di server (data yang dikirim sudah terfilter), dan halaman edit yang ditolak 403 ditangani halaman error standar.

## Yang TIDAK berubah
- Keuangan (accountant) & MyJobs/Manifest (field) — sudah punya scoping sendiri / di luar cakupan.
- Reminder manual & halaman Reminders — sudah per-akun sejak awal (`user_id` + 403 guard), tidak disentuh kecuali dropdown tour.
- Tour lama (created_by null): tampil & bisa diedit semua sales — sesuai keputusan B; begitu dibuat tour baru, otomatis terkunci ke pembuatnya.

## Verifikasi
1. `php artisan migrate`, `npm run build`.
2. Tinker (transaksi + rollback): buat 2 user sales (A & B) + 1 admin; buat tour oleh A → cek `created_by` terisi; `Tour::visibleTo(B)` tidak memuat tour A; `visibleTo(admin)` memuat semua; tour lama (null) muncul untuk keduanya; `isAccessibleBy` benar untuk 3 kasus.
3. Tinker: panggil `TourController::store()` sebagai sales A → Reminder tercipta (user A, H+1, tour terkait). Ubah status ke `quotation_sent` → reminder lama done, reminder baru tercipta. Ubah ke `confirmed` → reminder lama done, TIDAK ada reminder baru.
4. Browser: login sales A → daftar Tour hanya miliknya + tour lama; buka URL edit tour milik B → 403; dashboard menampilkan angka miliknya saja; halaman Booking hanya tour confirmed miliknya. Login admin → semua tampil; login operation → Booking tampil semua.
5. Regresi: buat tour baru sebagai admin → tetap jalan (created_by = admin), reminder tercipta untuk admin.
