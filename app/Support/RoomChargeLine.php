<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Aturan hitung baris kamar pada invoice hotel bermode per kamar per malam.
 *
 * Baris kamar dikenali dari KEHADIRAN key `rooms`, bukan nilainya — meneruskan
 * cara yang sudah dipakai `amount` untuk memisahkan baris bernominal dari
 * baris deskripsi. Baris yang baru diketik tanggalnya, dengan kamar masih
 * kosong, tetap baris kamar.
 *
 * Jumlah malam tidak pernah disimpan: ia selalu diturunkan dari `date` dan
 * `date_end` di baris yang sama, sehingga tidak mungkin berselisih dengan
 * tanggal yang tertulis.
 */
final class RoomChargeLine
{
    public static function isRoomLine(array $line): bool
    {
        return array_key_exists('rooms', $line);
    }

    /** Selisih hari check-in ke check-out. 0 bila tidak membentuk rentang yang sah. */
    public static function nights(array $line): int
    {
        $mulai   = self::tanggal($line['date'] ?? null);
        $selesai = self::tanggal($line['date_end'] ?? null);

        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            return 0;
        }

        return (int) $mulai->diffInDays($selesai);
    }

    /** harga per kamar per malam × jumlah kamar × jumlah malam. */
    public static function amount(array $line): float
    {
        $harga = (float) ($line['unit_price'] ?? 0);
        $kamar = (int) ($line['rooms'] ?? 0);

        return $harga * $kamar * self::nights($line);
    }

    /**
     * Menulis ulang `amount` setiap baris kamar. Baris biaya tambahan dan
     * baris deskripsi dikembalikan apa adanya.
     *
     * Dipanggil di server SEBELUM menyimpan, sehingga nominal yang dikirim
     * browser tidak pernah menjadi sumber kebenaran uang.
     */
    public static function recalculate(array $lines): array
    {
        return array_map(function (array $line) {
            if (self::isRoomLine($line)) {
                $line['amount'] = self::amount($line);
            }

            return $line;
        }, $lines);
    }

    /** Hanya menerima YYYY-MM-DD yang benar-benar ada di kalender. */
    private static function tanggal(?string $nilai): ?Carbon
    {
        $nilai = trim((string) $nilai);

        if ($nilai === '' || ! Carbon::hasFormat($nilai, 'Y-m-d')) {
            return null;
        }

        $tanggal = Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay();

        // hasFormat() hanya memeriksa rentang angka, bukan kalender:
        // 2026-02-30 lolos lalu digulung jadi 2 Maret.
        return $tanggal->format('Y-m-d') === $nilai ? $tanggal : null;
    }
}
