# 05 — Tahap 3 · Persetujuan

[← 04 Rincian Profit](04-rincian-profit.md) · [Peta](README.md) · Berikutnya: [06 — Penguncian](06-penguncian-setelah-disetujui.md)

---

Ini gerbang satu arah. Setelah disetujui, invoice masuk Keuangan dan tidak dapat diubah lagi oleh sales.

Endpoint: `POST /invoices/{invoice}/approve` → `InvoiceController::approve()`

## 5.1 Semua kondisi yang harus terpenuhi

| # | Kondisi | Ditegakkan di | Bila gagal |
|---|---|---|---|
| K-60 | `approved_at` masih `null` | `ensureNotApproved()` | "Invoice sudah disetujui, tidak bisa diubah lagi." |
| K-61 | `baseline_total > 0` | `approve()` | "Kunci patokan terlebih dahulu sebelum menyetujui." |
| K-62 | `exchange_rate` terisi bila non-IDR | validasi Laravel | galat validasi field |
| K-63 | `exchange_rate > 0` | `numeric\|gt:0` | galat validasi field |
| K-64 | UI: `baselineMatched(inv)` bernilai true | tombol `:disabled` | tombol mati, tanpa pesan |
| K-65 | UI: `proformaTotal(inv.id) > 0` | tombol `:disabled` | tombol mati, tanpa pesan |

Perhatikan K-61 diperiksa **setelah** `syncProformaTotal()` dipanggil, sehingga total selalu yang terbaru saat pemeriksaan berlangsung.

Aturan validasi kurs dibangun dinamis:

```php
$isIdr = ($invoice->currency ?: 'IDR') === 'IDR';

$data = $request->validate([
    'exchange_rate' => ($isIdr ? 'nullable' : 'required') . '|numeric|gt:0',
]);
```

## 5.2 Dua jalur menurut mata uang

### Jalur IDR

| Langkah | Perilaku |
|---|---|
| 1 | `approve(inv)` mendeteksi mata uang IDR |
| 2 | Dialog konfirmasi tunggal: "Setelah disetujui, invoice masuk ke Keuangan dan tidak bisa diubah lagi." |
| 3 | Bila disetujui → `afterFlush()` → kirim tanpa `exchange_rate` |
| 4 | Server memakai `rate = 1.0` |

### Jalur non-IDR — dua lapis konfirmasi

| Langkah | Perilaku |
|---|---|
| 1 | Dialog input kurs terbuka, diprefill dari `exchangeForms` bila ada |
| 2 | Pratinjau nilai IDR dihitung langsung: `proformaTotal × kurs` |
| 3 | Dialog kurs **ditutup dulu**, lalu `await nextTick()` |
| 4 | Konfirmasi kedua menampilkan `1 <CUR> = Rp <kurs>` dan nilai akhir ke Keuangan |
| 5 | Bila **dibatalkan** → dialog kurs dibuka lagi dengan nilai tetap terisi |
| 6 | Bila disetujui → `afterFlush()` → kirim dengan `exchange_rate` |

Langkah 3 bukan kosmetik. Komentar di kode menjelaskan sebabnya:

> Tutup dialog kurs dulu — dua Dialog terbuka bersamaan bikin overlay dialog pertama menutupi & memblokir klik pada tombol di modal konfirmasi kedua.

Dan alasan konfirmasi berlapis:

> Jeda konfirmasi terakhir — kurs salah tidak bisa dikoreksi lagi setelah masuk Keuangan.

## 5.3 Yang terjadi di dalam transaksi

Seluruhnya dalam satu `DB::transaction()`:

```php
$rate     = $isIdr ? 1.0 : (float) $data['exchange_rate'];
$totalIdr = (float) $invoice->total * $rate;
```

| # | Perubahan | Nilai |
|---|---|---|
| 1 | `exchange_rate` | `1.0` untuk IDR, atau kurs yang diinput |
| 2 | `total_idr` | `total × rate` |
| 3 | `status` | `sent` |
| 4 | `approved_at` | `now()` |
| 5 | `approved_by` | `auth()->id()` |
| 6 | `finance_number` | nomor lama bila ada, atau `Invoice::nextFinanceNumber()` |
| 7 | Bill draft | `Bill::createMissingFromInvoice($invoice)` |

Baris 6 memakai `??`, sehingga menyetujui ulang tidak akan menerbitkan nomor keuangan baru — meski dalam praktik K-60 sudah menghalangi jalur itu.

## 5.4 Nomor keuangan

```
INV - 2026 - 0001
 │     │      └── urut gapless, reset per tahun
 │     └───────── tahun saat DISETUJUI
 └─────────────── prefiks tetap
```

Berbeda dari `number` yang mengandung kode tipe penjualan, `finance_number` **tidak** membedakan tipe. Urutannya murni mengikuti kapan invoice masuk Keuangan.

| # | Kondisi | Perilaku |
|---|---|---|
| K-66 | Belum ada nomor keuangan tahun ini | mulai `0001` |
| K-67 | Sudah ada | tertinggi + 1 |
| K-68 | Dua persetujuan bersamaan | `lockForUpdate()` mencegah nomor kembar |
| K-69 | Ada invoice ter-soft-delete | tetap dihitung (`withTrashed()`) — nomor tidak dipakai ulang |

Kata "gapless" di komentar kode berarti tidak ada lompatan nomor akibat tipe penjualan, bukan jaminan kekebalan terhadap transaksi yang dibatalkan.

## 5.5 Pembuatan Bill otomatis

`Bill::createMissingFromInvoice()` menelusuri setiap item invoice:

| # | Kondisi | Perilaku |
|---|---|---|
| K-70 | Item punya produk **dan** produk punya `supplier_id` | dibuatkan Bill draft |
| K-71 | Item tanpa produk (hasil tempel massal) | dilewati |
| K-72 | Item punya produk tanpa supplier | dilewati |
| K-73 | Bill untuk `invoice_item_id` itu sudah ada | `firstOrCreate` tidak menggandakan |

Nilai Bill yang dibuat:

| Kolom | Nilai |
|---|---|
| `tour_id` | dari invoice |
| `supplier_id` | dari produk |
| `description` | `item.description` |
| `category` | dipetakan dari `product_type` lewat `Bill::PRODUCT_TYPE_TO_CATEGORY`, jatuh ke `other` |
| `date` | `invoice.date` |
| `amount` | **`0`** — akuntan yang mengisi |
| `status` | `unpaid` |

Nominal sengaja 0. Sistem hanya menyiapkan kerangka tagihan supplier; angkanya diisi akuntan setelah tagihan asli diterima.

## 5.6 Catatan riwayat

Setelah transaksi berhasil, satu entri ditulis ke riwayat tour:

```
Invoice INV-2026-11-0007 disetujui & masuk Keuangan sebagai INV-2026-0042 (USD 3.500 (≈ IDR 56.000.000)).
```

| # | Kondisi | Format nilai |
|---|---|---|
| K-74 | Mata uang IDR | hanya nominal invoice |
| K-75 | Mata uang non-IDR | nominal invoice + `(≈ IDR ...)` |

`created_by` diisi nama pengguna yang menyetujui, atau `'Sistem'` bila konteks autentikasi tidak tersedia.

## 5.7 Setelah ini

- Invoice terkunci untuk sales → [06-penguncian-setelah-disetujui.md](06-penguncian-setelah-disetujui.md)
- Blok pembayaran terbuka → [07-pembayaran.md](07-pembayaran.md)
- PDF Rincian Profit internal menjadi tersedia (`abort_unless($invoice->approved_at, 403)`)
