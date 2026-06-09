<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
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
        $query = Tour::with('customer')
            ->withSum('items as total_sell', 'line_sell')
            ->withSum('items as total_cost', 'line_cost')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tours = $query->paginate(25)->withQueryString();

        return Inertia::render('Tours/Index', [
            'tours'   => $tours,
            'filters' => $request->only('status'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Tours/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'country']),
            'packages'  => TourPackage::where('is_active', true)
                ->orderBy('type')
                ->orderBy('title')
                ->get(['id', 'type', 'title', 'duration_days', 'duration_nights']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
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
        ]);

        $tour = Tour::create($data);

        return redirect()->route('tours.edit', $tour)
            ->with('success', 'Tour ' . $tour->code . ' berhasil dibuat.');
    }

    public function edit(Tour $tour)
    {
        $tour->load(['customer', 'items.product', 'assignments', 'itineraryDays', 'itineraryHours', 'histories']);
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
        ]);
    }

    public function update(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'customer_id'    => 'nullable|exists:customers,id',
            'title'          => 'nullable|string|max:255',
            'pax'            => 'required|integer|min:1',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'required|string|in:inquiry,quotation_draft,quotation_sent,follow_up,negotiation,confirmed,cancelled',
            'sales_person'   => 'nullable|string|max:255',
            'default_markup' => 'nullable|numeric|min:0|max:100',
            'notes'          => 'nullable|string',
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
        }

        return redirect()->back()->with('success', 'Tour berhasil diperbarui.');
    }

    public function destroy(Tour $tour)
    {
        $tour->delete();

        return redirect()->route('tours.index')
            ->with('success', 'Tour berhasil dihapus.');
    }
}
