<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\FinTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_payment_creates_matching_ledger_transaction(): void
    {
        $tour = Tour::create(['pax' => 1, 'type' => 'tour']);
        $invoice = Invoice::create([
            'tour_id' => $tour->id,
            'number'  => 'INV-2026-11-0001',
            'date'    => '2026-07-01',
            'total'   => 1000000,
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'date'       => '2026-07-05',
            'amount'     => 1000000,
            'amount_idr' => 1000000,
            'method'     => 'transfer',
        ]);

        $txn = FinTransaction::where('source', 'invoice')->where('source_id', $payment->id)->first();

        $this->assertNotNull($txn, 'Pembayaran invoice harus otomatis membuat transaksi kas (AR sync).');
        $this->assertSame('in', $txn->direction);
        $this->assertEquals(1000000, (float) $txn->amount);
    }

    public function test_deleting_invoice_payment_removes_ledger_transaction(): void
    {
        $tour = Tour::create(['pax' => 1, 'type' => 'tour']);
        $invoice = Invoice::create([
            'tour_id' => $tour->id,
            'number'  => 'INV-2026-11-0002',
            'date'    => '2026-07-01',
            'total'   => 500000,
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'date'       => '2026-07-05',
            'amount'     => 500000,
            'amount_idr' => 500000,
            'method'     => 'cash',
        ]);

        $payment->delete();

        $this->assertDatabaseMissing('fin_transactions', [
            'source'    => 'invoice',
            'source_id' => $payment->id,
        ]);
    }

    public function test_bill_payment_creates_matching_ledger_transaction(): void
    {
        $tour = Tour::create(['pax' => 1, 'type' => 'tour']);
        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'DP Hotel',
            'category'    => 'hotel',
            'date'        => '2026-07-01',
            'amount'      => 700000,
        ]);

        $payment = BillPayment::create([
            'bill_id' => $bill->id,
            'date'    => '2026-07-06',
            'amount'  => 700000,
            'method'  => 'transfer',
        ]);

        $txn = FinTransaction::where('source', 'bill')->where('source_id', $payment->id)->first();

        $this->assertNotNull($txn, 'Pembayaran bill harus otomatis membuat transaksi kas (AP sync).');
        $this->assertSame('out', $txn->direction);
        $this->assertEquals(700000, (float) $txn->amount);
    }
}
