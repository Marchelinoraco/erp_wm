<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillPaymentController extends Controller
{
    public function store(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'date'            => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'method'          => 'required|in:transfer,cash,other',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'notes'           => 'nullable|string',
        ]);

        // Pembayaran dan baris buku besarnya harus jadi bersama atau batal
        // bersama. LedgerSync menulis lewat observer `saved`, jadi tanpa
        // pembungkus ini kegagalan di sana meninggalkan pembayaran yatim yang
        // tidak pernah muncul di laporan keuangan — persis kejadian 4 Sep 2026.
        DB::transaction(function () use ($bill, $data) {
            $bill->payments()->create($data);

            $paid = $bill->payments()->sum('amount');
            $bill->update([
                'status' => $paid >= $bill->amount ? 'paid' : 'partial',
            ]);
        });

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
