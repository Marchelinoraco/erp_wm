<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
            'total'    => 'required|numeric|min:0',
            'notes'    => 'nullable|string',
        ]);

        $tour->invoices()->create($data);

        return redirect()->back();
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
            'total'    => 'required|numeric|min:0',
            'status'   => 'required|in:draft,sent,partial,paid',
            'notes'    => 'nullable|string',
        ]);

        $invoice->update($data);

        return redirect()->back();
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->back();
    }
}
