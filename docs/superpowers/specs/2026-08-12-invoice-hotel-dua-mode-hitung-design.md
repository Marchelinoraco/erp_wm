# Invoice hotel: dua cara hitung (per pax dan per kamar/malam)

Tanggal: 2026-08-12
Jenis penjualan terdampak: hotel

## 1. Masalah

Total invoice hotel dihitung `harga × jumlah pax tour` — rumus yang sama dengan
tour. Untuk sebuah pemesanan hotel itu keliru: hotel ditagih per kamar per
malam, bukan per orang.

Akibatnya PDF ke customer mencetak `Price: Rp 1.500.000 × 4 pax` dan
`Total Pax : 4 pax` untuk pemesanan kamar, dan form menampilkan label
"Harga / pax".

`HotelRule` sebenarnya sudah menyatakan hal yang benar — `unitPriceLabel()`
mengembalikan `'Harga / kamar / malam'` dan `defaultMultipliers()` mengembalikan
kamar × malam — tetapi keduanya kode mati: `syncProformaTotal()` mematok pengali
`pax` untuk semua jenis, dan kolom `billing_quantities` tidak pernah diisi
maupun dibaca. Jadi aturan hotel mendeskripsikan satu hal, sementara yang
berjalan adalah hal lain.

## 2. Hasil yang diinginkan

Sales memilih sendiri, per invoice, salah satu dari dua cara hitung:

| Mode | Total | Tampilan form & PDF |
| --- | --- | --- |
| Per pax | `harga × pax tour` | persis seperti sekarang |
| Per kamar/malam | jumlah nominal baris rincian | seperti invoice rental |

Tidak ada invoice hotel yang sudah ada berubah nilainya.

## 3. Penyimpanan

### 3.1 Kolom baru `invoices.pricing_mode`

Satu kolom `string` nullable. Nilai yang dikenal: `'per_pax'` dan
`'per_room_night'`. **`null` diperlakukan sebagai `'per_pax'`** — sehingga
seluruh invoice hotel yang sudah ada otomatis mempertahankan perilaku lamanya
tanpa backfill apa pun.

Migrasi hanya menambah kolom nullable, sesuai protokol §7.1 pada
`docs/desain/pemisahan-invoice-per-jenis.md`. Tidak ada kolom yang diubah atau
dihapus, tidak ada data yang ditulis ulang.

Kolom ini berlaku umum secara teknis, tetapi hanya jenis `hotel` yang saat ini
punya lebih dari satu mode. Jenis lain mengabaikannya.

### 3.2 Baris rincian menumpang `description_lines`

Baris bernominal sudah tersimpan sebagai elemen JSON di `description_lines`
dengan bentuk `{label, date, date_end, detail, amount}`. Mode kamar/malam
menambah dua key pada baris kamar:

| Key | Arti |
| --- | --- |
| `unit_price` | harga per kamar per malam |
| `rooms` | jumlah kamar |

**Jumlah malam tidak disimpan.** Ia diturunkan dari `date` dan `date_end`
setiap kali dibutuhkan, sehingga tidak mungkin berselisih dengan tanggal yang
tertulis di baris yang sama.

`amount` pada baris kamar tetap diisi — hasil hitungan, bukan ketikan sales.
Konsekuensinya total invoice, PDF, dan Rincian Profit membaca `amount` seperti
biasa: tidak ada jalur uang baru yang perlu dipercaya, hanya sumber angka
`amount` yang berubah dari "diketik" menjadi "dihitung".

Tidak ada migrasi untuk bagian ini — `description_lines` sudah JSON.

### 3.2.1 Tiga jenis baris dan penandanya

`description_lines` kini menampung tiga jenis baris. Penandanya **kehadiran
key**, bukan nilainya — meneruskan cara yang sudah dipakai `amount` hari ini,
yang sengaja memakai kehadiran key supaya baris bernominal 0 tidak turun
pangkat jadi baris deskripsi:

| Jenis baris | Penanda | Nominal |
| --- | --- | --- |
| Deskripsi biasa | tidak punya key `amount` | — |
| Biaya tambahan | punya `amount`, **tanpa** key `rooms` | diketik sales |
| Kamar (mode kamar/malam) | punya `amount` **dan** key `rooms` | dihitung |

Baris kamar selalu menulis `rooms` dan `unit_price` bersamaan, termasuk saat
masih kosong. Baris biaya tambahan tidak pernah menulis keduanya. Disiplin ini
sama persis dengan yang sudah berlaku untuk `amount`, dan wajib dijaga di
ketiga tempat yang menangani bentuk baris di frontend.

## 4. Hitungan

Berlaku **hanya untuk baris kamar** (§3.2.1). Baris biaya tambahan nominalnya
tetap diketik sales dan tidak disentuh rumus ini.

```
malam  = date_end − date          (check-in 15/08, check-out 17/08 = 2 malam)
amount = unit_price × rooms × malam
total  = Σ amount seluruh baris kamar + Σ amount seluruh baris biaya tambahan
```

Konvensi malam mengikuti `BaseSalesLineRule::nightsOf()` yang sudah ada dan
kebiasaan perhotelan: menginap dihitung dari selisih hari, bukan jumlah hari
yang tersentuh.

Aturan nilai kosong — **tidak menebak**:

| Keadaan | `amount` |
| --- | --- |
| `date_end` kosong, atau lebih awal dari `date` | 0 |
| `rooms` kosong atau 0 | 0 |
| `unit_price` kosong atau 0 | 0 |

Baris yang belum lengkap bernilai 0 dan ikut terlihat sebagai 0 di layar,
sehingga sales melihat sendiri apa yang belum diisi. Tidak ada baris yang
diam-diam dianggap satu malam atau satu kamar.

## 5. Arsitektur aturan

### 5.1 Dua kelas, bukan satu kelas bercabang

`totalComposition()` selama ini konstan per jenis penjualan. Dengan dua mode,
hotel menjadi `per_unit` **atau** `line_items` tergantung invoice-nya.

Percabangan itu TIDAK ditaruh di dalam `HotelRule`: itu akan menuntut objek
`Invoice` dioper ke hampir seluruh method kontrak, sehingga ketujuh aturan ikut
berubah tanda tangannya demi satu jenis, dan menyembunyikan percabangan mode di
dalam kelas yang seharusnya menjawab satu pertanyaan saja.

Sebagai gantinya, mode memilih **kelas aturan yang berbeda**:

| Kelas | Mode | `totalComposition` | `chargeLinesDateFirstInPdf` | `unitPriceLabel` |
| --- | --- | --- | --- | --- |
| `HotelPerPaxRule` | `per_pax` (default) | `per_unit` | `false` | `Harga / pax` |
| `HotelPerRoomNightRule` | `per_room_night` | `line_items` | `true` | `Harga / kamar / malam` |

`HotelPerRoomNightRule` adalah `HotelRule` yang ada sekarang, diganti nama —
label dan pengali kamar × malam miliknya memang sudah tepat untuk mode ini, dan
assertion yang mengunci keduanya di `RuleLabelsAndMultipliersTest` tetap berlaku
apa adanya, hanya menunjuk nama kelas yang baru.

`HotelPerPaxRule` adalah kelas baru yang mendeskripsikan apa yang selama ini
benar-benar berjalan: satu harga dikali jumlah pax.

Keduanya mewarisi `BaseSalesLineRule`, dan `chargeLinesUseDateRange()` bernilai
`true` pada keduanya — kotak tanggal mulai & selesai pada baris bernominal hotel
sudah ada hari ini dan tidak dicabut oleh pekerjaan ini.

### 5.1.1 Menyalakan pemilih mode tanpa menyebut "hotel" di Vue

Kontrak mendapat satu method lagi:

```php
/** Mode hitung yang boleh dipilih sales untuk jenis ini. */
public function pricingModes(): array;
```

`BaseSalesLineRule` mengembalikan `[]` — mayoritas jenis tidak punya pilihan.
Kedua kelas hotel mengembalikan `['per_pax', 'per_room_night']`.

Vue menampilkan pemilih mode ketika daftar itu berisi lebih dari satu entri, dan
menyusun tombolnya dari isi daftar. Tanpa ini komponen harus bertanya "apakah
jenisnya hotel?" — persis percabangan tipe yang dilarang arsitektur ini.

`costingSource()` tetap `'tour_items'` pada kedua kelas. Rental memakai
`'invoice_items'` karena paketnya tak pernah disusun di muka; hotel tidak
demikian, dan mengubahnya bukan bagian dari permintaan ini.

### 5.2 Registry memilih berdasarkan invoice

`SalesLineRuleRegistry` mendapat satu method baru:

```php
public function forInvoice(?Invoice $invoice): SalesLineInvoiceRule;
```

Ia membaca jenis penjualan seperti biasa, lalu — khusus hotel — memilih kelas
sesuai `pricing_mode` invoice tersebut. `null`, nilai tak dikenal, dan invoice
yang tidak ada semuanya jatuh ke `HotelPerPaxRule`, yaitu perilaku hari ini.

`for(string $salesLine)` yang sudah ada tetap tersedia dan mengembalikan
`HotelPerPaxRule` untuk `'hotel'` — pemanggil yang hanya memegang jenis (tanpa
invoice) tidak berubah perilakunya.

Registry tetap satu-satunya tempat jenis penjualan — dan sekarang mode —
dipilih. Tidak ada `if ($type === 'hotel')` di controller, model, blade, atau
komponen Vue.

## 6. Aliran aturan ke frontend

Prop Inertia `salesLine` dibangun dari **tipe tour** dan dikirim satu kali per
halaman oleh `TourController` dan `FinanceController`. Itu tidak lagi cukup:
mode adalah milik invoice, dan satu tour bisa memuat beberapa invoice dengan
mode berbeda.

Karena itu setiap invoice membawa aturannya sendiri, sebagai atribut turunan
pada model `Invoice` yang ikut terserialisasi:

```php
// Invoice::getRulesAttribute() → payload yang sama bentuknya dengan prop
// salesLine, tapi diselesaikan lewat forInvoice($this).
protected $appends = ['rules'];
```

Dilekatkan pada model, bukan ditambahkan di kedua controller, supaya halaman
ketiga yang kelak menampilkan invoice tidak bisa lupa mengirimkannya.

Prop `salesLine` tingkat tour tetap ada dan tidak diubah — ia masih dipakai
untuk hal yang memang milik tour. Komponen membaca `inv.rules.*` untuk apa pun
yang bergantung mode.

## 7. Form proforma

### 7.1 Pemilih mode

Blok pemilih mode muncul di atas blok harga, hanya selama invoice belum
disetujui:

```
Cara Hitung:  ( ) Harga / pax     (•) Harga / kamar / malam
```

Kemunculannya ditentukan `pricingModes()` dari backend (§5.1.1) — muncul saat
daftarnya berisi lebih dari satu mode — bukan pengecekan tipe di Vue.

### 7.2 Mode per pax

Tidak ada yang berubah. Blok "Harga / pax × N pax" dan blok "Biaya Tambahan"
tampil persis seperti sekarang.

### 7.3 Mode per kamar/malam

Blok "Harga / pax" tidak ditampilkan. Sebagai gantinya ada **dua** blok
bernominal, berurutan.

**Blok pertama — Rincian Kamar.** Bentuk Rincian Tagihan rental ditambah dua
kolom isian dan dua kolom hasil:

```
MULAI       SELESAI     TIPE KAMAR  KETERANGAN   HARGA/MLM   KAMAR  MALAM   NOMINAL
[15/08/26]  [17/08/26]  [Deluxe]    [Twin bed]   [1500000]   [2]      2     6.000.000
[17/08/26]  [18/08/26]  [Suite]     [King bed]   [2500000]   [1]      1     2.500.000
```

`MALAM` dan `NOMINAL` adalah tampilan hasil hitungan — tidak bisa diketik, dan
ikut berubah begitu tanggal, harga, atau jumlah kamar diubah.

**Blok kedua — Biaya Tambahan.** Persis blok "Biaya Tambahan" yang sudah ada
untuk jenis non-rental hari ini, tanpa perubahan bentuk: label, tanggal,
keterangan, nominal yang diketik sales. Tempat biaya yang bukan per kamar per
malam — antar-jemput bandara, biaya administrasi, denda.

```
                                                     Total rincian = IDR 8.500.000
```

Total di bawah menjumlahkan kedua blok, sehingga sales melihat satu angka yang
sama dengan yang akan tercetak di PDF.

## 8. PDF invoice

Mode per kamar/malam memakai tata letak rental persis: rentang tanggal di kolom
kiri, tipe kamar dan keterangan di kolom kanan.

**Harga per malam, jumlah kamar, dan jumlah malam tidak tercetak** — ketiganya
hanya dasar perhitungan internal.

```
15/08/2026 – 17/08/2026 : Deluxe · Twin bed     IDR 6.000.000
17/08/2026 – 18/08/2026 : Suite · King bed      IDR 2.500.000
                                       Total    IDR 8.500.000
```

Mode per pax tidak berubah sama sekali.

Tata letak ini sudah ada dan digerbangi `chargeLinesDateFirstInPdf()`; pekerjaan
ini hanya membuat `HotelPerRoomNightRule` menyalakannya. Termasuk pelebaran
kolom label menjadi 180px, yang sudah dipasang bersama gerbang itu.

## 9. Berganti mode di tengah jalan

Data kedua mode disimpan utuh. Berpindah ke mode kamar tidak menghapus
`unit_price`, dan kembali ke mode pax tidak menghapus baris rinciannya. Hanya
mode yang sedang aktif yang menentukan total.

Alasannya sama dengan `unit_price` lama pada rental yang sengaja dibiarkan utuh:
sales yang salah pilih mode lalu membetulkannya tidak boleh kehilangan
pekerjaannya.

Invoice yang sudah disetujui tidak bisa berganti mode — jalur yang sama dengan
seluruh penyuntingan proforma, dijaga `ensureNotApproved()` yang sudah ada.

## 10. Tes

1. `pricing_mode` `null` pada invoice hotel menghasilkan total dan tampilan yang
   identik dengan sebelum fitur ini ada.
2. `forInvoice()` mengembalikan `HotelPerPaxRule` untuk `null`, `'per_pax'`, dan
   nilai tak dikenal; `HotelPerRoomNightRule` hanya untuk `'per_room_night'`.
3. Jenis selain hotel tidak terpengaruh `pricing_mode` apa pun yang tersimpan.
4. Hitungan: `unit_price × rooms × malam`, dengan malam = selisih hari.
5. Tiap keadaan kosong pada §4 menghasilkan `amount` 0, bukan tebakan.
6. Total invoice mode kamar = jumlah `amount` baris kamar DAN baris biaya
   tambahan, dan tidak memakai pax sama sekali.
7. Baris biaya tambahan pada mode kamar mempertahankan nominal yang diketik —
   tidak ikut dihitung ulang menjadi 0 karena tak punya `rooms`.
8. Penanda `rooms` memisahkan baris kamar dari baris biaya tambahan, dan tetap
   memisahkan dengan benar setelah invoice disimpan lalu dimuat ulang.
9. PDF mode kamar memakai tata letak tanggal-di-kiri; PDF mode pax tidak berubah.
10. PDF mode kamar tidak memuat harga per malam maupun jumlah kamar.
11. Berganti mode bolak-balik tidak menghilangkan `unit_price` maupun baris
    rincian.
12. Invoice hotel yang sudah disetujui menolak perubahan mode.
13. `pricingModes()` berisi dua entri hanya untuk hotel, kosong untuk enam jenis
    lain — disapu dari registry agar jenis baru tak lolos tanpa keputusan.

## 11. Di luar cakupan

- Mengubah pengali untuk lima jenis penjualan lain (Fase 3 penuh pada
  `docs/desain/pemisahan-invoice-per-jenis.md`).
- Mengalihkan `syncProformaTotal()` membaca kolom `sales_line`. Itu memicu
  kewajiban serah-terima §3.4.2 (mengisi `sales_line` saat invoice baru dibuat,
  plus backfill ulang) yang tidak perlu disentuh untuk mencapai hasil ini.
  Pembacaan jenis tetap lewat `tour->type` seperti sekarang.
- Mengisi kolom `billing_quantities`. Jumlah kamar dan malam hidup di baris
  rincian, bukan di kolom tingkat invoice.
- Mencetak label jenis penjualan di PDF (Fase 4).
- Mode ganda untuk jenis selain hotel.
