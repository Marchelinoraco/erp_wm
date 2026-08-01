# Master Karyawan, Kas Bon, dan Gajian Bulanan (Tahap B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memberi ERP master data karyawan, pencatatan kas bon sebagai piutang, dan proses gajian bulanan yang membukukan beban gaji penuh sambil melunasi kas bon lewat baris jurnal non-kas.

**Architecture:** Enam tabel baru (employees, employee_components, employee_advances, payrolls, payroll_items, payroll_item_lines) menumpang di atas mesin akuntansi Tahap A (`FinCategory.type='asset'`, `FinTransaction.contra_fin_category_id`) yang sudah live di `dev`. Draft gajian dihitung ulang setiap dibuka (tidak disimpan); hanya aksi "Bayar" yang menulis `payrolls`/`payroll_items`/`payroll_item_lines` dan jurnal, satu transaksi database.

**Tech Stack:** Laravel 12, PHPUnit 12 (bukan Pest), Inertia + Vue 3, MySQL production / SQLite uji.

**Spek:** [`docs/superpowers/specs/2026-07-31-master-karyawan-design.md`](../specs/2026-07-31-master-karyawan-design.md) §4-§9

**Branch:** `feat/master-karyawan` (sudah dibuat dari `dev` @ `4f93bdf`)

## Fakta dasar dari Tahap A yang sudah live di `dev` — WAJIB dipakai apa adanya

Tahap A selesai, di-review habis-habisan (final whole-branch review + 2 fix wave), dan sudah masuk `dev` (commit `4f93bdf`). Bentuk akhirnya, diverifikasi langsung dari kode saat rencana ini ditulis:

- **`FinCategory`**: `TYPES = ['income'=>'Pendapatan','expense'=>'Beban','asset'=>'Aset']`, scope `asset()`. Kategori **"Gaji Karyawan"** sudah ada (`type='expense'`, **`is_system=false`** — akan diubah jadi `true` di Task 1 rencana ini, lihat alasannya di sana). Kategori **"Piutang Karyawan" BELUM ADA** — harus di-seed di Task 1.
- **`FinTransaction`**: kolom `cash_account_id` (nullable), `contra_fin_category_id` (nullable, FK `fin_categories`, `restrictOnDelete`), `source` enum `('manual','invoice','bill','advance','payroll')`, `source_id` (nullable, sudah ada sejak awal — dipakai invoice/bill payment sync, **belum** dipakai payroll).
- **Aturan tepat-satu** (`FinTransaction::booted()`): tepat satu dari `cash_account_id` / `contra_fin_category_id` harus terisi, atau `InvalidArgumentException` dilempar. **Berlaku untuk SETIAP `FinTransaction::create()`, termasuk yang dibuat lewat service, bukan cuma lewat HTTP.**
- **`journalLines()`** — arah debit/kredit final:
  ```php
  $lawan = $this->cashAccount?->name ?? $this->contraCategory?->name ?? 'Kas';
  // direction='in':  [lawan debit, kategori kredit]
  // direction='out': [kategori debit, lawan kredit]
  ```
- **`other_assets`** di Neraca (`balanceSheetData()`) — rumus final (pasca fix Critical C1) membaca **kedua kolom kategori**:
  ```php
  $naikUtama  = FinTransaction::where('fin_category_id', $c->id)->where('direction','out')...->sum('amount');
  $turunUtama = FinTransaction::where('fin_category_id', $c->id)->where('direction','in')...->sum('amount');
  $turunLawan = FinTransaction::where('contra_fin_category_id', $c->id)->where('direction','out')...->sum('amount');
  $naikLawan  = FinTransaction::where('contra_fin_category_id', $c->id)->where('direction','in')...->sum('amount');
  balance = ($naikUtama + $naikLawan) - ($turunUtama + $turunLawan)
  ```
  **Konsekuensi konkret untuk Tahap B — INI YANG MENENTUKAN ARAH TRANSAKSI YANG BENAR DI TASK 3 & 6:**
  - Kas bon **diberikan** → kategori Piutang Karyawan sebagai **UTAMA** (`fin_category_id`), `direction='out'` → `$naikUtama` naik → saldo piutang **naik**. Benar.
  - Kas bon **dilunasi saat gajian** → kategori Piutang Karyawan sebagai **LAWAN** (`contra_fin_category_id`), `direction='out'` → `$turunLawan` naik → saldo piutang **turun**. Benar.
  - **Balikan arahnya (Piutang sebagai utama dengan `direction='in'`, atau sebagai lawan dengan `direction='in'`) akan SALAH secara akuntansi** — ini persis temuan N3 dari review Tahap A: *"Tahap B harus mengunci arah pasangan transaksi kontra (utama non-aset, lawan aset)"*. Task 3 dan Task 6 di rencana ini WAJIB memakai arah yang dikonfirmasi di atas, tidak boleh menebak ulang.
- **`ledgerData()`** (Buku Besar) — pasca fix N1, kategori yang sama (baik saat jadi utama maupun lawan) terakumulasi ke **satu** entri `$acc['cat-<id>']`, dengan `group` dari `match($type)` yang konsisten. Tidak perlu disentuh Tahap B — sudah benar untuk pola transaksi macam apa pun.
- **`incomeStatementData()` & `FiscalController::fiscalData()`** — keduanya sudah `whereNotIn('source', ['invoice','bill'])` + `whereHas('category', type != 'asset')` untuk `opex`/`otherIncome`. **`source='payroll'` otomatis ikut terhitung sebagai beban** tanpa perubahan apa pun di laporan — ini prasyarat yang membuat Tahap B tidak perlu menyentuh laporan sama sekali.
- **`finance:snapshot`** (`app/Console/Commands/FinanceSnapshot.php`) memanggil 6 laporan — **belum** memanggil `fiscalData()`. Di luar cakupan Tahap B untuk memperbaiki (dicatat sebagai item terpisah sebelum PR `dev→main`).

## Global Constraints

- **Seluruh fitur dibatasi role `admin`** (keputusan D3, dikonfirmasi eksplisit oleh pemilik produk). Rute dijaga `role:admin`, DAN menu disembunyikan di sidebar untuk role lain — dua lapis, bukan cuma satu.
- **Gaji = gaji pokok + komponen tetap tersimpan** (D4) — tidak ada baris bebas per bulan di versi ini (itu ditolak pemilik produk demi komponen tersimpan).
- **Gajian diproses batch bulanan** (D5) — satu `payrolls.period` unik per bulan, seluruh karyawan aktif sekaligus.
- **Kas bon dipotong lunas sekaligus, boleh diturunkan admin, sisa terbawa bulan depan** (D6).
- **Kas bon = Piutang Karyawan di Neraca** (D7) — sudah didukung penuh oleh mesin Tahap A, lihat bagian "Fakta dasar" di atas untuk arah transaksi yang benar.
- **Sisa kas bon DIHITUNG, tidak disimpan** (D10) — dari `amount` dikurangi total `payroll_item_lines` berjenis `kas_bon` yang merujuknya. Ini juga yang membuat "Batalkan" memulihkan sisa otomatis.
- **`payroll_items` menyimpan SALINAN** nama, jabatan, gaji pokok (D11) — bukan rujukan ke `employees`, supaya slip lama tidak ikut berubah saat master diedit.
- **`net_amount` pada `payroll_items` DISIMPAN** (bukan dihitung) — ini angka final yang tercetak di slip, harus beku selamanya setelah dibayar.
- **Berkas `paid` tidak bisa disunting** (D12) — satu-satunya jalan mengubahnya adalah Batalkan lalu proses ulang. Tidak ada endpoint update untuk `payroll_items`/`payroll_item_lines`.
- **Tidak ada prorata, tidak ada BPJS/PPh21/lembur otomatis** (D13) — YAGNI, di luar cakupan.
- **Draft dihitung ulang setiap dibuka, TIDAK pernah disimpan sebelum "Bayar"** — dibaca dari `docs/superpowers/specs/2026-07-31-master-karyawan-design.md` §4.4 sebagai satu-satunya sumber kebenaran (bukan mockup awal brainstorming yang sempat menyebut "Simpan Draft" — teks spek final yang disetujui pemilik produk tidak menyebutkan langkah persistensi terpisah itu). Satu-satunya cara `payrolls.status` bernilai `draft` adalah setelah "Batalkan" membalik status `paid`.
- **Bayar + jurnal dalam satu transaksi database** — `DB::transaction()`, wajib di setiap task yang menulisnya.
- Uji gaya PHPUnit klasik (class + method `test_*`), nama method bahasa Indonesia, doc comment merujuk keputusan spek (D1-D13, §4.x).
- JANGAN sentuh `docs/design-system/13-my-jobs-manifest.md`. `git add` per file, jangan `git add .`/`git add -A`.
- Jangan merge ke branch lain. Jangan `git push --force`.
- Perintah uji: `php artisan test`. Baseline saat ini (`dev` pasca-merge Tahap A): **237/237 hijau**.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php` | Seed kategori sistem + kunci "Gaji Karyawan" |
| `database/migrations/2026_08_01_000001_create_employees_table.php` | Skema karyawan |
| `database/migrations/2026_08_01_000002_create_employee_components_table.php` | Skema komponen tetap |
| `database/migrations/2026_08_01_000003_create_employee_advances_table.php` | Skema kas bon |
| `database/migrations/2026_08_01_000004_create_payrolls_table.php` | Skema periode gajian |
| `database/migrations/2026_08_01_000005_create_payroll_items_table.php` | Skema baris per karyawan |
| `database/migrations/2026_08_01_000006_create_payroll_item_lines_table.php` | Skema rincian per baris |
| `app/Models/Employee.php` | Model karyawan + relasi |
| `app/Models/EmployeeComponent.php` | Model komponen tetap |
| `app/Models/EmployeeAdvance.php` | Model kas bon + `sisa()` |
| `app/Models/Payroll.php` | Model periode gajian |
| `app/Models/PayrollItem.php` | Model baris per karyawan |
| `app/Models/PayrollItemLine.php` | Model rincian |
| `app/Services/Payroll/PayrollDraftBuilder.php` | Hitung draft (tanpa simpan) |
| `app/Services/Payroll/PayrollProcessor.php` | Bayar (persist+jurnal) & Batalkan |
| `app/Http/Controllers/EmployeeController.php` | CRUD karyawan + komponen |
| `app/Http/Controllers/EmployeeAdvanceController.php` | Kas bon: daftar + beri |
| `app/Http/Controllers/PayrollController.php` | Gajian: daftar, buka, bayar, batalkan |
| `resources/js/Pages/Employees/Index.vue` | Layar Data Master → Karyawan |
| `resources/js/Pages/Finance/EmployeeAdvances.vue` | Layar Kas Bon |
| `resources/js/Pages/Finance/Payrolls.vue` | Layar Gajian |
| `resources/js/Layouts/AuthenticatedLayout.vue` | Tambah menu (role:admin only) |
| `routes/web.php` | Rute baru, grup `role:admin` |

---

### Task 1: Seed kategori sistem + skema karyawan & komponen

**Files:**
- Create: `database/migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php`
- Create: `database/migrations/2026_08_01_000001_create_employees_table.php`
- Create: `database/migrations/2026_08_01_000002_create_employee_components_table.php`
- Create: `app/Models/Employee.php`
- Create: `app/Models/EmployeeComponent.php`
- Test: `tests/Feature/Employee/EmployeeModelTest.php`

**Interfaces:**
- Produces: kategori `FinCategory` bernama `'Piutang Karyawan'` (`type='asset'`, `is_system=true`) tersedia untuk dicari lewat `FinCategory::where('name', 'Piutang Karyawan')->first()`. Kategori `'Gaji Karyawan'` kini `is_system=true`. Model `Employee` dengan relasi `components()` (HasMany `EmployeeComponent`), `user()` (BelongsTo `User`), `advances()` (HasMany `EmployeeAdvance`, didefinisikan penuh di Task 3 tapi relasi method-nya ditulis di sini karena tabelnya sudah dikenal namanya).

- [ ] **Step 1: Tulis migrasi seed kategori**

Buat `database/migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php`:

```php
<?php

use App\Models\FinCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Tahap B §4.1 — kas bon dibukukan sebagai Piutang Karyawan (D7), dan
 * pelunasannya membebani kategori Gaji Karyawan yang sudah ada.
 *
 * Kedua kategori dikunci is_system=true supaya tidak bisa dihapus lewat
 * layar Transaksi (FinanceLedgerController::destroyCategory sudah menolak
 * kategori is_system) — mencegah payroll gagal karena kategori acuannya
 * hilang. "Gaji Karyawan" sebelumnya is_system=false; dikunci di sini karena
 * baru sekarang ia jadi kategori yang KODE (bukan cuma manusia) bergantung
 * padanya lewat pencarian nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        FinCategory::updateOrCreate(
            ['name' => 'Piutang Karyawan'],
            ['type' => 'asset', 'is_system' => true, 'sort_order' => (int) FinCategory::max('sort_order') + 1]
        );

        FinCategory::where('name', 'Gaji Karyawan')->update(['is_system' => true]);
    }

    public function down(): void
    {
        FinCategory::where('name', 'Gaji Karyawan')->update(['is_system' => false]);
        FinCategory::where('name', 'Piutang Karyawan')->where('is_system', true)->delete();
    }
};
```

- [ ] **Step 2: Tulis migrasi tabel employees**

Buat `database/migrations/2026_08_01_000001_create_employees_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 / D1-D2: karyawan berdiri sendiri dari users — tidak semua
 * yang digaji punya akun ERP. user_id nullable untuk yang kebetulan punya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->date('join_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

- [ ] **Step 3: Tulis migrasi tabel employee_components**

Buat `database/migrations/2026_08_01_000002_create_employee_components_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 / D4: komponen tetap per karyawan (tunjangan/potongan), ikut
 * otomatis di draft gajian tanpa diketik ulang tiap bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['tunjangan', 'potongan']);
            $table->decimal('amount', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_components');
    }
};
```

- [ ] **Step 4: Tulis model `Employee`**

Buat `app/Models/Employee.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'join_date'   => 'date',
        'is_active'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function components()
    {
        return $this->hasMany(EmployeeComponent::class);
    }

    public function advances()
    {
        return $this->hasMany(EmployeeAdvance::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }
}
```

- [ ] **Step 5: Tulis model `EmployeeComponent`**

Buat `app/Models/EmployeeComponent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeComponent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
```

- [ ] **Step 6: Tulis uji yang gagal**

Buat `tests/Feature/Employee/EmployeeModelTest.php`:

```php
<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.1 D1/D2/D4: karyawan berdiri sendiri, user_id opsional, komponen
 * tetap. Spek §4.1: kategori Piutang Karyawan di-seed, Gaji Karyawan dikunci.
 */
class EmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_kategori_piutang_karyawan_ter_seed_sebagai_aset_sistem(): void
    {
        $kategori = FinCategory::where('name', 'Piutang Karyawan')->first();

        $this->assertNotNull($kategori);
        $this->assertSame('asset', $kategori->type);
        $this->assertTrue((bool) $kategori->is_system);
    }

    public function test_kategori_gaji_karyawan_terkunci_is_system(): void
    {
        $kategori = FinCategory::where('name', 'Gaji Karyawan')->first();

        $this->assertNotNull($kategori);
        $this->assertTrue((bool) $kategori->is_system);
    }

    public function test_karyawan_bisa_dibuat_tanpa_akun_user(): void
    {
        $karyawan = Employee::create([
            'name'        => 'Budi Santoso',
            'position'    => 'Driver',
            'base_salary' => 4_000_000,
        ]);

        $this->assertNull($karyawan->user_id);
        $this->assertSame(4_000_000.0, (float) $karyawan->fresh()->base_salary);
    }

    public function test_komponen_tetap_terhitung_ke_karyawan_yang_benar(): void
    {
        $karyawan = Employee::create(['name' => 'Sari', 'base_salary' => 3_500_000]);

        EmployeeComponent::create([
            'employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan',
            'type' => 'tunjangan', 'amount' => 500_000,
        ]);

        $this->assertSame(500_000.0, (float) $karyawan->components()->first()->amount);
        $this->assertSame('tunjangan', $karyawan->components()->first()->type);
    }
}
```

- [ ] **Step 7: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Employee/EmployeeModelTest.php`
Expected: PASS, 4 uji.

- [ ] **Step 8: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua (237 + 4 = 241).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php \
        database/migrations/2026_08_01_000001_create_employees_table.php \
        database/migrations/2026_08_01_000002_create_employee_components_table.php \
        app/Models/Employee.php \
        app/Models/EmployeeComponent.php \
        tests/Feature/Employee/EmployeeModelTest.php
git commit -m "feat(karyawan): seed kategori Piutang Karyawan + skema karyawan/komponen

Gaji Karyawan dikunci is_system=true karena kode (bukan cuma manusia)
mulai bergantung pada nama kategori ini lewat pencarian nama."
```

---

### Task 2: Layar Data Master → Karyawan (CRUD + komponen)

**Files:**
- Create: `app/Http/Controllers/EmployeeController.php`
- Create: `resources/js/Pages/Employees/Index.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue` (icon `karyawan` saja, penautan menu di Task 8)
- Test: `tests/Feature/Employee/EmployeeControllerTest.php`

**Interfaces:**
- Consumes: `Employee`, `EmployeeComponent` (Task 1).
- Produces: rute bernama `employees.index`, `employees.store`, `employees.update`, `employees.components.store`, `employees.components.update`. Halaman Inertia `Employees/Index`.

- [ ] **Step 1: Tulis uji controller yang gagal**

Buat `tests/Feature/Employee/EmployeeControllerTest.php`:

```php
<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.3 D3: seluruh fitur karyawan dibatasi role admin.
 * Spek §5: karyawan dengan riwayat gajian tidak bisa dihapus, hanya
 * dinonaktifkan — karena itu tidak ada endpoint destroy() sama sekali,
 * hanya update() yang bisa mengubah is_active.
 */
class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    private function makeSales(): User
    {
        return User::create([
            'name' => 'Sales', 'email' => 'sales@test.local',
            'password' => bcrypt('password'), 'role' => 'sales',
        ]);
    }

    public function test_role_sales_tidak_bisa_akses_daftar_karyawan(): void
    {
        $this->actingAs($this->makeSales())->get(route('employees.index'))->assertForbidden();
    }

    public function test_admin_bisa_membuat_karyawan_beserta_komponen(): void
    {
        $resp = $this->actingAs($this->makeAdmin())->post(route('employees.store'), [
            'name' => 'Budi Santoso', 'position' => 'Driver', 'base_salary' => 4_000_000,
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['name' => 'Budi Santoso', 'base_salary' => 4_000_000]);
    }

    public function test_admin_bisa_menambah_komponen_tetap_ke_karyawan(): void
    {
        $karyawan = Employee::create(['name' => 'Sari', 'base_salary' => 3_500_000]);

        $resp = $this->actingAs($this->makeAdmin())->post(route('employees.components.store', $karyawan), [
            'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 500_000,
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_components', ['employee_id' => $karyawan->id, 'amount' => 500_000]);
    }

    public function test_karyawan_tidak_bisa_dihapus_hanya_dinonaktifkan(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('employees.destroy'));
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Employee/EmployeeControllerTest.php`
Expected: FAIL — rute `employees.*` belum terdaftar.

- [ ] **Step 3: Tulis controller**

Buat `app/Http/Controllers/EmployeeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeComponent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with('components')->orderBy('name')->get()->map(fn ($e) => [
            'id'                   => $e->id,
            'name'                 => $e->name,
            'position'             => $e->position,
            'phone'                => $e->phone,
            'email'                => $e->email,
            'base_salary'          => (float) $e->base_salary,
            'join_date'            => $e->join_date?->format('Y-m-d'),
            'bank_name'            => $e->bank_name,
            'bank_account_number'  => $e->bank_account_number,
            'bank_account_holder'  => $e->bank_account_holder,
            'is_active'            => $e->is_active,
            'notes'                => $e->notes,
            'sisa_kas_bon'         => (float) $e->advances()->get()->sum(fn ($a) => $a->sisa()),
            'components'           => $e->components->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'type' => $c->type,
                'amount' => (float) $c->amount, 'is_active' => $c->is_active,
            ])->values(),
        ]);

        return Inertia::render('Employees/Index', ['employees' => $employees]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'position'             => 'nullable|string|max:100',
            'phone'                => 'nullable|string|max:30',
            'email'                => 'nullable|email|max:150',
            'base_salary'          => 'required|numeric|min:0',
            'join_date'            => 'nullable|date',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:50',
            'bank_account_holder'  => 'nullable|string|max:150',
            'notes'                => 'nullable|string|max:1000',
        ]);

        Employee::create($data);

        return back()->with('success', 'Karyawan ditambahkan.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'position'             => 'nullable|string|max:100',
            'phone'                => 'nullable|string|max:30',
            'email'                => 'nullable|email|max:150',
            'base_salary'          => 'required|numeric|min:0',
            'join_date'            => 'nullable|date',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:50',
            'bank_account_holder'  => 'nullable|string|max:150',
            'is_active'            => 'boolean',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $employee->update($data);

        return back()->with('success', 'Karyawan diperbarui.');
    }

    public function storeComponent(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:100',
            'type'   => 'required|in:tunjangan,potongan',
            'amount' => 'required|numeric|min:1',
        ]);
        $data['sort_order'] = EmployeeComponent::where('employee_id', $employee->id)->max('sort_order') + 1;

        $employee->components()->create($data);

        return back()->with('success', 'Komponen ditambahkan.');
    }

    public function updateComponent(Request $request, Employee $employee, EmployeeComponent $component)
    {
        abort_unless($component->employee_id === $employee->id, 404);

        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'amount'    => 'required|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        $component->update($data);

        return back()->with('success', 'Komponen diperbarui.');
    }
}
```

- [ ] **Step 4: Daftarkan rute**

Di `routes/web.php`, tambahkan blok baru **setelah** grup `role:admin,accountant` yang berisi rute finance ditutup (baris `});` sekitar 306, **sebelum** penutup luar `});` di baris 307). Baca dulu file itu untuk konfirmasi nomor baris persis sebelum menyisipkan — sudah bergeser sejak rencana ini ditulis karena migrasi/task lain mungkin menambah baris di atasnya. Tempatkan:

```php
    // Master Karyawan, Kas Bon, Gajian — Tahap B, D3: khusus admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/employees',                              [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees',                             [EmployeeController::class, 'store'])->name('employees.store');
        Route::patch('/employees/{employee}',                 [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('/employees/{employee}/components',       [EmployeeController::class, 'storeComponent'])->name('employees.components.store');
        Route::patch('/employees/{employee}/components/{component}', [EmployeeController::class, 'updateComponent'])->name('employees.components.update');
    });
```

Tambahkan `use App\Http\Controllers\EmployeeController;` di bagian atas berkas, di samping import controller lain.

- [ ] **Step 5: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Employee/EmployeeControllerTest.php`
Expected: PASS, 4 uji.

- [ ] **Step 6: Tambah ikon Karyawan (icon saja, penautan menu di Task 8)**

Di `resources/js/Layouts/AuthenticatedLayout.vue`, cari objek `const ICON = { ... }`. **Sebelum** menambahkan entri baru, jalankan `grep -n "ICON\." resources/js/Layouts/AuthenticatedLayout.vue` untuk melihat SEMUA nilai path SVG yang sudah ada — repo ini pernah punya bug nyata (`mice` byte-identik dengan `users`) karena dua ikon menyalin path yang sama. Tambahkan satu entri baru:

```js
    karyawan:  `<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.16 2.16 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />`,
```

**WAJIB**: setelah menambahkan, jalankan `grep -c "20.25 14.15v4.25" resources/js/Layouts/AuthenticatedLayout.vue` — hasil harus **1** (cuma kemunculan baru ini). Kalau kamu tidak yakin path ini benar-benar unik dibanding ikon lain, jalankan pengecekan tambahan: bandingkan string path barumu satu-per-satu terhadap tiap nilai di `ICON` yang sudah ada. Kalau ternyata ada yang identik, ganti dengan path SVG outline 24×24 lain yang jelas berbeda (boleh cari di heroicons.com, gaya "briefcase"/"identification") — JANGAN commit ikon yang byte-identik dengan ikon lain, itu bug yang sudah pernah terjadi dan sudah pernah diperbaiki di file ini.

Jangan tautkan menu di langkah ini — itu Task 8, supaya bisa mengatur pembatasan `role:admin` di satu tempat untuk Karyawan+Kas Bon+Gajian sekaligus.

- [ ] **Step 7: Buat halaman Vue minimal**

Buat `resources/js/Pages/Employees/Index.vue` — ikuti pola `Loans.vue` (import `AuthenticatedLayout`, `useForm`, `fmtRp`):

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ employees: Array })

const showAddForm = ref(false)
const addForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '', notes: '',
})
function submitAdd() {
    addForm.post(route('employees.store'), {
        onSuccess: () => { addForm.reset(); showAddForm.value = false },
    })
}

const editingId = ref(null)
const editForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '',
    is_active: true, notes: '',
})
function startEdit(e) {
    editingId.value = e.id
    Object.assign(editForm, {
        name: e.name, position: e.position ?? '', phone: e.phone ?? '', email: e.email ?? '',
        base_salary: e.base_salary, join_date: e.join_date ?? '', bank_name: e.bank_name ?? '',
        bank_account_number: e.bank_account_number ?? '', bank_account_holder: e.bank_account_holder ?? '',
        is_active: e.is_active, notes: e.notes ?? '',
    })
}
function saveEdit(id) {
    editForm.patch(route('employees.update', id), { onSuccess: () => { editingId.value = null } })
}

const componentForms = {}
function componentForm(employeeId) {
    if (!componentForms[employeeId]) {
        componentForms[employeeId] = useForm({ name: '', type: 'tunjangan', amount: '' })
    }
    return componentForms[employeeId]
}
function addComponent(employeeId) {
    componentForm(employeeId).post(route('employees.components.store', employeeId), {
        onSuccess: () => { componentForm(employeeId).reset() },
    })
}
</script>

<template>
    <Head title="Karyawan" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-5xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold">Karyawan</h1>
                <button @click="showAddForm = !showAddForm" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">
                    + Tambah Karyawan
                </button>
            </div>

            <form v-if="showAddForm" @submit.prevent="submitAdd" class="border rounded p-4 grid grid-cols-2 gap-3 text-sm">
                <input v-model="addForm.name" placeholder="Nama" class="border rounded px-2 py-1" required />
                <input v-model="addForm.position" placeholder="Jabatan" class="border rounded px-2 py-1" />
                <input v-model="addForm.phone" placeholder="Telepon" class="border rounded px-2 py-1" />
                <input v-model="addForm.email" placeholder="Email" class="border rounded px-2 py-1" />
                <input v-model="addForm.base_salary" type="number" placeholder="Gaji Pokok" class="border rounded px-2 py-1" required />
                <input v-model="addForm.join_date" type="date" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_name" placeholder="Nama Bank" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_account_number" placeholder="No. Rekening" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_account_holder" placeholder="Atas Nama" class="border rounded px-2 py-1" />
                <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1.5">Simpan</button>
            </form>

            <div v-for="e in employees" :key="e.id" class="border rounded p-4 text-sm space-y-2">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-bold">{{ e.name }}</p>
                        <p class="text-gray-500">{{ e.position }} &middot; {{ fmtRp(e.base_salary) }}</p>
                    </div>
                    <div class="text-right">
                        <p v-if="e.sisa_kas_bon > 0" class="text-amber-600 font-mono text-xs">
                            Sisa kas bon: {{ fmtRp(e.sisa_kas_bon) }}
                        </p>
                        <button @click="startEdit(e)" class="text-indigo-600 text-xs">Ubah</button>
                    </div>
                </div>

                <form v-if="editingId === e.id" @submit.prevent="saveEdit(e.id)" class="grid grid-cols-2 gap-2 border-t pt-2">
                    <input v-model="editForm.name" class="border rounded px-2 py-1" />
                    <input v-model="editForm.position" class="border rounded px-2 py-1" />
                    <input v-model="editForm.base_salary" type="number" class="border rounded px-2 py-1" />
                    <label class="flex items-center gap-1">
                        <input v-model="editForm.is_active" type="checkbox" /> Aktif
                    </label>
                    <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1">Simpan</button>
                </form>

                <div class="border-t pt-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase">Komponen Tetap</p>
                    <div v-for="c in e.components" :key="c.id" class="flex justify-between text-xs py-0.5">
                        <span>{{ c.name }} ({{ c.type }})</span>
                        <span class="font-mono">{{ fmtRp(c.amount) }}</span>
                    </div>
                    <form @submit.prevent="addComponent(e.id)" class="flex gap-1 mt-1">
                        <input v-model="componentForm(e.id).name" placeholder="Nama komponen" class="border rounded px-2 py-0.5 text-xs flex-1" />
                        <select v-model="componentForm(e.id).type" class="border rounded text-xs">
                            <option value="tunjangan">Tunjangan</option>
                            <option value="potongan">Potongan</option>
                        </select>
                        <input v-model="componentForm(e.id).amount" type="number" placeholder="Nominal" class="border rounded px-2 py-0.5 text-xs w-28" />
                        <button type="submit" class="text-indigo-600 text-xs px-2">+</button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 8: Bangun frontend**

Run: `npm run build`
Expected: sukses, tidak ada error terkait `Employees/Index.vue`.

- [ ] **Step 9: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/EmployeeController.php \
        resources/js/Pages/Employees/Index.vue \
        routes/web.php \
        resources/js/Layouts/AuthenticatedLayout.vue \
        tests/Feature/Employee/EmployeeControllerTest.php
git commit -m "feat(karyawan): layar Data Master Karyawan (CRUD + komponen tetap)

Tidak ada endpoint destroy() untuk karyawan — spek §5 melarang hapus,
hanya nonaktifkan lewat toggle is_active."
```

---

### Task 3: Kas bon — skema, model, layar

**Files:**
- Create: `database/migrations/2026_08_01_000003_create_employee_advances_table.php`
- Create: `app/Models/EmployeeAdvance.php`
- Create: `app/Http/Controllers/EmployeeAdvanceController.php`
- Create: `resources/js/Pages/Finance/EmployeeAdvances.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Employee/EmployeeAdvanceTest.php`

**Interfaces:**
- Consumes: `Employee` (Task 1), `FinTransaction`/`FinCategory` (Tahap A), kategori `'Piutang Karyawan'` (Task 1).
- Produces: `EmployeeAdvance::sisa(): float` — dipakai Task 5 (`PayrollDraftBuilder`). Rute `employee-advances.index`, `employee-advances.store`, `employee-advances.destroy`.

**CATATAN ARAH TRANSAKSI — WAJIB DIBACA SEBELUM MENULIS KODE:**

Memberi kas bon = Piutang Karyawan sebagai kategori **UTAMA** (`fin_category_id`), `direction='out'`, `cash_account_id` terisi (transaksi ini KELUAR KAS sungguhan). Ini menaikkan saldo piutang di Neraca (`$naikUtama` di rumus `other_assets` yang sudah dikonfirmasi di bagian "Fakta dasar" rencana ini). **Jangan pernah membuat transaksi kas bon dengan `contra_fin_category_id`** — itu untuk pelunasan saat gajian (Task 6), bukan untuk pemberian.

- [ ] **Step 1: Tulis migrasi**

Buat `database/migrations/2026_08_01_000003_create_employee_advances_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D7/D10: kas bon adalah piutang (aset), bukan beban. Sisa
 * dihitung dari amount dikurangi payroll_item_lines yang merujuknya — TIDAK
 * disimpan di sini, supaya "Batalkan" gajian (Task 6) memulihkan sisa
 * otomatis tanpa perbaikan manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->foreignId('fin_transaction_id')->constrained('fin_transactions')->restrictOnDelete();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
```

- [ ] **Step 2: Tulis model dengan `sisa()`**

Buat `app/Models/EmployeeAdvance.php`. `PayrollItemLine` belum ada sampai Task 5 — pakai nama kelas penuh dengan pengecekan `class_exists` TIDAK diperlukan karena Task 5 akan membuat kelasnya sebelum method ini pernah benar-benar dipanggil dalam alur produksi (di Task 3 ini, `sisa()` akan selalu mengembalikan `amount` penuh karena tabel `payroll_item_lines` memang masih kosong — tidak error, sebab query `sum()` pada tabel kosong menghasilkan `0`, bukan exception). Tulis apa adanya, konsumsinya penuh baru terlihat di Task 5:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function finTransaction()
    {
        return $this->belongsTo(FinTransaction::class);
    }

    /**
     * Spek D10: sisa DIHITUNG, bukan disimpan. amount dikurangi total
     * payroll_item_lines berjenis kas_bon yang merujuk kas bon ini.
     */
    public function sisa(): float
    {
        $dipotong = PayrollItemLine::where('employee_advance_id', $this->id)->sum('amount');

        return round((float) $this->amount - (float) $dipotong, 2);
    }

    /**
     * Spek §5: kas bon yang sudah pernah dipotong tidak bisa dihapus.
     */
    public function sudahDipotong(): bool
    {
        return PayrollItemLine::where('employee_advance_id', $this->id)->exists();
    }
}
```

- [ ] **Step 3: Tulis uji yang gagal**

Buat `tests/Feature/Employee/EmployeeAdvanceTest.php`:

```php
<?php

namespace Tests\Feature\Employee;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.2 D7: kas bon = Piutang Karyawan sebagai kategori UTAMA,
 * direction='out', menyentuh kas sungguhan. Arah ini WAJIB persis begini
 * supaya saldo piutang di Neraca (other_assets) naik, bukan turun.
 */
class EmployeeAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_memberi_kas_bon_membuat_transaksi_piutang_utama_direction_out(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $resp = $this->actingAs($this->makeAdmin())->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);

        $resp->assertSessionHasNoErrors();

        $advance = $karyawan->advances()->first();
        $this->assertNotNull($advance);

        $trx = $advance->finTransaction;
        $this->assertSame('out', $trx->direction);
        $this->assertSame('Piutang Karyawan', $trx->category->name);
        $this->assertSame($kas->id, $trx->cash_account_id);
        $this->assertNull($trx->contra_fin_category_id);
        $this->assertSame('advance', $trx->source);
    }

    public function test_sisa_kas_bon_penuh_sebelum_pernah_dipotong(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $this->actingAs($this->makeAdmin())->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);

        $this->assertSame(1_000_000.0, $karyawan->advances()->first()->sisa());
    }

    public function test_kas_bon_yang_belum_dipotong_bisa_dihapus_dan_jurnal_ikut_hilang(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $this->actingAs($this->makeAdmin())->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);
        $advance = $karyawan->advances()->first();
        $trxId = $advance->fin_transaction_id;

        $this->actingAs($this->makeAdmin())->delete(route('employee-advances.destroy', $advance))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('employee_advances', ['id' => $advance->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('fin_transactions', ['id' => $trxId]);
    }
}
```

- [ ] **Step 4: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Employee/EmployeeAdvanceTest.php`
Expected: FAIL — rute `employee-advances.*` belum ada.

- [ ] **Step 5: Tulis controller**

Buat `app/Http/Controllers/EmployeeAdvanceController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeAdvanceController extends Controller
{
    public function index()
    {
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $advances = EmployeeAdvance::with('employee')->orderByDesc('date')->get()->map(fn ($a) => [
            'id'          => $a->id,
            'employee'    => $a->employee->name,
            'date'        => $a->date->format('Y-m-d'),
            'amount'      => (float) $a->amount,
            'sisa'        => $a->sisa(),
            'note'        => $a->note,
            'bisa_dihapus'=> ! $a->sudahDipotong(),
        ]);

        return Inertia::render('Finance/EmployeeAdvances', [
            'employees'    => $employees,
            'advances'     => $advances,
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'date'            => 'required|date',
            'amount'          => 'required|numeric|min:1',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'note'            => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

            $trx = FinTransaction::create([
                'date'            => $data['date'],
                'direction'       => 'out',
                'fin_category_id' => $kategori->id,
                'cash_account_id' => $data['cash_account_id'],
                'amount'          => $data['amount'],
                'description'     => 'Kas bon — ' . Employee::find($data['employee_id'])->name,
                'source'          => 'advance',
                'created_by'      => $request->user()?->name,
            ]);

            EmployeeAdvance::create([
                'employee_id'        => $data['employee_id'],
                'date'               => $data['date'],
                'amount'             => $data['amount'],
                'note'               => $data['note'] ?? null,
                'fin_transaction_id' => $trx->id,
                'created_by'         => $request->user()?->name,
            ]);
        });

        return back()->with('success', 'Kas bon dicatat.');
    }

    public function destroy(EmployeeAdvance $employeeAdvance)
    {
        abort_if($employeeAdvance->sudahDipotong(), 422, 'Kas bon sudah pernah dipotong, tidak bisa dihapus.');

        DB::transaction(function () use ($employeeAdvance) {
            $employeeAdvance->finTransaction->delete();
            $employeeAdvance->delete();
        });

        return back()->with('success', 'Kas bon dihapus.');
    }
}
```

- [ ] **Step 6: Daftarkan rute**

Di `routes/web.php`, dalam blok `Route::middleware('role:admin')->group(...)` yang dibuat Task 2, tambahkan:

```php
        Route::get('/employee-advances',            [EmployeeAdvanceController::class, 'index'])->name('employee-advances.index');
        Route::post('/employee-advances',           [EmployeeAdvanceController::class, 'store'])->name('employee-advances.store');
        Route::delete('/employee-advances/{employeeAdvance}', [EmployeeAdvanceController::class, 'destroy'])->name('employee-advances.destroy');
```

Tambahkan `use App\Http\Controllers\EmployeeAdvanceController;` di bagian atas.

- [ ] **Step 7: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Employee/EmployeeAdvanceTest.php`
Expected: PASS, 3 uji.

- [ ] **Step 8: Buat halaman Vue**

Buat `resources/js/Pages/Finance/EmployeeAdvances.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ employees: Array, advances: Array, cashAccounts: Array })

const form = useForm({
    employee_id: '', date: new Date().toISOString().slice(0, 10),
    amount: '', cash_account_id: '', note: '',
})
function submit() {
    form.post(route('employee-advances.store'), { onSuccess: () => form.reset('amount', 'note') })
}
function hapus(advance) {
    if (!advance.bisa_dihapus) return
    if (!confirm(`Hapus kas bon ${advance.employee}?`)) return
    router.delete(route('employee-advances.destroy', advance.id))
}
</script>

<template>
    <Head title="Kas Bon" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <h1 class="text-lg font-bold">Kas Bon Karyawan</h1>

            <form @submit.prevent="submit" class="border rounded p-4 grid grid-cols-2 gap-3 text-sm">
                <select v-model="form.employee_id" class="border rounded px-2 py-1" required>
                    <option value="" disabled>Pilih karyawan</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <input v-model="form.date" type="date" class="border rounded px-2 py-1" required />
                <input v-model="form.amount" type="number" placeholder="Nominal" class="border rounded px-2 py-1" required />
                <select v-model="form.cash_account_id" class="border rounded px-2 py-1" required>
                    <option value="" disabled>Dari akun kas</option>
                    <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <input v-model="form.note" placeholder="Catatan (opsional)" class="border rounded px-2 py-1 col-span-2" />
                <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1.5">Catat Kas Bon</button>
            </form>

            <table class="w-full text-sm border">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-2">Karyawan</th><th class="text-left p-2">Tanggal</th>
                        <th class="text-right p-2">Nominal</th><th class="text-right p-2">Sisa</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in advances" :key="a.id" class="border-t">
                        <td class="p-2">{{ a.employee }}</td>
                        <td class="p-2">{{ a.date }}</td>
                        <td class="p-2 text-right font-mono">{{ fmtRp(a.amount) }}</td>
                        <td class="p-2 text-right font-mono" :class="a.sisa > 0 ? 'text-amber-600' : 'text-gray-400'">
                            {{ fmtRp(a.sisa) }}
                        </td>
                        <td class="p-2 text-right">
                            <button v-if="a.bisa_dihapus" @click="hapus(a)" class="text-red-600 text-xs">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 9: Bangun frontend**

Run: `npm run build`
Expected: sukses.

- [ ] **Step 10: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_08_01_000003_create_employee_advances_table.php \
        app/Models/EmployeeAdvance.php \
        app/Http/Controllers/EmployeeAdvanceController.php \
        resources/js/Pages/Finance/EmployeeAdvances.vue \
        routes/web.php \
        tests/Feature/Employee/EmployeeAdvanceTest.php
git commit -m "feat(karyawan): kas bon — Piutang Karyawan sebagai kategori utama

Arah transaksi dikunci: direction=out, kategori Piutang Karyawan di
fin_category_id (bukan contra) — menaikkan saldo piutang di Neraca."
```

---

### Task 4: Skema gajian + `PayrollDraftBuilder` (hitung, tanpa simpan)

**Files:**
- Create: `database/migrations/2026_08_01_000004_create_payrolls_table.php`
- Create: `database/migrations/2026_08_01_000005_create_payroll_items_table.php`
- Create: `database/migrations/2026_08_01_000006_create_payroll_item_lines_table.php`
- Create: `app/Models/Payroll.php`
- Create: `app/Models/PayrollItem.php`
- Create: `app/Models/PayrollItemLine.php`
- Create: `app/Services/Payroll/PayrollDraftBuilder.php`
- Test: `tests/Unit/Payroll/PayrollDraftBuilderTest.php`

**Interfaces:**
- Consumes: `Employee::components()`, `Employee::advances()`, `EmployeeAdvance::sisa()` (Task 1 & 3).
- Produces: `PayrollDraftBuilder::build(string $period): array` — array baris per karyawan aktif, TIDAK disimpan ke database. Bentuk tiap baris:
  ```php
  [
      'employee_id' => int, 'employee_name' => string, 'position' => ?string,
      'base_salary' => float,
      'lines' => [['kind' => 'tunjangan'|'potongan', 'label' => string, 'amount' => float], ...],
      'advances' => [['employee_advance_id' => int, 'label' => string, 'sisa' => float, 'potongan' => float], ...],
      'net_amount' => float,
  ]
  ```
  Dipakai Task 5 (`PayrollProcessor::pay()`) dan Task 6 (layar).

- [ ] **Step 1: Tulis migrasi `payrolls`**

Buat `database/migrations/2026_08_01_000004_create_payrolls_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D5/D9: satu periode = satu berkas gajian. status draft hanya
 * pernah tercapai lewat "Batalkan" (§6) — pembayaran pertama langsung
 * menulis status paid, tidak ada langkah "simpan draft" terpisah (§4.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique(); // '2026-07'
            $table->enum('status', ['draft', 'paid'])->default('draft');
            $table->date('paid_date')->nullable();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
```

- [ ] **Step 2: Tulis migrasi `payroll_items`**

Buat `database/migrations/2026_08_01_000005_create_payroll_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D11: menyimpan SALINAN nama/jabatan/gaji pokok — bukan
 * merujuk employees — supaya slip lama tidak ikut berubah saat master
 * diedit. net_amount DISIMPAN (bukan dihitung), angka final yang beku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('employee_name');
            $table->string('position')->nullable();
            $table->decimal('base_salary', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
```

- [ ] **Step 3: Tulis migrasi `payroll_item_lines`**

Buat `database/migrations/2026_08_01_000006_create_payroll_item_lines_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->enum('kind', ['tunjangan', 'potongan', 'kas_bon']);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_lines');
    }
};
```

- [ ] **Step 4: Tulis tiga model**

Buat `app/Models/Payroll.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_date' => 'date'];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }
}
```

Buat `app/Models/PayrollItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'net_amount'  => 'decimal:2',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines()
    {
        return $this->hasMany(PayrollItemLine::class);
    }
}
```

Buat `app/Models/PayrollItemLine.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItemLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function item()
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function advance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
```

- [ ] **Step 5: Tulis uji `PayrollDraftBuilder` yang gagal**

Buat `tests/Unit/Payroll/PayrollDraftBuilderTest.php`:

```php
<?php

namespace Tests\Unit\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Services\Payroll\PayrollDraftBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.4/D6: draft dihitung dari keadaan LIVE (karyawan aktif, komponen,
 * kas bon belum lunas), tidak pernah disimpan. Potongan kas bon otomatis =
 * yang lebih kecil antara sisa kas bon dan gaji bersih (D6).
 */
class PayrollDraftBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function beriKasBon(Employee $karyawan, float $amount, string $date): EmployeeAdvance
    {
        $kas = CashAccount::firstOrCreate(['name' => 'Kas Besar'], ['type' => 'cash']);
        $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

        $trx = FinTransaction::create([
            'date' => $date, 'direction' => 'out', 'fin_category_id' => $kategori->id,
            'cash_account_id' => $kas->id, 'amount' => $amount, 'source' => 'advance',
        ]);

        return EmployeeAdvance::create([
            'employee_id' => $karyawan->id, 'date' => $date, 'amount' => $amount,
            'fin_transaction_id' => $trx->id,
        ]);
    }

    public function test_draft_menyertakan_gaji_pokok_dan_komponen_tetap(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000]);

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertCount(1, $draft);
        $this->assertSame(4_000_000.0, $draft[0]['base_salary']);
        $this->assertSame(4_800_000.0, $draft[0]['net_amount']); // belum ada kas bon
    }

    public function test_potongan_kas_bon_otomatis_penuh_saat_gaji_cukup(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000]);
        $this->beriKasBon($karyawan, 1_000_000, '2026-07-10');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(3_800_000.0, $draft[0]['net_amount']);
        $this->assertSame(1_000_000.0, $draft[0]['advances'][0]['potongan']);
    }

    public function test_kas_bon_dipotong_maksimal_sebesar_gaji_bersih_bukan_lebih(): void
    {
        $karyawan = Employee::create(['name' => 'Joko', 'base_salary' => 3_000_000]);
        $this->beriKasBon($karyawan, 5_000_000, '2026-07-01');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(0.0, $draft[0]['net_amount']);
        $this->assertSame(3_000_000.0, $draft[0]['advances'][0]['potongan']);
    }

    public function test_kas_bon_yang_sudah_diberi_setelah_gajian_bulan_lalu_tetap_muncul_penuh(): void
    {
        $karyawan = Employee::create(['name' => 'Sari', 'base_salary' => 3_500_000]);
        $this->beriKasBon($karyawan, 500_000, '2026-06-28');

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertSame(500_000.0, $draft[0]['advances'][0]['sisa']);
    }

    public function test_karyawan_nonaktif_tidak_masuk_draft(): void
    {
        Employee::create(['name' => 'Mantan', 'base_salary' => 3_000_000, 'is_active' => false]);

        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $this->assertCount(0, $draft);
    }
}
```

- [ ] **Step 6: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Unit/Payroll/PayrollDraftBuilderTest.php`
Expected: FAIL — `PayrollDraftBuilder` belum ada.

- [ ] **Step 7: Tulis `PayrollDraftBuilder`**

Buat `app/Services/Payroll/PayrollDraftBuilder.php`:

```php
<?php

namespace App\Services\Payroll;

use App\Models\Employee;

/**
 * Spek §4.4/D6: menghitung draft gajian dari keadaan LIVE, tidak pernah
 * menyimpan hasilnya. Dipanggil ulang setiap periode dibuka (bukan disimpan
 * ke DB) — kas bon baru otomatis ikut, angka baru dibekukan hanya saat
 * PayrollProcessor::pay() (Task 5) benar-benar menulis payroll_items.
 */
class PayrollDraftBuilder
{
    public function build(string $period): array
    {
        return Employee::where('is_active', true)
            ->with('components', 'advances')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $e) => $this->buildRow($e))
            ->values()
            ->all();
    }

    private function buildRow(Employee $e): array
    {
        $tunjangan = $e->components->where('type', 'tunjangan')->where('is_active', true);
        $potongan  = $e->components->where('type', 'potongan')->where('is_active', true);

        $tunjanganTotal = (float) $tunjangan->sum('amount');
        $potonganTotal  = (float) $potongan->sum('amount');
        $grossSebelumKasBon = max((float) $e->base_salary + $tunjanganTotal - $potonganTotal, 0.0);

        [$advanceLines, $totalPotonganKasBon] = $this->hitungPotonganKasBon($e, $grossSebelumKasBon);

        $lines = $tunjangan->map(fn ($c) => ['kind' => 'tunjangan', 'label' => $c->name, 'amount' => (float) $c->amount])
            ->concat($potongan->map(fn ($c) => ['kind' => 'potongan', 'label' => $c->name, 'amount' => (float) $c->amount]))
            ->values()->all();

        return [
            'employee_id'   => $e->id,
            'employee_name' => $e->name,
            'position'      => $e->position,
            'base_salary'   => (float) $e->base_salary,
            'lines'         => $lines,
            'advances'      => $advanceLines,
            'net_amount'    => round($grossSebelumKasBon - $totalPotonganKasBon, 2),
        ];
    }

    /**
     * D6: potongan otomatis = yang lebih kecil antara sisa kas bon dan gaji
     * bersih. Dibagi FIFO antar kas bon (yang paling lama diambil dilunasi
     * lebih dulu) bila karyawan punya lebih dari satu kas bon terbuka.
     */
    private function hitungPotonganKasBon(Employee $e, float $anggaran): array
    {
        $sisaPerKasBon = $e->advances->sortBy('date')->map(fn ($a) => [
            'advance' => $a, 'sisa' => $a->sisa(),
        ])->filter(fn ($x) => $x['sisa'] > 0.009)->values();

        $lines = [];
        $totalDipotong = 0.0;

        foreach ($sisaPerKasBon as $x) {
            if ($anggaran <= 0.009) break;

            $potongan = round(min($x['sisa'], $anggaran), 2);
            $lines[] = [
                'employee_advance_id' => $x['advance']->id,
                'label'               => 'Kas bon ' . $x['advance']->date->translatedFormat('d M Y'),
                'sisa'                => $x['sisa'],
                'potongan'            => $potongan,
            ];

            $anggaran      -= $potongan;
            $totalDipotong += $potongan;
        }

        return [$lines, $totalDipotong];
    }
}
```

- [ ] **Step 8: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Unit/Payroll/PayrollDraftBuilderTest.php`
Expected: PASS, 5 uji.

- [ ] **Step 9: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_01_000004_create_payrolls_table.php \
        database/migrations/2026_08_01_000005_create_payroll_items_table.php \
        database/migrations/2026_08_01_000006_create_payroll_item_lines_table.php \
        app/Models/Payroll.php app/Models/PayrollItem.php app/Models/PayrollItemLine.php \
        app/Services/Payroll/PayrollDraftBuilder.php \
        tests/Unit/Payroll/PayrollDraftBuilderTest.php
git commit -m "feat(gajian): skema payroll + PayrollDraftBuilder

Draft murni hitung, tidak menyimpan apa pun — kas bon dipotong FIFO
dengan anggaran dibatasi gaji bersih, tidak pernah negatif."
```

---

### Task 5: `PayrollProcessor` — Bayar (persist + jurnal) dan Batalkan

**Files:**
- Create: `app/Services/Payroll/PayrollProcessor.php`
- Test: `tests/Feature/Payroll/PayrollProcessorTest.php`

**Interfaces:**
- Consumes: `PayrollDraftBuilder::build()` (Task 4), `FinTransaction`/`FinCategory` (Tahap A).
- Produces: `PayrollProcessor::pay(string $period, array $items, int $cashAccountId, ?string $createdBy): Payroll` dan `PayrollProcessor::cancel(Payroll $payroll): void`. Dipakai Task 6 (controller).

**CATATAN ARAH TRANSAKSI PELUNASAN — WAJIB DIBACA SEBELUM MENULIS KODE:**

Pelunasan kas bon saat gajian = Piutang Karyawan sebagai kategori **LAWAN** (`contra_fin_category_id`), BUKAN utama. Kategori utamanya **Gaji Karyawan** (`fin_category_id`), `direction='out'`, `cash_account_id` **NULL** (transaksi ini TIDAK menyentuh kas — makanya disebut non-kas). Arah ini yang membuat `$turunLawan` di rumus `other_assets` Neraca terpicu, menurunkan saldo piutang. Membalik arah ini (Piutang jadi utama, atau `direction='in'`) akan salah secara akuntansi — ini persis peringatan N3 dari review Tahap A.

Dua transaksi jurnal per periode (BUKAN per karyawan — spek §4.2 eksplisit "dua transaksi untuk seluruh periode"):
1. **Gaji tunai**: `direction='out'`, `fin_category_id`=Gaji Karyawan, `cash_account_id`=terisi, `amount`=total `net_amount` seluruh karyawan. Dibuat HANYA jika totalnya `> 0`.
2. **Pelunasan kas bon**: `direction='out'`, `fin_category_id`=Gaji Karyawan, `contra_fin_category_id`=Piutang Karyawan, `cash_account_id`=NULL, `amount`=total seluruh `potongan` kas bon. Dibuat HANYA jika totalnya `> 0`.

Kedua transaksi memakai `source='payroll'`, `source_id=$payroll->id` — kolom `source_id` sudah ada di skema `fin_transactions` sejak Tahap A (dipakai invoice/bill payment sync), dipakai ulang di sini supaya `cancel()` bisa menemukan dan menghapus transaksi yang tepat tanpa kolom baru.

- [ ] **Step 1: Tulis uji yang gagal — skenario penuh spek §4.2**

Buat `tests/Feature/Payroll/PayrollProcessorTest.php`:

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Payroll;
use App\Services\Payroll\PayrollDraftBuilder;
use App\Services\Payroll\PayrollProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.2 D7/D9/D12: gajian 4.800.000 (gaji pokok 4jt + tunjangan 800rb),
 * kas bon 1jt dilunasi penuh -> dibayar tunai 3.800.000, beban gaji penuh
 * 4.800.000, Neraca tetap balance.
 */
class PayrollProcessorTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanBudi(): Employee
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000]);

        $kas = CashAccount::firstOrCreate(['name' => 'Kas Besar'], ['type' => 'cash']);
        $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();
        $trx = FinTransaction::create([
            'date' => '2026-07-10', 'direction' => 'out', 'fin_category_id' => $kategori->id,
            'cash_account_id' => $kas->id, 'amount' => 1_000_000, 'source' => 'advance',
        ]);
        EmployeeAdvance::create(['employee_id' => $karyawan->id, 'date' => '2026-07-10', 'amount' => 1_000_000, 'fin_transaction_id' => $trx->id]);

        return $karyawan;
    }

    public function test_bayar_membuat_dua_transaksi_jurnal_dengan_arah_yang_benar(): void
    {
        $this->siapkanBudi();
        $kas = CashAccount::first();
        $draft = (new PayrollDraftBuilder())->build('2026-07');

        $payroll = (new PayrollProcessor())->pay('2026-07', $draft, $kas->id, 'Admin');

        $this->assertSame('paid', $payroll->fresh()->status);

        $trxGaji = FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)
            ->whereNotNull('cash_account_id')->first();
        $this->assertNotNull($trxGaji);
        $this->assertSame(3_800_000.0, (float) $trxGaji->amount);
        $this->assertSame($kas->id, $trxGaji->cash_account_id);

        $trxPelunasan = FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)
            ->whereNull('cash_account_id')->first();
        $this->assertNotNull($trxPelunasan);
        $this->assertSame(1_000_000.0, (float) $trxPelunasan->amount);
        $this->assertSame('Gaji Karyawan', $trxPelunasan->category->name);
        $this->assertSame('Piutang Karyawan', $trxPelunasan->contraCategory->name);
    }

    public function test_neraca_tetap_balance_setelah_bayar(): void
    {
        $this->siapkanBudi();
        $kas = CashAccount::first();
        $draft = (new PayrollDraftBuilder())->build('2026-07');
        (new PayrollProcessor())->pay('2026-07', $draft, $kas->id, 'Admin');

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $neraca = $ref->invoke($controller, 2026);

        $this->assertSame(0.0, $neraca['aset']['other_assets_total']);
        $this->assertTrue($neraca['balanced']);
    }

    public function test_laba_rugi_mencatat_beban_gaji_penuh_bukan_hanya_yang_tunai(): void
    {
        $this->siapkanBudi();
        $kas = CashAccount::first();
        $draft = (new PayrollDraftBuilder())->build('2026-07');
        (new PayrollProcessor())->pay('2026-07', $draft, $kas->id, 'Admin');

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);
        $labaRugi = $ref->invoke($controller, 2026);

        $this->assertSame(4_800_000.0, $labaRugi['totalOpex']);
    }

    public function test_batalkan_menghapus_jurnal_dan_memulihkan_sisa_kas_bon(): void
    {
        $karyawan = $this->siapkanBudi();
        $kas = CashAccount::first();
        $draft = (new PayrollDraftBuilder())->build('2026-07');
        $payroll = (new PayrollProcessor())->pay('2026-07', $draft, $kas->id, 'Admin');

        (new PayrollProcessor())->cancel($payroll);

        $this->assertSame('draft', $payroll->fresh()->status);
        $this->assertSame(0, FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)->count());
        $this->assertSame(1_000_000.0, $karyawan->advances()->first()->sisa());
    }

    public function test_bayar_ulang_setelah_batalkan_membuat_jurnal_baru_bukan_duplikat(): void
    {
        $this->siapkanBudi();
        $kas = CashAccount::first();
        $processor = new PayrollProcessor();
        $builder = new PayrollDraftBuilder();

        $payroll = $processor->pay('2026-07', $builder->build('2026-07'), $kas->id, 'Admin');
        $processor->cancel($payroll);
        $payrollLagi = $processor->pay('2026-07', $builder->build('2026-07'), $kas->id, 'Admin');

        $this->assertSame($payroll->id, $payrollLagi->id, 'period unik — row yang sama diproses ulang, bukan duplikat');
        $this->assertSame(2, FinTransaction::where('source', 'payroll')->where('source_id', $payrollLagi->id)->count());
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Payroll/PayrollProcessorTest.php`
Expected: FAIL — `PayrollProcessor` belum ada.

- [ ] **Step 3: Tulis `PayrollProcessor`**

Buat `app/Services/Payroll/PayrollProcessor.php`:

```php
<?php

namespace App\Services\Payroll;

use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;

/**
 * Spek §4.2/§4.4/D9/D12: satu-satunya titik yang menulis payroll_items,
 * payroll_item_lines, dan jurnal. "Bayar" mengunci berkas (paid) — tidak
 * ada endpoint update setelahnya. Satu-satunya jalan mengubah adalah
 * cancel() lalu pay() lagi.
 */
class PayrollProcessor
{
    public function pay(string $period, array $items, int $cashAccountId, ?string $createdBy): Payroll
    {
        return DB::transaction(function () use ($period, $items, $cashAccountId, $createdBy) {
            $payroll = Payroll::updateOrCreate(
                ['period' => $period],
                ['status' => 'paid', 'paid_date' => now()->toDateString(), 'cash_account_id' => $cashAccountId, 'created_by' => $createdBy]
            );

            // Bersihkan item lama — menutup celah "Bayar setelah Batalkan"
            // (period sudah ada, item lama dari siklus sebelumnya harus diganti).
            $payroll->items()->delete();

            $totalTunai = 0.0;
            $totalPelunasanKasBon = 0.0;

            foreach ($items as $row) {
                $item = $payroll->items()->create([
                    'employee_id'   => $row['employee_id'],
                    'employee_name' => $row['employee_name'],
                    'position'      => $row['position'],
                    'base_salary'   => $row['base_salary'],
                    'net_amount'    => $row['net_amount'],
                ]);

                foreach ($row['lines'] as $l) {
                    $item->lines()->create(['kind' => $l['kind'], 'label' => $l['label'], 'amount' => $l['amount']]);
                }

                foreach ($row['advances'] as $a) {
                    if ($a['potongan'] <= 0.009) continue;

                    $item->lines()->create([
                        'kind' => 'kas_bon', 'label' => $a['label'], 'amount' => $a['potongan'],
                        'employee_advance_id' => $a['employee_advance_id'],
                    ]);
                    $totalPelunasanKasBon += $a['potongan'];
                }

                $totalTunai += $row['net_amount'];
            }

            $gajiKategori = FinCategory::where('name', 'Gaji Karyawan')->firstOrFail();

            if ($totalTunai > 0.009) {
                FinTransaction::create([
                    'date' => $payroll->paid_date, 'direction' => 'out',
                    'fin_category_id' => $gajiKategori->id, 'cash_account_id' => $cashAccountId,
                    'amount' => round($totalTunai, 2), 'description' => "Gajian {$period}",
                    'source' => 'payroll', 'source_id' => $payroll->id, 'created_by' => $createdBy,
                ]);
            }

            if ($totalPelunasanKasBon > 0.009) {
                $piutangKategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

                FinTransaction::create([
                    'date' => $payroll->paid_date, 'direction' => 'out',
                    'fin_category_id' => $gajiKategori->id, 'contra_fin_category_id' => $piutangKategori->id,
                    'amount' => round($totalPelunasanKasBon, 2), 'description' => "Pelunasan kas bon — Gajian {$period}",
                    'source' => 'payroll', 'source_id' => $payroll->id, 'created_by' => $createdBy,
                ]);
            }

            return $payroll->fresh();
        });
    }

    public function cancel(Payroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)->get()
                ->each(fn ($trx) => $trx->delete());

            $payroll->items()->delete();
            $payroll->update(['status' => 'draft', 'paid_date' => null]);
        });
    }
}
```

- [ ] **Step 4: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Payroll/PayrollProcessorTest.php`
Expected: PASS, 5 uji — termasuk **`test_neraca_tetap_balance_setelah_bayar`**, satu-satunya uji yang paling menentukan seluruh proyek: kalau ini hijau, mesin akuntansi Tahap A + logika Tahap B benar-benar bekerja sama sesuai rancangan.

- [ ] **Step 5: Buktikan uji bisa gagal — mutasi arah transaksi**

Ubah sementara di `pay()`: tukar `fin_category_id` dan `contra_fin_category_id` pada transaksi pelunasan (jadikan Piutang Karyawan sebagai UTAMA, Gaji Karyawan sebagai LAWAN). Jalankan ulang `test_neraca_tetap_balance_setelah_bayar`.
Expected: GAGAL — `balanced` menjadi `false`. Ini membuktikan arah yang benar (dari bagian "Fakta dasar" rencana ini) memang satu-satunya yang membuat Neraca seimbang.
Kembalikan kode, jalankan ulang, pastikan hijau lagi.

- [ ] **Step 6: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Payroll/PayrollProcessor.php tests/Feature/Payroll/PayrollProcessorTest.php
git commit -m "feat(gajian): PayrollProcessor — Bayar (jurnal 2-baris) + Batalkan

Arah dikunci: Gaji Karyawan utama, Piutang Karyawan lawan, non-kas.
Terbukti lewat mutasi: menukar arah membuat Neraca tidak balance."
```

---

### Task 6: Layar Gajian (daftar periode, buka, Bayar, Batalkan)

**Files:**
- Create: `app/Http/Controllers/PayrollController.php`
- Create: `resources/js/Pages/Finance/Payrolls.vue`
- Modify: `routes/web.php`
- Test: `tests/Feature/Payroll/PayrollControllerTest.php`

**Interfaces:**
- Consumes: `PayrollDraftBuilder`, `PayrollProcessor` (Task 4 & 5).
- Produces: rute `payrolls.index`, `payrolls.show`, `payrolls.pay`, `payrolls.cancel`.

- [ ] **Step 1: Tulis uji yang gagal**

Buat `tests/Feature/Payroll/PayrollControllerTest.php`:

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_buka_periode_menampilkan_draft_tanpa_menyimpan(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);

        $this->actingAs($this->makeAdmin())->get(route('payrolls.show', '2026-07'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('draft', 1));

        $this->assertDatabaseCount('payrolls', 0);
    }

    public function test_bayar_membuat_payroll_berstatus_paid(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payrolls', ['period' => '2026-07', 'status' => 'paid']);
    }

    public function test_periode_yang_sama_tidak_bisa_dibayar_dua_kali_tanpa_batalkan(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id])
            ->assertStatus(422);
    }

    public function test_batalkan_mengembalikan_status_draft(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);
        $payroll = Payroll::where('period', '2026-07')->firstOrFail();

        $this->actingAs($this->makeAdmin())->post(route('payrolls.cancel', $payroll))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', $payroll->fresh()->status);
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan GAGAL**

Run: `php artisan test tests/Feature/Payroll/PayrollControllerTest.php`
Expected: FAIL — rute `payrolls.*` belum ada.

- [ ] **Step 3: Tulis controller**

Buat `app/Http/Controllers/PayrollController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Payroll;
use App\Services\Payroll\PayrollDraftBuilder;
use App\Services\Payroll\PayrollProcessor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        $periods = Payroll::orderByDesc('period')->get(['period', 'status', 'paid_date']);

        return Inertia::render('Finance/Payrolls', [
            'periods'      => $periods,
            'currentMonth' => now()->format('Y-m'),
        ]);
    }

    public function show(string $period, PayrollDraftBuilder $builder)
    {
        $payroll = Payroll::where('period', $period)->first();

        abort_if($payroll && $payroll->status === 'paid', 409, 'Periode ini sudah dibayar — batalkan dulu untuk mengubahnya.');

        return Inertia::render('Finance/Payrolls', [
            'periods'      => Payroll::orderByDesc('period')->get(['period', 'status', 'paid_date']),
            'currentMonth' => now()->format('Y-m'),
            'period'       => $period,
            'draft'        => $builder->build($period),
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function pay(Request $request, string $period, PayrollDraftBuilder $builder, PayrollProcessor $processor)
    {
        $existing = Payroll::where('period', $period)->first();
        abort_if($existing && $existing->status === 'paid', 422, 'Periode ini sudah dibayar.');

        $data = $request->validate(['cash_account_id' => 'required|exists:cash_accounts,id']);

        $draft = $request->has('items') ? $request->input('items') : $builder->build($period);

        $processor->pay($period, $draft, $data['cash_account_id'], $request->user()?->name);

        return redirect()->route('payrolls.index')->with('success', "Gajian {$period} dibayar.");
    }

    public function cancel(Payroll $payroll, PayrollProcessor $processor)
    {
        $processor->cancel($payroll);

        return back()->with('success', 'Gajian dibatalkan, kembali ke draft.');
    }
}
```

- [ ] **Step 4: Daftarkan rute**

Di `routes/web.php`, dalam blok `Route::middleware('role:admin')` yang sama, tambahkan:

```php
        Route::get('/payrolls',                 [PayrollController::class, 'index'])->name('payrolls.index');
        Route::get('/payrolls/{period}',         [PayrollController::class, 'show'])->name('payrolls.show')->where('period', '\d{4}-\d{2}');
        Route::post('/payrolls/{period}/pay',    [PayrollController::class, 'pay'])->name('payrolls.pay')->where('period', '\d{4}-\d{2}');
        Route::post('/payrolls/{payroll}/cancel',[PayrollController::class, 'cancel'])->name('payrolls.cancel');
```

Tambahkan `use App\Http\Controllers\PayrollController;`.

- [ ] **Step 5: Jalankan uji, pastikan LULUS**

Run: `php artisan test tests/Feature/Payroll/PayrollControllerTest.php`
Expected: PASS, 4 uji.

- [ ] **Step 6: Buat halaman Vue**

Buat `resources/js/Pages/Finance/Payrolls.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    periods: Array, currentMonth: String,
    period: { type: String, default: null },
    draft: { type: Array, default: () => [] },
    cashAccounts: { type: Array, default: () => [] },
})

function bukaPeriode(p) {
    router.get(route('payrolls.show', p))
}

const payForm = useForm({ cash_account_id: '' })
function bayar() {
    if (!confirm(`Bayar gajian periode ${props.period}? Setelah dibayar, berkas terkunci.`)) return
    payForm.post(route('payrolls.pay', props.period))
}

function batalkan(period) {
    const p = props.periods.find(x => x.period === period)
    if (!p) return
    if (!confirm(`Batalkan gajian ${period}? Jurnal akan dihapus, status kembali draft.`)) return
    router.post(route('payrolls.cancel', p.id ?? period))
}

const totalNet = computed(() => props.draft.reduce((s, r) => s + r.net_amount, 0))
</script>

<template>
    <Head title="Gajian" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold">Gajian</h1>
                <button @click="bukaPeriode(currentMonth)" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">
                    Buka Periode {{ currentMonth }}
                </button>
            </div>

            <table class="w-full text-sm border">
                <thead class="bg-gray-50"><tr><th class="text-left p-2">Periode</th><th class="p-2">Status</th><th class="p-2"></th></tr></thead>
                <tbody>
                    <tr v-for="p in periods" :key="p.period" class="border-t">
                        <td class="p-2">{{ p.period }}</td>
                        <td class="p-2 text-center">
                            <span :class="p.status === 'paid' ? 'text-green-600' : 'text-gray-400'">{{ p.status }}</span>
                        </td>
                        <td class="p-2 text-right space-x-2">
                            <button @click="bukaPeriode(p.period)" class="text-indigo-600 text-xs">Buka</button>
                            <button v-if="p.status === 'paid'" @click="batalkan(p.period)" class="text-red-600 text-xs">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="period" class="border rounded p-4 space-y-3">
                <h2 class="font-bold">Draft Gajian — {{ period }}</h2>
                <table class="w-full text-sm">
                    <thead><tr>
                        <th class="text-left p-1">Karyawan</th><th class="text-right p-1">Pokok</th>
                        <th class="text-right p-1">Kas Bon</th><th class="text-right p-1">Dibayar</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in draft" :key="r.employee_id" class="border-t">
                            <td class="p-1">{{ r.employee_name }}</td>
                            <td class="p-1 text-right font-mono">{{ fmtRp(r.base_salary) }}</td>
                            <td class="p-1 text-right font-mono text-amber-600">
                                {{ fmtRp(r.advances.reduce((s,a)=>s+a.potongan,0)) }}
                            </td>
                            <td class="p-1 text-right font-mono font-bold">{{ fmtRp(r.net_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot><tr class="border-t font-bold"><td class="p-1">TOTAL</td><td></td><td></td>
                        <td class="p-1 text-right font-mono">{{ fmtRp(totalNet) }}</td>
                    </tr></tfoot>
                </table>

                <div class="flex gap-2 items-center pt-2 border-t">
                    <select v-model="payForm.cash_account_id" class="border rounded px-2 py-1 text-sm">
                        <option value="" disabled>Bayar dari akun kas</option>
                        <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <button @click="bayar" class="bg-green-600 text-white rounded px-4 py-1.5 text-sm">Bayar Semua</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 7: Bangun frontend**

Run: `npm run build`
Expected: sukses.

- [ ] **Step 8: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/PayrollController.php \
        resources/js/Pages/Finance/Payrolls.vue \
        routes/web.php \
        tests/Feature/Payroll/PayrollControllerTest.php
git commit -m "feat(gajian): layar Gajian — buka periode, Bayar, Batalkan

Buka periode tidak menyimpan apa pun (murni GET+hitung). Bayar dan
Batalkan satu-satunya aksi yang menulis ke database."
```

---

### Task 7: Navigasi sidebar — Karyawan, Kas Bon, Gajian (role:admin only)

**Files:**
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Test: `tests/Feature/Employee/SidebarVisibilityTest.php` (Inertia props, bukan Vue unit test — memverifikasi lewat rute yang sudah ada di Task 2/3/6, bukan menguji Vue langsung)

**Interfaces:**
- Consumes: rute `employees.index`, `employee-advances.index`, `payrolls.index` (Task 2, 3, 6).

- [ ] **Step 1: Tulis uji rute yang gagal untuk role selain admin**

Buat `tests/Feature/Employee/SidebarVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §4.3 D3: seluruh fitur Gaji dibatasi role admin, DUA lapis — rute
 * (sudah diuji Task 2/3/6) dan tampilan menu (diuji di sini secara tidak
 * langsung: accountant yang BISA lihat Keuangan tetap TIDAK BISA akses
 * rute Gaji, membuktikan middleware role:admin benar-benar independen
 * dari middleware role:admin,accountant milik Keuangan).
 */
class SidebarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => "{$role}@test.local",
            'password' => bcrypt('password'), 'role' => $role,
        ]);
    }

    public function test_accountant_bisa_akses_keuangan_tapi_tidak_gaji(): void
    {
        $accountant = $this->makeUser('accountant');

        $this->actingAs($accountant)->get(route('finance.transactions'))->assertOk();
        $this->actingAs($accountant)->get(route('employees.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('employee-advances.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('payrolls.index'))->assertForbidden();
    }

    public function test_admin_bisa_akses_ketiganya(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get(route('employees.index'))->assertOk();
        $this->actingAs($admin)->get(route('employee-advances.index'))->assertOk();
        $this->actingAs($admin)->get(route('payrolls.index'))->assertOk();
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan LULUS langsung**

Run: `php artisan test tests/Feature/Employee/SidebarVisibilityTest.php`
Expected: PASS — middleware `role:admin` dari Task 2/3/6 sudah menegakkan ini di level rute. Uji ini adalah uji REGRESI untuk memastikan langkah sidebar (murni tampilan) tidak diam-diam melonggarkan proteksi backend yang sudah ada. Kalau GAGAL di titik ini, ada yang salah di rute Task 2/3/6 — perbaiki DI SANA, bukan di sini.

- [ ] **Step 3: Tambahkan "Karyawan" ke Data Master, khusus role admin**

Di `resources/js/Layouts/AuthenticatedLayout.vue`, cari blok:

```js
        const dataItems = [
            { label: 'Customers',       route: 'customers.index',       match: 'customers.*',       icon: ICON.customers },
            { label: 'Produk',          route: 'products.index',        match: 'products.*',        icon: ICON.products },
            { label: 'Suppliers',       route: 'suppliers.index',       match: 'suppliers.*',       icon: ICON.suppliers },
            { label: 'Channel Manager', route: 'channel-manager.index', match: 'channel-manager.*', icon: ICON.channel },
```

`dataItems` dipush untuk role `admin` MAUPUN `sales` (blok `if (role === 'admin' || role === 'sales')`), tapi Karyawan cuma boleh admin (D3). Tambahkan kondisional SETELAH array literal itu, SEBELUM baris komentar "Rekening SENGAJA tidak di sini":

```js
        const dataItems = [
            { label: 'Customers',       route: 'customers.index',       match: 'customers.*',       icon: ICON.customers },
            { label: 'Produk',          route: 'products.index',        match: 'products.*',        icon: ICON.products },
            { label: 'Suppliers',       route: 'suppliers.index',       match: 'suppliers.*',       icon: ICON.suppliers },
            { label: 'Channel Manager', route: 'channel-manager.index', match: 'channel-manager.*', icon: ICON.channel },
        ]

        // Karyawan khusus admin (spek Tahap B D3) — role sales tidak boleh
        // lihat menu ini meski keduanya sama-sama dapat grup Data Master.
        if (role === 'admin') {
            dataItems.push({ label: 'Karyawan', route: 'employees.index', match: 'employees.*', icon: ICON.karyawan })
        }
```

(Baris komentar "Rekening SENGAJA tidak di sini..." dan `groups.push({ label: 'Data Master', items: dataItems })` yang sudah ada tetap di tempatnya — tidak perlu dipindah, cukup disisipi kondisional di atas sebelum baris `groups.push`.)

- [ ] **Step 4: Tambahkan sub-judul "Gaji" ke Keuangan, khusus role admin**

Cari blok Keuangan:

```js
        groups.push({ label: 'Keuangan', items: [
            { label: 'Invoice & Tagihan', route: 'finance.index', match: ['finance.index', 'finance.tour'], icon: ICON.invoice },
            { label: 'Transaksi', route: 'finance.transactions', match: 'finance.transactions', icon: ICON.transaksi },
            { label: 'Arus Kas',  route: 'finance.cashflow',     match: 'finance.cashflow', icon: ICON.cashflow },

            { sub: 'Laporan' },
            ...
            { label: 'Hutang',     route: 'finance.loans',            match: 'finance.loans',            icon: ICON.hutang },
        ]})
```

Ini dipush untuk role `admin` MAUPUN `accountant` (blok `if (role === 'admin' || role === 'accountant')`), tapi Gaji cuma boleh admin. Ganti jadi array yang dibangun dulu, baru kondisional menambah sub-judul + 2 item SEBELUM di-push:

```js
        const keuanganItems = [
            { label: 'Invoice & Tagihan', route: 'finance.index', match: ['finance.index', 'finance.tour'], icon: ICON.invoice },
            { label: 'Transaksi', route: 'finance.transactions', match: 'finance.transactions', icon: ICON.transaksi },
            { label: 'Arus Kas',  route: 'finance.cashflow',     match: 'finance.cashflow', icon: ICON.cashflow },

            { sub: 'Laporan' },
            { label: 'Jurnal',     route: 'finance.journal',          match: 'finance.journal',          icon: ICON.jurnal },
            { label: 'Buku Besar', route: 'finance.ledger',           match: 'finance.ledger',           icon: ICON.ledger },
            { label: 'Laba Rugi',  route: 'finance.income-statement', match: 'finance.income-statement', icon: ICON.profit },
            { label: 'Neraca',     route: 'finance.balance-sheet',    match: 'finance.balance-sheet',    icon: ICON.neraca },
            { label: 'Rekap',      route: 'finance.recap',            match: 'finance.recap',            icon: ICON.rekap },
            { label: 'Fiskal',     route: 'finance.fiscal',           match: 'finance.fiscal',           icon: ICON.fiskal },

            { sub: 'Akun & Aset' },
            { label: 'Saldo Akun', route: 'finance.account-balances', match: 'finance.account-balances', icon: ICON.balances },
            { label: 'Rekening',   route: 'bank-accounts.index',      match: 'bank-accounts.*',          icon: ICON.rekening },
            { label: 'Aset Tetap', route: 'finance.fixed-assets',     match: 'finance.fixed-assets',     icon: ICON.aset },
            { label: 'Hutang',     route: 'finance.loans',            match: 'finance.loans',            icon: ICON.hutang },
        ]

        // Gaji khusus admin (spek Tahap B D3) — accountant tetap dapat
        // seluruh Keuangan lain, tapi tidak sub-judul ini.
        if (role === 'admin') {
            keuanganItems.push(
                { sub: 'Gaji' },
                { label: 'Kas Bon', route: 'employee-advances.index', match: 'employee-advances.*', icon: ICON.kasbon },
                { label: 'Gajian',  route: 'payrolls.index',          match: 'payrolls.*',           icon: ICON.gajian },
            )
        }

        groups.push({ label: 'Keuangan', items: keuanganItems })
```

- [ ] **Step 5: Tambahkan 2 ikon baru**

Di objek `ICON`, tambahkan dua entri (di samping `karyawan` dari Task 2):

```js
    kasbon: `<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />`,
    gajian: `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />`,
```

**WAJIB, sama seperti Task 2 Step 6**: jalankan `grep` untuk memastikan kedua path baru ini TIDAK byte-identik dengan entri `ICON` lain yang sudah ada (termasuk `karyawan` dari Task 2, dan `ICON.rekening`/`ICON.balances` yang sudah dipakai grup Keuangan). Kalau ada tabrakan, ganti dengan path SVG outline 24×24 lain yang jelas berbeda.

- [ ] **Step 6: Bangun frontend**

Run: `npm run build`
Expected: sukses.

- [ ] **Step 7: Jalankan SELURUH suite**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 8: Verifikasi manual di browser**

Login sebagai admin: pastikan "Karyawan" muncul di Data Master, "Kas Bon"+"Gajian" muncul di bawah sub-judul "Gaji" di Keuangan. Login sebagai accountant: pastikan Keuangan tetap muncul TAPI tanpa sub-judul "Gaji". Login sebagai sales: pastikan Data Master muncul TAPI tanpa "Karyawan".

- [ ] **Step 9: Commit**

```bash
git add resources/js/Layouts/AuthenticatedLayout.vue tests/Feature/Employee/SidebarVisibilityTest.php
git commit -m "feat(karyawan): navigasi sidebar Karyawan/Kas Bon/Gajian, khusus admin

Dua lapis proteksi: rute role:admin (Task 2/3/6) + menu disembunyikan
untuk role lain, bukan cuma rute yang ditutup."
```

---

### Task 8: GERBANG — acceptance test end-to-end + verifikasi tidak ada regresi laporan

Bukan tugas menulis kode baru. Menjahit seluruh Tahap B jadi satu bukti utuh, dan memastikan Tahap A tidak diam-diam kebobolan oleh kode baru.

**Files:**
- Create: `tests/Feature/Payroll/PayrollAcceptanceTest.php`

- [ ] **Step 1: Tulis acceptance test lewat HTTP penuh (bukan model langsung)**

Buat `tests/Feature/Payroll/PayrollAcceptanceTest.php` — skenario spek §4.2 dari ujung ke ujung, lewat rute sungguhan seperti yang akan dipakai admin:

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance test — skenario penuh spek §4.2, LEWAT HTTP (bukan manipulasi
 * model langsung), meniru urutan aksi admin sungguhan:
 * 1. Buat karyawan + komponen tetap.
 * 2. Beri kas bon.
 * 3. Buka periode gajian, lihat draft.
 * 4. Bayar.
 * 5. Verifikasi: Neraca balance, Laba Rugi penuh, Buku Besar satu baris
 *    bersaldo nol untuk Piutang Karyawan.
 * 6. Batalkan, verifikasi semuanya pulih.
 */
class PayrollAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    public function test_skenario_lengkap_kas_bon_dan_gajian_dari_ujung_ke_ujung(): void
    {
        $admin = $this->actingAs($this->makeAdmin());
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        // 1. Karyawan + komponen
        $admin->post(route('employees.store'), [
            'name' => 'Budi Santoso', 'position' => 'Driver', 'base_salary' => 4_000_000,
        ])->assertSessionHasNoErrors();
        $karyawan = Employee::where('name', 'Budi Santoso')->firstOrFail();

        $admin->post(route('employees.components.store', $karyawan), [
            'name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 800_000,
        ])->assertSessionHasNoErrors();

        // 2. Kas bon
        $admin->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ])->assertSessionHasNoErrors();

        // 3. Buka periode — pastikan draft sudah mencerminkan kas bon
        $admin->get(route('payrolls.show', '2026-07'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('draft.0.net_amount', 3_800_000.0)
                ->where('draft.0.advances.0.potongan', 1_000_000.0)
            );

        // 4. Bayar
        $admin->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id])
            ->assertSessionHasNoErrors();

        // 5. Verifikasi laporan
        $controller = app(\App\Http\Controllers\FinanceReportController::class);

        $refNeraca = new \ReflectionMethod($controller, 'balanceSheetData');
        $refNeraca->setAccessible(true);
        $neraca = $refNeraca->invoke($controller, 2026);
        $this->assertTrue($neraca['balanced'], 'Neraca harus tetap balance setelah gajian dengan kas bon');
        $this->assertSame(0.0, $neraca['aset']['other_assets_total'], 'Piutang lunas -> saldo nol');

        $refLabaRugi = new \ReflectionMethod($controller, 'incomeStatementData');
        $refLabaRugi->setAccessible(true);
        $labaRugi = $refLabaRugi->invoke($controller, 2026);
        $this->assertSame(4_800_000.0, $labaRugi['totalOpex'], 'Beban gaji harus penuh, termasuk yang dilunasi lewat kas bon');

        $refBukuBesar = new \ReflectionMethod($controller, 'ledgerData');
        $refBukuBesar->setAccessible(true);
        $bukuBesar = $refBukuBesar->invoke($controller, 2026, null);
        $piutangEntries = collect($bukuBesar['accounts'])->where('name', 'Piutang Karyawan');
        $this->assertCount(1, $piutangEntries, 'Piutang Karyawan harus SATU baris, bukan pecah dua (regresi N1 Tahap A)');
        $this->assertSame(0.0, $piutangEntries->first()['balance']);

        // 6. Batalkan — semuanya pulih
        $payroll = Payroll::where('period', '2026-07')->firstOrFail();
        $admin->post(route('payrolls.cancel', $payroll))->assertSessionHasNoErrors();

        $this->assertSame('draft', $payroll->fresh()->status);
        $this->assertSame(1_000_000.0, $karyawan->fresh()->advances()->first()->sisa(), 'sisa kas bon pulih tanpa perbaikan manual (D10)');
    }
}
```

- [ ] **Step 2: Jalankan acceptance test**

Run: `php artisan test tests/Feature/Payroll/PayrollAcceptanceTest.php`
Expected: PASS, 1 uji — tapi uji ini menjahit SEMUA task (1-7) jadi satu jalur nyata; kalau merah, telusuri ke task mana pun yang relevan dengan pesan galatnya, JANGAN melonggarkan assertion di sini.

- [ ] **Step 3: Jalankan SELURUH suite Tahap B + Tahap A**

Run: `php artisan test`
Expected: PASS semua. Baseline sebelum Tahap B (`dev` pasca-merge Tahap A): 237. Setelah seluruh Tahap B: seharusnya jauh lebih banyak (setiap task menambah beberapa uji) — laporkan angka pastinya.

- [ ] **Step 4: Verifikasi manual sisa checklist spek §5 yang belum tertutup uji otomatis**

Baca ulang `docs/superpowers/specs/2026-07-31-master-karyawan-design.md` §5 dan §6, cocokkan tiap baris dengan task yang menutupinya:

| Aturan §5/§6 | Tertutup di |
|---|---|
| Potongan otomatis = lebih kecil (sisa, gaji bersih) | Task 4, `test_kas_bon_dipotong_maksimal_sebesar_gaji_bersih_bukan_lebih` |
| `payrolls.period` unik | Skema Task 4 (`unique()`), `test_bayar_ulang_setelah_batalkan...` Task 5 |
| Berkas `paid` tidak bisa disunting | Tidak ada endpoint update `payroll_items`/`payroll_item_lines` (Task 5/6 — verifikasi lagi tidak ada rute semacam itu) |
| Kas bon sudah dipotong tidak bisa dihapus | Task 3 `sudahDipotong()` + guard di `destroy()` — **belum ada uji eksplisit untuk kasus SUDAH dipotong**, tambahkan sekarang: |

Tambahkan satu uji terakhir ke `tests/Feature/Employee/EmployeeAdvanceTest.php`:

```php
    public function test_kas_bon_yang_sudah_dipotong_tidak_bisa_dihapus(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan', 'type' => 'tunjangan', 'amount' => 800_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $this->actingAs($this->makeAdmin())->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);
        $advance = $karyawan->advances()->first();

        (new \App\Services\Payroll\PayrollProcessor())->pay(
            '2026-07', (new \App\Services\Payroll\PayrollDraftBuilder())->build('2026-07'), $kas->id, 'Admin'
        );

        $this->actingAs($this->makeAdmin())->delete(route('employee-advances.destroy', $advance))
            ->assertStatus(422);
    }
```

Perlu `use App\Models\EmployeeComponent;` dan `use App\Models\CashAccount;` — pastikan sudah ada di bagian atas file test itu, tambahkan kalau belum.

- [ ] **Step 5: Jalankan SELURUH suite sekali lagi**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Payroll/PayrollAcceptanceTest.php tests/Feature/Employee/EmployeeAdvanceTest.php
git commit -m "test(gajian): acceptance test end-to-end skenario §4.2 + kas bon terpotong tak bisa dihapus

Menjahit seluruh Tahap B jadi satu jalur HTTP nyata: buat karyawan,
beri kas bon, bayar gajian, verifikasi Neraca/Laba Rugi/Buku Besar,
batalkan, verifikasi pulih."
```

---

## Self-Review (dilakukan penulis rencana, bukan subagent)

**Cakupan spek:** D1-D2 (Task 1), D3 (Task 2/3/6/7), D4 (Task 1/4), D5 (Task 4/5/6), D6 (Task 4, potongan FIFO), D7 (Task 3/5, arah transaksi eksplisit dengan bukti mutasi), D9 (Task 5, `period` unik), D10 (Task 3, `sisa()` dihitung bukan disimpan), D11 (Task 4, salinan di `payroll_items`), D12 (Task 5, tidak ada endpoint update), D13 (tidak ada task prorata/BPJS — sesuai, sengaja tidak dikerjakan). §5 aturan sistem — tabel penutup di Task 8. §6 penanganan kesalahan (Batalkan, kas bon dihapus) — Task 3 & 5. §7.2 skenario uji — semuanya tercermin di Task 4/5/8 dengan nilai yang sama persis dengan tabel spek.

**Placeholder scan:** Tidak ditemukan "TBD"/"tangani nanti". Satu pengecualian yang disengaja dan dijelaskan: Task 3 Step 2 mencatat bahwa `sisa()` akan selalu mengembalikan `amount` penuh sampai Task 5 ada — itu FAKTA tentang urutan tugas, bukan pekerjaan yang ditunda tanpa penjelasan.

**Konsistensi tipe:** `PayrollDraftBuilder::build()` mengembalikan array dengan struktur yang dipakai identik oleh `PayrollProcessor::pay()` (Task 5), `PayrollController::show()` (Task 6), dan `PayrollAcceptanceTest` (Task 8) — kunci `employee_id`, `employee_name`, `position`, `base_salary`, `lines`, `advances`, `net_amount` dipakai sama di keempat tempat, diperiksa ulang saat menulis rencana ini.

## Setelah Tahap B

Sebelum PR `dev` → `main`:
1. Tambahkan `fiscalData()` sebagai laporan ke-7 di `finance:snapshot` (rekomendasi terbuka dari review Tahap A) — cek dulu apakah masih relevan mengingat Tahap B sekarang membuat `fiscalData()` benar-benar dipakai kode produksi (kategori aset kini punya data nyata).
2. Jalankan `finance:snapshot` di dev-erp (salinan production) sebelum dan sesudah Tahap B, sama seperti gerbang Task 6/12 Tahap A — kali ini untuk membuktikan laporan **selain** yang disentuh langsung (Arus Kas, Rekap, Saldo Akun) juga tidak berubah untuk data yang sudah ada.
3. Satu PR `dev` → `main` mencakup KEDUA tahap sekaligus (D9 Tahap A).

---

## Gerbang finance:snapshot sebelum dev→main (2026-08-08)

Dibandingkan patokan-2025/2026.json (diambil 31 Jul, akhir Tahap A) dengan
snapshot baru sesudah Tahap B (data contoh sudah dibersihkan dulu).

Hasil: 7 baris beda untuk KEDUA tahun, seluruhnya expected:
- `other_assets: []` + `other_assets_total: 0` (kunci baru skema, kosong)
- `rekap.month` "2026-07"->"2026-08" (label bulan berjalan, bukan angka uang)

Nol angka keuangan bergeser. dev-erp adalah salinan statis (tidak
tersinkron kontinu), jadi tidak ada drift data produksi antara 31 Jul dan
sekarang selain yang sengaja diubah di dev-erp sendiri (sudah dibersihkan).

GERBANG LOLOS. Siap PR dev->main.
