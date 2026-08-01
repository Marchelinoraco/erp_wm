<?php

namespace Tests\Feature\Finance;

use App\Models\FinCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        // 'Piutang Karyawan' sudah di-seed oleh migrasi 2026_08_01_000000

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
        // 'Piutang Karyawan' sudah di-seed oleh migrasi 2026_08_01_000000, tambah 1 lagi untuk test
        FinCategory::create(['name' => 'Hutang Karyawan', 'type' => 'asset']);

        $migration = require database_path('migrations/2026_07_31_000000_add_asset_type_to_fin_categories.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("masih ada 2 kategori bertipe 'asset'");

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
     * Spek §3.4 no. 6: baris non-kas (lawannya kategori lewat
     * contra_fin_category_id, bukan akun kas) tidak memindahkan uang sama
     * sekali — tidak boleh muncul di totals Arus Kas.
     */
    public function test_transaksi_non_kas_tidak_mempengaruhi_arus_kas(): void
    {
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'cashFlowData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(0.0, $data['totals']['expense'],
            'Baris non-kas tidak memindahkan uang, tidak boleh muncul di Arus Kas');
    }

    /**
     * Spek §3.4 no. 6 — sisi 'in': baris non-kas dengan direction='in' juga
     * tidak boleh menambah totals.income di Arus Kas. Test di atas hanya
     * membuktikan sisi 'out'; filter `whereNotNull('cash_account_id')` pada
     * query $in di perulangan bulan cashFlowData() adalah baris kode terpisah
     * dari filter pada query $out, jadi butuh bukti terpisah supaya kalau
     * SALAH SATU filter (bukan keduanya) terhapus, ada test yang menangkap.
     */
    public function test_transaksi_non_kas_direction_in_tidak_mempengaruhi_arus_kas(): void
    {
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour',       'type' => 'income']);
        $hutang     = FinCategory::create(['name' => 'Hutang Karyawan Lain', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'                   => '2026-08-05',
            'direction'              => 'in',
            'fin_category_id'        => $pendapatan->id,
            'contra_fin_category_id' => $hutang->id,
            'amount'                 => 750_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'cashFlowData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(0.0, $data['totals']['income'],
            'Baris non-kas (direction=in) tidak memindahkan uang, tidak boleh muncul di Arus Kas');
    }

    /**
     * Spek §3.4 no. 6 — closure $byCategory di cashFlowData() punya query dan
     * filter SENDIRI, terpisah dari query $in/$out di perulangan bulan. Kalau
     * hanya filter di closure ini yang terhapus, totals tidak akan berubah
     * (dihitung dari $incomeSeries/$expenseSeries, bukan dari $byCategory),
     * tapi rincian per kategori (expenseByCat) akan salah menampilkan
     * kategori yang HANYA punya baris non-kas. Kategori di test ini sengaja
     * tidak dipakai transaksi kas apa pun supaya kemunculannya di
     * expenseByCat murni bukti bahwa baris non-kas ikut ter-groupBy.
     */
    public function test_transaksi_non_kas_tidak_muncul_di_rincian_kategori_arus_kas(): void
    {
        $bebanNonKas = FinCategory::create(['name' => 'Beban Non-Kas Saja', 'type' => 'expense']);
        $piutang     = FinCategory::create(['name' => 'Piutang Karyawan',  'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'                   => '2026-09-12',
            'direction'              => 'out',
            'fin_category_id'        => $bebanNonKas->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_500_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'cashFlowData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $namaKategori = collect($data['expenseByCat'])->pluck('name')->all();

        $this->assertNotContains('Beban Non-Kas Saja', $namaKategori,
            'Kategori yang hanya punya baris non-kas tidak boleh muncul di rincian expenseByCat');
    }

    /**
     * Spek §3.4 no. 6 — bukti regresi: untuk data yang HANYA berupa transaksi
     * kas biasa (persis kondisi database saat ini, tidak ada satu pun baris
     * dengan contra_fin_category_id terisi), totals hasil cashFlowData()
     * versi baru (dengan whereNotNull('cash_account_id')) harus IDENTIK
     * dengan hasil query lama (tanpa filter itu), karena semua baris memang
     * sudah punya cash_account_id.
     */
    public function test_cash_flow_data_identik_dengan_logika_lama_untuk_transaksi_kas(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-01-10', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-02-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 300_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-03-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'cashFlowData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026);

        // Oracle: salinan persis query totals SEBELUM Task 10 (tanpa
        // whereNotNull('cash_account_id')).
        $oldIncome  = (float) \App\Models\FinTransaction::whereYear('date', 2026)->where('direction', 'in')->sum('amount');
        $oldExpense = (float) \App\Models\FinTransaction::whereYear('date', 2026)->where('direction', 'out')->sum('amount');

        $this->assertSame($oldIncome, $actual['totals']['income'], 'totals.income harus identik dengan logika lama untuk data kas biasa');
        $this->assertSame($oldExpense, $actual['totals']['expense'], 'totals.expense harus identik dengan logika lama untuk data kas biasa');

        // Angka konkret, bukan hanya kesamaan dengan oracle.
        $this->assertSame(800_000.0, $actual['totals']['income']);
        $this->assertSame(200_000.0, $actual['totals']['expense']);
    }

    /**
     * Spek §3.4 no. 6 — cabang BULANAN recapData(): baris non-kas (direction
     * 'in' maupun 'out') tidak boleh mempengaruhi totals. Dua arah diuji
     * dalam satu test karena keduanya independen secara matematis (jumlahnya
     * beda), jadi kalau HANYA salah satu filter (query $in ATAU $out) yang
     * terhapus, assertion yang bersangkutan akan gagal sendiri.
     */
    public function test_transaksi_non_kas_tidak_mempengaruhi_rekap_bulanan(): void
    {
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour',       'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',       'type' => 'expense']);
        $piutang    = FinCategory::create(['name' => 'Piutang Karyawan',    'type' => 'asset']);
        $hutang     = FinCategory::create(['name' => 'Hutang Karyawan Lain', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date' => '2026-04-10', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'contra_fin_category_id' => $piutang->id, 'amount' => 900_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-05-10', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'contra_fin_category_id' => $hutang->id, 'amount' => 600_000,
        ]);

        $request = \Illuminate\Http\Request::create('/finance/recap', 'GET', ['mode' => 'monthly', 'year' => 2026]);
        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'recapData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, $request);

        $this->assertSame(0.0, $data['totals']['income'], 'Baris non-kas (in) tidak boleh muncul di Rekap bulanan');
        $this->assertSame(0.0, $data['totals']['expense'], 'Baris non-kas (out) tidak boleh muncul di Rekap bulanan');
    }

    /**
     * Spek §3.4 no. 6 — cabang MINGGUAN recapData(): sama seperti cabang
     * bulanan, tapi lewat query $txns yang berbeda (satu query untuk seluruh
     * bulan, lalu di-bucket per minggu di PHP). Filter di cabang ini terpisah
     * dari cabang bulanan, jadi butuh bukti sendiri.
     */
    public function test_transaksi_non_kas_tidak_mempengaruhi_rekap_mingguan(): void
    {
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour',       'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',       'type' => 'expense']);
        $piutang    = FinCategory::create(['name' => 'Piutang Karyawan',    'type' => 'asset']);
        $hutang     = FinCategory::create(['name' => 'Hutang Karyawan Lain', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-06', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'contra_fin_category_id' => $piutang->id, 'amount' => 400_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-20', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'contra_fin_category_id' => $hutang->id, 'amount' => 250_000,
        ]);

        $request = \Illuminate\Http\Request::create('/finance/recap', 'GET', ['mode' => 'weekly', 'month' => '2026-07']);
        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'recapData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, $request);

        $this->assertSame(0.0, $data['totals']['income'], 'Baris non-kas (in) tidak boleh muncul di Rekap mingguan');
        $this->assertSame(0.0, $data['totals']['expense'], 'Baris non-kas (out) tidak boleh muncul di Rekap mingguan');
    }

    /**
     * Spek §3.4 no. 6 — bukti regresi: untuk data yang HANYA berupa transaksi
     * kas biasa, totals hasil recapData() versi baru (cabang bulanan) harus
     * IDENTIK dengan logika lama (tanpa whereNotNull('cash_account_id')).
     */
    public function test_recap_data_identik_dengan_logika_lama_untuk_transaksi_kas_bulanan(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-02-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 1_200_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-06-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 450_000,
        ]);

        $request = \Illuminate\Http\Request::create('/finance/recap', 'GET', ['mode' => 'monthly', 'year' => 2026]);
        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'recapData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, $request);

        $oldIncome  = (float) \App\Models\FinTransaction::whereYear('date', 2026)->where('direction', 'in')->sum('amount');
        $oldExpense = (float) \App\Models\FinTransaction::whereYear('date', 2026)->where('direction', 'out')->sum('amount');

        $this->assertSame($oldIncome, $actual['totals']['income'], 'totals.income harus identik dengan logika lama untuk data kas biasa');
        $this->assertSame($oldExpense, $actual['totals']['expense'], 'totals.expense harus identik dengan logika lama untuk data kas biasa');

        $this->assertSame(1_200_000.0, $actual['totals']['income']);
        $this->assertSame(450_000.0, $actual['totals']['expense']);
    }

    /**
     * Spek §3.4 no. 6 — bukti regresi cabang MINGGUAN recapData(): untuk data
     * transaksi kas biasa dalam satu bulan, totals harus IDENTIK dengan
     * logika lama (tanpa whereNotNull('cash_account_id')).
     */
    public function test_recap_data_identik_dengan_logika_lama_untuk_transaksi_kas_mingguan(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-03', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 700_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-22', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 150_000,
        ]);

        $request = \Illuminate\Http\Request::create('/finance/recap', 'GET', ['mode' => 'weekly', 'month' => '2026-07']);
        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'recapData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, $request);

        $oldIncome  = (float) \App\Models\FinTransaction::whereYear('date', 2026)->whereMonth('date', 7)->where('direction', 'in')->sum('amount');
        $oldExpense = (float) \App\Models\FinTransaction::whereYear('date', 2026)->whereMonth('date', 7)->where('direction', 'out')->sum('amount');

        $this->assertSame($oldIncome, $actual['totals']['income'], 'totals.income harus identik dengan logika lama untuk data kas biasa');
        $this->assertSame($oldExpense, $actual['totals']['expense'], 'totals.expense harus identik dengan logika lama untuk data kas biasa');

        $this->assertSame(700_000.0, $actual['totals']['income']);
        $this->assertSame(150_000.0, $actual['totals']['expense']);
    }

    /**
     * Spek §3.4 no. 6 — balanceBefore(): baris non-kas (direction 'in' maupun
     * 'out', tanggal sebelum tanggal acuan) tidak boleh mempengaruhi saldo
     * berjalan sama sekali. Jumlah in/out sengaja dibuat BEDA supaya kalau
     * hanya salah satu filter ($in ATAU $out) yang terhapus, hasilnya jadi
     * bukan nol dan assertion menangkapnya.
     */
    public function test_transaksi_non_kas_tidak_mempengaruhi_saldo_berjalan(): void
    {
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour',       'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',       'type' => 'expense']);
        $piutang    = FinCategory::create(['name' => 'Piutang Karyawan',    'type' => 'asset']);
        $hutang     = FinCategory::create(['name' => 'Hutang Karyawan Lain', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date' => '2026-01-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'contra_fin_category_id' => $hutang->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-01-10', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'contra_fin_category_id' => $piutang->id, 'amount' => 300_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceBefore');
        $ref->setAccessible(true);
        $balance = $ref->invoke($controller, Carbon::create(2026, 2, 1));

        $this->assertSame(0.0, $balance,
            'Baris non-kas (in maupun out) tidak boleh mempengaruhi saldo berjalan');
    }

    /**
     * Spek §3.4 no. 6 — bukti regresi: untuk data yang HANYA berupa transaksi
     * kas biasa, hasil balanceBefore() versi baru harus IDENTIK dengan logika
     * lama (tanpa whereNotNull('cash_account_id')).
     */
    public function test_balance_before_identik_dengan_logika_lama_untuk_transaksi_kas(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash', 'opening_balance' => 0]);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-01-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 1_000_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-01-10', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 400_000,
        ]);

        $refDate = Carbon::create(2026, 2, 1);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceBefore');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, $refDate);

        // Oracle: salinan persis logika lama SEBELUM Task 10 (tanpa
        // whereNotNull('cash_account_id')).
        $oldOpening = (float) \App\Models\CashAccount::sum('opening_balance');
        $oldIn      = (float) \App\Models\FinTransaction::where('date', '<', $refDate)->where('direction', 'in')->sum('amount');
        $oldOut     = (float) \App\Models\FinTransaction::where('date', '<', $refDate)->where('direction', 'out')->sum('amount');
        $oldBalance = $oldOpening + $oldIn - $oldOut;

        $this->assertSame($oldBalance, $actual, 'balanceBefore() harus identik dengan logika lama untuk data kas biasa');
        $this->assertSame(600_000.0, $actual);
    }

    // ────────────────────────────────────────────────────────────────────
    // Fix wave final review (2026-08-01) — 6 temuan dari final whole-branch
    // review. Kriteria tidak berubah: nol perubahan angka untuk data
    // existing (belum ada kategori bertipe asset di production).
    // ────────────────────────────────────────────────────────────────────

    /**
     * ACCEPTANCE TEST — skenario spek §4.2 LENGKAP (bukan potongan), yang
     * direkomendasikan reviewer final sebagai satu-satunya test yang
     * membuktikan deliverable Tahap A benar-benar bekerja untuk kasus nyata
     * kas bon + gajian. Sebelum fix Temuan #1, test ini gagal: setelah kas
     * bon 1jt dilunasi penuh, other_assets_total tetap 1.000.000 (bukan 0)
     * dan balanced menjadi false, karena baris pelunasan non-kas menaruh
     * kategori asetnya di contra_fin_category_id yang tidak pernah dibaca
     * query lama.
     *
     * Transaksi memakai source 'advance'/'payroll' (nilai yang benar-benar
     * dipakai Tahap B, spek §4.2 tabel jurnal) supaya test ini juga
     * membuktikan Temuan #3 (incomeStatementData harus tetap menghitung
     * source baru sebagai opex, bukan hanya 'manual').
     */
    public function test_skenario_kas_bon_dan_gajian_lengkap_sesuai_spek_4_2(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);
        $gaji    = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        // 1) Kas bon 1.000.000 diberikan 10 Jul.
        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
            'source'          => 'advance',
        ]);

        // 2a) Gajian 30 Jul — bagian kas: 3.800.000.
        \App\Models\FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'out',
            'fin_category_id' => $gaji->id,
            'cash_account_id' => $kas->id,
            'amount'          => 3_800_000,
            'source'          => 'payroll',
        ]);

        // 2b) Gajian 30 Jul — pelunasan kas bon, non-kas: 1.000.000.
        \App\Models\FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $gaji->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
            'source'                 => 'payroll',
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);

        $balanceSheetRef = new \ReflectionMethod($controller, 'balanceSheetData');
        $balanceSheetRef->setAccessible(true);
        $neraca = $balanceSheetRef->invoke($controller, 2026);

        $this->assertSame(0.0, $neraca['aset']['other_assets_total'],
            'Piutang Karyawan harus kembali 0 setelah kas bon dilunasi penuh (Temuan #1)');
        $this->assertTrue($neraca['balanced'],
            'Neraca wajib tetap seimbang setelah pelunasan kas bon non-kas (Temuan #1)');

        $labaRugiRef = new \ReflectionMethod($controller, 'incomeStatementData');
        $labaRugiRef->setAccessible(true);
        $labaRugi = $labaRugiRef->invoke($controller, 2026);

        $this->assertSame(4_800_000.0, $labaRugi['totalOpex'],
            'Beban Gaji Juli harus PENUH 4.800.000 (3.800.000 kas + 1.000.000 pelunasan kas bon non-kas), bukan 3.800.000 saja');
    }

    /**
     * Temuan #1 (Critical) — fix-specific: kategori aset yang dipakai sebagai
     * contra_fin_category_id harus ikut menaikkan/menurunkan saldo aset di
     * Neraca. direction='out' pada baris contra berarti kategori UTAMA naik
     * dan kategori LAWAN (aset) turun; direction='in' kebalikannya. Diuji
     * terisolasi dari skenario penuh supaya kedua arah kontra teruji sendiri.
     */
    public function test_kategori_aset_sebagai_lawan_transaksi_menambah_dan_mengurangi_saldo_neraca(): void
    {
        $beban   = FinCategory::create(['name' => 'Beban Gaji', 'type' => 'expense']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        // contra + direction='out': kategori utama (beban) naik, Piutang (lawan) turun 400rb.
        \App\Models\FinTransaction::create([
            'date' => '2026-07-30', 'direction' => 'out',
            'fin_category_id' => $beban->id, 'contra_fin_category_id' => $piutang->id,
            'amount' => 400_000,
        ]);
        // contra + direction='in': kategori utama (pendapatan) turun, Piutang (lawan) naik 900rb.
        \App\Models\FinTransaction::create([
            'date' => '2026-07-31', 'direction' => 'in',
            'fin_category_id' => $pendapatan->id, 'contra_fin_category_id' => $piutang->id,
            'amount' => 900_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $piutangRow = collect($data['aset']['other_assets'])->firstWhere('name', 'Piutang Karyawan');

        // Piutang: -400.000 (out) + 900.000 (in) = 500.000.
        $this->assertSame(500_000.0, $piutangRow['balance'],
            'Saldo Piutang Karyawan sebagai kategori lawan harus turun saat out, naik saat in');
    }

    /**
     * Temuan #1 — bukti regresi: untuk data yang HANYA memakai
     * fin_category_id (tanpa contra_fin_category_id sama sekali, persis
     * kondisi database saat ini), other_assets_total hasil balanceSheetData()
     * versi baru (dengan dua query tambahan atas contra_fin_category_id)
     * harus IDENTIK dengan oracle logika lama (hanya naik-turun dari
     * fin_category_id).
     */
    public function test_other_assets_identik_dengan_logika_lama_tanpa_transaksi_contra_regresi(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-10', 'direction' => 'out',
            'fin_category_id' => $piutang->id, 'cash_account_id' => $kas->id, 'amount' => 1_500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-20', 'direction' => 'in',
            'fin_category_id' => $piutang->id, 'cash_account_id' => $kas->id, 'amount' => 600_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'balanceSheetData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026);

        // Oracle: logika lama (SEBELUM fix Temuan #1) — hanya naik-turun dari fin_category_id.
        $endDate = '2026-12-31';
        $naik  = (float) \App\Models\FinTransaction::where('fin_category_id', $piutang->id)
            ->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
        $turun = (float) \App\Models\FinTransaction::where('fin_category_id', $piutang->id)
            ->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');
        $oldOtherAssetsTotal = $naik - $turun;

        $this->assertSame($oldOtherAssetsTotal, $actual['aset']['other_assets_total'],
            'other_assets_total harus identik dengan logika lama saat tidak ada transaksi contra sama sekali');
        $this->assertSame(900_000.0, $actual['aset']['other_assets_total']);
    }

    /**
     * Temuan #3 (Important) — fix-specific: begitu Tahap B mulai menulis
     * source='payroll'/'advance', beban/pendapatan itu harus tetap terhitung
     * di Laba Rugi. Filter lama `where('source','manual')` akan
     * menghilangkannya begitu saja tanpa galat.
     */
    public function test_income_statement_source_baru_tetap_terhitung_sebagai_opex_dan_pendapatan_lain(): void
    {
        $kas   = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $gaji  = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);
        $lain  = FinCategory::create(['name' => 'Pendapatan Lain', 'type' => 'income']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-30', 'direction' => 'out', 'fin_category_id' => $gaji->id,
            'cash_account_id' => $kas->id, 'amount' => 3_800_000, 'source' => 'payroll',
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'in', 'fin_category_id' => $lain->id,
            'cash_account_id' => $kas->id, 'amount' => 250_000, 'source' => 'advance',
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'incomeStatementData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026);

        $this->assertSame(3_800_000.0, $data['totalOpex'],
            'Beban dengan source=payroll harus tetap terhitung sebagai opex');
        $this->assertSame(250_000.0, $data['otherIncome'],
            'Pendapatan dengan source=advance harus tetap terhitung sebagai pendapatan lain');
    }

    /**
     * Temuan #4 (Important) — fix-specific: baris non-kas (contra_fin_category_id
     * terisi, cash_account_id null) tidak boleh membuat Buku Besar mengkredit
     * akun "Kas" hantu. Akun lawan harus berupa kategori lawan sungguhan.
     */
    public function test_buku_besar_kredit_kategori_lawan_bukan_kas_hantu_untuk_baris_non_kas(): void
    {
        $beban   = FinCategory::create(['name' => 'Beban Gaji', 'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        \App\Models\FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026, null);

        $kasHantu = collect($data['accounts'])->firstWhere('name', 'Kas');
        $this->assertNull($kasHantu, 'Tidak boleh ada akun "Kas" hantu untuk baris non-kas tanpa akun kas sama sekali');

        $lawan = collect($data['accounts'])->firstWhere('name', 'Piutang Karyawan');
        $this->assertNotNull($lawan, 'Akun lawan baris non-kas harus berupa kategori Piutang Karyawan');
        $this->assertSame('aset', $lawan['group'], 'Kategori lawan bertipe asset harus masuk kelompok aset');
        $this->assertSame(1_000_000.0, $lawan['credit'], 'Piutang Karyawan harus dikredit sebesar transaksi non-kas (direction=out)');

        $bebanRow = collect($data['accounts'])->firstWhere('name', 'Beban Gaji');
        $this->assertSame(1_000_000.0, $bebanRow['debit'], 'Kategori utama tetap didebit sebesar transaksi');
    }

    /**
     * Temuan #4 — bukti regresi: untuk data yang HANYA berupa transaksi kas
     * biasa (cash_account_id selalu terisi, persis kondisi database saat
     * ini), akun lawan ($lawanKey/$lawanName) hasil ledgerData() versi baru
     * harus IDENTIK dengan oracle $cashKey/$cashName lama — mengganti nama
     * variabel tidak boleh mengubah baris manapun untuk data yang tidak
     * pernah menyentuh contra_fin_category_id.
     */
    public function test_ledger_lawan_identik_dengan_cash_key_lama_untuk_transaksi_kas_biasa_regresi(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);

        \App\Models\FinTransaction::create([
            'date' => '2026-07-05', 'direction' => 'in', 'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id, 'amount' => 500_000,
        ]);
        \App\Models\FinTransaction::create([
            'date' => '2026-07-15', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id, 'amount' => 200_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026, null);

        $expected = $this->oldLedgerData(2026, null);

        $kasActual   = collect($actual['accounts'])->firstWhere('name', 'Kas Besar');
        $kasExpected = collect($expected['accounts'])->firstWhere('name', 'Kas Besar');

        $this->assertSame($kasExpected['debit'], $kasActual['debit']);
        $this->assertSame($kasExpected['credit'], $kasActual['credit']);
        $this->assertSame($kasExpected['group'], $kasActual['group']);
        $this->assertSame(500_000.0, $kasActual['debit']);
        $this->assertSame(200_000.0, $kasActual['credit']);
    }

    // ────────────────────────────────────────────────────────────────────
    // Fix wave — perbaikan N1 dari final review (2026-08-01): key kategori
    // lawan (contra_fin_category_id) disatukan dengan key kategori utama
    // ('cat-<id>' untuk keduanya, bukan 'contra-<id>' terpisah untuk peran
    // lawan), dan aturan penentuan group disatukan (satu fungsi berdasarkan
    // type kategori, dipakai baik untuk peran utama maupun lawan).
    // ────────────────────────────────────────────────────────────────────

    /**
     * Spek §4.2 skenario kas bon 3-baris jurnal: kategori Piutang Karyawan
     * jadi kategori UTAMA (fin_category_id) saat kas bon diberikan, lalu jadi
     * kategori LAWAN (contra_fin_category_id) saat dilunasi penuh saat
     * gajian. Sebelum perbaikan N1, ledgerData() memakai key 'contra-<id>'
     * untuk peran lawan — terpisah dari key 'cat-<id>' yang dipakai kategori
     * yang SAMA saat berperan sebagai kategori utama — sehingga "Piutang
     * Karyawan" muncul sebagai DUA baris akun terpisah di Buku Besar (satu
     * didebit 1.000.000 dari peran utama, satu dikredit 1.000.000 dari peran
     * lawan) alih-alih SATU akun dengan saldo bersih 0 (piutang sudah lunas).
     */
    public function test_kategori_piutang_karyawan_sebagai_utama_dan_lawan_menyatu_jadi_satu_akun(): void
    {
        $kas     = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);
        $gaji    = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        // 1) Kas bon 1.000.000 diberikan 10 Jul — Piutang Karyawan sebagai UTAMA.
        \App\Models\FinTransaction::create([
            'date'            => '2026-07-10',
            'direction'       => 'out',
            'fin_category_id' => $piutang->id,
            'cash_account_id' => $kas->id,
            'amount'          => 1_000_000,
            'source'          => 'advance',
        ]);

        // 2) Pelunasan kas bon saat gajian 30 Jul — Piutang Karyawan sebagai LAWAN.
        \App\Models\FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $gaji->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
            'source'                 => 'payroll',
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $data = $ref->invoke($controller, 2026, null);

        $piutangRows = collect($data['accounts'])->where('name', 'Piutang Karyawan')->values();

        $this->assertCount(1, $piutangRows,
            'Piutang Karyawan harus jadi SATU akun, bukan dua baris terpisah antara peran utama dan peran lawan');

        $akun = $piutangRows->first();
        $this->assertSame($akun['debit'], $akun['credit'],
            'Piutang Karyawan harus lunas (debit = credit) setelah dilunasi penuh lewat peran lawan');
        $this->assertSame(1_000_000.0, $akun['debit']);
        $this->assertSame(1_000_000.0, $akun['credit']);
    }

    /**
     * Perbaikan N1 — bukti regresi: untuk data existing (SEMUA transaksi kas,
     * tidak ada satu pun baris dengan contra_fin_category_id terisi), hasil
     * ledgerData() (accounts, group, profit) harus PERSIS SAMA dengan sebelum
     * perbaikan N1. Oracle di bawah adalah salinan verbatim ledgerData()
     * SEBELUM perbaikan N1 (key 'contra-<id>' terpisah + group kategori
     * lawan lewat ternary) — cabang contra pada oracle itu tidak pernah
     * tereksekusi untuk fixture ini karena tidak ada satu pun transaksi non-
     * kas, jadi satu-satunya kode yang benar-benar dibandingkan adalah key
     * dan group kategori UTAMA (tidak diubah oleh perbaikan N1).
     */
    public function test_ledger_data_identik_dengan_sebelum_perbaikan_n1_untuk_transaksi_kas_biasa_regresi(): void
    {
        $kas = \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);

        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);
        $beban      = FinCategory::create(['name' => 'Gaji Karyawan',  'type' => 'expense']);
        $asetLain   = FinCategory::create(['name' => 'Piutang Karyawan Lain', 'type' => 'asset']);

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
            'date' => '2026-07-18', 'direction' => 'out', 'fin_category_id' => $asetLain->id,
            'cash_account_id' => $kas->id, 'amount' => 150_000,
        ]);

        $controller = app(\App\Http\Controllers\FinanceReportController::class);
        $ref = new \ReflectionMethod($controller, 'ledgerData');
        $ref->setAccessible(true);
        $actual = $ref->invoke($controller, 2026, null);

        $expected = $this->oldLedgerDataSebelumPerbaikanN1(2026, null);

        $actualGroups   = collect($actual['accounts'])->pluck('group', 'name')->sortKeys()->all();
        $expectedGroups = collect($expected['accounts'])->pluck('group', 'name')->sortKeys()->all();
        $actualDebitCredit   = collect($actual['accounts'])->map(fn ($a) => ['debit' => $a['debit'], 'credit' => $a['credit']])->sortKeys()->all();
        $expectedDebitCredit = collect($expected['accounts'])->map(fn ($a) => ['debit' => $a['debit'], 'credit' => $a['credit']])->sortKeys()->all();

        $this->assertSame($expectedGroups, $actualGroups, 'group tiap akun harus identik dengan sebelum perbaikan N1 untuk data kas biasa');
        $this->assertSame($expectedDebitCredit, $actualDebitCredit, 'debit/credit tiap akun harus identik dengan sebelum perbaikan N1 untuk data kas biasa');
        $this->assertSame($expected['profit'], $actual['profit'], 'profit.income/profit.expense harus identik dengan sebelum perbaikan N1');
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

    /**
     * Salinan verbatim ledgerData() SEBELUM perbaikan N1 (commit 2dba2f0):
     * key kategori lawan terpisah ('contra-<id>'), group kategori lawan lewat
     * ternary asset/beban. Dipakai sebagai oracle regresi di atas — bukan
     * untuk dipanggil di kode produksi.
     */
    private function oldLedgerDataSebelumPerbaikanN1(int $year, $month): array
    {
        $q = \App\Models\FinTransaction::with(['category', 'cashAccount', 'contraCategory'])->whereYear('date', $year);
        if ($month) {
            $q->whereMonth('date', (int) $month);
        }
        $txns = $q->orderBy('date')->orderBy('id')->get();

        $acc = [];
        $touch = function (string $key, string $name, string $group) use (&$acc) {
            $acc[$key] ??= ['name' => $name, 'group' => $group, 'debit' => 0.0, 'credit' => 0.0, 'postings' => []];
        };

        foreach ($txns as $t) {
            $amt  = (float) $t->amount;
            $date = $t->date->format('Y-m-d');
            $lawanKey  = $t->cash_account_id ? 'cash-' . $t->cash_account_id : 'contra-' . $t->contra_fin_category_id;
            $catKey    = 'cat-' . $t->fin_category_id;
            $lawanName = $t->cashAccount?->name ?? $t->contraCategory?->name ?? 'Kas';
            $catName   = $t->category?->name ?? '-';

            $touch($lawanKey, $lawanName, $t->cash_account_id ? 'aset' : ($t->contraCategory?->type === 'asset' ? 'aset' : 'beban'));
            $touch($catKey, $catName, match ($t->category?->type) {
                'income' => 'pendapatan',
                'asset'  => 'aset',
                default  => 'beban',
            });

            if ($t->direction === 'in') {
                $acc[$lawanKey]['debit'] += $amt;
                $acc[$lawanKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => $amt, 'credit' => 0];
                $acc[$catKey]['credit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $lawanName, 'debit' => 0, 'credit' => $amt];
            } else {
                $acc[$catKey]['debit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $lawanName, 'debit' => $amt, 'credit' => 0];
                $acc[$lawanKey]['credit'] += $amt;
                $acc[$lawanKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => 0, 'credit' => $amt];
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
