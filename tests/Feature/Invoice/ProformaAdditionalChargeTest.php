<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Biaya tambahan (Additional) yang sales tempel SENDIRI di tahap proforma —
 * sebelum invoice disetujui — lewat baris description_lines yang punya
 * `amount`. Bebas ditambah/diedit tanpa review akuntan (beda dari
 * AdditionalChargeCharacterizationTest, yang menutup jalur CostRequestController
 * SETELAH disetujui, lewat review akuntan).
 */
class ProformaAdditionalChargeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_total_proforma_menambahkan_satu_biaya_tambahan_di_luar_harga_pax(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000); // 250.000 × 4 = 1.000.000

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 250_000,
                'description_lines' => [
                    ['label' => 'Dokumen', 'detail' => 'Biaya pengurusan visa', 'amount' => 200_000],
                ],
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1_200_000, $invoice->total, '1.000.000 (proforma) + 200.000 (biaya tambahan)');
        $this->assertEquals(1_200_000, $invoice->total_idr, 'IDR — total_idr ikut naik sama besar');
    }

    public function test_total_proforma_menjumlah_banyak_baris_biaya_tambahan(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 500_000); // 500.000 × 2 = 1.000.000

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 500_000,
                'description_lines' => [
                    ['label' => 'Dokumen', 'detail' => 'Visa', 'amount' => 150_000],
                    ['label' => 'Izin Khusus', 'detail' => 'Izin taman nasional', 'amount' => 50_000],
                    ['label' => 'Hotel', 'date' => '13-15 Aug 2026', 'detail' => 'Deluxe Room'], // baris deskripsi biasa, tanpa amount
                ],
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1_200_000, $invoice->total, '1.000.000 + 150.000 + 50.000 — baris tanpa amount tidak ikut menambah');
        $this->assertCount(3, $invoice->description_lines, 'Ketiga baris (2 biaya tambahan + 1 deskripsi biasa) tetap tersimpan');
    }

    public function test_tanpa_biaya_tambahan_total_tetap_unit_price_kali_pax(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 5]);
        $invoice = $this->makeInvoice($tour, 300_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 300_000,
                'description_lines' => [
                    ['label' => 'Hotel', 'date' => '1-3 Aug', 'detail' => 'Superior Room'],
                ],
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1_500_000, $invoice->total, 'Tanpa biaya tambahan, total = 300.000 × 5 seperti rumus lama');
    }

    public function test_biaya_tambahan_pada_mata_uang_non_idr_ikut_masuk_total_tapi_total_idr_menunggu_kurs(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 100, ['currency' => 'USD']);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'USD',
                'unit_price'        => 100,
                'description_lines' => [
                    ['label' => 'Dokumen', 'detail' => 'Visa', 'amount' => 50],
                ],
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(250, $invoice->total, '100 × 2 + 50');
        $this->assertNotNull($invoice->total_idr);
        $this->assertEquals(0, $invoice->total_idr, 'Non-IDR: total_idr tetap menunggu kurs sampai disetujui');
    }
}
