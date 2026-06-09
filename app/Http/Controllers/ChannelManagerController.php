<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChannelManagerController extends Controller
{
    public function index(Request $request)
    {
        // Hanya supplier yang merupakan travel agent
        $suppliers = Supplier::where('is_travel_agent', true)
            ->with(['products' => function ($q) {
                $q->orderBy('type')->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        $pendingCount = Product::where('price_status', 'pending')->count();

        return Inertia::render('ChannelManager/Index', [
            'suppliers'    => $suppliers,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function approve(Product $product)
    {
        abort_unless($product->price_status === 'pending', 422, 'Tidak ada pengajuan harga.');

        $product->update([
            'cost'               => $product->pending_cost,
            'pending_cost'       => null,
            'price_status'       => null,
            'price_submitted_by' => null,
            'is_active'          => true,
        ]);

        return redirect()->back()->with('success', 'Harga disetujui & produk diaktifkan.');
    }

    public function reject(Product $product)
    {
        abort_unless($product->price_status === 'pending', 422, 'Tidak ada pengajuan harga.');

        $product->update([
            'pending_cost'       => null,
            'price_status'       => null,
            'price_submitted_by' => null,
        ]);

        return redirect()->back()->with('success', 'Pengajuan harga ditolak.');
    }

    public function updatePrice(Request $request, Product $product)
    {
        $data = $request->validate([
            'cost' => 'required|numeric|min:0',
            'sell' => 'required|numeric|min:0',
        ]);

        // Internal override langsung: set harga live, bersihkan pengajuan
        $product->update([
            'cost'               => $data['cost'],
            'sell'               => $data['sell'],
            'pending_cost'       => null,
            'price_status'       => null,
            'price_submitted_by' => null,
            'is_active'          => true,
        ]);

        return redirect()->back()->with('success', 'Harga produk diperbarui.');
    }
}
