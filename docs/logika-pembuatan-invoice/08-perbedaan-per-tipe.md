# 08 — Perbedaan per Tipe Penjualan

[← 07 Pembayaran](07-pembayaran.md) · [Peta](README.md) · Berikutnya: [09 — Matriks Kondisi](09-matriks-kondisi.md)

---

## 8.1 Kesimpulan lebih dulu

**Alur invoice hampir seluruhnya identik untuk ketujuh tipe penjualan.** Setelah menelusuri controller, model, panel Vue, dan template PDF, hanya tiga hal yang benar-benar bercabang:

1. Kode pada nomor invoice
2. Rumus profit internal
3. Panel pendamping di halaman tour

Bila Anda mencari "aturan khusus invoice untuk rental" atau "invoice MICE beda apa", jawabannya untuk hampir semua pertanyaan adalah: tidak ada.

## 8.2 Tabel tipe

| Tipe | Kode | Rumus profit | Panel khusus |
|---|:---:|---|---|
| Tour — inbound | `11` | tagihan IDR − Σ cost | Itinerary |
| Tour — outbound | `12` | tagihan IDR − Σ cost | Itinerary |
| Rental (mobil/kapal) | `13` | Σ (sell − cost) | — |
| Guide | `14` | Σ (sell − cost) | — |
| MICE / Event | `15` | Σ (sell − cost) | MICE Template |
| Hotel | `16` | Σ (sell − cost) | — |
| Document (visa/paspor) | `17` | Σ (sell − cost) | — |
| Ticketing | `18` | Σ (sell − cost) | — |

Kode berasal dari `Tour::TYPE_CODES`. Hanya `tour` yang dipecah menurut arah:

```php
public function resolveTypeCode(): string
{
    if ($this->type === 'tour') {
        return $this->tour_direction === 'outbound' ? '12' : '11';
    }
    return self::TYPE_CODES[$this->type] ?? '11';
}
```

| # | Kondisi | Kode |
|---|---|:---:|
| K-110 | `type === 'tour'` dan `tour_direction === 'outbound'` | `12` |
| K-111 | `type === 'tour'`, arah selain outbound (termasuk kosong) | `11` |
| K-112 | Tipe terdaftar di `TYPE_CODES` | sesuai tabel |
| K-113 | Tipe tidak dikenali | jatuh ke `11` |

Fungsi yang sama dipakai untuk kode tour (`WM-<tahun>-<kode>-NNNN`), sehingga kode tour dan kode invoice selalu sejalan.

## 8.3 Rumus profit — satu-satunya percabangan logika

Bercabang di **tiga tempat** yang harus selalu sepakat:

| Tempat | Berkas |
|---|---|
| Panel sales | `InvoicesPanel.vue:135-142` |
| PDF Rincian Profit | `InvoiceController::profitPdf()` |
| Ringkasan Biaya | `CostingPanel.vue:11-13` |

### Tipe `tour` (inbound & outbound)

```
profit = tagihan customer dalam IDR − Σ line_cost
margin = profit ÷ tagihan IDR
```

Kolom `sell` per item **diabaikan sepenuhnya**. Alasannya komersial: pada tour paket, yang dijual adalah satu harga per pax, bukan penjumlahan komponen. Harga jual per komponen tidak punya arti di sana.

### Semua tipe lain

```
profit = Σ (line_sell − line_cost)
margin = profit ÷ Σ line_sell
```

Di sini tiap komponen dijual terpisah, sehingga margin dihitung per baris.

### Kondisi kurs

| # | Kondisi | Hasil |
|---|---|---|
| K-114 | Tipe `tour`, sudah disetujui | pakai `total_idr` yang tersimpan |
| K-115 | Tipe `tour`, belum disetujui, mata uang IDR | pakai total proforma |
| K-116 | Tipe `tour`, belum disetujui, non-IDR, kurs terisi | total × kurs |
| K-117 | Tipe `tour`, belum disetujui, non-IDR, kurs kosong | **`null`** → UI menampilkan "kurs belum diisi" |
| K-118 | Tipe non-`tour` | tidak pernah `null`; dihitung dalam mata uang invoice |

K-117 disengaja: menebak angka profit tanpa kurs lebih berbahaya daripada menampilkan ketidaktahuan.

## 8.4 Ringkasan Biaya (CostingPanel)

```js
const fromInvoice = computed(() =>
    props.tour.type === 'tour' && (props.tour.invoices ?? []).some(i => i.approved_at)
)
```

| # | Kondisi | Sumber angka |
|---|---|---|
| K-119 | Tipe `tour` **dan** ada invoice disetujui | dari invoice — cost dari item, sell dari tagihan |
| K-120 | Selain itu | dari `tour_items` seperti biasa |

Kedua syarat harus terpenuhi. Tour tipe `rental` dengan invoice disetujui tetap memakai `tour_items`.

## 8.5 Panel pendamping

| Panel | Kondisi tampil |
|---|---|
| Invoice | `tour.status === 'confirmed'` — semua tipe |
| Cost Requests | `tour.status === 'confirmed'` — semua tipe |
| Itinerary | `type === 'tour'` |
| MICE Template | `type === 'mice'` |
| Quotation, Q-Items, Items, Operasional, History | semua tipe |

Budget gauge dan margin guard pada MICE berada di panel **Quotation Items**, bukan invoice. Keduanya tidak memengaruhi alur invoice sama sekali.

## 8.6 Yang terbukti **sama** untuk semua tipe

Diperiksa satu per satu, bukan diasumsikan:

| Aspek | Bukti |
|---|---|
| PDF invoice ke customer | `invoice.blade.php` tidak punya satu pun percabangan tipe |
| Tiga tahap alur dan seluruh gerbangnya | `InvoiceController` tidak membaca `tour->type` di `store`, `updateProforma`, `lockBaseline`, `approve`, `destroy` |
| Rumus `total = unit_price × pax` | `syncProformaTotal()` tidak membaca tipe |
| Penanganan mata uang dan kurs | sama di semua jalur |
| Pembuatan Bill otomatis | `createMissingFromInvoice()` hanya melihat supplier produk |
| Pencatatan pembayaran dan transisi status | `InvoicePaymentController` tidak membaca tipe |
| Aturan satu tour satu invoice | berlaku mutlak |

Satu-satunya pembacaan `tour->type` di `InvoiceController` ada di `profitPdf()` baris 247 — dokumen internal, bukan alur pembuatan.

> Percabangan `isTour` pada **Quotation** (`quotation.blade.php` vs `quotation_service.blade.php`) adalah hal terpisah dan tidak menyentuh invoice.

## 8.7 Catatan untuk tipe tanpa konsep "pax"

Rental, document, dan ticketing tidak mengenal jumlah peserta secara alami. Karena `total = unit_price × pax` berlaku tanpa kecuali:

| # | Kondisi | Akibat praktis |
|---|---|---|
| K-121 | `pax = 1` | `unit_price` efektif **adalah** nilai tagihan |
| K-122 | `pax > 1` pada tipe ini | total ikut berlipat — pastikan memang diinginkan |

PDF invoice tetap menampilkan baris "Total Pax" untuk semua tipe, karena template tidak membedakan tipe.

Label di panel juga tetap berbunyi "Harga / pax" untuk keempat tipe ini, meskipun yang sebenarnya ditagih adalah per hari, per unit, atau per dokumen. Bila `tour.pax` bernilai selain 1, sales terpaksa membagi nilai tagihan dengan jumlah pax agar totalnya benar. Dibahas lengkap di [10-temuan.md §10.4](10-temuan.md).
