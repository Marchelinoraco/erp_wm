<?php

namespace Tests\Feature\Invoice;

use App\Models\Bill;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci apa yang terjadi saat invoice disetujui: nomor keuangan terbit dan
 * item bersupplier menjadi Bill draft bernominal 0.
 *
 * Spec §3.4 menyatakan Keuangan tidak tersentuh Fase 1-5. Test ini buktinya.
 */
class ApprovalSideEffectsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_nomor_keuangan_terbit_saat_disetujui(): void
    {
        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->assertNull($invoice->finance_number, 'Sebelum disetujui belum punya nomor keuangan');

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertStringStartsWith('INV-' . now()->year . '-', $invoice->finance_number);
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->approved_at);
    }

    public function test_nomor_keuangan_tidak_membedakan_jenis_penjualan(): void
    {
        // Berbeda dari `number` yang mengandung kode tipe, finance_number urut
        // global mengikuti urutan masuk Keuangan.
        $year = now()->year;

        foreach (['guide', 'hotel'] as $index => $type) {
            $tour    = $this->makeTour($type);
            $invoice = $this->makeInvoice($tour, 500_000);
            $invoice->update(['baseline_total' => $invoice->total]);

            $this->actingAs($this->salesUser())
                ->post(route('invoices.approve', $invoice))
                ->assertRedirect();

            $this->assertSame(
                sprintf('INV-%d-%04d', $year, $index + 1),
                $invoice->fresh()->finance_number
            );
        }
    }

    public function test_item_bersupplier_menjadi_bill_draft_bernominal_nol(): void
    {
        $supplier = Supplier::create(['name' => 'Hotel Mitra']);
        $product  = Product::create([
            'name'        => 'Kamar Deluxe',
            'type'        => 'hotel',
            'cost'        => 500_000,
            'sell'        => 750_000,
            'supplier_id' => $supplier->id,
        ]);

        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 500_000);

        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'product_id'   => $product->id,
            'product_type' => $product->type,
            'description'  => $product->name,
            'qty'          => 1,
            'nights'       => 1,
            'unit_cost'    => $product->cost,
            'unit_sell'    => $product->sell,
        ]);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $bill = Bill::where('tour_id', $tour->id)->firstOrFail();

        $this->assertEquals($supplier->id, $bill->supplier_id);
        $this->assertEquals(0, $bill->amount, 'Nominal sengaja 0 — akuntan yang mengisi');
        $this->assertSame('unpaid', $bill->status);
    }

    public function test_item_tanpa_supplier_tidak_membuat_bill(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);

        // Item manual hasil tempel massal — tidak punya product_id sama sekali.
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Item manual tanpa produk',
            'qty'         => 1,
            'nights'      => 1,
            'unit_cost'   => 100_000,
            'unit_sell'   => 150_000,
        ]);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $this->assertSame(0, Bill::where('tour_id', $tour->id)->count());
    }
}
