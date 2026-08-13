<?php

namespace Tests\Feature\Finance;

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

    public function test_neraca_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        // Prop Neraca bersarang: aset.ar, bukan ar. Closure, bukan literal
        // float: totalnya bulat, json_encode membuang ".0" dan Inertia
        // membacanya balik sebagai int — assertSame literal akan gagal
        // walau nilainya sama (lihat catatan di test laba rugi di atas).
        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('aset.ar', fn ($v) => (float) $v === (float) $disetujui->total_idr));
    }

    public function test_neraca_tetap_seimbang_dengan_data_campuran(): void
    {
        // Penjaga terpenting di pekerjaan ini. Piutang dan laba ditahan
        // membesar bersamaan oleh nilai draft yang sama, di sisi berlawanan
        // persamaan neraca — sehingga Neraca SEIMBANG meski keduanya salah.
        // Memperbaiki satu sisi saja akan membuatnya benar-benar timpang.
        //
        // balanceSheetData() sudah menghitung sendiri prop `balanced`:
        // abs($asetTotal - ($kewajibanTotal + $ekuitasTotal)) < 1
        $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('balanced', true));
    }

    public function test_saldo_akun_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.account-balances'))
            ->assertInertia(fn ($page) => $page->where('ar', fn ($v) => (float) $v === (float) $disetujui->total_idr));
    }

    /**
     * Dashboard menuntut peran yang lolos DUA gerbang sekaligus, dan hanya
     * admin yang memenuhinya:
     *   - route('dashboard') dijaga middleware role:admin,sales
     *   - blok keuangannya dijaga isAdmin() || isAccountant()
     * Sales lolos rute tapi tidak pernah menerima prop arOutstanding;
     * accountant punya haknya tapi ditolak rute.
     */
    private function adminUser(): \App\Models\User
    {
        return \App\Models\User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    public function test_dashboard_piutang_hanya_dari_invoice_disetujui(): void
    {
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->adminUser())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('arOutstanding', fn ($v) => (float) $v === (float) $disetujui->total_idr));
    }

    public function test_fiskal_peredaran_bruto_hanya_dari_invoice_disetujui(): void
    {
        // Peredaran bruto adalah dasar hitung pajak — FiscalController memakai
        // $totalRevenue langsung sebagai $taxBase. Proforma draft yang ikut
        // terhitung berarti pajak dihitung dari penjualan yang belum ada.
        //
        // Fiskal memakai kolom `total`, BUKAN `total_idr` seperti lima tempat
        // lain. Perbedaan itu soal mata uang, bukan persetujuan, dan sengaja
        // tidak disentuh di sini.
        [$disetujui, ] = $this->duaInvoice();

        $this->actingAs($this->financeUser())
            ->get(route('finance.fiscal', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('totalRevenue', fn ($v) => (float) $v === (float) $disetujui->total));
    }

    public function test_pembayaran_pada_invoice_draft_tidak_mengurangi_piutang(): void
    {
        // Piutang = SUM(invoices) - SUM(invoice_payments). Menyaring sisi
        // invoice saja akan tetap mengurangkan pembayaran milik invoice yang
        // TIDAK ikut dihitung, sehingga piutang jadi terlalu kecil.
        //
        // Tanpa test ini separuh perbaikan tidak teruji: fixture duaInvoice()
        // tidak membuat pembayaran sama sekali, jadi SUM(invoice_payments)
        // bernilai 0 dengan atau tanpa penyaring.
        [$disetujui, $draft] = $this->duaInvoice();

        \App\Models\InvoicePayment::create([
            'invoice_id' => $draft->id,
            'date'       => now()->toDateString(),
            'amount'     => 1_000_000,
            'amount_idr' => 1_000_000,
            'method'     => 'transfer',
        ]);

        $harapan = (float) $disetujui->total_idr;

        $this->actingAs($this->financeUser())
            ->get(route('finance.account-balances'))
            ->assertInertia(fn ($page) => $page->where('ar', fn ($v) => (float) $v === $harapan));

        // KESENJANGAN YANG DIKETAHUI, sengaja dikunci di sini.
        //
        // Pembayaran atas invoice draft menciptakan FinTransaction nyata lewat
        // InvoicePaymentObserver -> LedgerSync, jadi kasnya bertambah. Tapi
        // piutang dan laba ditahan invoice itu kini (dengan benar) tidak
        // dihitung — sehingga kas tersebut kehilangan pasangannya dan Neraca
        // menjadi TIDAK seimbang.
        //
        // Ini bukan cacat yang diperkenalkan perbaikan ini, melainkan celah
        // jalur tulis yang tersingkap olehnya: InvoicePaymentController::store()
        // tidak pernah memeriksa apakah invoice-nya sudah disetujui. Diverifikasi
        // di production 2026-08-12: 0 pembayaran semacam itu — belum pernah
        // terjadi, tapi tidak dijaga.
        //
        // Assertion ini mengunci kenyataannya apa adanya. Kalau kelak jalur
        // tulisnya dijaga atau kas semacam itu dicatat sebagai uang muka,
        // test ini akan gagal dan memaksa keputusannya ditinjau ulang —
        // bukan lolos diam-diam.
        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page
                ->where('aset.ar', fn ($v) => (float) $v === $harapan)
                ->where('balanced', false));

        $this->actingAs($this->adminUser())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('arOutstanding', fn ($v) => (float) $v === $harapan));
    }

    public function test_piutang_sama_di_neraca_saldo_akun_dan_dashboard(): void
    {
        // Tiga halaman menghitung piutang dengan rumus terpisah. Kalau salah
        // satu terlewat disaring, angkanya akan berbeda — dan pengguna yang
        // membandingkan dua halaman akan melihat sistem berselisih dengan
        // dirinya sendiri. Ketiganya dibandingkan terhadap satu angka harapan
        // yang sama, sehingga kegagalannya menunjuk halaman mana yang salah.
        [$disetujui, ] = $this->duaInvoice();
        $harapan = (float) $disetujui->total_idr;
        $cocok   = fn ($v) => (float) $v === $harapan;

        // Neraca menyimpannya bersarang di aset.ar; dua lainnya datar.
        $this->actingAs($this->financeUser())
            ->get(route('finance.balance-sheet', ['year' => $this->tahun()]))
            ->assertInertia(fn ($page) => $page->where('aset.ar', $cocok));

        $this->actingAs($this->financeUser())
            ->get(route('finance.account-balances'))
            ->assertInertia(fn ($page) => $page->where('ar', $cocok));

        $this->actingAs($this->adminUser())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('arOutstanding', $cocok));
    }
}
