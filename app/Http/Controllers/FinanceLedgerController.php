<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class FinanceLedgerController extends Controller
{
    // ── Halaman: Transaksi (catat pendapatan/pengeluaran) ───────────────────
    public function transactions(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = array_pad(explode('-', $month), 2, now()->month);

        $rows = FinTransaction::with(['category', 'cashAccount'])
            ->whereYear('date', (int) $y)->whereMonth('date', (int) $m)
            ->orderByDesc('date')->orderByDesc('id')
            ->get();

        return Inertia::render('Finance/Transactions', [
            'month'        => $month,
            'transactions' => $rows,
            'categories'   => FinCategory::orderBy('type')->orderBy('sort_order')->orderBy('name')->get(),
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(),
            'summary'      => [
                'income'  => (float) $rows->where('direction', 'in')->sum('amount'),
                'expense' => (float) $rows->where('direction', 'out')->sum('amount'),
            ],
        ]);
    }

    public function storeTransaction(Request $request)
    {
        $data = $this->validateTransaction($request);
        $data['source']     = 'manual';
        $data['created_by'] = $request->user()?->name;
        FinTransaction::create($data);

        return back()->with('success', 'Transaksi dicatat.');
    }

    public function updateTransaction(Request $request, FinTransaction $finTransaction)
    {
        abort_unless($finTransaction->source === 'manual', 403, 'Transaksi otomatis dari AR/AP tidak bisa diedit di sini.');
        $finTransaction->update($this->validateTransaction($request));

        return back()->with('success', 'Transaksi diperbarui.');
    }

    public function destroyTransaction(FinTransaction $finTransaction)
    {
        abort_unless($finTransaction->source === 'manual', 403, 'Transaksi otomatis dari AR/AP tidak bisa dihapus di sini.');
        $finTransaction->delete();

        return back()->with('success', 'Transaksi dihapus.');
    }

    private function validateTransaction(Request $request): array
    {
        $data = $request->validate([
            'date'            => 'required|date',
            'direction'       => 'required|in:in,out',
            'fin_category_id' => 'required|exists:fin_categories,id',
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'amount'          => 'required|numeric|min:1',
            'description'     => 'nullable|string|max:255',
        ]);

        // Pastikan jenis kategori selaras dengan arah (in=income, out=expense)
        $cat = FinCategory::find($data['fin_category_id']);
        $want = $data['direction'] === 'in' ? 'income' : 'expense';
        abort_if($cat && $cat->type !== $want, 422, 'Kategori tidak sesuai dengan jenis transaksi.');

        return $data;
    }

    // ── Kategori CRUD ────────────────────────────────────────────────────────
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ]);
        $data['sort_order'] = FinCategory::max('sort_order') + 1;
        FinCategory::create($data);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, FinCategory $finCategory)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        $finCategory->update($data);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroyCategory(FinCategory $finCategory)
    {
        abort_if($finCategory->is_system, 403, 'Kategori bawaan tidak bisa dihapus.');
        abort_if($finCategory->transactions()->exists(), 422, 'Kategori masih dipakai transaksi.');
        $finCategory->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    // ── Akun kas CRUD ─────────────────────────────────────────────────────────
    public function storeCashAccount(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:cash,bank',
            'opening_balance' => 'nullable|numeric',
        ]);
        $data['sort_order'] = CashAccount::max('sort_order') + 1;
        CashAccount::create($data);

        return back()->with('success', 'Akun kas ditambahkan.');
    }

    public function updateCashAccount(Request $request, CashAccount $cashAccount)
    {
        $cashAccount->update($request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:cash,bank',
            'opening_balance' => 'nullable|numeric',
            'is_active'       => 'boolean',
        ]));

        return back()->with('success', 'Akun kas diperbarui.');
    }

    public function destroyCashAccount(CashAccount $cashAccount)
    {
        abort_if($cashAccount->transactions()->exists(), 422, 'Akun masih dipakai transaksi.');
        $cashAccount->delete();

        return back()->with('success', 'Akun kas dihapus.');
    }

    // ── Halaman: Arus Kas (chart) ─────────────────────────────────────────────
    public function cashFlow(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        // Seri bulanan: pemasukan vs pengeluaran
        $months = [];
        $incomeSeries = [];
        $expenseSeries = [];
        $netSeries = [];
        for ($m = 1; $m <= 12; $m++) {
            $in  = (float) FinTransaction::whereYear('date', $year)->whereMonth('date', $m)->where('direction', 'in')->sum('amount');
            $out = (float) FinTransaction::whereYear('date', $year)->whereMonth('date', $m)->where('direction', 'out')->sum('amount');
            $months[]        = Carbon::create($year, $m, 1)->translatedFormat('M');
            $incomeSeries[]  = $in;
            $expenseSeries[] = $out;
            $netSeries[]     = $in - $out;
        }

        // Saldo berjalan kumulatif (sepanjang tahun)
        $runningStart = $this->balanceBefore(Carbon::create($year, 1, 1));
        $balanceSeries = [];
        $run = $runningStart;
        foreach ($netSeries as $net) {
            $run += $net;
            $balanceSeries[] = round($run, 2);
        }

        // Breakdown per kategori (tahun berjalan)
        $byCategory = fn (string $dir) => FinTransaction::with('category')
            ->whereYear('date', $year)->where('direction', $dir)
            ->get()->groupBy('fin_category_id')
            ->map(fn ($g) => ['name' => $g->first()->category?->name ?? '-', 'total' => (float) $g->sum('amount')])
            ->sortByDesc('total')->values();

        // Saldo terkini per akun kas
        $accounts = CashAccount::orderBy('sort_order')->orderBy('id')->get()->map(function ($a) {
            $in  = (float) $a->transactions()->where('direction', 'in')->sum('amount');
            $out = (float) $a->transactions()->where('direction', 'out')->sum('amount');
            return ['name' => $a->name, 'type' => $a->type, 'balance' => (float) $a->opening_balance + $in - $out];
        });

        return Inertia::render('Finance/CashFlow', [
            'year'          => $year,
            'years'         => $this->availableYears(),
            'months'        => $months,
            'incomeSeries'  => $incomeSeries,
            'expenseSeries' => $expenseSeries,
            'balanceSeries' => $balanceSeries,
            'incomeByCat'   => $byCategory('in'),
            'expenseByCat'  => $byCategory('out'),
            'accounts'      => $accounts,
            'totals'        => [
                'income'  => array_sum($incomeSeries),
                'expense' => array_sum($expenseSeries),
                'net'     => array_sum($netSeries),
                'balance' => $accounts->sum('balance'),
            ],
        ]);
    }

    // ── Halaman: Jurnal Bulanan (debit = kredit) ─────────────────────────────
    public function journal(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        [$y, $m] = array_pad(explode('-', $month), 2, now()->month);

        $txns = FinTransaction::with(['category', 'cashAccount'])
            ->whereYear('date', (int) $y)->whereMonth('date', (int) $m)
            ->orderBy('date')->orderBy('id')->get();

        $entries = $txns->map(fn ($t) => [
            'date'        => $t->date->format('Y-m-d'),
            'description' => $t->description ?: ($t->category?->name ?? '-'),
            'ref'         => $t->source === 'manual' ? 'Manual' : ($t->source === 'invoice' ? 'AR' : 'AP'),
            'lines'       => $t->journalLines(),
        ])->values();

        $total = (float) $txns->sum('amount');

        return Inertia::render('Finance/Journal', [
            'month'   => $month,
            'entries' => $entries,
            'totals'  => ['debit' => $total, 'credit' => $total, 'count' => $txns->count()],
        ]);
    }

    // ── Halaman: Buku Besar + Laba Akuntansi ──────────────────────────────────
    public function ledger(Request $request)
    {
        $year  = (int) $request->input('year', now()->year);
        $month = $request->input('month'); // kosong = setahun penuh

        $q = FinTransaction::with(['category', 'cashAccount'])->whereYear('date', $year);
        if ($month) {
            $q->whereMonth('date', (int) $month);
        }
        $txns = $q->orderBy('date')->orderBy('id')->get();

        $acc = [];
        $touch = function (string $key, string $name, string $group) use (&$acc) {
            $acc[$key] ??= ['name' => $name, 'group' => $group, 'debit' => 0.0, 'credit' => 0.0, 'postings' => []];
        };

        foreach ($txns as $t) {
            $amt      = (float) $t->amount;
            $date     = $t->date->format('Y-m-d');
            $cashKey  = 'cash-' . $t->cash_account_id;
            $catKey   = 'cat-' . $t->fin_category_id;
            $cashName = $t->cashAccount?->name ?? 'Kas';
            $catName  = $t->category?->name ?? '-';

            $touch($cashKey, $cashName, 'aset');
            $touch($catKey, $catName, $t->direction === 'in' ? 'pendapatan' : 'beban');

            if ($t->direction === 'in') {
                $acc[$cashKey]['debit'] += $amt;
                $acc[$cashKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => $amt, 'credit' => 0];
                $acc[$catKey]['credit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $cashName, 'debit' => 0, 'credit' => $amt];
            } else {
                $acc[$catKey]['debit'] += $amt;
                $acc[$catKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $cashName, 'debit' => $amt, 'credit' => 0];
                $acc[$cashKey]['credit'] += $amt;
                $acc[$cashKey]['postings'][] = ['date' => $date, 'desc' => $t->description ?: $catName, 'debit' => 0, 'credit' => $amt];
            }
        }

        $accounts = collect($acc)->map(function ($a) {
            $normalDebit  = in_array($a['group'], ['aset', 'beban']);
            $a['balance'] = $normalDebit ? $a['debit'] - $a['credit'] : $a['credit'] - $a['debit'];
            return $a;
        })->sortBy([['group', 'asc'], ['name', 'asc']])->values();

        $pendapatan = (float) $accounts->where('group', 'pendapatan')->sum('balance');
        $beban      = (float) $accounts->where('group', 'beban')->sum('balance');

        return Inertia::render('Finance/Ledger', [
            'year'     => $year,
            'years'    => $this->availableYears(),
            'month'    => $month,
            'accounts' => $accounts,
            'profit'   => ['income' => $pendapatan, 'expense' => $beban, 'net' => $pendapatan - $beban],
        ]);
    }

    // ── Halaman: Rekap Mingguan / Bulanan ─────────────────────────────────────
    public function recap(Request $request)
    {
        $mode = $request->input('mode', 'monthly') === 'weekly' ? 'weekly' : 'monthly';
        $labels = $income = $expense = $net = $rows = [];

        if ($mode === 'weekly') {
            $month = $request->input('month', now()->format('Y-m'));
            [$y, $m] = array_pad(explode('-', $month), 2, now()->month);
            $txns = FinTransaction::whereYear('date', (int) $y)->whereMonth('date', (int) $m)->get();

            $bucket = [];
            foreach ($txns as $t) {
                $w = (int) ceil($t->date->day / 7);
                $bucket[$w]['in']  = ($bucket[$w]['in'] ?? 0) + ($t->direction === 'in' ? (float) $t->amount : 0);
                $bucket[$w]['out'] = ($bucket[$w]['out'] ?? 0) + ($t->direction === 'out' ? (float) $t->amount : 0);
            }
            $maxW = $bucket ? max(array_keys($bucket)) : 4;
            for ($w = 1; $w <= max($maxW, 4); $w++) {
                $in = (float) ($bucket[$w]['in'] ?? 0);
                $out = (float) ($bucket[$w]['out'] ?? 0);
                $labels[] = "Minggu $w"; $income[] = $in; $expense[] = $out; $net[] = $in - $out;
                $rows[] = ['label' => "Minggu $w", 'income' => $in, 'expense' => $out, 'net' => $in - $out];
            }
            $periodLabel = Carbon::create((int) $y, (int) $m, 1)->translatedFormat('F Y');
        } else {
            $year = (int) $request->input('year', now()->year);
            for ($mo = 1; $mo <= 12; $mo++) {
                $in  = (float) FinTransaction::whereYear('date', $year)->whereMonth('date', $mo)->where('direction', 'in')->sum('amount');
                $out = (float) FinTransaction::whereYear('date', $year)->whereMonth('date', $mo)->where('direction', 'out')->sum('amount');
                $lab = Carbon::create($year, $mo, 1)->translatedFormat('M');
                $labels[] = $lab; $income[] = $in; $expense[] = $out; $net[] = $in - $out;
                $rows[] = ['label' => Carbon::create($year, $mo, 1)->translatedFormat('F'), 'income' => $in, 'expense' => $out, 'net' => $in - $out];
            }
            $periodLabel = (string) $year;
        }

        return Inertia::render('Finance/Recap', [
            'mode'          => $mode,
            'periodLabel'   => $periodLabel,
            'year'          => (int) $request->input('year', now()->year),
            'month'         => $request->input('month', now()->format('Y-m')),
            'years'         => $this->availableYears(),
            'labels'        => $labels,
            'incomeSeries'  => $income,
            'expenseSeries' => $expense,
            'netSeries'     => $net,
            'rows'          => $rows,
            'totals'        => ['income' => array_sum($income), 'expense' => array_sum($expense), 'net' => array_sum($net)],
        ]);
    }

    // ── Halaman: Saldo per Akun/Pos (Kas, Bank, Piutang, Hutang) ──────────────
    public function accountBalances()
    {
        $accounts = CashAccount::orderBy('sort_order')->orderBy('id')->get()->map(function ($a) {
            $in    = (float) $a->transactions()->where('direction', 'in')->sum('amount');
            $out   = (float) $a->transactions()->where('direction', 'out')->sum('amount');
            $count = $a->transactions()->count();
            return [
                'name'    => $a->name,
                'type'    => $a->type,
                'opening' => (float) $a->opening_balance,
                'masuk'   => $in,
                'keluar'  => $out,
                'saldo'   => (float) $a->opening_balance + $in - $out,
                'count'   => $count,
            ];
        });

        $ar = (float) Invoice::sum('total') - (float) InvoicePayment::sum('amount');
        $ap = (float) Bill::sum('amount') - (float) BillPayment::sum('amount');

        return Inertia::render('Finance/AccountBalances', [
            'accounts'  => $accounts,
            'cashTotal' => (float) $accounts->sum('saldo'),
            'ar'        => $ar,
            'ap'        => $ap,
        ]);
    }

    // ── Halaman: Neraca (Balance Sheet) per tahun ─────────────────────────────
    public function balanceSheet(Request $request)
    {
        $year    = (int) $request->input('year', now()->year);
        $endDate = "{$year}-12-31";

        // ASET — Kas & Bank per akun (saldo s/d akhir tahun)
        $cashAccounts = CashAccount::orderBy('sort_order')->orderBy('id')->get()->map(function ($a) use ($endDate) {
            $in  = (float) FinTransaction::where('cash_account_id', $a->id)->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');
            $out = (float) FinTransaction::where('cash_account_id', $a->id)->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
            return ['name' => $a->name, 'type' => $a->type, 'balance' => (float) $a->opening_balance + $in - $out];
        });
        $cashTotal = (float) $cashAccounts->sum('balance');

        // Piutang (AR) & Hutang (AP) — outstanding s/d akhir tahun
        $ar = (float) Invoice::where('date', '<=', $endDate)->sum('total')
            - (float) InvoicePayment::where('date', '<=', $endDate)->sum('amount');
        $ap = (float) Bill::where('date', '<=', $endDate)->sum('amount')
            - (float) BillPayment::where('date', '<=', $endDate)->sum('amount');

        $asetTotal      = $cashTotal + $ar;
        $kewajibanTotal = $ap;

        // EKUITAS — modal awal + laba ditahan (akrual, s/d akhir tahun)
        $modal        = (float) CashAccount::sum('opening_balance');
        $invoicedRev  = (float) Invoice::where('date', '<=', $endDate)->sum('total');
        $billedCost   = (float) Bill::where('date', '<=', $endDate)->sum('amount');
        $manualIncome = (float) FinTransaction::where('source', 'manual')->where('direction', 'in')->where('date', '<=', $endDate)->sum('amount');
        $manualExpense = (float) FinTransaction::where('source', 'manual')->where('direction', 'out')->where('date', '<=', $endDate)->sum('amount');
        $labaDitahan  = ($invoicedRev + $manualIncome) - ($billedCost + $manualExpense);
        $ekuitasTotal = $modal + $labaDitahan;

        return Inertia::render('Finance/BalanceSheet', [
            'year'      => $year,
            'years'     => $this->availableYears(),
            'aset'      => ['cash' => $cashAccounts, 'ar' => $ar, 'total' => $asetTotal],
            'kewajiban' => ['ap' => $ap, 'total' => $kewajibanTotal],
            'ekuitas'   => ['modal' => $modal, 'laba_ditahan' => $labaDitahan, 'total' => $ekuitasTotal],
            'balanced'  => abs($asetTotal - ($kewajibanTotal + $ekuitasTotal)) < 1,
        ]);
    }

    private function balanceBefore(Carbon $date): float
    {
        $opening = (float) CashAccount::sum('opening_balance');
        $in  = (float) FinTransaction::where('date', '<', $date)->where('direction', 'in')->sum('amount');
        $out = (float) FinTransaction::where('date', '<', $date)->where('direction', 'out')->sum('amount');

        return $opening + $in - $out;
    }

    private function availableYears(): array
    {
        $min = FinTransaction::min('date');
        $start = $min ? (int) Carbon::parse($min)->year : now()->year;
        $years = range(now()->year, min($start, now()->year));

        return array_values(array_unique($years));
    }
}
