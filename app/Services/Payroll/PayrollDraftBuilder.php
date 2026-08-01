<?php

namespace App\Services\Payroll;

use App\Models\Employee;

/**
 * Spek §4.4/D6: menghitung draft gajian dari keadaan LIVE, tidak pernah
 * menyimpan hasilnya. Dipanggil ulang setiap periode dibuka (bukan disimpan
 * ke DB) — kas bon baru otomatis ikut, angka baru dibekukan hanya saat
 * PayrollProcessor::pay() (Task 5) benar-benar menulis payroll_items.
 */
class PayrollDraftBuilder
{
    public function build(string $period): array
    {
        return Employee::where('is_active', true)
            ->with('components', 'advances')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $e) => $this->buildRow($e))
            ->values()
            ->all();
    }

    private function buildRow(Employee $e): array
    {
        $tunjangan = $e->components->where('type', 'tunjangan')->where('is_active', true);
        $potongan  = $e->components->where('type', 'potongan')->where('is_active', true);

        $tunjanganTotal = (float) $tunjangan->sum('amount');
        $potonganTotal  = (float) $potongan->sum('amount');
        $grossSebelumKasBon = max((float) $e->base_salary + $tunjanganTotal - $potonganTotal, 0.0);

        [$advanceLines, $totalPotonganKasBon] = $this->hitungPotonganKasBon($e, $grossSebelumKasBon);

        $lines = $tunjangan->map(fn ($c) => ['kind' => 'tunjangan', 'label' => $c->name, 'amount' => (float) $c->amount])
            ->concat($potongan->map(fn ($c) => ['kind' => 'potongan', 'label' => $c->name, 'amount' => (float) $c->amount]))
            ->values()->all();

        return [
            'employee_id'   => $e->id,
            'employee_name' => $e->name,
            'position'      => $e->position,
            'base_salary'   => (float) $e->base_salary,
            'lines'         => $lines,
            'advances'      => $advanceLines,
            'net_amount'    => round($grossSebelumKasBon - $totalPotonganKasBon, 2),
        ];
    }

    /**
     * D6: potongan otomatis = yang lebih kecil antara sisa kas bon dan gaji
     * bersih. Dibagi FIFO antar kas bon (yang paling lama diambil dilunasi
     * lebih dulu) bila karyawan punya lebih dari satu kas bon terbuka.
     */
    private function hitungPotonganKasBon(Employee $e, float $anggaran): array
    {
        $sisaPerKasBon = $e->advances->sortBy('date')->map(fn ($a) => [
            'advance' => $a, 'sisa' => $a->sisa(),
        ])->filter(fn ($x) => $x['sisa'] > 0.009)->values();

        $lines = [];
        $totalDipotong = 0.0;

        foreach ($sisaPerKasBon as $x) {
            if ($anggaran <= 0.009) break;

            $potongan = round(min($x['sisa'], $anggaran), 2);
            $lines[] = [
                'employee_advance_id' => $x['advance']->id,
                'label'               => 'Kas bon ' . $x['advance']->date->translatedFormat('d M Y'),
                'sisa'                => $x['sisa'],
                'potongan'            => $potongan,
            ];

            $anggaran      -= $potongan;
            $totalDipotong += $potongan;
        }

        return [$lines, $totalDipotong];
    }
}
