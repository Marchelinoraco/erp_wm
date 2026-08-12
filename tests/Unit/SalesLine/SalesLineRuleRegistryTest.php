<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\DocumentRule;
use App\Services\SalesLine\GuideRule;
use App\Services\SalesLine\HotelPerPaxRule;
use App\Services\SalesLine\MiceRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use App\Services\SalesLine\TicketingRule;
use App\Services\SalesLine\TourRule;
use App\Services\SalesLine\TransportRule;
use PHPUnit\Framework\TestCase;

class SalesLineRuleRegistryTest extends TestCase
{
    public function test_memetakan_setiap_jenis_ke_aturannya(): void
    {
        $r = new SalesLineRuleRegistry();

        $this->assertInstanceOf(TourRule::class, $r->for('tour'));
        $this->assertInstanceOf(HotelPerPaxRule::class, $r->for('hotel'));
        $this->assertInstanceOf(GuideRule::class, $r->for('guide'));
        $this->assertInstanceOf(MiceRule::class, $r->for('mice'));
        $this->assertInstanceOf(DocumentRule::class, $r->for('document'));
        $this->assertInstanceOf(TicketingRule::class, $r->for('ticketing'));
    }

    public function test_transport_terdaftar_di_bawah_kunci_rental(): void
    {
        // tours.type menyimpan 'rental', bukan 'transport'.
        $this->assertInstanceOf(TransportRule::class, (new SalesLineRuleRegistry())->for('rental'));
    }

    public function test_jenis_tak_dikenal_jatuh_ke_tour(): void
    {
        $this->assertInstanceOf(TourRule::class, (new SalesLineRuleRegistry())->for('entah-apa'));
    }

    public function test_keys_berisi_ketujuh_jenis(): void
    {
        $this->assertEqualsCanonicalizing(
            ['tour', 'hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'],
            (new SalesLineRuleRegistry())->keys()
        );
    }
}
