<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Backfill satu kali: catat item Rincian Profit bersupplier dari invoice yang
 * SUDAH disetujui sebelum fitur auto-bill aktif. Aman dijalankan berkali-kali
 * (idempotent lewat Bill::createMissingFromInvoice).
 */
class BackfillSupplierBills extends Command
{
    protected $signature = 'bills:backfill-supplier';

    protected $description = 'Buat Bill draft untuk item Rincian Profit bersupplier pada invoice yang sudah disetujui';

    public function handle(): int
    {
        $invoices = Invoice::approved()->with('items.product.supplier')->get();
        $total    = 0;

        foreach ($invoices as $invoice) {
            $created = Bill::createMissingFromInvoice($invoice);
            $total  += $created;

            if ($created > 0) {
                $this->line("  {$invoice->number}: {$created} bill dibuat");
            }
        }

        $this->info("Selesai. {$total} bill baru dibuat dari {$invoices->count()} invoice approved.");

        return self::SUCCESS;
    }
}
