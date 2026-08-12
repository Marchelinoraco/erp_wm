<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\DocumentRule;
use App\Services\SalesLine\GuideRule;
use App\Services\SalesLine\HotelPerRoomNightRule;
use App\Services\SalesLine\MiceRule;
use App\Services\SalesLine\TicketingRule;
use App\Services\SalesLine\TourRule;
use App\Services\SalesLine\TransportRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

class RuleLabelsAndMultipliersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_label_harga_per_jenis(): void
    {
        $this->assertSame('Harga / pax', (new TourRule())->unitPriceLabel());
        $this->assertSame('Harga paket / peserta', (new MiceRule())->unitPriceLabel());
        $this->assertSame('Harga / dokumen', (new DocumentRule())->unitPriceLabel());
        $this->assertSame('Harga / tiket', (new TicketingRule())->unitPriceLabel());
        $this->assertSame('Harga / hari', (new GuideRule())->unitPriceLabel());
        $this->assertSame('Harga / hari', (new TransportRule())->unitPriceLabel());
        $this->assertSame('Harga / kamar / malam', (new HotelPerRoomNightRule())->unitPriceLabel());
    }

    public function test_jenis_berbasis_pax_mengembalikan_satu_pengali_pax(): void
    {
        // Tour default: pax 10, tanggal 2026-08-01..05.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 10]), 500_000);

        // Kunci pengali beda per jenis meski nilainya sama-sama dari pax —
        // kunci inilah yang jadi field JSON billing_quantities di Fase 2,
        // jadi wajib diuji eksplisit, bukan cuma nilainya.
        $expectedKeys = [
            TourRule::class      => 'pax',
            MiceRule::class      => 'pax',
            DocumentRule::class  => 'dokumen',
            TicketingRule::class => 'tiket',
        ];

        foreach ([new TourRule(), new MiceRule(), new DocumentRule(), new TicketingRule()] as $rule) {
            $m = $rule->defaultMultipliers($invoice);

            $this->assertCount(1, $m, get_class($rule));
            $this->assertSame($expectedKeys[get_class($rule)], $m[0]->key, get_class($rule).' kunci pengali salah');
            $this->assertSame(10, $m[0]->value, get_class($rule).' harus memakai pax');
        }
    }

    public function test_guide_dan_transport_memakai_hari_inklusif(): void
    {
        // 2026-08-01 sampai 2026-08-05 = 5 hari inklusif.
        $invoice = $this->makeInvoice($this->makeTour('guide'), 500_000);

        foreach ([new GuideRule(), new TransportRule()] as $rule) {
            $m = $rule->defaultMultipliers($invoice);

            $this->assertCount(1, $m, get_class($rule));
            $this->assertSame('hari', $m[0]->key);
            $this->assertSame(5, $m[0]->value, get_class($rule).' harus 5 hari inklusif');
        }
    }

    public function test_hotel_memakai_kamar_kali_malam(): void
    {
        // 2026-08-01 sampai 2026-08-05 = 4 malam; kamar mulai dari 1.
        $invoice = $this->makeInvoice($this->makeTour('hotel'), 500_000);

        $m = (new HotelPerRoomNightRule())->defaultMultipliers($invoice);

        $this->assertCount(2, $m);
        $this->assertSame('rooms', $m[0]->key);
        $this->assertSame(1, $m[0]->value);
        $this->assertSame('nights', $m[1]->key);
        $this->assertSame(4, $m[1]->value);
    }

    public function test_tanpa_tanggal_hari_dan_malam_jatuh_ke_satu(): void
    {
        $invoice = $this->makeInvoice(
            $this->makeTour('guide', ['start_date' => null, 'end_date' => null]),
            500_000
        );

        $this->assertSame(1, (new GuideRule())->defaultMultipliers($invoice)[0]->value);
        $this->assertSame(1, (new HotelPerRoomNightRule())->defaultMultipliers($invoice)[1]->value);
    }
}
