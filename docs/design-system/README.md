# Design System — Welcome Manado ERP

Dokumentasi teknis per modul fitur: alur bisnis, model data, route/controller, dan pola UI yang dipakai. Ditulis dari pembacaan langsung kode (bukan spekulasi) per 17 Jul 2026 — kalau ada perubahan kode signifikan, perbarui juga dokumen terkait.

Mulai dari [pola-ui-desain.md](../referensi/pola-ui-desain.md) — pola UI/warna/layout bersama yang tidak diulang di tiap dokumen modul.

## Penjualan
- ~~01 — Penjualan / Tour~~ — bermigrasi ke [fitur/penjualan-tour.md](../fitur/penjualan-tour.md), [fitur/quotation.md](../fitur/quotation.md), [fitur/invoice.md](../fitur/invoice.md), [fitur/mice-template.md](../fitur/mice-template.md)
- [02 — Channel Manager & Produk Agent](02-channel-manager-produk-agent.md) — review harga dari travel agent eksternal + halaman "Produk Saya"

## Operasional
- [03 — Booking](03-booking.md) — eksekusi pemesanan ke supplier setelah tour confirmed
- [04 — Reminder](04-reminder.md) — pengingat follow-up per akun sales

## Data Master
- [05 — Customers](05-customers.md) — termasuk tipe customer *Buyer* (travel agent pembeli tour)
- [06 — Produk & Harga](06-produk.md) — katalog produk internal + periode harga
- [07 — Suppliers](07-suppliers.md) — termasuk keterkaitan ke akun `travel_agent`
- [08 — Rekening](08-rekening.md) — Bank Account (tampil di PDF invoice) vs Cash Account (akun kas pencatatan)

## Administrasi
- [09 — Kelola Akun](09-kelola-akun.md) — manajemen user & role (admin only)

## Keuangan
- ~~10 — AR & AP~~ — bermigrasi ke [fitur/keuangan-ar-ap.md](../fitur/keuangan-ar-ap.md)
- ~~11 — Pembukuan & Laporan~~ — bermigrasi ke [fitur/keuangan-pembukuan.md](../fitur/keuangan-pembukuan.md)
- ~~12 — Aset Tetap, Fiskal & Pinjaman~~ — bermigrasi ke [fitur/keuangan-aset-fiskal-pinjaman.md](../fitur/keuangan-aset-fiskal-pinjaman.md)

## Tim Lapangan
- [13 — My Jobs & Manifest](13-my-jobs-manifest.md) — jadwal tugas guide/driver/tour leader + halaman manifest publik

---

Dokumen lain yang relevan (di luar folder ini): [roles-permissions.md](../referensi/roles-permissions.md) (hak akses tiap role), [tour-ownership.md](../rencana/tour-ownership.md) (rencana fitur yang ditunda).
