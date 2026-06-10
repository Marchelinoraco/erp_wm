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
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin']);

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

        // Pre-process deskripsi itinerary: konversi URL gambar → base64 agar mPDF render
        $tour->itineraryDays->each(function ($day) {
            $day->processedDescription = $this->processHtmlForPdf($day->description ?? '');
        });

        $html = view('quotation', [
            'tour'    => $tour,
            'company' => config('quotation.company'),
            'logo'    => $this->logoDataUri(),
            'included'    => $tour->included     ?: config('quotation.included'),
            'excluded'    => $tour->excluded     ?: config('quotation.excluded'),
            'childPolicy' => $tour->child_policy ?: config('quotation.child_policy'),
            'terms'       => $tour->terms        ?: config('quotation.terms'),
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /** Konversi URL gambar storage → base64 data URI agar mPDF render tanpa HTTP fetch. */
    private function processHtmlForPdf(string $html): string
    {
        if (blank($html)) return $html;

        return preg_replace_callback(
            '/<img([^>]+?)src="([^"]*)"([^>]*?)>/i',
            function ($m) {
                $src = $m[2];
                $localPath = null;

                $appUrl = rtrim(config('app.url'), '/');
                if (str_starts_with($src, $appUrl . '/storage/')) {
                    $localPath = storage_path('app/public/' . substr($src, strlen($appUrl . '/storage/')));
                } elseif (str_starts_with($src, '/storage/')) {
                    $localPath = storage_path('app/public/' . substr($src, 9));
                }

                if ($localPath && is_file($localPath)) {
                    $mime = mime_content_type($localPath);
                    $b64  = base64_encode(file_get_contents($localPath));
                    return "<img{$m[1]}src=\"data:{$mime};base64,{$b64}\"{$m[3]}>";
                }

                return $m[0];
            },
            $html
        );
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
