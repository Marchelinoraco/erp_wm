<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceItemController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $this->ensureEditable($invoice);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty'        => 'integer|min:1',
            'nights'     => 'integer|min:1',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Hotel/transport/guide wajib punya jadwal — tampil ke tim lapangan di MyJobs
        if (in_array($product->type, InvoiceItem::DATED_TYPES, true)) {
            $request->validate([
                'start_date' => 'required|date',
                'end_date'   => 'required|date|after_or_equal:start_date',
            ]);
        }

        $item = InvoiceItem::fromProduct($product, [
            'invoice_id' => $invoice->id,
            'qty'        => $request->input('qty', 1),
            'nights'     => $request->input('nights', 1),
            'start_date' => $request->input('start_date'),
            'end_date'   => $request->input('end_date'),
            'sort_order' => (int) $invoice->items()->max('sort_order') + 1,
        ]);

        $item->save();

        return redirect()->back();
    }

    /** Tempel massal dari clipboard (Excel/Sheets) — item manual tanpa product_id. */
    public function bulkStore(Request $request, Invoice $invoice)
    {
        $this->ensureEditable($invoice);

        $data = $request->validate([
            'items'                => 'required|array|min:1|max:200',
            'items.*.description'  => 'required|string|max:500',
            'items.*.product_type' => 'nullable|string|max:50',
            'items.*.qty'          => 'nullable|integer|min:1',
            'items.*.nights'       => 'nullable|integer|min:1',
            'items.*.unit_cost'    => 'nullable|numeric|min:0',
            'items.*.unit_sell'    => 'nullable|numeric|min:0',
        ]);

        $sort = (int) $invoice->items()->max('sort_order');

        foreach ($data['items'] as $row) {
            $invoice->items()->create([
                'product_type' => $row['product_type'] ?? null,
                'description'  => $row['description'],
                'qty'          => $row['qty'] ?? 1,
                'nights'       => $row['nights'] ?? 1,
                'unit_cost'    => $row['unit_cost'] ?? 0,
                'unit_sell'    => $row['unit_sell'] ?? 0,
                'sort_order'   => ++$sort,
            ]);
        }

        return redirect()->back();
    }

    /** Autosave massal dari tabel Rincian Profit — satu request untuk semua baris yang berubah. */
    public function bulkUpdate(Request $request, Invoice $invoice)
    {
        $this->ensureEditable($invoice);

        $data = $request->validate([
            'items'               => 'required|array|min:1|max:200',
            'items.*.id'          => 'required|integer',
            'items.*.qty'         => 'sometimes|integer|min:1',
            'items.*.nights'      => 'sometimes|integer|min:1',
            'items.*.description' => 'sometimes|nullable|string|max:500',
            'items.*.unit_cost'   => 'sometimes|numeric|min:0',
            'items.*.unit_sell'   => 'sometimes|numeric|min:0',
            'items.*.start_date'  => 'sometimes|nullable|date',
            'items.*.end_date'    => 'sometimes|nullable|date|after_or_equal:items.*.start_date',
        ]);

        $items = $invoice->items()
            ->whereIn('id', collect($data['items'])->pluck('id'))
            ->get()
            ->keyBy('id');

        foreach ($data['items'] as $row) {
            $items->get($row['id'])?->update(collect($row)->except('id')->all());
        }

        return redirect()->back();
    }

    public function update(Request $request, InvoiceItem $invoiceItem)
    {
        $this->ensureEditable($invoiceItem->invoice);

        $data = $request->validate([
            'qty'         => 'sometimes|integer|min:1',
            'nights'      => 'sometimes|integer|min:1',
            'description' => 'sometimes|nullable|string|max:500',
            'unit_cost'   => 'sometimes|numeric|min:0',
            'unit_sell'   => 'sometimes|numeric|min:0',
            'sort_order'  => 'sometimes|integer|min:0',
            'start_date'  => 'sometimes|nullable|date',
            'end_date'    => 'sometimes|nullable|date|after_or_equal:start_date',
        ]);

        $invoiceItem->update($data);

        return redirect()->back();
    }

    public function destroy(InvoiceItem $invoiceItem)
    {
        $this->ensureEditable($invoiceItem->invoice);

        $invoiceItem->delete();

        return redirect()->back();
    }

    /** Invoice yang sudah disetujui (masuk Keuangan) terkunci — tidak boleh diubah. */
    private function ensureEditable(Invoice $invoice): void
    {
        if ($invoice->is_approved) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice sudah disetujui dan masuk Keuangan, tidak bisa diubah. Buat invoice tambahan bila ada perubahan.',
            ]);
        }
    }
}
