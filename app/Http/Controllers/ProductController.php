<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('supplier')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate(20)->withQueryString();

        return Inertia::render('Products/Index', [
            'products'  => $products,
            'filters'   => $request->only('type', 'search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Form', [
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:hotel,transport,guide,restaurant,attraction,other',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit'        => 'required|string|in:per_pax,per_unit,per_night',
            'cost'        => 'required|numeric|min:0',
            'sell'        => 'required|numeric|min:0',
            'currency'    => 'required|string|size:3',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
        ]);

        Product::create($data);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Form', [
            'product'   => $product,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:hotel,transport,guide,restaurant,attraction,other',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit'        => 'required|string|in:per_pax,per_unit,per_night',
            'cost'        => 'required|numeric|min:0',
            'sell'        => 'required|numeric|min:0',
            'currency'    => 'required|string|size:3',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
        ]);

        $product->update($data);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
