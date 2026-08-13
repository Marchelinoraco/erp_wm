<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Tour;
use App\Services\SalesLine\SalesLineRuleRegistry;
use App\Support\CompactDateRange;
use App\Support\HotelRoomLabel;
use App\Support\Pdf;
use App\Support\RoomChargeLine;
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
                'invoice' => 'Tour ini sudah punya invoice — satu tour hanya boleh satu invoice.',
            ]);
        }

        $data = $request->validate([
            'pax'      => 'nullable|integer|min:1',
            'date'     => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes'    => 'nullable|string',
        ]);

        // Transaksi + lockForUpdate (Invoice::nextNumber) mencegah dua invoice
        // tipe & tahun sama dibuat bersamaan mendapat nomor kembar.
        DB::transaction(function () use ($tour, $data) {
            $tour->invoices()->create([
                'guest_name' => $tour->guest_name,
                'pax'      => $data['pax'] ?? $tour->pax,
                'date'     => $data['date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? ($tour->start_date?->toDateString() ?? now()->addDays(7)->toDateString()),
                'notes'    => $data['notes'] ?? null,
                'status'   => 'draft',
            ]);
        });

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
            'currency'                       => 'required|string|in:' . implode(',', self::CURRENCIES),
            'unit_price'                     => 'required|numeric|min:0',
            'pricing_mode'                   => 'nullable|string|in:' . implode(',', Invoice::PRICING_MODES),
            'guest_name'                     => 'nullable|string|max:255',
            'description_lines'              => 'nullable|array',
            'description_lines.*.label'      => 'nullable|string|max:255',
            'description_lines.*.date'       => 'nullable|string|max:255',
            'description_lines.*.date_end'   => 'nullable|string|max:255',
            'description_lines.*.rooms'      => 'nullable|integer|min:0',
            'description_lines.*.unit_price' => 'nullable|numeric|min:0',
            'description_lines.*.detail'     => 'nullable|string|max:1000',
            'description_lines.*.amount'     => 'nullable|numeric|min:0',
            'bank_account_ids'               => 'nullable|array',
            'bank_account_ids.*'             => 'integer|exists:bank_accounts,id',
            'notes'                          => 'nullable|string',
        ]);

        $invoice->fill([
            'currency'          => $data['currency'],
            'unit_price'        => $data['unit_price'],
            'guest_name'        => $data['guest_name'] ?? null,
            // Nominal baris kamar SELALU dihitung server — nilai yang dikirim
            // browser hanya untuk ditampilkan dan tidak pernah dipercaya.
            'description_lines' => RoomChargeLine::recalculate(array_values($data['description_lines'] ?? [])),
            'pricing_mode'      => $data['pricing_mode'] ?? $invoice->pricing_mode,
            // Kosong = tampilkan semua rekening aktif (lihat bankAccounts())
            'bank_account_ids'  => ! empty($data['bank_account_ids']) ? array_values($data['bank_account_ids']) : null,
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
            ]);

            // Item bersupplier di Rincian Profit langsung tercatat sebagai Bill
            // draft (nominal 0) — akuntan tinggal mengisi nominalnya.
            Bill::createMissingFromInvoice($invoice);
        });

        $money = ($invoice->currency ?: 'IDR') . ' ' . number_format((float) $invoice->total, 0, ',', '.');
        $idrEq = $isIdr ? '' : ' (≈ IDR ' . number_format($totalIdr, 0, ',', '.') . ')';

        $invoice->tour?->histories()->create([
            'type'            => 'note',
            'status_snapshot' => $invoice->tour->status,
            // finance_number pensiun — satu nomor (number) saja yang disebut di sini
            'description'     => 'Invoice ' . $invoice->number . ' disetujui & masuk Keuangan ('
                . $money . $idrEq . ').',
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

    /**
     * PDF Rincian Profit (internal) — modal vs jual untuk Keuangan.
     * Hanya tersedia setelah invoice disetujui sales.
     */
    public function profitPdf(Invoice $invoice)
    {
        abort_unless($invoice->approved_at, 403, 'Rincian profit hanya tersedia setelah invoice disetujui.');

        $invoice->load(['tour.customer', 'items', 'approvedBy']);

        $tour      = $invoice->tour;
        $isTour    = app(SalesLineRuleRegistry::class)->for($tour->type ?? 'tour')->profitFromRevenue();
        $totalCost = $invoice->items->sum('line_cost');
        $totalSell = $invoice->items->sum('line_sell');
        // Tour inbound/outbound: profit = tagihan customer (IDR) − cost item.
        // Tipe lain: profit per item (jual − cost). Rumus sama dengan panel sales.
        $revenue = $isTour ? (float) $invoice->total_idr : (float) $totalSell;
        $profit  = $revenue - $totalCost;
        $margin  = $revenue > 0 ? round($profit / $revenue * 100, 1) : 0;

        $number = $invoice->number;

        return Pdf::stream('finance.profit_breakdown', [
            'invoice'   => $invoice,
            'tour'      => $tour,
            'isTour'    => $isTour,
            'totalCost' => $totalCost,
            'totalSell' => $totalSell,
            'profit'    => $profit,
            'margin'    => $margin,
            'title'     => 'Rincian Profit',
            'period'    => $number,
        ], 'PROFIT-' . $number);
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

        $data        = $this->invoiceViewData($invoice);
        $paid        = $data['paid'];
        $outstanding = $data['outstanding'];

        // Watermark berdasarkan status pembayaran
        if ($paid > 0) {
            $watermarkText  = $outstanding <= 0.005 ? 'PAID IN FULL' : 'DEPOSIT RECEIVED';
            $mpdf->SetWatermarkText($watermarkText);
            $mpdf->showWatermarkText  = true;
            $mpdf->watermarkTextAlpha = 0.07;
        }

        $html = view('invoice', $data)->render();

        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Data view PDF invoice. Publik supaya bisa diuji langsung: menyusun ulang
     * data ini di dalam test hanya akan menguji blade, sementara penyambungan
     * aturan di sini — bagian yang paling mudah salah — tak tersentuh.
     */
    public function invoiceViewData(Invoice $invoice): array
    {
        // Satu aturan untuk seluruh dokumen, diselesaikan lewat invoice supaya
        // mode hitung hotel ikut terbaca — bukan hanya jenis penjualannya.
        $aturan = app(SalesLineRuleRegistry::class)->forInvoice($invoice);

        $paid        = (float) $invoice->payments->sum('amount');
        $outstanding = (float) $invoice->total - $paid;

        // Baris yang benar-benar ikut TOTAL mode aktif — aturan sama persis
        // dengan Invoice::syncProformaTotal(): baris kamar (RoomChargeLine::
        // isRoomLine()) hanya ikut ketika totalComposition() === 'line_items'.
        // Dihitung sekali di sini (bukan di Blade) supaya baris "Price" dan
        // daftar baris bernominal di PDF selalu sepakat dengan total-nya —
        // tidak pernah mengurangkan/menampilkan baris kamar yang tersimpan
        // tapi mode aktifnya sudah bukan mode kamar.
        $chargeLines = collect($invoice->description_lines ?? [])
            ->reject(fn ($l) => $aturan->totalComposition() !== 'line_items' && RoomChargeLine::isRoomLine($l))
            ->values()
            ->all();

        // Baris kamar yang dicetak berpasangan dengan "Price"-nya. Kosong bila
        // tata letaknya bukan hotel_room, sehingga Blade tidak perlu bertanya.
        $roomLines = $aturan->chargeLineLayout() === 'hotel_room'
            ? collect($chargeLines)->filter(fn ($l) => RoomChargeLine::isRoomLine($l))->values()->all()
            : [];

        // Spec §2.4: satu baris kamar menaikkan "Hotel / Room" ke blok info;
        // dua atau lebih menurunkannya berpasangan ke area bernominal. Mode
        // pax selalu memakai blok info, karena hanya punya satu keterangan.
        $hotelRoomInfo = match (true) {
            count($roomLines) === 1        => HotelRoomLabel::forRoomLine($roomLines[0]),
            $aturan->chargeLineLayout() === 'hotel_room' => '',
            default                        => trim((string) $invoice->hotel_room),
        };

        $resvDate = $aturan->usesCompactDateInPdf()
            ? CompactDateRange::format($invoice->tour?->start_date, $invoice->tour?->end_date)
            : ($invoice->tour?->start_date
                ? $invoice->tour->start_date->format('d F Y')
                    . ($invoice->tour->end_date ? ' – ' . $invoice->tour->end_date->format('d F Y') : '')
                : '');

        return [
            'invoice'      => $invoice,
            'company'      => config('quotation.company'),
            'bank'         => $this->bankAccounts($invoice),
            'paymentTerms' => config('quotation.payment_terms', ''),
            'logo'         => $this->logoDataUri(),
            'lines'        => $invoice->description_lines ?? [],
            'chargeLines'  => $chargeLines,
            'unitPrice'    => (float) $invoice->unit_price,
            // Jenis yang totalnya tersusun dari baris bernominal tidak punya
            // harga satuan yang bermakna — unit_price lamanya sengaja dibiarkan
            // utuh di database (agar banner panel bisa menampilkannya), jadi
            // nilainya TIDAK bisa dipakai menyimpulkan ini. Aturannya yang tahu.
            'fromLineItems'  => $aturan->totalComposition() === 'line_items',
            'chargeLineLayout' => $aturan->chargeLineLayout(),
            'showsTotalPax'    => $aturan->showsTotalPaxInPdf(),
            'hotelRoomInfo'    => $hotelRoomInfo,
            'roomLines'        => $roomLines,
            'resvDate'         => $resvDate,
            // Pax milik INVOICE (bukan tour) — invoice suplemen biaya tambahan
            // pakai pax 1 agar baris "harga × pax" cocok dengan totalnya.
            'pax'          => (int) ($invoice->pax ?? $invoice->tour?->pax ?? 0),
            'paid'         => $paid,
            'outstanding'  => $outstanding,
        ];
    }

    /**
     * Rekening yang tampil di PDF — sales bisa pilih sebagian saat mengisi
     * proforma (bank_account_ids); kosong = semua rekening aktif (default lama).
     */
    private function bankAccounts(Invoice $invoice): array
    {
        $query = BankAccount::active();
        if (! empty($invoice->bank_account_ids)) {
            $query->whereIn('id', $invoice->bank_account_ids);
        }

        $accounts = $query->get()
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
