# Tata letak PDF invoice hotel

Tanggal: 2026-08-12
Jenis penjualan terdampak: hotel (kedua modenya)

## 1. Masalah

PDF invoice hotel tidak berbentuk seperti dokumen yang selama ini dipakai Welcome
Manado. Pemilik proyek mengirim contoh dokumen acuan; tiga hal di dalamnya tidak
diproduksi sistem:

- **`Total Pax` hilang di mode kamar.** Pekerjaan sebelumnya menyembunyikannya
  karena pax tidak ikut menghitung total di mode itu. Tetapi pembaca dokumen tetap
  ingin tahu berapa orang menginap — angkanya informatif, bukan pengali.
- **Tidak ada baris `Hotel / Room`.** Nama hotel dan tipe kamar tidak punya tempat
  di dokumen.
- **Rincian hitungan tidak tercetak.** Mode kamar memakai tata letak rental
  (rentang tanggal di kolom kiri), sedangkan dokumen acuan memakai baris
  `Price : IDR 705.000 x 1 room x 1 night`.

Format tanggalnya pun berbeda: acuan menulis `1-2 Aug 2026`, sistem mencetak
`01 August 2026 – 02 August 2026`.

## 2. Hasil yang diinginkan

### 2.1 Mode kamar/malam — satu tipe kamar

Identik dengan dokumen acuan:

```
Guest Name    : FABIOLA FINA FEYBE OMBUH
Reservation   : HOTEL VOUCHER

Date          : 1-2 Aug 2026
Total Pax     : 2 pax
Hotel / Room  : Paradise Hotel Golf & Resort – 1 Deluxe Room Garden View

Price         : IDR 705.000 x 1 room x 1 night          IDR   705.000
                                            Total       IDR   705.000
```

### 2.2 Mode kamar/malam — beberapa tipe kamar

Tiap `Price` menempel di bawah `Hotel / Room` miliknya. Total menjumlahkan seluruh
`Price`. Nama hotel melekat per baris, sehingga satu invoice boleh memuat dua hotel
berbeda.

```
Date          : 1-3 Aug 2026
Total Pax     : 2 pax

Hotel / Room  : Paradise Hotel – 1 Deluxe Room Garden View
Price         : IDR 705.000 x 1 room x 2 night        IDR 1.410.000

Hotel / Room  : Ibis Manado – 1 Superior Room
Price         : IDR 950.000 x 1 room x 1 night        IDR   950.000
                                            Total     IDR 2.360.000
```

Pemisahan pasangan hanya berlaku bila baris kamarnya lebih dari satu. Dengan satu
baris, hasilnya persis §2.1 — `Hotel / Room` menempel di blok atas dan `Price`
dipisahkan satu baris kosong.

### 2.3 Mode pax

Tidak punya baris kamar, jadi tidak ada `x room x night` dan tidak ada pasangan
berulang. Yang ikut hanya format tanggal ringkas dan satu baris `Hotel / Room`
bebas:

```
Date          : 1-2 Aug 2026
Total Pax     : 2 pax
Hotel / Room  : Paradise Hotel Golf & Resort – Deluxe Room Garden View

Price         : IDR 705.000 x 2 pax                   IDR 1.410.000
                                            Total     IDR 1.410.000
```

### 2.4 Konsekuensi struktural yang tidak terlihat dari contoh

Blade membangun dokumen dari dua wilayah terpisah: **blok info** (`Guest Name`,
`Reservation`, `Date`, `Total Pax`) dan **area baris bernominal** di bawahnya.

Pada §2.1 baris `Hotel / Room` berada di blok info — menempel di bawah `Total Pax`,
dengan `Price` dipisahkan satu baris kosong di area bernominal. Pada §2.2 kedua
baris berpasangan di area bernominal, dan blok info tidak memuat `Hotel / Room`
sama sekali.

Jadi tata letak `hotel_room` bercabang menurut **jumlah baris kamar**, dan
percabangan itu melintasi dua wilayah blade. Satu baris kamar → `Hotel / Room` naik
ke blok info. Dua atau lebih → seluruhnya turun ke area bernominal, berpasangan.

Mode pax selalu memakai bentuk blok info (§2.3), karena memang hanya punya satu
keterangan.

## 3. Penyimpanan

Sumber teks `Hotel / Room` berbeda antar-mode, dan itu disengaja — bentuk datanya
memang berbeda:

| Mode | Sumber | Alasan |
| --- | --- | --- |
| Kamar/malam | key `hotel` pada tiap baris kamar di `description_lines` | satu invoice boleh memuat dua hotel berbeda |
| Pax | kolom baru `invoices.hotel_room` | tidak ada baris kamar; satu invoice = satu keterangan |

### 3.1 Key `hotel` pada baris kamar

Menumpang JSON `description_lines` yang sudah ada — **tanpa migrasi**. Baris kamar
kini berbentuk:

```
{label, date, date_end, detail, rooms, unit_price, hotel, amount}
```

Penanda baris kamar tetap kehadiran key `rooms`. `hotel` tidak pernah menjadi
penanda, dan baris kamar tetap menulis `rooms` serta `unit_price` bersamaan seperti
sebelumnya.

### 3.2 Kolom `invoices.hotel_room`

Satu kolom `string` nullable, teks bebas, dicetak apa adanya. **Ada migrasi** —
hanya menambah kolom nullable, tanpa backfill, sesuai protokol §7.1
`docs/desain/pemisahan-invoice-per-jenis.md`.

Kolomnya tidak dirangkai sistem: mode pax tidak punya jumlah kamar untuk disisipkan,
sehingga merangkai otomatis tidak menambah apa pun.

## 4. Tiga tata letak, satu pemilih

`chargeLinesDateFirstInPdf(): bool` hanya bisa menyatakan dua keadaan. Dengan
pekerjaan ini baris bernominal punya **tiga** tata letak:

| Nilai | Dipakai | Bentuk |
| --- | --- | --- |
| `default` | tour, guide, MICE, document, ticketing, hotel mode pax | label di kiri, tanggal menyatu dengan keterangan |
| `date_first` | rental | rentang tanggal di kolom kiri |
| `hotel_room` | hotel mode kamar | pasangan `Hotel / Room` + `Price` |

Karena itu method boolean itu **diganti** oleh:

```php
/** Bentuk baris bernominal di PDF: 'default', 'date_first', atau 'hotel_room'. */
public function chargeLineLayout(): string;
```

`BaseSalesLineRule` mengembalikan `'default'`; `TransportRule` `'date_first'`;
`HotelPerRoomNightRule` `'hotel_room'`. Tiga keadaan menjadi tiga nilai, sehingga
tidak ada kombinasi tak sah yang perlu dijaga disiplin.

Pelebaran kolom label menjadi 180px yang sudah ada untuk `date_first` ikut berlaku
untuk `hotel_room` — baris `Hotel / Room` juga lebih panjang dari label biasa.

## 5. `Total Pax` kembali tampil untuk hotel

Sekarang baris itu disembunyikan berdasarkan komposisi total (`fromLineItems`), yang
bernilai true untuk rental **dan** hotel mode kamar. Yang sebenarnya dimaksud adalah
"jenis ini tidak mengenal jumlah peserta" — dan itu hanya berlaku untuk rental.

Aturan mendapat method tersendiri:

```php
/** true = jumlah peserta bermakna untuk jenis ini dan dicetak di PDF. */
public function showsTotalPaxInPdf(): bool;
```

`BaseSalesLineRule` mengembalikan `true`; hanya `TransportRule` mengembalikan
`false`. Untuk hotel mode kamar, pax tercetak sebagai keterangan dan **tidak ikut
mengalikan apa pun** — totalnya tetap jumlah baris kamar.

## 6. Format tanggal ringkas

Berlaku **hanya untuk invoice hotel**, kedua modenya. Jenis lain tidak berubah
sedikit pun, sehingga tidak ada invoice terbit di luar hotel yang berubah tampilan.

| Keadaan | Tercetak |
| --- | --- |
| Sebulan sama | `1-2 Aug 2026` |
| Beda bulan, tahun sama | `30 Aug - 2 Sep 2026` |
| Beda tahun | `30 Dec 2026 - 2 Jan 2027` |
| Satu tanggal saja | `1 Aug 2026` |

Tanggal tanpa nol di depan (`1`, bukan `01`); bulan disingkat tiga huruf. Sumbernya
tetap tanggal tour, sama seperti sekarang.

Pemilihannya lewat aturan, bukan pengecekan jenis di blade:

```php
/** true = baris Date memakai format ringkas gaya voucher hotel. */
public function usesCompactDateInPdf(): bool;
```

`BaseSalesLineRule` mengembalikan `false`; kedua kelas hotel mengembalikan `true`.

### 6.1 Tiga perubahan kontrak sekaligus

Pekerjaan ini mengganti satu method kontrak (`chargeLinesDateFirstInPdf()` →
`chargeLineLayout()`) dan menambah dua (`showsTotalPaxInPdf()`,
`usesCompactDateInPdf()`).

Setiap kali kontrak `SalesLineInvoiceRule` bertambah, seluruh implementasinya —
termasuk test double `tests/Support/FakeSalesLineRule.php` dan
`tests/Support/FakeSalesLineRuleRegistry.php` — wajib ikut, atau test yang tidak
berhubungan pecah dengan fatal error, bukan kegagalan assertion yang menjelaskan
diri. Pola ini sudah terjadi empat kali pada fitur sebelumnya; perlakukan sebagai
bagian yang pasti dari pekerjaan, bukan kejutan.

## 7. Merangkai baris `Hotel / Room`

### 7.1 Mode kamar

`{hotel} – {rooms} {tipe kamar}`, lalu ` · {keterangan}` bila keterangannya diisi.

Bagian yang kosong dilewati tanpa menyisakan pemisah menggantung:

| Isi | Tercetak |
| --- | --- |
| hotel, 1 kamar, tipe, keterangan | `Paradise Hotel – 1 Deluxe Room · Twin bed` |
| hotel kosong | `1 Deluxe Room` |
| tipe kamar kosong | `Paradise Hotel` |
| semuanya kosong | baris `Hotel / Room` tidak dicetak |

### 7.2 Mode pax

Isi kolom `hotel_room` dicetak apa adanya. Kosong → baris tidak dicetak.

## 8. Baris `Price` mode kamar

```
IDR 705.000 x 1 room x 1 night
```

Angka nominalnya memakai pemformat mata uang yang sudah ada. Kata `room` dan
`night` ditulis **tunggal berapa pun jumlahnya**, mengikuti dokumen acuan — sama
seperti `pax` yang sudah tidak dijamakkan di dokumen ini.

Baris kamar yang nominalnya 0 karena isiannya belum lengkap tetap dicetak apa
adanya, agar tidak ada baris yang hilang diam-diam dari dokumen.

## 9. Form

Mode kamar mendapat satu kolom isian **NAMA HOTEL** di depan TIPE KAMAR. Mode pax
mendapat satu kolom isian **Hotel / Room** teks bebas, sejajar dengan blok harga.

## 10. Yang tidak berubah

- Hitungan `harga × kamar × malam` dan `harga × pax`.
- Tata letak, format tanggal, dan angka untuk rental serta lima jenis lain.
- Penanda tiga jenis baris `description_lines`.
- Kolom `sales_line` dan `billing_quantities` tetap tidak disentuh.

## 11. Tes

1. PDF hotel mode kamar dengan satu baris mencetak `Hotel / Room` dan `Price`
   sesuai §2.1, dengan pengali `x N room x N night`.
2. Dua baris kamar mencetak dua pasang `Hotel / Room` + `Price`, dan totalnya
   jumlah keduanya.
3. PDF hotel mode pax mencetak `Hotel / Room` dari kolom `hotel_room` dan baris
   `Price` berpengali pax.
4. `Total Pax` tercetak untuk hotel di KEDUA mode, dan tetap TIDAK tercetak untuk
   rental.
5. Format tanggal ringkas untuk keempat keadaan §6, dan hanya untuk hotel.
6. Rental dan lima jenis lain: PDF-nya tidak berubah sama sekali.
7. `chargeLineLayout()` bernilai `hotel_room` hanya untuk hotel mode kamar,
   `date_first` hanya untuk rental, `default` untuk sisanya — disapu dari registry
   agar jenis baru tak lolos tanpa keputusan.
8. Tiap bagian kosong pada §7.1 menghasilkan baris tanpa pemisah menggantung.
9. `hotel` tersimpan dan terbaca ulang pada baris kamar; `hotel_room` tersimpan dan
   terbaca ulang pada invoice.
10. Invoice hotel yang sudah ada — tanpa `hotel` maupun `hotel_room` — tetap
    tercetak tanpa error dan tanpa baris `Hotel / Room`.

## 12. Di luar cakupan

- Mengubah format tanggal untuk jenis selain hotel.
- Menjamakkan `room`/`night`/`pax` di dokumen.
- Menampilkan rincian kamar di PDF mode pax (tidak ada datanya di sana).
- Mengubah cara hitung apa pun.
