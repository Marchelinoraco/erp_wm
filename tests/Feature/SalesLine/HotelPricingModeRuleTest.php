<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\HotelPerPaxRule;
use App\Services\SalesLine\HotelPerRoomNightRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Hotel adalah satu-satunya jenis dengan lebih dari satu cara hitung. Mode
 * memilih KELAS yang berbeda, bukan mencabangkan satu kelas — sehingga tiap
 * kelas tetap menjawab satu pertanyaan dan bisa diuji sendiri.
 */
class HotelPricingModeRuleTest extends TestCase
{
    /** Kunci: default registry untuk 'hotel' adalah mode pax, yaitu perilaku yang sudah berjalan. */
    public function test_registry_untuk_hotel_default_ke_mode_pax(): void
    {
        $this->assertInstanceOf(
            HotelPerPaxRule::class,
            app(SalesLineRuleRegistry::class)->for('hotel')
        );
    }

    public function test_mode_pax_memakai_komposisi_per_unit_dan_tata_letak_pdf_lama(): void
    {
        $aturan = new HotelPerPaxRule();

        $this->assertSame('per_unit', $aturan->totalComposition());
        $this->assertFalse($aturan->chargeLinesDateFirstInPdf());
        $this->assertSame('Harga / pax', $aturan->unitPriceLabel());
    }

    public function test_mode_kamar_menyusun_total_dari_baris_dan_pakai_tata_letak_rental(): void
    {
        $aturan = new HotelPerRoomNightRule();

        $this->assertSame('line_items', $aturan->totalComposition());
        $this->assertTrue($aturan->chargeLinesDateFirstInPdf());
        $this->assertSame('Harga / kamar / malam', $aturan->unitPriceLabel());
    }

    public function test_kedua_mode_tetap_memakai_rentang_tanggal_dan_costing_tour_items(): void
    {
        // Kotak tanggal mulai & selesai pada baris bernominal hotel sudah ada
        // sebelum fitur ini dan tidak dicabut. costingSource sengaja TIDAK
        // diubah jadi invoice_items seperti rental — paket hotel tetap disusun
        // di muka.
        foreach ([new HotelPerPaxRule(), new HotelPerRoomNightRule()] as $aturan) {
            $this->assertTrue($aturan->chargeLinesUseDateRange(), get_class($aturan));
            $this->assertSame('tour_items', $aturan->costingSource(), get_class($aturan));
            $this->assertFalse($aturan->profitFromRevenue(), get_class($aturan));
        }
    }

    /** Peta harapan eksplisit: jenis baru di registry wajib memutuskan, bukan lolos diam-diam. */
    private const HARAPAN_MODE = [
        'hotel'     => ['per_pax', 'per_room_night'],
        'tour'      => [],
        'guide'     => [],
        'rental'    => [],
        'mice'      => [],
        'document'  => [],
        'ticketing' => [],
    ];

    public function test_hanya_hotel_yang_punya_pilihan_mode(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                self::HARAPAN_MODE,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan pricingModes di peta HARAPAN_MODE test ini."
            );

            $this->assertSame(self::HARAPAN_MODE[$key], $registry->for($key)->pricingModes(), "Jenis {$key}");
        }
    }
}
