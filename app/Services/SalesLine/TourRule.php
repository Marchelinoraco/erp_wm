<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Tour: dijual per orang. */
final class TourRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / pax';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }
}
