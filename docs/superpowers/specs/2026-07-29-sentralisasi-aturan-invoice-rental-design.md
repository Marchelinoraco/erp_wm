# Desain: Sentralisasi Aturan Invoice per Jenis + Mode Tagihan Baris untuk Rental

> Status (29 Jul 2026): disetujui, siap direncanakan.
>
> Lanjutan tematik dari [`docs/desain/pemisahan-invoice-per-jenis.md`](../../desain/pemisahan-invoice-per-jenis.md) (Fase 0–2 sudah live di production). Dokumen itu tetap berlaku; yang di sini tidak menggantikannya, hanya melanjutkan dan mengoreksi satu kekeliruan yang terjadi 29 Jul 2026.

---

## 1. Latar: tiga hal yang mendorong pekerjaan ini

### 1.1 Label satuan yang belum diputuskan sudah tampil ke customer

Pada 29 Jul 2026 dua commit — `1be88ff` (panel invoice) dan `bbde75f` (PDF customer) — mulai memakai `SalesLineRule::unitPriceLabel()` untuk mengganti kata "pax" menjadi satuan per jenis ("hari", "dokumen", "tiket").

Sebelum itu method `unitPriceLabel()` **ada tapi dorman** — ditulis di Fase 1 sesuai [§3.1.1 dokumen desain](../../desain/pemisahan-invoice-per-jenis.md), tidak pernah dipanggil siapa pun. Nilainya adalah **asumsi rancangan yang belum pernah divalidasi ke pemilik produk**, karena tidak pernah terlihat.

Menampilkannya menaikkan asumsi itu jadi pernyataan bisnis di dokumen keuangan ke customer. Saat ditanyakan, pemilik produk menegaskan: satuan untuk **rental, hotel, ticketing, dan jasa guide belum diputuskan** — hanya tour (inbound/outbound) dan MICE yang sudah pasti per pax.

Memperparah: angka yang dikalikan **selalu `tour.pax`** sampai Fase 3 dokumen lama selesai. Jadi untuk jasa guide dengan rombongan 20 orang, PDF customer berbunyi `× 20 hari` — padahal 20 itu jumlah orang, bukan jumlah hari.

`bbde75f` sudah berada di `main`. Konsekuensinya nyata, bukan hipotetis.

### 1.2 Aturan profit per jenis tersebar di tiga berkas, dua bahasa

Satu-satunya aturan uang yang bercabang per jenis — **profit tipe `tour` dihitung dari tagihan customer, tipe lain dari selisih per item** ([§8.3](../../logika-pembuatan-invoice/08-perbedaan-per-tipe.md)) — hidup terduplikasi di:

| Berkas | Bentuk |
|---|---|
| `resources/js/Components/Tours/InvoicesPanel.vue` | `isTourType` (dipakai di `invProfit`, `invMargin`, `copyProfitTable`, template) |
| `resources/js/Components/Tours/CostingPanel.vue` | `fromInvoice` |
| `app/Http/Controllers/InvoiceController.php` | `$isTour` di `profitPdf()` |

Mengubah aturan profit satu jenis menuntut penyuntingan tiga berkas serempak. Lupa satu → angka di panel, di Ringkasan Biaya, dan di PDF Rincian Profit berbeda tanpa galat apa pun.

Duplikasi label di §1.1 membuktikan risiko ini bukan teoretis: peta satuan di frontend dan backend sudah berselisih dalam hitungan jam (hotel `pax` vs `malam`, MICE `pax` vs `peserta`).

### 1.3 Rental butuh cara menyusun total yang berbeda

Rumus `total = unit_price × pax` mengasumsikan satu harga satuan dikalikan kuantitas. Rental kerap menagih beberapa unit berbeda dengan harga masing-masing — mis. Avanza Rp800.000 (22 Jul) + Innova Reborn Rp1.050.000 + biaya luar kota Rp200.000 (25 Jul).

Hari ini sales menjumlahkannya manual di luar sistem lalu mengetik hasilnya (Rp2.050.000) ke satu field "Harga / pax", dengan rinciannya ditulis sebagai **teks bebas** di kolom deskripsi. Sistem tidak pernah memeriksa apakah penjumlahan manual itu benar.

---

## 2. Keputusan yang sudah dikunci

| # | Keputusan |
|---|---|
| D1 | Satuan pengali kembali ke **"pax"** untuk semua jenis, di panel maupun PDF — bukan karena "pax" benar untuk semua, tapi karena angka yang dikalikan memang jumlah pax. Jujur apa pun keputusan bisnis nanti. |
| D2 | Satuan per jenis untuk rental, hotel, ticketing, jasa guide **ditunda** sampai pemilik produk memutuskan. Tour (inbound/outbound) dan MICE sudah pasti per pax. |
| D3 | `SalesLineRuleRegistry` di backend adalah **satu-satunya sumber kebenaran** aturan uang per jenis. Frontend tidak boleh punya peta jenis sendiri (menegaskan D10 dokumen lama). |
| D4 | Aturan disalurkan ke frontend lewat **payload Inertia** pada halaman `Tours/Edit`, sebagai prop `salesLine`. |
| D5 | Hanya properti yang **benar-benar dikonsumsi** yang dikirim. `unitPriceLabel` belum dikirim karena satuannya masih ditunda (D2). |
| D6 | Rental memakai komposisi total **`line_items`** (total = jumlah nominal baris). Enam jenis lain tetap `per_unit` (`unit_price × pengali`). |
| D7 | Komposisi total ditentukan **per jenis** lewat rule, bukan dipilih sales per invoice. |
| D8 | Invoice yang **sudah disetujui tidak pernah dihitung ulang** — dijamin `ensureNotApproved()` pada ketiga pemanggil `syncProformaTotal()`. |
| D9 | **Tidak ada migrasi data otomatis** untuk invoice rental lama. Ditangani lewat peringatan di panel + koreksi manual oleh sales, yang tahu rincian sebenarnya — lihat §5. |

---

## 3. Arsitektur

Dikerjakan dalam tiga fase yang masing-masing bisa dirilis sendiri.

### Fase 1 — Kembalikan satuan pengali ke "pax"

Membatalkan `1be88ff` dan `bbde75f` sampai keadaan sebelum 29 Jul 2026.

| Berkas | Perubahan |
|---|---|
| `resources/js/Components/Tours/InvoicesPanel.vue` | Hapus konstanta `BILLING_UNIT_LABELS` dan computed `billingUnit`; teks kembali ke `Harga / pax` dan `× N pax` |
| `resources/views/invoice.blade.php` | `{{ $billingUnit ?? 'pax' }}` → `pax` |
| `app/Http/Controllers/InvoiceController.php` | Hapus method privat `billingUnitNoun()` dan key `billingUnit` dari payload view |

`SalesLineRule::unitPriceLabel()` **tetap ada dan tidak diubah** — kembali dorman seperti sebelumnya, menunggu D2 diputuskan. Menghapusnya akan membuang rancangan yang masih berlaku.

Perilaku terlihat berubah: ya — mengembalikan keadaan sebelum 29 Jul.

### Fase 2 — Pusatkan aturan profit ke backend

**Kontrak** — `app/Contracts/SalesLineInvoiceRule.php` bertambah satu method:

```php
/** true = profit dari tagihan customer (total_idr − Σ cost item); false = profit per item (Σ sell − Σ cost). */
public function profitFromRevenue(): bool;
```

- `BaseSalesLineRule::profitFromRevenue()` → `false` (perilaku mayoritas)
- `TourRule::profitFromRevenue()` → `true` (satu-satunya yang menimpa)

**Penyaluran** — `TourController::edit()` menambah satu prop:

```php
'salesLine' => [
    'key'               => $tour->type,
    'profitFromRevenue' => $rule->profitFromRevenue(),
],
```

**Konsumen** — ketiganya berhenti memutuskan sendiri:

| Berkas | Sebelum | Sesudah |
|---|---|---|
| `InvoicesPanel.vue` | `const isTourType = computed(() => props.tour.type === 'tour')` | baca `props.salesLine.profitFromRevenue` |
| `CostingPanel.vue` | `props.tour.type === 'tour' && ...` | `props.salesLine.profitFromRevenue && ...` |
| `InvoiceController::profitPdf()` | `$isTour = $tour->type === 'tour'` | `$isTour = $rule->profitFromRevenue()` |

`Tours/Edit.vue` meneruskan prop `salesLine` ke `InvoicesPanel` dan `CostingPanel`.

Perilaku terlihat berubah: **tidak**. Refactor murni — angka profit, margin, dan isi PDF Rincian Profit harus identik sebelum/sesudah.

### Fase 3 — Mode tagihan baris-bernominal untuk rental

**Kontrak** bertambah satu method lagi:

```php
/** 'per_unit' = unit_price × pengali · 'line_items' = jumlah nominal baris deskripsi. */
public function totalComposition(): string;
```

- `BaseSalesLineRule::totalComposition()` → `'per_unit'`
- `TransportRule::totalComposition()` → `'line_items'` (rental; enam lainnya tidak disentuh)

Prop `salesLine` bertambah key `totalComposition`.

**Perhitungan** — `Invoice::syncProformaTotal()`:

```php
$base = $rule->totalComposition() === 'line_items'
    ? 0.0
    : $rule->calculateTotal((float) $this->unit_price, [new Multiplier('pax', 'Peserta', $pax)]);

// Penjumlahan nominal baris SUDAH ADA hari ini dan tidak diubah:
$total = $base + collect($this->description_lines ?? [])->sum(fn ($l) => (float) ($l['amount'] ?? 0));
```

**Yang sudah berjalan hari ini dan tidak perlu dibangun:**

- Penjumlahan `description_lines[].amount` ke total — sudah ada di `syncProformaTotal()`
- Validasi backend `description_lines.*.date` dan `.amount` — sudah ada di `updateProforma()`, **tidak perlu diubah**
- Pencetakan baris bernominal sebagai baris tersendiri berkolom nominal di PDF — sudah ada
- Penjumlahan sisi frontend di `proformaTotal()` — sudah ada

**Yang kurang:**

| Berkas | Perubahan |
|---|---|
| `InvoicesPanel.vue` | Tambah input **tanggal** pada baris bernominal (sekarang hanya label/keterangan/nominal); `saveProforma()` mengirim `date: l.date` alih-alih `date: ''` yang di-hardcode |
| `InvoicesPanel.vue` | Untuk `totalComposition === 'line_items'`: blok "Harga / pax" tidak dirender; bagian baris bernominal naik jadi utama dengan judul yang sesuai (bukan lagi "Biaya Tambahan (di luar harga/pax)") |
| `resources/views/invoice.blade.php` | Baris bernominal ikut mencetak tanggal; baris `Price :` disembunyikan saat `unit_price = 0` agar tidak muncul baris kosong |
| `InvoicesPanel.vue` | Banner peringatan untuk invoice rental yang masih menyimpan nilai di `unit_price` — lihat §5c |

Perilaku terlihat berubah: ya, untuk rental.

---

## 4. Risiko

| # | Risiko | Tingkat | Mitigasi |
|---|---|:---:|---|
| R1 | **Invoice rental yang belum disetujui totalnya jadi Rp0.** Begitu rental beralih ke `line_items`, invoice lama yang nilainya ada di `unit_price` (bukan di baris bernominal) dihitung ulang jadi `0 + 0` **saat sales menyimpan proformanya lagi** — tagihan hilang. Sudah dipastikan ada minimal satu: `INV-2026-13-0002`, proforma, `unit_price = 2.050.000`, tanpa baris bernominal. | **Sedang** | Peringatan eksplisit di panel + koreksi manual — lihat §5. Menurunkan tingkat dari Tinggi setelah dipastikan `syncProformaTotal()` tidak berjalan saat halaman sekadar dibuka, sehingga tidak ada kehilangan diam-diam. |
| R2 | Invoice rental yang sudah disetujui ikut berubah | Rendah | Tidak mungkin: ketiga pemanggil `syncProformaTotal()` (`updateProforma`, `lockBaseline`, `approve`) didahului `ensureNotApproved()`. Dikunci test regresi. |
| R3 | Fase 2 diam-diam menggeser angka profit | Sedang | Refactor murni; test karakterisasi membandingkan `invProfit`/`invMargin`/isi PDF sebelum-sesudah. Nol selisih atau tidak lanjut. |
| R4 | `salesLine` belum sampai saat render pertama | Rendah | Ikut payload Inertia awal, bukan permintaan terpisah — tidak ada jendela kosong |
| R5 | Halaman lain memakai `InvoicesPanel`/`CostingPanel` tanpa mengirim `salesLine` | Sedang | Ditelusuri saat implementasi; prop diberi nilai bawaan aman (`profitFromRevenue: false`, `totalComposition: 'per_unit'`) sehingga ketiadaannya tidak melempar galat |
| R6 | `baseline_total` (patokan) tidak cocok setelah komposisi berubah | Sedang | `lockBaseline()` menyimpan `total` apa adanya, tidak peduli asal-usulnya — tetap konsisten. Diverifikasi test. |

---

## 5. Penanganan invoice rental yang sudah ada

Dua fakta yang mengubah rencana awal (migrasi otomatis) — ditemukan saat review spec, sebelum kode ditulis:

1. **Tidak ada kehilangan diam-diam.** `syncProformaTotal()` hanya dipanggil dari `updateProforma`, `lockBaseline`, dan `approve` — bukan saat halaman dibuka. `unit_price` lama tetap utuh di database sampai sales menekan simpan. Yang terlihat lebih dulu adalah totalnya jatuh ke Rp0 **di layar**, sebelum tersimpan.
2. **Skrip harus menebak, sales tidak.** Migrasi otomatis hanya bisa membuat satu baris gelondongan berlabel karangan (mis. "dari harga sebelumnya") senilai total lama. Padahal rincian sebenarnya sudah diketahui sales — untuk `INV-2026-13-0002`: Avanza Rp800.000 (22 Jul) dan Innova Reborn Rp1.050.000 + biaya luar kota Rp200.000 (25 Jul). Label karangan itu juga akan **tercetak di PDF ke customer** bila invoice disetujui tanpa disunting.

**Keputusan: tidak ada migrasi otomatis.** Sebagai gantinya:

**a. Hitung dulu yang terdampak** — sebelum Fase 3 dirilis, jalankan query hitung (read-only):

```sql
SELECT i.id, i.number, i.unit_price, t.code
FROM invoices i
JOIN tours t ON t.id = i.tour_id
WHERE t.type = 'rental' AND i.approved_at IS NULL AND i.unit_price > 0;
```

**b. Bila jumlahnya sedikit (perkiraan: 1) — koreksi manual oleh sales.** Sales memasukkan ulang rinciannya sebagai baris bernominal, yang justru menghasilkan invoice lebih baik daripada hasil tebakan skrip. Daftar invoice dari langkah (a) diserahkan ke sales sebagai daftar kerja.

**c. Peringatan eksplisit di panel** — untuk invoice rental yang masih `unit_price > 0` sementara belum ada baris bernominal, panel menampilkan banner:

> Invoice ini masih memakai harga lama **Rp 2.050.000**. Masukkan rinciannya sebagai baris di bawah, lalu simpan. Selama belum diisi, total akan terbaca Rp 0.

Banner ini membuat keadaan peralihan mustahil terlewat, dan hilang sendiri begitu barisnya diisi. Ia juga menutup kasus yang tidak terpikirkan di langkah (a) — mis. invoice rental baru yang dibuat setelah query dijalankan tapi sebelum Fase 3 dirilis.

**Bila ternyata jumlahnya banyak** (di luar perkiraan), rencana ini ditinjau ulang dan migrasi berskrip dipertimbangkan kembali dengan protokol penuh [§7.4–7.5 dokumen lama](../../desain/pemisahan-invoice-per-jenis.md) (tulis kolom langsung, dilarang memanggil `syncProformaTotal()`, verifikasi nol selisih, uji di salinan data production, backup terverifikasi).

---

## 6. Pengujian

**Fase 1**
- PDF customer untuk tiap jenis mencetak `× N pax`
- Panel menampilkan `Harga / pax` untuk tiap jenis

**Fase 2**
- Unit: `TourRule::profitFromRevenue()` = `true`; enam rule lain = `false`
- Feature: payload `Tours/Edit` memuat `salesLine.profitFromRevenue` yang benar per jenis
- Karakterisasi: angka profit & margin dan isi PDF Rincian Profit **identik** sebelum-sesudah untuk jenis `tour` maupun non-`tour`
- Regresi: invoice yang sudah disetujui totalnya tidak berubah

**Fase 3**
- Unit: `TransportRule::totalComposition()` = `'line_items'`; enam lainnya `'per_unit'`
- Feature: invoice rental dengan dua baris bernominal → `total` = jumlah keduanya, `unit_price` diabaikan
- Feature: invoice non-rental tetap `unit_price × pax` + baris bernominal (perilaku lama)
- Feature: baris bernominal menyimpan dan mengembalikan `date`
- Feature: invoice rental yang masih `unit_price > 0` tanpa baris bernominal memunculkan banner §5c; banner hilang begitu ada baris bernominal
- Regresi: membuka halaman tour rental (tanpa menyimpan) **tidak** mengubah `total` maupun `unit_price` di database — menjamin dasar penurunan tingkat R1

---

## 7. Di luar lingkup

- **Memutuskan satuan** untuk rental/hotel/ticketing/jasa guide (D2) — keputusan bisnis, menyusul
- **Fase 3 dokumen lama** (`billing_quantities` bisa diedit sales, pengali lepas dari `tour.pax`) — pekerjaan tersendiri; dokumen ini tidak mengubah sumber pengali sama sekali
- **Memecah `InvoicesPanel.vue` jadi komponen per jenis** — tetap di luar lingkup sesuai [§6 dokumen lama](../../desain/pemisahan-invoice-per-jenis.md). Diukur ulang 29 Jul 2026: dari 1.347 baris, hanya ~10 yang bercabang per jenis (99,3% identik). Memecahnya menggandakan 1.337 baris tujuh kali dan justru memperburuk masalah §1.2 — percabangan aturan bertambah dari 3 tempat jadi 8.
- `HeaderPanel.vue` dan `QuotationPanel.vue` yang juga punya `isTour` — itu soal field mana yang tampil (urusan tampilan), wilayah Fase 5 dokumen lama, bukan aturan uang
- Modul Keuangan, ledger, Bill — tidak tersentuh
