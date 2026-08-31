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
| Hotel | `16` | tagihan IDR − Σ cost | — |
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

## 8.3 Rumus profit — satu sumber, `SalesLineRuleRegistry`

> **Diperbarui 30 Jul 2026.** Dulu bercabang di **enam tempat** yang harus selalu sepakat — dan tidak ada galat apa pun bila salah satu terlupa. Kini keputusannya hanya di satu tempat: `SalesLineInvoiceRule::profitFromRevenue()`, dipilih lewat `SalesLineRuleRegistry`. Mengubah aturan satu jenis berarti menyunting satu berkas rule.
>
> **Diperbarui 31 Agu 2026.** `hotel` (`HotelPerPaxRule` & `HotelPerRoomNightRule`) kini `profitFromRevenue() === true`, sama seperti `tour` — hotel dijual gelondongan (harga/pax × pax atau Σ baris kamar), Rincian Profit invoice hanya mencatat modal. Sebelumnya profit hotel selalu Rp 0 karena `Σ(sell − cost)` per baris sementara tak ada yang mengisi `sell` per baris hotel.

| Tempat | Berkas | Sekarang |
|---|---|---|
| Panel sales | `InvoicesPanel.vue` | baca prop `salesLine.profitFromRevenue` |
| PDF Rincian Profit | `InvoiceController::profitPdf()` | baca registry |
| Ringkasan Biaya | `CostingPanel.vue` | baca prop `salesLine.profitFromRevenue` |
| Angka Ringkasan Biaya | `Tour::usesInvoiceProfit()` | baca registry |
| Halaman Keuangan | `Finance/Tour.vue` | baca prop `salesLine.profitFromRevenue` |
| Agregat dashboard | `DashboardController::index()` | menjumlah `Tour::total_sell` |

Prop `salesLine` dibentuk `SalesLineRuleRegistry::payloadFor()` — satu tempat, dipakai `TourController` maupun `FinanceController`.

Aturannya sendiri tidak berubah:

### Tipe `tour` (inbound & outbound) dan `hotel` — `profitFromRevenue() === true`

```
profit = tagihan customer dalam IDR − Σ line_cost
margin = profit ÷ tagihan IDR
```

Kolom `sell` per item **diabaikan sepenuhnya**. Alasannya komersial: yang dijual adalah satu harga gelondongan (per pax pada tour paket; harga/pax × pax atau Σ baris kamar pada hotel), bukan penjumlahan komponen. Harga jual per komponen tidak punya arti di sana.

### Semua tipe lain — `profitFromRevenue() === false`

```
profit = Σ (line_sell − line_cost)
margin = profit ÷ Σ line_sell
```

Di sini tiap komponen dijual terpisah, sehingga margin dihitung per baris.

### Kondisi kurs

Berlaku untuk tipe ber-`profitFromRevenue()` (`tour` & `hotel`):

| # | Kondisi | Hasil |
|---|---|---|
| K-114 | Sudah disetujui | pakai `total_idr` yang tersimpan |
| K-115 | Belum disetujui, mata uang IDR | pakai total proforma |
| K-116 | Belum disetujui, non-IDR, kurs terisi | total × kurs |
| K-117 | Belum disetujui, non-IDR, kurs kosong | **`null`** → UI menampilkan "kurs belum diisi" |
| K-118 | Tipe `profitFromRevenue() === false` | tidak pernah `null`; dihitung dalam mata uang invoice |

K-117 disengaja: menebak angka profit tanpa kurs lebih berbahaya daripada menampilkan ketidaktahuan.

## 8.4 Ringkasan Biaya (CostingPanel)

> **Diperbarui 30 Jul 2026.** Label panel dan angka di bawahnya kini membaca aturan yang sama. Sebelumnya label memakai `props.tour.type === 'tour'` di Vue sementara angkanya dihitung `Tour::usesInvoiceProfit()` yang punya perbandingan tipe sendiri — dua tempat yang bisa berselisih diam-diam.

```js
const fromInvoice = computed(() =>
    props.salesLine.profitFromRevenue && (props.tour.invoices ?? []).some(i => i.approved_at)
)
```

| # | Kondisi | Sumber angka |
|---|---|---|
| K-119 | `profitFromRevenue()` **dan** ada invoice disetujui | dari invoice — cost dari item, sell dari tagihan |
| K-120 | Selain itu | dari `tour_items` seperti biasa |

Kedua syarat harus terpenuhi. Tour tipe `rental` dengan invoice disetujui tetap memakai `tour_items`, karena `TransportRule::profitFromRevenue()` mengembalikan `false`.

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
| PDF invoice ke customer | `invoice.blade.php` tidak punya satu pun percabangan **tipe**. Dua baris memang bersyarat — "Price" dan "Total Pax" — tetapi syaratnya `$fromLineItems`, yaitu `totalComposition()` dari registry, bukan `type === 'rental'`. Lihat §8.7 |
| Tiga tahap alur dan seluruh gerbangnya | `InvoiceController` tidak membaca `tour->type` di `store`, `updateProforma`, `lockBaseline`, `approve`, `destroy` |
| Rumus `total = unit_price × pax` | `syncProformaTotal()` tidak membaca tipe |
| Penanganan mata uang dan kurs | sama di semua jalur |
| Pembuatan Bill otomatis | `createMissingFromInvoice()` hanya melihat supplier produk |
| Pencatatan pembayaran dan transisi status | `InvoicePaymentController` tidak membaca tipe |
| Aturan satu tour satu invoice | berlaku mutlak |

Satu-satunya pembacaan `tour->type` di `InvoiceController` ada di `profitPdf()` baris 247 — dokumen internal, bukan alur pembuatan.

> Percabangan `isTour` pada **Quotation** (`quotation.blade.php` vs `quotation_service.blade.php`) adalah hal terpisah dan tidak menyentuh invoice.

## 8.7 Catatan untuk tipe tanpa konsep "pax"

Rental, document, dan ticketing tidak mengenal jumlah peserta secara alami.

**Rental sudah lepas dari pax sepenuhnya.** Sejak `TransportRule::totalComposition() === 'line_items'`, base perhitungannya `0.0` dan seluruh nilai datang dari baris bernominal — `pax` tidak mengalikan apa pun. K-121/K-122 di bawah **tidak lagi berlaku untuk rental**.

Untuk **document** dan **ticketing** (dan `guide`), `total = unit_price × pax` masih berlaku tanpa kecuali:

| # | Kondisi | Akibat praktis |
|---|---|---|
| K-121 | `pax = 1` | `unit_price` efektif **adalah** nilai tagihan |
| K-122 | `pax > 1` pada tipe ini | total ikut berlipat — pastikan memang diinginkan |

### Baris "Total Pax" di PDF

> **Diperbarui 10 Agu 2026.** Dulu baris ini tercetak untuk ketujuh tipe karena template tidak bercabang. Sekarang dilewati bila `$fromLineItems` — jadi rental tidak lagi mencetak "Total Pax : N pax".

| # | Kondisi | PDF customer |
|---|---|---|
| K-123 | `totalComposition() === 'line_items'` (rental) | baris "Total Pax" **tidak** dicetak |
| K-124 | `per_unit` (enam tipe lain), `tour.pax` terisi | baris "Total Pax" dicetak |

Syaratnya sengaja aturan jenis, bukan `tour.pax` maupun `type === 'rental'`: `tours.pax` tetap terisi untuk semua jenis (`syncProformaTotal()` selalu menyimpannya), sehingga nilai kolom itu tidak bisa dipakai menyimpulkan apa pun. Dikunci `CustomerPdfUnitLabelTest`.

Alasannya sama dengan pengecualian baris "Price": pada rental, pax tidak ikut menghitung total, jadi mencetaknya hanya menyatakan angka yang tidak menjelaskan dokumen tersebut. Pada enam tipe lain pax justru **menjelaskan** totalnya (`Price : IDR X × N pax`), jadi di sana baris itu tetap perlu.

**Yang belum diubah:** nama guest masih menjadi `<customer> & Party` bila `tour.pax > 1` ([invoice.blade.php:101](../../resources/views/invoice.blade.php#L101)) — termasuk untuk rental. Itu soal siapa yang bepergian, bukan satuan tagihan, jadi dibiarkan sampai ada keputusan tersendiri.

Label di panel juga tetap berbunyi "Harga / pax" untuk keempat tipe ini, meskipun yang sebenarnya ditagih adalah per hari, per unit, atau per dokumen. Bila `tour.pax` bernilai selain 1, sales terpaksa membagi nilai tagihan dengan jumlah pax agar totalnya benar. Dibahas lengkap di [10-temuan.md §10.4](10-temuan.md).
