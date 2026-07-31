<?php

namespace App\Services\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

/**
 * Menyediakan aritmetika total yang sama untuk semua jenis, plus helper
 * penurunan pengali dari data tour. Subclass cukup mengisi unitPriceLabel()
 * dan defaultMultipliers().
 */
abstract class BaseSalesLineRule implements SalesLineInvoiceRule
{
    public function calculateTotal(float $unitPrice, array $multipliers): float
    {
        $product = 1;

        foreach ($multipliers as $multiplier) {
            $product *= $multiplier->value;
        }

        // Tanpa round(): kolom decimal(15,2) yang membulatkan saat disimpan,
        // persis seperti rumus lama unit_price × pax.
        return $unitPrice * $product;
    }

    /** Mayoritas jenis: profit per item. TourRule menimpanya. */
    public function profitFromRevenue(): bool
    {
        return false;
    }

    /** Mayoritas jenis: satu harga satuan dikali kuantitas. */
    public function totalComposition(): string
    {
        return 'per_unit';
    }

    /** Mayoritas jenis menyusun paketnya di tour_items sebelum invoice dibuat. */
    public function costingSource(): string
    {
        return 'tour_items';
    }

    /** Ukuran rombongan, minimal 1. Sumber sama dengan rumus lama. */
    protected function paxOf(Invoice $invoice): int
    {
        return max((int) ($invoice->tour?->pax ?? $invoice->pax ?? 1), 1);
    }

    /** Jumlah hari inklusif dari rentang tanggal tour, minimal 1. */
    protected function daysOf(Invoice $invoice): int
    {
        $start = $invoice->tour?->start_date;
        $end   = $invoice->tour?->end_date;

        if (! $start || ! $end) {
            return 1;
        }

        return max((int) $start->diffInDays($end) + 1, 1);
    }

    /** Jumlah malam dari rentang tanggal tour (selisih hari), minimal 1. */
    protected function nightsOf(Invoice $invoice): int
    {
        $start = $invoice->tour?->start_date;
        $end   = $invoice->tour?->end_date;

        if (! $start || ! $end) {
            return 1;
        }

        return max((int) $start->diffInDays($end), 1);
    }
}
