<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoiceController extends Controller
{
    public function store(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
            'total'    => 'required|numeric|min:0',
            'notes'    => 'nullable|string',
        ]);

        $tour->invoices()->create($data);

        return redirect()->back();
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'date'     => 'required|date',
            'due_date' => 'nullable|date',
            'total'    => 'nullable|numeric|min:0',
            'status'   => 'required|in:draft,sent,partial,paid',
            'notes'    => 'nullable|string',
        ]);

        // Draft → total selalu mengikuti jumlah item (auto-sync).
        // Saat keluar dari draft → kunci snapshot = jumlah item saat ini.
        // Sudah terkunci (non-draft) → pakai input akuntan (koreksi/diskon manual).
        if ($invoice->status === 'draft') {
            $data['total'] = (float) ($invoice->tour?->items()->sum('line_sell') ?? 0);
        } else {
            $data['total'] = $data['total'] ?? $invoice->total;
        }

        $invoice->update($data);

        return redirect()->back();
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->back();
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
        $invoice->load(['tour.customer', 'tour.items.product', 'tour.quotationItems', 'payments']);

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
     * Rincian item invoice. Prioritas:
     * 1) tour_items (item disetujui customer yang dikonversi saat tour confirmed)
     * 2) quotation_items yang tidak ditolak (bila tour belum punya tour_items)
     * Mengembalikan koleksi [desc, qty, nights, unit, line].
     */
    private function lineItems(Invoice $invoice): \Illuminate\Support\Collection
    {
        $tour = $invoice->tour;

        if (! $tour) {
            return collect();
        }

        $items = $tour->items->map(fn ($it) => [
            'desc'   => $it->description ?: ($it->product?->name ?? 'Item'),
            'qty'    => (int) $it->qty,
            'nights' => (int) $it->nights,
            'unit'   => (float) $it->unit_sell,
            'line'   => (float) $it->line_sell,
        ])->values();

        if ($items->isEmpty()) {
            $items = $tour->quotationItems
                ->where('status', '!=', 'rejected')
                ->map(fn ($qi) => [
                    'desc'   => $qi->label,
                    'qty'    => (int) $qi->qty,
                    'nights' => (int) $qi->nights,
                    'unit'   => (float) $qi->unit_sell,
                    'line'   => (float) $qi->line_sell,
                ])->values();
        }

        return $items;
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
