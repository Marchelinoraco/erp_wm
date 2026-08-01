<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Payroll;
use App\Services\Payroll\PayrollDraftBuilder;
use App\Services\Payroll\PayrollProcessor;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Spek §4.4/§6: layar Gajian — daftar periode, buka periode (murni hitung,
 * tidak menyimpan), Bayar, Batalkan. Satu-satunya jalan mengubah payroll
 * yang sudah paid adalah Batalkan dulu (lihat docblock PayrollProcessor).
 */
class PayrollController extends Controller
{
    public function index()
    {
        $periods = Payroll::orderByDesc('period')->get(['id', 'period', 'status', 'paid_date']);

        return Inertia::render('Finance/Payrolls', [
            'periods'      => $periods,
            'currentMonth' => now()->format('Y-m'),
        ]);
    }

    public function show(string $period, PayrollDraftBuilder $builder)
    {
        $payroll = Payroll::where('period', $period)->first();

        abort_if($payroll && $payroll->status === 'paid', 409, 'Periode ini sudah dibayar — batalkan dulu untuk mengubahnya.');

        return Inertia::render('Finance/Payrolls', [
            'periods'      => Payroll::orderByDesc('period')->get(['id', 'period', 'status', 'paid_date']),
            'currentMonth' => now()->format('Y-m'),
            'period'       => $period,
            'draft'        => $builder->build($period),
            'cashAccounts' => CashAccount::orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function pay(Request $request, string $period, PayrollDraftBuilder $builder, PayrollProcessor $processor)
    {
        $existing = Payroll::where('period', $period)->first();
        abort_if($existing && $existing->status === 'paid', 422, 'Periode ini sudah dibayar — Batalkan dulu sebelum bayar ulang.');

        // Item penutup review Task 5 (#1): PayrollProcessor::pay() sendiri
        // tidak memvalidasi cash_account_id — ID yang tidak ada di
        // cash_accounts akan lempar QueryException mentah (500) kalau tidak
        // dijaga di sini.
        $data = $request->validate([
            'cash_account_id' => 'required|integer|exists:cash_accounts,id',
        ]);

        // Item penutup review Task 5 (#2): draft SELALU dihitung ulang dari
        // keadaan live di server, tidak pernah dipercaya dari body request —
        // kalau tidak, klien bisa mengirim employee_id/net_amount palsu yang
        // lolos apa adanya ke payroll_items dan jurnal keuangan.
        $draft = $builder->build($period);

        $processor->pay($period, $draft, $data['cash_account_id'], $request->user()?->name);

        return redirect()->route('payrolls.index')->with('success', "Gajian {$period} dibayar.");
    }

    public function cancel(Payroll $payroll, PayrollProcessor $processor)
    {
        // Item penutup review Task 5 (#3): PayrollProcessor::cancel() sendiri
        // no-op aman untuk payroll draft, tapi dari sisi UX user tidak boleh
        // bisa "Batalkan" sesuatu yang belum pernah dibayar.
        abort_if($payroll->status === 'draft', 422, 'Gajian periode ini belum pernah dibayar — tidak ada yang perlu dibatalkan.');

        $processor->cancel($payroll);

        return back()->with('success', 'Gajian dibatalkan, kembali ke draft.');
    }
}
