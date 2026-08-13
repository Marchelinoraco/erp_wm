<?php

namespace Tests\Unit\Support;

use App\Support\CompactDateRange;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Format tanggal ringkas gaya voucher hotel (spec §6). Bulan dan tahun tidak
 * diulang bila sama, dan tanggal ditulis tanpa nol di depan.
 */
class CompactDateRangeTest extends TestCase
{
    private function tgl(string $iso): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $iso)->startOfDay();
    }

    public function test_sebulan_sama_tidak_mengulang_bulan(): void
    {
        $this->assertSame('1-2 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), $this->tgl('2026-08-02')));
        $this->assertSame('15-17 Aug 2026', CompactDateRange::format($this->tgl('2026-08-15'), $this->tgl('2026-08-17')));
    }

    public function test_beda_bulan_mengulang_bulan_tapi_bukan_tahun(): void
    {
        $this->assertSame('30 Aug - 2 Sep 2026', CompactDateRange::format($this->tgl('2026-08-30'), $this->tgl('2026-09-02')));
    }

    public function test_beda_tahun_menulis_keduanya_lengkap(): void
    {
        $this->assertSame('30 Dec 2026 - 2 Jan 2027', CompactDateRange::format($this->tgl('2026-12-30'), $this->tgl('2027-01-02')));
    }

    public function test_satu_tanggal_saja(): void
    {
        $this->assertSame('1 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), null));
        $this->assertSame('1 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), $this->tgl('2026-08-01')));
    }

    public function test_tanpa_tanggal_mulai_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', CompactDateRange::format(null, null));
        $this->assertSame('', CompactDateRange::format(null, $this->tgl('2026-08-02')));
    }

    public function test_tanggal_selesai_mendahului_mulai_tetap_ditulis_apa_adanya(): void
    {
        // Data seperti ini tidak seharusnya ada, tapi jangan melempar dan
        // jangan diam-diam menukar urutannya — cetak apa yang tersimpan.
        $this->assertSame('5-2 Aug 2026', CompactDateRange::format($this->tgl('2026-08-05'), $this->tgl('2026-08-02')));
    }
}
