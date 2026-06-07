<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    public function download(Tour $tour)
    {
        $tour->load(['customer', 'items.product']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin']);

        $pdf = Pdf::loadView('quotation', ['tour' => $tour])
            ->setPaper('a4', 'portrait');

        return $pdf->download($tour->code . '-quotation.pdf');
    }

    public function preview(Tour $tour)
    {
        $tour->load(['customer', 'items.product']);
        $tour->append(['total_cost', 'total_sell', 'profit', 'margin']);

        $pdf = Pdf::loadView('quotation', ['tour' => $tour])
            ->setPaper('a4', 'portrait');

        return $pdf->stream($tour->code . '-quotation.pdf');
    }
}
