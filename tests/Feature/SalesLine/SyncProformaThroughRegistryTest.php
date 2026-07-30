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
        // Rumus lama: total = unit_price × pax. Sejak Fase 3 hanya `rental`
        // yang dikecualikan (D6, dikunci di test berikutnya); enam jenis lain
        // tidak boleh bergeser sedikit pun.
        foreach (array_diff(self::SALES_TYPES, ['rental']) as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 4]), 1_250_000);

            $this->assertEquals(5_000_000, $invoice->total, "Jenis {$type}");
        }
    }

    public function test_rental_memakai_komposisi_baris_bernominal(): void
    {
        // Pasangan dari test di atas: membuktikan `rental` dikecualikan karena
        // aturannya, bukan karena luput dari cakupan test.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 1_250_000, [
            'description_lines' => [
                ['label' => 'Innova', 'date' => '2026-07-25', 'detail' => '', 'amount' => 1_050_000],
            ],
        ]);

        $this->assertEquals(1_050_000, $invoice->total);
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
