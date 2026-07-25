<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** MICE: harga paket dikali jumlah peserta. */
final class MiceRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga paket / peserta';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }
}
