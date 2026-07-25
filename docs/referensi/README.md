# Referensi

Dokumen acuan yang mencerminkan kondisi kode saat ini. Kalau kode yang mendasarinya berubah, perbarui juga dokumen di sini.

| Dokumen | Isi |
|---|---|
| [pola-ui-desain.md](pola-ui-desain.md) | Fondasi UI/warna/layout dipakai bersama lintas fitur (stack, warna, tipografi, pola layout halaman, kontrol akses di level UI) — acuan lintas-fitur, bukan dokumentasi satu fitur. |
| [roles-permissions.md](roles-permissions.md) | Aturan hak akses per role — sumber kebenaran *siapa boleh apa*. Disusun dari `routes/web.php`, `User.php`, dan middleware `EnsureUserHasRole`. 8 role: admin, sales, accountant, guide, driver, tour_leader, travel_agent, operation. |
