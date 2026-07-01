<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $statuses = [
            'inquiry', 'quotation_draft', 'quotation_sent',
            'follow_up', 'negotiation', 'confirmed', 'cancelled',
        ];

        // Jumlah tour per status
        $countByStatus = Tour::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pipeline = collect($statuses)->map(fn ($s) => [
            'status' => $s,
            'total'  => $countByStatus[$s] ?? 0,
        ]);

        // ── PERKIRAAN — nilai jual confirmed (snapshot tour_items) ──
        $confirmedSell = (float) DB::table('tour_items')
            ->join('tours', 'tours.id', '=', 'tour_items.tour_id')
            ->where('tours.status', 'confirmed')
            ->sum('tour_items.line_sell');

        // ── RIIL (M6) — biaya aktual dari bills tour confirmed ──
        $actualCost = (float) DB::table('bills')
            ->join('tours', 'tours.id', '=', 'bills.tour_id')
            ->where('tours.status', 'confirmed')
            ->sum('bills.amount');

        // Profit riil = nilai jual confirmed − biaya aktual (SUM bills)
        $realProfit = $confirmedSell - $actualCost;

        // ── Arus kas & outstanding ──
        $arOutstanding = (float) Invoice::sum('total_idr') - (float) InvoicePayment::sum('amount_idr');
        $apOutstanding = (float) Bill::sum('amount') - (float) BillPayment::sum('amount');

        // Uang masuk bulan ini (pakai tanggal pembayaran — andal, bukan updated_at)
        $cashInMonth = (float) InvoicePayment::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount_idr');

        // 10 tour terbaru (semua status)
        $recentTours = Tour::with('customer')
            ->withSum('items as total_sell', 'line_sell')
            ->latest()
            ->limit(10)
            ->get();

        // Tour confirmed mendatang (start_date >= hari ini)
        $upcomingConfirmed = Tour::with('customer')
            ->where('status', 'confirmed')
            ->whereNotNull('start_date')
            ->where('start_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'pipeline'          => $pipeline,
            'totalTours'        => Tour::count(),
            'totalConfirmed'    => $countByStatus['confirmed'] ?? 0,
            // Perkiraan
            'confirmedSell'     => $confirmedSell,
            // Riil (M6)
            'actualCost'        => $actualCost,
            'realProfit'        => $realProfit,
            'arOutstanding'     => $arOutstanding,
            'apOutstanding'     => $apOutstanding,
            'cashInMonth'       => $cashInMonth,
            'recentTours'       => $recentTours,
            'upcomingConfirmed' => $upcomingConfirmed,
        ]);
    }
}
