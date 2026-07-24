<?php

namespace Tests\Feature\Invoice;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci PENULIS KEEMPAT dari invoice.total.
 *
 * ApprovedInvoiceFrozenTest membuktikan tiga jalur invoices.* (updateProforma,
 * lockBaseline, approve) ditolak setelah invoice disetujui. Tapi ada jalur
 * KEEMPAT yang sama sekali tidak lewat InvoiceController: CostRequestController
 * ::approve() → appendAdditionalCharge() menulis LANGSUNG ke kolom total dan
 * total_idr milik invoice yang SUDAH disetujui:
 *
 *   $mainInvoice->total     = (float) $mainInvoice->total + $amount;
 *   $mainInvoice->total_idr = (float) $mainInvoice->total_idr + $amount;
 *
 * Jalur ini tidak melalui ensureNotApproved() sama sekali — dan memang tidak
 * mungkin, karena tujuannya justru menambah tagihan SETELAH invoice disetujui
 * (biaya tak terduga saat tour berjalan).
 *
 * KONSEKUENSI yang dicatat test ini: begitu ada biaya tambahan, invoice.total
 * TIDAK LAGI SAMA DENGAN unit_price × pax. Fase berikutnya yang menghitung
 * ulang total dari rumus harga (mis. lewat syncProformaTotal() atau backfill
 * serupa) akan MENGHAPUS uang yang sudah tertagih ke customer lewat baris
 * "Additional" ini. Bila test ini merah, itu tanda bahaya besar: konsekuensi
 * di atas telah berubah dan perlu ditinjau ulang, bukan sekadar "diperbaiki".
 */
class AdditionalChargeCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /**
     * cost-requests.approve ada di grup middleware role:admin,accountant
     * (routes/web.php) — beda dari rute invoices.* yang role:admin,sales.
     */
    private function accountantUser(): User
    {
        return User::create([
            'name'     => 'Akuntan Uji',
            'email'    => 'akuntan' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }

    public function test_biaya_tambahan_pada_invoice_disetujui_membuat_total_melebihi_unit_price_kali_pax(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000); // IDR — proforma murni: 250.000 × 4 = 1.000.000

        $invoice = $this->approveInvoice($invoice);

        $this->assertEquals(1_000_000, $invoice->total, 'Prasyarat: total masih murni dari rumus sebelum biaya tambahan');

        // bill_customer hanya didukung untuk invoice IDR (lihat CostRequestController::approve()).
        $costRequest = $tour->costRequests()->create([
            'requested_by' => $this->salesUser()->id,
            'category'     => 'other',
            'description'  => 'Sewa perahu tambahan karena cuaca',
            'amount'       => 100_000,
            'status'       => 'pending',
        ]);

        $this->actingAs($this->accountantUser())
            ->post(route('cost-requests.approve', $costRequest), [
                'amount'        => 100_000,
                'date'          => now()->toDateString(),
                'bill_customer' => true,
                'sell_amount'   => 150_000,
            ])
            ->assertRedirect();

        $invoice->refresh();

        $formulaTotal = (float) $invoice->unit_price * (int) $invoice->pax;

        $this->assertEquals(1_000_000, $formulaTotal, 'Rumus harga (unit_price × pax) tidak berubah — masih 250.000 × 4');
        $this->assertEquals(1_150_000, $invoice->total, 'total = 1.000.000 (proforma) + 150.000 (Additional)');
        $this->assertGreaterThan(
            $formulaTotal,
            $invoice->total,
            'total sekarang MELEBIHI unit_price × pax — backfill berbasis rumus akan menghapus tagihan tambahan ini'
        );
        $this->assertEquals(
            1_150_000,
            $invoice->total_idr,
            'total_idr ikut bertambah sama besar — hanya valid karena bill_customer disyaratkan invoice IDR (kurs 1)'
        );

        $lines = $invoice->description_lines;
        $last  = end($lines);
        $this->assertSame('Additional', $last['label'], 'Baris "Additional" ditempel ke description_lines');
        $this->assertEquals(150_000, $last['amount']);

        $this->assertSame('approved', $costRequest->fresh()->status);
        $this->assertEquals($invoice->id, $costRequest->fresh()->invoice_id, 'CostRequest mencatat invoice mana yang ditagih');
    }
}
