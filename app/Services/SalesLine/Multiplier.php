<?php

namespace App\Services\SalesLine;

/**
 * Satu pengali tagihan beserta namanya, mis. pax=10 atau malam=4.
 *
 * `key` dipakai untuk menyimpan di billing_quantities (Fase 2), `label` untuk
 * ditampilkan di UI (Fase 3), `value` untuk perhitungan.
 */
final class Multiplier
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $value,
    ) {
    }
}
