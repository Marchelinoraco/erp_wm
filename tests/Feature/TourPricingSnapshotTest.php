<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tour;
use App\Models\TourItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourPricingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_product_price_does_not_affect_existing_tour_item_snapshot(): void
    {
        $product = Product::create([
            'name' => 'Hotel Swiss-Bel',
            'type' => 'hotel',
            'cost' => 500000,
            'sell' => 700000,
        ]);

        $tour = Tour::create(['pax' => 2, 'type' => 'tour']);

        $item = TourItem::create([
            'tour_id'    => $tour->id,
            'product_id' => $product->id,
            'qty'        => 1,
            'nights'     => 1,
            'unit_cost'  => $product->cost,
            'unit_sell'  => $product->sell,
        ]);

        // Harga produk naik setelah item tour ini dibuat.
        $product->update(['cost' => 900000, 'sell' => 1200000]);

        $item->refresh();

        $this->assertEquals(500000, (float) $item->unit_cost, 'Snapshot cost tidak boleh ikut berubah saat harga produk berubah.');
        $this->assertEquals(700000, (float) $item->unit_sell, 'Snapshot sell tidak boleh ikut berubah saat harga produk berubah.');
    }
}
