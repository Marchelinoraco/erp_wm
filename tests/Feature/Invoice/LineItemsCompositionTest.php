<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D6: total rental = jumlah nominal baris deskripsi. Enam jenis lain tetap
 * unit_price × pengali + baris bernominal (perilaku lama, tidak boleh bergeser).
 */
class LineItemsCompositionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_total_rental_adalah_jumlah_baris_bernominal(): void
    {
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
                ['label' => 'Innova Reborn', 'date' => '2026-07-25', 'detail' => 'Sewa harian', 'amount' => 1_050_000],
                ['label' => 'Luar kota', 'date' => '2026-07-25', 'detail' => 'Tambahan', 'amount' => 200_000],
            ],
        ]);

        // unit_price 2.050.000 DIABAIKAN; total murni dari ketiga baris.
        $this->assertEquals(2_050_000, $invoice->total);
        $this->assertEquals(2_050_000, $invoice->total_idr);
    }

    public function test_rental_tanpa_baris_bernominal_bertotal_nol(): void
    {
        // R1: inilah keadaan peralihan yang banner §5c wajib peringatkan.
        // Total Rp0 adalah perilaku yang DIINGINKAN di sini, bukan bug —
        // yang tidak boleh adalah sales tidak menyadarinya.
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000);

        $this->assertEquals(0, $invoice->total);
        // unit_price lamanya TETAP UTUH supaya banner bisa menampilkannya.
        $this->assertEquals(2_050_000, $invoice->unit_price);
    }

    public function test_jenis_lain_tetap_unit_price_kali_pax_plus_baris_bernominal(): void
    {
        foreach (['tour', 'hotel', 'guide', 'mice', 'document', 'ticketing'] as $type) {
            $tour    = $this->makeTour($type, ['pax' => 4]);
            $invoice = $this->makeInvoice($tour, 1_000_000, [
                'description_lines' => [
                    ['label' => 'Dokumen', 'date' => '', 'detail' => 'Izin', 'amount' => 500_000],
                ],
            ]);

            // 1.000.000 × 4 + 500.000
            $this->assertEquals(4_500_000, $invoice->total, "Jenis {$type}");
        }
    }

    public function test_membuka_halaman_tour_rental_tidak_mengubah_total_di_database(): void
    {
        // Dasar penurunan tingkat R1 dari Tinggi ke Sedang: syncProformaTotal()
        // tidak berjalan saat halaman sekadar dibuka, jadi tidak ada kehilangan
        // diam-diam. Bila test ini gagal, R1 kembali jadi Tinggi dan rencana §5
        // harus ditinjau ulang SEBELUM rilis.
        $user    = $this->salesUser();
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => '', 'amount' => 800_000],
            ],
        ]);

        $totalSebelum     = $invoice->total;
        $unitPriceSebelum = $invoice->unit_price;

        $this->actingAs($user)->get(route('tours.edit', $tour->id))->assertOk();

        $segar = $invoice->fresh();
        $this->assertEquals($totalSebelum, $segar->total);
        $this->assertEquals($unitPriceSebelum, $segar->unit_price);
    }
}
