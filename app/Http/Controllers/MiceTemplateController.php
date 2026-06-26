<?php

namespace App\Http\Controllers;

use App\Models\MiceTemplate;
use App\Models\Tour;
use Illuminate\Http\Request;

class MiceTemplateController extends Controller
{
    /** Simpan template baru (manual dari form). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.label'    => 'required|string|max:255',
            'items.*.pax_mode' => 'required|in:shared,per_pax',
            'items.*.unit_sell'=> 'required|numeric|min:0',
            'items.*.qty'      => 'integer|min:1',
            'items.*.nights'   => 'integer|min:1',
            'items.*.notes'    => 'nullable|string',
        ]);

        MiceTemplate::create($data);

        return redirect()->back()->with('success', 'Template "' . $data['name'] . '" berhasil disimpan.');
    }

    /** Simpan template dari quotation_items tour yang sedang aktif. */
    public function saveFromTour(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $items = $tour->quotationItems()
            ->where('status', '!=', 'rejected')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($qi) => [
                'label'    => $qi->label,
                'pax_mode' => $qi->pax_mode ?? 'per_pax',
                'unit_sell'=> (float) $qi->unit_sell,
                'qty'      => $qi->qty,
                'nights'   => $qi->nights,
                'notes'    => $qi->notes ?? '',
            ])
            ->values()
            ->all();

        abort_if(empty($items), 422, 'Tidak ada item untuk disimpan sebagai template.');

        MiceTemplate::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'items'       => $items,
        ]);

        return redirect()->back()->with('success', 'Template "' . $data['name'] . '" berhasil disimpan dari event ini.');
    }

    /** Terapkan template ke tour — bulk insert quotation_items. */
    public function apply(MiceTemplate $miceTemplate, Tour $tour)
    {
        abort_if($tour->status === 'confirmed', 403, 'Tour sudah dikonfirmasi.');

        $nextOrder = $tour->quotationItems()->max('sort_order') + 1;

        foreach ($miceTemplate->items as $i => $item) {
            $tour->quotationItems()->create([
                'label'     => $item['label'],
                'pax_mode'  => $item['pax_mode'] ?? 'per_pax',
                'unit_sell' => $item['unit_sell'] ?? 0,
                'qty'       => $item['qty'] ?? 1,
                'nights'    => $item['nights'] ?? 1,
                'notes'     => $item['notes'] ?? null,
                'status'    => 'proposed',
                'sort_order'=> $nextOrder + $i,
            ]);
        }

        return redirect()->back()->with('success', count($miceTemplate->items) . ' item dari template "' . $miceTemplate->name . '" berhasil diterapkan.');
    }

    /** Update nama/deskripsi template. */
    public function update(Request $request, MiceTemplate $miceTemplate)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $miceTemplate->update($data);

        return redirect()->back()->with('success', 'Template diperbarui.');
    }

    /** Hapus template. */
    public function destroy(MiceTemplate $miceTemplate)
    {
        $miceTemplate->delete();

        return redirect()->back()->with('success', 'Template dihapus.');
    }
}
