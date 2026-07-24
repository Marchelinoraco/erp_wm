<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci rumus penagihan yang berlaku SEKARANG: total = unit_price × pax,
 * sama untuk ketujuh jenis penjualan tanpa kecuali.
 *
 * Fase 1-3 akan mengganti rumus ini dengan aturan per jenis. Test di berkas ini
 * SENGAJA akan gagal saat itu — dan kegagalannya adalah buktinya bahwa
 * perubahan memang mengenai sasaran, bukan tanda kerusakan.
 */
class ProformaTotalCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_total_adalah_unit_price_kali_pax_untuk_ketujuh_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $tour    = $this->makeTour($type, ['pax' => 10]);
            $invoice = $this->makeInvoice($tour, 500_000);

            $this->assertEquals(
                5_000_000,
                $invoice->total,
                "Jenis {$type}: total seharusnya 500.000 × 10 pax = 5.000.000"
            );
        }
    }

    public function test_jumlah_pax_berbeda_menghasilkan_total_berbeda_di_semua_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $tour    = $this->makeTour($type, ['pax' => 3]);
            $invoice = $this->makeInvoice($tour, 1_000_000);

            $this->assertEquals(
                3_000_000,
                $invoice->total,
                "Jenis {$type}: pax ikut mengali meskipun jenis ini tidak ditagih per orang"
            );
        }
    }

    public function test_unit_price_nol_menghasilkan_total_nol(): void
    {
        $tour    = $this->makeTour('guide', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 0);

        $this->assertEquals(0, $invoice->total);
    }

    public function test_pax_ikut_disalin_ke_invoice_saat_total_disinkronkan(): void
    {
        // syncProformaTotal() menyimpan pax yang dipakai menghitung ke invoice,
        // supaya PDF dan perhitungan tidak pernah memakai angka berbeda.
        $tour    = $this->makeTour('tour', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 100_000);

        $this->assertEquals(7, $invoice->pax);
        $this->assertEquals(700_000, $invoice->total);
    }
}
