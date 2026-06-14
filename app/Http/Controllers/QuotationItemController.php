<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\Tour;
use Illuminate\Http\Request;

class QuotationItemController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        abort_if($tour->status === 'confirmed', 403, 'Tour sudah dikonfirmasi; quotation tidak bisa diubah.');

        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'label'      => 'required|string|max:255',
            'qty'        => 'integer|min:1',
            'nights'     => 'integer|min:1',
            'unit_sell'  => 'required|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

        $tour->quotationItems()->create([
            'product_id' => $data['product_id'] ?? null,
            'label'      => $data['label'],
            'qty'        => $data['qty'] ?? 1,
            'nights'     => $data['nights'] ?? 1,
            'unit_sell'  => $data['unit_sell'],
            'notes'      => $data['notes'] ?? null,
            'status'     => 'proposed',
            'sort_order' => $tour->quotationItems()->max('sort_order') + 1,
        ]);

        return redirect()->back()->with('success', 'Item ditambahkan ke quotation.');
    }

    public function update(Request $request, QuotationItem $quotationItem)
    {
        abort_if($quotationItem->tour->status === 'confirmed', 403);

        $data = $request->validate([
            'label'     => 'sometimes|string|max:255',
            'qty'       => 'sometimes|integer|min:1',
            'nights'    => 'sometimes|integer|min:1',
            'unit_sell' => 'sometimes|numeric|min:0',
            'notes'     => 'sometimes|nullable|string',
            'status'    => 'sometimes|string|in:proposed,approved,rejected',
        ]);

        $quotationItem->update($data);

        return redirect()->back()->with('success', 'Item quotation diperbarui.');
    }

    public function destroy(QuotationItem $quotationItem)
    {
        abort_if($quotationItem->tour->status === 'confirmed', 403);

        $quotationItem->delete();

        return redirect()->back()->with('success', 'Item quotation dihapus.');
    }
}
