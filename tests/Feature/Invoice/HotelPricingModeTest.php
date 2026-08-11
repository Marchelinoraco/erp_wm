<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Dua cara hitung invoice hotel. Pengunci terpenting di berkas ini adalah
 * bahwa pricing_mode NULL — keadaan seluruh invoice hotel yang sudah ada —
 * berperilaku persis seperti sebelum fitur ini ada.
 */
class HotelPricingModeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_invoice_baru_bernilai_null_dan_kolomnya_ada(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->assertNull($invoice->pricing_mode, 'Invoice baru tidak memilih mode apa pun sampai sales memilihnya');
    }

    public function test_konstanta_mode_terdefinisi(): void
    {
        $this->assertSame('per_pax', Invoice::PRICING_PER_PAX);
        $this->assertSame('per_room_night', Invoice::PRICING_PER_ROOM_NIGHT);
        $this->assertSame(['per_pax', 'per_room_night'], Invoice::PRICING_MODES);
    }

    public function test_mode_tersimpan_dan_terbaca_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $invoice->update(['pricing_mode' => Invoice::PRICING_PER_ROOM_NIGHT]);

        $this->assertSame('per_room_night', $invoice->fresh()->pricing_mode);
    }

    /** Dua tipe kamar dengan periode berbeda — kasus yang mode ini layani. */
    private const BARIS_KAMAR = [
        ['label' => 'Deluxe', 'detail' => 'Twin bed', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 6_000_000],
        ['label' => 'Suite', 'detail' => 'King bed', 'date' => '2026-08-17', 'date_end' => '2026-08-18', 'rooms' => 1, 'unit_price' => 2_500_000, 'amount' => 2_500_000],
    ];

    public function test_mode_kamar_menjumlah_baris_dan_mengabaikan_harga_pax(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);
        $invoice->fresh()->syncProformaTotal();

        // 1.000.000 × 4 pax = 4.000.000 SENGAJA tidak muncul di mana pun.
        $this->assertEquals(8_500_000, $invoice->fresh()->total);
    }

    public function test_mode_pax_tetap_harga_kali_pax(): void
    {
        // Pasangan pengunci: mode default tidak ikut berubah jadi penjumlahan baris.
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $this->assertEquals(4_000_000, $invoice->fresh()->total);
    }

    public function test_mode_kamar_menjumlah_baris_kamar_dan_biaya_tambahan(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => array_merge(self::BARIS_KAMAR, [
                ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
            ]),
        ]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertEquals(9_250_000, $invoice->fresh()->total, '8.500.000 kamar + 750.000 biaya tambahan');
    }

    public function test_mode_null_pada_hotel_identik_dengan_sebelum_fitur_ini(): void
    {
        // Pengunci terpenting: seluruh invoice hotel yang sudah ada bernilai
        // NULL, dan nominalnya tidak boleh bergeser sedikit pun.
        $tour    = $this->makeTour('hotel', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 350_000);

        $invoice->update(['description_lines' => [
            ['label' => 'Dokumen', 'detail' => 'Visa', 'amount' => 200_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertNull($invoice->fresh()->pricing_mode);
        $this->assertEquals(2_650_000, $invoice->fresh()->total, '350.000 × 7 pax + 200.000');
    }
}
