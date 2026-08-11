<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Rental dan hotel menagih layanan yang berjalan sepanjang rentang tanggal
 * (sewa kendaraan, menginap), jadi baris bernominalnya punya tanggal mulai
 * DAN selesai. Jenis lain menagih biaya pada satu titik tanggal.
 *
 * Kunci disapu dari registry (bukan ditulis ulang di test), tapi setiap kunci
 * WAJIB punya entri eksplisit di peta HARAPAN — dijaga oleh assertArrayHasKey()
 * di bawah SEBELUM nilainya dibandingkan. Tanpa penjagaan itu, jenis baru yang
 * lupa di-override akan jatuh diam-diam ke default `false` milik
 * BaseSalesLineRule, kebetulan cocok dengan ketiadaannya di peta lama, dan
 * assertSame(false, false) lolos tanpa ada yang benar-benar memutuskan
 * perilakunya. assertArrayHasKey menggagalkan test dengan lantang — pesannya
 * menyebut nama jenisnya — begitu registry punya kunci yang belum diputuskan
 * di sini.
 */
class ChargeLineDateRangeRuleTest extends TestCase
{
    /**
     * Keputusan eksplisit per jenis. Menambah jenis ke registry tanpa
     * menambah barisnya di sini menggagalkan test lewat assertArrayHasKey(),
     * bukan lolos diam-diam lewat default warisan.
     */
    private const HARAPAN = [
        'rental'    => true,
        'hotel'     => true,
        'tour'      => false,
        'guide'     => false,
        'mice'      => false,
        'document'  => false,
        'ticketing' => false,
    ];

    public function test_hanya_rental_dan_hotel_yang_memakai_rentang_tanggal(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                self::HARAPAN,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan chargeLinesUseDateRange di peta HARAPAN test ini."
            );

            $this->assertSame(
                self::HARAPAN[$key],
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
