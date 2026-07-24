<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci gerbang tiga tahap: baseline → detail → approved.
 *
 * Seluruh gerbang ini TIDAK berubah pada Fase 1-5 (spec §3.4). Test ini
 * memastikan refactor rumus tidak merembet ke aturan alurnya.
 */
class StageGateCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_satu_tour_hanya_boleh_punya_satu_invoice(): void
    {
        $tour = $this->makeTour('tour');
        $user = $this->salesUser();

        $this->actingAs($user)
            ->post(route('invoices.store', $tour))
            ->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);

        $this->actingAs($user)
            ->post(route('invoices.store', $tour))
            ->assertSessionHasErrors('invoice');

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoice_baru_dimulai_pada_tahap_baseline(): void
    {
        $tour = $this->makeTour('hotel');

        $this->actingAs($this->salesUser())
            ->post(route('invoices.store', $tour))
            ->assertRedirect();

        $invoice = $tour->invoices()->firstOrFail();

        $this->assertSame('draft', $invoice->status);
        $this->assertSame('baseline', $invoice->stage);
        $this->assertNull($invoice->approved_at);
        $this->assertEquals(0, $invoice->baseline_total);
    }

    public function test_patokan_ditolak_bila_total_masih_nol(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 0);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(0, $invoice->fresh()->baseline_total);
    }

    public function test_patokan_terkunci_memindahkan_tahap_ke_detail(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(5_000_000, $invoice->baseline_total);
        $this->assertSame('detail', $invoice->stage);
    }

    public function test_persetujuan_ditolak_bila_patokan_belum_terkunci(): void
    {
        $tour    = $this->makeTour('mice');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertNull($invoice->fresh()->approved_at);
    }

    public function test_persetujuan_non_idr_wajib_menyertakan_kurs(): void
    {
        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 1_000, ['currency' => 'USD']);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('exchange_rate');

        $this->assertNull($invoice->fresh()->approved_at);
    }
}
