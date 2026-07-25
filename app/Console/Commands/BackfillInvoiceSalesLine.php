<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Backfill Fase 2 pemisahan aturan invoice per jenis penjualan — lihat
 * docs/design_pemisahan_invoice_per_jenis_penjualan.md §3.3.1.
 *
 * HANYA mengisi sales_line. billing_quantities SENGAJA dibiarkan null —
 * mengisinya untuk invoice draft yang ada akan membekukan pengali pax pada
 * nilai saat backfill, melanggar sifat "total ikut pax tour" yang dikunci
 * Fase 0 (PaxSourceCharacterizationTest::test_mengubah_pax_tour_menggeser_total_invoice_draft).
 *
 * TIDAK PERNAH memanggil syncProformaTotal() — itu akan menulis ulang total
 * & pax, termasuk pada invoice yang sudah disetujui, karena syncProformaTotal()
 * sendiri tidak dijaga is_approved (§7.4). Perintah ini hanya menulis kolom
 * sales_line lewat update() langsung.
 *
 * Idempoten: hanya memproses baris sales_line IS NULL. Aman dijalankan
 * berkali-kali bila terputus di tengah jalan (§7.7).
 */
class BackfillInvoiceSalesLine extends Command
{
    protected $signature = 'invoices:backfill-sales-line';

    protected $description = 'Isi kolom sales_line dari tipe tour untuk invoice lama (billing_quantities sengaja tidak diisi)';

    public function handle(): int
    {
        // Cakupan default (tanpa withTrashed): audit data production
        // menunjukkan 0 invoice ter-soft-delete saat ini, dan tidak ada
        // kebutuhan menyertakannya — sales_line hanya dipakai jalur hitung
        // yang juga tidak menyentuh invoice terhapus.
        $invoices = Invoice::whereNull('sales_line')
            ->with('tour')
            ->get();

        foreach ($invoices as $invoice) {
            $salesLine = $invoice->tour?->type ?? 'tour';

            // update() langsung — BUKAN syncProformaTotal(). Tidak menyentuh
            // total, total_idr, pax, unit_price, atau billing_quantities.
            $invoice->update(['sales_line' => $salesLine]);
        }

        $this->info("Selesai. {$invoices->count()} invoice di-backfill sales_line-nya.");

        return self::SUCCESS;
    }
}
