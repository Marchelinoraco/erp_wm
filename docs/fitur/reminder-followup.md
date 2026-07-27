# Reminder & Follow-up

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026 (otomatis: Jul 2026)
> **Terkait:** [penjualan-tour.md](penjualan-tour.md), [rencana/tour-ownership.md](../rencana/tour-ownership.md)

## 1. Ringkasan

Pengingat follow-up per akun (biasanya sales), baik dibuat manual (tugas bertanggal, opsional ditautkan ke satu tour yang masih aktif di pipeline) maupun otomatis — reminder H+1 dibuat saat inquiry baru masuk dan berantai setiap kali status tour berubah, berhenti begitu tour mencapai confirmed atau cancelled. Setiap user hanya melihat & mengelola reminder miliknya sendiri; reminder yang jatuh tempo/terlewat juga dirangkum ke email digest harian. Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

### 1. Reminder manual — fondasi MVP

`ReminderController::index` memfilter `Reminder::where('user_id', $user->id)` — tiap user hanya melihat reminder miliknya, tidak ada reminder bersama/tim. Setiap aksi ubah (`update`, `done`, `destroy`) dijaga eksplisit dengan `abort_unless($reminder->user_id === auth()->id(), 403)`. Status ditampilkan lewat dua accessor turunan tanggal (bukan kolom): `is_overdue` (`!is_done && remind_at->isPast()`) dan `is_today` (`!is_done && remind_at->isToday()`); statistik `overdue/today/upcoming/done` di halaman dihitung ulang di controller dari koleksi yang sudah difilter per-user, dengan `overdue` sengaja mengecualikan hari ini supaya tidak tumpang tindih dengan `today`. Dropdown "Link ke Tour" pada form tambah/edit (`Reminders/Index.vue`) diisi dari `Tour::visibleTo($user)->whereNotIn('status', ['confirmed', 'cancelled'])` — begitu tour closing, ia tak lagi bisa **dipilih baru**, tapi reminder yang sudah tertaut tetap tampil apa adanya. Tersedia endpoint pintas `reminders.done` (klik lingkaran kosong di kartu) untuk menandai selesai tanpa membuka dialog.

### 2. Reminder otomatis berantai H+1

Dipicu langsung dari `TourController` (pemanggilan method biasa, **bukan** Observer/Event Laravel — tidak ada `app/Observers/TourObserver` atau listener terkait):

- **Saat inquiry dibuat** (`TourController::store` → `createAutoReminder`): begitu `Tour::create` sukses, dibuat satu `Reminder` untuk **pembuat tour** (`user_id = $request->user()->id`), `tour_id` terkait, `remind_at = now()->addDay()` (H+1), `title = 'Follow up ' . $tour->type_label . ' ' . $tour->code`, dan penanda `notes = 'Dibuat otomatis saat inquiry dibuat.'`.
- **Saat status tour berubah** (`TourController::update`, dalam blok `$statusChanged` → `handleStatusChangeReminder`): (1) semua reminder milik tour itu yang masih `is_done = false` **dan** `notes` diawali `'Dibuat otomatis'` langsung ditandai selesai — menutup mata rantai sebelumnya; (2) bila status baru **confirmed** atau **cancelled**, proses berhenti di situ (tidak ada reminder baru — rantai follow-up dianggap tuntas); (3) selain itu, dibuat satu `Reminder` baru H+1 untuk **pemilik tour** (`$tour->created_by ?? auth()->id()` — jatuh balik ke user yang sedang mengubah status bila tour belum punya `created_by`), `title` menyertakan label status baru, `notes = 'Dibuat otomatis saat status berubah.'`.

Reminder otomatis tampil di daftar `Reminders/Index.vue` persis seperti reminder manual — tidak ada badge/marker UI yang membedakan keduanya (diverifikasi: `Index.vue` tidak memproses `notes` atau `notified_at` secara khusus); satu-satunya sinyal "ini otomatis" adalah teks `notes` yang diawali `'Dibuat otomatis'`, dipakai murni sebagai penanda internal oleh `handleStatusChangeReminder` untuk menutup rantai lama.

### 3. Digest email harian

Command terjadwal `reminders:digest` (`app/Console/Commands/SendReminderDigest.php`, didaftarkan di `routes/console.php` via `Schedule::command('reminders:digest')->dailyAt('07:00')->timezone('Asia/Makassar')`) berjalan tiap hari jam 07:00 WITA. Query-nya mengambil semua reminder `is_done = false` dengan `remind_at <= today()` yang **belum** masuk digest hari ini (`notified_at` null atau `< today()` — anti-dobel bila command dijalankan lebih dari sekali sehari), dikelompokkan per `user_id`. Untuk tiap user ditemukan, dikirim satu `ReminderDigestMail` (lewat `Mail::to($user->email)->queue(...)`) berisi daftar reminder jatuh tempo/terlewat miliknya (termasuk yang manual maupun otomatis — tidak dibedakan), lalu semua reminder yang masuk email itu ditandai `notified_at = now()`. Perilaku ini diverifikasi lewat `tests/Feature/ReminderDigestTest.php` (satu email per sales, hanya reminder due miliknya, tidak dobel kirim di hari yang sama).

### 4. Log "email terkirim" (jalur terpisah, bukan reminder masa depan)

`TourEmailController::send` (dipanggil dari tab Email di halaman Tour edit, memakai template subjek/body per status dari `TourEmailController::templates`) mengirim email ke customer lalu **sekaligus** membuat baris `Reminder` dengan `is_done = true` langsung (`title = 'Email terkirim: ' . subjek`, `notes = 'To: ' . alamat`, `remind_at = today()`). Ini murni catatan riwayat "email sudah dikirim" yang numpang tampil di daftar reminder (masuk hitungan stat "Selesai"), **bukan** tugas follow-up baru — karena dibuat langsung `is_done = true`, baris ini tidak pernah tertangkap oleh command `reminders:digest` maupun rantai auto-reminder (notes-nya tidak diawali `'Dibuat otomatis'`).

## 3. Keterkaitan

- **Tour** ([penjualan-tour.md](penjualan-tour.md)) — sumber pemicu utama: pembuatan inquiry dan tiap perubahan status tour langsung memanggil method pembuat/penutup reminder di `TourController` (lihat §2.2). Kepemilikan reminder otomatis mengikuti kepemilikan tour (`tours.created_by`) — lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md) untuk status migrasinya.
- **Email/Brevo** ([email-brevo.md](email-brevo.md), masih kerangka) — keterkaitan nyata tapi terbatas, diverifikasi dari kode: (a) `TourEmailController::send` menulis baris `Reminder` sebagai log tiap kali email manual ke customer dikirim (§2.4); (b) digest `reminders:digest` mengirim email lewat `Mail` facade Laravel — di production jalurnya adalah Brevo **SMTP relay** (`MAIL_HOST=smtp-relay.brevo.com`, lihat `.env.example`), **bukan** Brevo REST API (`BrevoClient`/`BrevoGateway` di `app/Services/Brevo/`) yang dipakai fitur Kontak Marketing & status-email-terkirim — dua jalur Brevo ini terpisah total di kode, tidak saling memanggil.
- **User/Sales** — kepemilikan & visibilitas reminder ketat per `user_id`; route dijaga middleware `role:admin,sales`. Owner reminder otomatis ditentukan Tour (lihat di atas), reminder manual otomatis milik `auth()->user()` saat dibuat.

## 4. Batasan & jebakan ⚠️

- **Penanda rantai otomatis hanya teks bebas** (`notes` diawali `'Dibuat otomatis'`), bukan kolom/flag khusus. Mengedit `notes` reminder otomatis lewat dialog edit manual sehingga tak lagi diawali frasa itu akan membuatnya **tidak pernah ditutup otomatis** oleh perubahan status berikutnya (menumpuk sebagai reminder terbuka). Sebaliknya, reminder manual yang notes-nya kebetulan diawali `'Dibuat otomatis'` akan **ikut disapu jadi selesai** pada perubahan status tour berikutnya — meski dibuat manual oleh user.
- **Kepemilikan reminder otomatis mengikuti `tours.created_by`, yang belum aktif di production.** Selama migrasi `created_by` belum dijalankan di server (lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md)), `$tour->created_by` bernilai null untuk tour lama sehingga `handleStatusChangeReminder` jatuh balik ke `auth()->id()` — reminder follow-up lanjutan jadi milik siapa pun yang kebetulan mengubah status saat itu, bukan otomatis mengikuti "pemilik" tour yang sesungguhnya.
- **Rantai berjalan di setiap perubahan status**, termasuk mundur (mis. `negotiation` → `follow_up`), bukan hanya maju di pipeline — kode hanya mengecek `$data['status'] !== $tour->status`, tidak mengecek arah. Tidak menumpuk reminder terbuka ganda (reminder lama selalu ditutup lebih dulu), tapi setiap perubahan status menambah satu baris reminder baru + satu riwayat "selesai" di daftar.
- **Reminder digest & reminder otomatis memakai tabel dan kolom yang sama** — command `reminders:digest` tidak membedakan reminder manual vs otomatis vs status tour terkait; siapa pun yang mengedit `is_done`/`remind_at` reminder otomatis lewat UI manual turut mengubah perilaku rantai/digest tanpa proteksi tambahan.
- **Drift dokumentasi sumber**: `docs/design-system/04-reminder.md` (belum dihapus, masih dipakai sebagai sumber `email-brevo.md`) menyatakan *"tidak ada mekanisme otomatis apa pun ... tidak ada scheduled command/job terkait"* — pernyataan itu **sudah usang**, ditulis sebelum rantai auto-reminder (`TourController`) dan command `reminders:digest` ditambahkan (migrasi `notified_at` bertanggal 21 Jul 2026, rencana kepemilikan tour terverifikasi 18 Jul 2026). Jangan jadikan `04-reminder.md` acuan untuk perilaku otomatis — dokumen ini yang jadi acuan sekarang.

## 5. Status & yang belum

Reminder manual berjalan sejak fondasi MVP. Reminder otomatis berantai (H+1, mengikuti status tour) selesai & terverifikasi di kode (18 Juli 2026), tapi migrasi `tours.created_by` yang mendasari kepemilikan tour **belum dijalankan di production** — lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

- [penjualan-tour.md](penjualan-tour.md) — pipeline status & kepemilikan tour yang memicu rantai reminder otomatis (lihat §2 di sana)
- [rencana/tour-ownership.md](../rencana/tour-ownership.md) — rencana & status implementasi kepemilikan tour + desain asal reminder otomatis H+1
- [email-brevo.md](email-brevo.md) *(kerangka)* — forward-link: jalur pengiriman digest (Brevo SMTP relay) dan log "email terkirim" dari `TourEmailController`, lihat §3
- [ikhtisar-proyek.md](../ikhtisar-proyek.md) — invarian lintas-fitur
