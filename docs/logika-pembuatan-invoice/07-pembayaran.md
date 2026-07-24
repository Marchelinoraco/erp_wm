# 07 — Pembayaran (DP & Pelunasan)

[← 06 Penguncian](06-penguncian-setelah-disetujui.md) · [Peta](README.md) · Berikutnya: [08 — Perbedaan per Tipe](08-perbedaan-per-tipe.md)

---

## 7.1 Kondisi blok pembayaran muncul

| # | Kondisi | Ditegakkan di | Kekuatan |
|---|---|---|---|
| K-90 | `approved_at` terisi | `InvoicesPanel.vue` — `v-if="isApproved(inv)"` | **Hanya UI** |

Selama invoice masih draft, blok pembayaran tidak dirender. Server tidak memeriksa kondisi ini sama sekali — lihat [10-temuan.md §10.2](10-temuan.md).

## 7.2 Dua route, satu controller

| Route | Nama | Grup akses |
|---|---|---|
| `POST /invoices/{invoice}/deposits` | `invoice-deposits.store` | sales |
| `DELETE /invoice-deposits/{payment}` | `invoice-deposits.destroy` | sales |
| `POST /finance/invoices/{invoice}/payments` | `invoice-payments.store` | keuangan |
| `DELETE /finance/invoice-payments/{payment}` | `invoice-payments.destroy` | keuangan |

Keempatnya menunjuk ke **method yang sama** di `InvoicePaymentController`. Perbedaannya hanya middleware grup route, bukan perilaku.

## 7.3 Validasi

| Field | Aturan | Catatan |
|---|---|---|
| `date` | `required\|date` | tanggal uang diterima |
| `amount` | `required\|numeric\|min:0.01` | dalam **mata uang invoice** |
| `method` | `required\|in:transfer,cash,other` | |
| `cash_account_id` | `required\|exists:cash_accounts,id` | rekening penerima |
| `exchange_rate` | `required\|numeric\|min:0.000001` bila non-IDR, selain itu `nullable\|numeric\|min:0` | kurs saat pembayaran **ini** |
| `notes` | `nullable\|string` | mis. "DP 50%" |

Aturan `exchange_rate` dibangun dinamis seperti pada persetujuan:

```php
'exchange_rate' => ($invoice->currency ?: 'IDR') !== 'IDR'
    ? 'required|numeric|min:0.000001'
    : 'nullable|numeric|min:0',
```

| # | Kondisi | Akibat |
|---|---|---|
| K-91 | Invoice non-IDR | kurs wajib, minimal 0,000001 |
| K-92 | Invoice IDR | kurs opsional, boleh 0 |
| K-93 | `amount` ≤ 0 | ditolak — minimal 0,01 |

**Tidak ada validasi yang mencegah pembayaran melebihi total invoice.** Kelebihan bayar akan diterima dan membuat status menjadi `paid`.

## 7.4 Kurs per pembayaran

Setiap pembayaran menyimpan kursnya sendiri, terpisah dari kurs invoice. Komentar di kode menjelaskan alasannya:

> Kurs saat pembayaran INI diterima — wajib utk non-IDR, boleh beda dari kurs invoice

Di UI, nilai awalnya diambil berjenjang:

```js
exchange_rate: (inv.currency || 'IDR') !== 'IDR'
    ? (inv.payments?.at(-1)?.exchange_rate ?? inv.exchange_rate ?? '')
    : '',
```

| # | Kondisi | Nilai awal kurs |
|---|---|---|
| K-94 | Sudah ada pembayaran sebelumnya | kurs pembayaran terakhir |
| K-95 | Belum ada pembayaran | kurs invoice |
| K-96 | Keduanya kosong | kosong, sales isi manual |
| K-97 | Invoice IDR | selalu kosong (tidak dipakai) |

Ini memungkinkan skenario nyata: DP diterima tanggal 1 dengan kurs 16.200, pelunasan tanggal 4 dengan kurs 16.350 — keduanya tercatat benar.

## 7.5 Transisi status invoice

Dihitung ulang setiap kali pembayaran ditambah atau dihapus, dengan menjumlahkan seluruh pembayaran.

### Saat menambah

```php
$paid = $invoice->payments()->sum('amount');
$invoice->update([
    'status' => $paid >= $invoice->total ? 'paid' : 'partial',
]);
```

| # | Kondisi | Status baru |
|---|---|---|
| K-98 | `paid >= total` | `paid` |
| K-99 | `paid < total` | `partial` |

### Saat menghapus

```php
'status' => $paid <= 0 ? 'sent' : ($paid >= $invoice->total ? 'paid' : 'partial'),
```

| # | Kondisi | Status baru |
|---|---|---|
| K-100 | `paid <= 0` | `sent` — kembali ke keadaan belum dibayar |
| K-101 | `paid >= total` | `paid` |
| K-102 | selain itu | `partial` |

Perbandingannya memakai `>=`, jadi kelebihan bayar tetap menghasilkan `paid`.

Perhatikan perbandingan dilakukan terhadap `invoice.total` yang bermata uang invoice — konsisten dengan `amount` yang juga bermata uang invoice. Kurs tidak ikut dalam perhitungan status; ia hanya disimpan untuk keperluan pencatatan IDR.

## 7.6 Perhitungan di UI

```js
function invPaid(inv) {
    return (inv.payments ?? []).reduce((s, p) => s + Number(p.amount), 0)
}
function invOutstanding(inv) {
    return Math.max(Number(inv.total) - invPaid(inv), 0)
}
```

| # | Kondisi | Sisa tagihan yang ditampilkan |
|---|---|---|
| K-103 | Kelebihan bayar | `0` — tidak pernah negatif karena `Math.max` |

Kelebihan bayar tidak ditampilkan sebagai angka negatif maupun sebagai peringatan. Ia hanya tidak terlihat.

## 7.7 Setelah menyimpan

Form direset ke keadaan kosong lewat `emptyPayForm(inv)`, dengan kurs kembali diambil dari pembayaran terbaru — sehingga pencatatan pembayaran berturut-turut tetap cepat.

Nilai default form baru:

| Field | Default |
|---|---|
| `amount` | kosong |
| `date` | hari ini |
| `method` | `transfer` |
| `cash_account_id` | rekening kas pertama pada daftar |
| `exchange_rate` | lihat K-94 s/d K-97 |
| `notes` | kosong |
