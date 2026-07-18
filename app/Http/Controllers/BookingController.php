<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Tour;
use App\Models\TourBooking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    /** Daftar booking — semua tour confirmed dikelompokkan, untuk operation/sales/admin. */
    public function index(Request $request)
    {
        $user = $request->user();

        $tours = Tour::where('status', 'confirmed')
            ->when($user->isSales(), fn ($q) => $q->where(
                fn ($w) => $w->where('created_by', $user->id)->orWhereNull('created_by')
            ))
            ->with([
                'customer:id,name',
                'bookings' => fn ($q) => $q->orderByRaw("FIELD(status,'pending','booked','cancelled')")->orderBy('id'),
                'bookings.supplier:id,name,type',
                'bookings.bill:id,status,amount',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'code'       => $t->code,
                'title'      => $t->title,
                'type_label' => $t->type_label,
                'customer'   => $t->customer?->name,
                'pax'        => $t->pax,
                'start_date' => $t->start_date?->toDateString(),
                'end_date'   => $t->end_date?->toDateString(),
                'bookings'   => $t->bookings->map(fn ($b) => [
                    'id'           => $b->id,
                    'supplier_id'  => $b->supplier_id,
                    'supplier'     => $b->supplier?->name,
                    'description'  => $b->description,
                    'category'     => $b->category,
                    'est_cost'     => (float) $b->est_cost,
                    'actual_cost'  => $b->actual_cost !== null ? (float) $b->actual_cost : null,
                    'status'       => $b->status,
                    'booking_ref'  => $b->booking_ref,
                    'booked_at'    => $b->booked_at?->toDateTimeString(),
                    'booked_by'    => $b->booked_by,
                    'bill_id'      => $b->bill_id,
                    'bill_status'  => $b->bill?->status,
                    'notes'        => $b->notes,
                ])->values(),
            ])
            // tampilkan tour yg punya booking dulu, tapi tetap sertakan yg kosong (utk tambah manual)
            ->sortByDesc(fn ($t) => count($t['bookings']))
            ->values();

        $allBookings = TourBooking::query()
            ->when($user->isSales(), fn ($q) => $q->whereHas('tour', fn ($t) => $t->where(
                fn ($w) => $w->where('created_by', $user->id)->orWhereNull('created_by')
            )));

        $stats = [
            'pending'      => (clone $allBookings)->where('status', 'pending')->count(),
            'booked'       => (clone $allBookings)->where('status', 'booked')->count(),
            'cancelled'    => (clone $allBookings)->where('status', 'cancelled')->count(),
            'est_total'    => (float) (clone $allBookings)->whereIn('status', ['pending', 'booked'])->sum('est_cost'),
            'actual_total' => (float) (clone $allBookings)->where('status', 'booked')->sum('actual_cost'),
        ];

        return Inertia::render('Bookings/Index', [
            'tours'      => $tours,
            'suppliers'  => Supplier::orderBy('name')->get(['id', 'name', 'type']),
            'stats'      => $stats,
            'categories' => ['hotel', 'transport', 'guide', 'restaurant', 'attraction', 'agent', 'other'],
            'statuses'   => TourBooking::STATUSES,
        ]);
    }

    /** Tambah booking manual ke sebuah tour (mis. tour tanpa item / supplier tambahan). */
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'description' => 'required|string|max:255',
            'category'    => 'required|in:hotel,transport,guide,restaurant,attraction,agent,other',
            'est_cost'    => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string',
        ]);

        $tour->bookings()->create([
            'supplier_id' => $data['supplier_id'] ?? null,
            'description' => $data['description'],
            'category'    => $data['category'],
            'est_cost'    => $data['est_cost'] ?? 0,
            'notes'       => $data['notes'] ?? null,
            'status'      => 'pending',
        ]);

        return back()->with('success', 'Booking ditambahkan.');
    }

    /**
     * Ubah status booking.
     *  - booked   → catat ref + harga deal, OTOMATIS buat Bill (AP) di Keuangan.
     *  - pending/cancelled → hapus Bill terkait bila belum ada pembayaran.
     */
    public function update(Request $request, TourBooking $booking)
    {
        $data = $request->validate([
            'status'      => 'required|in:pending,booked,cancelled',
            'actual_cost' => 'nullable|numeric|min:0',
            'booking_ref' => 'nullable|string|max:255',
            'notes'       => 'nullable|string',
        ]);

        $booking->booking_ref = $data['booking_ref'] ?? $booking->booking_ref;
        $booking->notes       = $data['notes'] ?? $booking->notes;

        if ($data['status'] === 'booked') {
            $actual = $data['actual_cost'] ?? $booking->actual_cost ?? $booking->est_cost;

            $booking->actual_cost = $actual;
            $booking->status      = 'booked';
            $booking->booked_at   = now();
            $booking->booked_by   = auth()->user()->name;

            $this->syncBill($booking, (float) $actual);
        } else {
            $booking->status = $data['status'];

            if ($data['status'] === 'pending') {
                $booking->booked_at = null;
                $booking->booked_by = null;
            }

            // Lepas Bill bila belum ada pembayaran (jaga Keuangan tetap bersih).
            $this->detachBill($booking);
        }

        $booking->save();

        return back()->with('success', 'Booking diperbarui.');
    }

    public function destroy(TourBooking $booking)
    {
        $this->detachBill($booking);
        $booking->delete();

        return back()->with('success', 'Booking dihapus.');
    }

    /** Buat/perbarui Bill (AP) terkait booking. */
    private function syncBill(TourBooking $booking, float $amount): void
    {
        $tour = $booking->tour;

        if ($booking->bill_id && $booking->bill) {
            // Perbarui nominal hanya bila Bill belum dibayar sebagian/penuh.
            if ($booking->bill->payments()->count() === 0) {
                $booking->bill->update([
                    'amount'      => $amount,
                    'supplier_id' => $booking->supplier_id,
                    'category'    => $booking->category,
                ]);
            }
            return;
        }

        $bill = $tour->bills()->create([
            'supplier_id' => $booking->supplier_id,
            'description' => $booking->description . ' — ' . $tour->code,
            'category'    => $booking->category,
            'date'        => now()->toDateString(),
            'due_date'    => $tour->start_date?->toDateString() ?? now()->addDays(7)->toDateString(),
            'amount'      => $amount,
            'status'      => 'unpaid',
            'notes'       => 'Auto dari booking operasional.' . ($booking->booking_ref ? ' Ref: ' . $booking->booking_ref : ''),
        ]);

        $booking->bill_id = $bill->id;
    }

    /** Lepas & hapus Bill terkait bila belum ada pembayaran. */
    private function detachBill(TourBooking $booking): void
    {
        if ($booking->bill_id && $booking->bill && $booking->bill->payments()->count() === 0) {
            $booking->bill->delete();
            $booking->bill_id = null;
        }
    }
}
