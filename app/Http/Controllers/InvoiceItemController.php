<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceItemController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
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

        $this->catatRiwayatPascaApprove(
            $invoice,
            "Item Rincian Profit ditambahkan pasca-approve oleh {$this->namaPengguna()}: {$item->description}."
        );

        return redirect()->back();
    }

    /** Tempel massal dari clipboard (Excel/Sheets) — item manual tanpa product_id. */
    public function bulkStore(Request $request, Invoice $invoice)
    {
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

        $jumlah = count($data['items']);
        $deskripsi = collect($data['items'])->pluck('description')->join(', ');
        $this->catatRiwayatPascaApprove(
            $invoice,
            "{$jumlah} item Rincian Profit ditambahkan pasca-approve oleh {$this->namaPengguna()}: {$deskripsi}."
        );

        return redirect()->back();
    }

    /** Autosave massal dari tabel Rincian Profit — satu request untuk semua baris yang berubah. */
    public function bulkUpdate(Request $request, Invoice $invoice)
    {
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
            $item = $items->get($row['id']);
            if (! $item) {
                continue;
            }

            $perubahan = collect($row)->except('id')->all();
            $before    = $item->only(array_keys($perubahan));
            $item->update($perubahan);

            $ringkasan = $this->ringkasPerubahan($item, $before);
            $this->catatRiwayatPascaApprove(
                $invoice,
                "Item Rincian Profit diubah pasca-approve oleh {$this->namaPengguna()} ({$item->description}): {$ringkasan}."
            );
        }

        return redirect()->back();
    }

    public function update(Request $request, InvoiceItem $invoiceItem)
    {
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

        $before = $invoiceItem->only(array_keys($data));
        $invoiceItem->update($data);

        $ringkasan = $this->ringkasPerubahan($invoiceItem, $before);
        $this->catatRiwayatPascaApprove(
            $invoiceItem->invoice,
            "Item Rincian Profit diubah pasca-approve oleh {$this->namaPengguna()} ({$invoiceItem->description}): {$ringkasan}."
        );

        return redirect()->back();
    }

    public function destroy(InvoiceItem $invoiceItem)
    {
        if (Bill::where('invoice_item_id', $invoiceItem->id)->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.',
            ]);
        }

        $invoice = $invoiceItem->invoice;
        $label   = $invoiceItem->description ?: ($invoiceItem->product_type ?? 'item');

        $invoiceItem->delete();

        $this->catatRiwayatPascaApprove(
            $invoice,
            "Item Rincian Profit dihapus pasca-approve oleh {$this->namaPengguna()}: {$label}."
        );

        return redirect()->back();
    }

    /** Mencatat satu baris riwayat tour, HANYA bila invoice sudah disetujui (spec D5). */
    private function catatRiwayatPascaApprove(Invoice $invoice, string $keterangan): void
    {
        if (! $invoice->is_approved) {
            return;
        }

        $invoice->tour?->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $invoice->tour->status,
            'description'     => $keterangan,
            'created_by'      => $this->namaPengguna(),
        ]);
    }

    private function namaPengguna(): string
    {
        return auth()->user()?->name ?? 'Sistem';
    }

    /** Ringkasan "field lama → baru" untuk field yang benar-benar berubah, dipakai di riwayat tour. */
    private function ringkasPerubahan(InvoiceItem $item, array $before): string
    {
        $label = [
            'qty' => 'qty', 'nights' => 'nights', 'description' => 'deskripsi',
            'unit_cost' => 'unit_cost', 'unit_sell' => 'unit_sell',
            'start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai',
        ];

        $perubahan = [];
        foreach ($before as $field => $nilaiLama) {
            $nilaiBaru = $item->{$field};
            if ((string) $nilaiLama !== (string) $nilaiBaru) {
                $perubahan[] = ($label[$field] ?? $field) . " {$nilaiLama} → {$nilaiBaru}";
            }
        }

        return $perubahan ? implode(', ', $perubahan) : 'tidak ada field yang berubah';
    }
}
