<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Tour;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'role'        => 'required|string|in:guide,driver,tour_leader',
            'user_id'     => 'nullable|exists:users,id',
            'person_name' => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'vehicle'     => 'nullable|string|max:255',
            'pickup_time' => 'nullable|date_format:H:i',
            'notes'       => 'nullable|string',
        ]);

        $tour->assignments()->create($data);

        return redirect()->back();
    }

    public function update(Request $request, Assignment $assignment)
    {
        $data = $request->validate([
            'role'        => 'required|string|in:guide,driver,tour_leader',
            'user_id'     => 'nullable|exists:users,id',
            'person_name' => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'vehicle'     => 'nullable|string|max:255',
            'pickup_time' => 'nullable|date_format:H:i',
            'notes'       => 'nullable|string',
        ]);

        $assignment->update($data);

        return redirect()->back();
    }

    public function destroy(Assignment $assignment)
    {
        $assignment->delete();

        return redirect()->back();
    }
}
