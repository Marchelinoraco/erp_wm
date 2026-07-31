<?php

namespace Tests\Feature\Finance;

use App\Models\FinCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §3.2 / D7: kategori keuangan mendapat tipe ketiga 'asset' supaya kas bon
 * bisa dibukukan sebagai Piutang Karyawan, bukan sebagai beban.
 */
class AssetCategoryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_kategori_bisa_disimpan_dengan_tipe_asset(): void
    {
        $kategori = FinCategory::create([
            'name' => 'Piutang Karyawan',
            'type' => 'asset',
        ]);

        $this->assertSame('asset', $kategori->fresh()->type);
    }

    public function test_scope_asset_hanya_mengambil_kategori_aset(): void
    {
        FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        FinCategory::create(['name' => 'Penjualan Tour',   'type' => 'income']);
        FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->assertSame(['Piutang Karyawan'], FinCategory::asset()->pluck('name')->all());
    }

    /**
     * Fix round 1 (review Task 1), temuan Important 2: down() migrasi tidak boleh
     * diam-diam mengubah kategori 'asset' jadi 'expense' saat rollback — itu akan
     * menggeser Laba Rugi dan Neraca. Rollback harus ditolak selama masih ada
     * kategori bertipe 'asset'.
     */
    public function test_down_menolak_rollback_saat_masih_ada_kategori_asset(): void
    {
        FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $migration = require database_path('migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("masih ada 1 kategori bertipe 'asset'");

        $migration->down();
    }

    /**
     * Spek §3.4 no. 1: kelompok akun di Buku Besar harus dibaca dari tipe
     * kategorinya sendiri, bukan ditebak dari arah uang (direction). Kas bon
     * (kategori bertipe 'asset') harus masuk kelompok 'aset', bukan 'beban',
     * dan tidak boleh mengurangi laba.
     */
    public function test_kategori_aset_masuk_kelompok_aset_di_buku_besar(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026, null);

        $akun = collect($data['accounts'])->firstWhere('name', 'Piutang Karyawan');

        $this->assertSame('aset', $akun['group'], 'Kategori aset tidak boleh masuk kelompok beban');
        $this->assertSame(0.0, $data['profit']['expense'], 'Kas bon bukan beban, laba tidak boleh berkurang');
    }

    /**
     * Spek §3.4 no. 5: kategori bertipe 'asset' bukan pendapatan/beban, jadi
     * tidak boleh muncul di rincian beban operasional maupun pendapatan lain-lain
     * pada Laporan Laba Rugi.
     */
    public function test_kategori_aset_tidak_muncul_di_laba_rugi(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $json = json_encode($data);

        $this->assertStringNotContainsString('Piutang Karyawan', $json,
            'Kas bon adalah aset, tidak boleh tampil di Laba Rugi');
    }

    /**
     * Spek §3.4 no. 5 — bukti regresi: untuk data yang HANYA berkategori
     * income/expense (tanpa kategori 'asset' sama sekali, persis kondisi
     * database saat ini), totalOpex/otherIncome/netProfit hasil
     * incomeStatementData() versi baru harus IDENTIK dengan angka yang
     * dihasilkan query lama (tanpa penyaring `whereHas('category', ...)`).
     * Tidak ada invoice/bill/aset tetap di fixture ini supaya grossProfit dan
     * totalDepreciation nol, sehingga netProfit = otherIncome - totalOpex.
     */
    public function test_income_statement_identik_dengan_logika_lama_untuk_kategori_income_expense(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-08', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 300_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-20', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 100_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026);

        // Oracle: salinan persis query $opexTxns / $otherIncome SEBELUM Task 8
        // (tanpa whereHas('category', type != 'asset')).
        $oldOpexTotal = (float) \App\Models\FinTransaction::where('source', 'manual')
            ->where('direction', 'out')->whereYear('date', 2026)->sum('amount');
        $oldOtherIncome = (float) \App\Models\FinTransaction::where('source', 'manual')
            ->where('direction', 'in')->whereYear('date', 2026)->sum('amount');
        $oldNetProfit = 0 - $oldOpexTotal - 0 + $oldOtherIncome;

        $this->assertSame($oldOpexTotal, $actual['totalOpex'], 'totalOpex harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertSame($oldOtherIncome, $actual['otherIncome'], 'otherIncome harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertSame($oldNetProfit, $actual['netProfit'], 'netProfit harus identik dengan logika lama untuk data income/expense biasa');

        // Angka konkret, bukan hanya kesamaan dengan oracle.
        $this->assertSame(300_000.0, $actual['totalOpex']);
        $this->assertSame(800_000.0, $actual['otherIncome']);
        $this->assertSame(500_000.0, $actual['netProfit']);
    }

    /**
     * Spek §3.4 no. 1 — bukti regresi: untuk data yang HANYA berkategori
     * income/expense (tanpa kategori 'asset' sama sekali, persis kondisi
     * database saat ini), hasil ledgerData() versi baru harus IDENTIK dengan
     * versi lama yang menebak kelompok dari `direction`. Oracle di bawah ini
     * adalah salinan persis logika lama sebelum Task 7 mengubahnya.
     */
    public function test_ledger_data_identik_dengan_logika_lama_untuk_kategori_income_expense(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-12', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 300_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-20', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 100_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026, null);

        $expected = $this->oldLedgerData(2026, null);

        $actualGroups   = collect($actual['accounts'])->pluck('group', 'name')->sortKeys()->all();
        $expectedGroups = collect($expected['accounts'])->pluck('group', 'name')->sortKeys()->all();

        $this->assertSame($expectedGroups, $actualGroups, 'group tiap akun harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertSame($expected['profit'], $actual['profit'], 'profit.income/profit.expense harus identik dengan logika lama');
    }

    /**
     * Salinan persis ledgerData() SEBELUM Task 7 (kelompok akun kategori
     * ditebak dari direction). Dipakai sebagai oracle regresi di atas — bukan
     * untuk dipanggil di kode produksi.
     */
    private function oldLedgerData(int $year, $month): array
    {
        $q = \App\Models\FinTransaction::with(['category', 'cashAccount'])->whereYear('date', $year);
        if ($month) {
            $q->whereMonth('date', (int) $month);
        }
        $txns = $q->orderBy('date')->orderBy('id')->get();

        $acc = [];
        $touch = function (string $key, string $name, string $group) use (&$acc) {
            $acc[$key] ??= ['name' => $name, 'group' => $group, 'debit' => 0.0, 'credit' => 0.0, 'postings' => []];
        };

        foreach ($txns as $t) {
            $amt      = (float) $t->amount;
            $date     = $t->date->format('Y-m-d');
            $cashKey  = 'cash-' . $t->cash_account_id;
            $catKey   = 'cat-' . $t->fin_category_id;
            $cashName = $t->cashAccount?->name ?? 'Kas';
            $catName  = $t->category?->name ?? '-';

            $touch($cashKey, $cashName, 'aset');
            $touch($catKey, $catName, $t->direction === 'in' ? 'pendapatan' : 'beban');

            if ($t->direction === 'in') {
                $acc[$cashKey]['debit'] += $amt;
                $acc[$cashKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => $amt, 'credit' => 0];
                $acc[$catKey]['credit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $cashName, 'debit' => 0, 'credit' => $amt];
            } else {
                $acc[$catKey]['debit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $cashName, 'debit' => $amt, 'credit' => 0];
                $acc[$cashKey]['credit'] += $amt;
                $acc[$cashKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => 0, 'credit' => $amt];
            }
        }

        $accounts = collect($acc)->map(function ($a) {
            $normalDebit  = in_array($a['group'], ['aset', 'beban']);
            $a['balance'] = $normalDebit ? $a['debit'] - $a['credit'] : $a['credit'] - $a['debit'];
            return $a;
        })->sortBy([['group', 'asc'], ['name', 'asc']])->values();

        $pendapatan = (float) $accounts->where('group', 'pendapatan')->sum('balance');
        $beban      = (float) $accounts->where('group', 'beban')->sum('balance');

        return [
            'year'     => $year,
            'month'    => $month,
            'accounts' => $accounts,
            'profit'   => ['income' => $pendapatan, 'expense' => $beban, 'net' => $pendapatan - $beban],
        ];
    }
}
