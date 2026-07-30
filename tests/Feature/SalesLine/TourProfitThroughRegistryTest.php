<?php

namespace Tests\Feature\SalesLine;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Tour;
use App\Models\TourItem;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\Support\FakeSalesLineRule;
use Tests\Support\FakeSalesLineRuleRegistry;
use Tests\TestCase;

/**
 * `Tour::usesInvoiceProfit()` memilih dari mana angka total_cost/total_sell/
 * profit/margin diambil. Sebelum task ini ia meng-hardcode `type === 'tour'`,
 * padahal LABEL di CostingPanel.vue sudah dialihkan ke registry di Task 4 —
 * label dan angka bisa desync begitu aturan rule berubah.
 */
class TourProfitThroughRegistryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /**
     * Tour dengan DUA sumber angka yang berbeda sekaligus, supaya jelas mana
     * yang dipakai: item tour (cost 1jt / sell 2jt) dan invoice disetujui
     * (tagihan 10jt, item invoice cost 3jt / sell 4jt).
     */
    private function tourDenganKeduaSumber(string $type): Tour
    {
        $product = Product::create([
            'name' => 'Kamar Deluxe',
            'type' => 'hotel',
            'cost' => 300_000,
            'sell' => 400_000,
        ]);

        $tour = $this->makeTour($type, ['pax' => 10]);

        TourItem::create([
            'tour_id'   => $tour->id,
            'unit_cost' => 1_000_000,
            'unit_sell' => 2_000_000,
        ]);

        $invoice = $this->makeInvoice($tour, 1_000_000);

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

        $this->approveInvoice($invoice->fresh());

        return $tour->fresh(['items', 'invoices.items']);
    }

    public function test_tipe_tour_mengambil_angka_dari_invoice(): void
    {
        $tour = $this->tourDenganKeduaSumber('tour');

        // Dari invoice: cost = Σ line_cost item invoice, sell = Σ total_idr.
        // Item tour (1jt/2jt) diabaikan sepenuhnya.
        $this->assertSame(3_000_000.0, $tour->total_cost);
        $this->assertSame(10_000_000.0, $tour->total_sell);
        $this->assertSame(7_000_000.0, $tour->profit);
        $this->assertSame(70.0, $tour->margin);
    }

    public function test_tipe_lain_mengambil_angka_dari_item_tour(): void
    {
        $tour = $this->tourDenganKeduaSumber('rental');

        // Dari item tour. Invoice 10jt yang sudah disetujui diabaikan.
        $this->assertSame(1_000_000.0, $tour->total_cost);
        $this->assertSame(2_000_000.0, $tour->total_sell);
        $this->assertSame(1_000_000.0, $tour->profit);
        $this->assertSame(50.0, $tour->margin);
    }

    public function test_sumber_angka_benar_benar_ditentukan_registry(): void
    {
        // Registry palsu menyatakan `rental` menghitung profit dari tagihan.
        // Bila Tour membaca registry, angkanya beralih ke jalur invoice.
        // Bila masih hardcode `type === 'tour'`, angkanya tetap dari item tour.
        //
        // totalMultiplier: 10 (bukan default 1.0) SENGAJA disamakan dengan pax
        // fixture (10): registry palsu ini juga kepakai Invoice::syncProformaTotal()
        // (Invoice.php:111) saat tourDenganKeduaSumber() membuat invoice di bawah,
        // karena keduanya membaca binding container yang sama. Tanpa penyesuaian
        // ini, total_idr invoice ikut terhitung lewat FakeSalesLineRule (yang
        // sengaja mengabaikan multiplier asli) dan jadi 1jt, bukan 10jt — bukan
        // karena Tour gagal membaca registry, tapi karena efek samping tak
        // terkait yang mengotori pembuktian test ini.
        $this->app->instance(
            SalesLineRuleRegistry::class,
            new FakeSalesLineRuleRegistry(new FakeSalesLineRule(totalMultiplier: 10, profitFromRevenue: true))
        );

        $tour = $this->tourDenganKeduaSumber('rental');

        $this->assertSame(3_000_000.0, $tour->total_cost);
        $this->assertSame(10_000_000.0, $tour->total_sell);
    }
}
