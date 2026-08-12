<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/**
 * Hotel mode pax: satu harga dikali jumlah peserta — cara hitung yang selama
 * ini benar-benar berjalan untuk hotel, dan tetap menjadi default agar seluruh
 * invoice lama (pricing_mode NULL) tidak berubah nominalnya.
 */
final class HotelPerPaxRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / pax';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }

    /** Menginap berjalan dari tanggal check-in sampai check-out, sama seperti mode kamar. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }

    public function pricingModes(): array
    {
        return Invoice::PRICING_MODES;
    }
}
