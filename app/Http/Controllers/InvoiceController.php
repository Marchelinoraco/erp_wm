<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoiceController extends Controller
{
    /** Mata uang yang didukung untuk tagihan proforma ke customer. */
    public const CURRENCIES = ['IDR', 'USD', 'EUR', 'SGD', 'AUD', 'MYR'];

    // ── Alur sales ──────────────────────────────────────────────────────────────

    /** Sales membuat invoice baru untuk tour (Tahap 1 dimulai dari sini). */
    public function store(Request $request, Tour $tour)
    {
        if ($tour->invoices()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => 'Tour ini sudah punya invoice. Nomor invoice mengikuti kode tour, jadi satu tour hanya boleh satu invoice.',
            ]);
        }

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
     * Sales isi proforma Tahap 1: mata uang, harga/pax, dan baris deskripsi
     * terstruktur (Hotel/Transport dll). Total = unit_price × pax (dari tour).
     */
    public function updateProforma(Request $request, Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        $data = $request->validate([
            'currency'                   => 'required|string|in:' . implode(',', self::CURRENCIES),
            'unit_price'                 => 'required|numeric|min:0',
            'guest_name'                 => 'nullable|string|max:255',
            'description_lines'          => 'nullable|array',
            'description_lines.*.label'  => 'nullable|string|max:255',
            'description_lines.*.date'   => 'nullable|string|max:255',
            'description_lines.*.detail' => 'nullable|string|max:1000',
            'notes'                      => 'nullable|string',
        ]);

        $invoice->fill([
            'currency'          => $data['currency'],
            'unit_price'        => $data['unit_price'],
            'guest_name'        => $data['guest_name'] ?? null,
            'description_lines' => array_values($data['description_lines'] ?? []),
            'notes'             => $data['notes'] ?? $invoice->notes,
        ]);

        // IDR selalu kurs 1; non-IDR menunggu kurs saat disetujui.
        if ($data['currency'] === 'IDR') {
            $invoice->exchange_rate = 1;
        }
        $invoice->save();

        $invoice->syncProformaTotal();

        return redirect()->back();
    }

    /**
     * Kunci "patokan": baseline_total = total proforma (unit_price × pax) saat ini.
     * Dipakai juga untuk "samakan patokan" selama invoice belum disetujui.
     */
    public function lockBaseline(Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        $invoice->syncProformaTotal();

        if ((float) $invoice->total <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Isi harga proforma terlebih dahulu sebelum mengunci patokan.',
            ]);
        }

        $invoice->update(['baseline_total' => $invoice->total]);

        return redirect()->back();
    }

    /**
     * Setujui invoice → gerbang ke Keuangan. Wajib patokan terkunci; untuk mata
     * uang non-IDR wajib input kurs → simpan ekuivalen IDR (total_idr).
     */
    public function approve(Request $request, Invoice $invoice)
    {
        $this->ensureNotApproved($invoice);

        $invoice->syncProformaTotal();

        if ((float) $invoice->baseline_total <= 0) {
            throw ValidationException::withMessages([
                'invoice' => 'Kunci patokan terlebih dahulu sebelum menyetujui.',
            ]);
        }

        $isIdr = ($invoice->currency ?: 'IDR') === 'IDR';

        $data = $request->validate([
            'exchange_rate' => ($isIdr ? 'nullable' : 'required') . '|numeric|gt:0',
        ]);

        $rate     = $isIdr ? 1.0 : (float) $data['exchange_rate'];
        $totalIdr = (float) $invoice->total * $rate;

        DB::transaction(function () use ($invoice, $rate, $totalIdr) {
            $invoice->update([
                'exchange_rate'  => $rate,
                'total_idr'      => $totalIdr,
                'status'         => 'sent',
                'approved_at'    => now(),
                'approved_by'    => auth()->id(),
                // Nomor keuangan gapless — urut sesuai urutan masuk Keuangan
                'finance_number' => $invoice->finance_number ?? Invoice::nextFinanceNumber(),
            ]);
        });

        $money = ($invoice->currency ?: 'IDR') . ' ' . number_format((float) $invoice->total, 0, ',', '.');
        $idrEq = $isIdr ? '' : ' (≈ IDR ' . number_format($totalIdr, 0, ',', '.') . ')';

        $invoice->tour?->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $invoice->tour->status,
            'description'     => 'Invoice ' . $invoice->number . ' disetujui & masuk Keuangan sebagai '
                . $invoice->finance_number . ' (' . $money . $idrEq . ').',
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

        $paid        = (float) $invoice->payments->sum('amount');
        $outstanding = (float) $invoice->total - $paid;

        // Watermark berdasarkan status pembayaran
        if ($paid > 0) {
            $watermarkText  = $outstanding <= 0.005 ? 'PAID IN FULL' : 'DEPOSIT RECEIVED';
            $mpdf->SetWatermarkText($watermarkText);
            $mpdf->showWatermarkText  = true;
            $mpdf->watermarkTextAlpha = 0.07;
        }

        $html = view('invoice', [
            'invoice'      => $invoice,
            'company'      => config('quotation.company'),
            'bank'         => $this->bankAccounts(),
            'paymentTerms' => config('quotation.payment_terms', ''),
            'logo'         => $this->logoDataUri(),
            'lines'        => $invoice->description_lines ?? [],
            'unitPrice'    => (float) $invoice->unit_price,
            'pax'          => (int) ($invoice->tour?->pax ?? $invoice->pax ?? 0),
            'paid'         => $paid,
            'outstanding'  => $outstanding,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf;
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

    /** Logo di-embed sebagai data URI agar pasti tampil. Invoice pakai logo bulat sendiri. */
    private function logoDataUri(): ?string
    {
        $path = public_path('logo-inv1.png');
        if (! is_file($path)) {
            $path = public_path('logo.png');
        }

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
