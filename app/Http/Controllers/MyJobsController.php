<?php

namespace App\Http\Controllers;

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
            ->get()
            ->each->maskCustomerForField();

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
        $tour->maskCustomerForField();

        return Inertia::render('MyJobs/Show', [
            'tour'     => $tour,
            'schedule' => $tour->fieldSchedule(),
        ]);
    }
}
