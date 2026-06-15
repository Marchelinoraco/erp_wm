<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index()
    {
        return Inertia::render('Finance/BankAccounts', [
            'accounts' => BankAccount::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['sort_order'] = BankAccount::max('sort_order') + 1;
        BankAccount::create($data);

        return redirect()->back()->with('success', 'Rekening ditambahkan.');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $bankAccount->update($this->validateData($request));

        return redirect()->back()->with('success', 'Rekening diperbarui.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();

        return redirect()->back()->with('success', 'Rekening dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'bank'           => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'holder_name'    => 'required|string|max:150',
            'is_active'      => 'boolean',
        ]);
    }
}
