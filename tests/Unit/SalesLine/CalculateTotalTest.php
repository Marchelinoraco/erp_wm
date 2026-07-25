<?php

namespace Tests\Unit\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;
use App\Services\SalesLine\BaseSalesLineRule;
use App\Services\SalesLine\Multiplier;
use PHPUnit\Framework\TestCase;

class CalculateTotalTest extends TestCase
{
    /** Aturan telanjang untuk menguji hanya aritmetika BaseSalesLineRule. */
    private function rule(): SalesLineInvoiceRule
    {
        return new class extends BaseSalesLineRule {
            public function unitPriceLabel(): string
            {
                return 'Harga';
            }

            public function defaultMultipliers(Invoice $invoice): array
            {
                return [];
            }
        };
    }

    public function test_satu_pengali_mengali_harga(): void
    {
        $total = $this->rule()->calculateTotal(500_000, [new Multiplier('pax', 'Peserta', 10)]);

        $this->assertSame(5_000_000.0, $total);
    }

    public function test_dua_pengali_dikalikan_berurutan(): void
    {
        // Hotel: harga/kamar/malam × 3 kamar × 4 malam
        $total = $this->rule()->calculateTotal(500_000, [
            new Multiplier('rooms', 'Kamar', 3),
            new Multiplier('nights', 'Malam', 4),
        ]);

        $this->assertSame(6_000_000.0, $total);
    }

    public function test_tanpa_pengali_total_sama_dengan_harga(): void
    {
        $this->assertSame(750_000.0, $this->rule()->calculateTotal(750_000, []));
    }

    public function test_mengembalikan_float_tanpa_pembulatan(): void
    {
        // Input berpecahan NYATA — bukan angka yang kebetulan bulat sempurna.
        // round($x) atau round($x, 2) akan mengubah 333.335 menjadi 333/333.33,
        // sehingga round() yang menyelinap masuk pasti tertangkap test ini.
        $total = $this->rule()->calculateTotal(333.335, [new Multiplier('pax', 'Peserta', 1)]);

        $this->assertSame(333.335, $total);
    }
}
