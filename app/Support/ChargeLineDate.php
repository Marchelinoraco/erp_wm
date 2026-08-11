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

        // hasFormat() hanya memeriksa rentang angka per token (m: 01-12, d: 01-31),
        // bukan validitas kalender. Nilai seperti "2026-02-30" lolos hasFormat()
        // lalu digulung Carbon menjadi tanggal lain. Round-trip menangkapnya tanpa
        // exception: jika hasil format ulang tidak cocok input, berarti tanggal
        // kalender-mustahil, dikembalikan apa adanya.
        if ($nilai === '' || ! Carbon::hasFormat($nilai, 'Y-m-d')) {
            return $nilai;
        }

        $tanggal = Carbon::createFromFormat('Y-m-d', $nilai);

        // hasFormat() hanya memeriksa rentang angka, bukan kalender:
        // 2026-02-30 lolos lalu digulung jadi 2 Maret. Round-trip
        // menangkapnya tanpa melempar exception.
        if ($tanggal->format('Y-m-d') !== $nilai) {
            return $nilai;
        }

        return $tanggal->format('d/m/Y');
    }
}
