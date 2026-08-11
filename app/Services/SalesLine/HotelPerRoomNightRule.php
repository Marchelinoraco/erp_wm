<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Hotel mode kamar: harga per kamar per malam. Total disusun dari baris rincian kamar. */
final class HotelPerRoomNightRule extends BaseSalesLineRule
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

    /**
     * Satu invoice hotel bisa memuat beberapa tipe kamar dengan periode dan
     * harga masing-masing, jadi totalnya dijumlah dari baris rincian — bukan
     * satu harga satuan dikali kuantitas.
     */
    public function totalComposition(): string
    {
        return 'line_items';
    }

    /** Yang pertama dicari customer adalah periode menginapnya, baru tipe kamarnya. */
    public function chargeLinesDateFirstInPdf(): bool
    {
        return true;
    }

    /** Menginap berjalan dari tanggal check-in sampai check-out. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }

    public function pricingModes(): array
    {
        return Invoice::PRICING_MODES;
    }
}
