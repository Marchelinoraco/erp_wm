<?php

namespace Tests\Support;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

/**
 * Pengganti SalesLineRuleRegistry di container. Tidak meng-extend kelas
 * aslinya (yang `final`); container mengembalikan apa pun yang di-bind, dan
 * pemanggilnya butuh ->for() dan ->forInvoice() — Invoice::syncProformaTotal()
 * memanggil forInvoice() sejak fitur mode hitung hotel, jadi keduanya harus
 * selalu mengembalikan aturan palsu yang sama.
 *
 * ->payloadForInvoice() juga wajib ada: sejak atribut `rules` dilekatkan ke
 * model Invoice, SETIAP serialisasi invoice memanggilnya — termasuk pada test
 * yang mengikat fake ini dan hanya peduli pada angka, bukan pada payload
 * aturan. Tanpa method ini, respons Inertia-nya gagal dibentuk sama sekali.
 */
final class FakeSalesLineRuleRegistry
{
    public function __construct(private SalesLineInvoiceRule $rule)
    {
    }

    public function for(string $salesLine): SalesLineInvoiceRule
    {
        return $this->rule;
    }

    public function forInvoice(?Invoice $invoice): SalesLineInvoiceRule
    {
        return $this->rule;
    }

    /** Bentuknya cukup selengkap yang dibaca frontend; nilainya dari aturan palsu. */
    public function payloadForInvoice(?Invoice $invoice): array
    {
        return [
            'key'                     => $invoice?->tour?->type ?? 'tour',
            'profitFromRevenue'       => $this->rule->profitFromRevenue(),
            'totalComposition'        => $this->rule->totalComposition(),
            'chargeLinesUseDateRange' => $this->rule->chargeLinesUseDateRange(),
            'pricingModes'            => $this->rule->pricingModes(),
            'pricingMode'             => $invoice?->pricing_mode ?? Invoice::PRICING_PER_PAX,
        ];
    }
}
