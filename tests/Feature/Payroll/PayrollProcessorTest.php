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
