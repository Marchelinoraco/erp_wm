<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/**
 * Transport (sewa mobil/kapal): per hari. Disimpan sebagai `rental` di kolom
 * tours.type — lihat SalesLineRuleRegistry.
 */
final class TransportRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / hari';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('hari', 'Hari', $this->daysOf($invoice))];
    }

    /**
     * Rental menagih beberapa unit berbeda dengan harga masing-masing, jadi
     * totalnya dijumlah dari baris bernominal — bukan satu harga × kuantitas.
     * unit_price diabaikan untuk jenis ini.
     */
    public function totalComposition(): string
    {
        return 'line_items';
    }
}
