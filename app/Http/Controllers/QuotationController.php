<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    public function download(Tour $tour)
    {
        $pdf = $this->build($tour);

        return $pdf->download($tour->code . '-quotation.pdf');
    }

    public function preview(Tour $tour)
    {
        $pdf = $this->build($tour);

        return $pdf->stream($tour->code . '-quotation.pdf');
    }

    private function build(Tour $tour)
    {
        $tour->load(['customer', 'items.product', 'itineraryDays', 'itineraryHours']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin']);

        return Pdf::loadView('quotation', [
            'tour'    => $tour,
            'company' => config('quotation.company'),
            // fallback ke teks standar bila field per-tour kosong
            'included'     => $tour->included     ?: config('quotation.included'),
            'excluded'     => $tour->excluded     ?: config('quotation.excluded'),
            'childPolicy'  => $tour->child_policy  ?: config('quotation.child_policy'),
            'terms'        => $tour->terms         ?: config('quotation.terms'),
        ])->setPaper('a4', 'portrait');
    }
}
