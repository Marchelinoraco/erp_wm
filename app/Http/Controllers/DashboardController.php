<?php

namespace App\Http\Controllers;

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

        // Nilai & profit confirmed bulan ini
        $confirmedThisMonth = Tour::where('tours.status', 'confirmed')
            ->whereMonth('tours.updated_at', now()->month)
            ->whereYear('tours.updated_at', now()->year)
            ->join('tour_items', 'tour_items.tour_id', '=', 'tours.id')
            ->selectRaw('
                SUM(tour_items.line_sell) as total_sell,
                SUM(tour_items.line_cost) as total_cost
            ')
            ->first();

        $sellMonth  = (float) ($confirmedThisMonth->total_sell ?? 0);
        $costMonth  = (float) ($confirmedThisMonth->total_cost ?? 0);
        $profitMonth = $sellMonth - $costMonth;

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
            'sellMonth'         => $sellMonth,
            'profitMonth'       => $profitMonth,
            'totalTours'        => Tour::count(),
            'totalConfirmed'    => $countByStatus['confirmed'] ?? 0,
            'recentTours'       => $recentTours,
            'upcomingConfirmed' => $upcomingConfirmed,
        ]);
    }
}
