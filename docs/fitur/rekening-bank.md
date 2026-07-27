# Rekening Bank

> **Status:** ✅ Berjalan · **Peran:** admin, sales, accountant · **Sejak:** Jun 2026
> **Terkait:** [invoice.md](invoice.md), [keuangan-ar-ap.md](keuangan-ar-ap.md), [keuangan-pembukuan.md](keuangan-pembukuan.md)

## 1. Ringkasan

Mengelola `BankAccount` — rekening bank perusahaan yang tampil di PDF invoice untuk pembayaran customer — yang otomatis membuat pasangan `CashAccount` (akun kas pembukuan internal) saat rekening baru disimpan. Sales & akuntan bisa menambah/mengedit, tapi hapus dibatasi admin & akuntan karena berdampak ke data historis invoice/keuangan. Dipakai oleh admin, sales, dan accountant.

## 2. Cara kerja (as-built)

### Dua model yang mirip tapi berbeda tujuan

- **`BankAccount`** — rekening bank perusahaan yang **tampil di PDF invoice** yang dikirim ke customer (nomor rekening & atas nama untuk transfer). Dikelola di halaman Data Master `Finance/BankAccounts.vue`.
- **`CashAccount`** — akun kas/buku besar internal, dipilih dari dropdown **"Akun Kas"** saat mencatat pembayaran invoice (AR) atau bill (AP) — menentukan ke rekening/kas mana uang benar-benar masuk/keluar untuk pencatatan akuntansi. Dikelola juga lepas di modul Keuangan → Buku Kas (`FinanceLedgerController`), di luar cakupan halaman Data Master ini.

### `BankAccount` otomatis membuat `CashAccount` pasangannya

Saat rekening bank baru disimpan, `BankAccountController::store` langsung memanggil (bukan lewat observer/event Eloquent, murni kode manual di method yang sama):

```php
CashAccount::firstOrCreate(
    ['name' => $bankAccount->bank, 'type' => 'bank'],
    ['opening_balance' => 0, 'is_active' => true, 'sort_order' => CashAccount::max('sort_order') + 1]
);
```

Efeknya: begitu rekening bank baru ditambahkan lewat UI, ia langsung bisa dipilih di dropdown "Akun Kas" tanpa langkah tambahan. Pencocokan pakai **`name` = nilai kolom `bank`** (nama bank) + `type = 'bank'` — dua `BankAccount` dengan nilai `bank` yang sama persis (mis. dua rekening BCA dengan nomor berbeda) akan **berbagi satu `CashAccount`**, bukan dua akun kas terpisah. `update()`/`destroy()` di `BankAccountController` **tidak** menyentuh `CashAccount` — pasangan hanya dibuat sekali saat rekening pertama kali disimpan; mengubah nama bank di rekening yang sudah ada tidak mengganti nama `CashAccount` yang sudah dibuat, dan menghapus `BankAccount` **tidak** menghapus `CashAccount` pasangannya.

`CashAccount` juga punya jalur pembuatan sendiri di modul Keuangan (`FinanceLedgerController::storeCashAccount`) — dipakai untuk akun kas murni (`type = 'cash'`, mis. "Kas Tunai Kantor") tanpa rekening bank. Jadi tidak semua `CashAccount` berasal dari `BankAccount`.

### Dipakai di mana

- **`BankAccount`** (`scopeActive`, urut `sort_order`) — ditampilkan di PDF invoice; jumlah rekening yang ditampilkan diatur per-invoice lewat kolom `invoices.bank_account_ids` (JSON, cast `array`) — kosong berarti semua rekening aktif ditampilkan (default lama). Lihat [invoice.md](invoice.md).
- **`CashAccount`** (`scopeActive`) — dropdown "Akun Kas" di panel pembayaran invoice (`InvoicesPanel.vue`, field `cash_account_id` per pembayaran) dan pembayaran bill di Keuangan; juga jadi baris per akun di laporan Keuangan (Buku Kas, Neraca Saldo, Rekap). Lihat [keuangan-ar-ap.md](keuangan-ar-ap.md) dan [keuangan-pembukuan.md](keuangan-pembukuan.md).

### Aturan hapus — dibatasi lebih ketat dari tambah/edit

Route `bank-accounts.store`/`update` ada di grup `role:admin,sales,accountant`, tapi `bank-accounts.destroy` dipisah ke grup middleware sendiri `role:admin,accountant` — **sales tidak boleh menghapus rekening**, meski boleh menambah dan mengedit. Di sisi UI, `BankAccounts.vue` menyembunyikan tombol "Hapus" kalau `canSeeFinance` (role `admin`/`accountant`) bernilai `false`, konsisten dengan pembatasan route. Alasannya: hapus berdampak ke data historis invoice/keuangan.

### Validasi & seed data awal

`BankAccountController::validateData()` (dipakai bersama `store`/`update`) mewajibkan `bank` (maks 100), `account_number` (maks 50), `holder_name` (maks 150), `is_active` boolean opsional. Saat migration `create_bank_accounts_table` pertama kali dijalankan, tabel di-seed otomatis dari `config('quotation.bank')` — daftar rekening default yang tadinya hardcode di config, dipindah jadi data terkelola supaya invoice baru tidak tampil kosong di instalasi baru.

### Model data & route

| Tabel | Kolom | Catatan |
|---|---|---|
| `bank_accounts` | `bank`, `account_number`, `holder_name`, `is_active`, `sort_order` | `scopeActive()` filter `is_active` + urut `sort_order`, `id` |
| `cash_accounts` | `name`, `type` (`cash`/`bank`), `opening_balance`, `is_active`, `sort_order` | `hasMany FinTransaction`; `scopeActive()` sama pola |

Kedua model pakai `protected $guarded = ['id']` (bukan `$fillable` eksplisit). `invoice_payments`/`bill_payments` punya kolom `cash_account_id` (nullable, `nullOnDelete`) sejak migration `2026_07_15_000000_add_cash_account_id_to_payments` — menggantikan tebakan otomatis lama `LedgerSync::accountForMethod()` (yang cuma ambil akun cash/bank pertama, salah kalau akun bank >1); baris lama tanpa `cash_account_id` fallback ke tebakan lama.

Semua route rekening (`bank-accounts.*`) berada di prefix `/finance/bank-accounts` meski halamannya Data Master (bukan Keuangan) — terlihat dari link "kembali" di UI: `finance.index` untuk role Keuangan (`canSeeFinance`), `dashboard` untuk role lain (sales).

## 3. Keterkaitan

- **Invoice** ([invoice.md](invoice.md)) — `bank_account_ids` dipilih per invoice untuk menentukan rekening yang tampil di PDF.
- **Keuangan — AR/AP** ([keuangan-ar-ap.md](keuangan-ar-ap.md)) — `cash_account_id` dipilih di tiap pembayaran invoice/bill.
- **Keuangan — Pembukuan** ([keuangan-pembukuan.md](keuangan-pembukuan.md)) — `CashAccount` jadi basis laporan Buku Kas, Neraca Saldo, Rekap.

## 4. Batasan & jebakan ⚠️

- **`CashAccount` pasangan tidak diberi label yang jelas** — namanya persis sama dengan kolom `bank` (mis. "Bank BCA"), bukan gabungan bank+nomor rekening; kalau ada beberapa rekening dengan nama bank sama, dropdown "Akun Kas" tidak bisa membedakan keduanya (lihat §2).
- **Update dan delete `BankAccount` tidak menyinkronkan `CashAccount`** — berpotensi menyisakan `CashAccount` "yatim" (nama bank sudah tidak ada di daftar rekening) atau nama yang tidak lagi cocok setelah rekening diedit. Tidak ada mekanisme cleanup otomatis.
- **Hapus rekening tidak mengecek pemakaian** — `BankAccountController::destroy` langsung `delete()` tanpa memeriksa apakah `id`-nya masih tercantum di `invoices.bank_account_ids` (JSON, bukan foreign key sungguhan — tidak ada `nullOnDelete`/cascade) milik invoice lama. Invoice yang sudah di-PDF-kan tidak berubah (datanya sudah tercetak), tapi referensi ke rekening yang terhapus tidak divalidasi ulang.
- **`BankAccount`/`CashAccount` bukan soft delete** (beda dari `Customer`, lihat [customer.md §2](customer.md#2-cara-kerja-as-built)) — hapus rekening/akun kas bersifat permanen, tidak bisa dipulihkan lewat aplikasi.
- **Batas hapus admin/akuntan hanya di route `destroy`**, bukan `update` — sales tetap bisa mengedit rekening yang sudah ada (termasuk mengubah nama bank, yang bisa membuat `CashAccount` pasangannya tidak sinkron, lihat poin di atas), hanya tidak bisa menghapusnya.

## 5. Status & yang belum

Berjalan penuh di production; pembatasan hapus khusus admin+akuntan (sales dikecualikan) ditambahkan pertengahan Juli 2026.

## 6. Dokumen terkait

- [invoice.md](invoice.md) — pemakaian `bank_account_ids` di PDF invoice
- [keuangan-ar-ap.md](keuangan-ar-ap.md) — `cash_account_id` di pembayaran AR/AP
- [keuangan-pembukuan.md](keuangan-pembukuan.md) — `CashAccount` sebagai basis laporan pembukuan
- [customer.md](customer.md) — perbandingan pola soft delete di Master Data
