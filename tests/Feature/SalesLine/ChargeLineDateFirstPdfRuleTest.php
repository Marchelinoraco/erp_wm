<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Di PDF rental, yang dicari customer lebih dulu adalah PERIODE sewanya, baru
 * unit mana yang dipakai — jadi rentang tanggal menempati kolom kiri dan nama
 * unit pindah ke kanan. Jenis lain mempertahankan tata letak lama: label di
 * kiri, tanggal menyatu dengan keterangan di kanan.
 *
 * Perhatikan ini BUKAN aturan yang sama dengan chargeLinesUseDateRange():
 * hotel juga punya tanggal mulai & selesai, tapi tata letak PDF-nya tidak ikut
 * berubah. Keduanya sengaja dipisah supaya salah satu bisa berubah tanpa
 * menyeret yang lain.
 *
 * Kunci disapu dari registry, tapi setiap kunci WAJIB punya entri eksplisit di
 * peta HARAPAN — dijaga assertArrayHasKey() SEBELUM nilainya dibandingkan.
 * Tanpa itu, jenis baru yang lupa di-override jatuh diam-diam ke default
 * `false` milik BaseSalesLineRule dan lolos tanpa ada yang memutuskan apa pun.
 */
class ChargeLineDateFirstPdfRuleTest extends TestCase
{
    /**
     * Keputusan eksplisit per jenis. Menambah jenis ke registry tanpa
     * menambah barisnya di sini menggagalkan test lewat assertArrayHasKey().
     */
    private const HARAPAN = [
        'rental'    => true,
        'hotel'     => false,
        'tour'      => false,
        'guide'     => false,
        'mice'      => false,
        'document'  => false,
        'ticketing' => false,
    ];

    public function test_hanya_rental_yang_menaruh_tanggal_di_kolom_kiri(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                self::HARAPAN,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan chargeLinesDateFirstInPdf di peta HARAPAN test ini."
            );

            $this->assertSame(
                self::HARAPAN[$key],
                $registry->for($key)->chargeLinesDateFirstInPdf(),
                "Jenis {$key}"
            );
        }
    }

    public function test_hotel_pakai_rentang_tanggal_tapi_bukan_tata_letak_tanggal_di_depan(): void
    {
        // Pengunci pemisahan kedua aturan: kalau kelak keduanya dilebur jadi
        // satu bendera, hotel akan ikut berubah tata letak PDF-nya tanpa ada
        // yang meminta.
        $hotel = app(SalesLineRuleRegistry::class)->for('hotel');

        $this->assertTrue($hotel->chargeLinesUseDateRange(), 'Hotel tetap punya tanggal mulai & selesai di form');
        $this->assertFalse($hotel->chargeLinesDateFirstInPdf(), 'Tata letak PDF hotel tidak ikut berubah');
    }

    public function test_jenis_tak_dikenal_memakai_tata_letak_lama(): void
    {
        // Registry menjatuhkan jenis tak dikenal ke TourRule.
        $this->assertFalse(
            app(SalesLineRuleRegistry::class)->for('jenis-yang-belum-ada')->chargeLinesDateFirstInPdf()
        );
    }
}
