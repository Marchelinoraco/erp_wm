<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Hotel: harga per kamar per malam. Satu-satunya jenis dengan dua pengali. */
final class HotelRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / kamar / malam';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [
            new Multiplier('rooms', 'Kamar', 1),
            new Multiplier('nights', 'Malam', $this->nightsOf($invoice)),
        ];
    }

    /** Menginap berjalan dari tanggal check-in sampai check-out. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }
}
