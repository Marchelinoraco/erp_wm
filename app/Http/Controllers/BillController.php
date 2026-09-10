<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Tour;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'invoice_item_id'  => 'nullable|exists:invoice_items,id',
            'description'      => 'required|string|max:255',
            'category'         => 'required|in:hotel,transport,guide,restaurant,attraction,agent,other',
            'date'             => 'required|date',
            'due_date'         => 'nullable|date',
            'amount'           => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        $tour->bills()->create($data);

        return redirect()->back();
    }

    public function update(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'invoice_item_id'  => 'nullable|exists:invoice_items,id',
            'description'      => 'required|string|max:255',
            'category'         => 'required|in:hotel,transport,guide,restaurant,attraction,agent,other',
            'date'             => 'required|date',
            'due_date'         => 'nullable|date',
            'amount'           => 'required|numeric|min:0',
            'status'           => 'required|in:unpaid,partial,paid',
            'notes'            => 'nullable|string',
        ]);

        $bill->update($data);

        return redirect()->back();
    }

    public function destroy(Bill $bill)
    {
        // Ikut hapus payments-nya — kalau tidak, BillPayment::sum('amount') di
        // FinanceController tetap menghitung pembayaran bill yang sudah tidak ada,
        // membuat Hutang (AP) jadi salah/minus.
        //
        // WAJIB per model, BUKAN `$bill->payments()->delete()`. Sejak BillPayment
        // pakai SoftDeletes (0ce85d2, 18 Jul 2026), penghapusan massal lewat query
        // builder berubah jadi bulk UPDATE deleted_at yang TIDAK menyalakan event
        // `deleted` per baris — BillPaymentObserver tidak jalan, LedgerSync::remove()
        // tidak pernah dipanggil, dan baris fin_transactions tertinggal jadi hantu.
        // Per 10 Sep 2026 ada 12 hantu senilai Rp 16.272.000 di produksi.
        $bill->payments->each->delete();
        $bill->delete();

        return redirect()->back();
    }
}
