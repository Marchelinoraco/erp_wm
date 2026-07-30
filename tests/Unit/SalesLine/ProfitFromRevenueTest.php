<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Satu-satunya aturan uang yang bercabang per jenis: profit tipe `tour`
 * dihitung dari tagihan customer, tipe lain dari selisih per item
 * (docs/logika-pembuatan-invoice/08-perbedaan-per-tipe.md §8.3). Sebelum ini
 * aturan itu terduplikasi di tiga berkas, dua bahasa — lihat spec §1.2.
 */
class ProfitFromRevenueTest extends TestCase
{
    public function test_hanya_tour_yang_menghitung_profit_dari_tagihan(): void
    {
        $registry = new SalesLineRuleRegistry();

        $this->assertTrue($registry->for('tour')->profitFromRevenue());

        foreach (['hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'] as $type) {
            $this->assertFalse(
                $registry->for($type)->profitFromRevenue(),
                "Jenis {$type} seharusnya profit per item"
            );
        }
    }
}
