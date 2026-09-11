<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Laporan umur piutang & hutang di halaman Invoice & Tagihan.
 *
 * Umur dihitung dari TANGGAL DOKUMEN, bukan jatuh tempo — keputusan sadar
 * (10 Sep 2026): tagihan pemasok tidak punya jatuh tempo default, jadi umur
 * berbasis jatuh tempo akan timpang untuk sisi hutang.
 *
 * Dua aturan cakupan yang mudah salah dan mahal akibatnya:
 *  - Hanya invoice DISETUJUI yang masuk piutang. Kalau draft ikut terhitung,
 *    totalnya tidak akan cocok dengan kartu Piutang (AR) maupun Neraca.
 *  - Yang sudah lunas tidak punya umur. Sisa nol bukan piutang.
 */
class UmurPiutangHutangTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function bayar(int $invoiceId, float $jumlah): void
    {
        InvoicePayment::create([
            'invoice_id' => $invoiceId,
            'date'       => now()->toDateString(),
            'amount'     => $jumlah,
            'amount_idr' => $jumlah,
            'method'     => 'transfer',
        ]);
    }

    public function test_umur_piutang_dikelompokkan_dari_tanggal_invoice(): void
    {
        $tour = $this->makeTour('tour'); // pax 10

        // 45 hari, dibayar sebagian → sisa 6 jt masuk kelompok 31–60.
        $sebagian = $this->approveInvoice(
            $this->makeInvoice($tour, 1_000_000, ['date' => now()->subDays(45)->toDateString()])
        );
        $this->bayar($sebagian->id, 4_000_000);

        // 10 hari, belum dibayar → 5 jt masuk kelompok 0–30.
        $baru = $this->approveInvoice(
            $this->makeInvoice($tour, 500_000, ['date' => now()->subDays(10)->toDateString()])
        );

        // 100 hari tapi LUNAS → tidak punya umur, tidak boleh muncul di mana pun.
        $lunas = $this->approveInvoice(
            $this->makeInvoice($tour, 200_000, ['date' => now()->subDays(100)->toDateString()])
        );
        $this->bayar($lunas->id, 2_000_000);

        // Draft berumur 45 hari — bernilai besar, sengaja, supaya kalau ikut
        // terhitung kesalahannya langsung kentara.
        $this->makeInvoice($tour, 9_900_000, ['date' => now()->subDays(45)->toDateString()]);

        $props = $this->actingAs($this->accountantUser())
            ->get(route('finance.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $umur = collect($props['ar_aging'])->keyBy('key');

        $this->assertEquals(5_000_000, (float) $umur['0-30']['total'], 'Invoice 10 hari yang belum dibayar.');
        $this->assertSame(1, $umur['0-30']['count']);

        $this->assertEquals(
            6_000_000,
            (float) $umur['31-60']['total'],
            'Sisa setelah pembayaran sebagian, bukan nilai penuh invoice. Draft 99 jt yang seumur TIDAK boleh ikut.'
        );
        $this->assertSame(1, $umur['31-60']['count']);

        $this->assertEquals(0, (float) $umur['61-90']['total']);
        $this->assertEquals(0, (float) $umur['90+']['total'], 'Invoice 100 hari sudah lunas — sisa nol bukan piutang.');
    }

    public function test_umur_hutang_dikelompokkan_dari_tanggal_tagihan(): void
    {
        $tour = $this->makeTour('tour');

        // 120 hari, belum dibayar sama sekali → seluruhnya masuk "> 90 hari".
        Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Sewa bus lama',
            'category'    => 'transport',
            'date'        => now()->subDays(120)->toDateString(),
            'amount'      => 7_000_000,
            'status'      => 'unpaid',
        ]);

        // 70 hari, dibayar sebagian → sisa 1,5 jt masuk 61–90.
        $sebagian = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Hotel',
            'category'    => 'hotel',
            'date'        => now()->subDays(70)->toDateString(),
            'amount'      => 2_000_000,
            'status'      => 'partial',
        ]);
        BillPayment::create([
            'bill_id' => $sebagian->id,
            'date'    => now()->toDateString(),
            'amount'  => 500_000,
            'method'  => 'transfer',
        ]);

        // Lunas — tidak masuk daftar tagihan tertunggak sama sekali.
        Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Guide',
            'category'    => 'guide',
            'date'        => now()->subDays(120)->toDateString(),
            'amount'      => 9_000_000,
            'status'      => 'paid',
        ]);

        $props = $this->actingAs($this->accountantUser())
            ->get(route('finance.index'))
            ->assertOk()
            ->viewData('page')['props'];

        $umur = collect($props['ap_aging'])->keyBy('key');

        $this->assertEquals(1_500_000, (float) $umur['61-90']['total'], 'Sisa setelah pembayaran sebagian.');
        $this->assertSame(1, $umur['61-90']['count']);

        $this->assertEquals(7_000_000, (float) $umur['90+']['total'], 'Tagihan lunas yang seumur TIDAK boleh ikut.');
        $this->assertSame(1, $umur['90+']['count']);

        $this->assertEquals(0, (float) $umur['0-30']['total']);
    }
}
