<?php

namespace Tests\Unit\Support;

use App\Support\ChargeLineDate;
use PHPUnit\Framework\TestCase;

/**
 * Aturan penulisan tanggal baris bernominal invoice (spec §6).
 *
 * Inti aturannya: HANYA nilai berpola YYYY-MM-DD yang diformat. Nilai lain
 * dicetak apa adanya, karena kolom yang sama menampung teks bebas dari sales
 * dan format 'M d, Y' dari CostRequestController. Tanpa penjagaan itu,
 * invoice yang sudah terbit akan berubah tampilannya.
 */
class ChargeLineDateTest extends TestCase
{
    public function test_dua_tanggal_iso_ditulis_sebagai_rentang(): void
    {
        $this->assertSame(
            '15/08/2026 – 17/08/2026',
            ChargeLineDate::format('2026-08-15', '2026-08-17')
        );
    }

    public function test_tanpa_tanggal_selesai_hanya_tanggal_mulai(): void
    {
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', ''));
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', null));
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15'));
    }

    public function test_tidak_ada_tanda_pisah_menggantung(): void
    {
        $this->assertStringNotContainsString('–', ChargeLineDate::format('2026-08-15', ''));
    }

    public function test_tanggal_mulai_dan_selesai_sama_tidak_diulang(): void
    {
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', '2026-08-15'));
    }

    public function test_teks_bebas_dicetak_apa_adanya(): void
    {
        // Ditulis CostRequestController::appendAdditionalCharge() dengan format 'M d, Y'.
        $this->assertSame('Aug 15, 2026', ChargeLineDate::format('Aug 15, 2026'));
        // Diketik sales pada baris deskripsi.
        $this->assertSame('13-15 Aug 2026', ChargeLineDate::format('13-15 Aug 2026'));
    }

    public function test_tanggal_mustahil_diperlakukan_sebagai_teks_bebas(): void
    {
        // Berpola YYYY-MM-DD tapi bukan tanggal yang ada. Tidak boleh melempar
        // exception yang menggagalkan seluruh render PDF.
        $this->assertSame('2026-13-45', ChargeLineDate::format('2026-13-45'));
    }

    public function test_tanggal_kalender_mustahil_angka_di_rentang(): void
    {
        // hasFormat() hanya memeriksa rentang angka (m: 01-12, d: 01-31), bukan
        // validitas kalender. Kasus seperti ini lolos hasFormat() lalu digulung
        // Carbon. Round-trip menangkapnya tanpa exception.
        $this->assertSame('2026-02-30', ChargeLineDate::format('2026-02-30'));
        $this->assertSame('2026-04-31', ChargeLineDate::format('2026-04-31'));
        $this->assertSame('2025-02-29', ChargeLineDate::format('2025-02-29'));
        $this->assertSame('2026-06-31', ChargeLineDate::format('2026-06-31'));
    }

    public function test_tanpa_tanggal_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', ChargeLineDate::format(null, null));
        $this->assertSame('', ChargeLineDate::format('', ''));
        $this->assertSame('', ChargeLineDate::format('   '));
    }

    public function test_hanya_tanggal_selesai_yang_terisi_tetap_tercetak(): void
    {
        $this->assertSame('17/08/2026', ChargeLineDate::format('', '2026-08-17'));
    }
}
