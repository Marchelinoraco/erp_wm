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
     *
     * Fix round 2 (review Task 8), temuan Important 1: sebelumnya test ini hanya
     * assertStringNotContainsString terhadap nama kategori — tidak ada assert
     * numerik eksplisit. Sekarang dibandingkan totalOpex/netProfit SEBELUM dan
     * SESUDAH transaksi kategori aset (direction='out', kas bon diberikan)
     * ditambahkan, supaya benar-benar terbukti angka laporan tidak berubah,
     * bukan hanya disimpulkan dari membaca kode.
     */
    public function test_kategori_aset_tidak_muncul_di_laba_rugi(): void
    {
        $kas        = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-03', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-04', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);

        // Patokan (baseline) SEBELUM ada transaksi kategori aset sama sekali.
        $before = $ref->invoke($controller, 2026);

        // Kas bon diberikan: uang keluar dari kas (direction='out'), tapi
        // kategorinya 'asset' (Piutang Karyawan), bukan beban.
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);
        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
        ]);

        $after = $ref->invoke($controller, 2026);
        $json  = json_encode($after);

        $this->assertStringNotContainsString('Piutang Karyawan', $json,
            'Kas bon adalah aset, tidak boleh tampil di Laba Rugi');

        // Bukti numerik eksplisit: totalOpex dan netProfit harus PERSIS SAMA
        // sebelum dan sesudah transaksi kategori aset ditambahkan, walau uangnya
        // benar-benar keluar dari kas sebesar 1.000.000.
        $this->assertSame(200_000.0, $before['totalOpex']);
        $this->assertSame(200_000.0, $after['totalOpex'],
            'totalOpex tidak boleh bertambah akibat transaksi kategori aset (direction=out)');
        $this->assertSame($before['netProfit'], $after['netProfit'],
            'netProfit tidak boleh berubah akibat transaksi kategori aset (direction=out)');
        $this->assertSame(300_000.0, $after['netProfit']);
    }

    /**
     * Spek §3.4 no. 5 — kas bon yang DILUNASI (direction='in', kategori tetap
     * 'asset') juga bukan pendapatan lain-lain.
     *
     * Fix round 2 (review Task 8), temuan Important 2: filter
     * `whereHas('category', type != 'asset')` diterapkan di KEDUA query —
     * $opexTxns (direction='out') MAUPUN $otherIncome (direction='in'). Test
     * sebelumnya hanya membuktikan arah 'out' (kas bon diberikan); test ini
     * membuktikan arah 'in' (kas bon dilunasi/dikembalikan) juga tersaring dan
     * tidak mengubah netProfit.
     */
    public function test_kategori_aset_direction_in_tidak_muncul_sebagai_pendapatan_lain(): void
    {
        $kas        = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-03', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-04', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);

        // Patokan (baseline) SEBELUM ada transaksi kategori aset sama sekali.
        $before = $ref->invoke($controller, 2026);

        // Kas bon dilunasi: uang masuk ke kas (direction='in'), tapi kategorinya
        // tetap 'asset' (Piutang Karyawan berkurang, bukan pendapatan baru).
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan Dilunasi', 'type' => 'asset']);
        \App\Models\FinTransaction::create([
            'date'            => '2026-07-20',
            'direction'       => 'in',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 750_000,
        ]);

        $after = $ref->invoke($controller, 2026);
        $json  = json_encode($after);

        $this->assertStringNotContainsString('Piutang Karyawan Dilunasi', $json,
            'Kas bon dilunasi (kategori aset, direction=in) tidak boleh tampil sebagai pendapatan lain-lain');

        // Bukti numerik eksplisit: otherIncome dan netProfit harus PERSIS SAMA
        // sebelum dan sesudah transaksi kategori aset ditambahkan, walau uangnya
        // benar-benar masuk ke kas sebesar 750.000.
        $this->assertSame(500_000.0, $before['otherIncome']);
        $this->assertSame(500_000.0, $after['otherIncome'],
            'otherIncome tidak boleh bertambah akibat transaksi kategori aset (direction=in)');
        $this->assertSame($before['netProfit'], $after['netProfit'],
            'netProfit tidak boleh berubah akibat transaksi kategori aset (direction=in)');
        $this->assertSame(300_000.0, $after['netProfit']);
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
     * Spek §3.4 no. 2/3/4 — kas bon (kategori bertipe 'asset') harus muncul
     * sebagai baris aset baru di Neraca (other_assets_total), TIDAK mengurangi
     * laba ditahan (bukan beban), dan Neraca tetap balance (aset naik 1jt
     * diimbangi kas turun 1jt di sisi lain, net efek ke total aset = 0).
     *
     * Catatan implementasi (beda dari draf awal di brief): brief menulis
     * `opening_balance => 10_000_000` untuk akun kas. Itu diverifikasi SENDIRI
     * lewat run empiris (bukan cuma dihitung di kepala) dan terbukti membuat
     * skenario ini TIDAK BISA balance sama sekali — opening_balance 10 juta
     * tanpa modal_disetor yang menyertainya adalah modal "gratis" yang tidak
     * berpasangan (fresh test DB: modal_disetor = 0), sehingga Neraca sudah
     * timpang sejak sebelum transaksi kas bon dibuat (selisih tetap 10 juta,
     * terlepas dari benar/tidaknya perubahan Task 9). Kas bon sendiri memang
     * SEIMBANG (kas turun 1jt, aset lain naik 1jt, total aset tak berubah),
     * jadi baseline-nya harus sudah balance duluan. opening_balance dihapus
     * (default 0, sama seperti test_kategori_aset_masuk_kelompok_aset_di_
     * buku_besar di atas) supaya baseline seimbang dan bukti 'balanced' benar
     * benar menguji efek kas bon, bukan efek modal yang tidak disengaja.
     */
    public function test_kas_bon_pindah_ke_aset_dan_neraca_tetap_balance(): void
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
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(1_000_000.0, $data['aset']['other_assets_total'],
            'Kas bon harus muncul sebagai aset');
        $this->assertSame(0.0, $data['ekuitas']['laba_ditahan'],
            'Kas bon bukan beban, laba ditahan tidak boleh berkurang');
        $this->assertTrue($data['balanced'], 'Neraca wajib tetap seimbang');
    }

    /**
     * Spek §3.4 no. 2/3 — bukti regresi: untuk data yang HANYA berkategori
     * income/expense dengan source 'manual' (tanpa kategori 'asset' dan tanpa
     * source 'advance'/'payroll' sama sekali, persis kondisi database saat
     * ini), aset.total, ekuitas.laba_ditahan, dan balanced hasil
     * balanceSheetData() versi baru harus IDENTIK dengan angka yang dihasilkan
     * query lama (where('source', 'manual'), tanpa pengecualian kategori
     * aset, tanpa other_assets).
     *
     * opening_balance TIDAK diisi (default 0) dengan sengaja: opening_balance
     * kas yang tidak berpasangan dengan modal_disetor adalah modal "gratis"
     * yang membuat Neraca timpang sejak awal terlepas dari benar/tidaknya
     * Task 9 (dibuktikan empiris saat menyusun test kas-bon di atas). Semua
     * pergerakan di fixture ini murni dari transaksi in/out supaya baseline
     * benar-benar balance dan assertTrue(balanced) menguji hal yang nyata.
     */
    public function test_balance_sheet_identik_dengan_logika_lama_untuk_kategori_income_expense(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 2_000_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 800_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026);

        // Oracle: salinan persis logika lama SEBELUM Task 9 (where('source',
        // 'manual'), tanpa pengecualian kategori aset, tanpa other_assets).
        $endDate = '2026-12-31';
        $cashIn  = (float) \App\Models\FinTransaction::where('cash_account_id', $kas->id)->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');
        $cashOut = (float) \App\Models\FinTransaction::where('cash_account_id', $kas->id)->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
        $oldCashTotal = 0.0 + $cashIn - $cashOut;
        $oldAr = 0.0;
        $oldAsetTotal = $oldCashTotal + $oldAr; // tanpa aset tetap, tanpa other_assets

        $oldManualIncome  = (float) \App\Models\FinTransaction::where('source', 'manual')->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');
        $oldManualExpense = (float) \App\Models\FinTransaction::where('source', 'manual')->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
        $oldLabaDitahan = ($oldManualIncome) - ($oldManualExpense);

        $this->assertSame($oldAsetTotal, $actual['aset']['total'], 'aset.total harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertSame($oldLabaDitahan, $actual['ekuitas']['laba_ditahan'], 'ekuitas.laba_ditahan harus identik dengan logika lama untuk data income/expense biasa');
        $this->assertTrue($actual['balanced']);

        // Angka konkret, bukan hanya kesamaan dengan oracle.
        $this->assertSame(1_200_000.0, $actual['aset']['total']);
        $this->assertSame(1_200_000.0, $actual['ekuitas']['laba_ditahan']);
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
