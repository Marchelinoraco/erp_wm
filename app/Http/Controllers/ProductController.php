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
            'type'        => 'required|string|in:hotel,transport,guide,restaurant,attraction,venue,equipment,agent,other',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit'        => 'required|string|in:per_pax,per_unit,per_night',
            'cost'        => 'required|numeric|min:0',
            'sell'        => 'required|numeric|min:0',
            'currency'    => 'required|string|size:3',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
            'group_label' => 'nullable|string|max:255',
            'grade'       => 'nullable|string|in:hemat,standar,premium',
        ]);

        Product::create($data);

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $product->load(['prices' => fn ($q) => $q->orderBy('start_date')]);

        return Inertia::render('Products/Form', [
            'product'   => $product,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:hotel,transport,guide,restaurant,attraction,venue,equipment,agent,other',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit'        => 'required|string|in:per_pax,per_unit,per_night',
            'cost'        => 'required|numeric|min:0',
            'sell'        => 'required|numeric|min:0',
            'currency'    => 'required|string|size:3',
            'is_active'   => 'boolean',
            'notes'       => 'nullable|string',
            'group_label' => 'nullable|string|max:255',
            'grade'       => 'nullable|string|in:hemat,standar,premium',
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

    /** Download template CSV kosong untuk import produk massal. */
    public function downloadTemplate()
    {
        $path = public_path('templates/template_produk.csv');
        return response()->download($path, 'template_produk.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Export daftar supplier aktif sebagai CSV referensi (selalu up-to-date). */
    public function exportSuppliers()
    {
        $suppliers = Supplier::orderBy('type')->orderBy('name')
            ->get(['name', 'type', 'contact_person', 'phone', 'email']);

        $rows   = [];
        $rows[] = ['PETUNJUK: Gunakan kolom "name" persis seperti ini di template_produk.csv (kolom supplier_name)'];
        $rows[] = [];
        $rows[] = ['name', 'type', 'contact_person', 'phone', 'email'];
        foreach ($suppliers as $s) {
            $rows[] = [
                $s->name,
                $s->type ?? '',
                $s->contact_person ?? '',
                $s->phone ?? '',
                $s->email ?? '',
            ];
        }

        $csv = collect($rows)->map(function ($row) {
            return implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row));
        })->implode("\n");

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="referensi_supplier.csv"',
        ]);
    }
}
