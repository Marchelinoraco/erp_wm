# Desain: Master Karyawan, Kas Bon, dan Gajian Bulanan

> Status (31 Jul 2026): disetujui, siap direncanakan.
>
> Dikerjakan dalam **dua tahap pada dua branch terpisah**, tetapi **satu rilis** ke production. Tahap A (`feat/akun-non-laba-rugi`) adalah prasyarat teknis yang tidak mengubah satu angka pun; Tahap B (`feat/master-karyawan`) adalah fiturnya.

---

## 1. Latar

### 1.1 Kebutuhan yang diminta

Belum ada daftar karyawan di ERP. Data gaji hidup di luar sistem, dan pembayarannya dicatat sebagai transaksi kas biasa berkategori "Gaji Karyawan" tanpa keterangan siapa menerima berapa.

Yang diminta: master karyawan berisi seluruh nama beserta datanya, dipakai untuk pembayaran gaji bulanan, dengan penanganan **kas bon** — karyawan meminta sebagian gajinya sebelum tanggal gajian. Contoh yang diberikan: gaji 4 juta, kas bon 1 juta, yang dibayar saat gajian tinggal 3 juta.

### 1.2 Kenapa kas bon bukan sekadar pengurang angka

Cara pencatatan yang paling sering dipakai orang — kas bon 1 juta dicatat sebagai Beban Gaji, lalu gajian 3 juta dicatat sebagai Beban Gaji lagi — menghasilkan total 4 juta yang tampak benar. Tiga hal rusak diam-diam:

1. **Laba Rugi bergeser antar bulan.** Kas bon diambil 28 Juli, gajian 5 Agustus → beban Juli kelebihan 1 juta, beban Agustus kekurangan 1 juta. Laba kedua bulan salah.
2. **Tidak ada daftar siapa masih menyangkut kas bon berapa.** Informasi itu tidak tersimpan di mana pun.
3. **Pertanyaan "berapa total kas bon beredar sekarang?" tidak bisa dijawab sistem.**

Perlakuan yang benar: kas bon adalah **piutang** (aset), bukan beban. Karyawan berhutang ke perusahaan sampai dipotong gaji.

```
Kas bon 1 jt   D  Piutang Karyawan   1.000.000
               K  Kas                1.000.000     (belum ada beban)

Gajian         D  Beban Gaji         4.000.000     (beban penuh, bulan yang benar)
               K  Kas                3.000.000     (yang benar-benar keluar)
               K  Piutang Karyawan   1.000.000     (piutang lunas)
```

### 1.3 Kenapa sistem yang ada belum bisa menampungnya

Hasil pembacaan kode pada 31 Jul 2026:

| Kendala | Bukti |
|---|---|
| Kategori keuangan hanya kenal pendapatan dan beban — tidak ada sisi aset | `fin_categories.type` enum `('income','expense')` |
| Setiap transaksi wajib menyentuh kas; tidak ada baris jurnal non-kas | `fin_transactions.cash_account_id` NOT NULL |
| Buku Besar menentukan jenis akun dari **arah uang**, bukan dari jenis akunnya | `FinanceReportController::ledgerData()` — `$t->direction === 'in' ? 'pendapatan' : 'beban'` |
| Neraca menghitung laba ditahan dari `source = 'manual'` saja | `FinanceReportController::balanceSheetData()` — `manualIncome` / `manualExpense` |

Baris terakhir adalah jebakan yang paling berbahaya. `fin_transactions.source` adalah enum `('manual','invoice','bill')`. Bila gajian menulis dengan nilai `source` baru, **beban gaji hilang dari perhitungan laba ditahan dan Neraca berhenti balance** — tanpa galat, tanpa peringatan.

Kas bon melanggar dua asumsi dasar sistem sekaligus: ia akun aset, dan pelunasannya tidak menyentuh kas. Karena itu prasyaratnya dipisah jadi Tahap A.

---

## 2. Keputusan yang sudah dikunci

| # | Keputusan | Alasan |
|---|---|---|
| D1 | Karyawan disimpan di tabel `employees` **sendiri**, bukan menumpang `users` | Tidak semua yang digaji punya akun ERP (driver, guide lepas, staf kantor). Memaksa email+password untuk orang yang tidak pernah membuka ERP itu mengada-ada |
| D2 | `employees.user_id` **nullable** — tautan opsional ke akun ERP | Yang kebetulan punya akun tidak tercatat dobel dengan data berbeda |
| D3 | Seluruh fitur dibatasi **role `admin`** | Diminta pemilik produk. Rute ditutup middleware `role:admin`, menunya juga disembunyikan |
| D4 | Gaji = gaji pokok **+ komponen tetap tersimpan** per orang | Tunjangan makan/transport/jabatan selalu sama tiap bulan; mengetik ulang tiap bulan itu pemborosan dan sumber salah ketik |
| D5 | Gajian diproses **sekali per bulan untuk semua karyawan** (batch), bukan per orang | Satu layar menjawab "sudah semua yang digaji bulan ini?" — pola per-orang gampang ada yang kelewat |
| D6 | Kas bon dipotong **lunas sekaligus** di gajian berikutnya, nominalnya **boleh diturunkan** admin; sisa terbawa ke bulan depan | Menangani kasus normal tanpa setelan apa pun, sekaligus menampung "bulan ini potong separuh dulu" tanpa perlu fitur cicilan terpisah |
| D7 | Kas bon dibukukan sebagai **Piutang Karyawan di Neraca**, bukan langsung Beban Gaji | Dipilih sadar oleh pemilik produk setelah ongkosnya dijelaskan (menyentuh Buku Besar, Neraca, Laba Rugi, Arus Kas, dan Rekap — semuanya baru live). Selalu benar termasuk lintas bulan |
| D8 | Prasyarat akuntansinya dipisah jadi **Tahap A** pada branch sendiri | Tahap A punya sifat yang tidak akan didapat lagi bila tercampur: bila benar, **tidak ada satu angka pun yang berubah**. Begitu tercampur Tahap B, setiap selisih jadi ambigu — salah di mesin akuntansi atau di logika gaji? |
| D9 | Dua branch, dua kali masuk `dev`, **satu** PR `dev` → `main` | Dapat pemisahannya tanpa membayar dua kali ongkos deploy, dan production tidak pernah menerima rilis yang manfaatnya nol |
| D10 | Sisa kas bon **dihitung**, tidak disimpan sebagai kolom | Kolom tersimpan bisa melenceng bila ada satu jalur update yang lupa memperbaruinya, dan tidak ada yang memberi tahu. Ini juga yang membuat "Batalkan gajian" memulihkan sisa kas bon tanpa perbaikan manual |
| D11 | `payroll_items` menyimpan **salinan** nama, jabatan, gaji pokok, dan komponen | Bila gaji naik bulan depan, slip gaji bulan lalu tidak boleh ikut berubah |
| D12 | Berkas gajian berstatus `paid` **tidak bisa disunting**, hanya dibatalkan lalu dibuat ulang | Menyunting yang sudah membentuk jurnal adalah cara tercepat membuat slip gaji dan pembukuan berbeda isi |
| D13 | **Tidak ada** prorata masuk/keluar tengah bulan, **tidak ada** BPJS/PPh21/lembur otomatis | Belum tentu dibutuhkan. Aturan pajak berubah tiap tahun dan menuntut perawatan terus-menerus. Penyesuaian ditangani lewat baris potongan manual |

---

## 3. Tahap A — Akun non-laba-rugi

Branch: `feat/akun-non-laba-rugi`

### 3.1 Temuan yang menyederhanakan pekerjaan

Dugaan awal: entri gajian butuh jurnal **3 baris**, sedangkan `FinTransaction::journalLines()` dipatok 2 baris, jadi mesin jurnalnya harus dirombak.

Dugaan itu keliru. Satu entri 3 baris dapat dipecah menjadi **dua entri 2 baris** yang masing-masing sah, dan `journalLines()` tidak perlu dirombak sama sekali. Yang dibutuhkan hanya dua hal kecil.

### 3.2 Perubahan skema

**`fin_categories.type`** — tambah nilai `'asset'` ke enum, jadi `('income','expense','asset')`.

Kategori bertipe `asset` tersedia untuk **kedua** arah transaksi: `out` menaikkan saldo aset (memberi kas bon), `in` menurunkannya.

**`fin_transactions`** — dua perubahan:

| Kolom | Perubahan |
|---|---|
| `cash_account_id` | jadi **nullable** |
| `contra_fin_category_id` | kolom **baru**, nullable, FK ke `fin_categories` |
| `source` | tambah nilai `'advance'` dan `'payroll'` ke enum |

Aturan: **tepat satu** dari `cash_account_id` dan `contra_fin_category_id` terisi. Bila yang terisi adalah kategori lawan, transaksi itu tidak menyentuh kas.

### 3.3 `journalLines()`

Perubahan satu baris: nama akun lawan diambil dari akun kas **atau** kategori lawan.

```php
$lawan = $this->cashAccount?->name ?? $this->contraCategory?->name ?? 'Kas';
```

Sisanya tidak berubah. Arah debit/kredit tetap ditentukan `direction` seperti sekarang.

### 3.4 Enam tempat di laporan yang berubah

Setiap perubahan disertai alasan kenapa hasilnya **identik dengan hari ini**.

| # | Tempat | Perubahan | Jaminan invariansi |
|---|---|---|---|
| 1 | `ledgerData()` — Buku Besar | Kelompok akun dibaca dari `category->type`, bukan dari `direction` | Belum ada kategori `asset`; pemetaan `in`→income→pendapatan dan `out`→expense→beban sudah sejalan dengan perilaku sekarang |
| 2 | `balanceSheetData()` — Neraca | `where('source','manual')` → `whereNotIn('source',['invoice','bill'])` | Enum `source` hari ini hanya 3 nilai, jadi kedua filter **setara secara logika**. Ini yang membuat `source` baru aman ditambahkan |
| 3 | `balanceSheetData()` — Neraca | Kategori `asset` dikeluarkan dari laba ditahan | Belum ada kategori `asset` → tidak ada yang dikeluarkan |
| 4 | `balanceSheetData()` — Neraca | Baris aset baru dari saldo kategori bertipe `asset` | Belum ada → saldonya 0, baris tidak ditampilkan |
| 5 | `incomeStatementData()` — Laba Rugi | Kategori `asset` dikeluarkan dari pendapatan/beban | Sama, belum ada |
| 6 | `cashFlow()` + `recapData()` | Tambah `whereNotNull('cash_account_id')` | Semua baris hari ini punya akun kas |

Catatan bentuk kode: lima laporan punya method data tersendiri (`journalData`, `ledgerData`, `recapData`, `accountBalancesData`, `balanceSheetData`, `incomeStatementData`), tetapi **Arus Kas merakit datanya langsung di dalam `cashFlow()`** yang publik. Sebagai bagian Tahap A, isinya dipindah ke `cashFlowData()` agar keenam laporan berbentuk sama — tanpa itu, `finance:snapshot` (§3.6) tidak punya cara memanggil Arus Kas tanpa melewati HTTP. Pemindahan murni, tidak mengubah perilaku.

**Nomor 1 adalah satu-satunya yang tidak dijamin oleh logika, hanya oleh data.** Bila di database production ada transaksi `in` berkategori `expense` (atau sebaliknya), angkanya akan bergeser. Justru itu yang ingin ditemukan — dan berkas patokan dari salinan database production di dev-erp akan menangkapnya sebelum apa pun naik.

### 3.5 Layar Transaksi

`resources/js/Pages/Finance/Transactions.vue` menyaring kategori dengan pencocokan persis `c.type === 'income'` / `'expense'`. Kategori `asset` otomatis tidak muncul — aman sebagai perilaku bawaan, tetapi perlu ditangani:

- Dropdown untuk uang masuk: pendapatan **+ aset**
- Dropdown untuk uang keluar: beban **+ aset**
- Lencana kategori mendapat warna ketiga, supaya aset tidak terbaca sebagai "Keluar"

### 3.6 Perintah `finance:snapshot`

Perintah artisan yang menuliskan enam laporan (Neraca, Laba Rugi, Buku Besar, Arus Kas, Rekap, Saldo Akun) untuk satu tahun ke satu berkas JSON.

Method datanya `private`, jadi perintah ini memanggilnya lewat `ReflectionMethod` — teknik yang sudah dipakai di repo ini untuk menguji `InvoiceController::build()`, bukan cara kerja baru. Alternatifnya menjadikan keenamnya `public`, tetapi method publik pada controller Laravel secara konvensi berarti aksi rute; refleksi menjaga batas itu tetap jelas.

Dijalankan di dev-erp **sebelum** kode disentuh, lalu **sesudah**, lalu dibandingkan. Karena dev-erp memakai salinan database production, ini bukan data mainan. Selisih satu rupiah pun berarti berhenti dan periksa.

Perintah ini bukan sekali pakai — setiap perubahan laporan keuangan di kemudian hari mewarisi jaring pengaman yang sama.

---

## 4. Tahap B — Master Karyawan

Branch: `feat/master-karyawan` (dibuat setelah Tahap A masuk `dev` dan gerbang invariansinya lolos)

### 4.1 Tabel

**`employees`**

| Kolom | Keterangan |
|---|---|
| `name`, `position` | nama dan jabatan |
| `phone`, `email` | nullable |
| `user_id` | nullable, FK `users` — tautan opsional (D2) |
| `base_salary` | decimal(15,2) |
| `join_date` | nullable |
| `bank_name`, `bank_account_number`, `bank_account_holder` | nullable, untuk transfer |
| `is_active` | default true |
| `notes` | nullable |
| | timestamps + softDeletes |

**`employee_components`** — komponen tetap per orang: `employee_id`, `name`, `type` (`tunjangan`/`potongan`), `amount`, `is_active`, `sort_order`.

**`employee_advances`** — kas bon: `employee_id`, `date`, `amount`, `note`, `fin_transaction_id` (jejak ke jurnal), `created_by`, timestamps, softDeletes.

Tidak ada kolom "sisa" (D10). Sisa = `amount` dikurangi total baris `payroll_item_lines` bertipe `kas_bon` yang merujuk kas bon ini.

**`payrolls`** — `period` char(7) `'2026-07'` **unik**, `status` (`draft`/`paid`), `paid_date` nullable, `cash_account_id` nullable, `created_by`, timestamps.

**`payroll_items`** — satu baris per karyawan: `payroll_id`, `employee_id`, salinan `employee_name`, `position`, `base_salary`, dan `net_amount`.

`net_amount` **disimpan**, dihitung saat berkas disimpan: gaji pokok + tunjangan − potongan − kas bon. Berbeda dengan sisa kas bon (D10) yang dihitung, di sini justru penyimpanan yang benar — angka itu adalah yang tercetak di slip gaji dan tidak boleh berubah kalau master diedit (D11).

**`payroll_item_lines`** — rincian: `payroll_item_id`, `kind` (`tunjangan`/`potongan`/`kas_bon`), `label`, `amount` (selalu positif; tandanya ditentukan `kind`), `employee_advance_id` nullable (hanya untuk `kind = kas_bon`).

### 4.2 Jurnal yang dihasilkan

| Kejadian | Transaksi | Jumlah baris |
|---|---|---|
| Beri kas bon | `out` · kategori **Piutang Karyawan** · dari akun kas · `source='advance'` | 1 per kas bon |
| Bayar gajian | `out` · kategori **Gaji Karyawan** · dari akun kas · `source='payroll'` | 1 per periode |
| Pelunasan kas bon saat gajian | `out` · kategori **Gaji Karyawan** · lawan **Piutang Karyawan** · non-kas · `source='payroll'` | 1 per periode, hanya bila ada kas bon |

Gajian menghasilkan **dua** transaksi untuk seluruh periode, bukan dua per karyawan, supaya Jurnal tidak banjir puluhan baris tiap bulan. Rincian per orang tetap utuh di `payroll_items`.

Kategori **Piutang Karyawan** dibuat lewat seeder dengan `type = 'asset'` dan `is_system = true`, supaya tidak bisa dihapus dari layar Transaksi.

**Contoh — Budi, gaji pokok 4.000.000 + tunjangan 800.000, kas bon 1.000.000:**

| Tgl | Debit | Kredit | Kas? |
|---|---|---|---|
| 10 Jul | Piutang Karyawan 1.000.000 | Kas 1.000.000 | ya |
| 30 Jul | Beban Gaji 3.800.000 | Kas 3.800.000 | ya |
| 30 Jul | Beban Gaji 1.000.000 | Piutang Karyawan 1.000.000 | tidak |

Beban Gaji Juli **4.800.000** (penuh), kas keluar **4.800.000**, Piutang Karyawan kembali **0**.

Bila kas bon diambil 28 Juli tetapi gajian 5 Agustus: Neraca akhir Juli menunjukkan Kas −1jt dan Piutang Karyawan +1jt — aset bersih tidak berubah, laba Juli tidak tersentuh.

### 4.3 Layar dan hak akses

| Menu | Isi |
|---|---|
| Data Master → **Karyawan** | Daftar + tambah/ubah, termasuk komponen tetap. Sisa kas bon tiap orang tampil di sini |
| Keuangan → Gaji → **Kas Bon** | Daftar kas bon, beri kas bon baru, sisa per orang |
| Keuangan → Gaji → **Gajian** | Daftar periode; buka periode → draft → periksa → Bayar |

Sub-judul **Gaji** baru di kelompok Keuangan, menyusul Laporan dan Akun & Aset. Ketiganya dibatasi `role:admin` — middleware `role:` sudah tersedia di repo ini, tidak ada mekanisme baru. Menu disembunyikan untuk role lain, bukan hanya rutenya ditutup.

### 4.4 Alur draft → bayar

Buka periode → sistem menarik semua karyawan aktif beserta gaji pokok, komponen tetap, dan **seluruh kas bon yang belum lunas** (potongan terisi penuh, boleh diturunkan) → admin periksa dan sesuaikan → **Bayar**.

Draft **dihitung ulang setiap kali dibuka**, bukan menyimpan hasil hitungan, sehingga kas bon yang baru masuk otomatis ikut. Angka baru dibekukan saat Bayar.

Saat Bayar: status jadi `paid`, transaksi jurnal dibuat, berkas dikunci. Seluruhnya dalam **satu transaksi database**.

---

## 5. Aturan yang dijaga sistem

| Aturan | Alasan |
|---|---|
| Potongan kas bon otomatis = yang lebih kecil antara sisa kas bon dan gaji bersih | Gaji bersih tidak boleh minus. Kas bon 5jt atas gaji 4jt → dipotong 4jt, sisa 1jt terbawa |
| Gaji bersih boleh **nol**, tidak boleh **minus** | Nol itu sah — seluruh gaji sudah diambil di muka |
| `payrolls.period` unik | Mencegah dua admin membuat gajian Juli dua kali |
| Berkas `paid` tidak bisa disunting (D12) | Slip gaji dan pembukuan harus selalu sama isi |
| Kas bon yang sudah pernah dipotong tidak bisa dihapus | Menghapusnya membuat jurnal menggantung tanpa lawan |
| Karyawan dengan riwayat gajian tidak bisa dihapus, hanya dinonaktifkan | Riwayat gaji harus tetap terbaca |
| Bayar + pembuatan jurnal dalam satu transaksi database | Tidak boleh ada berkas `paid` tanpa jurnal, atau sebaliknya |

---

## 6. Yang bisa salah, dan penanganannya

**Kas bon diberikan setelah draft dibuat.** Draft menghitung ulang tiap dibuka (§4.4), jadi kas bon baru otomatis ikut.

**Berkas gajian salah dan sudah dibayar.** Disediakan **Batalkan**: status kembali `draft`, jurnalnya dihapus. Karena sisa kas bon dihitung dan bukan disimpan (D10), sisa kas bon pulih sendiri tanpa perbaikan manual.

**Kas bon dihapus.** Transaksi jurnalnya ikut terhapus. Hanya boleh untuk kas bon yang belum pernah dipotong.

**Karyawan masuk/keluar tengah bulan.** Tidak ada prorata (D13). Draft menarik gaji penuh; admin menyesuaikan lewat baris potongan.

**Saldo kas tidak cukup.** Tidak diblokir. Sistem ini tidak memblokir di tempat lain (bayar bill, bayar biaya); memblokir khusus di gajian jadi kejutan yang tidak konsisten.

---

## 7. Pengujian

### 7.1 Tahap A — pembuktian "tidak ada yang berubah"

1. Jalankan `finance:snapshot` di dev-erp **sebelum** kode disentuh → berkas patokan
2. Kerjakan Tahap A
3. Jalankan lagi → bandingkan. **Selisih satu rupiah pun berarti berhenti dan periksa**

Ditambah uji otomatis:

- `journalLines()` dengan kategori lawan menghasilkan dua baris yang benar
- Aturan **tepat satu** antara `cash_account_id` dan `contra_fin_category_id` ditegakkan

### 7.2 Tahap B — skenario

| Skenario | Yang dipastikan |
|---|---|
| Gaji 4jt, kas bon 1jt, satu bulan | Dibayar 3jt · Beban Gaji 4jt · Piutang Karyawan 0 |
| Kas bon 28 Jul, gajian 5 Ags | Beban Juli **0** · Piutang akhir Juli 1jt · beban Agustus penuh |
| Kas bon 1jt dipotong 500rb | Sisa 500rb muncul sendiri di draft bulan berikutnya |
| Kas bon 5jt atas gaji 4jt | Dipotong 4jt · gaji bersih 0 · sisa 1jt terbawa |
| Gaji master diubah setelah gajian dibayar | Berkas gajian lama tidak berubah (D11) |
| Batalkan gajian yang sudah dibayar | Jurnal hilang · sisa kas bon pulih |

**Di setiap skenario diperiksa hal yang sama: Neraca tetap balance.** Kolom `balanced` sudah tersedia di `balanceSheetData()` dan tinggal dipakai. Ini pemeriksaan paling berharga di seluruh rencana — pembukuan double-entry yang salah hampir selalu ketahuan dari neraca yang tidak seimbang, dan satu baris uji itu menangkap seluruh kelas kesalahan sekaligus.

### 7.3 Setiap uji dibuktikan bisa gagal

Kode sengaja dirusak sebentar untuk memastikan ujinya benar-benar menguji sesuatu. Uji yang selalu hijau apa pun keadaannya lebih berbahaya daripada tidak ada uji sama sekali, karena memberi rasa aman palsu.

Pelajaran ini datang dari pekerjaan 30 Jul 2026: 185 uji hijau sempat menyembunyikan bug PDF invoice yang nyata, dan baru ketahuan setelah PDF-nya benar-benar dibuka dan dibaca.

---

## 8. Rilis

| Langkah | Keterangan |
|---|---|
| 1 | `feat/akun-non-laba-rugi` → `dev` |
| 2 | **Gerbang**: `finance:snapshot` di dev-erp, seluruh angka identik |
| 3 | `feat/master-karyawan` → `dev` |
| 4 | **Gerbang**: skenario §7.2 di dev-erp |
| 5 | Satu PR `dev` → `main` |
| 6 | Di server production: `git pull`, `php artisan migrate`, `npm ci && npm run build` |

Rilis ini **ada migrasinya** (tidak seperti rilis sidebar sebelumnya) dan **ada seeder** untuk kategori Piutang Karyawan.

---

## 9. Yang sengaja tidak dikerjakan

| Tidak dikerjakan | Alasan |
|---|---|
| BPJS, PPh21, lembur otomatis | Aturan pajak berubah tiap tahun dan menuntut perawatan terus-menerus. Belum tentu dibutuhkan (D13) |
| Prorata masuk/keluar tengah bulan | Ditangani baris potongan manual. Bila ternyata sering, penambahannya kecil di kemudian hari |
| Cicilan kas bon terjadwal | D6 sudah menampung pemotongan sebagian tanpa tabel jadwal terpisah |
| Slip gaji PDF | Belum diminta. Datanya sudah lengkap di `payroll_items`, jadi bisa menyusul kapan saja |
| Portal karyawan melihat slipnya sendiri | Seluruh fitur admin-only (D3) |
| Nomor induk karyawan | Nama sudah cukup membedakan pada skala ini |
