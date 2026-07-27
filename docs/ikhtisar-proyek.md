# Welcome Manado ERP — Ikhtisar Proyek

> **Update: 2026-07-25.** Dokumen ini adalah gambaran umum & status proyek — titik masuk untuk memahami sistem secara keseluruhan. Untuk peta seluruh dokumentasi, lihat [README.md](README.md). Untuk *bagaimana tiap fitur bekerja*, lihat [design-system/](design-system/README.md).
>
> Menggantikan `PROJECT_STATUS.md` dan `WELCOME_MANADO_MVP_SPEC.md` (dua dokumen lama tertanggal 7 Juni yang saling tumpang tindih dan statusnya sudah usang).

**Stack:** Laravel 13 · Inertia.js v2 · Vue 3 + Vite · MySQL 8 · shadcn/vue · Tailwind · Laravel Breeze · barryvdh/laravel-dompdf · Ziggy · Brevo SMTP (email)

---

## 1. Apa ini

ERP internal untuk operasi travel Welcome Manado: dari inquiry customer → penyusunan tour + costing → quotation → invoice → keuangan (AR/AP + pembukuan) → penugasan tim lapangan. Sudah berjalan di production dan dipakai sales sehari-hari.

Alur inti: **Inquiry → Tour Builder (costing) → Quotation (PDF) → Invoice 2-tahap → Keuangan (profit riil) → My Jobs (tim lapangan).**

---

## 2. Status

Fondasi MVP (M1–M7) **selesai seluruhnya**, lalu sistem terus berkembang melampaui cakupan MVP awal (terutama modul Keuangan dan email). Riwayat perubahan yang terasa pengguna ada di [`CHANGELOG.md`](../CHANGELOG.md).

| # | Milestone | Status |
|---|---|---|
| M1 | Master Data (Supplier, Product, Customer) | ✅ |
| M2 | Tour Builder + panel costing realtime | ✅ |
| M3 | Quotation PDF branded + Dashboard pipeline | ✅ |
| M4 | Operations (assignments, manifest) | ✅ |
| M5 | Peran & Akses + My Jobs | ✅ |
| M6 | Keuangan (profit riil, AR/AP) | ✅ |
| M7 | Katalog Tour + Sumber Inquiry | ✅ |

**Berkembang setelah MVP** (lihat CHANGELOG untuk tanggalnya):

- **Keuangan meluas jauh melampaui AR/AP** — kini mencakup pembukuan penuh: Jurnal, Buku Besar, Laba/Rugi akuntansi, Neraca, Aset Tetap, Hutang, dan Koreksi Fiskal. (Spec MVP lama menyatakan "jangan bangun general ledger"; kenyataannya modul ini sudah dibangun — lihat [design-system/11](design-system/11-keuangan-pembukuan.md) & [design-system/12](design-system/12-keuangan-aset-fiskal-pinjaman.md).)
- **Invoice 2-tahap** (patokan → rincian → setujui → masuk Keuangan) dengan nomor gapless `INV-<tahun>-NNNN` ditetapkan saat disetujui.
- **Multi-mata-uang** pada invoice proforma, dengan kurs bisa berbeda per pembayaran (DP vs pelunasan).
- **Biaya tambahan** — Sales mengajukan saat tour berjalan, akuntan verifikasi → tercatat sebagai Bill, dan tampil sebagai baris "Additional" pada invoice.
- **Kepemilikan tour per Sales** + reminder follow-up otomatis H+1 berantai mengikuti status.
- **Email Brevo** — quotation/invoice benar-benar terkirim ke customer lewat SMTP di latar belakang; digest follow-up harian ke Sales.
- **Soft delete** pada data finansial (penghapusan tidak permanen).
- **8 role** (dari 6 semula): bertambah `travel_agent` dan `operation`.

**Pra-produksi / catatan:** deploy sudah jalan di production. Backup DB otomatis harian (cron) sudah aktif.

---

## 3. Konsep inti (WAJIB dipahami — jangan sampai lupa)

1. **Snapshot harga** — saat produk ditambah ke tour, `cost`/`sell` produk **disalin** ke `tour_items.unit_cost`/`unit_sell`. Bukan referensi harga live. Mengubah harga produk tidak boleh mengubah total tour lama. (Kesalahan #1 yang harus dihindari.)
2. **Profit = query, bukan modul** — `total_cost`, `total_sell`, `profit`, `margin` adalah accessor di model `Tour`, dihitung dari sum `tour_items`.
3. **`line_cost` & `line_sell` = generated columns** MySQL (`qty * nights * unit_*`). Jangan diisi manual → wajib **MySQL 8+**.
4. **Pipeline status** `tours.status`: `inquiry → quotation_draft → quotation_sent → follow_up → negotiation → confirmed → cancelled`.
5. **Perkiraan vs Aktual** — angka tour = perkiraan (snapshot). Modul keuangan menambahkan biaya **aktual** (`bills`) → **profit riil** = `total_sell − SUM(bills.amount)`.
6. **Invoice tidak dihitung ulang setelah disetujui** — begitu invoice approved, totalnya terkunci; biaya tambahan ditambahkan langsung, di luar jalur perhitungan proforma. Menghitung ulang invoice yang sudah disetujui akan menghapus uang yang sudah ditagihkan. (Batasan kritis untuk semua pekerjaan invoice — lihat [logika-pembuatan-invoice/06](logika-pembuatan-invoice/06-penguncian-setelah-disetujui.md).)

---

## 4. Jenis penjualan

Tujuh jenis, masing-masing dengan perilaku costing/tagihan sendiri: **Tour, Rental (Transport), Jasa Guide, Visa/Document, Ticketing, MICE, Hotel.** Kode tour dan nomor invoice mengikuti jenis penjualannya. Detail per jenis: [fitur/penjualan-tour.md](fitur/penjualan-tour.md) dan [logika-pembuatan-invoice/08](logika-pembuatan-invoice/08-perbedaan-per-tipe.md).

---

## 5. Peran & akses

**8 role** di enum `users.role`: `admin`, `sales`, `accountant`, `guide`, `driver`, `tour_leader`, `travel_agent`, `operation`.

Akses ditegakkan lewat middleware `role:<daftar>` (`EnsureUserHasRole`) di tiap route; role yang tidak berhak ditolak 403 atau diarahkan ke halaman utamanya (`User::homePath()`). Sidebar merender menu berbeda total per role.

Ringkas: `admin/sales` = penjualan penuh · `accountant` = Keuangan · `guide/driver/tour_leader` = hanya "Jadwal Saya" (tanpa cost/profit) · `travel_agent` = "Produk Saya" · `operation` = "Booking".

**Sumber kebenaran hak akses:** [referensi/roles-permissions.md](referensi/roles-permissions.md).

---

## 6. Keuangan

| Metrik | Rumus |
|---|---|
| Biaya aktual / tour | `SUM(bills.amount)` |
| **Profit riil** / tour | `total_sell − biaya aktual` |
| Cost variance | `biaya aktual − total_cost` (+ = boros) |
| Piutang (AR) | `SUM(invoices.total) − SUM(invoice_payments.amount)` |
| Hutang (AP) | `SUM(bills.amount) − SUM(bill_payments.amount)` |

Di atas AR/AP, modul keuangan menyediakan pembukuan (Jurnal, Buku Besar, Laba/Rugi, Neraca, Aset Tetap, Hutang, Koreksi Fiskal). Detail: [design-system/10–12](design-system/10-keuangan-ar-ap.md).

---

## 7. Sedang dikerjakan

**Pemisahan Aturan Invoice per Jenis Penjualan** — memberi tiap jenis penjualan aturan hitung tagihannya sendiri lewat kontrak `SalesLineInvoiceRule` + registry, sekaligus memperbaiki kolom "Harga / pax" yang dipaksakan pada jenis yang sebenarnya ditagih per hari/unit.

- Desain & protokol keamanan data: [desain/pemisahan-invoice-per-jenis.md](desain/pemisahan-invoice-per-jenis.md)
- Rencana per fase: [rencana/](rencana/README.md)

| Fase | Isi | Status |
|:---:|---|---|
| 0 | Characterization test — kunci perilaku lama | ✅ di `dev` |
| 1 | Kontrak + registry + 7 aturan, hasil hitung identik | ✅ di `dev` |
| 2 | Migrasi kolom + backfill `sales_line` | ✅ di `dev` — lulus uji staging §7.8, **belum dijalankan ke production** |
| 3 | Pengali bisa diedit + label per jenis (fase pertama yang mengubah perilaku terlihat) | ⬜ belum ada plan |
| 4 | Label jenis penjualan di PDF | ⬜ |
| 5 | Definisi jenis penjualan dipecah per berkas di frontend | ⬜ |

---

## 8. Cara menjalankan (lokal)

```bash
composer install
npm install
cp .env.example .env          # set DB_DATABASE, kredensial Brevo, dst
php artisan key:generate
php artisan migrate
npm run dev                    # atau: npm run build
php artisan serve
```

Wajib **MySQL 8.0+** (generated columns).

---

## 9. Di luar cakupan (belum / tidak dibangun)

- Mesin pajak / e-Faktur, rekonsiliasi bank otomatis
- Aplikasi mobile khusus driver/guide (cukup login + My Jobs)
- Modul AI
- Seasonal pricing terjadwal penuh (harga produk berperiode sudah ada sebagian)
