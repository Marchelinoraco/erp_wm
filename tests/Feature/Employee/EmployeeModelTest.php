<?php

namespace Tests\Feature\Employee;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\FinCategory;
use App\Models\FinTransaction;
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

    public function test_down_migrasi_menolak_rollback_jika_ada_transaksi_kas_bon(): void
    {
        $piutang = FinCategory::where('name', 'Piutang Karyawan')->first();
        $kas = CashAccount::create(['name' => 'Kas Test', 'type' => 'cash']);

        // Buat transaksi yang merujuk kategori Piutang Karyawan via fin_category_id
        FinTransaction::create([
            'date'            => '2026-08-01',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $migration = require database_path('migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('masih ada 1 transaksi yang merujuk kategori Piutang Karyawan');

        $migration->down();
    }

    public function test_down_migrasi_menolak_rollback_jika_ada_transaksi_contra_fin_category(): void
    {
        $piutang = FinCategory::where('name', 'Piutang Karyawan')->first();
        $gaji = FinCategory::where('name', 'Gaji Karyawan')->first();

        // Buat transaksi yang merujuk kategori Piutang Karyawan via contra_fin_category_id
        FinTransaction::create([
            'date'                   => '2026-08-01',
            'direction'              => 'out',
            'fin_category_id'        => $gaji->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 500_000,
        ]);

        $migration = require database_path('migrations/2026_08_01_000000_seed_piutang_karyawan_dan_kunci_gaji_karyawan.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('masih ada 1 transaksi yang merujuk kategori Piutang Karyawan');

        $migration->down();
    }
}
