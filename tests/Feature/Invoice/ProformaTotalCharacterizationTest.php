<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci rumus penagihan total = unit_price × pax.
 *
 * Berlaku untuk ENAM jenis. Sejak Fase 3, `rental` sengaja dikecualikan:
 * totalnya disusun dari jumlah nominal baris deskripsi (D6), dan pengecualian
 * itu dikunci eksplisit di bawah — bukan sekadar dihapus dari daftar. Docblock
 * versi Fase 1 memang meramalkan test ini akan gagal saat aturan per jenis
 * mendarat; kegagalan itu sudah terjadi dan ditindaklanjuti di sini.
 */
class ProformaTotalCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Enam jenis yang totalnya masih unit_price × pax. */
    private const TIPE_PER_UNIT = ['tour', 'hotel', 'guide', 'mice', 'document', 'ticketing'];

    public function test_total_adalah_unit_price_kali_pax_untuk_enam_jenis(): void
    {
        foreach (self::TIPE_PER_UNIT as $type) {
            $tour    = $this->makeTour($type, ['pax' => 10]);
            $invoice = $this->makeInvoice($tour, 500_000);

            $this->assertEquals(
                5_000_000,
                $invoice->total,
                "Jenis {$type}: total seharusnya 500.000 × 10 pax = 5.000.000"
            );
        }
    }

    public function test_rental_dikecualikan_dari_rumus_kali_pax(): void
    {
        // D6: unit_price diabaikan sepenuhnya untuk rental — bukan dikali 1,
        // bukan dikali pax. Yang menentukan hanya baris bernominal.
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => '', 'amount' => 750_000],
            ],
        ]);

        $this->assertEquals(750_000, $invoice->total);
    }

    public function test_jumlah_pax_berbeda_menghasilkan_total_berbeda_di_enam_jenis(): void
    {
        foreach (self::TIPE_PER_UNIT as $type) {
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
