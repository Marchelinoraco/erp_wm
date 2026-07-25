# Rencana Implementasi

Rencana kerja bertahap (task-by-task, TDD) untuk fitur atau refactor tertentu. Tiap rencana berdiri sendiri dan bisa dieksekusi terpisah.

## Pemisahan aturan invoice per jenis penjualan

Tiga fase pertama dari refactor yang desainnya ada di [../desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md).

| Rencana | Isi | Status |
|---|---|---|
| [invoice-per-jenis-fase-0-karakterisasi.md](invoice-per-jenis-fase-0-karakterisasi.md) | Characterization test — mengunci perilaku invoice yang berjalan sebelum diubah | ✅ selesai di `dev` |
| [invoice-per-jenis-fase-1-aturan.md](invoice-per-jenis-fase-1-aturan.md) | Kontrak `SalesLineInvoiceRule` + registry + 7 aturan, hasil hitung identik | ✅ selesai di `dev` |
| [invoice-per-jenis-fase-2-backfill.md](invoice-per-jenis-fase-2-backfill.md) | Migrasi kolom `sales_line`/`billing_quantities` + backfill `sales_line` | ✅ selesai di `dev`, lulus uji staging §7.8; belum ke production |

## Fitur lain

| Rencana | Isi | Status |
|---|---|---|
| [tour-ownership.md](tour-ownership.md) | Kepemilikan tour per akun Sales + reminder follow-up otomatis | ✅ selesai & terverifikasi (kode); migrasi belum dijalankan di production |
