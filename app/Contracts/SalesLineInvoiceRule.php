<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Services\SalesLine\Multiplier;

/**
 * Aturan hitung tagihan untuk satu jenis penjualan.
 *
 * Tiap jenis (tour, hotel, guide, transport, mice, document, ticketing) punya
 * satu implementasi. Mengubah cara hitung satu jenis berarti menyentuh satu
 * berkas — jenis lain tidak terpengaruh.
 */
interface SalesLineInvoiceRule
{
    /** Label kolom harga di form & PDF, mis. "Harga / kamar / malam". */
    public function unitPriceLabel(): string;

    /**
     * Pengali beserta nilai AWAL saat invoice dibuat, diturunkan dari data tour
     * (pax atau rentang tanggal). Belum dipakai di jalur produksi pada Fase 1;
     * dialirkan lewat backfill dan store pada Fase 2.
     *
     * @return Multiplier[]
     */
    public function defaultMultipliers(Invoice $invoice): array;

    /** total = unit_price × hasil kali seluruh nilai Multiplier. Tanpa pembulatan. */
    public function calculateTotal(float $unitPrice, array $multipliers): float;
}
