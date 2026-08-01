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
