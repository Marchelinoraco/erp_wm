<?php

namespace Tests\Unit\Support;

use App\Support\RoomChargeLine;
use PHPUnit\Framework\TestCase;

/**
 * Aturan hitung baris kamar invoice hotel (spec §4).
 *
 * Dua hal yang dikunci di sini: penanda baris kamar adalah KEHADIRAN key
 * `rooms` (bukan nilainya), dan tidak ada keadaan kosong yang ditebak menjadi
 * 1 malam atau 1 kamar — semuanya menghasilkan 0 agar sales melihat sendiri
 * apa yang belum diisi.
 */
class RoomChargeLineTest extends TestCase
{
    public function test_penanda_baris_kamar_adalah_kehadiran_key_rooms(): void
    {
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => 2]));
        // Nilai kosong tetap baris kamar — sama seperti `amount` 0 yang tetap
        // baris bernominal. Kalau tidak, baris kamar yang baru diketik
        // tanggalnya akan turun pangkat jadi biaya tambahan setelah disimpan.
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => '']));
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => null]));

        $this->assertFalse(RoomChargeLine::isRoomLine(['amount' => 500000]));
        $this->assertFalse(RoomChargeLine::isRoomLine(['label' => 'Hotel', 'date' => '13-15 Aug']));
    }

    public function test_malam_adalah_selisih_hari(): void
    {
        $this->assertSame(2, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '2026-08-17']));
        $this->assertSame(1, RoomChargeLine::nights(['date' => '2026-08-17', 'date_end' => '2026-08-18']));
        $this->assertSame(31, RoomChargeLine::nights(['date' => '2026-12-15', 'date_end' => '2027-01-15']));
    }

    public function test_malam_nol_untuk_tanggal_yang_tidak_membentuk_rentang(): void
    {
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-17', 'date_end' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights([]));
    }

    public function test_malam_nol_untuk_tanggal_yang_bukan_iso(): void
    {
        // Baris deskripsi memakai teks bebas di kolom yang sama; jangan sampai
        // melempar exception yang menggagalkan penyimpanan seluruh invoice.
        $this->assertSame(0, RoomChargeLine::nights(['date' => '13-15 Aug 2026', 'date_end' => 'entah']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-02-30', 'date_end' => '2026-03-02']));
    }

    public function test_nominal_adalah_harga_kali_kamar_kali_malam(): void
    {
        $baris = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(6_000_000.0, RoomChargeLine::amount($baris));
    }

    public function test_nominal_nol_untuk_tiap_isian_yang_kosong(): void
    {
        $lengkap = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => 0])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => ''])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => 0])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => ''])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['date_end' => ''])));
    }

    public function test_recalculate_menimpa_nominal_baris_kamar(): void
    {
        // Browser boleh mengirim nominal apa pun; server yang menentukan.
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Deluxe', 'rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'amount' => 999],
        ]);

        $this->assertSame(6_000_000.0, $hasil[0]['amount']);
    }

    public function test_recalculate_tidak_menyentuh_baris_lain(): void
    {
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
            ['label' => 'Hotel', 'date' => '13-15 Aug 2026', 'detail' => 'Deluxe Room'],
        ]);

        $this->assertSame(750_000, $hasil[0]['amount'], 'Biaya tambahan tetap nominal yang diketik sales');
        $this->assertArrayNotHasKey('amount', $hasil[1], 'Baris deskripsi tidak mendapat amount');
    }

    public function test_recalculate_mempertahankan_urutan_dan_key_lain(): void
    {
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Deluxe', 'detail' => 'Twin bed', 'rooms' => 1, 'unit_price' => 1_000_000, 'date' => '2026-08-15', 'date_end' => '2026-08-16'],
            ['label' => 'Antar-jemput', 'amount' => 750_000],
        ]);

        $this->assertCount(2, $hasil);
        $this->assertSame('Deluxe', $hasil[0]['label']);
        $this->assertSame('Twin bed', $hasil[0]['detail']);
        $this->assertSame('Antar-jemput', $hasil[1]['label']);
    }

    public function test_malam_nol_untuk_tanggal_bertipe_array(): void
    {
        // JSON decoded dari description_lines bisa menghasilkan array untuk nilai bersarang
        // (bug UI atau payload yang dirancang khusus). Tidak boleh melempar TypeError.
        $this->assertSame(0, RoomChargeLine::nights(['date' => ['a'], 'date_end' => '2026-08-17']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => ['b']]));
        $this->assertSame(0, RoomChargeLine::nights(['date' => [1, 2, 3], 'date_end' => []]));
    }

    public function test_nominal_nol_untuk_rooms_non_skalar(): void
    {
        // array tidak boleh dicast menjadi 1 (perilaku PHP default); harus 0
        $lengkap = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => [1, 2, 3]])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => (object)['val' => 1]])));
    }

    public function test_nominal_nol_untuk_unit_price_non_skalar(): void
    {
        // array tidak boleh dicast; harus 0
        $lengkap = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => ['x']])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => (object)['val' => 1000]])));
    }

    public function test_recalculate_tidak_melempar_untuk_tanggal_array(): void
    {
        // Baris kamar dengan date array tidak boleh melempar; amount menjadi 0
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Deluxe', 'rooms' => 2, 'unit_price' => 1_500_000, 'date' => ['nested'], 'date_end' => '2026-08-17'],
        ]);

        $this->assertSame(0.0, $hasil[0]['amount']);
    }
}
