# Modul: Rekening (Bank Account & Cash Account)

> Bagian dari sistem ERP Welcome Manado. Rujuk [pola-ui-desain.md](../referensi/pola-ui-desain.md) untuk pola UI/warna bersama. Mencerminkan kode per 17 Jul 2026.

## Ringkasan

Dua model yang terlihat serupa tapi punya tujuan berbeda:

- **`BankAccount`** — rekening bank perusahaan yang **tampil di PDF invoice** yang dikirim ke customer (nomor rekening & atas nama untuk transfer pembayaran). Dikelola di halaman Data Master khusus, **`Finance/BankAccounts.vue`**.
- **`CashAccount`** — akun kas/buku besar internal, dipilih dari dropdown **"Akun Kas"** saat mencatat pembayaran invoice (AR) atau bill (AP) — menentukan ke rekening/kas mana uang benar-benar masuk/keluar untuk pencatatan akuntansi. Dikelola di modul Keuangan → Buku Kas (`FinanceLedgerController`, di luar cakupan dokumen ini).

Halaman Data Master "Rekening" yang dibahas di sini hanya mengelola **`BankAccount`** — `CashAccount` disinggung karena keduanya saling terhubung otomatis (lihat §Alur Bisnis).

## Alur Bisnis

### 1. BankAccount otomatis membuat CashAccount pasangannya
Saat rekening bank baru disimpan (`BankAccountController::store`), controller **langsung** membuat (atau memakai yang sudah ada) `CashAccount` pasangannya di baris kode yang sama:

```php
CashAccount::firstOrCreate(
    ['name' => $bankAccount->bank, 'type' => 'bank'],
    ['opening_balance' => 0, 'is_active' => true, 'sort_order' => CashAccount::max('sort_order') + 1]
);
```

Ini **bukan** observer/event Eloquent — murni dipanggil manual di dalam method `store()`. Efeknya: begitu rekening bank baru ditambahkan lewat UI, ia langsung bisa dipilih di dropdown "Akun Kas" tanpa langkah tambahan di Keuangan.

**Yang perlu diperhatikan dari pola `firstOrCreate` ini**:
- Pencocokan pakai **`name` = nilai kolom `bank`** (nama bank, bukan nomor rekening) + `type = 'bank'`. Kalau dua `BankAccount` berbeda dibuat dengan nilai `bank` yang sama persis (mis. dua rekening BCA dengan nomor berbeda), keduanya akan **berbagi satu `CashAccount`** yang sama ("BCA") — bukan dua akun kas terpisah.
- `update()` dan `destroy()` di `BankAccountController` **tidak** menyentuh `CashAccount` sama sekali — pasangan `CashAccount` hanya pernah dibuat sekali, tepat saat rekening bank pertama kali disimpan. Mengubah nama bank di rekening yang sudah ada tidak mengganti nama `CashAccount` yang sudah dibuat sebelumnya (jadi bisa saling tidak sinkron), dan menghapus `BankAccount` **tidak** menghapus `CashAccount` pasangannya (akun kas tetap ada, hanya rekening banknya yang hilang dari daftar tampilan invoice).

### 2. CashAccount juga bisa dibuat lepas dari BankAccount
`CashAccount` juga punya jalur pembuatan sendiri di modul Keuangan (`FinanceLedgerController::storeCashAccount`, route `finance.cash-accounts.store`) — dipakai untuk akun kas murni (`type = 'cash'`, mis. "Kas Tunai Kantor") yang tidak punya rekening bank sama sekali. Jadi tidak semua `CashAccount` berasal dari `BankAccount`; keduanya independen di level data, hanya disatukan otomatis untuk kasus rekening bank.

### 3. Dipakai di mana
- **`BankAccount`** (`scopeActive`, urut `sort_order`) — ditampilkan di PDF invoice (jumlah rekening yang ditampilkan diatur per-invoice lewat `bank_account_ids` JSON, lihat §3 di [01-penjualan-tour.md](01-penjualan-tour.md)) dan sebagai daftar yang dikelola di halaman `Finance/BankAccounts.vue`.
- **`CashAccount`** (`scopeActive`) — dropdown "Akun Kas" di panel pembayaran invoice (`InvoicesPanel.vue`, field `cash_account_id` per pembayaran) dan pembayaran bill di Keuangan; juga dipakai di laporan Keuangan (Buku Kas, Neraca Saldo, Rekap) sebagai baris per akun.

### 4. Aturan hapus
Route `bank-accounts.destroy` dipisah ke grup middleware sendiri: `role:admin,accountant` — **sales tidak boleh menghapus rekening**, meski sales boleh menambah dan mengedit (`bank-accounts.store`/`update` ada di grup `role:admin,sales,accountant`). Di sisi UI, `BankAccounts.vue` menyembunyikan tombol "Hapus" kalau `canSeeFinance` (role `admin`/`accountant`) bernilai false — konsisten dengan pembatasan route. Alasan bisnisnya konsisten dengan komentar di `routes/web.php`: "Rekening pembayaran (tampil di invoice) — sales & akuntan bisa tambah/edit" tapi hapus dibatasi lebih ketat karena berdampak ke data historis invoice/keuangan.

### 5. Validasi & seed data awal
`BankAccountController::validateData()` (dipakai bersama oleh `store` dan `update`) mewajibkan `bank` (maks 100), `account_number` (maks 50), `holder_name` (maks 150), dan `is_active` boolean opsional. Saat migration `create_bank_accounts_table` pertama kali dijalankan, tabel di-seed otomatis dari `config('quotation.bank')` — daftar rekening default yang tadinya hardcode di config file quotation, dipindah jadi data terkelola supaya invoice baru tidak tampil kosong di instalasi baru.

## Model Data

| Tabel | Kolom | Catatan |
|---|---|---|
| `bank_accounts` | `bank`, `account_number`, `holder_name`, `is_active`, `sort_order` | Di-seed awal dari `config('quotation.bank')` saat migration jalan (supaya invoice tidak kosong di instalasi baru); `scopeActive()` filter `is_active` + urut `sort_order`, `id` |
| `cash_accounts` | `name`, `type` (enum `cash`/`bank`), `opening_balance`, `is_active`, `sort_order` | `hasMany FinTransaction`; `scopeActive()` sama pola dengan `BankAccount` |
| `invoice_payments`, `bill_payments` | + `cash_account_id` (nullable, `nullOnDelete`) | Ditambahkan lewat migration `2026_07_15_000000_add_cash_account_id_to_payments` — menggantikan tebakan otomatis lama (`LedgerSync::accountForMethod()` yang cuma ambil akun cash/bank pertama, salah kalau akun bank >1). Nullable supaya baris lama tetap valid; baris tanpa `cash_account_id` fallback ke tebakan lama. |

Kedua model (`BankAccount`, `CashAccount`) pakai `protected $guarded = ['id']` — bukan `$fillable` eksplisit seperti `ProductPrice`.

## Route & Controller

| Route | Controller | Role |
|---|---|---|
| `bank-accounts.index/store/update` | `BankAccountController` | admin, sales, accountant |
| `bank-accounts.destroy` | `BankAccountController` | **admin, accountant** (sales dikecualikan) |
| `finance.cash-accounts.store/update/destroy` | `FinanceLedgerController` | admin, accountant (grup route Keuangan) |

Semua route rekening berada di prefix `/finance/bank-accounts` meski halamannya dianggap Data Master (bukan Keuangan) — terlihat dari link "kembali" di UI yang mengarah ke `finance.index` untuk role Keuangan, atau `dashboard` untuk role lain (lihat §Halaman).

## Halaman & Komponen (UI)

- **`Finance/BankAccounts.vue`** — satu halaman list sederhana (bukan tabel, tapi `divide-y` list card), tiap baris menampilkan nama bank + badge status Aktif/Nonaktif, nomor rekening besar (`font-mono text-lg font-bold`), dan "a.n. {holder_name}". Aksi per baris: **Nonaktifkan/Aktifkan** (toggle cepat lewat `router.patch` langsung tanpa dialog), **Edit** (buka `Dialog`), dan **Hapus** (hanya render tombolnya kalau `canSeeFinance`).
- Tombol "+ Rekening" membuka `Dialog` yang sama dipakai untuk tambah maupun edit (`editing` ref menentukan mode) — field `bank`, `account_number`, `holder_name`, checkbox `is_active`.
- Link navigasi kembali di header **kondisional per role**: `canSeeFinance` (admin/accountant) diarahkan ke `finance.index`, role lain (sales) diarahkan ke `dashboard` — karena sales bisa mengelola rekening tapi tidak berhak melihat halaman ringkasan Keuangan.
- Caption di atas daftar menjelaskan efek langsung ke customer: "Rekening **aktif** akan tampil di PDF invoice yang dikirim ke customer" — hanya rekening dengan `is_active = true` yang muncul di PDF (lewat `scopeActive`).
- Tombol **Nonaktifkan/Aktifkan** mengirim `router.patch` langsung dengan seluruh field lama plus `is_active` yang dibalik (`!acc.is_active`) — bukan endpoint toggle terpisah, memakai route `bank-accounts.update` yang sama dengan form edit.
- Dialog tambah/edit dipakai identik untuk kedua mode (`editing.value` menentukan apakah `submit()` memanggil `.post` atau `.patch`), konsisten dengan pola "hindari dua Dialog terbuka bersamaan" dari fondasi desain — halaman ini hanya punya satu Dialog sehingga tidak berisiko soal itu.

## Yang Perlu Diperhatikan

- **CashAccount pasangan tidak otomatis diberi label yang jelas** — namanya persis sama dengan kolom `bank` (mis. "Bank BCA"), bukan gabungan bank+nomor rekening, jadi kalau ada beberapa rekening dengan nama bank sama, dropdown "Akun Kas" tidak bisa membedakan keduanya (lihat §1).
- **Update dan delete `BankAccount` tidak menyinkronkan `CashAccount`** — berpotensi menyisakan `CashAccount` "yatim" (nama bank yang sudah tidak ada di daftar rekening) atau nama yang tidak lagi cocok setelah rekening diedit. Tidak ada mekanisme cleanup otomatis di kode saat ini.
- **Hapus rekening tidak mengecek pemakaian** — `BankAccountController::destroy` langsung `delete()` tanpa memeriksa apakah `id`-nya masih tercantum di `invoices.bank_account_ids` (JSON) milik invoice lama; invoice yang sudah dibuat/di-PDF-kan sebelumnya tidak berubah (datanya sudah tercetak), tapi referensi ke rekening yang terhapus tidak divalidasi ulang.
