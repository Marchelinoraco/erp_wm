<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci dari mana pengali tagihan diambil sekarang:
 *
 *     $pax = (int) ($this->tour?->pax ?? $this->pax ?? 1);
 *
 * Tour menang lebih dulu. Inilah sebab invoice untuk jasa guide ikut dikali
 * jumlah rombongan — akar masalah yang diperbaiki Fase 3.
 */
class PaxSourceCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_pax_tour_menang_atas_pax_invoice(): void
    {
        $tour = $this->makeTour('guide', ['pax' => 20]);

        // Invoice sengaja diberi pax berbeda — nilainya harus DIABAIKAN.
        $invoice = $this->makeInvoice($tour, 100_000, ['pax' => 3]);

        $this->assertEquals(
            2_000_000,
            $invoice->total,
            'Pengali diambil dari tour.pax (20), bukan dari invoice.pax (3)'
        );
        $this->assertEquals(20, $invoice->pax, 'invoice.pax ditimpa nilai dari tour');
    }

    public function test_mengubah_pax_tour_menggeser_total_invoice_draft(): void
    {
        $tour    = $this->makeTour('guide', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 100_000);

        $this->assertEquals(1_000_000, $invoice->total);

        // Orang lain mengubah ukuran rombongan di panel Header tour.
        $tour->update(['pax' => 12]);

        $invoice->refresh();
        $invoice->syncProformaTotal();

        $this->assertEquals(
            1_200_000,
            $invoice->fresh()->total,
            'Total invoice draft mengikuti perubahan pax tour pada sinkronisasi berikutnya'
        );
    }

    public function test_pax_tour_nol_diperlakukan_sebagai_satu(): void
    {
        // max($pax, 1) menutupi nilai 0 agar total tidak menjadi 0.
        $tour    = $this->makeTour('document', ['pax' => 0]);
        $invoice = $this->makeInvoice($tour, 750_000);

        $this->assertEquals(750_000, $invoice->total);
        $this->assertEquals(1, $invoice->pax);
    }

    public function test_pax_tour_tidak_boleh_null_di_database(): void
    {
        // Sudah diverifikasi empiris: kolom tours.pax bersifat NOT NULL, jadi
        // cabang `?? $this->pax ?? 1` pada syncProformaTotal() tidak pernah
        // tercapai lewat jalur normal. Dicatat di sini supaya Fase 1-3 tahu
        // cabang itu memang mati, bukan terlewat diuji.
        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeTour('ticketing', ['pax' => null]);
    }
}
