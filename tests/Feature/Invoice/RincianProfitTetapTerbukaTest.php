<?php

namespace Tests\Feature\Invoice;

use App\Models\Bill;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Kebalikan dari kunci lama: sejak keputusan D1 (lihat
 * docs/superpowers/specs/2026-08-05-rincian-profit-tetap-terbuka-design.md),
 * Rincian Profit TIDAK PERNAH terkunci oleh status approval invoice — sales
 * maupun akuntan/admin bisa menambah, mengubah, atau menghapus item kapan
 * pun. Test ini menutup jalur itu; ApprovedInvoiceFrozenTest tetap menutup
 * jalur proforma/baseline/approve/delete-invoice yang TIDAK berubah.
 */
class RincianProfitTetapTerbukaTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function approvedInvoice(): Invoice
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);

        return $this->approveInvoice($invoice);
    }

    public function test_item_bisa_ditambahkan_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Sisipan setelah disetujui', 'unit_cost' => 1, 'unit_sell' => 2]],
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertCount(1, $invoice->fresh()->items);
    }

    public function test_item_bisa_diubah_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000])
            ->assertSessionDoesntHaveErrors();

        $this->assertEquals('600000.00', (string) $item->fresh()->unit_cost);
    }

    public function test_item_bisa_dihapus_setelah_invoice_disetujui_bila_belum_ada_bill(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($item->fresh());
    }

    public function test_item_yang_sudah_punya_bill_tidak_bisa_dihapus(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);
        Bill::create([
            'tour_id' => $invoice->tour_id, 'invoice_item_id' => $item->id,
            'description' => 'Hotel test', 'category' => 'hotel',
            'date' => now()->toDateString(), 'amount' => 500_000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item))
            ->assertSessionHasErrors('invoice');

        $this->assertNotNull($item->fresh(), 'Item tidak boleh terhapus bila sudah punya Bill');
    }

    public function test_mengubah_item_yang_sudah_punya_bill_tetap_boleh(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);
        Bill::create([
            'tour_id' => $invoice->tour_id, 'invoice_item_id' => $item->id,
            'description' => 'Hotel test', 'category' => 'hotel',
            'date' => now()->toDateString(), 'amount' => 500_000, 'status' => 'unpaid',
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 550_000])
            ->assertSessionDoesntHaveErrors();

        $this->assertEquals('550000.00', (string) $item->fresh()->unit_cost);
    }

    public function test_menambah_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Extra bed pasca approve', 'unit_cost' => 100_000, 'unit_sell' => 150_000]],
            ]);

        $this->assertTrue(
            $invoice->tour->histories()->where('description', 'like', '%Extra bed pasca approve%')->exists(),
            'Penambahan item pasca-approve harus tercatat di riwayat tour'
        );
    }

    public function test_mengubah_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel test', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000]);

        $this->assertTrue(
            $invoice->tour->histories()
                ->where('description', 'like', '%diubah pasca-approve%')
                ->where('description', 'like', '%unit_cost%')
                ->exists(),
            'Perubahan item pasca-approve harus tercatat di riwayat tour dengan menyebut field yang berubah'
        );
    }

    public function test_menghapus_item_setelah_disetujui_mencatat_riwayat_tour(): void
    {
        $invoice = $this->approvedInvoice();
        $item    = $invoice->items()->create([
            'description' => 'Hotel dihapus lagi', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $this->actingAs($this->salesUser())
            ->delete(route('invoice-items.destroy', $item));

        $this->assertTrue(
            $invoice->tour->histories()->where('description', 'like', '%Hotel dihapus lagi%')->exists(),
            'Penghapusan item pasca-approve harus tercatat di riwayat tour'
        );
    }

    public function test_edit_item_sebelum_disetujui_tidak_menambah_riwayat_tour(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);
        $item    = $invoice->items()->create([
            'description' => 'Belum disetujui', 'qty' => 1, 'nights' => 1,
            'unit_cost' => 500_000, 'unit_sell' => 700_000, 'sort_order' => 1,
        ]);

        $historiesSebelum = $tour->histories()->count();

        $this->actingAs($this->salesUser())
            ->patch(route('invoice-items.update', $item), ['unit_cost' => 600_000]);

        $this->assertEquals(
            $historiesSebelum,
            $tour->histories()->count(),
            'Edit sebelum invoice disetujui tidak boleh menambah riwayat tour'
        );
    }

    public function test_tagihan_customer_tidak_berubah_oleh_edit_rincian_profit_pasca_approve(): void
    {
        $invoice = $this->approvedInvoice();
        $totalSebelum = $invoice->total;

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Item baru', 'unit_cost' => 1, 'unit_sell' => 9_999_999]],
            ]);

        $this->assertEquals(
            $totalSebelum,
            $invoice->fresh()->total,
            'Rincian Profit adalah internal — menambah item tidak boleh menggeser tagihan customer (unit_price × pax)'
        );
    }
}
