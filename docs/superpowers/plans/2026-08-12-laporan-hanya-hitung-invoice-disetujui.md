# Laporan Hanya Menghitung Invoice Disetujui — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enam laporan keuangan berhenti menghitung proforma yang belum disetujui sebagai penjualan, piutang, laba ditahan, dan peredaran bruto pajak.

**Architecture:** `Invoice` sudah punya `scopeApproved()` dan `FinanceController` sudah memakainya dengan benar; enam pemanggilan di empat controller yang menyimpang. Perbaikannya menambahkan `->approved()` — tidak ada logika baru. Dua tempat menghitung piutang sebagai `SUM(invoices) − SUM(invoice_payments)`, sehingga penyaringnya harus dipasang di KEDUA sisi agar piutang tidak jadi terlalu kecil.

**Tech Stack:** Laravel 11, PHPUnit, MySQL (production) / SQLite (test).

**Spec:** `docs/superpowers/specs/2026-08-12-laporan-hanya-hitung-invoice-disetujui-design.md`

**Branch:** `fix/invoice-approved-laporan-keuangan` (sudah dibuat dari `dev`)

## Global Constraints

- **Definisi "disetujui" tidak diubah.** `Invoice::scopeApproved()` tetap `whereNotNull('approved_at')`. Pakai scope itu, jangan menulis `whereNotNull` sendiri.
- **Tidak ada migrasi. Tidak ada satu baris data pun ditulis ulang.** Yang berubah hanya cara membaca.
- **Piutang disaring di kedua sisi.** `SUM(invoices) − SUM(invoice_payments)`: sisi invoice memakai `approved()`, sisi pembayaran memakai `whereHas('invoice', fn ($q) => $q->approved())`. Menyaring satu sisi saja membuat piutang terlalu kecil.
- **Neraca wajib dikoreksi berpasangan.** Piutang (aset) dan laba ditahan (ekuitas) sama-sama membesar oleh nilai draft yang sama di sisi berlawanan, sehingga Neraca seimbang meski keduanya salah. Memperbaiki satu sisi saja membuatnya benar-benar tidak seimbang — keduanya dalam satu task, satu commit.
- **`FiscalController` tetap memakai `sum('total')`**, bukan `total_idr`. Perbedaan kolom itu pertanyaan mata uang, bukan persetujuan, dan mengubahnya akan membuat selisih snapshot mustahil ditafsirkan. Di luar cakupan.
- **`FinanceController` tidak disentuh** — sudah benar (baris 21, 44, 46).
- **Laporan berbasis `fin_transactions` tidak disentuh:** Rekap Keuangan, Arus Kas, Jurnal, Buku Besar.
- **Gerbang snapshot wajib.** `php artisan finance:snapshot --out=...` sebelum dan sesudah; selisihnya harus hanya pada angka yang dituju dan **nol di tempat lain**. Laporan lain yang ikut bergeser berarti ada pemanggilan ketujuh yang belum ditemukan — selidiki, jangan abaikan.
- **Menjalankan test:** `php artisan test --filter=<NamaTest>` dari root `/Users/marchelinoraco/Documents/2026/erp_wm`.
- **Jangan meng-commit** `docs/design-system/13-my-jobs-manifest.md`, `resources/js/Pages/Finance/Loans.vue`, `docs/superpowers/specs/2026-08-12-invoice-hotel-dua-mode-hitung-design.md`. Selalu `git add` eksplisit, jangan `git add -A`.

## File Structure

| Berkas | Tanggung jawab | Aksi |
| --- | --- | --- |
| `app/Http/Controllers/FinanceReportController.php` | Laba Rugi, Neraca (2 sisi), Saldo Akun | Modifikasi |
| `app/Http/Controllers/DashboardController.php` | Piutang di Dashboard | Modifikasi |
| `app/Http/Controllers/FiscalController.php` | Peredaran bruto | Modifikasi |
| `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php` | Seluruh perilaku baru | Buat |

---

### Task 1: Rekam snapshot dasar

Tanpa langkah ini tidak ada cara membuktikan laporan LAIN tidak ikut bergeser.

**Files:** tidak ada perubahan kode

**Interfaces:**
- Consumes: tidak ada
- Produces: `storage/app/finance-snapshot-SEBELUM.json` — dibaca Task 6

- [ ] **Step 1: Rekam snapshot sebelum perubahan apa pun**

```bash
php artisan finance:snapshot --out=storage/app/finance-snapshot-SEBELUM.json
```

- [ ] **Step 2: Pastikan berkasnya terbentuk dan berisi**

```bash
test -s storage/app/finance-snapshot-SEBELUM.json && head -20 storage/app/finance-snapshot-SEBELUM.json
```
Expected: JSON berisi angka keenam laporan, bukan berkas kosong.

Bila perintahnya gagal karena database test kosong, jalankan `php artisan migrate --seed` lebih dulu, lalu ulangi. Catat di laporan bahwa snapshot diambil dari data lokal, bukan production.

- [ ] **Step 3: Catat isinya di laporan**

Salin ringkasan angka snapshot ke berkas laporan Anda. Task 6 membandingkannya, jadi angka ini harus tercatat di luar berkas JSON yang bisa tertimpa.

Tidak ada commit di task ini — berkas snapshot adalah scratch, bukan bagian dari repo. Pastikan `storage/app/finance-snapshot-*.json` TIDAK ikut ter-commit.

---

### Task 2: Laba Rugi

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php:512`
- Test: `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`

**Interfaces:**
- Consumes: `Invoice::scopeApproved()`
- Produces: tidak ada

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`:

```php
<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Enam laporan keuangan hanya menghitung invoice yang SUDAH disetujui.
 *
 * Proforma yang masih draft bukan penjualan, bukan piutang, bukan laba
 * ditahan, dan bukan peredaran bruto pajak. Terukur di production: 17 dari 29
 * invoice 2026 belum disetujui, menyumbang 57,8% dari angka "Total Penjualan"
 * yang selama ini tampil.
 */
class LaporanHanyaInvoiceDisetujuiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Satu invoice disetujui + satu draft, pada tahun yang sama. */
    private function duaInvoice(): array
    {
        $tour = $this->makeTour('tour', ['pax' => 2]);

        $disetujui = $this->makeInvoice($tour, 5_000_000);
        $this->approveInvoice($disetujui);

        $draft = $this->makeInvoice($tour, 9_000_000);

        return [$disetujui->fresh(), $draft->fresh()];
    }

    private function tahun(): int
    {
        return (int) now()->year;
    }

    public function test_laba_rugi_mengabaikan_invoice_belum_disetujui(): void
    {
        [$disetujui, $draft] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('totalRevenue', (float) $disetujui->total_idr));

        $this->assertGreaterThan(0, $draft->total_idr, 'Draft-nya memang bernilai — kalau nol, test ini tidak membuktikan apa pun');
    }

    public function test_laba_rugi_per_lini_bisnis_ikut_menyaring(): void
    {
        // Bukan hanya angka totalnya: tabel per lini bisnis dihitung terpisah
        // dari koleksi yang sama, jadi ia bisa saja lolos dari penyaring.
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(function ($page) use ($disetujui) {
                $lines = collect($page->toArray()['props']['lines']);

                $this->assertSame(
                    (float) $disetujui->total_idr,
                    (float) $lines->sum('revenue'),
                    'Jumlah penjualan seluruh lini bisnis harus sama dengan total'
                );
            });
    }

    public function test_invoice_disetujui_lalu_dihapus_tetap_tidak_terhitung(): void
    {
        [$disetujui, ] = $this->duaInvoice();
        $disetujui->delete();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('totalRevenue', 0.0));
    }

    public function test_draft_yang_kemudian_disetujui_mulai_terhitung(): void
    {
        // Membuktikan penyaringnya membaca keadaan terkini, bukan hasil cache.
        [$disetujui, $draft] = $this->duaInvoice();

        $this->approveInvoice($draft);

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('totalRevenue', (float) $disetujui->total_idr + (float) $draft->fresh()->total_idr));
    }
}
```

Berkas ini butuh helper `financeUser()`. Bila `Tests\Support\CreatesSalesFixtures` belum menyediakannya, tambahkan method privat berikut di kelas test ini (pola yang sama sudah dipakai `tests/Feature/SalesLine/SalesLinePropPayloadTest.php`):

```php
    /** Halaman Keuangan dibatasi middleware role:admin,accountant. */
    private function financeUser(): \App\Models\User
    {
        return \App\Models\User::create([
            'name'     => 'Akuntan Uji',
            'email'    => 'akuntan' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: FAIL — `totalRevenue` bernilai 14.000.000 (5jt + 9jt), bukan 5.000.000. Angka itu bukti draft-nya memang ikut terhitung hari ini.

- [ ] **Step 3: Saring Laba Rugi**

Di `app/Http/Controllers/FinanceReportController.php`, method `incomeStatementData()`, ganti baris pengambilan invoice:

```php
        $invoices = Invoice::with('tour')->whereYear('date', $year)->get();
```

menjadi:

```php
        // approved(): proforma yang masih draft bukan penjualan. Konvensi yang
        // sama sudah dipakai FinanceController; enam pemanggilan di laporan
        // inilah yang dulu menyimpang darinya.
        $invoices = Invoice::approved()->with('tour')->whereYear('date', $year)->get();
```

`$bills` di baris berikutnya TIDAK disentuh — bill tidak punya konsep persetujuan.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: PASS — 4 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php \
        tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php
git commit -m "fix: Laba Rugi hanya menghitung invoice yang sudah disetujui"
```

---

### Task 3: Neraca — kedua sisi sekaligus

Piutang (aset) dan laba ditahan (ekuitas) sama-sama membesar oleh nilai draft yang sama, di sisi berlawanan persamaan neraca. Memperbaiki satu saja membuat Neraca benar-benar tidak seimbang.

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php:362-363` dan `:433`
- Test: `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`

**Interfaces:**
- Consumes: `Invoice::scopeApproved()`
- Produces: tidak ada

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`:

```php
    public function test_neraca_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        // Prop Neraca bersarang: aset.ar, bukan ar.
        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('aset.ar', (float) $disetujui->total_idr));
    }

    public function test_neraca_tetap_seimbang_dengan_data_campuran(): void
    {
        // Penjaga terpenting di pekerjaan ini. Piutang dan laba ditahan
        // membesar bersamaan oleh nilai draft yang sama, di sisi berlawanan
        // persamaan neraca — sehingga Neraca SEIMBANG meski keduanya salah.
        // Memperbaiki satu sisi saja akan membuatnya benar-benar timpang.
        //
        // balanceSheetData() sudah menghitung sendiri prop `balanced`:
        // abs($asetTotal - ($kewajibanTotal + $ekuitasTotal)) < 1
        $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('balanced', true));
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: FAIL pada `test_neraca_piutang_hanya_dari_invoice_disetujui` — `ar` bernilai 14.000.000, bukan 5.000.000. Test keseimbangan kemungkinan LULUS sejak awal; itu memang sifatnya sebagai penjaga, bukan pendorong perubahan.

- [ ] **Step 3: Saring piutang di Neraca — kedua sisi**

Di `balanceSheetData()`, ganti perhitungan `$ar`:

```php
        $ar = (float) Invoice::where('date', '<=', $endDate)->sum('total_idr')
            - (float) InvoicePayment::where('date', '<=', $endDate)->sum('amount_idr');
```

menjadi:

```php
        // Kedua sisi disaring. Menyaring sisi invoice saja akan mengurangkan
        // pembayaran milik invoice yang tidak ikut dihitung, sehingga piutang
        // jadi terlalu kecil.
        $ar = (float) Invoice::approved()->where('date', '<=', $endDate)->sum('total_idr')
            - (float) InvoicePayment::whereHas('invoice', fn ($q) => $q->approved())
                ->where('date', '<=', $endDate)->sum('amount_idr');
```

- [ ] **Step 4: Saring laba ditahan di Neraca**

Masih di `balanceSheetData()`, pada blok EKUITAS, ganti:

```php
        $invoicedRev   = (float) Invoice::where('date', '<=', $endDate)->sum('total_idr');
```

menjadi:

```php
        $invoicedRev   = (float) Invoice::approved()->where('date', '<=', $endDate)->sum('total_idr');
```

`$billedCost` di baris berikutnya TIDAK disentuh.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: PASS — 6 test hijau, termasuk test keseimbangan

- [ ] **Step 6: Buktikan penjaga keseimbangan itu nyata**

Cabut sementara `approved()` HANYA dari `$invoicedRev` (Step 4), sisakan pada `$ar`. Jalankan `php artisan test --filter=test_neraca_tetap_seimbang`. Test itu harus GAGAL — membuktikan ia benar-benar menangkap koreksi setengah jalan. Kembalikan, lalu jalankan ulang hingga hijau. Sertakan output kegagalannya di laporan.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php \
        tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php
git commit -m "fix: Neraca menyaring piutang dan laba ditahan ke invoice disetujui"
```

---

### Task 4: Saldo Akun dan Dashboard

Keduanya menghitung piutang dengan rumus yang sama, jadi diperbaiki bersama dan diuji konsisten satu sama lain.

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php:329`
- Modify: `app/Http/Controllers/DashboardController.php:70-71`
- Test: `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`

**Interfaces:**
- Consumes: `Invoice::scopeApproved()`
- Produces: tidak ada

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`:

```php
    public function test_saldo_akun_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.account-balances'))
            ->assertInertia(fn ($page) => $page->where('ar', (float) $disetujui->total_idr));
    }

    public function test_dashboard_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('arOutstanding', (float) $disetujui->total_idr));
    }
```

Nama route dan prop di atas sudah diverifikasi: `finance.account-balances` mengembalikan `ar` (datar, dari `accountBalancesData()`), dan `dashboard` mengembalikan `arOutstanding` (`DashboardController:87`). Pakai apa adanya.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: FAIL pada kedua test baru — keduanya bernilai 14.000.000, bukan 5.000.000

- [ ] **Step 3: Saring piutang di Saldo Akun**

Di `accountBalancesData()`, ganti:

```php
            'ar'        => (float) Invoice::sum('total_idr') - (float) InvoicePayment::sum('amount_idr'),
```

menjadi:

```php
            // Kedua sisi disaring — lihat balanceSheetData() untuk alasannya.
            'ar'        => (float) Invoice::approved()->sum('total_idr')
                - (float) InvoicePayment::whereHas('invoice', fn ($q) => $q->approved())->sum('amount_idr'),
```

- [ ] **Step 4: Saring piutang di Dashboard**

Di `app/Http/Controllers/DashboardController.php`, ganti perhitungan `$arOutstanding`:

```php
            $arOutstanding = (float) Invoice::when($user->isSales(), fn ($q) => $q->whereHas('tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('total_idr')
                - (float) InvoicePayment::when($user->isSales(), fn ($q) => $q->whereHas('invoice.tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('amount_idr');
```

menjadi:

```php
            // approved() di kedua sisi — proforma draft bukan piutang, dan
            // pembayarannya tidak boleh ikut mengurangi.
            $arOutstanding = (float) Invoice::approved()->when($user->isSales(), fn ($q) => $q->whereHas('tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('total_idr')
                - (float) InvoicePayment::whereHas('invoice', fn ($q) => $q->approved())->when($user->isSales(), fn ($q) => $q->whereHas('invoice.tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('amount_idr');
```

`$apOutstanding` di bawahnya TIDAK disentuh.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: PASS — 8 test hijau

- [ ] **Step 6: Buktikan ketiga tempat sepakat**

Tambahkan test berikut dan jalankan:

```php
    public function test_piutang_sama_di_neraca_saldo_akun_dan_dashboard(): void
    {
        // Tiga halaman menghitung piutang dengan rumus terpisah. Kalau salah
        // satu terlewat disaring, angkanya akan berbeda — dan pengguna yang
        // membandingkan dua halaman akan melihat sistem berselisih dengan
        // dirinya sendiri.
        [$disetujui, ] = $this->duaInvoice();
        $akuntan  = $this->financeUser();
        $harapan  = (float) $disetujui->total_idr;

        // Neraca menyimpannya bersarang di aset.ar; dua lainnya datar.
        $this->actingAs($akuntan)
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('aset.ar', $harapan));

        $this->actingAs($akuntan)
            ->get(route('finance.account-balances'))
            ->assertInertia(fn ($page) => $page->where('ar', $harapan));

        $this->actingAs($akuntan)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('arOutstanding', $harapan));
    }
```

Ketiganya dibandingkan terhadap satu angka harapan yang sama, sehingga kalau salah satu halaman terlewat disaring, test ini menunjuk halaman mana yang berselisih.

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: PASS — 9 test hijau

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php \
        app/Http/Controllers/DashboardController.php \
        tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php
git commit -m "fix: piutang di Saldo Akun dan Dashboard menyaring invoice disetujui"
```

---

### Task 5: Fiskal

Ini dasar perhitungan pajak — nilai tertinggi risikonya di seluruh pekerjaan ini.

**Files:**
- Modify: `app/Http/Controllers/FiscalController.php:37`
- Test: `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`

**Interfaces:**
- Consumes: `Invoice::scopeApproved()`
- Produces: tidak ada

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php`:

```php
    public function test_fiskal_peredaran_bruto_hanya_dari_invoice_disetujui(): void
    {
        // Peredaran bruto adalah dasar hitung pajak. Proforma draft yang ikut
        // terhitung berarti pajak dihitung dari penjualan yang belum ada.
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.fiscal', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('totalRevenue', (float) $disetujui->total));
    }
```

Sudah diverifikasi: route `finance.fiscal` ada di `routes/web.php:268`, dan `FiscalController` mengembalikan `'totalRevenue'` pada baris 108. Perhatikan Fiskal memakai kolom `total`, BUKAN `total_idr` seperti lima tempat lain — assertion di atas sudah memakai `total`, jangan diubah.

Satu hal yang menjelaskan kenapa task ini paling berisiko: `FiscalController:95` memakai `$totalRevenue` langsung sebagai `$taxBase`. Angka yang diperbaiki di sini adalah dasar hitung pajaknya, bukan sekadar tampilan.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: FAIL — `totalRevenue` memuat kedua invoice

- [ ] **Step 3: Saring peredaran bruto**

Di `app/Http/Controllers/FiscalController.php`, ganti:

```php
        $totalRevenue = (float) Invoice::whereYear('date', $year)->sum('total');
```

menjadi:

```php
        // approved(): pajak tidak dihitung dari proforma yang belum diakui
        // sebagai penjualan. Kolomnya tetap `total`, bukan `total_idr` —
        // perbedaan itu soal mata uang dan sengaja tidak disentuh di sini.
        $totalRevenue = (float) Invoice::approved()->whereYear('date', $year)->sum('total');
```

`$totalCogs` di baris berikutnya TIDAK disentuh.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=LaporanHanyaInvoiceDisetujuiTest`
Expected: PASS — 10 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FiscalController.php \
        tests/Feature/Finance/LaporanHanyaInvoiceDisetujuiTest.php
git commit -m "fix: peredaran bruto Fiskal menyaring invoice disetujui"
```

---

### Task 6: Gerbang snapshot dan verifikasi menyeluruh

**Files:** tidak ada perubahan kode

**Interfaces:**
- Consumes: `storage/app/finance-snapshot-SEBELUM.json` dari Task 1
- Produces: tidak ada

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `php artisan test`
Expected: PASS — seluruh suite hijau. Kegagalan di sini berarti laporan lain bergantung pada perilaku lama; laporkan, jangan tambal.

- [ ] **Step 2: Rekam snapshot sesudah**

```bash
php artisan finance:snapshot --out=storage/app/finance-snapshot-SESUDAH.json
```

- [ ] **Step 3: Bandingkan**

```bash
diff storage/app/finance-snapshot-SEBELUM.json storage/app/finance-snapshot-SESUDAH.json
```

Salin seluruh keluaran `diff` ke laporan Anda.

**Syarat lulus:** setiap angka yang berubah harus dapat Anda jelaskan sebagai akibat langsung dari salah satu dari enam penyaring, dan angka itu harus **turun**, tidak naik. Bila ada angka yang berubah dan Anda TIDAK bisa menjelaskannya dari keenam perubahan itu — berhenti, laporkan sebagai temuan, jangan lanjutkan. Itu tanda ada pemanggilan ketujuh yang belum ditemukan.

Bila `diff` kosong sama sekali, itu juga temuan: berarti data uji lokal tidak memuat invoice yang belum disetujui, sehingga gerbang ini tidak membuktikan apa pun. Katakan begitu apa adanya di laporan.

- [ ] **Step 4: Sapu pemanggilan yang tersisa**

```bash
grep -rn "Invoice::" app/Http/Controllers app/Models | grep -iE "sum\(|count\(" | grep -v "approved()"
```
Expected: tidak ada hasil, ATAU hanya hasil yang bisa Anda jelaskan alasannya tidak perlu disaring. Daftarkan setiap hasil beserta alasannya di laporan.

- [ ] **Step 5: Pastikan berkas snapshot tidak ikut ter-commit**

```bash
git status --short
```
Expected: hanya tiga berkas tak berhubungan yang sudah dikenal. Berkas `storage/app/finance-snapshot-*.json` TIDAK boleh muncul sebagai untracked yang akan ter-commit; hapus keduanya setelah `diff` tercatat di laporan.

```bash
rm -f storage/app/finance-snapshot-SEBELUM.json storage/app/finance-snapshot-SESUDAH.json
```

- [ ] **Step 6: Pastikan commit bersih**

```bash
git log --oneline dev..HEAD
```
Expected: 4 commit perbaikan ditambah 1 commit spesifikasi, tanpa commit yang menyentuh `docs/design-system/`, `Pages/Finance/Loans.vue`, atau berkas snapshot.
