# 03 — Tahap 2 · Patokan

[← 02 Proforma](02-tahap-1-proforma.md) · [Peta](README.md) · Berikutnya: [04 — Rincian Profit](04-rincian-profit.md)

---

"Patokan" (`baseline_total`) adalah **snapshot total tagihan pada saat dikunci**. Fungsinya satu: memastikan angka yang disetujui adalah angka yang sengaja dikunci, bukan angka yang bergeser diam-diam.

Endpoint: `PATCH /invoices/{invoice}/baseline` → `InvoiceController::lockBaseline()`

## 3.1 Kondisi mengunci patokan

| # | Kondisi | Ditegakkan di | Pesan galat |
|---|---|---|---|
| K-20 | `approved_at` masih `null` | `ensureNotApproved()` | "Invoice sudah disetujui, tidak bisa diubah lagi." |
| K-21 | `total > 0` setelah disinkronkan | `lockBaseline()` | "Isi harga proforma terlebih dahulu sebelum mengunci patokan." |
| K-22 | UI: `proformaTotal(inv.id) > 0` | tombol `:disabled` | tombol mati, tanpa pesan |

Urutan eksekusinya penting:

```php
$this->ensureNotApproved($invoice);
$invoice->syncProformaTotal();          // hitung ulang DULU

if ((float) $invoice->total <= 0) {
    throw ValidationException::withMessages([...]);
}

$invoice->update(['baseline_total' => $invoice->total]);
```

`syncProformaTotal()` dipanggil lebih dulu, sehingga patokan selalu mengunci angka **terbaru** — termasuk bila `pax` tour berubah sejak proforma terakhir disimpan.

## 3.2 Efek pada tahap

Tahap tidak disimpan sebagai kolom. Ia diturunkan (`Invoice::getStageAttribute()`):

```php
if ($this->is_approved)  return 'approved';
return $this->baseline_total > 0 ? 'detail' : 'baseline';
```

| `approved_at` | `baseline_total` | Tahap | Badge |
|---|---|---|---|
| `null` | `0` | `baseline` | Tahap 1 · Proforma (biru) |
| `null` | `> 0` | `detail` | Patokan Terkunci (kuning) |
| terisi | apa pun | `approved` | Sudah di Keuangan (hijau) |

Panel Vue menghitung ulang logika yang sama di `stage(inv)` agar badge berubah tanpa menunggu respons server.

## 3.3 "Samakan Patokan" — mengunci ulang

Setelah tahap `detail`, tombol berganti menjadi **Samakan Patokan** yang memanggil endpoint yang sama. Tidak ada pembatasan berapa kali boleh dipanggil selama invoice belum disetujui.

| # | Kondisi | Tombol yang tampil |
|---|---|---|
| K-23 | `stage === 'baseline'` | "Kunci Patokan", disabled bila total ≤ 0 |
| K-24 | `stage === 'detail'` | "Samakan Patokan" (selalu aktif) + "Setujui" |

## 3.4 `baselineMatched()` — penjaga persetujuan

```js
function baselineMatched(inv) {
    return Math.abs(proformaTotal(inv.id) - Number(inv.baseline_total)) < 0.01
}
```

Membandingkan total proforma **saat ini di layar** dengan patokan yang tersimpan, dengan toleransi 0,01 untuk menghindari selisih pembulatan pecahan.

| # | Kondisi | Akibat |
|---|---|---|
| K-25 | Selisih < 0,01 | tombol Setujui aktif |
| K-26 | Selisih ≥ 0,01 | tombol Setujui **disabled** |

Penting: `proformaTotal()` menghitung dari **form di layar** (`unit_price` yang sedang diketik × `tour.pax` terkini), bukan dari `invoice.total` yang tersimpan. Jadi begitu sales mengubah harga per pax, tombol Setujui langsung mati sebelum apa pun dikirim ke server.

Penyebab ketidakcocokan yang mungkin terjadi:

1. Sales mengubah `unit_price` setelah mengunci patokan
2. Sales mengubah mata uang (mengubah tampilan, tetapi total dihitung ulang)
3. **`pax` di tour diubah orang lain** — ini yang paling membingungkan karena sales tidak menyentuh apa pun di panel invoice. Lihat [10-temuan.md §10.3](10-temuan.md).

Pemulihannya selalu sama: tekan **Samakan Patokan**.

## 3.5 Yang boleh dilakukan pada tahap `detail`

Semuanya masih terbuka selama `approved_at` masih `null`:

| Aksi | Boleh? |
|---|---|
| Ubah proforma (mata uang, harga, deskripsi, rekening) | ✅ |
| Ubah jatuh tempo | ✅ |
| Kunci ulang patokan | ✅ |
| Tambah / ubah / hapus item Rincian Profit | ✅ |
| Hapus invoice | ✅ |
| Catat pembayaran | ❌ — blok pembayaran baru muncul setelah disetujui |

Patokan terkunci **tidak** membekukan apa pun. Ia hanya menjadi acuan pembanding.

## 3.6 Perlindungan terhadap race dengan autosave

Tombol Kunci/Samakan Patokan tidak langsung mengirim permintaan. Ia dibungkus `afterFlush()`:

```js
function lockBaseline(inv) {
    errorMsg.value = ''
    afterFlush(() => router.patch(route('invoices.baseline', inv.id), {}, reload))
}
```

Bila masih ada baris Rincian Profit yang belum tersimpan, penguncian ditunda sampai autosave selesai. Mekanismenya dijelaskan di [04-rincian-profit.md §4.6](04-rincian-profit.md).
