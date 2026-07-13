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
        $bill->delete();

        return redirect()->back();
    }
}
