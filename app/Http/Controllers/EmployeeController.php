<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeComponent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        // EmployeeAdvance (Kas Bon) belum dibuat — Task 1 sengaja meninggalkan
        // Employee::advances() merujuk kelas yang belum ada karena baru
        // dipakai tahap berikutnya. Jaga di sini supaya index() tidak crash
        // sebelum tabel/model kas bon lahir; begitu ada, baris ini otomatis
        // aktif tanpa perlu menyentuh controller ini lagi.
        $hasAdvances = class_exists(\App\Models\EmployeeAdvance::class);

        $employees = Employee::with('components')->orderBy('name')->get()->map(fn ($e) => [
            'id'                   => $e->id,
            'name'                 => $e->name,
            'position'             => $e->position,
            'phone'                => $e->phone,
            'email'                => $e->email,
            'base_salary'          => (float) $e->base_salary,
            'join_date'            => $e->join_date?->format('Y-m-d'),
            'bank_name'            => $e->bank_name,
            'bank_account_number'  => $e->bank_account_number,
            'bank_account_holder'  => $e->bank_account_holder,
            'is_active'            => $e->is_active,
            'notes'                => $e->notes,
            'sisa_kas_bon'         => $hasAdvances ? (float) $e->advances()->get()->sum(fn ($a) => $a->sisa()) : 0.0,
            'components'           => $e->components->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'type' => $c->type,
                'amount' => (float) $c->amount, 'is_active' => $c->is_active,
            ])->values(),
        ]);

        return Inertia::render('Employees/Index', ['employees' => $employees]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'position'             => 'nullable|string|max:100',
            'phone'                => 'nullable|string|max:30',
            'email'                => 'nullable|email|max:150',
            'base_salary'          => 'required|numeric|min:0',
            'join_date'            => 'nullable|date',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:50',
            'bank_account_holder'  => 'nullable|string|max:150',
            'notes'                => 'nullable|string|max:1000',
        ]);

        Employee::create($data);

        return back()->with('success', 'Karyawan ditambahkan.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:150',
            'position'             => 'nullable|string|max:100',
            'phone'                => 'nullable|string|max:30',
            'email'                => 'nullable|email|max:150',
            'base_salary'          => 'required|numeric|min:0',
            'join_date'            => 'nullable|date',
            'bank_name'            => 'nullable|string|max:100',
            'bank_account_number'  => 'nullable|string|max:50',
            'bank_account_holder'  => 'nullable|string|max:150',
            'is_active'            => 'boolean',
            'notes'                => 'nullable|string|max:1000',
        ]);

        $employee->update($data);

        return back()->with('success', 'Karyawan diperbarui.');
    }

    public function storeComponent(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:100',
            'type'   => 'required|in:tunjangan,potongan',
            'amount' => 'required|numeric|min:1',
        ]);
        $data['sort_order'] = EmployeeComponent::where('employee_id', $employee->id)->max('sort_order') + 1;

        $employee->components()->create($data);

        return back()->with('success', 'Komponen ditambahkan.');
    }

    public function updateComponent(Request $request, Employee $employee, EmployeeComponent $component)
    {
        abort_unless($component->employee_id === $employee->id, 404);

        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'amount'    => 'required|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        $component->update($data);

        return back()->with('success', 'Komponen diperbarui.');
    }
}
