<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\Support\FakeSalesLineRule;
use Tests\Support\FakeSalesLineRuleRegistry;
use Tests\TestCase;

class SyncProformaThroughRegistryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_hasil_identik_dengan_rumus_lama_unit_price_kali_pax(): void
    {
        // Rumus lama untuk ketujuh jenis: total = unit_price × pax. Fase 1 tidak
        // boleh menggesernya, apa pun jenisnya.
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 4]), 1_250_000);

            $this->assertEquals(5_000_000, $invoice->total, "Jenis {$type}");
        }
    }

    public function test_syncProformaTotal_benar_benar_memakai_registry(): void
    {
        // Registry palsu memberi aturan pengali ×1000, membuktikan
        // syncProformaTotal memanggil registry, bukan menghitung sendiri.
        $this->app->instance(
            SalesLineRuleRegistry::class,
            new FakeSalesLineRuleRegistry(new FakeSalesLineRule(totalMultiplier: 1000.0))
        );

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000);

        // Bila registry dipakai: 1.000 × 1000 = 1.000.000. Bila tidak: 1.000 × 4.
        $this->assertEquals(1_000_000, $invoice->total);
    }
}
