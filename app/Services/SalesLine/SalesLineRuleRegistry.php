<?php

namespace App\Services\SalesLine;

use App\Contracts\SalesLineInvoiceRule;

/**
 * Memetakan nilai tours.type ke aturan hitungnya. Satu-satunya tempat jenis
 * penjualan dipilih — tidak ada percabangan `if type ===` di controller/model.
 */
final class SalesLineRuleRegistry
{
    /** @var array<string, SalesLineInvoiceRule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            'tour'      => new TourRule(),
            'hotel'     => new HotelRule(),
            'guide'     => new GuideRule(),
            // tours.type menyimpan 'rental' untuk penjualan Transport.
            'rental'    => new TransportRule(),
            'mice'      => new MiceRule(),
            'document'  => new DocumentRule(),
            'ticketing' => new TicketingRule(),
        ];
    }

    /** Jenis tak dikenal jatuh ke aturan tour (perilaku pax) agar tak ada type yang menggagalkan hitung. */
    public function for(string $salesLine): SalesLineInvoiceRule
    {
        return $this->rules[$salesLine] ?? $this->rules['tour'];
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->rules);
    }
}
