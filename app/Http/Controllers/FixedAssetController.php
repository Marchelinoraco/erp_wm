<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedAssetController extends Controller
{
    public function index()
    {
        $year   = now()->year;
        $assets = FixedAsset::orderBy('category')->orderBy('name')->get()
            ->map(fn ($a) => array_merge($a->toArray(), [
                'annual_depreciation'  => $a->annualDepreciation(),
                'accumulated'          => $a->accumulatedAsOf($year),
                'book_value'           => $a->bookValueAsOf($year),
            ]));

        return Inertia::render('Finance/FixedAssets', [
            'assets'       => $assets,
            'categories'   => FixedAsset::CATEGORIES,
            'fiscalGroups' => FixedAsset::FISCAL_GROUPS,
            'currentYear'  => $year,
            'totals'       => [
                'cost'        => (float) $assets->where('is_active', true)->sum('acquisition_cost'),
                'accumulated' => (float) $assets->where('is_active', true)->sum('accumulated'),
                'book_value'  => (float) $assets->where('is_active', true)->sum('book_value'),
            ],
        ]);
    }

    private function validFiscalGroups(): string
    {
        return 'nullable|in:' . implode(',', array_keys(FixedAsset::FISCAL_GROUPS));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'category'          => 'required|in:vehicle,equipment,building,other',
            'acquisition_date'  => 'required|date',
            'acquisition_cost'  => 'required|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1|max:50',
            'residual_value'    => 'nullable|numeric|min:0',
            'fiscal_group'      => $this->validFiscalGroups(),
            'notes'             => 'nullable|string',
        ]);
        $data['residual_value'] ??= 0;
        FixedAsset::create($data);

        return back()->with('success', 'Aset tetap berhasil ditambahkan.');
    }

    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'category'          => 'required|in:vehicle,equipment,building,other',
            'acquisition_date'  => 'required|date',
            'acquisition_cost'  => 'required|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1|max:50',
            'residual_value'    => 'nullable|numeric|min:0',
            'fiscal_group'      => $this->validFiscalGroups(),
            'notes'             => 'nullable|string',
            'is_active'         => 'boolean',
        ]);
        $data['residual_value'] ??= 0;
        $fixedAsset->update($data);

        return back()->with('success', 'Aset tetap diperbarui.');
    }

    public function destroy(FixedAsset $fixedAsset)
    {
        abort_if($fixedAsset->is_active, 422, 'Nonaktifkan aset sebelum menghapus.');
        $fixedAsset->delete();

        return back()->with('success', 'Aset tetap dihapus.');
    }
}
