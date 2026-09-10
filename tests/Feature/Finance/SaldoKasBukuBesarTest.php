<?php

namespace Tests\Feature\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Buku Besar menampilkan akun kas di bawah judul "KAS & BANK (ASET)" — pembaca
 * membacanya sebagai saldo. Tapi ledgerData() menghitungnya sebagai `debit −
 * kredit` dari transaksi TAHUN TERPILIH SAJA, tanpa opening_balance. Yang
 * tampil sebenarnya mutasi periode, bukan saldo.
 *
 * cashFlowData() sudah benar (`opening_balance + masuk − keluar`, sepanjang
 * waktu), jadi dua halaman menghitung akun yang sama dengan dua cara berbeda.
 *
 * Per 2026-09-10 keduanya kebetulan menunjukkan angka sama karena seluruh
 * opening_balance masih 0 dan semua transaksi ada di tahun 2026 — dua kesalahan
 * yang saling menutupi. Bugnya laten dan akan terbuka begitu ada transaksi
 * tahun 2027, atau begitu akuntan mengisi saldo awal dari rekening koran.
 */
class SaldoKasBukuBesarTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_saldo_kas_di_buku_besar_menyertakan_saldo_awal_dan_tahun_sebelumnya(): void
    {
        $akuntan = $this->accountantUser();

        $bca = CashAccount::create([
            'name'            => 'Bank BCA Uji',
            'type'            => 'bank',
            'opening_balance' => 100_000_000,
        ]);

        $beban = FinCategory::where('name', 'Biaya Supplier')->firstOrFail();

        // Tahun lalu — tidak boleh hilang dari saldo hanya karena laporan
        // yang dibuka tahun 2026.
        FinTransaction::create([
            'date' => '2025-11-20', 'direction' => 'out', 'amount' => 10_000_000,
            'fin_category_id' => $beban->id, 'cash_account_id' => $bca->id, 'source' => 'manual',
        ]);

        FinTransaction::create([
            'date' => '2026-07-13', 'direction' => 'out', 'amount' => 63_000_000,
            'fin_category_id' => $beban->id, 'cash_account_id' => $bca->id, 'source' => 'manual',
        ]);

        $props = $this->actingAs($akuntan)
            ->get(route('finance.ledger', ['year' => 2026]))
            ->assertOk()
            ->viewData('page')['props'];

        $baris = collect($props['accounts'])->firstWhere('name', 'Bank BCA Uji');

        $this->assertNotNull($baris, 'Akun kas harus muncul di Buku Besar.');
        $this->assertSame(
            27_000_000.0,
            (float) $baris['balance'],
            'Saldo = 100jt saldo awal − 10jt (2025) − 63jt (2026). Tanpa ini, kolom bertajuk '
            . '"KAS & BANK (ASET)" menampilkan mutasi periode tetapi dibaca sebagai saldo — '
            . 'dan bisa tampil minus padahal rekeningnya berisi.'
        );
    }
}
