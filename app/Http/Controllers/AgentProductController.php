<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentProductController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $request->user()->supplier;

        abort_unless($supplier, 403, 'Akun ini belum terhubung ke supplier.');

        $products = $supplier->products()
            ->with(['prices' => fn ($q) => $q->orderBy('start_date')])
            ->latest()
            ->get(['id', 'name', 'type', 'unit', 'cost', 'pending_cost', 'price_status', 'price_updated_at', 'currency', 'is_active']);

        return Inertia::render('AgentProducts/Index', [
            'supplier' => $supplier->only('id', 'name', 'type'),
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:hotel,transport,guide,restaurant,attraction,agent,other',
            'unit' => 'required|string|in:per_pax,per_unit,per_night',
            'cost' => 'required|numeric|min:0',
        ]);

        // Produk baru: harga diajukan, belum aktif sampai disetujui internal
        $supplier->products()->create([
            'name'               => $data['name'],
            'type'               => $data['type'],
            'unit'               => $data['unit'],
            'cost'               => 0,
            'pending_cost'       => $data['cost'],
            'price_status'       => 'pending',
            'price_submitted_by' => $request->user()->name,
            'price_updated_at'   => now(),
            'sell'               => 0,
            'currency'           => 'IDR',
            'is_active'          => false,
        ]);

        return redirect()->back()->with('success', 'Produk diajukan. Menunggu persetujuan tim internal.');
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeOwnership($request, $product);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:hotel,transport,guide,restaurant,attraction,agent,other',
            'unit' => 'required|string|in:per_pax,per_unit,per_night',
            'cost' => 'required|numeric|min:0',
        ]);

        $product->name = $data['name'];
        $product->type = $data['type'];
        $product->unit = $data['unit'];

        // Perubahan harga modal → masuk antrian persetujuan, harga live tidak berubah dulu
        if ((float) $data['cost'] !== (float) $product->cost) {
            $product->pending_cost       = $data['cost'];
            $product->price_status       = 'pending';
            $product->price_submitted_by = $request->user()->name;
            $product->price_updated_at   = now();
        }

        $product->save();

        return redirect()->back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeOwnership($request, $product);
        $product->delete();

        return redirect()->back()->with('success', 'Produk dihapus.');
    }

    private function authorizeOwnership(Request $request, Product $product): void
    {
        abort_unless($product->supplier_id === $request->user()->supplier_id, 403);
    }
}
