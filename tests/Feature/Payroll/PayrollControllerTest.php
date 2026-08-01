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
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);

        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id])
            ->assertStatus(422);
    }

    public function test_batalkan_mengembalikan_status_draft(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('payrolls.pay', '2026-07'), ['cash_account_id' => $kas->id]);
        $payroll = Payroll::where('period', '2026-07')->firstOrFail();

        $this->actingAs($admin)->post(route('payrolls.cancel', $payroll))
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', $payroll->fresh()->status);
    }

    /**
     * Item penutup review Task 5 (#1): PayrollProcessor::pay() sendiri TIDAK
     * memvalidasi cash_account_id — kalau ID tidak ada di tabel cash_accounts,
     * tanpa validasi di controller ini akan lempar QueryException mentah
     * (500). Controller wajib menolaknya rapi lewat validasi request.
     */
    public function test_bayar_dengan_cash_account_id_tidak_valid_ditolak_rapi_bukan_500(): void
    {
        Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);

        $response = $this->actingAs($this->makeAdmin())->post(route('payrolls.pay', '2026-07'), [
            'cash_account_id' => 999999,
        ]);

        $response->assertSessionHasErrors('cash_account_id');
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertDatabaseCount('payrolls', 0);
    }

    /**
     * Item penutup review Task 5 (#3): PayrollProcessor::cancel() sendiri
     * adalah no-op aman untuk payroll berstatus draft (belum pernah dibayar),
     * tapi dari sisi UX controller harus menolak dengan pesan jelas — supaya
     * user tidak bisa "Batalkan" sesuatu yang belum pernah dibayar.
     */
    public function test_batalkan_ditolak_untuk_payroll_berstatus_draft(): void
    {
        $payroll = Payroll::create(['period' => '2026-07', 'status' => 'draft']);

        $this->actingAs($this->makeAdmin())->post(route('payrolls.cancel', $payroll))
            ->assertStatus(422);

        $this->assertSame('draft', $payroll->fresh()->status);
    }
}
