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

    /**
     * Helper generik untuk uji dua-karyawan (Important 1 review Task 5):
     * karyawan + tunjangan tetap + (opsional) satu kas bon, memakai kas yang
     * sama supaya draft builder mengumpulkan semuanya dalam satu periode.
     */
    private function siapkanKaryawan(string $nama, float $baseSalary, float $tunjangan, float $kasBon, CashAccount $kas): Employee
    {
        $karyawan = Employee::create(['name' => $nama, 'base_salary' => $baseSalary]);
        EmployeeComponent::create(['employee_id' => $karyawan->id, 'name' => 'Tunjangan', 'type' => 'tunjangan', 'amount' => $tunjangan]);

        if ($kasBon > 0.009) {
            $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();
            $trx = FinTransaction::create([
                'date' => '2026-07-10', 'direction' => 'out', 'fin_category_id' => $kategori->id,
                'cash_account_id' => $kas->id, 'amount' => $kasBon, 'source' => 'advance',
            ]);
            EmployeeAdvance::create(['employee_id' => $karyawan->id, 'date' => '2026-07-10', 'amount' => $kasBon, 'fin_transaction_id' => $trx->id]);
        }

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

    /**
     * Fix review Task 5 (Critical): pay() dua kali berturut-turut TANPA
     * cancel() di antaranya, pada periode yang sama, WAJIB ditolak — bukan
     * menggandakan jurnal secara diam-diam. items() lama dibersihkan tapi
     * FinTransaction lama TIDAK, dan gerbang `balanced` Neraca tidak
     * menangkap korupsi ini (aset & ekuitas bergerak bersama sama besar).
     */
    public function test_bayar_dua_kali_tanpa_batalkan_ditolak_dan_tidak_menggandakan_jurnal(): void
    {
        $this->siapkanBudi();
        $kas = CashAccount::first();
        $processor = new PayrollProcessor();
        $builder = new PayrollDraftBuilder();

        $payroll = $processor->pay('2026-07', $builder->build('2026-07'), $kas->id, 'Admin');

        $gagal = null;
        try {
            $processor->pay('2026-07', $builder->build('2026-07'), $kas->id, 'Admin');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal, 'pay() kedua wajib ditolak selama payroll periode ini masih berstatus paid');
        $this->assertSame(422, $gagal->getStatusCode());
        $this->assertSame('paid', $payroll->fresh()->status);
        $this->assertSame(2, FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)->count(),
            'jurnal tidak boleh bertambah jadi 4 — tetap 2 dari pay() pertama');
    }

    /**
     * Fix review Task 5 (Important 1): kelima uji sebelumnya cuma pakai SATU
     * karyawan, jadi "2 transaksi per PERIODE" tidak bisa dibedakan dari "2
     * transaksi per karyawan yang kebetulan cuma satu orang". Dengan DUA
     * karyawan (gaji, tunjangan, kas bon berbeda), tetap harus tepat 2
     * transaksi gabungan (bukan 4).
     */
    public function test_bayar_dengan_dua_karyawan_membuat_tepat_dua_transaksi_untuk_seluruh_periode(): void
    {
        $kas = CashAccount::firstOrCreate(['name' => 'Kas Besar'], ['type' => 'cash']);
        // Budi: 4.000.000 + 800.000 tunjangan - 1.000.000 kas bon = net 3.800.000
        $this->siapkanKaryawan('Budi', 4_000_000, 800_000, 1_000_000, $kas);
        // Siti: 5.000.000 + 500.000 tunjangan - 2.000.000 kas bon = net 3.500.000
        $this->siapkanKaryawan('Siti', 5_000_000, 500_000, 2_000_000, $kas);

        $draft = (new PayrollDraftBuilder())->build('2026-07');
        $this->assertCount(2, $draft, 'draft harus berisi baris untuk kedua karyawan');

        $payroll = (new PayrollProcessor())->pay('2026-07', $draft, $kas->id, 'Admin');

        $this->assertSame(2, FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)->count(),
            'spek §4.2: dua transaksi untuk SELURUH periode, bukan dua per karyawan (yang jadi 4)');

        $trxGaji = FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)
            ->whereNotNull('cash_account_id')->first();
        $trxPelunasan = FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)
            ->whereNull('cash_account_id')->first();

        $this->assertNotNull($trxGaji);
        $this->assertNotNull($trxPelunasan);
        // Gabungan net_amount: 3.800.000 (Budi) + 3.500.000 (Siti)
        $this->assertSame(7_300_000.0, (float) $trxGaji->amount);
        // Gabungan potongan kas bon: 1.000.000 (Budi) + 2.000.000 (Siti)
        $this->assertSame(3_000_000.0, (float) $trxPelunasan->amount);
    }

    /**
     * Fix review Task 5 (Important 2): items kosong tidak boleh membuat
     * "periode hantu" — payroll berstatus paid tanpa item/jurnal apa pun,
     * terkunci tanpa ada yang salah untuk di-"Batalkan" secara wajar.
     * Perilaku yang dipilih: ditolak SEBELUM baris payrolls ditulis sama
     * sekali (bukan ditulis lalu ditandai gagal) — tidak ada row 'draft'
     * atau 'paid' untuk period ini setelah penolakan.
     */
    public function test_bayar_dengan_items_kosong_ditolak_tanpa_membuat_periode_hantu(): void
    {
        $kas = CashAccount::firstOrCreate(['name' => 'Kas Besar'], ['type' => 'cash']);

        $gagal = null;
        try {
            (new PayrollProcessor())->pay('2026-08', [], $kas->id, 'Admin');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal, 'pay() dengan items kosong wajib ditolak');
        $this->assertSame(422, $gagal->getStatusCode());
        $this->assertNull(Payroll::where('period', '2026-08')->first(), 'tidak boleh ada payroll "hantu" tercatat sama sekali');
    }
}
