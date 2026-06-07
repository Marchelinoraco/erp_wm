<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Tour;
use App\Models\TourItem;
use Illuminate\Http\Request;

class TourItemController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'day_number' => 'nullable|integer|min:1',
            'qty'        => 'integer|min:1',
            'nights'     => 'integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        $item = TourItem::fromProduct($product, [
            'tour_id'    => $tour->id,
            'day_number' => $request->day_number,
            'qty'        => $request->input('qty', 1),
            'nights'     => $request->input('nights', 1),
            'sort_order' => $tour->items()->max('sort_order') + 1,
        ]);

        $item->save();

        return redirect()->back();
    }

    public function update(Request $request, TourItem $tourItem)
    {
        $data = $request->validate([
            'qty'         => 'sometimes|integer|min:1',
            'nights'      => 'sometimes|integer|min:1',
            'day_number'  => 'sometimes|nullable|integer|min:1',
            'description' => 'sometimes|nullable|string|max:500',
            'unit_cost'   => 'sometimes|numeric|min:0',
            'unit_sell'   => 'sometimes|numeric|min:0',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        $tourItem->update($data);

        return redirect()->back();
    }

    public function destroy(TourItem $tourItem)
    {
        $tourItem->delete();

        return redirect()->back();
    }
}
