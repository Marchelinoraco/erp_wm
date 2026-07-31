<?php

namespace Tests\Feature\Finance;

use App\Http\Controllers\FiscalController;
use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fix wave final review (2026-08-01), Temuan #2 (Critical): FiscalController::
 * fiscalData() (Laporan Koreksi Fiskal, dasar perhitungan pajak) tidak pernah
 * disentuh Task 8 — masih menghitung SEMUA transaksi source='manual' sebagai
 * opex/otherIncome, TERMASUK kategori bertipe asset, dan mengabaikan nilai
 * source baru ('advance'/'payroll') yang sudah reachable hari ini lewat Task
 * 11 (layar Transaksi mengenali kategori aset). Pola perbaikan sama persis
 * dengan incomeStatementData() (Task 8 & Temuan #3): filter source diganti
 * "bukan invoice/bill", kategori asset dikecualikan.
 */
class FiscalCategoryExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function fiscalData(int $year, string $regime = 'badan_22'): array
    {
        $controller = app(FiscalController::class);
        $ref = new ReflectionMethod($controller, 'fiscalData');
        $ref->setAccessible(true);

        return $ref->invoke($controller, Request::create('/finance/fiscal', 'GET', [
            'year' => $year, 'regime' => $regime,
        ]));
    }

    /**
     * Fix-specific: kas bon (kategori bertipe asset, direction='out') tidak
     * boleh masuk totalOpex; pelunasannya (direction='in', kategori asset
     * yang sama) tidak boleh masuk otherIncome. Sebelum fix, keduanya reachable
     * lewat layar Transaksi (Task 11) dan langsung menggeser labaKomersial.
     */
    public function test_kategori_aset_tidak_masuk_total_opex_dan_other_income_koreksi_fiskal(): void
    {
        $kas        = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban      = FinCategory::create(['name' => 'Operasional',     'type' => 'expense']);
        $pendapatan = FinCategory::create(['name' => 'Pendapatan Lain', 'type' => 'income']);
        $piutang    = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        FinTransaction::create([
            'date' => '2026-07-04', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);
        FinTransaction::create([
            'date' => '2026-07-06', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 150_000,
        ]);

        // Kas bon diberikan: uang keluar (direction='out') tapi kategori asset.
        FinTransaction::create([
            'date' => '2026-07-10', 'direction' => 'out', 'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id, 'amount' => 1_000_000,
        ]);
        // Kas bon dilunasi: uang masuk (direction='in') tapi kategori asset.
        FinTransaction::create([
            'date' => '2026-07-25', 'direction' => 'in', 'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id, 'amount' => 400_000,
        ]);

        $data = $this->fiscalData(2026);

        $this->assertSame(200_000.0, $data['totalOpex'],
            'Kas bon (kategori asset, direction=out) tidak boleh masuk totalOpex Koreksi Fiskal');
        $this->assertSame(150_000.0, $data['otherIncome'],
            'Pelunasan kas bon (kategori asset, direction=in) tidak boleh masuk otherIncome Koreksi Fiskal');
    }

    /**
     * Fix-specific: begitu source mulai bernilai 'advance'/'payroll' (Tahap
     * B), Koreksi Fiskal harus tetap menghitungnya. Filter lama
     * where('source','manual') akan menghilangkannya tanpa galat.
     */
    public function test_source_advance_dan_payroll_tetap_terhitung_di_koreksi_fiskal(): void
    {
        $kas  = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $gaji = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);
        $lain = FinCategory::create(['name' => 'Pendapatan Lain', 'type' => 'income']);

        FinTransaction::create([
            'date' => '2026-07-30', 'direction' => 'out', 'fin_category_id' => $gaji->id,
            'cash_account_id' => $kas->id, 'amount' => 3_800_000, 'source' => 'payroll',
        ]);
        FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'in', 'fin_category_id' => $lain->id,
            'cash_account_id' => $kas->id, 'amount' => 250_000, 'source' => 'advance',
        ]);

        $data = $this->fiscalData(2026);

        $this->assertSame(3_800_000.0, $data['totalOpex'],
            'Beban dengan source=payroll harus tetap terhitung sebagai opex di Koreksi Fiskal');
        $this->assertSame(250_000.0, $data['otherIncome'],
            'Pendapatan dengan source=advance harus tetap terhitung sebagai other income di Koreksi Fiskal');
    }

    /**
     * Bukti regresi: untuk data yang HANYA berkategori income/expense dengan
     * source 'manual' (persis kondisi database saat ini — tidak ada kategori
     * asset, tidak ada source advance/payroll), totalOpex/otherIncome hasil
     * fiscalData() versi baru harus IDENTIK dengan oracle logika lama
     * (where('source','manual'), tanpa pengecualian kategori asset).
     */
    public function test_fiscal_data_identik_dengan_logika_lama_untuk_kategori_income_expense_regresi(): void
    {
        $kas   = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban = FinCategory::create(['name' => 'Operasional', 'type' => 'expense']);
        $inc   = FinCategory::create(['name' => 'Pendapatan Lain', 'type' => 'income']);

        FinTransaction::create([
            'date' => '2026-03-05', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        FinTransaction::create([
            'date' => '2026-04-12', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 300_000,
        ]);
        FinTransaction::create([
            'date' => '2026-05-20', 'direction' => 'in', 'fin_category_id' => $inc->id,
            'cash_account_id' => $kas->id, 'amount' => 700_000,
        ]);

        $actual = $this->fiscalData(2026);

        // Oracle: salinan persis logika lama SEBELUM fix Temuan #2.
        $oldTotalOpex = (float) FinTransaction::where('source', 'manual')
            ->where('direction', 'out')->whereYear('date', 2026)->sum('amount');
        $oldOtherIncome = (float) FinTransaction::where('source', 'manual')
            ->where('direction', 'in')->whereYear('date', 2026)->sum('amount');

        $this->assertSame($oldTotalOpex, $actual['totalOpex'], 'totalOpex harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertSame($oldOtherIncome, $actual['otherIncome'], 'otherIncome harus identik dengan logika lama untuk data income/expense biasa');

        // Angka konkret, bukan hanya kesamaan dengan oracle.
        $this->assertSame(800_000.0, $actual['totalOpex']);
        $this->assertSame(700_000.0, $actual['otherIncome']);
    }
}
