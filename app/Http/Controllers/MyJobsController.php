<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\Tour;
use Inertia\Inertia;

class MyJobsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $tours = Tour::whereHas('assignments', fn ($q) => $q->where('user_id', $user->id))
            ->with([
                'customer:id,name,country',
                'assignments' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderBy('start_date')
            ->get();

        return Inertia::render('MyJobs/Index', [
            'tours' => $tours,
        ]);
    }

    public function show(Tour $tour)
    {
        $user = auth()->user();

        abort_unless(
            $tour->assignments()->where('user_id', $user->id)->exists(),
            403
        );

        $tour->load(['customer', 'assignments', 'items', 'itineraryDays', 'itineraryHours']);

        // Jadwal layanan berjadwal (hotel/transport/guide) dari item invoice —
        // hanya field aman untuk tim lapangan, TANPA harga/cost/profit.
        $schedule = $tour->invoices()->with('items')->get()
            ->flatMap(fn ($inv) => $inv->items)
            ->filter(fn ($i) => in_array($i->product_type, InvoiceItem::DATED_TYPES, true) && $i->start_date)
            ->unique(fn ($i) => $i->product_type . '|' . $i->description . '|' . $i->start_date . '|' . $i->end_date)
            ->sortBy('start_date')
            ->map(fn ($i) => [
                'id'           => $i->id,
                'product_type' => $i->product_type,
                'description'  => $i->description,
                'qty'          => $i->qty,
                'nights'       => $i->nights,
                'start_date'   => $i->start_date?->format('Y-m-d'),
                'end_date'     => $i->end_date?->format('Y-m-d'),
            ])
            ->values();

        return Inertia::render('MyJobs/Show', [
            'tour'     => $tour,
            'schedule' => $schedule,
        ]);
    }
}
