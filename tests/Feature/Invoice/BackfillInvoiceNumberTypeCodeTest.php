<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Setiap test membuktikan satu klaim keamanan spesifik dari §5 dokumen
 * desain (docs/superpowers/specs/2026-07-27-satu-nomor-invoice-design.md)
 * — bukan sekadar "backfill jalan tanpa error".
 */
class BackfillInvoiceNumberTypeCodeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_mengisi_kode_tipe_untuk_number_format_lama(): void
    {
        $tour    = $this->makeTour('hotel');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']); // simulasi format lama (2 tanda hubung)

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-2026-16-0001', $invoice->fresh()->number, 'hotel = kode tipe 16 (Tour::TYPE_CODES)');
    }

    public function test_tidak_mengubah_number_yang_sudah_berkode_tipe(): void
    {
        $tour         = $this->makeTour('tour');
        $invoice      = $this->makeInvoice($tour, 500_000); // sudah dapat number berkode tipe dari Invoice::nextNumber()
        $nomorSebelum = $invoice->number;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame($nomorSebelum, $invoice->fresh()->number, 'Invoice yang sudah berkode tipe TIDAK BOLEH berubah sama sekali');
    }

    public function test_backfill_menyambung_di_ujung_bukan_menyisip_kronologis(): void
    {
        // Invoice BARU (dibuat sekarang, sudah otomatis berkode tipe) dapat 0001.
        $tourBaru    = $this->makeTour('tour');
        $invoiceBaru = $this->makeInvoice($tourBaru, 500_000);
        $this->assertSame('INV-' . now()->year . '-11-0001', $invoiceBaru->number);

        // Invoice LAMA (tipe sama), disimulasikan SEBENARNYA dibuat lebih dulu
        // secara kronologis (created_at 2 bulan lalu), tapi baru SEKARANG
        // di-backfill. TIDAK BOLEH mengambil 0001 lalu menggeser invoice baru
        // ke 0002 — harus dapat 0002 (menyambung di ujung urutan yang ADA).
        $tourLama    = $this->makeTour('tour');
        $invoiceLama = $this->makeInvoice($tourLama, 300_000);
        $invoiceLama->update([
            'number'     => 'INV-' . now()->year . '-0001',
            'created_at' => now()->subMonths(2),
        ]);

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-' . now()->year . '-11-0001', $invoiceBaru->fresh()->number, 'Nomor yang sudah stabil TIDAK BOLEH bergeser');
        $this->assertSame('INV-' . now()->year . '-11-0002', $invoiceLama->fresh()->number, 'Invoice lama dapat nomor BERIKUTNYA, bukan menyisip di 0001');
    }

    public function test_tidak_mengubah_nominal_atau_kolom_lain(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->approveInvoice($this->makeInvoice($tour, 13_139_000));
        $invoice->update(['number' => 'INV-2026-0009']);

        $totalSebelum      = $invoice->fresh()->total;
        $totalIdrSebelum   = $invoice->fresh()->total_idr;
        $approvedAtSebelum = (string) $invoice->fresh()->approved_at;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $sesudah = $invoice->fresh();
        $this->assertEquals($totalSebelum, $sesudah->total, 'total tidak boleh bergeser');
        $this->assertEquals($totalIdrSebelum, $sesudah->total_idr, 'total_idr tidak boleh bergeser');
        $this->assertSame($approvedAtSebelum, (string) $sesudah->approved_at, 'approved_at tidak boleh bergeser');
    }

    public function test_idempoten_pass_kedua_tidak_mengubah_apa_pun(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']);

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);
        $nomorSetelahPass1 = $invoice->fresh()->number;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame($nomorSetelahPass1, $invoice->fresh()->number, 'Pass kedua tidak boleh mengubah apa pun — semua sudah berkode tipe (3 tanda hubung)');
    }

    public function test_tidak_ada_duplikat_number_setelah_backfill(): void
    {
        foreach (['tour', 'tour', 'hotel', 'guide'] as $i => $type) {
            $invoice = $this->makeInvoice($this->makeTour($type), 100_000);
            $invoice->update(['number' => 'INV-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)]);
        }

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $numbers = Invoice::pluck('number');
        $this->assertSame($numbers->count(), $numbers->unique()->count(), 'Tidak boleh ada number yang sama pada dua invoice berbeda');
    }

    public function test_tour_soft_deleted_jatuh_ke_kode_11(): void
    {
        $tour    = $this->makeTour('hotel');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']);
        $tour->delete();

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-2026-11-0001', $invoice->fresh()->number, 'Tour soft-deleted -> $invoice->tour null -> fallback kode 11, sama seperti Invoice::nextNumber()');
    }

    public function test_laporan_jumlah_baris_diproses(): void
    {
        $inv1 = $this->makeInvoice($this->makeTour('tour'), 100_000);
        $inv1->update(['number' => 'INV-2026-0001']);
        $inv2 = $this->makeInvoice($this->makeTour('hotel'), 100_000);
        $inv2->update(['number' => 'INV-2026-0002']);

        $this->artisan('invoices:backfill-number-type-code')
            ->expectsOutputToContain('2 invoice')
            ->assertExitCode(0);
    }
}
