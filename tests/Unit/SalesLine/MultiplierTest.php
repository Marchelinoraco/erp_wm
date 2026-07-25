<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\Multiplier;
use PHPUnit\Framework\TestCase;

class MultiplierTest extends TestCase
{
    public function test_menyimpan_kunci_label_dan_nilai(): void
    {
        $m = new Multiplier('pax', 'Peserta', 10);

        $this->assertSame('pax', $m->key);
        $this->assertSame('Peserta', $m->label);
        $this->assertSame(10, $m->value);
    }
}
