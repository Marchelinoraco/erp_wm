<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * D6/D7: rental menyusun total dari jumlah nominal baris deskripsi, enam jenis
 * lain tetap unit_price × pengali. Ditentukan per jenis lewat rule, bukan
 * dipilih sales per invoice.
 */
class TotalCompositionTest extends TestCase
{
    public function test_hanya_rental_memakai_komposisi_line_items(): void
    {
        $registry = new SalesLineRuleRegistry();

        $this->assertSame('line_items', $registry->for('rental')->totalComposition());

        foreach (['tour', 'hotel', 'guide', 'mice', 'document', 'ticketing'] as $type) {
            $this->assertSame(
                'per_unit',
                $registry->for($type)->totalComposition(),
                "Jenis {$type} tidak boleh berubah komposisinya"
            );
        }
    }
}
