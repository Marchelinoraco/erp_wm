<?php

namespace App\Services\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

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
            // Hotel punya dua cara hitung. Yang terdaftar di sini adalah
            // default-nya (pricing_mode NULL); forInvoice() memilih kelas
            // satunya saat invoice memintanya.
            'hotel'     => new HotelPerPaxRule(),
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

    /**
     * Aturan untuk satu invoice — memperhitungkan cara hitung yang dipilih
     * sales, bukan hanya jenis penjualannya.
     *
     * Inilah satu-satunya tempat mode diterjemahkan menjadi kelas aturan.
     * Mode tak dikenal dan invoice yang tidak ada sama-sama jatuh ke perilaku
     * yang sudah berjalan, sehingga data nyasar tidak pernah menggeser nominal.
     */
    public function forInvoice(?Invoice $invoice): SalesLineInvoiceRule
    {
        $rule = $this->for($invoice?->tour?->type ?? 'tour');

        if ($rule instanceof HotelPerPaxRule && $invoice?->pricing_mode === Invoice::PRICING_PER_ROOM_NIGHT) {
            return new HotelPerRoomNightRule();
        }

        return $rule;
    }

    /**
     * Payload aturan milik SATU invoice. Mode adalah milik invoice, bukan
     * tour, dan satu tour bisa memuat beberapa invoice dengan mode berbeda —
     * jadi payloadFor() tingkat tour tidak cukup.
     *
     * @return array{key: string, profitFromRevenue: bool, totalComposition: string, chargeLinesUseDateRange: bool, pricingModes: string[], pricingMode: string}
     */
    public function payloadForInvoice(?Invoice $invoice): array
    {
        $key  = $invoice?->tour?->type ?? 'tour';
        $rule = $this->forInvoice($invoice);

        return [
            'key'                     => $key,
            'profitFromRevenue'       => $rule->profitFromRevenue(),
            'totalComposition'        => $rule->totalComposition(),
            'chargeLinesUseDateRange' => $rule->chargeLinesUseDateRange(),
            'pricingModes'            => $rule->pricingModes(),
            // NULL dilaporkan sebagai per_pax supaya frontend tidak perlu tahu
            // bahwa ketiadaan nilai berarti mode default.
            'pricingMode'             => $invoice?->pricing_mode ?? Invoice::PRICING_PER_PAX,
        ];
    }

    /**
     * Bentuk prop Inertia `salesLine` untuk halaman yang menampilkan angka
     * uang per jenis penjualan. Satu tempat, supaya menambah properti tidak
     * berarti menyunting setiap controller yang mengirimnya.
     *
     * Hanya properti yang benar-benar dikonsumsi frontend yang masuk sini —
     * `unitPriceLabel()` sengaja TIDAK dikirim, satuannya masih ditunda.
     *
     * @return array{key: string, profitFromRevenue: bool, totalComposition: string, chargeLinesUseDateRange: bool}
     */
    public function payloadFor(?string $salesLine): array
    {
        $key  = $salesLine ?? 'tour';
        $rule = $this->for($key);

        return [
            'key'                     => $key,
            'profitFromRevenue'       => $rule->profitFromRevenue(),
            'totalComposition'        => $rule->totalComposition(),
            'chargeLinesUseDateRange' => $rule->chargeLinesUseDateRange(),
        ];
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->rules);
    }
}
