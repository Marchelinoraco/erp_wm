<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TourItineraryController extends Controller
{
    public function updateDays(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'days'                => 'array',
            'days.*.day_number'   => 'required|integer|min:1',
            'days.*.title'        => 'nullable|string|max:255',
            'days.*.description'  => 'nullable|string',
        ]);

        $tour->itineraryDays()->delete();

        foreach ($data['days'] ?? [] as $day) {
            $tour->itineraryDays()->create([
                'day_number'  => $day['day_number'],
                'title'       => $day['title'] ?? null,
                'description' => $day['description'] ?? null,
            ]);
        }

        return redirect()->back()->with('success', 'Itinerary berhasil disimpan.');
    }

    /** Impor itinerary hasil tombol "Tempel": ganti seluruh hari + aktivitas jam sekaligus. */
    public function import(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'days'                => 'required|array|min:1',
            'days.*.day_number'   => 'required|integer|min:1',
            'days.*.title'        => 'nullable|string|max:255',
            'days.*.description'  => 'nullable|string',
            'hours'               => 'array',
            'hours.*.day_number'  => 'required|integer|min:1',
            'hours.*.start_time'  => 'required|date_format:H:i',
            'hours.*.end_time'    => 'nullable|date_format:H:i',
            'hours.*.activity'    => 'required|string|max:255',
            'hours.*.notes'       => 'nullable|string',
        ]);

        $tour->itineraryDays()->delete();
        $tour->itineraryHours()->delete();

        foreach ($data['days'] as $day) {
            $tour->itineraryDays()->create([
                'day_number'  => $day['day_number'],
                'title'       => $day['title'] ?? null,
                'description' => $day['description'] ?? null,
            ]);
        }

        foreach ($data['hours'] ?? [] as $hour) {
            $tour->itineraryHours()->create([
                'day_number' => $hour['day_number'],
                'start_time' => $hour['start_time'],
                'end_time'   => $hour['end_time'] ?? null,
                'activity'   => $hour['activity'],
                'notes'      => $hour['notes'] ?? null,
            ]);
        }

        return redirect()->back()->with('success', 'Itinerary berhasil ditempel.');
    }

    public function uploadPdf(Request $request, Tour $tour)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($tour->itinerary_pdf) {
            Storage::disk('public')->delete($tour->itinerary_pdf);
        }

        $path = $request->file('pdf')->store('itineraries/' . $tour->id, 'public');
        $tour->update(['itinerary_pdf' => $path]);

        return redirect()->back()->with('success', 'PDF itinerary berhasil diupload.');
    }

    public function deletePdf(Tour $tour)
    {
        if ($tour->itinerary_pdf) {
            Storage::disk('public')->delete($tour->itinerary_pdf);
            $tour->update(['itinerary_pdf' => null]);
        }

        return redirect()->back()->with('success', 'PDF itinerary berhasil dihapus.');
    }

    public function downloadPdf(Tour $tour)
    {
        abort_unless(
            $tour->itinerary_pdf && Storage::disk('public')->exists($tour->itinerary_pdf),
            404
        );

        return Storage::disk('public')->download(
            $tour->itinerary_pdf,
            $tour->code . '-itinerary.pdf'
        );
    }

    // ── Hourly Itinerary Management ──
    public function storeHour(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'day_number' => 'required|integer|min:1',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i',
            'activity'   => 'required|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $tour->itineraryHours()->create($data);

        return redirect()->back()->with('success', 'Aktivitas itinerary berhasil ditambahkan.');
    }

    public function updateHour(Request $request, Tour $tour, $hourId)
    {
        $hour = $tour->itineraryHours()->findOrFail($hourId);

        $data = $request->validate([
            'day_number' => 'required|integer|min:1',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i',
            'activity'   => 'required|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $hour->update($data);

        return redirect()->back()->with('success', 'Aktivitas itinerary berhasil diperbarui.');
    }

    public function deleteHour(Tour $tour, $hourId)
    {
        $hour = $tour->itineraryHours()->findOrFail($hourId);
        $hour->delete();

        return redirect()->back()->with('success', 'Aktivitas itinerary berhasil dihapus.');
    }
}
