<?php

namespace App\Support;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Helper render PDF laporan keuangan via mPDF (header & footer branded).
 */
class Pdf
{
    public static function stream(string $view, array $data, string $filename, string $orientation = 'P'): Response
    {
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'format'        => $orientation === 'L' ? 'A4-L' : 'A4',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 16,
            'margin_header' => 0,
            'margin_footer' => 8,
            'default_font'  => 'dejavusans',
            'tempDir'       => $tmp,
        ]);

        $mpdf->SetTitle($filename);

        $html = view($view, array_merge($data, [
            'company' => config('quotation.company'),
            'logo'    => self::logoDataUri(),
        ]))->render();

        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename . '.pdf', Destination::STRING_RETURN), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '.pdf"',
        ]);
    }

    private static function logoDataUri(): ?string
    {
        $path = public_path('logo.png');

        return is_file($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }
}
