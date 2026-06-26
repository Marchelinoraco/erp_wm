<?php

namespace App\Http\Controllers;

use App\Models\FinanceSetting;
use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::orderBy('loan_type')->orderBy('name')->get()->map(fn ($l) => [
            'id'                   => $l->id,
            'name'                 => $l->name,
            'lender'               => $l->lender,
            'loan_type'            => $l->loan_type,
            'original_amount'      => (float) $l->original_amount,
            'start_date'           => $l->start_date->format('Y-m-d'),
            'tenor_months'         => $l->tenor_months,
            'monthly_installment'  => (float) $l->monthly_installment,
            'outstanding_balance'  => (float) $l->outstanding_balance,
            'notes'                => $l->notes,
            'is_active'            => $l->is_active,
        ]);

        $active = $loans->where('is_active', true);

        return Inertia::render('Finance/Loans', [
            'loans'           => $loans,
            'types'           => Loan::TYPES,
            'modalDisetor'    => FinanceSetting::get('modal_disetor'),
            'totals'          => [
                'original'    => (float) $active->sum('original_amount'),
                'outstanding' => (float) $active->sum('outstanding_balance'),
                'monthly'     => (float) $active->sum('monthly_installment'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'lender'               => 'nullable|string|max:100',
            'loan_type'            => 'required|in:bank_loan,leasing,other',
            'original_amount'      => 'required|numeric|min:1',
            'start_date'           => 'required|date',
            'tenor_months'         => 'required|integer|min:1|max:600',
            'monthly_installment'  => 'required|numeric|min:0',
            'outstanding_balance'  => 'required|numeric|min:0',
            'notes'                => 'nullable|string|max:500',
        ]);

        Loan::create($data);

        return back()->with('success', 'Pinjaman ditambahkan.');
    }

    public function update(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'lender'               => 'nullable|string|max:100',
            'loan_type'            => 'required|in:bank_loan,leasing,other',
            'original_amount'      => 'required|numeric|min:1',
            'start_date'           => 'required|date',
            'tenor_months'         => 'required|integer|min:1|max:600',
            'monthly_installment'  => 'required|numeric|min:0',
            'outstanding_balance'  => 'required|numeric|min:0',
            'notes'                => 'nullable|string|max:500',
            'is_active'            => 'boolean',
        ]);

        $loan->update($data);

        return back()->with('success', 'Pinjaman diperbarui.');
    }

    public function destroy(Loan $loan)
    {
        $loan->delete();

        return back()->with('success', 'Pinjaman dihapus.');
    }

    public function updateSetting(Request $request)
    {
        $data = $request->validate([
            'modal_disetor' => 'required|numeric|min:0',
        ]);

        FinanceSetting::updateOrCreate(
            ['key' => 'modal_disetor'],
            ['value' => $data['modal_disetor'], 'label' => 'Modal Disetor']
        );

        return back()->with('success', 'Modal Disetor diperbarui.');
    }
}
