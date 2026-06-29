<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class QuotationController extends Controller
{
    public function download(Tour $tour)
    {
        return $this->respond($tour, Destination::DOWNLOAD);
    }

    public function preview(Tour $tour)
    {
        return $this->respond($tour, Destination::INLINE);
    }

    /**
     * Ekspor Word (.doc) — HTML quotation yang sama dengan PDF, dibuka & bisa
     * diedit di Microsoft Word. Tanpa library tambahan; tag khusus mPDF dibuang.
     */
    public function word(Tour $tour)
    {
        $html = $this->renderHtml($tour);

        // Buang konstruksi khusus mPDF yang tak dikenal Word (footer/header halaman).
        $html = preg_replace('#<htmlpagefooter.*?</htmlpagefooter>#is', '', $html);
        $html = preg_replace('#<htmlpageheader.*?</htmlpageheader>#is', '', $html);

        // Pastikan ada deklarasi charset agar simbol (Rp, dsb.) terbaca benar di Word.
        if (! str_contains($html, 'charset')) {
            $html = preg_replace('/<head>/i', '<head><meta charset="utf-8">', $html, 1);
        }

        $filename = $tour->code . '-quotation.doc';

        return response($html, 200, [
            'Content-Type'        => 'application/msword; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function respond(Tour $tour, string $destination)
    {
        $pdf      = $this->build($tour);
        $filename = $tour->code . '-quotation.pdf';
        $disp     = $destination === Destination::DOWNLOAD ? 'attachment' : 'inline';

        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disp . '; filename="' . $filename . '"',
        ]);
    }

    private function build(Tour $tour): Mpdf
    {
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        // Margin dlm mm. margin_bottom menampung footer → mPDF otomatis reserve,
        // jadi konten TIDAK pernah tertimpa footer di halaman mana pun.
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

        $mpdf->SetTitle('Quotation ' . $tour->code);
        $mpdf->WriteHTML($this->renderHtml($tour));

        return $mpdf;
    }

    /** Render HTML quotation (dipakai bersama oleh ekspor PDF & Word). */
    private function renderHtml(Tour $tour): string
    {
        $isTour = ($tour->type ?? 'tour') === 'tour';

        $tour->load(['customer', 'items.product', 'itineraryDays', 'itineraryHours']);
        if (! $isTour) {
            $tour->load('quotationItems');
        }
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin', 'type_label']);

        $viewName = $isTour ? 'quotation' : 'quotation_service';

        return view($viewName, [
            'tour'    => $tour,
            'company' => config('quotation.company'),
            'logo'    => $this->logoDataUri(),
            // Untuk non-tour: tidak fallback ke config default (agar tidak tampil jika dikosongkan)
            'included'     => $isTour ? ($tour->included ?: config('quotation.included')) : ($tour->included ?: ''),
            'excluded'     => $isTour ? ($tour->excluded ?: config('quotation.excluded')) : ($tour->excluded ?: ''),
            'childPolicy'  => $isTour ? ($tour->child_policy ?: config('quotation.child_policy')) : '',
            'terms'        => $tour->terms ?: config('quotation.terms'),
            'detailLabels' => Tour::DETAIL_LABELS[$tour->type] ?? [],
            'qItems'       => $isTour ? collect() : ($tour->quotationItems ?? collect()),
        ])->render();
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
}
