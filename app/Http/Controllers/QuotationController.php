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
        $tour->load(['customer', 'items.product', 'itineraryDays', 'itineraryHours']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin', 'type_label']);

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

        $isTour   = ($tour->type ?? 'tour') === 'tour';
        $viewName = $isTour ? 'quotation' : 'quotation_service';

        $html = view($viewName, [
            'tour'    => $tour,
            'company' => config('quotation.company'),
            'logo'    => $this->logoDataUri(),
            // Untuk non-tour: tidak fallback ke config default (agar tidak tampil jika dikosongkan)
            'included'    => $isTour ? ($tour->included ?: config('quotation.included')) : ($tour->included ?: ''),
            'excluded'    => $isTour ? ($tour->excluded ?: config('quotation.excluded')) : ($tour->excluded ?: ''),
            'childPolicy' => $isTour ? ($tour->child_policy ?: config('quotation.child_policy')) : '',
            'terms'       => $tour->terms ?: config('quotation.terms'),
            'detailLabels' => Tour::DETAIL_LABELS[$tour->type] ?? [],
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf;
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
