<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $statuses = [
            'inquiry', 'quotation_draft', 'quotation_sent',
            'follow_up', 'negotiation', 'confirmed', 'cancelled',
        ];

        // Jumlah tour per status
        $countByStatus = Tour::visibleTo($user)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pipeline = collect($statuses)->map(fn ($s) => [
            'status' => $s,
            'total'  => $countByStatus[$s] ?? 0,
        ]);

        // Hanya admin/accountant boleh melihat data finansial company-wide di
        // Dashboard (lihat docs/superpowers/specs/2026-07-29-dashboard-keuangan-restricted-design.md).
        // Modul Keuangan sendiri sudah dibatasi role:admin,accountant; Dashboard
        // adalah satu-satunya tempat yang tadinya membocorkan ringkasan ini ke sales.
        $canViewFinance = $user->isAdmin() || $user->isAccountant();

        $financeData = [];

        if ($canViewFinance) {
            // ── PERKIRAAN — nilai jual confirmed ──
            // Dari mana sell diambil (tagihan invoice vs tour_items) adalah
            // aturan per jenis penjualan, dan Tour::total_sell sudah memutuskan
            // itu lewat SalesLineRuleRegistry. Dashboard menjumlah saja — dulu
            // ia mengulang aturannya dengan tangan dan bisa berselisih dengan
            // panel sales tanpa ada yang menyadarinya.
            // Eager load di bawah memuat justru yang dibutuhkan atribut itu,
            // jadi tidak ada query tambahan per tour.
            $confirmedSell = (float) Tour::where('status', 'confirmed')
                ->when($user->isSales(), fn ($q) => $this->tourOwnershipFilter($q, $user))
                ->with(['items:id,tour_id,line_sell', 'invoices' => fn ($q) => $q->approved()])
                ->get(['id', 'type'])
                ->sum(fn (Tour $tour) => $tour->total_sell);

            // ── RIIL (M6) — biaya aktual dari bills tour confirmed ──
            $actualCost = (float) DB::table('bills')
                ->join('tours', 'tours.id', '=', 'bills.tour_id')
                ->where('tours.status', 'confirmed')
                ->when($user->isSales(), fn ($q) => $this->applyTourOwnership($q, $user))
                ->sum('bills.amount');

            // Profit riil = nilai jual confirmed − biaya aktual (SUM bills)
            $realProfit = $confirmedSell - $actualCost;

            // ── Arus kas & outstanding (untuk sales, hanya tour miliknya) ──
            // approved() di kedua sisi — proforma draft bukan piutang, dan
            // pembayarannya tidak boleh ikut mengurangi. Penyaring kepemilikan
            // tour untuk sales tetap utuh di sampingnya.
            $arOutstanding = (float) Invoice::approved()->when($user->isSales(), fn ($q) => $q->whereHas('tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('total_idr')
                - (float) InvoicePayment::whereHas('invoice', fn ($q) => $q->approved())->when($user->isSales(), fn ($q) => $q->whereHas('invoice.tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('amount_idr');
            $apOutstanding = (float) Bill::when($user->isSales(), fn ($q) => $q->whereHas('tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('amount')
                - (float) BillPayment::when($user->isSales(), fn ($q) => $q->whereHas('bill.tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))->sum('amount');

            // Uang masuk bulan ini (pakai tanggal pembayaran — andal, bukan updated_at)
            $cashInMonth = (float) InvoicePayment::whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->when($user->isSales(), fn ($q) => $q->whereHas('invoice.tour', fn ($t) => $this->tourOwnershipFilter($t, $user)))
                ->sum('amount_idr');

            $financeData = [
                // Perkiraan
                'confirmedSell' => $confirmedSell,
                // Riil (M6)
                'actualCost'    => $actualCost,
                'realProfit'    => $realProfit,
                'arOutstanding' => $arOutstanding,
                'apOutstanding' => $apOutstanding,
                'cashInMonth'   => $cashInMonth,
            ];
        }

        // 10 tour terbaru (semua status)
        $recentTours = Tour::visibleTo($user)
            ->with('customer')
            ->withSum('items as total_sell', 'line_sell')
            ->latest()
            ->limit(10)
            ->get();

        // Tour confirmed mendatang (start_date >= hari ini)
        $upcomingConfirmed = Tour::visibleTo($user)
            ->with('customer')
            ->where('status', 'confirmed')
            ->whereNotNull('start_date')
            ->where('start_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        $data = [
            'pipeline'          => $pipeline,
            'totalTours'        => Tour::visibleTo($user)->count(),
            'totalConfirmed'    => $countByStatus['confirmed'] ?? 0,
            'canViewFinance'    => $canViewFinance,
            'recentTours'       => $recentTours,
            'upcomingConfirmed' => $upcomingConfirmed,
        ];

        return Inertia::render('Dashboard', array_merge($data, $financeData));
    }

    /** Filter kepemilikan tour untuk query DB::table yang sudah join ke `tours`. */
    private function applyTourOwnership($query, $user)
    {
        return $query->where(fn ($w) => $w->where('tours.created_by', $user->id)->orWhereNull('tours.created_by'));
    }

    /** Filter kepemilikan tour untuk dipakai di dalam closure whereHas('tour', ...). */
    private function tourOwnershipFilter($query, $user)
    {
        return $query->where(fn ($w) => $w->where('created_by', $user->id)->orWhereNull('created_by'));
    }
}
