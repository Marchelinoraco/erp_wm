<?php

namespace Tests\Feature\SalesLine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D3/D4: aturan uang per jenis hanya hidup di backend dan disalurkan ke Vue
 * lewat payload Inertia halaman Tours/Edit. D5: hanya properti yang benar-benar
 * dikonsumsi yang dikirim — unitPriceLabel TIDAK dikirim karena satuannya
 * masih ditunda (D2).
 */
class SalesLinePropPayloadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_payload_memuat_profit_from_revenue_yang_benar_per_jenis(): void
    {
        $harapan = [
            'tour' => true, 'hotel' => false, 'guide' => false, 'rental' => false,
            'mice' => false, 'document' => false, 'ticketing' => false,
        ];

        foreach ($harapan as $type => $expected) {
            $tour = $this->makeTour($type);

            $this->actingAs($this->salesUser())
                ->get(route('tours.edit', $tour->id))
                ->assertInertia(fn ($page) => $page
                    ->where('salesLine.key', $type)
                    ->where('salesLine.profitFromRevenue', $expected));
        }
    }

    public function test_payload_tidak_mengirim_unit_price_label(): void
    {
        // D5 + D2: satuan per jenis belum diputuskan, jadi tidak boleh bocor
        // ke frontend dalam bentuk apa pun.
        $tour = $this->makeTour('rental');

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(fn ($page) => $page->missing('salesLine.unitPriceLabel'));
    }
}
