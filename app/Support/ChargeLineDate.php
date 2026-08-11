<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Menulis tanggal baris bernominal invoice (baris description_lines yang
 * punya `amount`).
 *
 * Nilainya bisa berupa tanggal ISO dari input bertipe date ("2026-08-15")
 * ATAU teks bebas — diketik sales pada baris deskripsi, atau ditulis
 * CostRequestController dengan format 'M d, Y'. Hanya yang berpola ISO yang
 * diformat; sisanya dikembalikan apa adanya supaya invoice yang sudah terbit
 * tidak berubah tampilannya.
 */
final class ChargeLineDate
{
    /** En dash Unicode — sama dengan $resvDate di invoice.blade.php. */
    private const PEMISAH = ' – ';

    public static function format(?string $start, ?string $end = null): string
    {
        $awal  = self::satu($start);
        $akhir = self::satu($end);

        if ($awal === '') {
            return $akhir;
        }

        // Tanpa tanggal selesai, atau rentang sehari: satu tanggal saja.
        // Menjaga agar tidak pernah ada tanda pisah menggantung.
        if ($akhir === '' || $akhir === $awal) {
            return $awal;
        }

        return $awal . self::PEMISAH . $akhir;
    }

    private static function satu(?string $nilai): string
    {
        $nilai = trim((string) $nilai);

        // hasFormat() menolak "Aug 15, 2026" DAN "2026-13-45" sekaligus, jadi
        // teks bebas dan tanggal mustahil sama-sama lolos tanpa exception.
        if ($nilai === '' || ! Carbon::hasFormat($nilai, 'Y-m-d')) {
            return $nilai;
        }

        return Carbon::createFromFormat('Y-m-d', $nilai)->format('d/m/Y');
    }
}
