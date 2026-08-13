<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Enam laporan keuangan hanya menghitung invoice yang SUDAH disetujui.
 *
 * Proforma yang masih draft bukan penjualan, bukan piutang, bukan laba
 * ditahan, dan bukan peredaran bruto pajak. Terukur di production: 17 dari 29
 * invoice 2026 belum disetujui, menyumbang 57,8% dari angka "Total Penjualan"
 * yang selama ini tampil.
 */
class LaporanHanyaInvoiceDisetujuiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Halaman Keuangan dibatasi middleware role:admin,accountant. */
    private function financeUser(): \App\Models\User
    {
        return \App\Models\User::create([
            'name'     => 'Akuntan Uji',
            'email'    => 'akuntan' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }

    /** Satu invoice disetujui + satu draft, pada tahun yang sama. */
    private function duaInvoice(): array
    {
        $tour = $this->makeTour('tour', ['pax' => 2]);

        $disetujui = $this->makeInvoice($tour, 5_000_000);
        $this->approveInvoice($disetujui);

        $draft = $this->makeInvoice($tour, 9_000_000);

        return [$disetujui->fresh(), $draft->fresh()];
    }

    private function tahun(): int
    {
        return (int) now()->year;
    }

    public function test_laba_rugi_mengabaikan_invoice_belum_disetujui(): void
    {
        [$disetujui, $draft] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            // Closure, bukan literal float: totalnya bulat (kelipatan pax),
            // jadi json_encode membuang ".0" dan Inertia membacanya balik
            // sebagai int — assertSame literal akan gagal walau nilainya sama.
            ->assertInertia(fn ($page) => $page
                ->where('totalRevenue', fn ($value) => (float) $value === (float) $disetujui->total_idr));

        $this->assertGreaterThan(0, $draft->total_idr, 'Draft-nya memang bernilai — kalau nol, test ini tidak membuktikan apa pun');
    }

    public function test_laba_rugi_per_lini_bisnis_ikut_menyaring(): void
    {
        // Bukan hanya angka totalnya: tabel per lini bisnis dihitung terpisah
        // dari koleksi yang sama, jadi ia bisa saja lolos dari penyaring.
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(function ($page) use ($disetujui) {
                $lines = collect($page->toArray()['props']['lines']);

                $this->assertSame(
                    (float) $disetujui->total_idr,
                    (float) $lines->sum('revenue'),
                    'Jumlah penjualan seluruh lini bisnis harus sama dengan total'
                );
            });
    }

    public function test_invoice_disetujui_lalu_dihapus_tetap_tidak_terhitung(): void
    {
        [$disetujui, ] = $this->duaInvoice();
        $disetujui->delete();

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('totalRevenue', fn ($value) => (float) $value === 0.0));
    }

    public function test_draft_yang_kemudian_disetujui_mulai_terhitung(): void
    {
        // Membuktikan penyaringnya membaca keadaan terkini, bukan hasil cache.
        [$disetujui, $draft] = $this->duaInvoice();

        $this->approveInvoice($draft);

        $this->actingAs($this->financeUser())
            ->get(route('finance.income-statement', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('totalRevenue', fn ($value) => (float) $value === (float) $disetujui->total_idr + (float) $draft->fresh()->total_idr));
    }
}
