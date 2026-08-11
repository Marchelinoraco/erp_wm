# Tanggal selesai & urutan baru pada Rincian Tagihan invoice

Tanggal: 2026-08-11
Jenis penjualan terdampak: rental (transport) dan hotel

## 1. Masalah

Baris bernominal pada proforma invoice hanya punya satu kolom tanggal. Untuk
rental mobil/kapal dan pemesanan hotel, satu tanggal tidak cukup: sewa berjalan
dari tanggal mulai sampai tanggal selesai, dan customer perlu melihat rentang
itu di invoice.

Sales menyiasatinya dengan menulis rentang di dalam nama unit
(mis. label `Reborn Tgl 15-16`), sehingga nama kendaraan dan periode sewa
bercampur di satu kolom.

Urutan kolom sekarang — nama, tanggal, keterangan, nominal — juga tidak
mengikuti cara sales membaca baris sewa, yang selalu dimulai dari periodenya.

## 2. Hasil yang diinginkan

Baris bernominal untuk rental dan hotel tersusun: **tanggal mulai → tanggal
selesai → nama/unit → keterangan → nominal**, dan PDF ke customer mencetak
periodenya sebagai rentang.

## 3. Bentuk data

Baris bernominal disimpan sebagai elemen JSON di kolom
`invoices.description_lines`, bukan di tabel `invoice_items`. Strukturnya
bertambah satu key opsional:

```
{ label, date, date_end, detail, amount }
```

| Key | Arti | Catatan |
| --- | --- | --- |
| `date` | tanggal mulai | sudah ada, maknanya tidak berubah |
| `date_end` | tanggal selesai | **baru**, boleh kosong/absen |

**Tidak ada migrasi.** `description_lines` sudah bertipe JSON, jadi baris lama
yang tak punya `date_end` tetap sah dan tampil persis seperti sebelumnya.

Validasi di `InvoiceController` bertambah satu aturan, sejajar dengan `date`
yang sudah ada:

```php
'description_lines.*.date_end' => 'nullable|string|max:255',
```

Aturannya `string`, bukan `date`, karena kolom yang sama juga menampung teks
bebas pada baris deskripsi non-nominal.

## 4. Menentukan jenis yang memakai rentang tanggal

Rental dan hotel memakai dua kolom tanggal; jenis lain tidak.

Pemilihan jenis penjualan di sistem ini hanya boleh terjadi di satu tempat —
`SalesLineRuleRegistry` beserta implementasi aturannya. Tidak ada percabangan
`if type === 'rental'` di controller, model, maupun komponen Vue.

Karena itu ditambahkan satu method pada kontrak `SalesLineInvoiceRule`:

```php
/**
 * true = baris bernominal punya tanggal mulai DAN tanggal selesai.
 * Sewa kendaraan dan menginap berjalan sepanjang rentang, bukan pada
 * satu titik tanggal seperti biaya dokumen atau izin.
 */
public function chargeLinesUseDateRange(): bool;
```

- `BaseSalesLineRule` mengembalikan `false` — perilaku mayoritas jenis.
- `TransportRule` dan `HotelRule` meng-override menjadi `true`.
- `SalesLineRuleRegistry::payloadFor()` ikut mengirim
  `chargeLinesUseDateRange` ke frontend, sejajar dengan `profitFromRevenue`
  dan `totalComposition` yang sudah dikirim.

Menambahkan jenis lain kelak berarti menyunting satu berkas aturan saja.

Catatan: `hotel` memakai `totalComposition = per_unit`, sehingga bloknya
berjudul "Biaya Tambahan (di luar harga/pax)", sedangkan `rental` memakai
`line_items` sehingga berjudul "Rincian Tagihan". Keduanya komponen yang sama,
dan judulnya tidak diubah oleh pekerjaan ini.

## 5. Form proforma (`InvoicesPanel.vue`)

### 5.1 Urutan kolom

Saat `chargeLinesUseDateRange` bernilai true, baris bernominal menjadi:

```
MULAI        SELESAI      NAMA/UNIT     KETERANGAN         NOMINAL
[15/08/2026] [17/08/2026] [Reborn]      [Dengan Sopir]     [1700000]  ✕
[16/08/2026] [17/08/2026] [Avanza]      [sopir]            [1200000]  ✕
```

Saat bernilai false, baris tetap seperti sekarang — satu tanggal, urutan lama,
tanpa perubahan apa pun.

### 5.2 Baris judul kolom

Input bertipe `date` tidak bisa menampilkan placeholder, sehingga dua kotak
tanggal berdampingan tanpa penanda tidak terbaca. Karena itu daftar baris
bernominal mendapat satu baris judul kolom tipis di atasnya, hanya saat mode
rentang aktif.

### 5.3 Placeholder nama

- mode rentang aktif: `Nama/Unit (mis. Avanza)`
- mode rentang tidak aktif: `Label (mis. Dokumen)` — tidak berubah

### 5.4 Alur data form

Tiga tempat menangani bentuk baris dan ketiganya bertambah key `date_end`:

1. hidrasi dari props (`watch` atas `tour.invoices`) — `date_end: l.date_end ?? ''`
2. `addAdditionalLine()` — baris baru berisi `date_end: ''`
3. `saveProforma()` — payload menyertakan `date_end`

Penanda pembeda baris bernominal vs baris deskripsi tetap **kehadiran key
`amount`**, tidak berubah. `date_end` tidak pernah dipakai sebagai penanda.

## 6. PDF invoice (`invoice.blade.php`)

Tata letak kolom tidak berubah. Yang berubah hanya cara nilai tanggal ditulis
pada baris bernominal:

| Isi data | Tercetak |
| --- | --- |
| `date` = `2026-08-15`, `date_end` = `2026-08-17` | `15/08/2026 – 17/08/2026` |
| `date` = `2026-08-15`, `date_end` kosong/absen | `15/08/2026` |
| `date` dan `date_end` bernilai sama | `15/08/2026` (tidak diulang) |
| `date` = teks bebas, mis. `Aug 15, 2026` | `Aug 15, 2026` (apa adanya) |
| `date` kosong | tidak ada tanggal, seperti sekarang |

### 6.1 Aturan pemformatan

Pemformatan hanya berjalan bila nilainya benar-benar berpola `YYYY-MM-DD` —
yaitu yang dihasilkan input bertipe `date`. Nilai lain dicetak apa adanya.

Aturan ini **tidak dibatasi jenis penjualan**: patokannya bentuk nilai, bukan
`chargeLinesUseDateRange`. Jadi baris biaya tambahan pada tour atau MICE yang
tanggalnya diisi lewat input `date` juga ikut tercetak `15/08/2026`, bukan
`2026-08-15`. Yang dibatasi rental & hotel hanyalah **kotak tanggal kedua di
form** (§5). Membedakan format cetak per jenis hanya akan membuat satu dokumen
punya dua gaya tanggal tanpa alasan yang bisa dijelaskan ke customer.

Alasannya: `CostRequestController::appendAdditionalCharge()` menulis tanggal
dengan format `M d, Y` (mis. `Aug 15, 2026`) pada baris berlabel `Additional`,
dan baris deskripsi non-nominal diisi teks bebas oleh sales. Memaksa parsing
atas nilai-nilai itu akan mengubah tampilan invoice lama tanpa diminta.

Dengan aturan ini, tidak ada satu pun invoice yang sudah terbit berubah
tampilan PDF-nya, kecuali baris yang tanggalnya memang berasal dari input
`date` — dan itu hanya berpindah dari `2026-08-15` menjadi `15/08/2026`.

### 6.2 Tanda pisah

Tanda pisah adalah en dash dengan spasi di kedua sisi (` – `), dan hanya
muncul bila kedua tanggal terisi. Tidak boleh ada tanda pisah menggantung
seperti `15/08/2026 –`.

Tanda pisahnya karakter en dash Unicode (`–`), bukan entitas `&ndash;`,
mengikuti `$resvDate` di `invoice.blade.php` yang sudah memakainya dan
tercetak normal di PDF produksi. Entitas HTML tidak dipakai karena nilai
tanggal dirakit di PHP lalu dicetak lewat `{{ }}` yang meng-escape — `&ndash;`
akan tercetak sebagai teks mentah, bukan sebagai tanda pisah.

## 7. Ringkasan invoice yang sudah disetujui

Blok ringkasan baris bernominal di `InvoicesPanel.vue` saat ini sama sekali
tidak menampilkan tanggal. Blok itu mendapat rentang tanggal di depan nama,
memakai aturan penulisan yang sama persis dengan PDF (§6), supaya yang terlihat
di layar cocok dengan dokumen yang diterima customer.

## 8. Tes

Ditambahkan ke `tests/Feature/Invoice/` dan `tests/Feature/SalesLine/`:

1. PDF mencetak `15/08/2026 – 17/08/2026` untuk baris bernominal yang punya
   kedua tanggal.
2. PDF mencetak satu tanggal saja, tanpa tanda pisah menggantung, saat
   `date_end` kosong.
3. PDF mencetak nilai tanggal non-ISO apa adanya — jaminan invoice lama dan
   baris dari pengajuan biaya tidak berubah.
4. `date_end` tersimpan lewat penyimpanan proforma dan terbaca kembali utuh.
5. `chargeLinesUseDateRange()` bernilai true hanya untuk `rental` dan `hotel`,
   false untuk seluruh jenis lain yang terdaftar di registry.

## 9. Di luar cakupan

- Memindahkan baris bernominal dari JSON `description_lines` ke tabel
  tersendiri.
- Menyentuh `invoice_items` beserta kolom `start_date`/`end_date` miliknya —
  tabel itu dipakai untuk Rincian Profit internal, bukan untuk tagihan.
- Mengubah judul blok ("Rincian Tagihan" vs "Biaya Tambahan").
- Validasi bahwa tanggal selesai tidak mendahului tanggal mulai. Sales kerap
  mengetik tanggal secara bertahap, dan menahan penyimpanan di tengah
  pengisian lebih mengganggu daripada membantu.
