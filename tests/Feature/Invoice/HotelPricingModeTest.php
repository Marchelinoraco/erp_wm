<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Dua cara hitung invoice hotel. Pengunci terpenting di berkas ini adalah
 * bahwa pricing_mode NULL — keadaan seluruh invoice hotel yang sudah ada —
 * berperilaku persis seperti sebelum fitur ini ada.
 */
class HotelPricingModeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_invoice_baru_bernilai_null_dan_kolomnya_ada(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->assertNull($invoice->pricing_mode, 'Invoice baru tidak memilih mode apa pun sampai sales memilihnya');
    }

    public function test_konstanta_mode_terdefinisi(): void
    {
        $this->assertSame('per_pax', Invoice::PRICING_PER_PAX);
        $this->assertSame('per_room_night', Invoice::PRICING_PER_ROOM_NIGHT);
        $this->assertSame(['per_pax', 'per_room_night'], Invoice::PRICING_MODES);
    }

    public function test_mode_tersimpan_dan_terbaca_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $invoice->update(['pricing_mode' => Invoice::PRICING_PER_ROOM_NIGHT]);

        $this->assertSame('per_room_night', $invoice->fresh()->pricing_mode);
    }

    /** Dua tipe kamar dengan periode berbeda — kasus yang mode ini layani. */
    private const BARIS_KAMAR = [
        ['label' => 'Deluxe', 'detail' => 'Twin bed', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 6_000_000],
        ['label' => 'Suite', 'detail' => 'King bed', 'date' => '2026-08-17', 'date_end' => '2026-08-18', 'rooms' => 1, 'unit_price' => 2_500_000, 'amount' => 2_500_000],
    ];

    public function test_mode_kamar_menjumlah_baris_dan_mengabaikan_harga_pax(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);
        $invoice->fresh()->syncProformaTotal();

        // 1.000.000 × 4 pax = 4.000.000 SENGAJA tidak muncul di mana pun.
        $this->assertEquals(8_500_000, $invoice->fresh()->total);
    }

    public function test_mode_pax_tetap_harga_kali_pax(): void
    {
        // Pasangan pengunci: mode default tidak ikut berubah jadi penjumlahan baris.
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $this->assertEquals(4_000_000, $invoice->fresh()->total);
    }

    public function test_mode_kamar_menjumlah_baris_kamar_dan_biaya_tambahan(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => array_merge(self::BARIS_KAMAR, [
                ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
            ]),
        ]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertEquals(9_250_000, $invoice->fresh()->total, '8.500.000 kamar + 750.000 biaya tambahan');
    }

    public function test_mode_null_pada_hotel_identik_dengan_sebelum_fitur_ini(): void
    {
        // Pengunci terpenting: seluruh invoice hotel yang sudah ada bernilai
        // NULL, dan nominalnya tidak boleh bergeser sedikit pun.
        $tour    = $this->makeTour('hotel', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 350_000);

        $invoice->update(['description_lines' => [
            ['label' => 'Dokumen', 'detail' => 'Visa', 'amount' => 200_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertNull($invoice->fresh()->pricing_mode);
        $this->assertEquals(2_650_000, $invoice->fresh()->total, '350.000 × 7 pax + 200.000');
    }

    public function test_mode_dan_isian_kamar_tersimpan_lewat_proforma(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Deluxe', 'detail' => 'Twin bed', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 0],
                ],
            ])
            ->assertRedirect();

        $segar = $invoice->fresh();
        $baris = $segar->description_lines[0];

        $this->assertSame('per_room_night', $segar->pricing_mode);
        $this->assertEquals(2, $baris['rooms']);
        $this->assertEquals(1_500_000, $baris['unit_price']);
    }

    public function test_nominal_baris_kamar_dihitung_server_bukan_dipercaya_dari_browser(): void
    {
        // Browser mengirim nominal yang mengada-ada; server harus menimpanya.
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Deluxe', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 1],
                ],
            ])
            ->assertRedirect();

        $segar = $invoice->fresh();

        $this->assertEquals(6_000_000, $segar->description_lines[0]['amount'], '2 kamar × 2 malam × 1.500.000');
        $this->assertEquals(6_000_000, $segar->total);
    }

    public function test_biaya_tambahan_mempertahankan_nominal_yang_diketik(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
                ],
            ])
            ->assertRedirect();

        $baris = $invoice->fresh()->description_lines[0];

        $this->assertEquals(750_000, $baris['amount'], 'Tanpa key rooms, nominalnya tidak dihitung ulang');
        $this->assertArrayNotHasKey('rooms', $baris);
    }

    public function test_mode_tak_dikenal_ditolak_validasi(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'     => 'IDR',
                'unit_price'   => 1_000_000,
                'pricing_mode' => 'per_kucing',
            ])
            ->assertSessionHasErrors('pricing_mode');
    }

    public function test_invoice_yang_sudah_disetujui_menolak_ganti_mode(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $disetujui = $this->approveInvoice($invoice);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $disetujui), [
                'currency'     => 'IDR',
                'unit_price'   => 1_000_000,
                'pricing_mode' => 'per_room_night',
            ])
            // ensureNotApproved() melempar ValidationException (redirect + session
            // errors), bukan respons HTTP 403 — sejalan dengan konvensi pengunci
            // yang sama di ApprovedInvoiceFrozenTest dan StageGateCharacterizationTest.
            ->assertSessionHasErrors('invoice');

        $this->assertNull($disetujui->fresh()->pricing_mode);
    }

    public function test_ganti_mode_bolak_balik_tidak_menghilangkan_data(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $sales   = $this->salesUser();

        $barisKamar = [
            ['label' => 'Deluxe', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 0],
        ];

        $this->actingAs($sales)->patch(route('invoices.proforma', $invoice), [
            'currency' => 'IDR', 'unit_price' => 1_000_000,
            'pricing_mode' => 'per_room_night', 'description_lines' => $barisKamar,
        ])->assertRedirect();

        // Kembali ke mode pax — baris kamar TIDAK dihapus.
        $this->actingAs($sales)->patch(route('invoices.proforma', $invoice), [
            'currency' => 'IDR', 'unit_price' => 1_000_000,
            'pricing_mode' => 'per_pax', 'description_lines' => $barisKamar,
        ])->assertRedirect();

        $segar = $invoice->fresh();

        $this->assertSame('per_pax', $segar->pricing_mode);
        $this->assertEquals(1_000_000, $segar->unit_price, 'Harga/pax tetap utuh');
        $this->assertCount(1, $segar->description_lines, 'Baris kamar tetap tersimpan');
        $this->assertEquals(2, $segar->description_lines[0]['rooms']);
    }

    public function test_jenis_tanpa_pilihan_mode_tetap_null_setelah_disimpan(): void
    {
        // Backend tidak boleh bergantung pada frontend untuk ini: permintaan
        // tanpa pricing_mode wajib membiarkan kolomnya apa adanya.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 1_000_000,
            ])
            ->assertRedirect();

        $this->assertNull($invoice->fresh()->pricing_mode);
    }

    /** Data view yang sama persis dengan yang dipakai InvoiceController::build(). */
    private function renderInvoice(Invoice $invoice): string
    {
        $data = app(\App\Http\Controllers\InvoiceController::class)
            ->invoiceViewData($invoice->fresh());

        return view('invoice', $data)->render();
    }

    public function test_pdf_mode_kamar_memakai_tata_letak_tanggal_di_kiri(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);

        $html = $this->renderInvoice($invoice);

        $this->assertStringContainsString('<td class="k">15/08/2026 – 17/08/2026</td>', $html);
        $this->assertStringNotContainsString('<td class="k">Deluxe</td>', $html);
    }

    public function test_pdf_mode_kamar_tidak_mencetak_harga_per_malam_maupun_jumlah_kamar(): void
    {
        // Keduanya hanya dasar perhitungan internal — customer melihat hasilnya.
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);

        $html = $this->renderInvoice($invoice);

        $this->assertStringNotContainsString('1.500.000', $html, 'Harga per malam tidak tercetak');
        $this->assertStringNotContainsString('2 kamar', $html);
        $this->assertStringNotContainsString('2 malam', $html);
    }

    public function test_pdf_mode_pax_tidak_berubah(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update(['description_lines' => [
            ['label' => 'Dokumen', 'date' => '2026-08-15', 'detail' => 'Visa', 'amount' => 200_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $html = $this->renderInvoice($invoice);

        $this->assertStringContainsString('<td class="k">Dokumen</td>', $html);
        $this->assertStringContainsString('>Price<', $html);
    }
}
