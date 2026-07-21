# Desain: Integrasi Brevo (Email) untuk ERP Welcome Manado

> **Status (21 Jul 2026): L1 + L2 SUDAH DIIMPLEMENTASI & TERUJI di lokal (test 42/42 lolos). Belum dideploy ke VPS.** Dokumen ini menyimpan konteks, keputusan, dan target MVP. L3 tetap ditunda (§7). Sisa pekerjaan = konfigurasi `.env` produksi + jalankan queue worker & scheduler cron di VPS (§9), lalu rotasi key (§8.2). Bug laten `TourEmail` (properti `$subject` menabrak `Mailable` → fatal di PHP 8.4+) ditemukan & diperbaiki saat implementasi L1.
>
> **Keputusan yang sudah dikunci:**
> - Brevo dipakai sebagai **pipa pengiriman email**, bukan pengganti sistem email/reminder yang sudah ada.
> - Dibangun **bertahap 3 layer** (1→2→3). Layer 3 hanya di-*reserve* di desain ini, TIDAK dibangun sekarang.
> - Layer 2 = **notifikasi digest ke Sales**, BUKAN auto-email ke customer (keputusan user 21 Jul 2026).
> - WhatsApp **ditunda** — di luar cakupan dokumen ini.

---

## 1. Konteks & Masalah

ERP Welcome Manado (`https://erp.welcomemanado.com`, Laravel 13 + Inertia + Vue) sudah punya:

- **Email manual per-tour**: `TourEmailController::send()` + `App\Mail\TourEmail` (Mailable, sudah `Queueable`) + view `resources/views/emails/tour.blade.php`. Sales pilih template per status (inquiry, quotation_sent, follow_up, negotiation, confirmed, cancelled) di Tours/Edit lalu klik kirim. Setiap kirim dicatat sebagai `Reminder` (`is_done=true`) untuk audit trail.
- **Reminder follow-up otomatis H+1**: dibuat di `TourController` saat inquiry dibuat & berantai tiap status berubah (fitur kepemilikan tour, commit `b695f78`). Ini murni catatan to-do untuk Sales di tabel `reminders` — **tidak mengirim apa pun**.
- **Queue**: `QUEUE_CONNECTION=database`, tabel `jobs`/`failed_jobs` sudah ada (`0001_01_01_000002_create_jobs_table.php`).

**Masalah inti:** `MAIL_MAILER=log` — semua email hanya ditulis ke `storage/logs/laravel.log`, **tidak pernah benar-benar sampai ke customer**. Ini sudah lama tercatat sebagai Known Issue. Reminder follow-up juga tidak "mendorong" Sales secara aktif; harus buka aplikasi untuk tahu.

**Tujuan:** email benar-benar terkirim dari domain resmi, dan Sales aktif diingatkan untuk follow-up — dengan fondasi yang bisa berkembang ke broadcast/campaign tanpa merombak ulang.

## 2. Target MVP (Definition of Done)

| Layer | Hasil yang bisa dipakai | Status target |
|-------|-------------------------|---------------|
| **L1 — Transaksional** | Email manual dari Tours/Edit benar-benar sampai ke inbox customer via Brevo SMTP, terkirim async (tidak nge-block request), gagal-kirim tercatat di `failed_jobs`. | MVP inti |
| **L2 — Digest Sales** | Tiap pagi, tiap Sales menerima 1 email berisi daftar follow-up yang jatuh tempo/terlewat, dengan link balik ke tour terkait. | MVP inti |
| **L3 — Broadcast** | (Ditunda) Sales kirim promo ke segmen customer via Brevo Campaigns API. | Reserved, tidak dibangun |

MVP dianggap **selesai** jika: (a) email uji benar-benar diterima di kotak masuk nyata dari alamat domain terverifikasi, (b) digest terkirim ke Sales sesuai jadwal dan hanya memuat reminder miliknya yang belum selesai, (c) tidak ada regresi di test suite.

## 3. Non-Goals (di luar cakupan, sengaja)

- WhatsApp / SMS campaign.
- Auto-email ke customer tanpa review Sales (ditolak — brand risk).
- Broadcast/newsletter (L3) — hanya disiapkan celahnya, tidak dikoding.
- Fitur CRM/Deals bawaan Brevo (redundan; sumber kebenaran customer/tour tetap di ERP).
- Webhook status email (Opened/Clicked/Bounced) — opsional, dibahas sebagai *future* di §8, bukan MVP.

## 4. Prinsip Arsitektur (Clean)

1. **Layer 1 & 2 tidak tahu-menahu soal "Brevo".** Keduanya hanya memakai abstraksi `Illuminate\Mail` bawaan Laravel dengan driver SMTP. Brevo cuma nilai konfigurasi di `.env`. Konsekuensi: kalau suatu hari pindah provider (Mailgun, SES, dst), **nol perubahan kode** — cukup ganti `.env`. Ini batas (boundary) yang paling penting.
2. **Layer 3 (nanti) diisolasi di balik satu service.** Semua panggilan REST API Brevo (Contacts, Campaigns) dibungkus `App\Services\Brevo\BrevoClient` dengan interface `App\Contracts\BroadcastGateway`. Tidak ada kode lain yang meng-`Http::` ke Brevo langsung. Bisa di-*fake* saat test.
3. **Konfigurasi terpisah sesuai jalur.** L1/L2 memakai config mail Laravel standar (`config/mail.php` ← `.env`) — nol kode Brevo-spesifik. Kredensial REST API untuk L3 (nanti) dibaca lewat `config/services.php` (blok `brevo`), bukan `env()` tersebar. Keduanya ramah `config:cache`.
4. **Async by default.** Semua pengiriman lewat queue (`->queue()`), tidak `->send()` sinkron. Request Sales tidak pernah menunggu SMTP.
5. **Idempoten & tidak spam.** Digest menandai apa yang sudah dikirim (`notified_at`) supaya tidak dobel dalam satu hari.

## 5. Layer 1 — Email Transaksional

**Perubahan konfigurasi (`.env` production, TIDAK di-commit):**

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login SMTP dari Brevo, mis. 9170cd001@smtp-brevo.com>
MAIL_PASSWORD=<SMTP key Brevo — BUKAN API key xkeysib->
MAIL_FROM_ADDRESS=noreply@welcomemanado.com   # wajib domain terverifikasi di Brevo
MAIL_FROM_NAME="Welcome Manado Tour & Travel"
```

> Catatan: L1 memakai **SMTP key** (di tab Brevo → SMTP), bukan API key (`xkeysib-...`). API key baru dibutuhkan di L3.

**Perubahan kode (minimal, 1 baris inti):**

- `TourEmailController::send()` — ganti `Mail::to($data['to'])->send(new TourEmail(...))` menjadi `->queue(new TourEmail(...))`. Mailable sudah `use Queueable`, jadi tinggal diganti verb-nya. Pencatatan `Reminder` audit-trail tetap seperti sekarang.
- Tidak ada Mailable/param baru. `App\Mail\TourEmail` dipakai apa adanya.

**Ketahanan:** job yang gagal (SMTP down, kredensial salah) otomatis masuk `failed_jobs`. Bisa di-`queue:retry`. Tidak perlu retry-logic buatan sendiri.

## 6. Layer 2 — Digest Follow-up Harian ke Sales

Sesuai keputusan: **notifikasi ke Sales dulu**, bukan auto-email ke customer.

**Perubahan data:**

- Migration `..._add_notified_at_to_reminders.php`: `$table->timestamp('notified_at')->nullable()->after('is_done');` — penanda reminder ini sudah masuk digest hari itu (anti-dobel).

**Komponen baru:**

- `App\Mail\ReminderDigestMail` (Mailable, `Queueable`) — menerima `User $sales` + `Collection $reminders`, render view `resources/views/emails/reminder-digest.blade.php` (daftar: judul reminder, tour terkait + `code`, jatuh tempo, link `https://erp.welcomemanado.com/tours/{id}/edit`).
- `App\Console\Commands\SendReminderDigest` — command `reminders:digest`:
  1. Ambil reminder `is_done=false` yang `remind_at <= today()` (jatuh tempo + terlewat), di-*group by* `user_id`.
  2. Per Sales: kirim 1 `ReminderDigestMail` (via queue), lalu set `notified_at = now()` pada reminder yang masuk digest itu.
  3. Reminder yang belum selesai akan **tetap** muncul di digest hari-hari berikutnya sampai `is_done=true` (sifatnya pengingat berulang; `notified_at` hanya mencegah dobel di hari yang sama bila command jalan >1×).
- Penjadwalan di `routes/console.php`: `Schedule::command('reminders:digest')->dailyAt('07:00')->timezone('Asia/Makassar');` (WITA, sesuai Manado).

**Reuse:** memakai pipa mailer yang sama dengan L1. Tidak ada infrastruktur baru selain 1 command + 1 mailable + 1 kolom.

**Prasyarat operasional:** butuh **queue worker** & **scheduler** berjalan di VPS (lihat §9). Ini konsekuensi wajib dari async + terjadwal.

## 7. Layer 3 — ERP sebagai Control Panel Brevo (RESERVED, belum dibangun)

**Keputusan (21 Jul 2026):** tujuan user = **tidak perlu membuka situs Brevo**, semua dari `erp.welcomemanado`. Maka L3 bukan sekadar "broadcast", tapi **menjadikan ERP remote-control Brevo** untuk audiens marketing. Diambil **live via API** (bukan mirror ke DB ERP).

**Pemisahan customer (bersih, otomatis karena beda sumber data):**

| Menu ERP | Isinya | Sumber |
|----------|--------|--------|
| **Customers** (sudah ada) | Customer transaksional (booking, profit, invoice) | Tabel `customers` (DB ERP) |
| **Marketing / Kontak Brevo** (baru) | ±5.359 audiens marketing + broadcast | Brevo API (live, tidak disimpan lokal) |

Karena "Kontak Brevo" tidak pernah masuk DB ERP, tabel `customers` tetap bersih dan keduanya tak tercampur.

**Arsitektur (live proxy, isolasi penuh):**

- `config/services.php` blok `brevo` → `key` dari `env('BREVO_API_KEY')` (API key `xkeysib-...`, **server-side only**, tidak pernah ke frontend).
- `App\Services\Brevo\BrevoClient` (di balik `App\Contracts\BrevoGateway`) — satu-satunya tempat `Http::withToken(...)->baseUrl('https://api.brevo.com/v3')`. Endpoint: `GET /contacts` (list+paginasi), `POST /contacts` (tambah / dorong customer ERP→Brevo), `GET /contacts` search, `POST /emailCampaigns` (broadcast). Bisa di-`Http::fake()` untuk test.
- `MarketingContactController` + Vue `Marketing/Contacts/Index.vue` (tabel + paginasi + cari; tombol "Tambah kontak" & "Dorong customer ke Brevo"). Akses: admin + sales.
- **Degradasi anggun:** bila Brevo API down/401, halaman tampilkan pesan error, bukan crash.
- **Fase:** (7a) lihat/cari/tambah kontak + dorong customer ERP→Brevo; (7b) broadcast/campaign dari ERP (pilih list/segmen + konten). Consent/opt-in tetap syarat broadcast (§8.7).

L3 **tidak menyentuh** kode L1/L2 (SMTP) — jalur REST API terpisah total dari jalur SMTP. Butuh **rotasi API key** yang sempat ter-expose (§8.2) sebelum dipakai.

## 8. Keamanan (WAJIB)

1. **Kredensial hanya di `.env`, tidak pernah di-commit.** Pastikan `.env` ada di `.gitignore` (sudah standar Laravel). Tidak ada key di kode, seed, atau dokumen ini.
2. **Rotasi key yang sudah bocor.** API key `xkeysib-...` dan SMTP key sempat muncul plaintext di screenshot chat. Perlakukan sebagai **bocor**: setelah setup terverifikasi, **revoke key lama & generate baru** di Brevo, dan hanya key baru yang dipasang di production. Key yang pernah tampil di chat tidak boleh jadi key produksi.
3. **Verifikasi domain pengirim (SPF/DKIM/DMARC).** `MAIL_FROM_ADDRESS` harus di domain yang sudah diverifikasi di Brevo → *Senders, domains, IPs*. Tanpa ini email masuk spam / bisa dispoofing. Ini bagian dari Definition of Done L1.
4. **Authorized IPs (opsional, pengerasan).** Setelah stabil, daftarkan IP VPS (`103.172.205.136`) di Brevo → Security → Authorized IPs lalu aktifkan blocking untuk SMTP/API key. Bukan syarat awal; langkah hardening.
5. **Minim PII di log.** Jangan log body email lengkap. `failed_jobs` menyimpan payload job — cukup, tidak perlu logging tambahan berisi isi email.
6. **Webhook (jika kelak dipakai, §future) wajib diverifikasi.** Endpoint publik penerima event Brevo harus memvalidasi *secret*/signature agar tidak bisa dipalsukan pihak luar. Tidak ada webhook di MVP.
7. **Consent sebelum broadcast (L3).** Tidak ada customer yang masuk campaign tanpa `marketing_opt_in=true`. Transaksional (L1) tidak butuh consent; broadcast (L3) butuh.

**Future (bukan MVP):** webhook `/v3` untuk status Sent/Delivered/Opened/Bounced yang dikaitkan ke `tour_histories` — deteksi email invalid/bounce supaya Sales tahu harus ganti kanal. Ditulis di sini sebagai arah, bukan pekerjaan sekarang.

## 9. Deployment (VPS aaPanel)

1. Update `.env` production (§5) — SMTP Brevo + FROM domain terverifikasi.
2. `php artisan config:cache` (karena config dibaca via `config/services.php`/mail config).
3. `php artisan migrate` — kolom `reminders.notified_at`.
4. **Queue worker** harus jalan permanen (mis. Supervisor / aaPanel daemon): `php artisan queue:work --tries=3`. Tanpa ini, email yang di-`queue()` tidak pernah terkirim.
5. **Scheduler** harus terpasang di cron VPS: `* * * * * cd /www/wwwroot/erp_wm && php artisan schedule:run >> /dev/null 2>&1`. Tanpa ini, digest harian (L2) tidak jalan.
6. `npm run build` bila ada perubahan front-end (L1/L2 minim/none FE).

## 10. Verifikasi & Test

1. **L1 (real inbox):** kirim email uji dari Tours/Edit ke alamat nyata → email diterima, `From` = domain terverifikasi, tidak masuk spam. Cek `queue:work` memproses job (bukan sinkron).
2. **L1 (gagal):** matikan/rusak kredensial → job masuk `failed_jobs`, request Sales tetap responsif.
3. **L2 (feature test):** `SendReminderDigest` — buat reminder jatuh tempo utk Sales A & B → tiap Sales dapat digest berisi HANYA reminder miliknya; `notified_at` terisi; jalankan lagi di hari sama → tidak dobel; reminder `is_done=true` tidak muncul. Pakai `Mail::fake()`.
4. **L2 (scheduler):** `php artisan schedule:list` menampilkan `reminders:digest` di 07:00 WITA.
5. **Regresi:** `php artisan test` penuh tetap hijau.
6. **Keamanan:** konfirmasi tidak ada key di `git log`/kode; key produksi adalah key hasil rotasi (bukan yang pernah muncul di chat).

## 11. Ringkasan Perubahan (checklist implementasi)

- [x] `TourEmailController::send()`: `->send()` → `->queue()` (§5)
- [x] **Fix bug laten** `App\Mail\TourEmail`: properti `$subject` menabrak `Mailable` (fatal PHP 8.4+) → di-rename `$subjectLine`
- [x] Migration `reminders.notified_at` (§6) — sudah `migrate` di lokal
- [x] `App\Mail\ReminderDigestMail` + view `emails/reminder-digest.blade.php` (§6)
- [x] `App\Console\Commands\SendReminderDigest` (`reminders:digest`) (§6)
- [x] Jadwal di `routes/console.php` (dailyAt 07:00 WITA) (§6) — terverifikasi via `schedule:list`
- [x] Test L1/L2 + regresi (§10) — `ReminderDigestTest` (3) + `TourEmailQueueTest` (1), full suite **42/42**
- [x] `.env.example`: struktur Brevo SMTP (placeholder, tanpa kredensial)
- [ ] `.env` production di VPS: SMTP Brevo + FROM `marketing@welcomemanado.com` (§5, §8.3)
- [ ] Deploy: `migrate` + **queue worker** (`queue:work`) + **scheduler cron** (`schedule:run`) aktif di VPS (§9)
- [ ] Test kirim nyata ke inbox (§10.1) + **rotasi SMTP key** yang sempat ter-expose di chat (§8.2)
- [ ] *(L3 — ditunda: `config/services.php` blok brevo, `customers.marketing_opt_in`, `BrevoClient`, UI campaign)*
```
