<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
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
                $q->with(['prices' => fn ($q2) => $q2->orderBy('start_date')])
                  ->orderBy('type')
                  ->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        $pendingCount = Product::where('price_status', 'pending')->count()
            + ProductPrice::where('status', 'pending')->count();

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

    // ── Period price approval ──

    public function approvePrice(Request $request, ProductPrice $productPrice)
    {
        abort_unless($productPrice->status === 'pending', 422, 'Tidak ada pengajuan harga periode.');

        $data = $request->validate([
            'sell' => 'required|numeric|min:0',
        ]);

        $productPrice->update([
            'cost'         => $productPrice->pending_cost,
            'sell'         => $data['sell'],
            'pending_cost' => null,
            'status'       => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'is_active'    => true,
        ]);

        return redirect()->back()->with('success', 'Periode harga disetujui.');
    }

    public function rejectPrice(ProductPrice $productPrice)
    {
        abort_unless($productPrice->status === 'pending', 422, 'Tidak ada pengajuan harga periode.');

        $productPrice->update([
            'pending_cost' => null,
            'status'       => null,
            'submitted_by' => null,
            'submitted_at' => null,
        ]);

        return redirect()->back()->with('success', 'Pengajuan periode ditolak.');
    }

    public function updatePeriodPrice(Request $request, ProductPrice $productPrice)
    {
        $data = $request->validate([
            'cost'      => 'required|numeric|min:0',
            'sell'      => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $productPrice->update($data);

        return redirect()->back()->with('success', 'Harga periode diperbarui.');
    }
}
