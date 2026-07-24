<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci kapan total_idr terisi.
 *
 * IDR → terisi langsung saat sinkronisasi. Non-IDR → sengaja dibiarkan kosong
 * sampai disetujui, supaya laporan IDR tidak terdistorsi kurs placeholder
 * sebelum kurs pasti diinput.
 */
class CurrencyCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_idr_mengisi_total_idr_langsung(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $this->assertEquals(1_000_000, $invoice->total);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }

    public function test_non_idr_membiarkan_total_idr_kosong_sebelum_disetujui(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        $this->assertEquals(1_000, $invoice->total);

        // CATATAN: nilainya 0.00, BUKAN null — sudah diverifikasi empiris.
        // Dokumen desain menyebutnya "dibiarkan kosong"; kenyataannya kolom
        // punya default 0. Efek praktisnya sama (laporan IDR tidak terdistorsi
        // kurs placeholder), tapi penulisan test harus mengikuti kenyataan.
        // assertNotNull WAJIB mendahului: assertEquals(0, null) LOLOS di PHPUnit,
        // jadi tanpa penjaga ini regresi ke null pada Fase 2 tidak akan tertangkap
        // padahal justru itu yang ingin dikunci di sini.
        $this->assertNotNull(
            $invoice->total_idr,
            'total_idr harus tetap ada sebagai 0, bukan berubah jadi null'
        );
        $this->assertEquals(
            0,
            $invoice->total_idr,
            'total_idr tetap 0 sampai kurs pasti diinput saat persetujuan'
        );
    }

    public function test_persetujuan_non_idr_mengisi_total_idr_dari_kurs(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        // baseline_total sengaja DIBEDAKAN dari total. Server hanya mensyaratkan
        // baseline_total > 0, dan dengan nilai yang berbeda test dapat membuktikan
        // konversi memakai `total` — bukan `baseline_total` yang kebetulan sama.
        $invoice->update(['baseline_total' => 1]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice), ['exchange_rate' => 16_000])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(16_000, $invoice->exchange_rate);
        $this->assertEquals(16_000_000, $invoice->total_idr, '1.000 USD × 16.000');
    }

    public function test_persetujuan_idr_memakai_kurs_satu(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1, $invoice->exchange_rate);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }
}
