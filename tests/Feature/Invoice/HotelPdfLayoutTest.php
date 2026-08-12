<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Bentuk PDF invoice hotel mengikuti dokumen acuan pemilik. Berkas ini juga
 * menjaga bahwa jenis SELAIN hotel tidak ikut berubah sedikit pun.
 */
class HotelPdfLayoutTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_kolom_hotel_room_tersedia_dan_kosong_untuk_invoice_baru(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);

        $this->assertNull($invoice->hotel_room);

        $invoice->update(['hotel_room' => 'Paradise Hotel Golf & Resort – Deluxe Room Garden View']);

        $this->assertSame(
            'Paradise Hotel Golf & Resort – Deluxe Room Garden View',
            $invoice->fresh()->hotel_room
        );
    }

    private const SATU_KAMAR = [
        ['hotel' => 'Paradise Hotel Golf & Resort', 'label' => 'Deluxe Room Garden View',
         'date' => '2026-08-01', 'date_end' => '2026-08-02', 'rooms' => 1,
         'unit_price' => 705_000, 'amount' => 705_000],
    ];

    private const DUA_KAMAR = [
        ['hotel' => 'Paradise Hotel', 'label' => 'Deluxe Room Garden View',
         'date' => '2026-08-01', 'date_end' => '2026-08-03', 'rooms' => 1,
         'unit_price' => 705_000, 'amount' => 1_410_000],
        ['hotel' => 'Ibis Manado', 'label' => 'Superior Room',
         'date' => '2026-08-03', 'date_end' => '2026-08-04', 'rooms' => 1,
         'unit_price' => 950_000, 'amount' => 950_000],
    ];

    private function dataView(\App\Models\Invoice $invoice): array
    {
        return app(\App\Http\Controllers\InvoiceController::class)->invoiceViewData($invoice->fresh());
    }

    private function invoiceKamar(array $baris): \App\Models\Invoice
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);
        $invoice->update([
            'pricing_mode'      => \App\Models\Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => $baris,
        ]);
        $invoice->fresh()->syncProformaTotal();

        return $invoice->fresh();
    }

    public function test_satu_baris_kamar_menaikkan_hotel_room_ke_blok_info(): void
    {
        $data = $this->dataView($this->invoiceKamar(self::SATU_KAMAR));

        $this->assertSame('hotel_room', $data['chargeLineLayout']);
        $this->assertSame('Paradise Hotel Golf & Resort – 1 Deluxe Room Garden View', $data['hotelRoomInfo']);
        $this->assertCount(1, $data['roomLines']);
    }

    public function test_dua_baris_kamar_mengosongkan_blok_info(): void
    {
        $data = $this->dataView($this->invoiceKamar(self::DUA_KAMAR));

        $this->assertSame('', $data['hotelRoomInfo'], 'Dengan dua kamar, Hotel/Room turun berpasangan ke area bernominal');
        $this->assertCount(2, $data['roomLines']);
    }

    public function test_mode_pax_memakai_kolom_hotel_room(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);
        $invoice->update(['hotel_room' => 'Paradise Hotel – Deluxe Room Garden View']);

        $data = $this->dataView($invoice);

        $this->assertSame('default', $data['chargeLineLayout']);
        $this->assertSame('Paradise Hotel – Deluxe Room Garden View', $data['hotelRoomInfo']);
        $this->assertSame([], $data['roomLines']);
    }

    public function test_tanggal_ringkas_hanya_untuk_hotel(): void
    {
        $tourHotel = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $hotel     = $this->makeInvoice($tourHotel, 705_000);

        $tourLain = $this->makeTour('tour', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $lain     = $this->makeInvoice($tourLain, 705_000);

        $this->assertSame('1-2 Aug 2026', $this->dataView($hotel)['resvDate']);
        $this->assertSame('01 August 2026 – 02 August 2026', $this->dataView($lain)['resvDate']);
    }

    public function test_total_pax_tampil_untuk_hotel_dan_tidak_untuk_rental(): void
    {
        $hotel  = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);
        $rental = $this->makeInvoice($this->makeTour('rental', ['pax' => 2]), 705_000);

        $this->assertTrue($this->dataView($hotel)['showsTotalPax']);
        $this->assertFalse($this->dataView($rental)['showsTotalPax']);
    }
}
