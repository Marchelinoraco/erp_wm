<?php

namespace Tests\Unit\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spek §3.3: satu entri 3 baris dipecah jadi dua entri 2 baris. Mesin jurnal
 * tidak dirombak — hanya akun lawannya yang boleh berupa kategori.
 */
class JournalLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_kas_tetap_berlawan_akun_kas(): void
    {
        $kas   = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        $trx = FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'out',
            'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id,
            'amount'          => 3_800_000,
        ]);

        $this->assertSame([
            ['account' => 'Gaji Karyawan', 'debit' => 3_800_000.0, 'credit' => 0],
            ['account' => 'Kas Besar',     'debit' => 0,           'credit' => 3_800_000.0],
        ], $trx->journalLines());
    }

    public function test_transaksi_non_kas_berlawan_kategori(): void
    {
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $trx = FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $this->assertSame([
            ['account' => 'Gaji Karyawan',    'debit' => 1_000_000.0, 'credit' => 0],
            ['account' => 'Piutang Karyawan', 'debit' => 0,           'credit' => 1_000_000.0],
        ], $trx->journalLines());
    }

    /**
     * Fix round 1 (review Task 3): JournalLinesTest bawaan brief hanya menguji
     * direction='out'. journalLines() dipakai langsung oleh
     * FinanceReportController::journalData() (laporan Jurnal produksi), jadi
     * klaim non-regresi untuk arah 'in' harus dibuktikan lewat test yang
     * benar-benar lulus, bukan cuma inspeksi kode.
     */
    public function test_transaksi_kas_masuk_tetap_berlawan_akun_kas(): void
    {
        $kas        = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);

        $trx = FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'in',
            'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id,
            'amount'          => 3_800_000,
        ]);

        $this->assertSame([
            ['account' => 'Kas Besar',      'debit' => 3_800_000.0, 'credit' => 0],
            ['account' => 'Penjualan Tour', 'debit' => 0,           'credit' => 3_800_000.0],
        ], $trx->journalLines());
    }

    public function test_transaksi_masuk_non_kas_berlawan_kategori(): void
    {
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour',   'type' => 'income']);
        $piutang    = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $trx = FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'in',
            'fin_category_id'        => $pendapatan->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);

        $this->assertSame([
            ['account' => 'Piutang Karyawan', 'debit' => 1_000_000.0, 'credit' => 0],
            ['account' => 'Penjualan Tour',    'debit' => 0,           'credit' => 1_000_000.0],
        ], $trx->journalLines());
    }
}
