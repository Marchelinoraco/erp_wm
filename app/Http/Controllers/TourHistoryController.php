<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourHistory;
use Illuminate\Http\Request;

class TourHistoryController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'type'        => 'required|string|in:revision,note,call,meeting,email,confirmed,cancelled',
            'description' => 'required|string|max:1000',
        ]);

        $tour->histories()->create([
            'type'            => $data['type'],
            'description'     => $data['description'],
            'status_snapshot' => $tour->status,
            'created_by'      => auth()->user()->name,
        ]);

        return redirect()->back()->with('success', 'Riwayat berhasil ditambahkan.');
    }

    public function destroy(Tour $tour, TourHistory $history)
    {
        abort_if($history->tour_id !== $tour->id, 403);
        $history->delete();

        return redirect()->back()->with('success', 'Riwayat berhasil dihapus.');
    }
}
