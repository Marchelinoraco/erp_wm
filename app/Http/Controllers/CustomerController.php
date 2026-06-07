<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::withCount('tours')->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('contact_person', 'like', '%' . $request->search . '%');
            });
        }

        $customers = $query->paginate(20)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string|in:agent,corporate,direct',
            'country'        => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'notes'          => 'nullable|string',
        ]);

        Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil ditambahkan.');
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Form', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|string|in:agent,corporate,direct',
            'country'        => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'notes'          => 'nullable|string',
        ]);

        $customer->update($data);

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dihapus.');
    }
}
