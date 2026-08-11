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
}
