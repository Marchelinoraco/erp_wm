<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'label'      => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'cost'       => 'required|numeric|min:0',
            'sell'       => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $product->prices()->create($data);

        return redirect()->back()->with('success', 'Periode harga ditambahkan.');
    }

    public function update(Request $request, ProductPrice $productPrice)
    {
        $data = $request->validate([
            'label'      => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'cost'       => 'required|numeric|min:0',
            'sell'       => 'required|numeric|min:0',
            'is_active'  => 'boolean',
            'notes'      => 'nullable|string',
        ]);

        $productPrice->update($data);

        return redirect()->back()->with('success', 'Periode harga diperbarui.');
    }

    public function destroy(ProductPrice $productPrice)
    {
        $productPrice->delete();

        return redirect()->back()->with('success', 'Periode harga dihapus.');
    }
}
