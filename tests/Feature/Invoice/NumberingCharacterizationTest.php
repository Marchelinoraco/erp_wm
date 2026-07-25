<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci kode tipe pada nomor invoice: INV-<tahun>-<kode>-NNNN.
 *
 * Penomoran TIDAK termasuk yang diubah Fase 1-5. Test ini adalah penjaga agar
 * refactor tidak menggesernya tanpa sengaja.
 */
class NumberingCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Kode angka per jenis penjualan, dari Tour::TYPE_CODES & resolveTypeCode(). */
    private const EXPECTED_CODES = [
        'rental'    => '13',
        'guide'     => '14',
        'mice'      => '15',
        'hotel'     => '16',
        'document'  => '17',
        'ticketing' => '18',
    ];

    public function test_kode_tipe_muncul_pada_nomor_invoice(): void
    {
        $year = now()->year;

        foreach (self::EXPECTED_CODES as $type => $code) {
            $tour   = $this->makeTour($type);
            $number = Invoice::nextNumber($tour);

            $this->assertStringStartsWith(
                "INV-{$year}-{$code}-",
                $number,
                "Jenis {$type} seharusnya memakai kode {$code}"
            );
        }
    }

    public function test_tour_dibedakan_menurut_arah(): void
    {
        $year = now()->year;

        $inbound  = $this->makeTour('tour', ['tour_direction' => 'inbound']);
        $outbound = $this->makeTour('tour', ['tour_direction' => 'outbound']);

        $this->assertStringStartsWith("INV-{$year}-11-", Invoice::nextNumber($inbound));
        $this->assertStringStartsWith("INV-{$year}-12-", Invoice::nextNumber($outbound));
    }

    public function test_arah_kosong_jatuh_ke_inbound(): void
    {
        $year = now()->year;
        $tour = $this->makeTour('tour', ['tour_direction' => null]);

        $this->assertStringStartsWith("INV-{$year}-11-", Invoice::nextNumber($tour));
    }

    public function test_urutan_berjalan_per_jenis_bukan_global(): void
    {
        $guide = $this->makeTour('guide');
        $hotel = $this->makeTour('hotel');

        $this->makeInvoice($guide, 100_000);

        // Hotel belum punya invoice sama sekali, jadi tetap mulai dari 0001.
        $this->assertStringEndsWith('0001', Invoice::nextNumber($hotel));

        // Guide sudah punya satu, jadi lanjut ke 0002.
        $this->assertStringEndsWith('0002', Invoice::nextNumber($guide));
    }
}
