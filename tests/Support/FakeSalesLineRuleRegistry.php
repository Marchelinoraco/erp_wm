<?php

namespace Tests\Support;

use App\Contracts\SalesLineInvoiceRule;

/**
 * Pengganti SalesLineRuleRegistry di container. Tidak meng-extend kelas
 * aslinya (yang `final`); container mengembalikan apa pun yang di-bind, dan
 * pemanggilnya hanya butuh ->for().
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
}
