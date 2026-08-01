<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeAdvanceController extends Controller
{
    public function index()
    {
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $advances = EmployeeAdvance::with('employee')->orderByDesc('date')->get()->map(fn ($a) => [
            'id'          => $a->id,
            'employee'    => $a->employee->name,
            'date'        => $a->date->format('Y-m-d'),
            'amount'      => (float) $a->amount,
            'sisa'        => $a->sisa(),
            'note'        => $a->note,
            'bisa_dihapus'=> ! $a->sudahDipotong(),
        ]);

        return Inertia::render('Finance/EmployeeAdvances', [
            'employees'    => $employees,
            'advances'     => $advances,
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'date'            => 'required|date',
            'amount'          => 'required|numeric|min:1',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'note'            => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $request) {
            $kategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

            $trx = FinTransaction::create([
                'date'            => $data['date'],
                'direction'       => 'out',
                'fin_category_id' => $kategori->id,
                'cash_account_id' => $data['cash_account_id'],
                'amount'          => $data['amount'],
                'description'     => 'Kas bon — ' . Employee::find($data['employee_id'])->name,
                'source'          => 'advance',
                'created_by'      => $request->user()?->name,
            ]);

            EmployeeAdvance::create([
                'employee_id'        => $data['employee_id'],
                'date'               => $data['date'],
                'amount'             => $data['amount'],
                'note'               => $data['note'] ?? null,
                'fin_transaction_id' => $trx->id,
                'created_by'         => $request->user()?->name,
            ]);
        });

        return back()->with('success', 'Kas bon dicatat.');
    }

    public function destroy(EmployeeAdvance $employeeAdvance)
    {
        abort_if($employeeAdvance->sudahDipotong(), 422, 'Kas bon sudah pernah dipotong, tidak bisa dihapus.');

        // employee_advances.fin_transaction_id punya restrictOnDelete(): FinTransaction
        // tidak bisa dihapus selama ada baris employee_advances yang merujuknya —
        // dan karena EmployeeAdvance pakai SoftDeletes, delete() biasa TIDAK
        // menghapus baris secara fisik (hanya isi deleted_at). Karena itu baris
        // ini WAJIB forceDelete() dulu (hilang total dari tabel) sebelum
        // fin_transaction induknya boleh dihapus, kalau tidak FK constraint
        // menolak (SQLSTATE 23000) dan seluruh transaksi DB batal.
        DB::transaction(function () use ($employeeAdvance) {
            $trx = $employeeAdvance->finTransaction;
            $employeeAdvance->forceDelete();
            $trx->delete();
        });

        return back()->with('success', 'Kas bon dihapus.');
    }
}
