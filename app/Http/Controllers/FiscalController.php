<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\FinTransaction;
use App\Models\FiscalCorrection;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class FiscalController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Finance/FiscalCorrection', $this->fiscalData($request));
    }

    public function pdf(Request $request)
    {
        $data = $this->fiscalData($request);
        return Pdf::stream('finance.fiscal_correction', $data + [
            'title'  => 'Koreksi Fiskal',
            'period' => "Tahun {$data['year']}",
        ], "Koreksi-Fiskal-{$data['year']}");
    }

    private function fiscalData(Request $request): array
    {
        $year   = (int) $request->input('year', now()->year);
        $regime = $request->input('regime', 'badan_22'); // badan_22 | pp23

        // ── Laba Komersial ───────────────────────────────────────────────────
        $totalRevenue = (float) Invoice::whereYear('date', $year)->sum('total');
        $totalCogs    = (float) Bill::whereYear('date', $year)->sum('amount');
        $grossProfit  = $totalRevenue - $totalCogs;

        $totalOpex   = (float) FinTransaction::where('source', 'manual')
            ->where('direction', 'out')->whereYear('date', $year)->sum('amount');
        $otherIncome = (float) FinTransaction::where('source', 'manual')
            ->where('direction', 'in')->whereYear('date', $year)->sum('amount');

        $assets        = FixedAsset::where('is_active', true)->orderBy('name')->get();
        $depKomersial  = (float) $assets->sum(fn ($a) => $a->depreciationForYear($year));
        $labaKomersial = $grossProfit - $totalOpex - $depKomersial + $otherIncome;

        // ── Penyusutan fiskal per aset ───────────────────────────────────────
        $depAssets = $assets->map(fn ($a) => [
            'id'           => $a->id,
            'name'         => $a->name,
            'category'     => $a->category,
            'fiscal_group' => $a->fiscal_group,
            'fiscal_label' => $a->fiscal_group ? (FixedAsset::FISCAL_GROUPS[$a->fiscal_group]['label'] ?? '—') : '—',
            'dep_comm'     => $a->depreciationForYear($year),
            'dep_fiscal'   => $a->fiscalDepreciationForYear($year),
            'selisih'      => $a->depreciationForYear($year) - $a->fiscalDepreciationForYear($year),
        ])->filter(fn ($d) => $d['dep_comm'] > 0 || $d['dep_fiscal'] > 0)->values();

        $depFiskal     = (float) $depAssets->sum('dep_fiscal');
        $selisihDep    = $depKomersial - $depFiskal; // + → kor.positif; - → kor.negatif

        // ── Koreksi fiskal manual ────────────────────────────────────────────
        $corrections = FiscalCorrection::where('year', $year)
            ->orderBy('type')->orderBy('sort_order')->orderBy('id')
            ->get()->map(fn ($c) => [
                'id'     => $c->id,
                'year'   => $c->year,
                'name'   => $c->name,
                'type'   => $c->type,
                'amount' => (float) $c->amount,
                'notes'  => $c->notes,
            ]);

        $manualPositif = (float) $corrections->where('type', 'positive')->sum('amount');
        $manualNegatif = (float) $corrections->where('type', 'negative')->sum('amount');

        $korPositif = $manualPositif + max(0, $selisihDep);
        $korNegatif = $manualNegatif + max(0, -$selisihDep);

        $pkp = max(0, round($labaKomersial + $korPositif - $korNegatif, 0));

        // ── PPh Terutang ─────────────────────────────────────────────────────
        if ($regime === 'pp23') {
            $taxBase     = $totalRevenue;
            $taxRatePct  = 0.5;
        } else {
            $taxBase     = $pkp;
            $taxRatePct  = 22.0;
        }
        $pphTerutang = round($taxBase * $taxRatePct / 100, 0);

        return [
            'year'          => $year,
            'years'         => $this->availableYears(),
            'regime'        => $regime,
            // Komersial
            'totalRevenue'  => $totalRevenue,
            'totalCogs'     => $totalCogs,
            'grossProfit'   => $grossProfit,
            'totalOpex'     => $totalOpex,
            'otherIncome'   => $otherIncome,
            'depKomersial'  => $depKomersial,
            'labaKomersial' => $labaKomersial,
            // Penyusutan fiskal
            'depAssets'     => $depAssets,
            'depFiskal'     => $depFiskal,
            'selisihDep'    => $selisihDep,
            // Koreksi manual
            'corrections'   => $corrections,
            // Summary PKP
            'korPositif'    => $korPositif,
            'korNegatif'    => $korNegatif,
            'pkp'           => $pkp,
            // Pajak
            'taxBase'       => $taxBase,
            'taxRatePct'    => $taxRatePct,
            'pphTerutang'   => $pphTerutang,
            // Constants
            'fiscalGroups'  => FixedAsset::FISCAL_GROUPS,
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year'   => 'required|integer|min:2000|max:2099',
            'name'   => 'required|string|max:200',
            'type'   => 'required|in:positive,negative',
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string|max:500',
        ]);
        $data['sort_order'] = FiscalCorrection::where('year', $data['year'])->max('sort_order') + 1;
        FiscalCorrection::create($data);

        return back()->with('success', 'Koreksi fiskal ditambahkan.');
    }

    public function update(Request $request, FiscalCorrection $fiscalCorrection)
    {
        $fiscalCorrection->update($request->validate([
            'name'   => 'required|string|max:200',
            'type'   => 'required|in:positive,negative',
            'amount' => 'required|numeric|min:0',
            'notes'  => 'nullable|string|max:500',
        ]));

        return back()->with('success', 'Koreksi fiskal diperbarui.');
    }

    public function destroy(FiscalCorrection $fiscalCorrection)
    {
        $fiscalCorrection->delete();

        return back()->with('success', 'Koreksi fiskal dihapus.');
    }

    private function availableYears(): array
    {
        $min   = Invoice::min('date') ?? now()->toDateString();
        $start = (int) Carbon::parse($min)->year;
        return array_values(array_unique(range(now()->year, min($start, now()->year))));
    }
}
