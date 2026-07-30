<?php

namespace Tests\Feature\SalesLine;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Tour;
use App\Models\TourItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Ringkasan Biaya (CostingPanel) menampilkan Rp 0 untuk rental yang invoicenya
 * sudah disetujui dan Rincian Profitnya terisi. Sebabnya `total_cost`/`total_sell`
 * menjumlah `tour_items`, padahal rental mencatat modal & jual di Rincian Profit
 * invoice — tabel tour_items-nya memang kosong.
 *
 * Angka di berkas ini diambil dari kasus nyata WM-2026-13-0001 (Innova Reborn,
 * 3 baris): jual 2.530.000, modal 2.000.000, profit 530.000, margin 20,9%.
 */
class RentalCostingFromInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Tiga baris Rincian Profit persis seperti kasus nyata. */
    private function invoiceRentalDisetujui(string $type): Tour
    {
        $tour    = $this->makeTour($type, ['pax' => 1]);
        $invoice = $this->makeInvoice($tour, 2_530_000, [
            'description_lines' => [
                ['label' => 'Innova Reborn', 'date' => '2026-07-17', 'detail' => 'Fullday Luar Kota', 'amount' => 1_480_000],
                ['label' => 'Innova Reborn', 'date' => '2026-07-19', 'detail' => 'Drop Paradise', 'amount' => 700_000],
                ['label' => 'Innova Reborn', 'date' => '2026-07-20', 'detail' => 'Drop Airport', 'amount' => 350_000],
            ],
        ]);

        foreach ([[1_250_000, 1_480_000], [500_000, 700_000], [250_000, 350_000]] as [$cost, $sell]) {
            $product = Product::create([
                'name' => 'Sewa Innova',
                'type' => 'transport',
                'cost' => $cost,
                'sell' => $sell,
            ]);

            InvoiceItem::create([
                'invoice_id'   => $invoice->id,
                'product_id'   => $product->id,
                'product_type' => $product->type,
                'description'  => $product->name,
                'qty'          => 1,
                'nights'       => 1,
                'unit_cost'    => $cost,
                'unit_sell'    => $sell,
            ]);
        }

        $this->approveInvoice($invoice->fresh());

        return $tour->fresh(['items', 'invoices.items']);
    }

    public function test_rental_membaca_modal_dan_jual_dari_rincian_profit(): void
    {
        $tour = $this->invoiceRentalDisetujui('rental');

        // tour_items memang kosong — itu kondisi normal untuk rental.
        $this->assertSame(0, $tour->items()->count());

        $this->assertSame(2_000_000.0, $tour->total_cost);
        $this->assertSame(2_530_000.0, $tour->total_sell);
        $this->assertSame(530_000.0, $tour->profit);
        $this->assertSame(20.9, $tour->margin);
    }

    public function test_tipe_tour_tidak_berubah_tetap_dari_tagihan(): void
    {
        // Pagar utama: pemilik repo menyatakan perilaku tipe `tour` SUDAH BENAR
        // dan tidak boleh ikut bergeser oleh perbaikan rental ini.
        $tour = $this->invoiceRentalDisetujui('tour');

        // Untuk tipe `tour` baris bernominal MENAMBAH harga/pax, jadi tagihannya
        // 2.530.000 (harga/pax × 1) + 2.530.000 (tiga baris) = 5.060.000.
        // Angka itu jelas berbeda dari Σ sell item (2.530.000) — pembeda yang
        // membuktikan tipe `tour` benar-benar memakai tagihan, bukan item.
        $this->assertSame(2_000_000.0, $tour->total_cost);
        $this->assertSame(5_060_000.0, $tour->total_sell);
    }

    public function test_jenis_lain_tetap_membaca_tour_items(): void
    {
        // Hanya rental yang pindah sumber. Lima jenis lain masih menyusun
        // paketnya di tour_items, dan itu tidak boleh ikut berubah.
        foreach (['hotel', 'guide', 'mice', 'document', 'ticketing'] as $type) {
            $tour = $this->invoiceRentalDisetujui($type);

            TourItem::create([
                'tour_id'   => $tour->id,
                'unit_cost' => 100_000,
                'unit_sell' => 175_000,
            ]);

            $segar = $tour->fresh(['items', 'invoices.items']);

            $this->assertSame(100_000.0, $segar->total_cost, "Jenis {$type}");
            $this->assertSame(175_000.0, $segar->total_sell, "Jenis {$type}");
        }
    }

    public function test_rental_tanpa_invoice_disetujui_tetap_dari_tour_items(): void
    {
        // Tanpa invoice yang disetujui tidak ada Rincian Profit yang sah untuk
        // dibaca, jadi perilakunya tidak berubah dari sebelumnya.
        $tour = $this->makeTour('rental', ['pax' => 1]);
        $this->makeInvoice($tour, 2_530_000, [
            'description_lines' => [
                ['label' => 'Innova Reborn', 'date' => '2026-07-17', 'detail' => '', 'amount' => 1_480_000],
            ],
        ]);

        TourItem::create([
            'tour_id'   => $tour->id,
            'unit_cost' => 90_000,
            'unit_sell' => 120_000,
        ]);

        $segar = $tour->fresh(['items', 'invoices.items']);

        $this->assertSame(90_000.0, $segar->total_cost);
        $this->assertSame(120_000.0, $segar->total_sell);
    }
}
