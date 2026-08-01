<?php

namespace Tests\Feature\Employee;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
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

    /**
     * Task 4 D6/D10: sebelum Task 4, class_exists(PayrollItemLine::class)
     * selalu false sehingga sisa() cuma mengembalikan amount penuh — guard
     * itu belum pernah benar-benar teruji. Sekarang PayrollItemLine ada,
     * begitu sebuah baris kind='kas_bon' merujuk kas bon ini (potongan
     * otomatis lewat gajian), sisa() WAJIB dihitung ulang: amount dikurangi
     * total payroll_item_lines yang merujuknya, bukan disimpan di kolom apa
     * pun. Ini bukti self-healing itu sungguh terjadi, bukan asumsi.
     */
    public function test_sisa_kas_bon_berkurang_setelah_dipotong_lewat_payroll_item_line(): void
    {
        $karyawan = Employee::create(['name' => 'Budi', 'base_salary' => 4_000_000]);
        $kas = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $this->actingAs($this->makeAdmin())->post(route('employee-advances.store'), [
            'employee_id' => $karyawan->id, 'date' => '2026-07-10',
            'amount' => 1_000_000, 'cash_account_id' => $kas->id,
        ]);
        $advance = $karyawan->advances()->first();

        $this->assertSame(1_000_000.0, $advance->sisa());
        $this->assertFalse($advance->sudahDipotong());

        $payroll = Payroll::create(['period' => '2026-07', 'status' => 'paid', 'paid_date' => '2026-07-31']);
        $item = PayrollItem::create([
            'payroll_id' => $payroll->id, 'employee_id' => $karyawan->id,
            'employee_name' => $karyawan->name, 'base_salary' => $karyawan->base_salary,
            'net_amount' => 3_400_000,
        ]);
        PayrollItemLine::create([
            'payroll_item_id' => $item->id, 'kind' => 'kas_bon',
            'label' => 'Kas bon 10 Jul 2026', 'amount' => 600_000,
            'employee_advance_id' => $advance->id,
        ]);

        $this->assertSame(400_000.0, $advance->sisa());
        $this->assertTrue($advance->sudahDipotong());
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
