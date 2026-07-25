<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Jasa Guide: per hari, dari rentang tanggal tour. */
final class GuideRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / hari';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('hari', 'Hari', $this->daysOf($invoice))];
    }
}
