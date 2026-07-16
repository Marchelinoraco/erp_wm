<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nomori ulang SEMUA invoice yang sudah ada mengikuti skema baru
 * (INV-<tahun>-<kode tipe>-NNNN, urut berdasarkan kapan invoice dibuat)
 * — menggantikan skema lama yang mengikuti kode tour. Dua tahap dalam satu
 * transaksi (kosongkan dulu ke placeholder, baru isi nomor final) supaya
 * tidak tabrakan dengan unique constraint (format lama & baru bisa sama persis).
 */
class RenumberInvoices extends Command
{
    protected $signature = 'invoices:renumber';

    protected $description = 'Nomori ulang semua invoice per tipe penjualan & tahun, urut berdasarkan waktu dibuat';

    public function handle(): int
    {
        $invoices  = Invoice::with('tour')->orderBy('created_at')->orderBy('id')->get();
        $oldNumbers = $invoices->pluck('number', 'id'); // catat nomor asli sebelum diubah, utk log

        DB::transaction(function () use ($invoices, $oldNumbers) {
            // Tahap 1: kosongkan ke placeholder unik — bebaskan ruang nomor lama
            foreach ($invoices as $inv) {
                $inv->forceFill(['number' => 'TMP-' . $inv->id])->saveQuietly();
            }

            // Tahap 2: isi nomor final berurutan per (tahun, kode tipe)
            $counters = [];
            foreach ($invoices as $inv) {
                $typeCode = $inv->tour?->resolveTypeCode() ?? '11';
                $year     = $inv->created_at->year;
                $key      = $year . '-' . $typeCode;
                $counters[$key] = ($counters[$key] ?? 0) + 1;

                $number = 'INV-' . $year . '-' . $typeCode . '-' . str_pad($counters[$key], 4, '0', STR_PAD_LEFT);
                $inv->forceFill(['number' => $number])->saveQuietly();

                $this->line("  {$oldNumbers[$inv->id]} → {$number}");
            }
        });

        $this->info("Selesai. {$invoices->count()} invoice dinomori ulang.");

        return self::SUCCESS;
    }
}
