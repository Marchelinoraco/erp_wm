<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D1: satuan pengali di PDF customer adalah "pax" untuk SEMUA jenis — bukan
 * karena "pax" benar untuk semua, tapi karena angka yang dikalikan memang
 * jumlah pax (tour.pax) sampai Fase 3 dokumen lama selesai. Test ini mengunci
 * pembatalan 1be88ff/bbde75f agar satuan per jenis tidak diam-diam kembali.
 *
 * Caranya penting: setiap test SENGAJA mengirim `billingUnit` bernilai salah.
 * Selama blade masih memakai `$billingUnit ?? 'pax'`, test gagal; setelah
 * blade mematok 'pax', nilai salah itu diabaikan dan test lulus. Tanpa nilai
 * salah yang disengaja, fallback `?? 'pax'` membuat test lulus sejak awal dan
 * tidak membuktikan apa pun.
 */
class CustomerPdfUnitLabelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /**
     * Data view persis seperti yang dikirim InvoiceController::build().
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed>             $extra  disisipkan/menimpa data view
     */
    private function renderInvoice(
        \App\Models\Invoice $invoice,
        float $unitPrice,
        int $pax,
        array $lines = [],
        array $extra = [],
    ): string {
        $segar = $invoice->fresh();

        return view('invoice', array_replace([
            'invoice'      => $segar,
            'company'      => config('quotation.company'),
            'bank'         => [],
            'paymentTerms' => '',
            'logo'         => '',
            'lines'        => $lines,
            'unitPrice'    => $unitPrice,
            'pax'          => $pax,
            'paid'         => 0.0,
            'outstanding'  => (float) $segar->total,
            // DITURUNKAN seperti InvoiceController::build(), bukan diterima
            // sebagai parameter. Sebelumnya helper ini membiarkan test memilih
            // nilainya sendiri, dan itu menyembunyikan bug nyata: test mengoper
            // unitPrice 0 untuk rental padahal produksi mengirim harga lamanya
            // yang utuh, sehingga baris "Price :" bernominal 0 tetap tercetak
            // di PDF sungguhan meski test hijau.
            'fromLineItems' => app(\App\Services\SalesLine\SalesLineRuleRegistry::class)
                ->for($segar->tour?->type ?? 'tour')
                ->totalComposition() === 'line_items',
        ], $extra))->render();
    }

    public function test_pdf_customer_mencetak_pax_untuk_setiap_jenis(): void
    {
        // `rental` dikecualikan sejak Fase 3: baris harga satuannya tidak
        // dicetak sama sekali, jadi tidak ada "× N pax" untuk diperiksa.
        // Perilaku barunya dikunci test tersendiri di bawah.
        foreach (array_diff(self::SALES_TYPES, ['rental']) as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 7]), 100_000);

            // 'hari' sengaja salah: blade harus mengabaikannya sepenuhnya.
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => 'hari',
            ]);

            $this->assertStringContainsString('&times; 7 pax', $html, "Jenis {$type}");
            $this->assertStringNotContainsString('&times; 7 hari', $html, "Jenis {$type}");
        }
    }

    public function test_tidak_ada_satuan_per_jenis_yang_tersisa_di_pdf(): void
    {
        // Keempat kata yang bbde75f perkenalkan. Kemunculannya kembali berarti
        // asumsi D2 yang belum diputuskan naik lagi jadi pernyataan ke customer.
        // Dipakai jenis `guide` (bukan `rental` seperti semula) karena rental
        // kini tidak mencetak baris harga satuan sama sekali — pemeriksaan ini
        // butuh jenis yang masih mencetaknya.
        $invoice = $this->makeInvoice($this->makeTour('guide', ['pax' => 7]), 100_000);

        foreach (['hari', 'dokumen', 'tiket', 'malam'] as $satuan) {
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => $satuan,
            ]);

            $this->assertStringNotContainsString('&times; 7 ' . $satuan, $html);
            $this->assertStringContainsString('&times; 7 pax', $html);
        }
    }

    /** Rincian rental seperti kasus nyata: tiga baris bernominal bertanggal. */
    private const BARIS_RENTAL = [
        ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
        ['label' => 'Innova Reborn', 'date' => '2026-07-25', 'detail' => 'Sewa harian', 'amount' => 1_050_000],
        ['label' => 'Luar kota', 'date' => '2026-07-25', 'detail' => 'Tambahan', 'amount' => 200_000],
    ];

    public function test_baris_bernominal_mencetak_tanggal(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 2_050_000, [
            'description_lines' => self::BARIS_RENTAL,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_050_000, pax: 10, lines: self::BARIS_RENTAL);

        $this->assertStringContainsString('2026-07-22', $html);
        $this->assertStringContainsString('2026-07-25', $html);
    }

    public function test_baris_price_tidak_dicetak_untuk_komposisi_baris_bernominal(): void
    {
        // unit_price rental SENGAJA dibiarkan utuh di database (banner panel
        // menampilkannya), jadi PDF tidak boleh memakai nilai itu sebagai
        // patokan. Ditemukan lewat pemeriksaan PDF sungguhan: versi pertama
        // memakai `@if($unitPrice > 0)` dan tetap mencetak baris
        // "Price : IDR 2.050.000 × 10 pax" bernominal IDR 0 ke customer,
        // sementara test hijau karena mengoper unitPrice 0.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 2_050_000, [
            'description_lines' => self::BARIS_RENTAL,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_050_000, pax: 10, lines: self::BARIS_RENTAL);

        $this->assertStringNotContainsString('>Price<', $html);
        // Totalnya tetap jumlah baris, bukan unit_price × pax.
        $this->assertEquals(2_050_000, $invoice->fresh()->total);
    }

    public function test_baris_price_tetap_dicetak_untuk_jenis_per_unit(): void
    {
        // Pasangan dari test di atas: pengecualian hanya berlaku bagi jenis
        // berkomposisi line_items, bukan diam-diam menghapus baris Price
        // untuk semua orang.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 10]), 1_000_000);

        $html = $this->renderInvoice($invoice, unitPrice: 1_000_000, pax: 10);

        $this->assertStringContainsString('>Price<', $html);
    }
}
