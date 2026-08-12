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
}
