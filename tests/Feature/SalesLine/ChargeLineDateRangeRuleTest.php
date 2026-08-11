<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Rental dan hotel menagih layanan yang berjalan sepanjang rentang tanggal
 * (sewa kendaraan, menginap), jadi baris bernominalnya punya tanggal mulai
 * DAN selesai. Jenis lain menagih biaya pada satu titik tanggal.
 *
 * Disapu lewat registry, bukan daftar yang ditulis ulang di test: menambah
 * jenis baru tanpa memutuskan perilakunya akan langsung menggagalkan test ini.
 */
class ChargeLineDateRangeRuleTest extends TestCase
{
    private const MEMAKAI_RENTANG = ['rental', 'hotel'];

    public function test_hanya_rental_dan_hotel_yang_memakai_rentang_tanggal(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertSame(
                in_array($key, self::MEMAKAI_RENTANG, true),
                $registry->for($key)->chargeLinesUseDateRange(),
                "Jenis {$key}"
            );
        }
    }

    public function test_jenis_tak_dikenal_tidak_memakai_rentang(): void
    {
        // Registry menjatuhkan jenis tak dikenal ke TourRule.
        $this->assertFalse(
            app(SalesLineRuleRegistry::class)->for('jenis-yang-belum-ada')->chargeLinesUseDateRange()
        );
    }
}
