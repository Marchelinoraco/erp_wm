<?php

namespace App\Http\Controllers;

use App\Models\CostRequest;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Biaya tak terduga saat tour berjalan — sales ajukan, akuntan verifikasi
 * (boleh sesuaikan nominal) jadi Bill, atau tolak dengan alasan. Rincian
 * Profit (invoice items) tetap terkunci; ini jalur pelaporan terpisah.
 */
class CostRequestController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'category'    => 'required|in:hotel,transport,guide,restaurant,attraction,other',
            'description' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0',
            'notes'       => 'nullable|string',
        ]);

        $tour->costRequests()->create($data + [
            'requested_by' => auth()->id(),
            'status'       => 'pending',
        ]);

        return redirect()->back();
    }

    public function destroy(CostRequest $costRequest)
    {
        if ($costRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'cost_request' => 'Permintaan yang sudah direview tidak bisa dibatalkan.',
            ]);
        }

        $costRequest->delete();

        return redirect()->back();
    }

    public function approve(Request $request, CostRequest $costRequest)
    {
        $this->ensurePending($costRequest);

        $data = $request->validate([
            'amount'        => 'required|numeric|min:0',
            'date'          => 'required|date',
            'due_date'      => 'nullable|date',
            // Opsional: tagihkan juga ke customer → nempel sebagai baris "Additional"
            // di invoice yang sudah disetujui (nominal jual boleh beda dari nominal biaya)
            'bill_customer' => 'nullable|boolean',
            'sell_amount'   => 'required_if_accepted:bill_customer|nullable|numeric|min:0.01',
        ]);

        $mainInvoice = null;
        if (! empty($data['bill_customer'])) {
            $mainInvoice = $costRequest->tour->invoices()->whereNotNull('approved_at')->first();
            if (! $mainInvoice) {
                throw ValidationException::withMessages([
                    'bill_customer' => 'Tour ini belum punya invoice yang disetujui — tidak bisa menagih biaya tambahan ke customer.',
                ]);
            }
            if (($mainInvoice->currency ?: 'IDR') !== 'IDR') {
                throw ValidationException::withMessages([
                    'bill_customer' => 'Invoice tour ini memakai mata uang ' . $mainInvoice->currency . ' — penagihan biaya tambahan otomatis hanya didukung untuk invoice IDR.',
                ]);
            }
        }

        DB::transaction(function () use ($costRequest, $data, $mainInvoice) {
            $bill = $costRequest->tour->bills()->create([
                'supplier_id' => $costRequest->supplier_id,
                'category'    => $costRequest->category,
                'description' => $costRequest->description,
                'date'        => $data['date'],
                'due_date'    => $data['due_date'] ?? null,
                'amount'      => $data['amount'],
                'status'      => 'unpaid',
            ]);

            if ($mainInvoice) {
                $this->appendAdditionalCharge($mainInvoice, $costRequest->description, (float) $data['sell_amount'], $data['date']);
            }

            $costRequest->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'bill_id'     => $bill->id,
                'invoice_id'  => $mainInvoice?->id,
            ]);
        });

        return redirect()->back();
    }

    /**
     * Tempel baris "Additional" ke invoice yang sudah disetujui — total tagihan
     * customer bertambah otomatis. Invoice tidak diganti/dibuat baru: nomor,
     * kurs, dan pembayaran yang sudah tercatat tetap konsisten (Balance Due
     * cukup dihitung ulang dari total baru dikurangi yang sudah dibayar).
     */
    private function appendAdditionalCharge(Invoice $mainInvoice, string $description, float $amount, string $date): void
    {
        $lines   = $mainInvoice->description_lines ?? [];
        $lines[] = [
            'label'  => 'Additional',
            'date'   => \Carbon\Carbon::parse($date)->format('M d, Y'),
            'detail' => $description,
            'amount' => $amount,
        ];

        $mainInvoice->description_lines = $lines;
        $mainInvoice->total             = (float) $mainInvoice->total + $amount;
        $mainInvoice->total_idr         = (float) $mainInvoice->total_idr + $amount;
        $mainInvoice->save();
    }

    public function reject(Request $request, CostRequest $costRequest)
    {
        $this->ensurePending($costRequest);

        $data = $request->validate([
            'review_notes' => 'required|string|max:500',
        ]);

        $costRequest->update([
            'status'       => 'rejected',
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
            'review_notes' => $data['review_notes'],
        ]);

        return redirect()->back();
    }

    private function ensurePending(CostRequest $costRequest): void
    {
        if ($costRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'cost_request' => 'Permintaan ini sudah direview sebelumnya.',
            ]);
        }
    }
}
