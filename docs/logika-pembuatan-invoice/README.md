# Logika Pembuatan Invoice — Peta Dokumen

> **Status (24 Jul 2026): dokumentasi perilaku sistem yang berjalan.** Ditulis dari pembacaan kode, bukan dari rencana. Setiap kondisi di sini merujuk berkas dan baris yang dapat diperiksa sendiri. Bukan dokumen desain — tidak ada usulan perubahan kecuali di [10-temuan.md](10-temuan.md) yang ditandai jelas.

Dokumen ini memecah alur pembuatan invoice menjadi kondisi-kondisi terpisah, satu berkas per tahap.

## Urutan baca

| # | Dokumen | Isi |
|---|---|---|
| 01 | [Prasyarat & Pembuatan](01-prasyarat-dan-pembuatan.md) | Kapan invoice boleh dibuat, nilai awal, penomoran |
| 02 | [Tahap 1 — Proforma](02-tahap-1-proforma.md) | Mata uang, harga/pax, baris deskripsi, rekening bank |
| 03 | [Tahap 2 — Patokan](03-tahap-2-patokan.md) | Kunci patokan, samakan patokan, arti `baselineMatched` |
| 04 | [Rincian Profit](04-rincian-profit.md) | Item internal, tanggal wajib, tempel massal, autosave |
| 05 | [Tahap 3 — Persetujuan](05-tahap-3-persetujuan.md) | Gerbang ke Keuangan, kurs, efek samping |
| 06 | [Penguncian Setelah Disetujui](06-penguncian-setelah-disetujui.md) | Apa yang terkunci, apa yang masih boleh |
| 07 | [Pembayaran](07-pembayaran.md) | DP, pelunasan, transisi status |
| 08 | [Perbedaan per Tipe Penjualan](08-perbedaan-per-tipe.md) | Yang berbeda dan yang ternyata sama |
| 09 | [Matriks Kondisi](09-matriks-kondisi.md) | Tabel rujukan cepat semua kondisi |
| 10 | [Temuan](10-temuan.md) | Celah dan perilaku yang perlu diketahui |

## Peta alur

```
                    tour.status = 'confirmed'
                              │
                    ┌─────────▼─────────┐
                    │  + Buat Invoice   │  ← satu tour hanya boleh satu invoice
                    └─────────┬─────────┘
                              │  status: draft
                              │  number: INV-<tahun>-<kode tipe>-NNNN
              ┌───────────────▼───────────────┐
              │  TAHAP 1 · Proforma           │  baseline_total = 0
              │  mata uang, harga/pax,        │
              │  deskripsi, rekening bank     │
              └───────────────┬───────────────┘
                              │  syarat: total > 0
                    ┌─────────▼─────────┐
                    │  Kunci Patokan    │
                    └─────────┬─────────┘
              ┌───────────────▼───────────────┐
              │  TAHAP 2 · Patokan Terkunci   │  baseline_total > 0
              │  isi Rincian Profit           │
              │  (tidak mengubah tagihan)     │
              └───────────────┬───────────────┘
                              │  syarat: baseline_total > 0
                              │          total proforma = patokan
                              │          kurs terisi (non-IDR)
                    ┌─────────▼─────────┐
                    │     Setujui       │
                    └─────────┬─────────┘
              ┌───────────────▼───────────────┐
              │  TAHAP 3 · Sudah di Keuangan  │  approved_at terisi
              │  finance_number, Bill draft   │  → TERKUNCI
              │  pembayaran dibuka            │
              └───────────────────────────────┘
```

## Tiga hal yang paling sering disalahpahami

**1. Item Rincian Profit tidak memengaruhi tagihan customer.** Tagihan murni `unit_price × pax`. Menambah sepuluh baris hotel tidak mengubah sepeser pun angka yang dilihat customer. Detail di [04-rincian-profit.md](04-rincian-profit.md).

**2. Alur invoice hampir identik untuk ketujuh tipe penjualan.** Yang benar-benar berbeda hanya kode nomor, rumus profit internal, dan panel pendamping. Detail di [08-perbedaan-per-tipe.md](08-perbedaan-per-tipe.md).

**3. Syarat status `confirmed` hanya ditegakkan di UI.** Server tidak memeriksanya. Detail di [10-temuan.md](10-temuan.md).

## Berkas sumber

| Berkas | Peran |
|---|---|
| [`app/Http/Controllers/InvoiceController.php`](../../app/Http/Controllers/InvoiceController.php) | Alur utama: buat, proforma, patokan, setujui, hapus, PDF |
| [`app/Http/Controllers/InvoiceItemController.php`](../../app/Http/Controllers/InvoiceItemController.php) | Item Rincian Profit |
| [`app/Http/Controllers/InvoicePaymentController.php`](../../app/Http/Controllers/InvoicePaymentController.php) | Pembayaran / DP |
| [`app/Models/Invoice.php`](../../app/Models/Invoice.php) | Tahap turunan, penomoran, `syncProformaTotal()` |
| [`app/Models/InvoiceItem.php`](../../app/Models/InvoiceItem.php) | `fromProduct()`, `DATED_TYPES` |
| [`resources/js/Components/Tours/InvoicesPanel.vue`](../../resources/js/Components/Tours/InvoicesPanel.vue) | Seluruh antarmuka sales |
| [`resources/views/invoice.blade.php`](../../resources/views/invoice.blade.php) | PDF ke customer |
