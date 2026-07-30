<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D1: satuan pengali di PDF customer adalah "pax" untuk SEMUA jenis — bukan
 * karena "pax" benar untuk semua, tapi karena angka yang dikalikan memang
 * jumlah pax (tour.pax) sampai Fase 3 dokumen lama selesai. Test ini mengunci
 * pembatalan 1be88ff/bbde75f agar satuan per jenis tidak diam-diam kembali.
 *
 * Caranya penting: setiap test SENGAJA mengirim `billingUnit` bernilai salah.
 * Selama blade masih memakai `$billingUnit ?? 'pax'`, test gagal; setelah
 * blade mematok 'pax', nilai salah itu diabaikan dan test lulus. Tanpa nilai
 * salah yang disengaja, fallback `?? 'pax'` membuat test lulus sejak awal dan
 * tidak membuktikan apa pun.
 */
class CustomerPdfUnitLabelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /**
     * Data view persis seperti yang dikirim InvoiceController::build().
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed>             $extra  disisipkan/menimpa data view
     */
    private function renderInvoice(
        \App\Models\Invoice $invoice,
        float $unitPrice,
        int $pax,
        array $lines = [],
        array $extra = [],
    ): string {
        $segar = $invoice->fresh();

        return view('invoice', array_replace([
            'invoice'      => $segar,
            'company'      => config('quotation.company'),
            'bank'         => [],
            'paymentTerms' => '',
            'logo'         => '',
            'lines'        => $lines,
            'unitPrice'    => $unitPrice,
            'pax'          => $pax,
            'paid'         => 0.0,
            'outstanding'  => (float) $segar->total,
        ], $extra))->render();
    }

    public function test_pdf_customer_mencetak_pax_untuk_setiap_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 7]), 100_000);

            // 'hari' sengaja salah: blade harus mengabaikannya sepenuhnya.
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => 'hari',
            ]);

            $this->assertStringContainsString('&times; 7 pax', $html, "Jenis {$type}");
            $this->assertStringNotContainsString('&times; 7 hari', $html, "Jenis {$type}");
        }
    }

    public function test_tidak_ada_satuan_per_jenis_yang_tersisa_di_pdf(): void
    {
        // Keempat kata yang bbde75f perkenalkan. Kemunculannya kembali berarti
        // asumsi D2 yang belum diputuskan naik lagi jadi pernyataan ke customer.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 7]), 100_000);

        foreach (['hari', 'dokumen', 'tiket', 'malam'] as $satuan) {
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => $satuan,
            ]);

            $this->assertStringNotContainsString('&times; 7 ' . $satuan, $html);
            $this->assertStringContainsString('&times; 7 pax', $html);
        }
    }
}
