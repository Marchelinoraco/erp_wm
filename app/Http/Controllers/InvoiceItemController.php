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
        ]);

        $product = Product::findOrFail($request->product_id);

        $item = InvoiceItem::fromProduct($product, [
            'invoice_id' => $invoice->id,
            'qty'        => $request->input('qty', 1),
            'nights'     => $request->input('nights', 1),
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
