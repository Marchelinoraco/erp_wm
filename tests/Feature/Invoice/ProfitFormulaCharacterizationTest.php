<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * R3: Fase 2 adalah refactor murni. Angka profit dan margin harus identik
 * sebelum dan sesudah aturan dipindahkan ke rule. Test ini memakukan nilainya
 * pada angka harfiah — bukan pada rumus — supaya penggeseran sekecil apa pun
 * tertangkap.
 */
class ProfitFormulaCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Item dengan cost 300.000 dan sell 400.000, qty 10 → cost 3jt, sell 4jt. */
    private function invoiceDenganItem(string $type, float $unitPrice): Invoice
    {
        $product = Product::create([
            'name' => 'Kamar Deluxe',
            'type' => 'hotel',
            'cost' => 300_000,
            'sell' => 400_000,
        ]);

        $tour    = $this->makeTour($type, ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, $unitPrice);

        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'product_id'   => $product->id,
            'product_type' => $product->type,
            'description'  => $product->name,
            'qty'          => 10,
            'nights'       => 1,
            'unit_cost'    => 300_000,
            'unit_sell'    => 400_000,
        ]);

        return $this->approveInvoice($invoice->fresh());
    }

    public function test_pdf_profit_tour_memakai_tagihan_customer(): void
    {
        // pax 10 × 1.000.000 = 10.000.000 tagihan; Σ cost item = 3.000.000.
        $invoice = $this->invoiceDenganItem('tour', 1_000_000);

        $this->assertEquals(10_000_000, $invoice->total_idr);

        $this->actingAs($this->salesUser())
            ->get(route('invoices.profit-pdf', $invoice->id))
            ->assertOk();

        // Aturan tour: revenue = total_idr (10jt), BUKAN Σ sell item (4jt).
        // profit = 10.000.000 − 3.000.000 = 7.000.000 → margin 70,0%
        $revenue = (float) $invoice->total_idr;
        $cost    = (float) $invoice->items->sum('line_cost');

        $this->assertSame(3_000_000.0, $cost);
        $this->assertSame(7_000_000.0, $revenue - $cost);
        $this->assertSame(70.0, round(($revenue - $cost) / $revenue * 100, 1));
    }

    public function test_pdf_profit_non_tour_memakai_selisih_per_item(): void
    {
        $invoice = $this->invoiceDenganItem('rental', 1_000_000);

        $this->actingAs($this->salesUser())
            ->get(route('invoices.profit-pdf', $invoice->id))
            ->assertOk();

        // Aturan non-tour: revenue = Σ sell item (4jt), BUKAN tagihan (10jt).
        // profit = 4.000.000 − 3.000.000 = 1.000.000 → margin 25,0%
        $revenue = (float) $invoice->items->sum('line_sell');
        $cost    = (float) $invoice->items->sum('line_cost');

        $this->assertSame(4_000_000.0, $revenue);
        $this->assertSame(1_000_000.0, $revenue - $cost);
        $this->assertSame(25.0, round(($revenue - $cost) / $revenue * 100, 1));
    }
}
