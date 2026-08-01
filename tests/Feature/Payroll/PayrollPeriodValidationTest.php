<?php

namespace Tests\Feature\Payroll;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix wave final review (2026-08-01), Temuan #6 (Minor): regex rute
 * `payrolls.show`/`payrolls.pay` sebelumnya '\d{4}-\d{2}' menerima bulan
 * tidak valid seperti 2026-99, 2026-00, 9999-13. POST payrolls.pay dengan
 * period begitu berhasil membuat payroll berstatus paid PERMANEN dengan
 * bulan tidak masuk akal — dan tidak ada endpoint hapus payroll. Regex
 * diperketat jadi tahun 4 digit + bulan 01-12: '\d{4}-(0[1-9]|1[0-2])'.
 */
class PayrollPeriodValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('password'), 'role' => 'admin',
        ]);
    }

    /**
     * Fix-specific (a): period dengan bulan tidak valid (2026-13) sekarang
     * gagal dengan 404 (rute tidak match), bukan 422/500 dari controller,
     * dan bukan berhasil membuat payroll paid permanen.
     */
    public function test_bayar_dengan_bulan_tidak_valid_2026_13_ditolak_404_rute_tidak_match(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $response = $this->actingAs($this->makeAdmin())->post('/payrolls/2026-13/pay', [
            'cash_account_id' => $kas->id,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('payrolls', 0);
    }

    /**
     * Bukti tambahan: 2026-00 dan 9999-99 (dua digit tapi bukan bulan sah)
     * juga ditolak 404, bukan hanya kasus 13.
     */
    public function test_bayar_dengan_bulan_00_ditolak_404_rute_tidak_match(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $response = $this->actingAs($this->makeAdmin())->post('/payrolls/2026-00/pay', [
            'cash_account_id' => $kas->id,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('payrolls', 0);
    }

    /**
     * Fix-specific (b) — regresi: period valid seperti 2026-07 tetap
     * berfungsi seperti sebelumnya, guard baru tidak melonggarkan/merusak
     * alur yang sah.
     */
    public function test_bayar_dengan_period_valid_2026_07_tetap_berfungsi_regresi(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $response = $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => $kas->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payrolls', ['period' => '2026-07', 'status' => 'paid']);
    }

    /**
     * Bukti tambahan: bulan pinggir (01 dan 12) masih diterima — regex tidak
     * boleh secara tidak sengaja mengecualikan bulan sah di ujung rentang.
     */
    public function test_bayar_dengan_bulan_01_dan_12_tetap_diterima(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('payrolls.pay', '2026-01'), ['cash_account_id' => $kas->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('payrolls.pay', '2026-12'), ['cash_account_id' => $kas->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payrolls', ['period' => '2026-01', 'status' => 'paid']);
        $this->assertDatabaseHas('payrolls', ['period' => '2026-12', 'status' => 'paid']);
    }
}
