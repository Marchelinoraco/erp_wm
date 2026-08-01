<?php

namespace Tests\Feature\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task 2 fix round 1 (review): kolom `source_baru` di cabang SQLite migrasi
 * 2026_07_31_000001_add_contra_account_to_fin_transactions.php sempat disalin
 * apa adanya dari brief tanpa ->default('manual') -- padahal kolom `source` asli
 * (dan cabang MySQL lewat ALTER ... DEFAULT 'manual') selalu punya default itu.
 * Akibatnya FinTransaction yang dibuat tanpa 'source' eksplisit jadi NULL di
 * SQLite/uji tapi tetap 'manual' di MySQL/production -- celah divergensi yang
 * baru kelihatan kalau ada uji (Task 3+) yang lupa set 'source'.
 *
 * Task 3 (spek §3.2): tepat satu dari cash_account_id / contra_fin_category_id
 * harus terisi. Dua-duanya terisi berarti jurnalnya ambigu; dua-duanya kosong
 * berarti tidak ada lawan sama sekali. Keduanya menghasilkan pembukuan yang
 * tidak seimbang, jadi model melempar InvalidArgumentException saat disimpan.
 */
class ContraAccountRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_default_ke_manual_saat_tidak_diisi_eksplisit(): void
    {
        $kategori = FinCategory::create(['name' => 'Operasional', 'type' => 'expense']);
        $kas = CashAccount::create(['name' => 'Kas Uji', 'type' => 'cash']);

        $txn = FinTransaction::create([
            'date'            => '2026-07-31',
            'direction'       => 'out',
            'fin_category_id' => $kategori->id,
            'cash_account_id' => $kas->id,
            'amount'          => 10000,
        ]);

        $this->assertSame('manual', $txn->fresh()->source);
    }

    public function test_menolak_transaksi_dengan_kas_dan_kategori_lawan_sekaligus(): void
    {
        $kas     = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban   = FinCategory::create(['name' => 'Gaji Karyawan',    'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->expectException(InvalidArgumentException::class);

        FinTransaction::create([
            'date'                   => '2026-07-30',
            'direction'              => 'out',
            'fin_category_id'        => $beban->id,
            'cash_account_id'        => $kas->id,
            'contra_fin_category_id' => $piutang->id,
            'amount'                 => 1_000_000,
        ]);
    }

    public function test_menolak_transaksi_tanpa_lawan_sama_sekali(): void
    {
        $beban = FinCategory::create(['name' => 'Gaji Karyawan', 'type' => 'expense']);

        $this->expectException(InvalidArgumentException::class);

        FinTransaction::create([
            'date'            => '2026-07-30',
            'direction'       => 'out',
            'fin_category_id' => $beban->id,
            'amount'          => 1_000_000,
        ]);
    }
}
