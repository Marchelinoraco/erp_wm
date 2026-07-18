<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\MiceTemplate;
use App\Models\Product;
use App\Models\QuotationItem;
use App\Models\Reminder;
use App\Models\Supplier;
use App\Models\Tour;
use App\Http\Controllers\TourEmailController;
use App\Models\TourItem;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TourController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        if (! array_key_exists($type, Tour::TYPES)) {
            $type = null;
        }

        $query = Tour::visibleTo($request->user())
            ->with('customer')
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

        // Cari bebas: kode, judul, atau nama customer
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('title', 'like', "%{$q}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%")));
        }

        // Rentang tanggal keberangkatan
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('start_date', '<=', $request->date_to);
        }

        if ($request->filled('sales')) {
            $query->where('sales_person', $request->sales);
        }

        $tours = $query->paginate(25)->withQueryString();

        return Inertia::render('Tours/Index', [
            'tours'       => $tours,
            'filters'     => $request->only('status', 'q', 'date_from', 'date_to', 'sales'),
            'type'        => $type,
            'types'       => Tour::TYPES,
            'salesPeople' => Tour::whereNotNull('sales_person')->where('sales_person', '!=', '')
                ->distinct()->orderBy('sales_person')->pluck('sales_person'),
        ]);
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        if (! array_key_exists($type, Tour::TYPES)) {
            $type = 'tour';
        }

        return Inertia::render('Tours/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'country', 'type']),
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
            'tour_direction' => 'nullable|in:inbound,outbound',
            'inquiry_source' => 'nullable|in:website,external',
            'package_id'     => 'nullable|exists:tour_packages,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'title'          => 'nullable|string|max:255',
            // Wajib bila customer bertipe buyer — nama inilah yang dilihat tim lapangan
            'guest_name'     => [
                Rule::requiredIf(fn () => Customer::find($request->customer_id)?->type === 'buyer'),
                'nullable', 'string', 'max:255',
            ],
            'guest_phone'    => 'nullable|string|max:50',
            'pax'            => 'required|integer|min:1',
            'budget'         => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'required|string|in:inquiry,quotation_draft,quotation_sent,follow_up,negotiation,confirmed,cancelled',
            'sales_person'   => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
            'details'        => 'nullable|array',
        ]);

        $data['created_by'] = $request->user()->id;
        if (empty($data['sales_person'])) {
            $data['sales_person'] = $request->user()->name;
        }

        $tour = Tour::create($data);

        $this->createAutoReminder($tour, $request->user()->id, 'Dibuat otomatis saat inquiry dibuat.');

        return redirect()->route('tours.edit', $tour)
            ->with('success', $tour->type_label . ' ' . $tour->code . ' berhasil dibuat.');
    }

    public function edit(Tour $tour)
    {
        abort_unless($tour->isAccessibleBy(auth()->user()), 403);

        $tour->load(['customer', 'items.product', 'quotationItems.product', 'assignments', 'itineraryDays', 'itineraryHours', 'histories', 'invoices.items.product', 'invoices.payments.cashAccount:id,name', 'costRequests.requestedBy:id,name', 'costRequests.invoice:id,number,finance_number']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin', 'itinerary_pdf_url']);

        return Inertia::render('Tours/Edit', [
            'tour'        => $tour,
            'customers'    => Customer::orderBy('name')->get(['id', 'name', 'type']),
            'suppliers'    => Supplier::orderBy('name')->get(['id', 'name']),
            'bankAccounts' => BankAccount::active()->get(['id', 'bank', 'account_number']),
            'cashAccounts' => CashAccount::active()->get(['id', 'name', 'type']),
            'products'    => Product::where('is_active', true)
                ->orderBy('type')
                ->orderBy('group_label')
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'unit', 'cost', 'sell', 'currency', 'group_label', 'grade']),
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
            'miceTemplates' => $tour->type === 'mice'
                ? MiceTemplate::orderBy('name')->get(['id', 'name', 'description', 'items'])
                : [],
        ]);
    }

    public function update(Request $request, Tour $tour)
    {
        abort_unless($tour->isAccessibleBy($request->user()), 403);

        $data = $request->validate([
            'type'           => 'nullable|string|in:' . implode(',', array_keys(Tour::TYPES)),
            'tour_direction' => 'nullable|in:inbound,outbound',
            'customer_id'    => 'nullable|exists:customers,id',
            'title'          => 'nullable|string|max:255',
            'guest_name'     => [
                Rule::requiredIf(fn () => $request->has('customer_id')
                    && Customer::find($request->customer_id)?->type === 'buyer'),
                'nullable', 'string', 'max:255',
            ],
            'guest_phone'    => 'nullable|string|max:50',
            'pax'            => 'sometimes|required|integer|min:1',
            'budget'         => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'sometimes|required|string|in:inquiry,quotation_draft,quotation_sent,follow_up,negotiation,confirmed,cancelled',
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

            $this->handleStatusChangeReminder($tour, $data['status']);

            // Konversi quotation items yang disetujui → tour items saat dikonfirmasi.
            if ($data['status'] === 'confirmed') {
                $this->convertApprovedQuotationItems($tour);
            }

            // Invoice TIDAK lagi dibuat otomatis — dibuat & disetujui sales (alur 2 tahap)
            // di panel Invoice halaman Tour, lalu masuk Keuangan setelah disetujui.

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
        abort_unless($tour->isAccessibleBy(auth()->user()), 403);

        $tour->delete();

        return redirect()->route('tours.index')
            ->with('success', 'Tour berhasil dihapus.');
    }

    /** Reminder follow-up otomatis H+1, dibuat sekali saat inquiry baru dibuat. */
    private function createAutoReminder(Tour $tour, int $userId, string $notes): void
    {
        Reminder::create([
            'user_id'   => $userId,
            'tour_id'   => $tour->id,
            'title'     => 'Follow up ' . $tour->type_label . ' ' . $tour->code,
            'notes'     => $notes,
            'remind_at' => now()->addDay(),
        ]);
    }

    /**
     * Reminder follow-up berantai: tiap kali status berubah (kecuali ke status akhir),
     * reminder otomatis lama ditandai selesai dan reminder baru H+1 dibuat untuk
     * pemilik tour. Status akhir (confirmed/cancelled) hanya menutup reminder lama.
     */
    private function handleStatusChangeReminder(Tour $tour, string $newStatus): void
    {
        $tour->reminders()
            ->where('is_done', false)
            ->where('notes', 'like', 'Dibuat otomatis%')
            ->update(['is_done' => true]);

        if (in_array($newStatus, ['confirmed', 'cancelled'], true)) {
            return;
        }

        $labels = [
            'inquiry'         => 'Inquiry',
            'quotation_draft' => 'Draft Quotation',
            'quotation_sent'  => 'Sent',
            'follow_up'       => 'Follow Up',
            'negotiation'     => 'Negosiasi',
        ];

        Reminder::create([
            'user_id'   => $tour->created_by ?? auth()->id(),
            'tour_id'   => $tour->id,
            'title'     => 'Follow up ' . $tour->code . ' — status: ' . ($labels[$newStatus] ?? $newStatus),
            'notes'     => 'Dibuat otomatis saat status berubah.',
            'remind_at' => now()->addDay(),
        ]);
    }

    /**
     * Konversi quotation items yang disetujui customer menjadi tour items.
     * Hanya berjalan satu kali saat status berubah ke confirmed.
     */
    private function convertApprovedQuotationItems(Tour $tour): void
    {
        $approved = $tour->quotationItems()
            ->where('status', 'approved')
            ->with('product')
            ->get();

        if ($approved->isEmpty()) {
            return;
        }

        $maxOrder = $tour->items()->max('sort_order') ?? 0;

        foreach ($approved as $qi) {
            if ($qi->product) {
                $item = TourItem::fromProduct($qi->product, [
                    'tour_id'    => $tour->id,
                    'description' => $qi->label,
                    'qty'        => $qi->qty,
                    'nights'     => $qi->nights,
                    'unit_sell'  => $qi->unit_sell,
                    'sort_order' => ++$maxOrder,
                ]);
            } else {
                $item = new TourItem([
                    'tour_id'     => $tour->id,
                    'product_id'  => null,
                    'description' => $qi->label,
                    'qty'         => $qi->qty,
                    'nights'      => $qi->nights,
                    'unit_cost'   => 0,
                    'unit_sell'   => $qi->unit_sell,
                    'currency'    => 'IDR',
                    'sort_order'  => ++$maxOrder,
                ]);
            }
            $item->save();
        }

        $tour->histories()->create([
            'type'            => 'note',
            'status_snapshot' => 'confirmed',
            'description'     => $approved->count() . ' item quotation yang disetujui customer berhasil ditambahkan ke Item Produk tour.',
            'created_by'      => auth()->user()?->name ?? 'Sistem',
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
