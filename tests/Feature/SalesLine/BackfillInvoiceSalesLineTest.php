<?php

namespace Tests\Feature\SalesLine;

use App\Console\Commands\BackfillInvoiceSalesLine;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Setiap test di sini membuktikan satu klaim keamanan spesifik dari §7
 * dokumen desain — bukan sekadar "backfill jalan tanpa error".
 */
class BackfillInvoiceSalesLineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_mengisi_sales_line_sesuai_tipe_tour_untuk_ketujuh_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type), 500_000);

            $this->assertNull($invoice->sales_line, "Prasyarat: {$type} belum di-backfill");
        }

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        foreach (Invoice::all() as $invoice) {
            $this->assertSame(
                $invoice->tour->type,
                $invoice->fresh()->sales_line,
                "Invoice untuk tour {$invoice->tour->type} harus mendapat sales_line yang sama"
            );
        }
    }

    public function test_tidak_mengubah_satu_pun_nominal_pada_invoice_draft(): void
    {
        // Nominal yang direkam SEBELUM backfill — ini yang dibuktikan tetap sama.
        $tour    = $this->makeTour('guide', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $totalSebelum      = $invoice->total;
        $totalIdrSebelum   = $invoice->total_idr;
        $paxSebelum        = $invoice->pax;
        $unitPriceSebelum  = (string) $invoice->unit_price;

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $sesudah = $invoice->fresh();

        $this->assertEquals($totalSebelum, $sesudah->total, 'total tidak boleh bergeser');
        $this->assertEquals($totalIdrSebelum, $sesudah->total_idr, 'total_idr tidak boleh bergeser');
        $this->assertSame($paxSebelum, $sesudah->pax, 'pax tidak boleh bergeser');
        $this->assertSame($unitPriceSebelum, (string) $sesudah->unit_price, 'unit_price tidak boleh bergeser');
        $this->assertNull($sesudah->billing_quantities, 'billing_quantities TETAP null — §3.3.1');
    }

    public function test_tidak_mengubah_nominal_pada_invoice_yang_sudah_disetujui(): void
    {
        // Mencerminkan INV-2026-0009 di production: invoice disetujui dengan
        // biaya tambahan (§3.4.1) — total-nya bukan lagi unit_price × pax
        // sederhana, dan backfill harus tetap tidak menyentuhnya.
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->approveInvoice($this->makeInvoice($tour, 13_139_000));

        $totalSetelahApprove = $invoice->fresh()->total;

        // Simulasikan biaya tambahan pasca-persetujuan, seperti
        // CostRequestController::appendAdditionalCharge() di production.
        $invoice->update([
            'total'     => $totalSetelahApprove + 650_000,
            'total_idr' => $invoice->fresh()->total_idr + 650_000,
        ]);
        $totalDenganTambahan = $invoice->fresh()->total;

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $sesudah = $invoice->fresh();

        $this->assertEquals($totalDenganTambahan, $sesudah->total, 'total invoice approved+tambahan tidak boleh bergeser');
        $this->assertSame('tour', $sesudah->sales_line, 'sales_line tetap terisi meski sudah disetujui');
    }

    public function test_idempoten_baris_yang_sudah_terisi_tidak_diproses_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('mice'), 500_000);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);
        $sesudahPertama = $invoice->fresh()->sales_line;

        // Ubah tipe tour SETELAH backfill pertama. Bila backfill kedua
        // memproses ulang baris yang sudah terisi, sales_line akan berubah
        // ikut tour — itu justru bukti pelanggaran idempotensi.
        $invoice->tour->update(['type' => 'hotel']);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertSame($sesudahPertama, $invoice->fresh()->sales_line, 'Baris yang sudah terisi tidak boleh diproses ulang');
        $this->assertSame('mice', $invoice->fresh()->sales_line);
    }

    public function test_tour_soft_deleted_jatuh_ke_tour(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);
        $tour->delete();

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertSame(
            'tour',
            $invoice->fresh()->sales_line,
            'Tour soft-deleted -> $invoice->tour null -> fallback ke tour, sama seperti syncProformaTotal()'
        );
    }

    public function test_tidak_pernah_memanggil_sync_proforma_total(): void
    {
        // Bukti tidak langsung: unit_price sengaja diisi 0 dan pax dibuat
        // besar. Bila backfill diam-diam memanggil syncProformaTotal(), total
        // akan berubah (dari nilai awal manual ke 0 × pax = 0, atau sebaliknya
        // jika unit_price diubah — intinya total TIDAK boleh bergerak sama
        // sekali karena tidak ada perhitungan yang seharusnya berjalan).
        $tour    = $this->makeTour('ticketing', ['pax' => 99]);
        $invoice = $this->makeInvoice($tour, 500_000);

        // Paksa total ke nilai yang TIDAK mungkin dihasilkan syncProformaTotal
        // untuk kombinasi unit_price/pax di atas (500_000 × 99 = 49_500_000).
        $invoice->update(['total' => 1, 'total_idr' => 1]);

        $this->artisan('invoices:backfill-sales-line')->assertExitCode(0);

        $this->assertEquals(
            1,
            $invoice->fresh()->total,
            'Jika ini gagal, backfill diam-diam memanggil syncProformaTotal() dan menghitung ulang total — dilarang §7.4'
        );
    }

    public function test_laporan_jumlah_baris_diproses(): void
    {
        $this->makeInvoice($this->makeTour('tour'), 100_000);
        $this->makeInvoice($this->makeTour('hotel'), 100_000);

        $this->artisan('invoices:backfill-sales-line')
            ->expectsOutputToContain('2 invoice')
            ->assertExitCode(0);
    }
}
