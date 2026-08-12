<?php

namespace App\Support;

/**
 * Merangkai baris "Hotel / Room" dari satu baris kamar:
 * `{hotel} – {jumlah} {tipe kamar} · {keterangan}`.
 *
 * Bagian yang kosong dilewati tanpa menyisakan pemisah menggantung. Nilai
 * non-skalar diperlakukan kosong, bukan dilempar — kolom JSON yang sama bisa
 * memuat bentuk apa pun, dan satu exception di sini menggagalkan render
 * seluruh PDF.
 */
final class HotelRoomLabel
{
    public static function forRoomLine(array $line): string
    {
        $hotel  = self::teks($line['hotel'] ?? null);
        $tipe   = self::teks($line['label'] ?? null);
        $ket    = self::teks($line['detail'] ?? null);
        $kamar  = is_scalar($line['rooms'] ?? null) ? (int) $line['rooms'] : 0;

        // Jumlah kamar hanya bermakna bila ada tipe kamarnya untuk dihitung.
        $unit = $tipe === '' ? '' : trim(($kamar > 0 ? $kamar . ' ' : '') . $tipe);

        $kiri = implode(' – ', array_filter([$hotel, $unit], fn ($v) => $v !== ''));

        if ($kiri === '') {
            return '';
        }

        return $ket === '' ? $kiri : $kiri . ' · ' . $ket;
    }

    private static function teks(mixed $nilai): string
    {
        return is_scalar($nilai) ? trim((string) $nilai) : '';
    }
}
