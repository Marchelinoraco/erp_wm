<?php

namespace App\Console\Commands;

use App\Http\Controllers\FinanceReportController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use ReflectionMethod;

/**
 * Spek §3.6 — jaring pengaman Tahap A.
 *
 * Menulis enam laporan keuangan ke satu berkas JSON supaya angkanya bisa
 * dibandingkan sebelum dan sesudah perubahan kode. Kriteria Tahap A adalah
 * TIDAK ADA satu angka pun yang berubah; perintah ini yang membuktikannya.
 *
 * Method data pada controller bersifat private, jadi dipanggil lewat refleksi.
 * Alternatifnya menjadikan keenamnya public, tetapi method public pada controller
 * Laravel secara konvensi berarti aksi rute.
 */
class FinanceSnapshot extends Command
{
    protected $signature = 'finance:snapshot {tahun? : Tahun laporan, default tahun berjalan} {--out= : Path berkas keluaran}';

    protected $description = 'Tulis enam laporan keuangan ke JSON untuk dibandingkan sebelum/sesudah perubahan';

    public function handle(): int
    {
        $tahun = (int) ($this->argument('tahun') ?: now()->year);
        $out   = $this->option('out') ?: storage_path("app/finance-snapshot-{$tahun}.json");

        $controller = app(FinanceReportController::class);

        $panggil = function (string $method, ...$args) use ($controller) {
            $ref = new ReflectionMethod($controller, $method);
            $ref->setAccessible(true);

            return $ref->invoke($controller, ...$args);
        };

        $data = [
            'tahun'      => $tahun,
            'neraca'     => $panggil('balanceSheetData', $tahun),
            'laba_rugi'  => $panggil('incomeStatementData', $tahun),
            'buku_besar' => $panggil('ledgerData', $tahun, null),
            'arus_kas'   => $panggil('cashFlowData', $tahun),
            'rekap'      => $panggil('recapData', new Request(['mode' => 'monthly', 'year' => $tahun])),
            'saldo_akun' => $panggil('accountBalancesData'),
        ];

        file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Snapshot tahun {$tahun} ditulis ke: {$out}");

        return self::SUCCESS;
    }
}
