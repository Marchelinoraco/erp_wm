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

    /**
     * Sama seperti mode pax (dan tipe `tour`): tagihan customer = Σ baris kamar,
     * modal ada di Rincian Profit invoice. Profit = tagihan − Σ modal item.
     */
    public function profitFromRevenue(): bool
    {
        return true;
    }

    /** Dokumen acuan hotel: pasangan "Hotel / Room" dan "Price". */
    public function chargeLineLayout(): string
    {
        return 'hotel_room';
    }

    public function usesCompactDateInPdf(): bool
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
