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
        // Middleware role: mengarahkan (302) ke homePath() utk request biasa,
        // bukan 403 — lihat EnsureUserHasRole (konvensi sama seperti
        // UserControllerTest::test_sales_tidak_bisa_mengakses_kelola_akun).
        $this->actingAs($this->makeSales())
            ->get(route('employees.index'))
            ->assertRedirect(route('dashboard'));
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
