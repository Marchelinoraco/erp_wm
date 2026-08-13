<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\HotelPerPaxRule;
use App\Services\SalesLine\HotelPerRoomNightRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Tiga tata letak baris bernominal di PDF, plus dua keputusan tampilan lain.
 *
 * Ketiganya disapu dari registry dengan peta harapan eksplisit: jenis baru
 * yang lupa memutuskan akan menggagalkan test dengan menyebut namanya, bukan
 * lolos diam-diam lewat default warisan.
 */
class ChargeLineLayoutRuleTest extends TestCase
{
    private const LAYOUT = [
        'rental'    => 'date_first',
        'hotel'     => 'default',   // registry default hotel = mode pax
        'tour'      => 'default',
        'guide'     => 'default',
        'mice'      => 'default',
        'document'  => 'default',
        'ticketing' => 'default',
    ];

    private const PAX = [
        'rental'    => false,
        'hotel'     => true,
        'tour'      => true,
        'guide'     => true,
        'mice'      => true,
        'document'  => true,
        'ticketing' => true,
    ];

    private const TANGGAL_RINGKAS = [
        'rental'    => false,
        'hotel'     => true,
        'tour'      => false,
        'guide'     => false,
        'mice'      => false,
        'document'  => false,
        'ticketing' => false,
    ];

    /** @param array<string, mixed> $harapan */
    private function sapu(array $harapan, string $method): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                $harapan,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan {$method} di peta harapan test ini."
            );

            $this->assertSame($harapan[$key], $registry->for($key)->{$method}(), "Jenis {$key} — {$method}");
        }
    }

    public function test_tata_letak_baris_bernominal_per_jenis(): void
    {
        $this->sapu(self::LAYOUT, 'chargeLineLayout');
    }

    public function test_hanya_rental_yang_menyembunyikan_total_pax(): void
    {
        $this->sapu(self::PAX, 'showsTotalPaxInPdf');
    }

    public function test_hanya_hotel_yang_memakai_tanggal_ringkas(): void
    {
        $this->sapu(self::TANGGAL_RINGKAS, 'usesCompactDateInPdf');
    }

    public function test_mode_kamar_hotel_memakai_tata_letak_hotel_room(): void
    {
        // Tidak terjangkau lewat for('hotel') — modenya milik invoice.
        $this->assertSame('hotel_room', (new HotelPerRoomNightRule())->chargeLineLayout());
        $this->assertSame('default', (new HotelPerPaxRule())->chargeLineLayout());
    }

    public function test_kedua_mode_hotel_menampilkan_pax_dan_tanggal_ringkas(): void
    {
        foreach ([new HotelPerPaxRule(), new HotelPerRoomNightRule()] as $aturan) {
            $this->assertTrue($aturan->showsTotalPaxInPdf(), get_class($aturan));
            $this->assertTrue($aturan->usesCompactDateInPdf(), get_class($aturan));
        }
    }

    public function test_method_lama_sudah_tidak_ada(): void
    {
        // Gerbang: boolean dua keadaan tidak boleh tertinggal berdampingan
        // dengan pemilih tiga keadaan — dua sumber kebenaran akan berselisih.
        $this->assertFalse(
            method_exists(HotelPerRoomNightRule::class, 'chargeLinesDateFirstInPdf'),
            'chargeLinesDateFirstInPdf() harus DIGANTI chargeLineLayout(), bukan dibiarkan berdampingan'
        );
    }
}
