<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount('products')->with('user:id,name,email,supplier_id')->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $suppliers = $query->paginate(20)->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters'   => $request->only('search', 'type'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|in:hotel,transport,guide,restaurant,attraction,other',
            'is_travel_agent' => 'boolean',
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'notes'           => 'nullable|string',
            // akun travel agent (wajib jika is_travel_agent)
            'account_email'    => 'nullable|required_if:is_travel_agent,true|email|unique:users,email',
            'account_password' => 'nullable|required_if:is_travel_agent,true|string|min:8',
        ]);

        $supplier = Supplier::create([
            'name'            => $data['name'],
            'type'            => $data['type'] ?? null,
            'is_travel_agent' => $data['is_travel_agent'] ?? false,
            'contact_person'  => $data['contact_person'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        if (! empty($data['is_travel_agent'])) {
            User::create([
                'name'        => $data['name'],
                'email'       => $data['account_email'],
                'password'    => Hash::make($data['account_password']),
                'role'        => 'travel_agent',
                'supplier_id' => $supplier->id,
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('user:id,name,email,supplier_id');

        return Inertia::render('Suppliers/Form', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->load('user');
        $existingUserId = $supplier->user?->id;

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'type'            => 'nullable|string|in:hotel,transport,guide,restaurant,attraction,other',
            'is_travel_agent' => 'boolean',
            'contact_person'  => 'nullable|string|max:255',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'notes'           => 'nullable|string',
            'account_email'    => [
                'nullable', 'required_if:is_travel_agent,true', 'email',
                Rule::unique('users', 'email')->ignore($existingUserId),
            ],
            // password opsional saat edit (kosong = tidak diubah)
            'account_password' => 'nullable|string|min:8',
        ]);

        $supplier->update([
            'name'            => $data['name'],
            'type'            => $data['type'] ?? null,
            'is_travel_agent' => $data['is_travel_agent'] ?? false,
            'contact_person'  => $data['contact_person'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'] ?? null,
            'notes'           => $data['notes'] ?? null,
        ]);

        if (! empty($data['is_travel_agent'])) {
            // buat atau perbarui akun travel agent
            $user = $supplier->user ?? new User(['role' => 'travel_agent', 'supplier_id' => $supplier->id]);
            $user->name        = $data['name'];
            $user->email       = $data['account_email'];
            $user->role        = 'travel_agent';
            $user->supplier_id = $supplier->id;
            if (! empty($data['account_password'])) {
                $user->password = Hash::make($data['account_password']);
            }
            $user->save();
        } elseif ($supplier->user) {
            // dimatikan sebagai travel agent → hapus akun loginnya
            $supplier->user->delete();
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        // hapus akun travel agent terkait (jika ada)
        $supplier->user?->delete();
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier berhasil dihapus.');
    }
}
