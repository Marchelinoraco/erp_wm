<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceLedgerController extends Controller
{
    // ── Halaman: Transaksi (catat pendapatan/pengeluaran) ───────────────────
    public function transactions(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = array_pad(explode('-', $month), 2, now()->month);

        $rows = FinTransaction::with(['category', 'cashAccount'])
            ->whereYear('date', (int) $y)->whereMonth('date', (int) $m)
            ->orderByDesc('date')->orderByDesc('id')
            ->get();

        return Inertia::render('Finance/Transactions', [
            'month'        => $month,
            'transactions' => $rows,
            'categories'   => FinCategory::orderBy('type')->orderBy('sort_order')->orderBy('name')->get(),
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(),
            'summary'      => [
                'income'  => (float) $rows->where('direction', 'in')->sum('amount'),
                'expense' => (float) $rows->where('direction', 'out')->sum('amount'),
            ],
        ]);
    }

    public function storeTransaction(Request $request)
    {
        $data = $this->validateTransaction($request);
        $data['source']     = 'manual';
        $data['created_by'] = $request->user()?->name;
        FinTransaction::create($data);

        return back()->with('success', 'Transaksi dicatat.');
    }

    public function updateTransaction(Request $request, FinTransaction $finTransaction)
    {
        abort_unless($finTransaction->source === 'manual', 403, 'Transaksi otomatis dari AR/AP tidak bisa diedit di sini.');
        $finTransaction->update($this->validateTransaction($request));

        return back()->with('success', 'Transaksi diperbarui.');
    }

    public function destroyTransaction(FinTransaction $finTransaction)
    {
        abort_unless($finTransaction->source === 'manual', 403, 'Transaksi otomatis dari AR/AP tidak bisa dihapus di sini.');
        $finTransaction->delete();

        return back()->with('success', 'Transaksi dihapus.');
    }

    private function validateTransaction(Request $request): array
    {
        $data = $request->validate([
            'date'            => 'required|date',
            'direction'       => 'required|in:in,out',
            'fin_category_id' => 'required|exists:fin_categories,id',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'amount'          => 'required|numeric|min:1',
            'description'     => 'nullable|string|max:255',
        ]);

        // Pastikan jenis kategori selaras dengan arah (in=income, out=expense)
        $cat = FinCategory::find($data['fin_category_id']);
        $want = $data['direction'] === 'in' ? 'income' : 'expense';
        abort_if($cat && $cat->type !== $want, 422, 'Kategori tidak sesuai dengan jenis transaksi.');

        return $data;
    }

    // ── Kategori CRUD ────────────────────────────────────────────────────────
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ]);
        $data['sort_order'] = FinCategory::max('sort_order') + 1;
        FinCategory::create($data);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, FinCategory $finCategory)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        $finCategory->update($data);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroyCategory(FinCategory $finCategory)
    {
        abort_if($finCategory->is_system, 403, 'Kategori bawaan tidak bisa dihapus.');
        abort_if($finCategory->transactions()->exists(), 422, 'Kategori masih dipakai transaksi.');
        $finCategory->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    // ── Akun kas CRUD ─────────────────────────────────────────────────────────
    public function storeCashAccount(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:cash,bank',
            'opening_balance' => 'nullable|numeric',
        ]);
        $data['sort_order'] = CashAccount::max('sort_order') + 1;
        CashAccount::create($data);

        return back()->with('success', 'Akun kas ditambahkan.');
    }

    public function updateCashAccount(Request $request, CashAccount $cashAccount)
    {
        $cashAccount->update($request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:cash,bank',
            'opening_balance' => 'nullable|numeric',
            'is_active'       => 'boolean',
        ]));

        return back()->with('success', 'Akun kas diperbarui.');
    }

    public function destroyCashAccount(CashAccount $cashAccount)
    {
        abort_if($cashAccount->transactions()->exists(), 422, 'Akun masih dipakai transaksi.');
        $cashAccount->delete();

        return back()->with('success', 'Akun kas dihapus.');
    }
}
