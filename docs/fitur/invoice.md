# Invoice

> **Status:** ✅ Berjalan (refactor per-jenis 🟡 di `dev`) · **Peran:** admin, sales, accountant · **Sejak:** Jun 2026
> **Terkait:** [logika-pembuatan-invoice/README.md](../logika-pembuatan-invoice/README.md), [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md), [rencana/README.md](../rencana/README.md)

## 1. Ringkasan

Alur penagihan dua tahap (Patokan → Rincian → Setujui) dengan nomor gapless `INV-<tahun>-NNNN` ditetapkan saat disetujui, mendukung multi-mata-uang dengan kurs per pembayaran, dan biaya tambahan yang diajukan sales saat tour berjalan lalu tampil sebagai baris "Additional". Begitu disetujui, invoice terkunci dan menjadi gerbang masuk ke modul Keuangan (AR) serta memicu pembuatan Bill draft otomatis untuk tiap item bersupplier. Dipakai oleh admin & sales (pembuatan/persetujuan) dan accountant (status/pembayaran/laporan); detail lengkap alur per-tahap ada di deep-dive [`logika-pembuatan-invoice/`](../logika-pembuatan-invoice/README.md).

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Berjalan penuh di production. Sedang berjalan paralel refactor **"Pemisahan Aturan Invoice per Jenis Penjualan"** (kontrak `SalesLineInvoiceRule` per jenis penjualan): Fase 0 (characterization test) dan Fase 1 (kontrak + registry + 7 aturan) selesai di `dev`; Fase 2 (migrasi kolom + backfill `sales_line`) selesai di `dev` dan lulus uji staging, tapi **belum dijalankan ke production**. Fase 3 (pengali bisa diedit + label per jenis), Fase 4 (label di PDF), dan Fase 5 (pemecahan definisi frontend) belum ada plan. Lihat [desain/pemisahan-invoice-per-jenis.md](../desain/pemisahan-invoice-per-jenis.md) dan [rencana/README.md](../rencana/README.md).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
