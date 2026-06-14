<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;

class AgentProductPriceController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier && $product->supplier_id === $supplier->id, 403);

        $data = $request->validate([
            'label'      => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'cost'       => 'required|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

        $product->prices()->create([
            'label'        => $data['label'] ?? '',
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date'],
            'cost'         => 0,
            'sell'         => 0,
            'pending_cost' => $data['cost'],
            'status'       => 'pending',
            'submitted_by' => $request->user()->name,
            'submitted_at' => now(),
            'is_active'    => false,
            'notes'        => $data['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Periode harga diajukan, menunggu persetujuan.');
    }

    public function destroy(Request $request, ProductPrice $productPrice)
    {
        $supplier = $request->user()->supplier;
        abort_unless(
            $supplier && $productPrice->product->supplier_id === $supplier->id
                && $productPrice->status === 'pending',
            403
        );

        $productPrice->delete();

        return redirect()->back()->with('success', 'Pengajuan periode dihapus.');
    }
}
