<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tour;
use App\Http\Controllers\TourEmailController;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        if (! array_key_exists($type, Tour::TYPES)) {
            $type = null;
        }

        $query = Tour::with('customer')
            ->withSum('items as total_sell', 'line_sell')
            ->withSum('items as total_cost', 'line_cost')
            ->withCount('invoices')
            ->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tours = $query->paginate(25)->withQueryString();

        return Inertia::render('Tours/Index', [
            'tours'   => $tours,
            'filters' => $request->only('status'),
            'type'    => $type,
            'types'   => Tour::TYPES,
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        if (! array_key_exists($type, Tour::TYPES)) {
            $type = 'tour';
        }

        return Inertia::render('Tours/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'country']),
            'packages'  => TourPackage::where('is_active', true)
                ->orderBy('type')
                ->orderBy('title')
                ->get(['id', 'type', 'title', 'duration_days', 'duration_nights']),
            'type'  => $type,
            'types' => Tour::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'           => 'required|string|in:' . implode(',', array_keys(Tour::TYPES)),
            'inquiry_source' => 'nullable|in:website,external',
            'package_id'     => 'nullable|exists:tour_packages,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'title'          => 'nullable|string|max:255',
            'pax'            => 'required|integer|min:1',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'required|string|in:inquiry,quotation_draft,quotation_sent,follow_up,negotiation,confirmed,cancelled',
            'sales_person'   => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
            'details'        => 'nullable|array',
        ]);

        $tour = Tour::create($data);

        return redirect()->route('tours.edit', $tour)
            ->with('success', $tour->type_label . ' ' . $tour->code . ' berhasil dibuat.');
    }

    public function edit(Tour $tour)
    {
        $tour->load(['customer', 'items.product', 'assignments', 'itineraryDays', 'itineraryHours', 'histories', 'invoices']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin', 'itinerary_pdf_url']);

        return Inertia::render('Tours/Edit', [
            'tour'        => $tour,
            'customers'   => Customer::orderBy('name')->get(['id', 'name']),
            'products'    => Product::where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'unit', 'cost', 'sell', 'currency']),
            'manifestUrl'    => URL::signedRoute('manifest', ['tour' => $tour->id]),
            'fieldUsers'     => User::whereIn('role', ['guide', 'driver', 'tour_leader'])
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'emailTemplates' => (new TourEmailController)->templates($tour),
            'quotationDefaults' => [
                'included'     => config('quotation.included'),
                'excluded'     => config('quotation.excluded'),
                'child_policy' => config('quotation.child_policy'),
                'terms'        => config('quotation.terms'),
            ],
        ]);
    }

    public function update(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'type'           => 'nullable|string|in:' . implode(',', array_keys(Tour::TYPES)),
            'customer_id'    => 'nullable|exists:customers,id',
            'title'          => 'nullable|string|max:255',
            'pax'            => 'required|integer|min:1',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'required|string|in:inquiry,quotation_draft,quotation_sent,follow_up,negotiation,confirmed,cancelled',
            'sales_person'   => 'nullable|string|max:255',
            'default_markup' => 'nullable|numeric|min:0|max:100',
            'notes'          => 'nullable|string',
            'details'        => 'nullable|array',
            // Quotation (customer-facing)
            'pricing'        => 'nullable|array',
            'included'       => 'nullable|string',
            'excluded'       => 'nullable|string',
            'child_policy'   => 'nullable|string',
            'terms'          => 'nullable|string',
            'price_validity' => 'nullable|date',
        ]);

        $statusChanged = isset($data['status']) && $data['status'] !== $tour->status;
        $oldStatus     = $tour->status;

        $tour->update($data);

        if ($statusChanged) {
            $labels = [
                'inquiry'         => 'Inquiry',
                'quotation_draft' => 'Draft Quotation',
                'quotation_sent'  => 'Sent',
                'follow_up'       => 'Follow Up',
                'negotiation'     => 'Negosiasi',
                'confirmed'       => 'Confirmed',
                'cancelled'       => 'Cancelled',
            ];
            $tour->histories()->create([
                'type'            => $data['status'],
                'status_snapshot' => $data['status'],
                'description'     => 'Status diubah dari "' . ($labels[$oldStatus] ?? $oldStatus) . '" menjadi "' . ($labels[$data['status']] ?? $data['status']) . '".',
                'created_by'      => auth()->user()->name,
            ]);

            // Auto-buat invoice DRAFT saat tour dikonfirmasi (anti-duplikat).
            // Nominal = total jual (snapshot item); akuntan finalisasi & kirim.
            if ($data['status'] === 'confirmed' && $tour->invoices()->count() === 0) {
                $this->createDraftInvoice($tour);
            }

            // Auto-buat tugas booking per supplier saat confirmed (anti-duplikat).
            // Operation eksekusi di menu Booking → jadi tagihan AP di Keuangan.
            if ($data['status'] === 'confirmed' && $tour->bookings()->count() === 0) {
                $this->generateBookings($tour);
            }
        }

        return redirect()->back()->with('success', 'Tour berhasil diperbarui.');
    }

    public function destroy(Tour $tour)
    {
        $tour->delete();

        return redirect()->route('tours.index')
            ->with('success', 'Tour berhasil dihapus.');
    }

    /**
     * Buat invoice DRAFT otomatis untuk tour yang baru dikonfirmasi.
     * Nominal diambil dari total jual (snapshot item); akuntan finalisasi.
     */
    private function createDraftInvoice(Tour $tour): void
    {
        $tour->invoices()->create([
            'date'     => now()->toDateString(),
            'due_date' => $tour->start_date?->toDateString() ?? now()->addDays(7)->toDateString(),
            'total'    => $tour->total_sell,
            'status'   => 'draft',
            'notes'    => 'Dibuat otomatis saat tour dikonfirmasi.',
        ]);

        $tour->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $tour->status,
            'description'     => 'Invoice draft otomatis dibuat (IDR ' . number_format($tour->total_sell, 0, ',', '.') . '). Silakan finalisasi di menu Keuangan.',
            'created_by'      => auth()->user()->name,
        ]);
    }

    /**
     * Buat tugas booking per supplier untuk tour yang baru dikonfirmasi.
     * Item tour dikelompokkan per supplier → 1 booking per supplier (status pending).
     * Operation lalu eksekusi di menu Booking.
     */
    private function generateBookings(Tour $tour): void
    {
        $tour->loadMissing('items.product');

        // Kelompokkan item per supplier (lewat product.supplier_id). 0 = tanpa supplier.
        $groups = $tour->items->groupBy(fn ($item) => $item->product?->supplier_id ?? 0);

        $allowed = ['hotel', 'transport', 'guide', 'restaurant', 'attraction', 'other'];

        foreach ($groups as $supplierId => $items) {
            $supplier = $supplierId ? Supplier::find($supplierId) : null;

            // Kategori = tipe produk paling dominan dalam grup.
            $category = $items->groupBy('product_type')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first();
            $category = in_array($category, $allowed, true) ? $category : 'other';

            // Rincian item → notes (biar operation tahu apa yg di-booking).
            $detail = $items->map(function ($i) {
                $qty   = $i->qty > 1 ? $i->qty . '× ' : '';
                $night = $i->nights > 1 ? ' (' . $i->nights . ' malam)' : '';
                return '• ' . $qty . ($i->description ?: $i->product?->name ?: 'Item') . $night;
            })->implode("\n");

            $tour->bookings()->create([
                'supplier_id' => $supplier?->id,
                'description' => $supplier?->name ?: 'Tanpa supplier (cek manual)',
                'category'    => $category,
                'est_cost'    => (float) $items->sum('line_cost'),
                'status'      => 'pending',
                'notes'       => $detail ?: null,
            ]);
        }

        $count = $tour->bookings()->count();
        if ($count > 0) {
            $tour->histories()->create([
                'type'            => 'note',
                'status_snapshot' => $tour->status,
                'description'     => $count . ' tugas booking supplier dibuat otomatis. Operation eksekusi di menu Booking.',
                'created_by'      => auth()->user()?->name ?? 'Sistem',
            ]);
        }
    }
}
