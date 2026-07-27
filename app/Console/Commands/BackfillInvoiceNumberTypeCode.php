<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Backfill kode tipe penjualan pada invoice.number yang masih format lama
 * (INV-<tahun>-NNNN, invoice dibuat sebelum 16 Jul 2026) — lihat
 * docs/superpowers/specs/2026-07-27-satu-nomor-invoice-design.md §3.
 *
 * HANYA memproses number berformat LAMA (2 tanda hubung: INV-tahun-NNNN).
 * Invoice yang number-nya SUDAH berkode tipe (3 tanda hubung, format
 * INV-tahun-tipe-NNNN) tidak pernah tersentuh — deteksinya lewat jumlah
 * tanda hubung, bukan tanggal, supaya presisi dan tidak ada yang lolos.
 *
 * Nomor baru diberikan di UJUNG urutan (tahun, tipe) yang ADA SEKARANG —
 * TIDAK disisipkan berdasarkan kapan invoice sebenarnya dibuat. Ini
 * disengaja: menyisipkan kronologis akan menggeser nomor yang SUDAH
 * stabil dan mungkin sudah dikirim ke customer lewat PDF — dilarang
 * keras (§2.5 spec).
 *
 * Idempoten: pass kedua tidak menemukan invoice format lama lagi (semua
 * sudah 3 tanda hubung setelah pass pertama), jadi tidak melakukan apa pun.
 */
class BackfillInvoiceNumberTypeCode extends Command
{
    protected $signature = 'invoices:backfill-number-type-code';

    protected $description = 'Tambahkan kode tipe penjualan ke invoice.number format lama, tanpa mengubah nomor yang sudah berkode tipe';

    public function handle(): int
    {
        // Format lama = 2 tanda hubung (INV-tahun-NNNN). Format baru = 3
        // tanda hubung (INV-tahun-tipe-NNNN). withTrashed() supaya invoice
        // yang sudah soft-delete pun tercakup — konsisten dengan
        // Invoice::nextNumber()/nextFinanceNumber() yang juga withTrashed()
        // saat mencari nomor terakhir.
        $legacy = Invoice::withTrashed()
            ->whereRaw("(LENGTH(number) - LENGTH(REPLACE(number, '-', ''))) = 2")
            ->with('tour')
            ->orderBy('created_at')
            ->get();

        $count = 0;
        foreach ($legacy as $invoice) {
            $matches = [];
            // Tahun diambil dari number LAMA milik invoice itu sendiri, BUKAN
            // now()->year — invoice ini historis, mungkin dibuat di tahun lalu.
            if (! preg_match('/^INV-(\d{4})-\d+$/', $invoice->number, $matches)) {
                continue; // Format tak dikenal — dilewati, tidak dipaksakan.
            }
            $year     = $matches[1];
            $typeCode = $invoice->tour?->resolveTypeCode() ?? '11';

            $prefix = "INV-{$year}-{$typeCode}-";
            $latest = Invoice::withTrashed()
                ->where('number', 'like', $prefix . '%')
                ->orderByDesc('number')
                ->value('number');
            $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

            $invoice->update(['number' => $prefix . str_pad($next, 4, '0', STR_PAD_LEFT)]);
            $count++;
        }

        $this->info("Selesai. {$count} invoice number di-backfill dengan kode tipe.");

        return self::SUCCESS;
    }
}
