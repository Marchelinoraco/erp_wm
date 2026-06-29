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
        // Hanya invoice yang sudah disetujui sales yang masuk ke Keuangan.
        $arTotal    = Invoice::approved()->sum('total');
        $arReceived = InvoicePayment::whereHas('invoice', fn ($q) => $q->approved())->sum('amount');

        $apTotal = Bill::sum('amount');
        $apPaid  = BillPayment::sum('amount');

        $outstandingInvoices = Invoice::approved()
            ->with(['tour:id,code,customer_id', 'tour.customer:id,name', 'payments'])
            ->whereIn('status', ['sent', 'partial'])
            ->latest('date')
            ->get();

        $unpaidBills = Bill::with(['tour:id,code', 'supplier:id,name', 'payments'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->latest('date')
            ->get();

        // Tour confirmed — pintu masuk ke pencatatan keuangan per tour
        $confirmedTours = Tour::where('status', 'confirmed')
            ->with('customer:id,name')
            ->withSum('items as est_sell', 'line_sell')
            ->withSum('items as est_cost', 'line_cost')
            ->withSum(['invoices as invoiced_total' => fn ($q) => $q->approved()], 'total')
            ->withSum('bills as billed_total', 'amount')
            ->withCount(['invoices as invoices_count' => fn ($q) => $q->approved(), 'bills'])
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'code'           => $t->code,
                'title'          => $t->title,
                'customer'       => $t->customer?->name,
                'est_sell'       => (float) $t->est_sell,
                'est_cost'       => (float) $t->est_cost,
                'est_profit'     => (float) $t->est_sell - (float) $t->est_cost,
                'invoiced_total' => (float) $t->invoiced_total,
                'billed_total'   => (float) $t->billed_total,
                'invoices_count' => $t->invoices_count,
                'bills_count'    => $t->bills_count,
            ]);

        return Inertia::render('Finance/Index', [
            'ar_total'             => (float) $arTotal,
            'ar_received'          => (float) $arReceived,
            'ap_total'             => (float) $apTotal,
            'ap_paid'              => (float) $apPaid,
            'outstanding_invoices' => $outstandingInvoices,
            'unpaid_bills'         => $unpaidBills,
            'confirmed_tours'      => $confirmedTours,
        ]);
    }

    public function tour(Tour $tour)
    {
        $tour->load([
            'customer:id,name',
            'items',
            // Hanya invoice yang sudah disetujui sales yang dikelola di Keuangan.
            'invoices' => fn ($q) => $q->approved()->with('payments'),
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
