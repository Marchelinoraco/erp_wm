<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Rentang tanggal ringkas gaya voucher hotel: `1-2 Aug 2026`.
 *
 * Bulan dan tahun hanya ditulis sekali bila kedua ujungnya sama, sehingga
 * baris Date tetap pendek pada kasus yang paling sering — menginap beberapa
 * malam dalam bulan yang sama.
 */
final class CompactDateRange
{
    public static function format(?Carbon $start, ?Carbon $end): string
    {
        if (! $start) {
            return '';
        }

        if (! $end || $end->isSameDay($start)) {
            return $start->format('j M Y');
        }

        if ($start->year !== $end->year) {
            return $start->format('j M Y') . ' - ' . $end->format('j M Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('j M') . ' - ' . $end->format('j M Y');
        }

        return $start->format('j') . '-' . $end->format('j M Y');
    }
}
