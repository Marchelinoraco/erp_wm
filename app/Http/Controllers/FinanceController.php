<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Supplier;
use App\Models\Tour;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        $arTotal    = Invoice::sum('total');
        $arReceived = InvoicePayment::sum('amount');

        $apTotal = Bill::sum('amount');
        $apPaid  = BillPayment::sum('amount');

        $outstandingInvoices = Invoice::with(['tour:id,code,customer_id', 'tour.customer:id,name', 'payments'])
            ->whereIn('status', ['draft', 'sent', 'partial'])
            ->latest('date')
            ->get();

        $unpaidBills = Bill::with(['tour:id,code', 'supplier:id,name', 'payments'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->latest('date')
            ->get();

        return Inertia::render('Finance/Index', [
            'ar_total'             => (float) $arTotal,
            'ar_received'          => (float) $arReceived,
            'ap_total'             => (float) $apTotal,
            'ap_paid'              => (float) $apPaid,
            'outstanding_invoices' => $outstandingInvoices,
            'unpaid_bills'         => $unpaidBills,
        ]);
    }

    public function tour(Tour $tour)
    {
        $tour->load([
            'customer:id,name',
            'items',
            'invoices.payments',
            'bills.payments',
            'bills.supplier:id,name',
        ]);

        $tour->append([
            'total_cost', 'total_sell', 'profit', 'margin',
            'actual_cost', 'actual_profit', 'cost_variance',
            'received', 'receivable',
        ]);

        return Inertia::render('Finance/Tour', [
            'tour'      => $tour,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
