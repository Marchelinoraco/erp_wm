# Akun Non-Laba-Rugi (Tahap A) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memberi sistem keuangan ERP kemampuan mencatat akun aset dan baris jurnal non-kas, tanpa mengubah satu angka pun di laporan yang sudah berjalan.

**Architecture:** Kategori keuangan mendapat tipe ketiga (`asset`). Transaksi mendapat lawan-akun opsional berupa kategori, sehingga ada baris jurnal yang tidak menyentuh kas. Mesin jurnal 2-baris yang ada **tidak dirombak** — satu entri 3 baris dipecah jadi dua entri 2 baris yang masing-masing sah. Lima laporan berhenti mengasumsikan setiap pengeluaran adalah beban.

**Tech Stack:** Laravel 12, PHPUnit 12 (bukan Pest), Inertia + Vue 3, MySQL di production, SQLite in-memory untuk uji.

**Spek:** [`docs/superpowers/specs/2026-07-31-master-karyawan-design.md`](../specs/2026-07-31-master-karyawan-design.md) §3

**Branch:** `feat/akun-non-laba-rugi` (sudah dibuat dari `dev` @ `d2a382d`)

## Global Constraints

- **Tidak ada satu angka laporan pun yang boleh berubah.** Ini kriteria keberhasilan Tahap A, bukan sekadar harapan. Gerbang di Task 6 dan Task 12 menegakkannya.
- **Uji berjalan di SQLite** (`phpunit.xml` baris 26: `DB_CONNECTION=sqlite`, `:memory:`), **production MySQL**. `$table->enum()` di SQLite menjadi VARCHAR + CHECK constraint yang **tidak bisa diubah lewat ALTER**. Setiap migrasi yang menyentuh enum wajib menangani dua driver — pola `if (DB::getDriverName() === 'mysql')` saja **tidak cukup**, karena di SQLite constraint lamanya akan tetap menolak nilai baru dan ujinya gagal.
- **`doctrine/dbal` tidak terpasang.** `->change()` tidak tersedia. Perubahan kolom memakai SQL mentah (MySQL) atau bangun-ulang kolom (SQLite).
- **Migrasi wajib idempoten** dengan penjaga `Schema::hasColumn`, mengikuti pola `database/migrations/2026_07_25_000000_add_sales_line_and_billing_quantities_to_invoices.php`.
- **Uji ditulis gaya PHPUnit klasik** (class + method `test_*`), nama method **bahasa Indonesia**, doc comment merujuk nomor keputusan spek. Ikuti `tests/Unit/SalesLine/TotalCompositionTest.php`.
- **Jangan pernah menyentuh** `docs/design-system/13-my-jobs-manifest.md`. File itu modified sejak sebelum pekerjaan ini dan bukan bagian dari perubahan ini. Jangan commit, jangan stash, jangan discard.
- **Jangan merge ke `main`.** Jangan `push --force`.
- Nilai enum baru, persis: `fin_categories.type` → `('income','expense','asset')`. `fin_transactions.source` → `('manual','invoice','bill','advance','payroll')`.

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php` | Kategori bisa bertipe aset |
| `database/migrations/2026_07_31_000001_add_contra_account_to_fin_transactions.php` | Lawan-akun kategori + kas nullable + nilai source baru |
| `app/Models/FinCategory.php` | Scope `asset`, konstanta tipe |
| `app/Models/FinTransaction.php` | Relasi `contraCategory`, `journalLines()`, aturan tepat-satu |
| `app/Console/Commands/FinanceSnapshot.php` | Perintah `finance:snapshot` |
| `app/Http/Controllers/FinanceReportController.php` | Ekstraksi `cashFlowData()` + 5 penyesuaian laporan |
| `resources/js/Pages/Finance/Transactions.vue` | Dropdown + lencana untuk kategori aset |
| `tests/Unit/Finance/JournalLinesTest.php` | Baris jurnal dengan kategori lawan |
| `tests/Feature/Finance/ContraAccountRuleTest.php` | Aturan tepat-satu |
| `tests/Feature/Finance/AssetCategoryReportTest.php` | Kategori aset tidak masuk laba rugi |

---

### Task 1: Kategori keuangan bisa bertipe aset

**Files:**
- Create: `database/migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php`
- Modify: `app/Models/FinCategory.php`
- Test: `tests/Feature/Finance/AssetCategoryReportTest.php`

**Interfaces:**
- Produces: `FinCategory::TYPES` (array), `FinCategory::scopeAsset($q)`. Tipe `'asset'` dapat disimpan di `fin_categories.type` pada MySQL maupun SQLite.

- [ ] **Step 1: Tulis uji yang gagal**

Buat `tests/Feature/Finance/AssetCategoryReportTest.php`:

```php
<?php

namespace Tests\Feature\Finance;

use App\Models\FinCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §3.2 / D7: kategori keuangan mendapat tipe ketiga 'asset' supaya kas bon
 * bisa dibukukan sebagai Piutang Karyawan, bukan sebagai beban.
 */
class AssetCategoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_kategori_bisa_disimpan_dengan_tipe_asset(): void
    {
        $kategori = FinCategory::create([
            'name' => 'Piutang Karyawan',
            'type' => 'asset',
        ]);

        $this->assertSame('asset', $kategori->fresh()->type);
    }

    public function test_scope_asset_hanya_mengambil_kategori_aset(): void
    {
        FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        FinCategory::create(['name' => 'Penjualan Tour',   'type' => 'income']);
        FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->assertSame(['Piutang Karyawan'], FinCategory::asset()->pluck('name')->all());
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: FAIL. Di SQLite pesannya berupa pelanggaran CHECK constraint pada kolom `type`, bukan "method not found" — itu justru bukti jebakan SQLite yang disebut di Global Constraints memang nyata.

- [ ] **Step 3: Tulis migrasi**

Buat `database/migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap A — lihat docs/superpowers/specs/2026-07-31-master-karyawan-design.md §3.2.
 *
 * Kategori keuangan mendapat tipe ketiga 'asset'. Kategori bertipe asset tersedia
 * untuk KEDUA arah transaksi: 'out' menaikkan saldo aset (memberi kas bon),
 * 'in' menurunkannya.
 *
 * Dua driver ditangani berbeda dan ini disengaja:
 * - MySQL: ALTER ... MODIFY ENUM, mengikuti pola migrasi enum lain di repo ini.
 * - SQLite (dipakai uji): $table->enum() menghasilkan VARCHAR + CHECK constraint.
 *   CHECK tidak bisa diubah lewat ALTER, jadi kolomnya dibangun ulang sebagai
 *   VARCHAR polos. Tanpa ini, setiap uji yang menyimpan tipe 'asset' gagal.
 *
 * Validasi nilai pindah ke lapisan aplikasi (FinCategory::TYPES) — tempat yang
 * memang seharusnya, dan tidak menuntut migrasi ALTER tiap ada nilai baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_categories MODIFY type ENUM('income','expense','asset') NOT NULL");

            return;
        }

        if (Schema::hasColumn('fin_categories', 'type_baru')) {
            return;
        }

        Schema::table('fin_categories', function (Blueprint $table) {
            $table->string('type_baru', 20)->nullable();
        });

        DB::statement('UPDATE fin_categories SET type_baru = type');

        Schema::table('fin_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('fin_categories', function (Blueprint $table) {
            $table->renameColumn('type_baru', 'type');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_categories MODIFY type ENUM('income','expense') NOT NULL");
        }

        // SQLite: CHECK constraint asli tidak dipulihkan. Basis data uji selalu
        // dibangun dari nol, jadi tidak ada yang bergantung padanya.
    }
};
```

- [ ] **Step 4: Tambah konstanta dan scope di model**

Ubah `app/Models/FinCategory.php` — tambahkan setelah blok `$casts`:

```php
    public const TYPES = [
        'income'  => 'Pendapatan',
        'expense' => 'Beban',
        'asset'   => 'Aset',
    ];
```

dan tambahkan scope di samping dua scope yang sudah ada:

```php
    public function scopeAsset($q)   { return $q->where('type', 'asset'); }
```

- [ ] **Step 5: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: PASS, 2 uji.

- [ ] **Step 6: Buktikan ujinya bisa gagal**

Ubah sementara `scopeAsset` jadi `where('type', 'expense')`, jalankan ulang uji.
Expected: `test_scope_asset_hanya_mengambil_kategori_aset` GAGAL.
Kembalikan ke `'asset'`, jalankan ulang, pastikan hijau lagi.

Uji yang tidak pernah bisa gagal lebih berbahaya daripada tidak ada uji, karena memberi rasa aman palsu.

- [ ] **Step 7: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua. Migrasi ini menyentuh tabel yang dipakai uji lain — kalau ada yang merah, berhenti dan periksa sebelum lanjut.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php \
        app/Models/FinCategory.php \
        tests/Feature/Finance/AssetCategoryReportTest.php
git commit -m "feat(keuangan): kategori bisa bertipe aset

Prasyarat kas bon sebagai Piutang Karyawan. SQLite ditangani terpisah
karena CHECK constraint dari enum() tidak bisa diubah lewat ALTER."
```

---

### Task 2: Transaksi bisa punya lawan-akun kategori

**Files:**
- Create: `database/migrations/2026_07_31_000001_add_contra_account_to_fin_transactions.php`
- Test: dijalankan lewat Task 3

**Interfaces:**
- Produces: kolom `fin_transactions.contra_fin_category_id` (nullable, FK `fin_categories`), `fin_transactions.cash_account_id` menjadi nullable, `source` menerima `'advance'` dan `'payroll'`.

- [ ] **Step 1: Tulis migrasi**

Buat `database/migrations/2026_07_31_000001_add_contra_account_to_fin_transactions.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap A — lihat docs/superpowers/specs/2026-07-31-master-karyawan-design.md §3.2.
 *
 * Tiga perubahan pada fin_transactions:
 * 1. contra_fin_category_id — lawan transaksi boleh berupa kategori, bukan hanya
 *    akun kas. Transaksi seperti itu TIDAK menyentuh kas.
 * 2. cash_account_id jadi nullable — konsekuensi dari (1).
 * 3. source menerima 'advance' (kas bon) dan 'payroll' (gajian).
 *
 * Aturan "tepat satu dari cash_account_id / contra_fin_category_id" ditegakkan di
 * lapisan model (FinTransaction), bukan lewat CHECK constraint basis data, supaya
 * perilakunya sama di MySQL dan SQLite dan pesan galatnya bisa dibaca manusia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_transactions', 'contra_fin_category_id')) {
                $table->foreignId('contra_fin_category_id')
                    ->nullable()
                    ->after('fin_category_id')
                    ->constrained('fin_categories')
                    ->restrictOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fin_transactions MODIFY cash_account_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE fin_transactions MODIFY source ENUM('manual','invoice','bill','advance','payroll') NOT NULL DEFAULT 'manual'");

            return;
        }

        // SQLite: NOT NULL dan CHECK tidak bisa dilepas lewat ALTER, jadi kedua
        // kolom dibangun ulang. Laravel membangun ulang tabelnya sendiri saat
        // dropColumn/renameColumn di SQLite, termasuk foreign key-nya.
        if (! Schema::hasColumn('fin_transactions', 'cash_account_id_baru')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_account_id_baru')->nullable();
                $table->string('source_baru', 20)->nullable();
            });

            DB::statement('UPDATE fin_transactions SET cash_account_id_baru = cash_account_id, source_baru = source');

            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->dropColumn(['cash_account_id', 'source']);
            });

            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->renameColumn('cash_account_id_baru', 'cash_account_id');
                $table->renameColumn('source_baru', 'source');
            });
        }
    }

    public function down(): void
    {
        Schema::table('fin_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contra_fin_category_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_transactions MODIFY source ENUM('manual','invoice','bill') NOT NULL DEFAULT 'manual'");
            DB::statement('ALTER TABLE fin_transactions MODIFY cash_account_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
```

- [ ] **Step 2: Jalankan migrasi di basis data uji**

Run: `php artisan test tests/Unit/ExampleTest.php`
Expected: PASS. Uji apa pun akan menjalankan seluruh migrasi di SQLite; kalau migrasi ini rusak, uji ini gagal saat penyiapan basis data. Ini cara tercepat membuktikan migrasinya jalan di SQLite sebelum menulis kode model.

- [ ] **Step 3: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_07_31_000001_add_contra_account_to_fin_transactions.php
git commit -m "feat(keuangan): transaksi bisa berlawan kategori, bukan hanya kas

Menyiapkan baris jurnal non-kas untuk pelunasan kas bon saat gajian."
```

---

### Task 3: `journalLines()` menerima kategori lawan + aturan tepat-satu

**Files:**
- Modify: `app/Models/FinTransaction.php`
- Test: `tests/Unit/Finance/JournalLinesTest.php`, `tests/Feature/Finance/ContraAccountRuleTest.php`

**Interfaces:**
- Consumes: kolom `contra_fin_category_id` dari Task 2.
- Produces: `FinTransaction::contraCategory()` (BelongsTo), `journalLines()` yang memakai akun kas **atau** kategori lawan. Menyimpan transaksi dengan keduanya terisi, atau keduanya kosong, melempar `InvalidArgumentException`.

- [ ] **Step 1: Tulis uji jurnal yang gagal**

Buat `tests/Unit/Finance/JournalLinesTest.php`:

```php
<?php

namespace Tests\Unit\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §3.3: satu entri 3 baris dipecah jadi dua entri 2 baris. Mesin jurnal
 * tidak dirombak — hanya akun lawannya yang boleh berupa kategori.
 */
class JournalLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_kas_tetap_berlawan_akun_kas(): void
    {
        $kas   = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        $trx = FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'out',
            'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id,
            'amount'          => 3_800_000,
        ]);

        $this->assertSame([
            ['account' => 'Gaji Karyawan', 'debit' => 3_800_000.0, 'credit' => 0],
            ['account' => 'Kas Besar',     'debit' => 0,           'credit' => 3_800_000.0],
        ], $trx->journalLines());
    }

    public function test_transaksi_non_kas_berlawan_kategori(): void
    {
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $trx = FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $this->assertSame([
            ['account' => 'Gaji Karyawan',    'debit' => 1_000_000.0, 'credit' => 0],
            ['account' => 'Piutang Karyawan', 'debit' => 0,           'credit' => 1_000_000.0],
        ], $trx->journalLines());
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Unit/Finance/JournalLinesTest.php`
Expected: `test_transaksi_non_kas_berlawan_kategori` GAGAL — akun lawan terbaca `'Kas'` karena relasi `contraCategory` belum ada.

- [ ] **Step 3: Tambah relasi dan ubah `journalLines()`**

Di `app/Models/FinTransaction.php`, tambahkan relasi setelah `cashAccount()`:

```php
    public function contraCategory()
    {
        return $this->belongsTo(FinCategory::class, 'contra_fin_category_id');
    }
```

Lalu ubah `journalLines()`. Yang berubah **hanya** cara akun lawan ditentukan; arah debit/kredit tidak disentuh:

```php
    public function journalLines(): array
    {
        $lawan = $this->cashAccount?->name ?? $this->contraCategory?->name ?? 'Kas';
        $cat   = $this->category?->name ?? '-';
        $amt   = (float) $this->amount;

        return $this->direction === 'in'
            ? [
                ['account' => $lawan, 'debit' => $amt, 'credit' => 0],
                ['account' => $cat,   'debit' => 0,    'credit' => $amt],
            ]
            : [
                ['account' => $cat,   'debit' => $amt, 'credit' => 0],
                ['account' => $lawan, 'debit' => 0,    'credit' => $amt],
            ];
    }
```

Perbarui juga doc comment method itu — tambahkan satu baris: `Lawan transaksi adalah akun kas, atau kategori lawan bila transaksinya non-kas.`

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Unit/Finance/JournalLinesTest.php`
Expected: PASS, 2 uji.

- [ ] **Step 5: Tulis uji aturan tepat-satu yang gagal**

Buat `tests/Feature/Finance/ContraAccountRuleTest.php`:

```php
<?php

namespace Tests\Feature\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Spek §3.2: tepat satu dari cash_account_id / contra_fin_category_id terisi.
 * Dua-duanya terisi berarti jurnalnya ambigu; dua-duanya kosong berarti tidak
 * ada lawan sama sekali. Keduanya menghasilkan pembukuan yang tidak seimbang.
 */
class ContraAccountRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_menolak_transaksi_dengan_kas_dan_kategori_lawan_sekaligus(): void
    {
        $kas     = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->expectException(InvalidArgumentException::class);

        FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'cash_account_id'        => $kas->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);
    }

    public function test_menolak_transaksi_tanpa_lawan_sama_sekali(): void
    {
        $beban = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        $this->expectException(InvalidArgumentException::class);

        FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'out',
            'fin_category_id' => $beban->id,
            'amount'          => 1_000_000,
        ]);
    }
}
```

- [ ] **Step 6: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/ContraAccountRuleTest.php`
Expected: GAGAL keduanya — tidak ada exception yang dilempar.

- [ ] **Step 7: Tegakkan aturannya di model**

Tambahkan di `app/Models/FinTransaction.php`:

```php
    /**
     * Spek §3.2: tepat satu lawan. Ditegakkan di model, bukan lewat CHECK
     * constraint, supaya perilakunya sama di MySQL dan SQLite dan pesannya
     * terbaca manusia.
     */
    protected static function booted(): void
    {
        static::saving(function (self $trx) {
            $punyaKas   = ! is_null($trx->cash_account_id);
            $punyaLawan = ! is_null($trx->contra_fin_category_id);

            if ($punyaKas === $punyaLawan) {
                throw new \InvalidArgumentException(
                    'Transaksi keuangan harus punya tepat satu lawan: akun kas ATAU kategori lawan.'
                );
            }
        });
    }
```

- [ ] **Step 8: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/ContraAccountRuleTest.php`
Expected: PASS, 2 uji.

- [ ] **Step 9: Buktikan uji jurnal bisa gagal**

Ubah sementara urutan di cabang `'out'` `journalLines()` jadi `$lawan` lebih dulu, jalankan `php artisan test tests/Unit/Finance/JournalLinesTest.php`.
Expected: kedua uji GAGAL. Kembalikan, jalankan ulang, hijau lagi.

- [ ] **Step 10: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua. **Perhatian khusus:** aturan tepat-satu berlaku untuk SETIAP penyimpanan `FinTransaction`, termasuk yang dibuat controller invoice dan bill. Kalau ada uji lain yang merah di sini, artinya ada jalur kode yang menyimpan transaksi tanpa akun kas — temukan dan perbaiki jalurnya, jangan longgarkan aturannya.

- [ ] **Step 11: Commit**

```bash
git add app/Models/FinTransaction.php tests/Unit/Finance/JournalLinesTest.php tests/Feature/Finance/ContraAccountRuleTest.php
git commit -m "feat(keuangan): journalLines terima kategori lawan + aturan tepat-satu

Mesin jurnal 2-baris tidak dirombak; hanya akun lawannya yang boleh
berupa kategori. Satu entri 3 baris jadi dua entri 2 baris yang sah."
```

---

### Task 4: Ekstraksi `cashFlowData()` — pemindahan murni

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php:23-81`

**Interfaces:**
- Produces: `private function cashFlowData(int $year): array` yang mengembalikan persis array yang sekarang diserahkan ke `Inertia::render`.

Arus Kas satu-satunya dari enam laporan yang merakit datanya langsung di dalam method publiknya. Tanpa ekstraksi ini, `finance:snapshot` (Task 5) tidak punya cara memanggilnya tanpa lewat HTTP.

- [ ] **Step 1: Pindahkan isi `cashFlow()` ke `cashFlowData()`**

Ganti `cashFlow()` menjadi:

```php
    public function cashFlow(Request $request)
    {
        return Inertia::render('Finance/CashFlow', $this->cashFlowData((int) $request->input('year', now()->year)));
    }

    private function cashFlowData(int $year): array
    {
```

lalu **seluruh isi lama** dari `$months = [];` sampai sebelum `return Inertia::render(...)` dipindahkan apa adanya ke dalam `cashFlowData()`, dan `return Inertia::render('Finance/CashFlow', [ ... ]);` diganti `return [ ... ];` dengan isi array yang sama persis. Baris `$year = (int) $request->input(...)` dihapus karena `$year` kini parameter.

Bentuknya jadi sama dengan `ledger()`/`ledgerData()` yang sudah ada di berkas ini.

- [ ] **Step 2: Buktikan ini pemindahan murni**

Run: `git diff app/Http/Controllers/FinanceReportController.php`
Expected: tidak ada perubahan pada **logika** — hanya perpindahan baris, pergantian `Inertia::render('Finance/CashFlow', [` jadi `return [`, dan sumber `$year`. Kalau ada baris logika yang ikut berubah, kembalikan. Task ini harus tidak mengubah perilaku sama sekali, karena patokan snapshot di Task 6 diambil setelahnya.

- [ ] **Step 3: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 4: Periksa halaman Arus Kas hidup**

Run: `php artisan route:list --name=finance.cashflow`
Expected: rute terdaftar. Lalu buka `/finance/cash-flow` di browser dan pastikan grafik serta angkanya tampil seperti sebelumnya.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php
git commit -m "refactor(keuangan): ekstrak cashFlowData dari cashFlow

Pemindahan murni tanpa perubahan perilaku, menyamakan bentuk Arus Kas
dengan lima laporan lain supaya bisa dipanggil finance:snapshot."
```

---

### Task 5: Perintah `finance:snapshot`

**Files:**
- Create: `app/Console/Commands/FinanceSnapshot.php`

**Interfaces:**
- Consumes: `cashFlowData()` dari Task 4.
- Produces: perintah `php artisan finance:snapshot {tahun?} {--out=}` yang menulis JSON berisi enam laporan.

- [ ] **Step 1: Tulis perintahnya**

Buat `app/Console/Commands/FinanceSnapshot.php`:

```php
<?php

namespace App\Console\Commands;

use App\Http\Controllers\FinanceReportController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use ReflectionMethod;

/**
 * Spek §3.6 — jaring pengaman Tahap A.
 *
 * Menulis enam laporan keuangan ke satu berkas JSON supaya angkanya bisa
 * dibandingkan sebelum dan sesudah perubahan kode. Kriteria Tahap A adalah
 * TIDAK ADA satu angka pun yang berubah; perintah ini yang membuktikannya.
 *
 * Method data pada controller bersifat private, jadi dipanggil lewat refleksi —
 * teknik yang sudah dipakai di repo ini untuk menguji InvoiceController::build().
 * Alternatifnya menjadikan keenamnya public, tetapi method public pada controller
 * Laravel secara konvensi berarti aksi rute.
 */
class FinanceSnapshot extends Command
{
    protected $signature = 'finance:snapshot {tahun? : Tahun laporan, default tahun berjalan} {--out= : Path berkas keluaran}';

    protected $description = 'Tulis enam laporan keuangan ke JSON untuk dibandingkan sebelum/sesudah perubahan';

    public function handle(): int
    {
        $tahun = (int) ($this->argument('tahun') ?: now()->year);
        $out   = $this->option('out') ?: storage_path("app/finance-snapshot-{$tahun}.json");

        $controller = app(FinanceReportController::class);

        $panggil = function (string $method, ...$args) use ($controller) {
            $ref = new ReflectionMethod($controller, $method);
            $ref->setAccessible(true);

            return $ref->invoke($controller, ...$args);
        };

        $data = [
            'tahun'           => $tahun,
            'neraca'          => $panggil('balanceSheetData', $tahun),
            'laba_rugi'       => $panggil('incomeStatementData', $tahun),
            'buku_besar'      => $panggil('ledgerData', $tahun, null),
            'arus_kas'        => $panggil('cashFlowData', $tahun),
            'rekap'           => $panggil('recapData', new Request(['mode' => 'monthly', 'year' => $tahun])),
            'saldo_akun'      => $panggil('accountBalancesData'),
        ];

        file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Snapshot tahun {$tahun} ditulis ke: {$out}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Jalankan perintahnya**

Run: `php artisan finance:snapshot 2026 --out=/tmp/cek-snapshot.json`
Expected: keluar pesan sukses, berkas terbentuk.

- [ ] **Step 3: Periksa isinya masuk akal**

Run: `php -r "\$d = json_decode(file_get_contents('/tmp/cek-snapshot.json'), true); echo 'kunci: '.implode(', ', array_keys(\$d)).PHP_EOL; echo 'neraca balanced: '.var_export(\$d['neraca']['balanced'], true).PHP_EOL;"`
Expected: enam kunci laporan muncul, dan `neraca balanced` bernilai `true`. Kalau `false`, **berhenti** — Neracamu sudah tidak seimbang sebelum kode ini disentuh, dan itu harus diselesaikan lebih dulu karena seluruh gerbang Tahap A bertumpu padanya.

- [ ] **Step 4: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/FinanceSnapshot.php
git commit -m "feat(keuangan): perintah finance:snapshot

Jaring pengaman Tahap A: enam laporan ditulis ke JSON supaya angkanya
bisa dibandingkan sebelum dan sesudah perubahan."
```

---

### Task 6: GERBANG — ambil patokan sebelum laporan disentuh

Bukan tugas menulis kode. **Jangan lanjut ke Task 7 sebelum langkah ini selesai.**

Task 1–5 tidak menyentuh logika laporan sama sekali: dua migrasi, model, satu ekstraksi murni, satu perintah baru. Jadi angka pada titik ini masih mewakili perilaku asli.

- [ ] **Step 1: Ambil patokan di basis data yang berisi data nyata**

Jalankan di dev-erp (yang memakai salinan basis data production), bukan di basis data lokal yang isinya sedikit:

```bash
php artisan finance:snapshot 2026 --out=storage/app/patokan-2026.json
php artisan finance:snapshot 2025 --out=storage/app/patokan-2025.json
```

- [ ] **Step 2: Simpan patokannya di luar repo**

Salin kedua berkas ke tempat aman di luar direktori kerja (mis. `~/patokan-erp/`). Berkas ini **tidak** di-commit — isinya angka keuangan nyata.

- [ ] **Step 3: Catat bahwa patokan sudah diambil**

Konfirmasikan ke pemilik produk bahwa patokan sudah ada sebelum lanjut. Tanpa patokan, sisa Tahap A kehilangan seluruh nilai pembuktiannya.

---

### Task 7: Buku Besar membaca jenis akun dari kategori

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php` — `ledgerData()`, baris pemanggilan `$touch(...)` untuk kategori

**Interfaces:**
- Consumes: `FinCategory::TYPES` dari Task 1.

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `tests/Feature/Finance/AssetCategoryReportTest.php`:

```php
    public function test_kategori_aset_masuk_kelompok_aset_di_buku_besar(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026, null);

        $akun = collect($data['accounts'])->firstWhere('name', 'Piutang Karyawan');

        $this->assertSame('aset', $akun['group'], 'Kategori aset tidak boleh masuk kelompok beban');
        $this->assertSame(0.0, $data['profit']['expense'], 'Kas bon bukan beban, laba tidak boleh berkurang');
    }
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php --filter=buku_besar`
Expected: GAGAL — `group` terbaca `'beban'` karena ditentukan dari `direction`.

- [ ] **Step 3: Ganti penentuan kelompok akun**

Di `ledgerData()`, ganti baris:

```php
            $touch($catKey, $catName, $t->direction === 'in' ? 'pendapatan' : 'beban');
```

menjadi:

```php
            // Spek §3.4 no. 1: jenis akun dibaca dari kategorinya, bukan ditebak
            // dari arah uang. Sebelum ada kategori bertipe 'asset', kedua cara ini
            // menghasilkan hasil yang sama persis.
            $touch($catKey, $catName, match ($t->category?->type) {
                'income' => 'pendapatan',
                'asset'  => 'aset',
                default  => 'beban',
            });
```

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: PASS semua.

- [ ] **Step 5: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php tests/Feature/Finance/AssetCategoryReportTest.php
git commit -m "fix(keuangan): Buku Besar baca jenis akun dari kategori

Sebelumnya setiap transaksi keluar dianggap beban. Tanpa kategori
bertipe aset, hasilnya identik dengan sebelumnya."
```

---

### Task 8: Laba Rugi mengeluarkan kategori aset

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php` — `incomeStatementData()`, sekitar baris 473 dan 489

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `tests/Feature/Finance/AssetCategoryReportTest.php`:

```php
    public function test_kategori_aset_tidak_muncul_di_laba_rugi(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $json = json_encode($data);

        $this->assertStringNotContainsString('Piutang Karyawan', $json,
            'Kas bon adalah aset, tidak boleh tampil di Laba Rugi');
    }
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php --filter=laba_rugi`
Expected: GAGAL — "Piutang Karyawan" muncul di rincian beban operasional.

- [ ] **Step 3: Saring kategori aset**

Di `incomeStatementData()`, pada query `$opexTxns` (sekitar baris 473) dan `$otherIncome` (sekitar baris 489), tambahkan penyaring relasi:

```php
            ->whereHas('category', fn ($q) => $q->where('type', '!=', 'asset'))
```

Tambahkan komentar satu baris di atas masing-masing: `// Spek §3.4 no. 5: kategori aset bukan pendapatan/beban.`

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: PASS semua.

- [ ] **Step 5: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php tests/Feature/Finance/AssetCategoryReportTest.php
git commit -m "fix(keuangan): Laba Rugi keluarkan kategori aset"
```

---

### Task 9: Neraca — filter source, kecualikan aset, baris aset baru

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php` — `balanceSheetData()`, baris 382-383 dan blok `aset`
- Modify: `resources/js/Pages/Finance/BalanceSheet.vue`

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `tests/Feature/Finance/AssetCategoryReportTest.php`:

```php
    public function test_kas_bon_pindah_ke_aset_dan_neraca_tetap_balance(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash', 'opening_balance' => 10_000_000]);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(1_000_000.0, $data['aset']['other_assets_total'],
            'Kas bon harus muncul sebagai aset');
        $this->assertSame(0.0, $data['ekuitas']['laba_ditahan'],
            'Kas bon bukan beban, laba ditahan tidak boleh berkurang');
        $this->assertTrue($data['balanced'], 'Neraca wajib tetap seimbang');
    }
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php --filter=neraca`
Expected: GAGAL — `other_assets_total` belum ada.

- [ ] **Step 3: Ubah `balanceSheetData()`**

Ganti dua baris `manualIncome`/`manualExpense` (baris 382-383) menjadi:

```php
        // Spek §3.4 no. 2 & 3: 'manual' diganti "bukan invoice/bill" supaya nilai
        // source baru (advance, payroll) ikut terhitung — tanpa ini beban gaji
        // hilang dari laba ditahan dan Neraca berhenti balance. Kategori aset
        // dikeluarkan karena bukan pendapatan maupun beban.
        $bukanARAP = fn ($q) => $q->whereNotIn('source', ['invoice', 'bill'])
            ->whereHas('category', fn ($c) => $c->where('type', '!=', 'asset'));

        $manualIncome  = (float) FinTransaction::where('direction', 'in')
            ->where('date', '<=', $endDate)->tap($bukanARAP)->sum('amount');
        $manualExpense = (float) FinTransaction::where('direction', 'out')
            ->where('date', '<=', $endDate)->tap($bukanARAP)->sum('amount');
```

Tambahkan perhitungan aset lain sebelum `$asetTotal`:

```php
        // Spek §3.4 no. 4: saldo kategori bertipe aset (mis. Piutang Karyawan).
        // 'out' menaikkan saldo aset, 'in' menurunkannya.
        $otherAssets = FinCategory::asset()->orderBy('name')->get()->map(function ($c) use ($endDate) {
            $naik  = (float) FinTransaction::where('fin_category_id', $c->id)
                ->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
            $turun = (float) FinTransaction::where('fin_category_id', $c->id)
                ->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');

            return ['name' => $c->name, 'balance' => $naik - $turun];
        })->filter(fn ($a) => abs($a['balance']) > 0.001)->values();

        $otherAssetsTotal = (float) $otherAssets->sum('balance');
```

Ubah `$asetTotal`:

```php
        $asetTotal = $cashTotal + $ar + $totalFixedNet + $otherAssetsTotal;
```

Tambahkan ke array `'aset'` yang dikembalikan:

```php
                'other_assets'       => $otherAssets,
                'other_assets_total' => $otherAssetsTotal,
```

Pastikan `use App\Models\FinCategory;` ada di bagian atas berkas.

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: PASS semua.

- [ ] **Step 5: Tampilkan di halaman Neraca**

Di `resources/js/Pages/Finance/BalanceSheet.vue`, tambahkan baris aset lain di bagian ASET, setelah Piutang Usaha dan sebelum Aset Tetap. Ikuti markup baris yang sudah ada di sekitarnya:

```vue
<template v-for="a in aset.other_assets" :key="a.name">
    <div class="flex justify-between py-1">
        <span>{{ a.name }}</span>
        <span>{{ fmtRp(a.balance) }}</span>
    </div>
</template>
```

`fmtRp` sudah diimpor di baris 5 berkas itu (`import { fmtRp } from '@/lib/fmt'`), jadi tidak perlu impor tambahan.

- [ ] **Step 6: Bangun frontend dan periksa halaman**

Run: `npm run build`
Lalu buka `/finance/balance-sheet` dan pastikan angkanya tampil serta tulisan seimbang/tidak seimbang masih benar.

- [ ] **Step 7: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php resources/js/Pages/Finance/BalanceSheet.vue tests/Feature/Finance/AssetCategoryReportTest.php
git commit -m "fix(keuangan): Neraca tampung akun aset dan source baru

Filter source 'manual' diganti 'bukan invoice/bill' supaya nilai source
baru tidak diam-diam hilang dari laba ditahan."
```

---

### Task 10: Arus Kas, Rekap, dan saldo berjalan mengabaikan baris non-kas

**Files:**
- Modify: `app/Http/Controllers/FinanceReportController.php` — `cashFlowData()`, `recapData()`, `balanceBefore()`

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `tests/Feature/Finance/AssetCategoryReportTest.php`:

```php
    public function test_transaksi_non_kas_tidak_mempengaruhi_arus_kas(): void
    {
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'cashFlowData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(0.0, $data['totals']['expense'],
            'Baris non-kas tidak memindahkan uang, tidak boleh muncul di Arus Kas');
    }
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php --filter=arus_kas`
Expected: GAGAL — pengeluaran terbaca 1.000.000.

- [ ] **Step 3: Tambahkan penyaring di tiga tempat**

Tambahkan `->whereNotNull('cash_account_id')` pada:

1. `cashFlowData()` — dua query `$in`/`$out` di dalam perulangan bulan, dan closure `$byCategory`
2. `recapData()` — query `$txns` pada cabang mingguan, dan dua query `$in`/`$out` pada cabang bulanan
3. `balanceBefore()` — dua query `$in`/`$out`

Beri komentar satu baris di masing-masing method: `// Spek §3.4 no. 6: baris non-kas tidak memindahkan uang.`

Query saldo per akun kas (`accountBalancesData()` dan blok kas di `balanceSheetData()`) **tidak perlu diubah** — keduanya sudah menyaring `cash_account_id`, jadi baris non-kas otomatis tidak ikut.

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Finance/AssetCategoryReportTest.php`
Expected: PASS semua.

- [ ] **Step 5: Buktikan uji ini bisa gagal**

Hapus sementara `->whereNotNull('cash_account_id')` dari perulangan bulan di `cashFlowData()`, jalankan ulang uji.
Expected: `test_transaksi_non_kas_tidak_mempengaruhi_arus_kas` GAGAL. Kembalikan, hijau lagi.

- [ ] **Step 6: Jalankan SELURUH uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/FinanceReportController.php tests/Feature/Finance/AssetCategoryReportTest.php
git commit -m "fix(keuangan): Arus Kas dan Rekap abaikan baris non-kas"
```

---

### Task 11: Layar Transaksi mengenali kategori aset

**Files:**
- Modify: `resources/js/Pages/Finance/Transactions.vue:40,62,115,135-136,181,190-191`

- [ ] **Step 1: Perbaiki penyaring kategori pada form**

Baris 40 sekarang mencocokkan tipe secara persis, sehingga kategori aset tidak pernah muncul. Ganti supaya kategori aset tersedia untuk **kedua** arah:

```js
    props.categories.filter(c => c.is_active && (
        c.type === 'asset' || c.type === (form.direction === 'in' ? 'income' : 'expense')
    ))
```

Terapkan logika yang sama pada baris 62.

- [ ] **Step 2: Tambahkan daftar kategori aset**

Di samping `incomeCats` dan `expenseCats` (baris 135-136), tambahkan:

```js
const assetCats = computed(() => props.categories.filter(c => c.type === 'asset'))
```

Tampilkan daftar ini di bagian pengelolaan kategori, mengikuti markup dua daftar yang sudah ada.

- [ ] **Step 3: Beri warna ketiga pada lencana**

Baris 181 memakai kondisi dua cabang. Ganti jadi tiga cabang supaya kategori aset tidak terbaca sebagai "Keluar":

```vue
<span class="text-xs px-1.5 py-0.5 rounded mr-1"
      :class="{
        'bg-green-100 text-green-700': c.type === 'income',
        'bg-red-100 text-red-700':     c.type === 'expense',
        'bg-blue-100 text-blue-700':   c.type === 'asset',
      }">
    {{ c.type === 'income' ? 'Masuk' : c.type === 'expense' ? 'Keluar' : 'Aset' }}
</span>
```

- [ ] **Step 4: Tambahkan pilihan Aset pada dropdown tipe kategori**

Setelah baris 191, tambahkan:

```vue
<option value="asset">Aset</option>
```

- [ ] **Step 5: Bangun dan periksa di browser**

Run: `npm run build`
Lalu buka `/finance/transactions` dan pastikan: kategori bertipe aset bisa dibuat, muncul di dropdown untuk uang masuk maupun keluar, dan lencananya biru bertuliskan "Aset".

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Finance/Transactions.vue
git commit -m "feat(keuangan): layar Transaksi kenali kategori aset"
```

---

### Task 12: GERBANG — buktikan tidak ada angka yang berubah

Bukan tugas menulis kode. Ini kriteria keberhasilan seluruh Tahap A.

- [ ] **Step 1: Jalankan seluruh uji**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 2: Ambil snapshot pembanding di dev-erp**

Di mesin yang sama dan basis data yang sama dengan Task 6:

```bash
php artisan finance:snapshot 2026 --out=storage/app/banding-2026.json
php artisan finance:snapshot 2025 --out=storage/app/banding-2025.json
```

- [ ] **Step 3: Bandingkan dengan patokan**

```bash
diff <(python3 -m json.tool ~/patokan-erp/patokan-2026.json) <(python3 -m json.tool storage/app/banding-2026.json)
diff <(python3 -m json.tool ~/patokan-erp/patokan-2025.json) <(python3 -m json.tool storage/app/banding-2025.json)
```

Expected: **tidak ada keluaran sama sekali** dari kedua perintah.

Perbedaan yang boleh diterima **hanya** munculnya kunci baru `other_assets` dan `other_assets_total` di bagian `aset`, dengan nilai berupa daftar kosong dan `0`. Kunci baru bernilai nol bukan perubahan angka — belum ada kategori aset yang dibuat.

**Perbedaan angka apa pun selain itu berarti berhenti.** Jangan sesuaikan patokan agar cocok; cari sebabnya. Tersangka paling mungkin adalah Task 7 — bila di basis data ada transaksi `in` berkategori `expense` atau sebaliknya, pengelompokan Buku Besar memang akan bergeser. Kalau itu penyebabnya, laporkan ke pemilik produk: datanya yang perlu diperbaiki, bukan kodenya.

- [ ] **Step 4: Periksa keenam halaman laporan di browser**

Buka satu per satu dan bandingkan dengan ingatan/tangkapan layar sebelumnya: Arus Kas, Jurnal, Buku Besar, Rekap, Neraca, Laba Rugi, Saldo Akun. Angka tidak boleh bergeser, dan Neraca harus tetap menyatakan seimbang.

- [ ] **Step 5: Serahkan ke pemilik produk**

Laporkan hasil `diff` apa adanya. Bila bersih, minta persetujuan untuk merge `feat/akun-non-laba-rugi` → `dev` lewat agent `branch-fitur`.

**Jangan merge ke `main`.** Tahap A tidak naik production sendirian — menunggu Tahap B, lalu satu PR `dev` → `main` (spek D9).

---

## Setelah Tahap A

Tahap B (`feat/master-karyawan`) direncanakan **setelah** gerbang Task 12 lolos, bukan sekarang. Rencananya bergantung pada bentuk akhir yang benar-benar dihasilkan Tahap A — nama kolom, tanda tangan `journalLines()`, dan apa saja yang tersingkap oleh perbandingan snapshot.
