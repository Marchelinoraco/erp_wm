<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'date'            => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'method'          => 'required|in:transfer,cash,other',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'notes'           => 'nullable|string',
        ]);

        $invoice->payments()->create($data);

        $paid = $invoice->payments()->sum('amount');
        $invoice->update([
            'status' => $paid >= $invoice->total ? 'paid' : 'partial',
        ]);

        return redirect()->back();
    }

    public function destroy(InvoicePayment $invoicePayment)
    {
        $invoice = $invoicePayment->invoice;
        $invoicePayment->delete();

        $paid = $invoice->payments()->sum('amount');
        $invoice->update([
            'status' => $paid <= 0 ? 'sent' : ($paid >= $invoice->total ? 'paid' : 'partial'),
        ]);

        return redirect()->back();
    }
}
