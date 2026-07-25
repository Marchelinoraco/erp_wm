<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Document (visa/paspor): per dokumen; nilai awal mengikuti jumlah peserta. */
final class DocumentRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / dokumen';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('dokumen', 'Dokumen', $this->paxOf($invoice))];
    }
}
