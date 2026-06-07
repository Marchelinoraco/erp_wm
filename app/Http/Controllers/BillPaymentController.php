<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use Illuminate\Http\Request;

class BillPaymentController extends Controller
{
    public function store(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'date'   => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:transfer,cash,other',
            'notes'  => 'nullable|string',
        ]);

        $bill->payments()->create($data);

        $paid = $bill->payments()->sum('amount');
        $bill->update([
            'status' => $paid >= $bill->amount ? 'paid' : 'partial',
        ]);

        return redirect()->back();
    }

    public function destroy(BillPayment $billPayment)
    {
        $bill = $billPayment->bill;
        $billPayment->delete();

        $paid = $bill->payments()->sum('amount');
        $bill->update([
            'status' => $paid <= 0 ? 'unpaid' : ($paid >= $bill->amount ? 'paid' : 'partial'),
        ]);

        return redirect()->back();
    }
}
