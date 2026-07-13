<?php

namespace App\Http\Controllers;

use App\Models\CostRequest;
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
            'amount'   => 'required|numeric|min:0',
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($costRequest, $data) {
            $bill = $costRequest->tour->bills()->create([
                'supplier_id' => $costRequest->supplier_id,
                'category'    => $costRequest->category,
                'description' => $costRequest->description,
                'date'        => $data['date'],
                'due_date'    => $data['due_date'] ?? null,
                'amount'      => $data['amount'],
                'status'      => 'unpaid',
            ]);

            $costRequest->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'bill_id'     => $bill->id,
            ]);
        });

        return redirect()->back();
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
