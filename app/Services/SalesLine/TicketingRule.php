<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Ticketing: per tiket; nilai awal mengikuti jumlah peserta. */
final class TicketingRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / tiket';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('tiket', 'Tiket', $this->paxOf($invoice))];
    }
}
