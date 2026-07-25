# Fase 2 — Migrasi & Backfill `sales_line`: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah kolom `sales_line` dan `billing_quantities` pada tabel `invoices`, lalu mengisi **hanya** `sales_line` untuk data lama lewat perintah backfill yang aman dijalankan berkali-kali — tanpa mengubah satu angka pun (`total`, `total_idr`, `pax`, `unit_price`) pada invoice mana pun, draft maupun yang sudah disetujui.

**Architecture:** Migrasi murni penambahan (dua kolom nullable, dijaga `Schema::hasColumn` untuk idempotensi). Backfill sebagai perintah artisan terpisah dari migrasi, mengikuti pola `BackfillSupplierBills` yang sudah ada di repo. `billing_quantities` sengaja **tidak diisi** — lihat §3.3.1 dokumen desain untuk alasannya: mengisinya untuk invoice draft yang ada akan membekukan pengali `pax` pada nilai backfill, melanggar sifat "total ikut pax tour" yang sudah dikunci Fase 0.

**Tech Stack:** Laravel 13, PHPUnit, SQLite in-memory untuk test (mengikuti pola seluruh Fase 0–1).

**Spec:** [`docs/design_pemisahan_invoice_per_jenis_penjualan.md`](design_pemisahan_invoice_per_jenis_penjualan.md) — §3.3 (skema), §3.3.1 (batas cakupan Fase 2), §4 (baris Fase 2), §7 (protokol keamanan data lengkap).

**Cakupan repo:** `erp_wm`, branch `feat/backfill-sales-line-fase-2` (sudah dibuat dari `dev`, sudah membawa koreksi desain §3.3.1).

---

## Verifikasi empiris yang sudah dilakukan sebelum plan ini ditulis

Tiga asumsi diuji langsung sebelum plan ditulis, karena Fase 2 menyentuh migrasi skema — kesalahan di sini lebih mahal daripada di fase test-only:

| Asumsi | Hasil verifikasi |
|---|---|
| Migrasi dua kolom nullable + `Schema::hasColumn` guard jalan di SQLite (lingkungan test) | ✅ Berhasil, 1 test, 5 assertion |
| Migrasi aman dijalankan dua kali (idempoten) | ✅ `up()` kedua tidak melempar exception |
| `down()` membersihkan kedua kolom tanpa error | ✅ `Schema::hasColumn` kembali `false` untuk keduanya |
| `$invoice->tour` mengembalikan `null` untuk tour yang ter-soft-delete | ✅ Terverifikasi — `Tour` memakai `SoftDeletes`, relasi `belongsTo` tanpa `withTrashed()` mengembalikan `null` |

Catatan penting: `.env` lokal menunjuk ke MySQL (`127.0.0.1:3306`) yang **tidak berjalan** di komputer pengembangan ini — itu bukan indikasi masalah kompatibilitas. Semua verifikasi di atas dan seluruh test di plan ini berjalan lewat `php artisan test`, yang memakai SQLite in-memory (`phpunit.xml`). Verifikasi terhadap MySQL sungguhan terjadi di staging (`dev-erp.welcomemanado.com` + database `erp_wm_dev`, sudah tersedia) sesuai §7.3 — **itu langkah wajib terpisah di luar plan ini**, dijalankan manual sebelum Fase 2 boleh menyentuh production (lihat Verifikasi Akhir).

## Perbedaan penting dari fase sebelumnya

Fase 0 dan Fase 1 murni menulis test atau kode aplikasi yang diuji lewat `php artisan test`. Fase 2 **menulis migrasi skema** — jenis perubahan yang tidak bisa di-*rollback* semudah `git revert` begitu sudah jalan di data production sungguhan. Karena itu:

- Setiap task diakhiri test yang membuktikan migrasi/backfill **tidak mengubah nominal apa pun** — bukan cuma "kolom baru ada", tapi "kolom lama tidak bergeser".
- Task terakhir menjalankan ulang **seluruh 118 test yang sudah ada** (Fase 0 + Fase 1) sebagai gerbang — persis pola gerbang identitas Fase 1.
- Dokumen ini **tidak** menggantikan §7.3/§7.8 dokumen desain. Menyelesaikan seluruh task di sini berarti kode sudah benar dan teruji di SQLite; migrasi **belum boleh** dijalankan di database production sungguhan sampai checklist §7.8 (backup, uji di salinan data production, nol selisih) dipenuhi secara terpisah — itu langkah operasional, bukan langkah coding.

## Global Constraints

- Semua path relatif terhadap `/Users/marchelinoraco/Documents/2026/erp_wm`.
- Perintah test: `php artisan test` dari root repo.
- **`billing_quantities` TIDAK diisi backfill.** Tetap `null` untuk semua invoice. Jangan menulis kode yang mengisinya — itu keputusan sadar (§3.3.1), bukan pekerjaan yang terlewat.
- **Backfill DILARANG memanggil `syncProformaTotal()`** untuk baris mana pun (§7.4). Backfill hanya boleh menulis `sales_line` lewat `update(['sales_line' => ...])` langsung.
- **Backfill wajib idempoten**: hanya memproses baris `sales_line IS NULL`. Menjalankan ulang tidak boleh mengubah baris yang sudah terisi.
- Migrasi wajib idempoten lewat `Schema::hasColumn()` (§7.7) — migrasi boleh dijalankan ulang tanpa error bila terputus di tengah jalan.
- `sales_line` diisi dari `$invoice->tour?->type ?? 'tour'` — fallback yang **sama persis** dengan yang dipakai `syncProformaTotal()` di `Invoice.php`, supaya konsisten dengan sumber yang menghasilkan `total` invoice itu sendiri.
- Komentar kode dalam **Bahasa Indonesia**.
- Setiap commit memakai prefiks Conventional Commits dan diakhiri baris:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
- Jangan menyentuh `tests/Feature/Invoice/` (34 test Fase 0) maupun `tests/Feature/SalesLine/` atau `tests/Unit/SalesLine/` (Fase 1) — task terakhir membuktikan lewat `git diff` bahwa berkas-berkas itu nol berubah.

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php` | Migrasi dua kolom nullable, idempoten |
| `app/Console/Commands/BackfillInvoiceSalesLine.php` | Backfill `sales_line` saja, idempoten, tanpa `syncProformaTotal()` |
| `tests/Feature/SalesLine/InvoiceSalesLineMigrationTest.php` | Kolom ada, nullable, idempoten, `down()` bersih |
| `tests/Feature/SalesLine/BackfillInvoiceSalesLineTest.php` | Korektheid backfill per jenis, non-sentuh nominal, idempotensi, kasus tepi |

---

### Task 1: Migrasi kolom `sales_line` dan `billing_quantities`

**Files:**
- Create: `database/migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php`
- Test: `tests/Feature/SalesLine/InvoiceSalesLineMigrationTest.php`

**Interfaces:**
- Consumes: tabel `invoices` yang sudah ada.
- Produces: kolom `invoices.sales_line` (`string(20)`, nullable) dan `invoices.billing_quantities` (`json`, nullable). Task 2 menulis ke `sales_line`; `billing_quantities` tidak dipakai task mana pun di plan ini.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/InvoiceSalesLineMigrationTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrasi Fase 2: dua kolom nullable pada invoices. Idempoten (Schema::hasColumn)
 * supaya aman dijalankan ulang bila migrasi terputus di tengah jalan (§7.7).
 */
class InvoiceSalesLineMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_sales_line_ada_dan_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
    }

    public function test_kolom_billing_quantities_ada_dan_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'));
    }

    public function test_kedua_kolom_defaultnya_null_untuk_invoice_baru(): void
    {
        $tour = \App\Models\Tour::create(['type' => 'tour', 'status' => 'confirmed', 'pax' => 1]);

        $invoice = \App\Models\Invoice::create([
            'tour_id'    => $tour->id,
            'number'     => \App\Models\Invoice::nextNumber($tour),
            'date'       => now()->toDateString(),
            'currency'   => 'IDR',
            'unit_price' => 100_000,
        ]);

        $this->assertNull($invoice->fresh()->sales_line);
        $this->assertNull($invoice->fresh()->billing_quantities);
    }

    public function test_migrasi_aman_dijalankan_ulang(): void
    {
        // Simulasi migrasi terputus lalu dijalankan ulang — up() kedua tidak
        // boleh error karena kolom sudah ada (Schema::hasColumn guard).
        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'));
    }

    public function test_migrasi_pulih_dari_kolom_yang_setengah_selesai(): void
    {
        // Skenario §7.7 yang sesungguhnya: migrasi terputus PERSIS di antara
        // dua kolom — satu sudah ada, satu belum. Guard per kolom harus
        // independen: kolom yang hilang ditambahkan, kolom yang sudah ada
        // TIDAK boleh memicu error "column already exists".
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('billing_quantities');
        });
        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertFalse(Schema::hasColumn('invoices', 'billing_quantities'));

        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('invoices', 'sales_line'), 'Kolom yang sudah ada tidak boleh error');
        $this->assertTrue(Schema::hasColumn('invoices', 'billing_quantities'), 'Kolom yang hilang harus ditambahkan');
    }

    public function test_down_membersihkan_kedua_kolom(): void
    {
        $migration = require database_path(
            'migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php'
        );

        $migration->down();

        $this->assertFalse(Schema::hasColumn('invoices', 'sales_line'));
        $this->assertFalse(Schema::hasColumn('invoices', 'billing_quantities'));

        // Pulihkan supaya RefreshDatabase test berikutnya tidak terpengaruh —
        // migrate:fresh di awal setiap run akan membangun ulang dari nol,
        // tapi kita kembalikan eksplisit agar test ini tidak meninggalkan
        // skema rusak bila dijalankan sendirian dengan --filter.
        $migration->up();
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=InvoiceSalesLineMigrationTest`
Expected: FAIL — kolom `sales_line` belum ada (`Schema::hasColumn` mengembalikan `false`, assertion gagal). Migrasi belum dibuat sehingga `require database_path(...)` pada dua test terakhir juga akan gagal dengan file tidak ditemukan.

- [ ] **Step 3: Buat migrasi**

`database/migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 2 pemisahan aturan invoice per jenis penjualan — lihat
     * docs/design_pemisahan_invoice_per_jenis_penjualan.md §3.3.
     *
     * Migrasi ini HANYA menambah dua kolom nullable. Tidak ada kolom dihapus,
     * diganti nama, atau diubah tipe — down() aman tanpa kehilangan data
     * (§7.1 protokol keamanan data).
     *
     * Schema::hasColumn menjaga migrasi tetap idempoten: bila proses migrate
     * terputus di tengah jalan dan dijalankan ulang, up() tidak melempar
     * error "column already exists" (§7.7).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'sales_line')) {
                $table->string('sales_line', 20)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('invoices', 'billing_quantities')) {
                $table->json('billing_quantities')->nullable()->after('sales_line');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['sales_line', 'billing_quantities']);
        });
    }
};
```

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=InvoiceSalesLineMigrationTest`
Expected: PASS, 6 test

- [ ] **Step 5: Jalankan seluruh suite untuk memastikan tidak ada regresi**

Run: `php artisan test`
Expected: PASS semua — 118 test lama + 6 test baru = 124

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php tests/Feature/SalesLine/InvoiceSalesLineMigrationTest.php
git commit -m "feat: migrasi kolom sales_line dan billing_quantities pada invoices

Dua kolom nullable, dijaga Schema::hasColumn agar idempoten bila migrasi
terputus di tengah jalan (§7.7). Tidak ada kolom lama disentuh — down()
aman tanpa kehilangan data (§7.1).

billing_quantities sengaja belum diisi apa pun di Fase 2 — lihat §3.3.1
dokumen desain. Backfill (task berikutnya) hanya mengisi sales_line.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Perintah backfill `invoices:backfill-sales-line`

**Files:**
- Create: `app/Console/Commands/BackfillInvoiceSalesLine.php`
- Test: `tests/Feature/SalesLine/BackfillInvoiceSalesLineTest.php`

**Interfaces:**
- Consumes: kolom `invoices.sales_line` (Task 1); model `Invoice`, `Tour` yang sudah ada; trait `Tests\Support\CreatesSalesFixtures` (dari Fase 0, sudah ada di `tests/Support/CreatesSalesFixtures.php`) untuk `makeTour()`, `makeInvoice()`, `approveInvoice()`.
- Produces: perintah artisan `invoices:backfill-sales-line`, dipanggil manual (bukan dijadwalkan) saat Fase 2 dirilis ke production sesuai §7.8. Tidak ada task lanjutan di plan ini yang memakainya.

Ini task paling penting di Fase 2. Setiap test di sini membuktikan **satu klaim keamanan spesifik** dari §7 dokumen desain — bukan sekadar "backfill jalan".

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/BackfillInvoiceSalesLineTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Console\Commands\BackfillInvoiceSalesLine;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Setiap test di sini membuktikan satu klaim keamanan spesifik dari §7
 * dokumen desain — bukan sekadar "backfill jalan tanpa error".
 */
class BackfillInvoiceSalesLineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_mengisi_sales_line_sesuai_tipe_tour_untuk_ketujuh_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type), 500_000);

            $this->assertNull($invoice->sales_line, "Prasyarat: {$type} belum di-backfill");
        }

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        foreach (Invoice::all() as $invoice) {
            $this->assertSame(
                $invoice->tour->type,
                $invoice->fresh()->sales_line,
                "Invoice untuk tour {$invoice->tour->type} harus mendapat sales_line yang sama"
            );
        }
    }

    public function test_tidak_mengubah_satu_pun_nominal_pada_invoice_draft(): void
    {
        // Nominal yang direkam SEBELUM backfill — ini yang dibuktikan tetap sama.
        $tour    = $this->makeTour('guide', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $totalSebelum      = $invoice->total;
        $totalIdrSebelum   = $invoice->total_idr;
        $paxSebelum        = $invoice->pax;
        $unitPriceSebelum  = (string) $invoice->unit_price;

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $sesudah = $invoice->fresh();

        $this->assertEquals($totalSebelum, $sesudah->total, 'total tidak boleh bergeser');
        $this->assertEquals($totalIdrSebelum, $sesudah->total_idr, 'total_idr tidak boleh bergeser');
        $this->assertSame($paxSebelum, $sesudah->pax, 'pax tidak boleh bergeser');
        $this->assertSame($unitPriceSebelum, (string) $sesudah->unit_price, 'unit_price tidak boleh bergeser');
        $this->assertNull($sesudah->billing_quantities, 'billing_quantities TETAP null — §3.3.1');
    }

    public function test_tidak_mengubah_nominal_pada_invoice_yang_sudah_disetujui(): void
    {
        // Mencerminkan INV-2026-0009 di production: invoice disetujui dengan
        // biaya tambahan (§3.4.1) — total-nya bukan lagi unit_price × pax
        // sederhana, dan backfill harus tetap tidak menyentuhnya.
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->approveInvoice($this->makeInvoice($tour, 13_139_000));

        $totalSetelahApprove = $invoice->fresh()->total;

        // Simulasikan biaya tambahan pasca-persetujuan, seperti
        // CostRequestController::appendAdditionalCharge() di production.
        $invoice->update([
            'total'     => $totalSetelahApprove + 650_000,
            'total_idr' => $invoice->fresh()->total_idr + 650_000,
        ]);
        $totalDenganTambahan = $invoice->fresh()->total;

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $sesudah = $invoice->fresh();

        $this->assertEquals($totalDenganTambahan, $sesudah->total, 'total invoice approved+tambahan tidak boleh bergeser');
        $this->assertSame('tour', $sesudah->sales_line, 'sales_line tetap terisi meski sudah disetujui');
    }

    public function test_idempoten_baris_yang_sudah_terisi_tidak_diproses_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('mice'), 500_000);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);
        $sesudahPertama = $invoice->fresh()->sales_line;

        // Ubah tipe tour SETELAH backfill pertama. Bila backfill kedua
        // memproses ulang baris yang sudah terisi, sales_line akan berubah
        // ikut tour — itu justru bukti pelanggaran idempotensi.
        $invoice->tour->update(['type' => 'hotel']);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertSame($sesudahPertama, $invoice->fresh()->sales_line, 'Baris yang sudah terisi tidak boleh diproses ulang');
        $this->assertSame('mice', $invoice->fresh()->sales_line);
    }

    public function test_tour_soft_deleted_jatuh_ke_tour(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);
        $tour->delete();

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertSame(
            'tour',
            $invoice->fresh()->sales_line,
            'Tour soft-deleted -> $invoice->tour null -> fallback ke tour, sama seperti syncProformaTotal()'
        );
    }

    public function test_tidak_pernah_memanggil_sync_proforma_total(): void
    {
        // Bukti tidak langsung: unit_price sengaja diisi 0 dan pax dibuat
        // besar. Bila backfill diam-diam memanggil syncProformaTotal(), total
        // akan berubah (dari nilai awal manual ke 0 × pax = 0, atau sebaliknya
        // jika unit_price diubah — intinya total TIDAK boleh bergerak sama
        // sekali karena tidak ada perhitungan yang seharusnya berjalan).
        $tour    = $this->makeTour('ticketing', ['pax' => 99]);
        $invoice = $this->makeInvoice($tour, 500_000);

        // Paksa total ke nilai yang TIDAK mungkin dihasilkan syncProformaTotal
        // untuk kombinasi unit_price/pax di atas (500_000 × 99 = 49_500_000).
        $invoice->update(['total' => 1, 'total_idr' => 1]);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertEquals(
            1,
            $invoice->fresh()->total,
            'Jika ini gagal, backfill diam-diam memanggil syncProformaTotal() dan menghitung ulang total — dilarang §7.4'
        );
    }

    public function test_laporan_jumlah_baris_diproses(): void
    {
        $this->makeInvoice($this->makeTour('tour'), 100_000);
        $this->makeInvoice($this->makeTour('hotel'), 100_000);

        $this->artisan('invoices:backfill-sales-line')
            ->expectsOutputToContain('2 invoice')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=BackfillInvoiceSalesLineTest`
Expected: FAIL — perintah `invoices:backfill-sales-line` belum terdaftar (`Command "invoices:backfill-sales-line" is not defined`).

- [ ] **Step 3: Buat perintah backfill**

`app/Console/Commands/BackfillInvoiceSalesLine.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Backfill Fase 2 pemisahan aturan invoice per jenis penjualan — lihat
 * docs/design_pemisahan_invoice_per_jenis_penjualan.md §3.3.1.
 *
 * HANYA mengisi sales_line. billing_quantities SENGAJA dibiarkan null —
 * mengisinya untuk invoice draft yang ada akan membekukan pengali pax pada
 * nilai saat backfill, melanggar sifat "total ikut pax tour" yang dikunci
 * Fase 0 (PaxSourceCharacterizationTest::test_mengubah_pax_tour_menggeser_total_invoice_draft).
 *
 * TIDAK PERNAH memanggil syncProformaTotal() — itu akan menulis ulang total
 * & pax, termasuk pada invoice yang sudah disetujui, karena syncProformaTotal()
 * sendiri tidak dijaga is_approved (§7.4). Perintah ini hanya menulis kolom
 * sales_line lewat update() langsung.
 *
 * Idempoten: hanya memproses baris sales_line IS NULL. Aman dijalankan
 * berkali-kali bila terputus di tengah jalan (§7.7).
 */
class BackfillInvoiceSalesLine extends Command
{
    protected $signature = 'invoices:backfill-sales-line';

    protected $description = 'Isi kolom sales_line dari tipe tour untuk invoice lama (billing_quantities sengaja tidak diisi)';

    public function handle(): int
    {
        // Cakupan default (tanpa withTrashed): audit data production
        // menunjukkan 0 invoice ter-soft-delete saat ini, dan tidak ada
        // kebutuhan menyertakannya — sales_line hanya dipakai jalur hitung
        // yang juga tidak menyentuh invoice terhapus.
        $invoices = Invoice::whereNull('sales_line')
            ->with('tour')
            ->get();

        foreach ($invoices as $invoice) {
            $salesLine = $invoice->tour?->type ?? 'tour';

            // update() langsung — BUKAN syncProformaTotal(). Tidak menyentuh
            // total, total_idr, pax, unit_price, atau billing_quantities.
            $invoice->update(['sales_line' => $salesLine]);
        }

        $this->info("Selesai. {$invoices->count()} invoice di-backfill sales_line-nya.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=BackfillInvoiceSalesLineTest`
Expected: PASS, 7 test

- [ ] **Step 5: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS semua — 124 test dari Task 1 + 7 test baru = 131

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillInvoiceSalesLine.php tests/Feature/SalesLine/BackfillInvoiceSalesLineTest.php
git commit -m "feat: perintah backfill invoices:backfill-sales-line

Mengisi HANYA sales_line dari tipe tour, fallback 'tour' sama persis
seperti syncProformaTotal() (termasuk untuk tour yang sudah soft-deleted).
billing_quantities sengaja tidak diisi (§3.3.1). Tidak pernah memanggil
syncProformaTotal() — dibuktikan total/total_idr/pax/unit_price tidak
bergeser sama sekali, termasuk pada invoice yang sudah disetujui dengan
biaya tambahan pasca-approval (mencerminkan INV-2026-0009 di production).
Idempoten: baris yang sudah terisi tidak diproses ulang.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Gerbang identitas — buktikan Fase 0 dan Fase 1 tidak tersentuh

**Files:**
- Tidak ada berkas baru. Task ini murni verifikasi dan dokumentasi status.

**Interfaces:**
- Consumes: seluruh 118 test dari Fase 0 + Fase 1 (tidak diubah).
- Produces: tidak ada — task terakhir plan ini.

- [ ] **Step 1: Jalankan gerbang identitas Fase 0**

Run: `php artisan test tests/Feature/Invoice`
Expected: PASS, 34/34 — persis seperti sebelum Fase 2 dimulai.

- [ ] **Step 2: Jalankan gerbang identitas Fase 1**

Run: `php artisan test tests/Feature/SalesLine tests/Unit/SalesLine`
Expected: PASS — seluruh test Fase 1 (16 test) **plus** test baru Fase 2 (13 test dari Task 1+2 — Task 1 punya 6 setelah perbaikan §7.7, bukan 5) dari direktori yang sama, karena berkas Fase 2 sengaja diletakkan di `tests/Feature/SalesLine/`. Total pada direktori ini: 29.

- [ ] **Step 3: Buktikan tidak ada satu pun berkas Fase 0/Fase 1 yang berubah**

Run: `git diff --stat dev...HEAD -- tests/Feature/Invoice tests/Unit/SalesLine/MultiplierTest.php tests/Unit/SalesLine/CalculateTotalTest.php tests/Unit/SalesLine/SalesLineRuleRegistryTest.php tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php app/Models/Invoice.php app/Services/SalesLine app/Contracts/SalesLineInvoiceRule.php`
Expected: keluaran kosong. Bila ada satu baris pun, Task 1/2 diam-diam menyentuh sesuatu di luar cakupannya — periksa sebelum lanjut.

- [ ] **Step 4: Jalankan seluruh suite sekali lagi sebagai gerbang akhir**

Run: `php artisan test`
Expected: PASS semua, 131 test.

- [ ] **Step 5: Commit penanda selesai (jika ada perubahan tertunda)**

Bila Step 1–4 semua lulus tanpa perubahan berkas tambahan, tidak ada yang perlu di-commit di task ini — Task 1 dan 2 sudah final. Jika ternyata ada temuan yang memerlukan perbaikan, perbaiki, lalu ulangi Step 1–4 sebelum commit.

---

## Verifikasi Akhir Fase 2 (kode) — sebelum lanjut ke langkah operasional

```bash
php artisan test
php artisan test tests/Feature/Invoice
git diff --stat dev...HEAD -- tests/Feature/Invoice app/Models/Invoice.php app/Services/SalesLine app/Contracts
```

Yang harus terbukti oleh test, bukan oleh keyakinan:

| Properti | Dibuktikan oleh |
|---|---|
| Kolom baru ada, nullable, idempoten, `down()` bersih | `InvoiceSalesLineMigrationTest` |
| `sales_line` terisi benar untuk ketujuh jenis, termasuk fallback tour ter-soft-delete | `BackfillInvoiceSalesLineTest::test_mengisi_sales_line_...`, `test_tour_soft_deleted_jatuh_ke_tour` |
| **Nol perubahan nominal pada invoice draft** | `test_tidak_mengubah_satu_pun_nominal_pada_invoice_draft` |
| **Nol perubahan nominal pada invoice disetujui dengan biaya tambahan** (mencerminkan data production nyata) | `test_tidak_mengubah_nominal_pada_invoice_yang_sudah_disetujui` |
| `billing_quantities` tetap `null` (§3.3.1) | kedua test nominal di atas |
| Backfill idempoten | `test_idempoten_baris_yang_sudah_terisi_tidak_diproses_ulang` |
| Backfill tidak pernah memanggil `syncProformaTotal()` | `test_tidak_pernah_memanggil_sync_proforma_total` |
| Fase 0 (34 test) dan Fase 1 (16 test) sama sekali tidak tersentuh | Task 3, `git diff` kosong |

**Definisi selesai untuk plan ini:** seluruh 131 test hijau, `git diff` terhadap berkas Fase 0/Fase 1 kosong.

**Definisi selesai untuk Fase 2 secara keseluruhan (di luar plan ini, langkah operasional §7.8):**

- [ ] Backup manual database production terbaru, diverifikasi bisa di-restore
- [ ] Migrasi + backfill dijalankan di **salinan data production** — gunakan staging `dev-erp.welcomemanado.com` + database `erp_wm_dev` yang sudah tersedia, isi dengan dump production terbaru
- [ ] Query verifikasi §7.5 (`SELECT id, number, total, total_idr FROM invoices ORDER BY id`) dibandingkan sebelum/sesudah backfill di staging — nol selisih
- [ ] Baru setelah staging bersih: migrasi + backfill dijalankan di production sungguhan

Langkah-langkah operasional ini **tidak** bisa diotomatisasi lewat plan/task seperti Fase 0–1, karena menyentuh server dan data sungguhan — dilakukan manual oleh pengguna mengikuti checklist §7.8, dengan kode dari plan ini sebagai alat yang sudah teruji.

## Cakupan Spec

| Bagian spec | Task |
|---|---|
| §3.3 — kolom baru `sales_line`, `billing_quantities` | Task 1 |
| §3.3.1 — `billing_quantities` sengaja tidak diisi Fase 2 | Task 1 (skema), Task 2 (backfill tidak menyentuhnya) |
| §4 — baris Fase 2: migrasi + backfill sales_line + syncProformaTotal tidak berubah | Task 1, 2, 3 |
| §7.1 — migrasi hanya menambah | Task 1 |
| §7.4 — backfill dilarang memanggil `syncProformaTotal()`, update langsung | Task 2 |
| §7.7 — migrasi & backfill idempoten | Task 1 (migrasi), Task 2 (backfill) |
| §7.5/§7.8 — nol selisih di data production | Di luar cakupan plan ini — langkah operasional terpisah, lihat Verifikasi Akhir |

**Bagian spec yang sengaja belum dikerjakan di plan ini:** §7.2/§7.3/§7.6/§7.8 adalah langkah operasional (backup, uji di salinan production, peluncuran bertahap) — tidak ada kode untuk itu, hanya checklist yang dijalankan manusia setelah plan ini selesai. Fase 3 (pengali dapat diedit + label UI) dan seterusnya mendapat plan sendiri.
