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
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);
        $advance = $karyawan->advances()->first();
        $trxId = $advance->fin_transaction_id;

        $this->actingAs($admin)->delete(route('employee-advances.destroy', $advance))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('employee_advances', ['id' => $advance->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('fin_transactions', ['id' => $trxId]);
    }
}
