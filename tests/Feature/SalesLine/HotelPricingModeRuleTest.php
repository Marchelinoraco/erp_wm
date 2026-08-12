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
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    use \Tests\Support\CreatesSalesFixtures;

    /** Kunci: default registry untuk 'hotel' adalah mode pax, yaitu perilaku yang sudah berjalan. */
    public function test_registry_untuk_hotel_default_ke_mode_pax(): void
    {
        $this->assertInstanceOf(
            HotelPerPaxRule::class,
            app(SalesLineRuleRegistry::class)->for('hotel')
        );
    }

    public function test_mode_pax_memakai_komposisi_per_unit_dan_tata_letak_default(): void
    {
        $aturan = new HotelPerPaxRule();

        $this->assertSame('per_unit', $aturan->totalComposition());
        $this->assertSame('default', $aturan->chargeLineLayout());
        $this->assertSame('Harga / pax', $aturan->unitPriceLabel());
    }

    public function test_mode_kamar_menyusun_total_dari_baris_dan_pakai_tata_letak_hotel_room(): void
    {
        $aturan = new HotelPerRoomNightRule();

        $this->assertSame('line_items', $aturan->totalComposition());
        $this->assertSame('hotel_room', $aturan->chargeLineLayout());
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

    public function test_for_invoice_memilih_kelas_sesuai_mode(): void
    {
        $tour = $this->makeTour('hotel', ['pax' => 4]);

        $harapan = [
            null                              => HotelPerPaxRule::class,
            'per_pax'                         => HotelPerPaxRule::class,
            'per_room_night'                  => HotelPerRoomNightRule::class,
            'mode-yang-tidak-pernah-ada'      => HotelPerPaxRule::class,
        ];

        foreach ($harapan as $mode => $kelas) {
            $invoice = $this->makeInvoice($tour, 1_000_000);
            $invoice->update(['pricing_mode' => $mode === '' ? null : $mode]);

            $this->assertInstanceOf(
                $kelas,
                app(SalesLineRuleRegistry::class)->forInvoice($invoice->fresh()),
                'Mode ' . var_export($mode, true)
            );
        }
    }

    public function test_for_invoice_tanpa_invoice_jatuh_ke_aturan_tour(): void
    {
        $this->assertInstanceOf(
            \App\Services\SalesLine\TourRule::class,
            app(SalesLineRuleRegistry::class)->forInvoice(null)
        );
    }

    public function test_mode_tidak_mempengaruhi_jenis_selain_hotel(): void
    {
        // Kolomnya berlaku umum secara teknis, tapi hanya hotel yang punya
        // lebih dari satu mode. Nilai nyasar pada jenis lain tidak boleh
        // menggeser aturannya.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000);
        $invoice->update(['pricing_mode' => 'per_room_night']);

        $aturan = app(SalesLineRuleRegistry::class)->forInvoice($invoice->fresh());

        $this->assertInstanceOf(\App\Services\SalesLine\TourRule::class, $aturan);
        $this->assertSame('per_unit', $aturan->totalComposition());
    }

    public function test_payload_per_invoice_memuat_mode_aktif_dan_pilihannya(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update(['pricing_mode' => 'per_room_night']);

        $payload = app(SalesLineRuleRegistry::class)->payloadForInvoice($invoice->fresh());

        $this->assertSame('hotel', $payload['key']);
        $this->assertSame('line_items', $payload['totalComposition']);
        $this->assertSame(['per_pax', 'per_room_night'], $payload['pricingModes']);
        $this->assertSame('per_room_night', $payload['pricingMode']);
        $this->assertTrue($payload['chargeLinesUseDateRange']);
    }

    public function test_payload_per_invoice_melaporkan_mode_pax_saat_kolomnya_null(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $payload = app(SalesLineRuleRegistry::class)->payloadForInvoice($invoice->fresh());

        $this->assertSame('per_pax', $payload['pricingMode'], 'NULL dilaporkan sebagai per_pax, bukan null');
        $this->assertSame('per_unit', $payload['totalComposition']);
    }

    public function test_setiap_invoice_membawa_aturannya_sendiri_ke_frontend(): void
    {
        $tour = $this->makeTour('hotel', ['pax' => 4]);

        $paxInvoice   = $this->makeInvoice($tour, 1_000_000);
        $kamarInvoice = $this->makeInvoice($tour, 1_000_000);
        $kamarInvoice->update(['pricing_mode' => 'per_room_night']);

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(function ($page) use ($paxInvoice, $kamarInvoice) {
                $invoices = collect($page->toArray()['props']['tour']['invoices']);

                $pax   = $invoices->firstWhere('id', $paxInvoice->id);
                $kamar = $invoices->firstWhere('id', $kamarInvoice->id);

                // Dua invoice pada tour yang SAMA membawa aturan berbeda —
                // inilah yang tidak bisa diwakili prop salesLine tingkat tour.
                $this->assertSame('per_unit', $pax['rules']['totalComposition']);
                $this->assertSame('per_pax', $pax['rules']['pricingMode']);
                $this->assertSame('line_items', $kamar['rules']['totalComposition']);
                $this->assertSame('per_room_night', $kamar['rules']['pricingMode']);
                $this->assertSame(['per_pax', 'per_room_night'], $kamar['rules']['pricingModes']);
            });
    }

    public function test_invoice_jenis_lain_membawa_daftar_mode_kosong(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(function ($page) use ($invoice) {
                $baris = collect($page->toArray()['props']['tour']['invoices'])->firstWhere('id', $invoice->id);

                $this->assertSame([], $baris['rules']['pricingModes'], 'Tanpa pilihan mode, pemilihnya tidak muncul');
            });
    }
}
