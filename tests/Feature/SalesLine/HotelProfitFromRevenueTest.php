<?php

namespace Tests\Feature\SalesLine;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Tour;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Hotel dijual gelondongan ke customer (harga/pax × pax, atau Σ baris kamar);
 * Rincian Profit invoice hanya mencatat modalnya. Jadi profit hotel = tagihan
 * customer − Σ modal item, sama seperti tipe `tour` — bukan Σ(jual − modal) per
 * item, yang selalu Rp 0 karena tidak ada yang mengisi "jual per baris" hotel.
 *
 * Angka fixture diambil dari kasus nyata WM-2026-16-0003: harga/pax 2.492.500
 * × 2 pax = tagihan 4.985.000; modal item 4.650.000; profit 335.000 (6,7%).
 */
class HotelProfitFromRevenueTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function hotelDenganModalDiRincianProfit(bool $setujui): Tour
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 2_492_500); // total = 2.492.500 × 2 = 4.985.000

        $product = Product::create([
            'name' => 'Kamar hotel',
            'type' => 'hotel',
            'cost' => 4_650_000,
            'sell' => 4_650_000,
        ]);

        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'product_id'   => $product->id,
            'product_type' => 'hotel',
            'description'  => 'Kamar hotel',
            'qty'          => 1,
            'nights'       => 1,
            'unit_cost'    => 4_650_000,
            // Sengaja ngawur & beda dari modal maupun tagihan — harus DIABAIKAN.
            'unit_sell'    => 9_999_999,
        ]);

        if ($setujui) {
            $this->approveInvoice($invoice->fresh());
        }

        return $tour->fresh(['items', 'invoices.items']);
    }

    public function test_profit_hotel_adalah_tagihan_dikurangi_modal_item(): void
    {
        $tour = $this->hotelDenganModalDiRincianProfit(setujui: true);

        // tour_items memang kosong untuk hotel — modal ada di Rincian Profit invoice.
        $this->assertSame(0, $tour->items()->count());

        // Jual = tagihan invoice (total_idr), BUKAN Σ unit_sell item (9.999.999).
        $this->assertSame(4_985_000.0, $tour->total_sell);
        // Modal = Σ line_cost item invoice.
        $this->assertSame(4_650_000.0, $tour->total_cost);
        $this->assertSame(335_000.0, $tour->profit);
        $this->assertSame(6.7, $tour->margin);
    }

    public function test_hotel_belum_disetujui_accessor_tetap_nol(): void
    {
        // Sama seperti tipe `tour`: angka berbasis tagihan hanya dihitung dari
        // invoice yang SUDAH disetujui. Panel invoice punya estimasi pra-approval
        // sendiri; accessor tingkat tour (kolom list, Ringkasan Biaya) tidak.
        $tour = $this->hotelDenganModalDiRincianProfit(setujui: false);

        $this->assertSame(0.0, $tour->total_sell);
        $this->assertSame(0.0, $tour->total_cost);
        $this->assertSame(0.0, $tour->profit);
    }

    public function test_halaman_list_hotel_menampilkan_profit_dari_tagihan(): void
    {
        // Keluhan asli: kolom "Nilai Jual" & "Profit" di halaman list Hotel
        // selalu Rp 0. Halaman itu membaca accessor rule-aware yang sama dengan
        // halaman detail (withSum ditimpa oleh getTotalSellAttribute()).
        $tour = $this->hotelDenganModalDiRincianProfit(setujui: true);

        $this->actingAs($this->salesUser())
            ->get(route('tours.index', ['type' => 'hotel']))
            ->assertInertia(function ($page) use ($tour) {
                $baris = collect($page->toArray()['props']['tours']['data'])
                    ->firstWhere('id', $tour->id);

                $this->assertSame(4_985_000.0, (float) $baris['total_sell']);
                $this->assertSame(4_650_000.0, (float) $baris['total_cost']);
            });
    }

    public function test_kedua_mode_hitung_hotel_menghitung_profit_dari_tagihan(): void
    {
        // Panel invoice & halaman Keuangan membaca aturan PER INVOICE lewat
        // pricing_mode. Kedua kelas mode harus setuju: profit dari tagihan.
        $registry = app(SalesLineRuleRegistry::class);
        $tour     = $this->makeTour('hotel', ['pax' => 2]);

        $pax  = $this->makeInvoice($tour, 1_000_000);
        $room = $this->makeInvoice($tour, 1_000_000);
        $room->update(['pricing_mode' => 'per_room_night']);

        $this->assertTrue($registry->payloadForInvoice($pax->fresh())['profitFromRevenue']);
        $this->assertTrue($registry->payloadForInvoice($room->fresh())['profitFromRevenue']);
    }
}
