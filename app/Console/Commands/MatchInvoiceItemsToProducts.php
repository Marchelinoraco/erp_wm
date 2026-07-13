<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Item Rincian Profit yang diinput lewat "Tempel" (paste massal) tidak punya
 * product_id, jadi supplier-nya tidak terdeteksi otomatis. Command ini
 * mencocokkan deskripsi item ke nama produk (sama persis, tanpa spasi
 * berlebih, tanpa peduli besar-kecil huruf) — hanya mengisi product_id bila
 * cocok dengan TEPAT SATU produk (nama ganda/ambigu dilewati demi keamanan
 * data supplier). Tidak mengubah unit_cost/unit_sell/line_cost/line_sell
 * yang sudah tersimpan di item — murni menautkan metadata produk.
 *
 * Setelah menautkan, otomatis menjalankan bills:backfill-supplier supaya
 * item yang baru tertaut & bersupplier langsung tercatat sebagai Bill draft.
 */
class MatchInvoiceItemsToProducts extends Command
{
    protected $signature = 'items:match-products {--dry-run : Tampilkan hasil tanpa menyimpan perubahan}';

    protected $description = 'Tautkan item Rincian Profit lama (dari Tempel) ke Produk berdasarkan nama persis';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $productsByName = Product::all()->groupBy(fn ($p) => mb_strtolower(trim($p->name)));
        $items          = InvoiceItem::whereNull('product_id')->get();

        $matched = $ambiguous = $unmatched = 0;
        $affectedInvoiceIds = [];

        foreach ($items as $item) {
            $key   = mb_strtolower(trim($item->description));
            $group = $productsByName->get($key);

            if (! $group || $group->isEmpty()) {
                $unmatched++;
                continue;
            }

            if ($group->count() > 1) {
                $ambiguous++;
                $this->warn("  Ambigu ({$group->count()} produk bernama sama): \"{$item->description}\" — dilewati");
                continue;
            }

            $product = $group->first();
            $matched++;
            $this->line("  #{$item->id} \"{$item->description}\" → produk #{$product->id}" . ($product->supplier_id ? " (supplier: {$product->supplier?->name})" : ' (tanpa supplier)'));

            if (! $dryRun) {
                $item->update([
                    'product_id'   => $product->id,
                    'product_type' => $item->product_type ?: $product->type,
                ]);
                $affectedInvoiceIds[] = $item->invoice_id;
            }
        }

        $this->newLine();
        $this->info("Cocok: {$matched} · Ambigu (dilewati): {$ambiguous} · Tidak cocok: {$unmatched}" . ($dryRun ? ' [DRY RUN — tidak disimpan]' : ''));

        if (! $dryRun && $matched > 0) {
            $created = 0;
            foreach (\App\Models\Invoice::approved()->whereIn('id', array_unique($affectedInvoiceIds))->get() as $invoice) {
                $created += Bill::createMissingFromInvoice($invoice);
            }
            $this->info("{$created} bill baru dibuat dari item yang baru tertaut.");
        }

        return self::SUCCESS;
    }
}
