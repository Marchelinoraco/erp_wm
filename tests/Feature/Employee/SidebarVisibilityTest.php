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

        // Middleware role: mengarahkan (302) ke homePath() utk request biasa,
        // bukan 403 — lihat EnsureUserHasRole (konvensi sama seperti
        // EmployeeControllerTest::test_role_sales_tidak_bisa_akses_daftar_karyawan
        // dan UserControllerTest::test_sales_tidak_bisa_mengakses_kelola_akun).
        // homePath() milik accountant adalah finance.index.
        $this->actingAs($accountant)->get(route('finance.transactions'))->assertOk();
        $this->actingAs($accountant)->get(route('employees.index'))->assertRedirect(route('finance.index'));
        $this->actingAs($accountant)->get(route('employee-advances.index'))->assertRedirect(route('finance.index'));
        $this->actingAs($accountant)->get(route('payrolls.index'))->assertRedirect(route('finance.index'));
    }

    public function test_admin_bisa_akses_ketiganya(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get(route('employees.index'))->assertOk();
        $this->actingAs($admin)->get(route('employee-advances.index'))->assertOk();
        $this->actingAs($admin)->get(route('payrolls.index'))->assertOk();
    }
}
