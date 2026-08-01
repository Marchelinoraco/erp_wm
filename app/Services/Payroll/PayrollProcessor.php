<?php

namespace App\Services\Payroll;

use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;

/**
 * Spek §4.2/§4.4/D9/D12: satu-satunya titik yang menulis payroll_items,
 * payroll_item_lines, dan jurnal. "Bayar" mengunci berkas (paid) — tidak
 * ada endpoint update setelahnya. Satu-satunya jalan mengubah adalah
 * cancel() lalu pay() lagi.
 */
class PayrollProcessor
{
    public function pay(string $period, array $items, int $cashAccountId, ?string $createdBy): Payroll
    {
        return DB::transaction(function () use ($period, $items, $cashAccountId, $createdBy) {
            $payroll = Payroll::updateOrCreate(
                ['period' => $period],
                ['status' => 'paid', 'paid_date' => now()->toDateString(), 'cash_account_id' => $cashAccountId, 'created_by' => $createdBy]
            );

            // Bersihkan item lama — menutup celah "Bayar setelah Batalkan"
            // (period sudah ada, item lama dari siklus sebelumnya harus diganti).
            $payroll->items()->delete();

            $totalTunai = 0.0;
            $totalPelunasanKasBon = 0.0;

            foreach ($items as $row) {
                $item = $payroll->items()->create([
                    'employee_id'   => $row['employee_id'],
                    'employee_name' => $row['employee_name'],
                    'position'      => $row['position'],
                    'base_salary'   => $row['base_salary'],
                    'net_amount'    => $row['net_amount'],
                ]);

                foreach ($row['lines'] as $l) {
                    $item->lines()->create(['kind' => $l['kind'], 'label' => $l['label'], 'amount' => $l['amount']]);
                }

                foreach ($row['advances'] as $a) {
                    if ($a['potongan'] <= 0.009) continue;

                    $item->lines()->create([
                        'kind' => 'kas_bon', 'label' => $a['label'], 'amount' => $a['potongan'],
                        'employee_advance_id' => $a['employee_advance_id'],
                    ]);
                    $totalPelunasanKasBon += $a['potongan'];
                }

                $totalTunai += $row['net_amount'];
            }

            $gajiKategori = FinCategory::where('name', 'Gaji Karyawan')->firstOrFail();

            if ($totalTunai > 0.009) {
                FinTransaction::create([
                    'date' => $payroll->paid_date, 'direction' => 'out',
                    'fin_category_id' => $gajiKategori->id, 'cash_account_id' => $cashAccountId,
                    'amount' => round($totalTunai, 2), 'description' => "Gajian {$period}",
                    'source' => 'payroll', 'source_id' => $payroll->id, 'created_by' => $createdBy,
                ]);
            }

            if ($totalPelunasanKasBon > 0.009) {
                $piutangKategori = FinCategory::where('name', 'Piutang Karyawan')->firstOrFail();

                FinTransaction::create([
                    'date' => $payroll->paid_date, 'direction' => 'out',
                    'fin_category_id' => $gajiKategori->id, 'contra_fin_category_id' => $piutangKategori->id,
                    'amount' => round($totalPelunasanKasBon, 2), 'description' => "Pelunasan kas bon — Gajian {$period}",
                    'source' => 'payroll', 'source_id' => $payroll->id, 'created_by' => $createdBy,
                ]);
            }

            return $payroll->fresh();
        });
    }

    public function cancel(Payroll $payroll): void
    {
        DB::transaction(function () use ($payroll) {
            FinTransaction::where('source', 'payroll')->where('source_id', $payroll->id)->get()
                ->each(fn ($trx) => $trx->delete());

            $payroll->items()->delete();
            $payroll->update(['status' => 'draft', 'paid_date' => null]);
        });
    }
}
