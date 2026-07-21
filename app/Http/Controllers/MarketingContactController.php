<?php

namespace App\Http\Controllers;

use App\Contracts\BrevoGateway;
use App\Models\Customer;
use App\Services\Brevo\BrevoException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarketingContactController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private BrevoGateway $brevo) {}

    public function index(Request $request)
    {
        $page  = max(1, (int) $request->query('page', 1));
        $email = trim((string) $request->query('email', ''));

        $contacts = [];
        $count    = 0;
        $lists    = [];
        $error    = null;

        try {
            if ($email !== '') {
                // Brevo tidak punya pencarian teks — hanya lookup email persis.
                $found    = $this->brevo->findContact($email);
                $contacts = $found ? [$found] : [];
                $count    = count($contacts);
            } else {
                $result   = $this->brevo->contacts(self::PER_PAGE, ($page - 1) * self::PER_PAGE);
                $contacts = $result['contacts'];
                $count    = $result['count'];
            }

            $lists = $this->brevo->lists();
        } catch (BrevoException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Marketing/Contacts/Index', [
            'contacts' => $contacts,
            'count'    => $count,
            'page'     => $page,
            'perPage'  => self::PER_PAGE,
            'search'   => $email,
            'lists'    => $lists,
            'error'    => $error,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email'      => 'required|email',
            'name'       => 'nullable|string|max:255',
            'list_ids'   => 'array',
            'list_ids.*' => 'integer',
        ]);

        // Hanya atribut bawaan Brevo yang pasti ada di tiap akun.
        $attributes = filled($data['name'] ?? null) ? ['FIRSTNAME' => $data['name']] : [];

        try {
            $this->brevo->createContact($data['email'], $attributes, $data['list_ids'] ?? []);
        } catch (BrevoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kontak ' . $data['email'] . ' tersimpan di Brevo.');
    }

    /**
     * Daftar list Brevo untuk picker. Endpoint JSON terpisah supaya halaman
     * Customers tidak ikut menunggu Brevo saat dimuat — hanya dipanggil saat
     * dialog "Dorong ke Brevo" dibuka.
     */
    public function lists()
    {
        try {
            return response()->json(['lists' => $this->brevo->lists(), 'error' => null]);
        } catch (BrevoException $e) {
            return response()->json(['lists' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Dorong satu customer ERP ke audiens Brevo.
     * Customer yang sudah terdaftar bukan error — lihat updateEnabled di BrevoClient.
     */
    public function push(Request $request, Customer $customer)
    {
        $request->validate([
            'list_ids'   => 'array',
            'list_ids.*' => 'integer',
        ]);

        // customers.email nullable — jangan hanya andalkan tombol yang dinonaktifkan di UI.
        if (blank($customer->email)) {
            return back()->withErrors([
                'email' => 'Customer ini belum punya email, tidak bisa didorong ke Brevo.',
            ]);
        }

        try {
            $this->brevo->createContact(
                $customer->email,
                filled($customer->name) ? ['FIRSTNAME' => $customer->name] : [],
                $request->input('list_ids', []),
            );
        } catch (BrevoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $customer->name . ' ditambahkan ke Brevo.');
    }
}
