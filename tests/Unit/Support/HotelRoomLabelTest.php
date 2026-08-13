<?php

namespace Tests\Unit\Support;

use App\Support\HotelRoomLabel;
use PHPUnit\Framework\TestCase;

/**
 * Perangkaian baris "Hotel / Room" untuk baris kamar (spec §7.1). Bagian yang
 * kosong dilewati tanpa menyisakan pemisah menggantung — dokumen ke customer
 * tidak boleh memuat tanda hubung yang berdiri sendiri.
 */
class HotelRoomLabelTest extends TestCase
{
    public function test_lengkap(): void
    {
        $this->assertSame(
            'Paradise Hotel – 1 Deluxe Room · Twin bed',
            HotelRoomLabel::forRoomLine([
                'hotel' => 'Paradise Hotel', 'rooms' => 1,
                'label' => 'Deluxe Room', 'detail' => 'Twin bed',
            ])
        );
    }

    public function test_tanpa_keterangan(): void
    {
        $this->assertSame(
            'Paradise Hotel – 1 Deluxe Room',
            HotelRoomLabel::forRoomLine([
                'hotel' => 'Paradise Hotel', 'rooms' => 1, 'label' => 'Deluxe Room',
            ])
        );
    }

    public function test_tanpa_nama_hotel_tidak_menyisakan_tanda_hubung(): void
    {
        $hasil = HotelRoomLabel::forRoomLine(['rooms' => 2, 'label' => 'Deluxe Room']);

        $this->assertSame('2 Deluxe Room', $hasil);
        $this->assertStringNotContainsString('–', $hasil);
    }

    public function test_tanpa_tipe_kamar_hanya_nama_hotel(): void
    {
        $this->assertSame(
            'Paradise Hotel',
            HotelRoomLabel::forRoomLine(['hotel' => 'Paradise Hotel', 'rooms' => 1, 'label' => ''])
        );
    }

    public function test_semuanya_kosong_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', HotelRoomLabel::forRoomLine([]));
        $this->assertSame('', HotelRoomLabel::forRoomLine(['hotel' => '', 'label' => '', 'rooms' => 0]));
    }

    public function test_jumlah_kamar_kosong_tidak_mencetak_angka(): void
    {
        $this->assertSame(
            'Paradise Hotel – Deluxe Room',
            HotelRoomLabel::forRoomLine(['hotel' => 'Paradise Hotel', 'rooms' => 0, 'label' => 'Deluxe Room'])
        );
    }

    public function test_nilai_non_skalar_tidak_melempar(): void
    {
        // description_lines didekode dengan json_decode(..., true), jadi array
        // adalah satu-satunya bentuk non-skalar yang bisa muncul di sini.
        $this->assertSame('', HotelRoomLabel::forRoomLine(['hotel' => ['x'], 'label' => ['y'], 'rooms' => ['z']]));
    }
}
