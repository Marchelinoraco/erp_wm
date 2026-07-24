# 02 — Tahap 1 · Proforma

[← 01 Prasyarat](01-prasyarat-dan-pembuatan.md) · [Peta](README.md) · Berikutnya: [03 — Tahap 2 Patokan](03-tahap-2-patokan.md)

---

Tahap ini menentukan **berapa yang ditagih ke customer**. Semua isinya masih bebas diubah.

Endpoint: `PATCH /invoices/{invoice}/proforma` → `InvoiceController::updateProforma()`

## 2.1 Kondisi masuk

| # | Kondisi | Keterangan |
|---|---|---|
| K-10 | `approved_at` masih `null` | dijaga `ensureNotApproved()`; bila sudah disetujui → "Invoice sudah disetujui, tidak bisa diubah lagi." |

Tahap ini tidak mensyaratkan `baseline_total` bernilai apa pun. Proforma tetap bisa diubah **setelah** patokan dikunci — itu justru yang membuat tombol "Samakan Patokan" ada gunanya (lihat [03](03-tahap-2-patokan.md)).

## 2.2 Aturan validasi

| Field | Aturan | Catatan |
|---|---|---|
| `currency` | `required\|in:IDR,USD,EUR,SGD,AUD,MYR` | daftar di `InvoiceController::CURRENCIES` |
| `unit_price` | `required\|numeric\|min:0` | harga **per pax** |
| `guest_name` | `nullable\|string\|max:255` | menimpa snapshot dari tour |
| `description_lines` | `nullable\|array` | |
| `description_lines.*.label` | `nullable\|string\|max:255` | |
| `description_lines.*.date` | `nullable\|string\|max:255` | teks bebas, **bukan** tanggal tervalidasi |
| `description_lines.*.detail` | `nullable\|string\|max:1000` | |
| `bank_account_ids` | `nullable\|array` | |
| `bank_account_ids.*` | `integer\|exists:bank_accounts,id` | |
| `notes` | `nullable\|string` | bila tidak dikirim, nilai lama dipertahankan |

Perhatikan `unit_price` boleh 0 — validasi mengizinkannya. Yang menolak total 0 adalah tahap berikutnya, bukan di sini.

## 2.3 Rumus total

```
total = unit_price × pax
```

Dihitung di `Invoice::syncProformaTotal()`, dipanggil di akhir `updateProforma()`.

### Kondisi sumber `pax`

| # | Kondisi | Nilai `pax` yang dipakai |
|---|---|---|
| K-11 | Tour ada dan punya `pax` | `tour.pax` |
| K-12 | Tour tidak ada / `pax` kosong | `invoice.pax` |
| K-13 | Keduanya kosong | `1` |
| K-14 | Hasil di bawah 1 | dipaksa jadi 1 lewat `max($pax, 1)` |

```php
$pax   = (int) ($this->tour?->pax ?? $this->pax ?? 1);
$total = (float) $this->unit_price * max($pax, 1);
```

**Konsekuensi penting:** sumber utamanya adalah tour, bukan invoice. Mengubah `pax` di tour akan menggeser total invoice saat proforma disimpan berikutnya — dan itu dapat memblokir persetujuan. Lihat [10-temuan.md §10.3](10-temuan.md).

Nilai `pax` yang dipakai disalin balik ke `invoice.pax` supaya PDF dan perhitungan tidak pernah memakai angka berbeda:

```php
$updates = ['total' => $total, 'pax' => max($pax, 1)];
```

## 2.4 Kondisi mata uang

| # | Kondisi | `exchange_rate` | `total_idr` |
|---|---|---|---|
| K-15 | `currency === 'IDR'` | di-set `1` saat proforma disimpan | ikut terisi = `total` |
| K-16 | `currency !== 'IDR'` | dibiarkan apa adanya | **tetap kosong** sampai disetujui |

Alasan K-16 tertulis di komentar `syncProformaTotal()`: agar laporan IDR tidak terdistorsi kurs placeholder sebelum kurs pasti diinput. Rincian di [05-tahap-3-persetujuan.md](05-tahap-3-persetujuan.md).

## 2.5 Baris deskripsi

Baris deskripsi adalah teks bebas yang muncul di PDF customer. Sifatnya:

- Disimpan sebagai JSON (`description_lines` di-cast `array`)
- Diindeks ulang dengan `array_values()` supaya kunci array selalu rapat setelah penghapusan
- Field `date` bertipe **string**, bukan tanggal — tidak divalidasi sebagai tanggal dan tidak diurai
- Menambah baris di UI (`addLine`) tidak langsung menyimpan; menghapus baris (`removeLine`) langsung memanggil `saveProforma`

Baris ini murni tampilan. Tidak ada satu pun yang memengaruhi nominal.

## 2.6 Rekening bank

| # | Kondisi | Perilaku |
|---|---|---|
| K-17 | `bank_account_ids` terisi | hanya rekening tersebut yang tampil di PDF |
| K-18 | `bank_account_ids` kosong / tidak dikirim | disimpan sebagai `null`, artinya **tampilkan semua rekening aktif** |

```php
'bank_account_ids' => ! empty($data['bank_account_ids']) ? array_values($data['bank_account_ids']) : null,
```

Di sisi UI, `null` dari server diterjemahkan menjadi "semua checkbox tercentang" saat form diisi, sehingga sales melihat keadaan yang setara. Mencentang atau melepas satu rekening langsung memicu `saveProforma()`.

## 2.7 Kapan tersimpan

Tidak ada autosave di tahap ini — berbeda dengan Rincian Profit. Proforma tersimpan hanya ketika:

- Tombol simpan proforma ditekan
- Checkbox rekening bank diubah (`toggleBankAccount`)
- Baris deskripsi dihapus (`removeLine`)

Menambah baris deskripsi atau mengetik di kolomnya **belum** tersimpan sampai salah satu pemicu di atas terjadi.
