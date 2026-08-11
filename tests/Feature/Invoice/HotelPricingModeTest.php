<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Dua cara hitung invoice hotel. Pengunci terpenting di berkas ini adalah
 * bahwa pricing_mode NULL — keadaan seluruh invoice hotel yang sudah ada —
 * berperilaku persis seperti sebelum fitur ini ada.
 */
class HotelPricingModeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_invoice_baru_bernilai_null_dan_kolomnya_ada(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->assertNull($invoice->pricing_mode, 'Invoice baru tidak memilih mode apa pun sampai sales memilihnya');
    }

    public function test_konstanta_mode_terdefinisi(): void
    {
        $this->assertSame('per_pax', Invoice::PRICING_PER_PAX);
        $this->assertSame('per_room_night', Invoice::PRICING_PER_ROOM_NIGHT);
        $this->assertSame(['per_pax', 'per_room_night'], Invoice::PRICING_MODES);
    }

    public function test_mode_tersimpan_dan_terbaca_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $invoice->update(['pricing_mode' => Invoice::PRICING_PER_ROOM_NIGHT]);

        $this->assertSame('per_room_night', $invoice->fresh()->pricing_mode);
    }
}
