<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * JAMINAN INTI (spec §7.4): invoice yang sudah disetujui tidak akan pernah
 * dihitung ulang, apa pun yang berubah pada rumus atau pada data tour.
 *
 * syncProformaTotal() dipanggil di tiga tempat — updateProforma(), lockBaseline(),
 * dan approve() — dan ketiganya didahului ensureNotApproved(). Test ini menutup
 * ketiga jalur itu sekaligus.
 *
 * Test ini WAJIB tetap hijau di setiap fase. Bila ia merah, hentikan pekerjaan:
 * artinya data keuangan yang sudah masuk pembukuan bisa berubah.
 */
class ApprovedInvoiceFrozenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function approvedInvoice(): Invoice
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertNotNull($invoice->approved_at, 'Prasyarat: invoice harus benar-benar tersetujui');
        $this->assertEquals(5_000_000, $invoice->total);

        return $invoice;
    }

    public function test_perubahan_pax_tour_tidak_menggeser_total_invoice_yang_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        // Ubah pax tour secara drastis SETELAH invoice disetujui.
        $invoice->tour->update(['pax' => 99]);

        $this->assertEquals(
            5_000_000,
            $invoice->fresh()->total,
            'Total invoice yang sudah masuk Keuangan tidak boleh ikut berubah'
        );
    }

    public function test_ketiga_jalur_perhitungan_ulang_ditolak_setelah_disetujui(): void
    {
        $invoice = $this->approvedInvoice();
        $user    = $this->salesUser();

        // Jalur 1 — updateProforma()
        $this->actingAs($user)
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 1,
            ])
            ->assertSessionHasErrors('invoice');

        // Jalur 2 — lockBaseline()
        $this->actingAs($user)
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        // Jalur 3 — approve()
        $this->actingAs($user)
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(5_000_000, $invoice->fresh()->total, 'Total tetap utuh setelah ketiga percobaan');
        $this->assertEquals(500_000, $invoice->fresh()->unit_price, 'unit_price tetap utuh');
    }

    public function test_invoice_yang_disetujui_tidak_bisa_dihapus(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->delete(route('invoices.destroy', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertNotNull($invoice->fresh(), 'Invoice yang sudah masuk Keuangan tidak boleh hilang');
    }

    public function test_item_tidak_bisa_diubah_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Sisipan setelah disetujui', 'unit_cost' => 1, 'unit_sell' => 2]],
            ])
            ->assertSessionHasErrors('invoice');

        $this->assertCount(0, $invoice->fresh()->items);
    }
}
