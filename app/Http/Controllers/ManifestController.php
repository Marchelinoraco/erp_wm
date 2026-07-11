<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Inertia\Inertia;

class ManifestController extends Controller
{
    public function show(Tour $tour)
    {
        $tour->load(['customer', 'items' => function ($q) {
            $q->orderBy('day_number')->orderBy('sort_order');
        }, 'assignments', 'itineraryDays', 'itineraryHours']);
        $tour->maskCustomerForField();

        return Inertia::render('Manifest', [
            'tour'     => $tour,
            'schedule' => $tour->fieldSchedule(),
        ]);
    }
}
