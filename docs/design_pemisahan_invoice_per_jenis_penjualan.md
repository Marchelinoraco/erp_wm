# Desain: Pemisahan Aturan Invoice per Jenis Penjualan

> **Status (24 Jul 2026): DRAFT — menunggu persetujuan sebelum implementasi.** ERP ini sudah berjalan di production dan dipakai sales sehari-hari. Setiap keputusan di dokumen ini mempertimbangkan itu — lihat §7 Protokol Keamanan Data sebelum menyetujui.

---

## 1. Masalah

Alur invoice saat ini memakai **satu rumus untuk ketujuh jenis penjualan** (`tour`, `hotel`, `guide`, `rental`, `mice`, `document`, `ticketing`):

```php
$pax   = (int) ($this->tour?->pax ?? $this->pax ?? 1);
$total = (float) $this->unit_price * max($pax, 1);
```

Rumus ini benar untuk tour (dijual per orang), tetapi dipaksakan pada jenis yang ditagih dengan cara berbeda — guide per hari, hotel per kamar per malam, rental per unit per hari. Sudah didokumentasikan sebagai temuan di [`docs/logika-pembuatan-invoice/10-temuan.md` §10.4](logika-pembuatan-invoice/10-temuan.md).

Akibatnya sales terpaksa membagi nominal supaya `unit_price × pax` menghasilkan angka yang benar — dan angka hasil bagi itu tersimpan permanen, bukan angka asli dari daftar harga.

**Masalah kedua, lebih mendasar:** `tour.type` dipakai sebagai payung untuk semua jenis penjualan, padahal "jasa guide" atau "penjualan hotel" bukan sebuah tour. Konsep `Tour` yang menaungi segalanya membuat kode ikut menganggap semua jenis penjualan setara — itulah sebab rumus tunggal ini ada.

**Tujuan pemilik produk:** setiap jenis penjualan (Tour, Hotel, Jasa Guide, Transport, MICE, Document, Ticketing) berdiri sendiri secara aturan hitung, sehingga mengubah cara hitung satu jenis **tidak berisiko mengganggu** jenis lain.

---

## 2. Keputusan yang Sudah Dikunci

| # | Keputusan |
|---|---|
| D1 | Kedalaman pemisahan: **aturan harga & label saja** (bukan field/validasi khusus, bukan alur tahapan berbeda) |
| D2 | Jenis penjualan (`sales_line`) **berdiri sendiri**, bukan diturunkan dari `tour.type` |
| D3 | `sales_line` disimpan sebagai kolom sendiri di invoice, **dikunci saat invoice dibuat** — tidak pernah dibaca ulang dari tour |
| D4 | 7 jenis: Tour, Hotel, Jasa Guide, Transport (kode `rental` di database), MICE, Document, Ticketing |
| D5 | Document dan Ticketing **tetap ada**, ikut dipisahkan seperti jenis lain (bukan memakai aturan bawaan tanpa penyesuaian) |
| D6 | Struktur: **field sendiri di invoice** — bukan tabel/model terpisah, bukan sekadar ganti istilah UI |
| D7 | Kontrak aturan mendukung **lebih dari satu pengali** (hotel = kamar × malam) |
| D8 | MICE dihitung sebagai **harga paket × jumlah peserta** (bukan jumlah dari Quotation Items) |
| D9 | Frontend memakai **satu berkas definisi per jenis** (`resources/js/sales-lines/*.js`), bukan 7 folder halaman terpisah. Halaman `Index`/`Create`/`Edit` tetap bersama dan membaca dari registry. |
| D10 | Label harga & pengali **hanya hidup di backend** dan dikirim ke frontend lewat payload Inertia — tidak digandakan di berkas definisi frontend |
| D11 | Perapian 38 controller ke folder fitur adalah **proyek terpisah**, di luar spec ini (lihat §6) |

---

## 3. Arsitektur

### 3.1 Kontrak `SalesLineInvoiceRule`

Mengikuti pola yang sudah ada di proyek ini untuk kebutuhan serupa — kontrak + implementasi per varian, seperti `app/Contracts/BrevoGateway.php` + `app/Services/Brevo/`.

```
app/Contracts/
└── SalesLineInvoiceRule.php        interface

app/Services/SalesLine/
├── SalesLineRuleRegistry.php       key → instance rule
├── Multiplier.php                  value object: { key, label, value }
├── BaseSalesLineRule.php           total = unit_price × Π(multiplier.value)
├── TourRule.php                    pengali: pax
├── HotelRule.php                   pengali: rooms × nights
├── GuideRule.php                   pengali: hari
├── TransportRule.php               pengali: hari
├── MiceRule.php                    pengali: pax (harga paket × peserta)
├── DocumentRule.php                pengali: dokumen
└── TicketingRule.php               pengali: tiket
```

Setiap rule menjawab tiga pertanyaan lewat kontrak:

```php
interface SalesLineInvoiceRule
{
    /** Label kolom harga di form & PDF, mis. "Harga / kamar / malam". */
    public function unitPriceLabel(): string;

    /**
     * Pengali yang dipakai menghitung total, dengan nilai AWAL saat invoice
     * dibuat (mis. dari tour->pax atau dari rentang tanggal item).
     *
     * @return Multiplier[]
     */
    public function defaultMultipliers(Invoice $invoice): array;

    /** total = unit_price × produk seluruh nilai Multiplier. */
    public function calculateTotal(float $unitPrice, array $multipliers): float;
}
```

`BaseSalesLineRule` mengimplementasikan `calculateTotal()` sebagai perkalian generik; subclass hanya mengganti `unitPriceLabel()` dan `defaultMultipliers()`. Hotel adalah satu-satunya yang mengoverride dengan dua entri di array pengali.

### 3.1.1 Label dan nilai awal per jenis

| Jenis | `unitPriceLabel()` | Pengali | Sumber nilai awal |
|---|---|---|---|
| Tour | Harga / pax | `pax` | `tour.pax`, fallback `1` |
| Hotel | Harga / kamar / malam | `rooms`, `nights` | `rooms = 1`; `nights` = selisih `tour.start_date`–`tour.end_date`, fallback `1` |
| Jasa Guide | Harga / hari | `hari` | selisih `tour.start_date`–`tour.end_date` inklusif, fallback `1` |
| Transport | Harga / hari | `hari` | sama seperti Jasa Guide |
| MICE | Harga paket / peserta | `pax` | `tour.pax`, fallback `1` |
| Document | Harga / dokumen | `dokumen` | `tour.pax`, fallback `1` |
| Ticketing | Harga / tiket | `tiket` | `tour.pax`, fallback `1` |

**Sumber pengali "hari" untuk Jasa Guide dan Transport** sengaja memakai rentang tanggal **tour** (`tour.start_date` ke `tour.end_date`), bukan tanggal per item — sejalan dengan D1 (tidak menambah field khusus per jenis di invoice). Bila salah satu tanggal kosong, pengali jatuh ke `1` dan sales mengoreksi manual di Fase 3.

Nilai-nilai di atas hanya berlaku sebagai **nilai awal saat invoice dibuat** (D3). Setelah itu, `billing_quantities` lepas dari tour dan hanya berubah bila sales mengeditnya langsung di invoice (Fase 3).

### 3.2 Kenapa bentuk ini memberi isolasi

Mengubah cara hitung Jasa Guide berarti mengedit **`GuideRule.php` saja**. Tidak ada `if ($type === 'guide')` yang tersebar di controller atau model — satu-satunya percabangan jenis ada di `SalesLineRuleRegistry::get($salesLine)`, dan itu pun cuma pemetaan string ke instance kelas, bukan logika bisnis.

Ini juga berarti menambah jenis penjualan ke-8 nanti adalah pekerjaan "tambah satu berkas", bukan "sunting berkas yang sudah ada di enam tempat".

### 3.3 Kolom baru pada `invoices`

```php
$table->string('sales_line', 20)->nullable()->after('currency');
$table->json('billing_quantities')->nullable()->after('sales_line');
```

| Kolom | Isi | Kapan diisi |
|---|---|---|
| `sales_line` | `tour`, `hotel`, `guide`, `rental`, `mice`, `document`, `ticketing` | saat invoice dibuat, dari `tour->type` **hanya sebagai nilai awal** — setelahnya lepas dari tour |
| `billing_quantities` | `{"pax": 20}` atau `{"rooms": 3, "nights": 4}` | saat invoice dibuat (nilai awal dari rule), bisa diedit sales selama belum disetujui |

Rumus baru di `Invoice::syncProformaTotal()`:

```php
$rule    = SalesLineRuleRegistry::get($this->sales_line);
$total   = $rule->calculateTotal((float) $this->unit_price, $this->billing_quantities);
```

**`tour.pax` berhenti menjadi pengali tagihan.** Ia kembali ke satu peran: ukuran rombongan untuk ditampilkan di PDF. Pengali tagihan yang sebenarnya hidup di `invoice.billing_quantities`, terpisah dan dapat diedit sendiri.

### 3.4 Yang TIDAK berubah

- Tiga tahap alur (`baseline` → `detail` → `approved`) — sama untuk ketujuh jenis
- `ensureNotApproved()` dan seluruh gerbang persetujuan
- `finance_number`, pembuatan Bill otomatis, ledger — Keuangan tidak tersentuh
- Struktur tabel `orders`/`payments` — tidak ada, ini modul yang berbeda dari fitur Midtrans di `client_wm`/`api_wm`
- Panel Vue 1.282 baris — **tidak dipecah**. Perubahan pada Fase 3 hanya mengganti label statis dan menambah input pengali; struktur komponen tetap.
- `Pages/Tours/` tetap satu folder dengan tiga halaman bersama (`Index`, `Create`, `Edit`) — lihat §3.5 untuk alasannya

### 3.5 Definisi jenis penjualan di frontend

#### Kondisi sekarang

`resources/js/lib/inquiryTypes.js` sudah memuat definisi per jenis penjualan — `INQUIRY_TYPES`, `TYPE_BADGE`, `TYPE_FIELDS`, `typeLabel()`, `emptyDetails()` — hanya saja ditumpuk dalam satu berkas. Pola yang dibutuhkan sudah ada embrionya; yang kurang adalah pemisahannya.

`Pages/` sendiri **sudah rapi per fitur** (13 folder: `Finance`, `Marketing`, `Products`, `Customers`, dan seterusnya). Hanya `Tours` yang menampung tujuh jenis penjualan sekaligus.

#### Struktur target

```
resources/js/sales-lines/
├── index.js          registry + helper resolusi
├── tour.js           inbound & outbound (arah = sub-field, bukan berkas terpisah)
├── hotel.js
├── guide.js
├── transport.js
├── mice.js
├── document.js
└── ticketing.js
```

Tiap berkas memuat segala yang khas jenis itu:

```js
// sales-lines/guide.js
export default {
    key:   'guide',
    label: 'Penjualan Jasa Guide',
    badge: { class: 'bg-emerald-100 text-emerald-700' },
    fields: [...],                    // pindahan dari TYPE_FIELDS
    panels: ['header', 'items', 'invoices', 'costRequests',
             'operasional', 'quotation', 'qItems', 'history'],
}
```

`Index.vue`, `Create.vue`, dan `Edit.vue` tetap **satu berkas bersama** yang membaca definisi dari registry. `Edit.vue` yang sekarang memakai `v-if="isTour"` dan `v-if="isMice"` berganti menjadi perulangan atas `definisi.panels`.

Setelah seluruh isinya dipindahkan, `lib/inquiryTypes.js` dihapus.

#### Kenapa bukan tujuh folder halaman

Diukur langsung dari kode:

| Berkas | Ukuran | Titik percabangan tipe |
|---|---:|---:|
| `Create.vue` | 24 KB | 9 |
| `Index.vue` | 18 KB | 16 |
| `Edit.vue` | 9 KB | 9 |
| **Total** | **51 KB** | **34** |

Ketiga halaman itu sekitar **95% identik** untuk ketujuh jenis. Memecahnya menjadi tujuh folder mengubah 51 KB menjadi ~360 KB, dan setiap perubahan umum — menambah satu kolom di tabel daftar penjualan, misalnya — menjadi **tujuh kali penyuntingan**.

Itu kebalikan dari tujuan yang diminta. Folder terpisah terlihat rapi di file explorer, tetapi menaikkan ongkos setiap perubahan tujuh kali lipat. Registry memberi isolasi yang sama tanpa duplikasi: menambah jenis ke-8 cukup menambah satu berkas dan mendaftarkannya di `index.js`.

#### Pembagian sumber kebenaran

Label harga dan pengali **tidak digandakan** di berkas definisi frontend. Dua sumber kebenaran untuk aturan penagihan akan berselisih cepat atau lambat.

| Sumber kebenaran | Isi | Sampai ke frontend lewat |
|---|---|---|
| Backend `SalesLineRule` | label harga, nama & jumlah pengali, rumus total | payload Inertia |
| Frontend `sales-lines/*.js` | panel yang tampil, field form, warna badge, label jenis | langsung |

Tidak ada tumpang tindih: backend memegang segala yang menyangkut uang, frontend memegang segala yang menyangkut tampilan.

---

## 4. Rencana Bertahap

| Fase | Isi | Perilaku terlihat berubah? |
|---|---|:---:|
| 0 | Characterization test — kunci perilaku SEKARANG untuk ketujuh jenis, sebelum kode produksi disentuh | Tidak |
| 1 | Kontrak + registry + 7 rule ditulis, dipanggil dari `syncProformaTotal()` tapi **menghasilkan angka identik** dengan rumus lama | Tidak |
| 2 | Migrasi kolom + backfill data lama + `syncProformaTotal()` sepenuhnya lewat registry | Tidak (lihat §7) |
| 3 | Pengali `billing_quantities` bisa diedit langsung di panel invoice + label harga mengikuti jenis (dikirim dari backend, D10) | **Ya** |
| 4 | Label jenis penjualan ikut tercetak di PDF invoice | **Ya** |
| 5 | `lib/inquiryTypes.js` dipecah ke `sales-lines/*.js`; `Edit.vue` beralih dari `v-if` per tipe ke perulangan `panels` | Tidak |

Fase 0–2 sengaja tidak mengubah satu pun yang dilihat pengguna — kalau ada yang salah, ketahuan sebelum ada perilaku baru yang membingungkan sales. Fase 3 baru menghadirkan nilai yang diminta (§10.4 selesai).

---

## 5. Risiko

| # | Risiko | Tingkat | Mitigasi |
|---|---|:---:|---|
| R1 | Backfill mengubah nominal invoice lama secara diam-diam | **Tinggi** | §7 — backfill dirancang mempertahankan nilai persis; verifikasi wajib nol selisih sebelum lanjut |
| R2 | Test suite tipis — 0 dari 21 berkas test menyentuh alur pembuatan invoice | **Tinggi** | Fase 0 wajib sebelum Fase 1 dimulai |
| R3 | `syncProformaTotal()` dipanggil dari `approve()` — salah di sini menulis angka permanen ke Keuangan | **Tinggi** | Sudah terverifikasi: `approve()`, `updateProforma()`, `lockBaseline()` ketiganya didahului `ensureNotApproved()` — invoice yang sudah disetujui tidak pernah dihitung ulang, di rumus lama maupun baru. Ditambah test regresi eksplisit (§7.4). |
| R4 | PDF mencetak "Total Pax" untuk semua jenis, termasuk yang tidak relevan | Sedang | Ditangani di Fase 4, di luar cakupan Fase 0–3 |
| R5 | Panel Vue besar, autosave & anti-race di dalamnya | Sedang | Fase 1–3 tidak menyentuh mekanisme autosave sama sekali — hanya label dan satu blok input pengali baru |
| R6 | Data lama dengan `tour.pax` bernilai 0/null | Rendah | `max($pax,1)` sudah menutupinya sekarang; backfill menegaskan eksplisit per baris |
| R7 | Sistem sudah dipakai production — lihat §7 | — | Bagian tersendiri |
| R8 | Fase 5 mengubah `Edit.vue` dari `v-if` per tipe menjadi perulangan `panels` — panel bisa hilang diam-diam bila daftar `panels` salah ketik | Sedang | Registry memvalidasi nama panel terhadap daftar komponen yang terdaftar dan melempar galat saat build, bukan diam. Test manual per jenis: buka satu penjualan tiap jenis, pastikan panel yang tampil sama persis dengan sebelum perubahan. |
| R9 | Label harga dari backend belum sampai saat halaman pertama render | Rendah | Label ikut di payload Inertia awal, bukan permintaan terpisah — tidak ada jendela kosong |

---

## 6. Di Luar Lingkup

- Memecah invoice jadi tabel/model terpisah per jenis
- Memecah `Pages/Tours/` menjadi tujuh folder halaman (lihat §3.5 untuk alasannya)
- Mengganti istilah "Tour" menjadi "Penjualan" di seluruh UI
- Field dan validasi khusus per jenis (mis. guide wajib isi bahasa & area)
- Alur tahapan berbeda per jenis penjualan
- Perubahan apa pun pada modul Keuangan, ledger, atau Bill
- Memecah `InvoicesPanel.vue` menjadi komponen per jenis

### Proyek terpisah: perapian folder controller

`app/Http/Controllers/` saat ini berisi **38 berkas datar** tanpa satu pun folder fitur — dari `FinanceReportController` (24 KB) sampai `TourHistoryController` (1 KB), hanya `Auth/` yang sudah terpisah. Merapikannya ke folder per fitur adalah kebutuhan yang sah dan sudah disepakati akan dikerjakan (D11).

Namun ia **tidak masuk spec ini** karena menyentuh seluruh 13 fitur — Keuangan, Marketing, Produk, Supplier, Booking — yang sama sekali tidak berhubungan dengan jenis penjualan. Menggabungkannya berarti satu perubahan raksasa menyentuh hampir seluruh aplikasi production sekaligus, sementara test suite tidak cukup untuk mengamankannya.

Dikerjakan setelah spec ini selesai, dengan spec dan jadwal rilisnya sendiri.

---

## 7. Protokol Keamanan Data

ERP ini **sudah berjalan di production dan dipakai sales sehari-hari**. Bagian ini adalah syarat mutlak, bukan saran — tidak ada fase yang boleh menyentuh database production tanpa melewati protokol ini.

### 7.1 Prinsip: migrasi hanya menambah

Migrasi pada Fase 2 **hanya menambah dua kolom nullable**:

```php
public function up(): void
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->string('sales_line', 20)->nullable()->after('currency');
        $table->json('billing_quantities')->nullable()->after('sales_line');
    });
}
```

Tidak ada kolom dihapus, diganti nama, atau diubah tipenya. Tidak ada baris dihapus. Tidak ada tabel lain tersentuh. Ini berarti:

- `down()` migrasinya adalah `dropColumn` kedua kolom itu — rollback tidak kehilangan data apa pun karena kolom lama tidak pernah disentuh.
- Baris yang ada tetap utuh persis seperti sebelum migrasi berjalan, hanya bertambah dua kolom kosong.

### 7.2 Backup wajib sebelum setiap fase yang menyentuh production

Rutinitas backup VPS yang sudah ada **dipakai**, dengan satu tambahan: **backup manual terpisah tepat sebelum menjalankan migrasi**, bukan mengandalkan jadwal rutin yang mungkin baru berjalan besok. Verifikasi dump benar-benar bisa di-restore sebelum melanjutkan — bukan sekadar filenya ada.

### 7.3 Wajib diuji di salinan data production dulu

Sebelum migrasi menyentuh database production sungguhan:

1. Ambil dump production terbaru ke lingkungan staging/lokal
2. Jalankan migrasi + backfill Fase 2 di salinan tersebut
3. Jalankan query verifikasi (§7.5) di salinan itu
4. **Hasil harus nol selisih.** Bila ada satu pun invoice yang totalnya bergeser, Fase 2 belum boleh jalan di production — perbaiki dulu logika backfill-nya.

### 7.4 Invoice yang sudah disetujui tidak pernah tersentuh — dan ini diuji, bukan diasumsikan

Sudah diverifikasi langsung ke kode: `syncProformaTotal()` dipanggil di tiga tempat (`updateProforma`, `lockBaseline`, `approve`), dan ketiganya didahului `ensureNotApproved()`. Invoice dengan `approved_at` terisi tidak akan pernah masuk jalur perhitungan ulang — baik dengan rumus lama maupun baru.

Fase 0 menambahkan test regresi eksplisit untuk mengunci jaminan ini:

```php
public function test_invoice_yang_sudah_disetujui_totalnya_tidak_pernah_berubah(): void
{
    $invoice = Invoice::factory()->approved()->create(['total' => 5_000_000]);

    // Backfill / rule registry / apa pun perubahan Fase 1-2 dijalankan di sini

    $this->assertEquals(5_000_000, $invoice->fresh()->total);
}
```

Test ini masuk sebagai gerbang wajib lolos sebelum Fase 2 dianggap selesai.

### 7.5 Query verifikasi backfill — nol selisih atau tidak lanjut

Sebelum dan sesudah backfill, jumlahkan total seluruh invoice per status dan bandingkan:

```sql
-- Jalankan SEBELUM migrasi, simpan hasilnya
SELECT id, number, total, total_idr FROM invoices ORDER BY id;
```

```sql
-- Jalankan SESUDAH backfill
SELECT id, number, total, total_idr FROM invoices ORDER BY id;
```

Kedua hasil dibandingkan baris per baris (skrip sederhana, bukan manual). **Toleransi: nol.** Bila ada satu invoice pun yang berbeda, backfill belum boleh dianggap aman.

### 7.6 Peluncuran bertahap dengan jendela pengamatan

- Fase 0–2 dirilis **sebagai satu paket**, karena keduanya sengaja tidak mengubah perilaku yang terlihat pengguna.
- Setelah Fase 0–2 naik ke production, **amati dulu** sebelum melanjutkan ke Fase 3 — sales melanjutkan pekerjaan normal, dan invoice baru yang dibuat/diproforma/dikunci/disetujui pada periode ini adalah bukti hidup bahwa rumus baru berperilaku sama dengan rumus lama.
- Fase 3 (pengali dapat diedit, label berubah) baru dirilis setelah jendela pengamatan itu bersih.
- Fase 4 (label di PDF) menyusul setelah Fase 3 stabil.
- Fase 5 (pemecahan `inquiryTypes.js` ke `sales-lines/`) **tidak bergantung pada Fase 3 maupun 4** dan tidak mengubah perilaku yang terlihat. Ia boleh dirilis kapan saja setelah Fase 2, termasuk berbarengan dengan jendela pengamatan — asalkan tidak digabung dalam satu rilis dengan Fase 3, agar bila ada panel yang hilang (R8) penyebabnya tidak tercampur dengan perubahan label.

### 7.7 Migrasi terputus di tengah jalan

Perubahan struktur tabel di MySQL tidak selalu terbungkus satu transaksi. Bila `php artisan migrate` terputus (koneksi putus, proses mati):

- Menambah kolom bersifat idempoten jika ditulis dengan pemeriksaan (`Schema::hasColumn`) — migrasi aman dijalankan ulang.
- Backfill dijalankan sebagai command terpisah dari migrasi struktur (bukan di dalam `up()`), dan **idempoten**: hanya mengisi baris yang `sales_line`-nya masih `null`. Menjalankan ulang tidak menggandakan atau merusak apa pun.

### 7.8 Ringkasan syarat sebelum Fase 2 boleh menyentuh production

- [ ] Backup manual terbaru sudah diambil dan diverifikasi bisa di-restore
- [ ] Migrasi + backfill sudah dijalankan di salinan data production, bukan data dummy
- [ ] Query verifikasi menunjukkan nol selisih pada seluruh invoice
- [ ] Test regresi §7.4 lolos
- [ ] Command backfill bersifat idempoten (aman dijalankan ulang)
- [ ] Rencana rollback (`down()` migrasi) sudah diuji jalan di salinan yang sama
