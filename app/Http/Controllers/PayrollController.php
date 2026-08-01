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
        //
        // D6 (fix round ini): `overrides` adalah jalan SEMPIT dan AMAN untuk
        // admin menurunkan potongan kas bon sebelum bayar (mis. "bulan ini
        // potong separuh dulu") — TIDAK sama dengan celah `items` mentah yang
        // sudah dihapus dari controller ini (lihat komentar di bawah): admin
        // hanya boleh mengirim employee_advance_id -> nominal, tidak pernah
        // employee_id/net_amount/baris lain apa pun.
        $data = $request->validate([
            'cash_account_id'                  => 'required|integer|exists:cash_accounts,id',
            'overrides'                         => 'nullable|array',
            'overrides.*.employee_advance_id'   => 'required_with:overrides|integer|exists:employee_advances,id',
            'overrides.*.potongan'              => 'required_with:overrides|numeric|min:0',
        ]);

        // Item penutup review Task 5 (#2): draft SELALU dihitung ulang dari
        // keadaan live di server, tidak pernah dipercaya dari body request —
        // kalau tidak, klien bisa mengirim employee_id/net_amount palsu yang
        // lolos apa adanya ke payroll_items dan jurnal keuangan.
        $draft = $builder->build($period);

        if (! empty($data['overrides'])) {
            $draft = $this->terapkanOverridesKasBon($draft, $data['overrides']);
        }

        $processor->pay($period, $draft, $data['cash_account_id'], $request->user()?->name);

        return redirect()->route('payrolls.index')->with('success', "Gajian {$period} dibayar.");
    }

    /**
     * D6: kas bon otomatis (hasil FIFO dari PayrollDraftBuilder) boleh
     * DITURUNKAN admin sebelum bayar, tidak pernah dinaikkan. `$overrides`
     * berisi employee_advance_id -> nominal potongan baru; employee_advance_id
     * yang tidak muncul di baris draft manapun (ID valid tapi tidak relevan
     * untuk periode ini, mis. milik karyawan nonaktif) diabaikan dengan aman.
     * Gross sebelum kas bon (gaji pokok + tunjangan - potongan komponen)
     * tidak pernah berubah — hanya potongan kas bon yang disesuaikan turun.
     */
    private function terapkanOverridesKasBon(array $draft, array $overrides): array
    {
        $overridesByAdvanceId = collect($overrides)->keyBy('employee_advance_id');

        foreach ($draft as &$row) {
            $totalPotonganLama = array_sum(array_column($row['advances'], 'potongan'));
            $totalPotonganBaru = 0.0;

            foreach ($row['advances'] as &$advance) {
                $override = $overridesByAdvanceId->get($advance['employee_advance_id']);
                if ($override) {
                    // HANYA boleh diturunkan — dibatasi tidak pernah melebihi
                    // hasil FIFO otomatis yang sudah menjamin gaji bersih >= 0.
                    $advance['potongan'] = min((float) $override['potongan'], $advance['potongan']);
                }
                $totalPotonganBaru += $advance['potongan'];
            }
            unset($advance);

            $grossSebelumKasBon = $row['net_amount'] + $totalPotonganLama;
            $row['net_amount']  = round($grossSebelumKasBon - $totalPotonganBaru, 2);
        }
        unset($row);

        return $draft;
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
