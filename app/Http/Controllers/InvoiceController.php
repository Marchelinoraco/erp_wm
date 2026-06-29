<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoiceController extends Controller
{
    // ── Alur sales ──────────────────────────────────────────────────────────────

    /** Sales membuat invoice baru untuk tour (Tahap 1 dimulai dari sini). */
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'pax'      => 'nullable|integer|min:1',
            'date'     => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes'    => 'nullable|string',
        ]);

        $tour->invoices()->create([
            'pax'      => $data['pax'] ?? $tour->pax,
            'date'     => $data['date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? ($tour->start_date?->toDateString() ?? now()->addDays(7)->toDateString()),
            'notes'    => $data['notes'] ?? null,
            'status'   => 'draft',
        ]);

        return redirect()->back();
    }

    /** Sales/admin atur jatuh tempo invoice — hanya selama belum disetujui. */
    public function updateDueDate(Request $request, Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        $data = $request->validate([
            'due_date' => 'nullable|date',
        ]);

        $invoice->update(['due_date' => $data['due_date'] ?? null]);

        return redirect()->back();
    }

    /**
     * Kunci "patokan" (Tahap 1 → Tahap 2): baseline_total = jumlah jual baris saat ini.
     * Dipakai juga untuk "naikkan patokan" selama invoice belum disetujui.
     */
    public function lockBaseline(Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        $sum = (float) $invoice->items()->sum('line_sell');

        if ($sum <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Tambahkan minimal satu item sebelum mengunci patokan.',
            ]);
        }

        $invoice->update(['baseline_total' => $sum, 'total' => $sum]);

        return redirect()->back();
    }

    /**
     * Setujui invoice → gerbang ke Keuangan. Rincian (Tahap 2) wajib = patokan.
     */
    public function approve(Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        if ($invoice->baseline_total <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Kunci patokan terlebih dahulu sebelum menyetujui.',
            ]);
        }

        $total = (float) $invoice->items()->sum('line_sell');

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice belum punya rincian item.',
            ]);
        }

        if (abs($total - (float) $invoice->baseline_total) >= 0.01) {
            throw ValidationException::withMessages([
                'invoice' => 'Total rincian belum sama dengan patokan. Samakan dulu atau ubah patokan.',
            ]);
        }

        $invoice->update([
            'total'       => $total,
            'status'      => 'sent',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        $invoice->tour?->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $invoice->tour->status,
            'description'     => 'Invoice ' . $invoice->number . ' disetujui & dikirim ke Keuangan (IDR ' . number_format($total, 0, ',', '.') . ').',
            'created_by'      => auth()->user()?->name ?? 'Sistem',
        ]);

        return redirect()->back();
    }

    /** Update terbatas oleh akuntan: kelola tanggal/status/catatan pada invoice approved. */
    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
            'status'   => 'required|in:sent,partial,paid',
            'notes'    => 'nullable|string',
        ]);

        $invoice->update($data);

        return redirect()->back();
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->is_approved) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice sudah disetujui dan masuk Keuangan, tidak bisa dihapus.',
            ]);
        }

        $invoice->delete();

        return redirect()->back();
    }

    private function ensureNotApproved(Invoice $invoice): void
    {
        if ($invoice->is_approved) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice sudah disetujui, tidak bisa diubah lagi.',
            ]);
        }
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    public function download(Invoice $invoice)
    {
        return $this->respond($invoice, Destination::DOWNLOAD);
    }

    public function preview(Invoice $invoice)
    {
        return $this->respond($invoice, Destination::INLINE);
    }

    private function respond(Invoice $invoice, string $destination)
    {
        $pdf      = $this->build($invoice);
        $filename = $invoice->number . '.pdf';
        $disp     = $destination === Destination::DOWNLOAD ? 'attachment' : 'inline';

        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disp . '; filename="' . $filename . '"',
        ]);
    }

    private function build(Invoice $invoice): Mpdf
    {
        $invoice->load(['tour.customer', 'items.product', 'payments']);

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_left'   => 9,
            'margin_right'  => 9,
            'margin_top'    => 8,
            'margin_bottom' => 22,
            'margin_header' => 0,
            'margin_footer' => 7,
            'default_font'  => 'dejavusans',
            'tempDir'       => $tmp,
        ]);

        $mpdf->SetTitle('Invoice ' . $invoice->number);

        $items       = $this->lineItems($invoice);
        $itemsTotal  = $items->sum('line');
        $paid        = (float) $invoice->payments->sum('amount');
        $outstanding = (float) $invoice->total - $paid;
        $adjustment  = (float) $invoice->total - $itemsTotal; // selisih item vs nilai tagihan

        $html = view('invoice', [
            'invoice'      => $invoice,
            'company'      => config('quotation.company'),
            'bank'         => $this->bankAccounts(),
            'paymentTerms' => config('quotation.payment_terms', ''),
            'logo'         => $this->logoDataUri(),
            'items'        => $items,
            'itemsTotal'   => $itemsTotal,
            'adjustment'   => $adjustment,
            'paid'         => $paid,
            'outstanding'  => $outstanding,
            'amountWords'  => ucwords($this->terbilang($invoice->total)) . ' Rupiah',
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Rincian item invoice — diambil dari invoice_items (dibuat & disetujui sales).
     * Tahap 1 = 1 baris ringkas; Tahap 2 = banyak baris detail. PDF ikut isi terkini.
     * Mengembalikan koleksi [desc, qty, nights, unit, line].
     */
    private function lineItems(Invoice $invoice): \Illuminate\Support\Collection
    {
        return $invoice->items->map(fn ($it) => [
            'desc'   => $it->description ?: ($it->product?->name ?? 'Item'),
            'qty'    => (int) $it->qty,
            'nights' => (int) $it->nights,
            'unit'   => (float) $it->unit_sell,
            'line'   => (float) $it->line_sell,
        ])->values();
    }

    /** Rekening aktif dari DB (dikelola akuntan); fallback ke config bila kosong. */
    private function bankAccounts(): array
    {
        $accounts = BankAccount::active()->get()
            ->map(fn ($b) => [
                'bank'    => $b->bank,
                'account' => $b->account_number,
                'name'    => $b->holder_name,
            ])->all();

        return $accounts ?: config('quotation.bank', []);
    }

    /** Logo di-embed sebagai data URI agar pasti tampil. */
    private function logoDataUri(): ?string
    {
        $path = public_path('logo.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    /** Konversi angka → terbilang Bahasa Indonesia. */
    private function terbilang($number): string
    {
        $number = (int) abs($number);

        if ($number === 0) {
            return 'nol';
        }

        return trim(preg_replace('/\s+/', ' ', $this->toWords($number)));
    }

    /** Helper rekursif untuk terbilang — sisa bernilai 0 mengembalikan string kosong. */
    private function toWords(int $number): string
    {
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($number < 12) {
            return $words[$number];
        } elseif ($number < 20) {
            return $this->toWords($number - 10) . ' belas';
        } elseif ($number < 100) {
            return $this->toWords(intdiv($number, 10)) . ' puluh ' . $this->toWords($number % 10);
        } elseif ($number < 200) {
            return 'seratus ' . $this->toWords($number - 100);
        } elseif ($number < 1000) {
            return $this->toWords(intdiv($number, 100)) . ' ratus ' . $this->toWords($number % 100);
        } elseif ($number < 2000) {
            return 'seribu ' . $this->toWords($number - 1000);
        } elseif ($number < 1000000) {
            return $this->toWords(intdiv($number, 1000)) . ' ribu ' . $this->toWords($number % 1000);
        } elseif ($number < 1000000000) {
            return $this->toWords(intdiv($number, 1000000)) . ' juta ' . $this->toWords($number % 1000000);
        } elseif ($number < 1000000000000) {
            return $this->toWords(intdiv($number, 1000000000)) . ' miliar ' . $this->toWords($number % 1000000000);
        }

        return $this->toWords(intdiv($number, 1000000000000)) . ' triliun ' . $this->toWords($number % 1000000000000);
    }
}
