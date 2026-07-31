<?php

namespace Tests\Support;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

/**
 * Aturan jenis penjualan palsu untuk test yang perlu membuktikan jalur kode
 * benar-benar melewati registry, bukan menghitung sendiri.
 *
 * Sengaja satu kelas bersama, bukan kelas anonim di dalam test: setiap
 * penambahan method ke SalesLineInvoiceRule hanya perlu diikuti di SATU
 * tempat ini. Kelas anonim yang tersebar akan pecah dengan fatal error, bukan
 * kegagalan assertion yang menjelaskan diri.
 */
final class FakeSalesLineRule implements SalesLineInvoiceRule
{
    public function __construct(
        private float $totalMultiplier = 1.0,
        private bool $profitFromRevenue = false,
        private string $totalComposition = 'per_unit',
        private string $costingSource = 'tour_items',
    ) {
    }

    public function unitPriceLabel(): string
    {
        return 'Harga palsu';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [];
    }

    /** Mengabaikan pengali sungguhan supaya efeknya tak mungkin tertukar. */
    public function calculateTotal(float $unitPrice, array $multipliers): float
    {
        return $unitPrice * $this->totalMultiplier;
    }

    public function profitFromRevenue(): bool
    {
        return $this->profitFromRevenue;
    }

    public function totalComposition(): string
    {
        return $this->totalComposition;
    }

    public function costingSource(): string
    {
        return $this->costingSource;
    }
}
