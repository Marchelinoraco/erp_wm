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
        // Satu aturan, dipakai untuk fromLineItems, dateFirstLines, DAN
        // chargeLines di bawah — sama seperti InvoiceController::
        // invoiceViewData() menyelesaikannya sekali lewat forInvoice(). Test
        // di berkas ini tidak pernah mengirim baris kamar (`rooms`), jadi
        // reject()-nya selalu no-op di sini — filter §9 tidak mengubah
        // perilaku test unit label ini sama sekali.
        $rule = app(\App\Services\SalesLine\SalesLineRuleRegistry::class)
            ->for($segar->tour?->type ?? 'tour');

        return view('invoice', array_replace([
            'invoice'      => $segar,
            'company'      => config('quotation.company'),
            'bank'         => [],
            'paymentTerms' => '',
            'logo'         => '',
            'lines'        => $lines,
            // DITURUNKAN seperti InvoiceController::invoiceViewData() —
            // lihat App\Support\RoomChargeLine::isRoomLine().
            'chargeLines'  => collect($lines)
                ->reject(fn ($l) => $rule->totalComposition() !== 'line_items' && \App\Support\RoomChargeLine::isRoomLine($l))
                ->values()->all(),
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
            'fromLineItems' => $rule->totalComposition() === 'line_items',
            // DITURUNKAN juga, dengan alasan yang sama seperti fromLineItems di
            // atas: tata letak kolom PDF ditentukan aturan jenis, dan test yang
            // boleh memilih nilainya sendiri akan menyembunyikan ketidakcocokan
            // dengan InvoiceController::build().
            'dateFirstLines' => $rule->chargeLineLayout() === 'date_first',
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

        // Tanggal ISO kini ditulis d/m/Y untuk customer, bukan mentah.
        $this->assertStringContainsString('22/07/2026', $html);
        $this->assertStringContainsString('25/07/2026', $html);
        $this->assertStringNotContainsString('2026-07-22', $html);
    }

    /** Baris rental dengan periode sewa penuh — kasus yang fitur ini layani. */
    private const BARIS_RENTAL_BERENTANG = [
        ['label' => 'Innova Reborn', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'detail' => 'Dengan Sopir', 'amount' => 1_700_000],
        ['label' => 'Avanza', 'date' => '2026-08-16', 'date_end' => '2026-08-17', 'detail' => 'sopir', 'amount' => 1_200_000],
    ];

    public function test_baris_bernominal_mencetak_rentang_tanggal(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 2_900_000, [
            'description_lines' => self::BARIS_RENTAL_BERENTANG,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_900_000, pax: 4, lines: self::BARIS_RENTAL_BERENTANG);

        $this->assertStringContainsString('15/08/2026 – 17/08/2026', $html);
        $this->assertStringContainsString('16/08/2026 – 17/08/2026', $html);
    }

    public function test_tanpa_tanggal_selesai_tidak_ada_tanda_pisah_menggantung(): void
    {
        $baris = [
            ['label' => 'Avanza', 'date' => '2026-08-15', 'detail' => 'sopir', 'amount' => 900_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 900_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 900_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('15/08/2026', $html);
        $this->assertStringNotContainsString('15/08/2026 –', $html);
    }

    public function test_tanggal_teks_bebas_pada_invoice_lama_tidak_berubah(): void
    {
        // CostRequestController::appendAdditionalCharge() menulis format 'M d, Y'.
        // Memformat ulang nilai seperti ini akan mengubah tampilan invoice yang
        // sudah terbit — justru yang paling harus dihindari.
        $baris = [
            ['label' => 'Additional', 'date' => 'Aug 15, 2026', 'detail' => 'Biaya tambahan disetujui', 'amount' => 500_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 1_000_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('Aug 15, 2026', $html);
    }

    public function test_tanggal_iso_diformat_untuk_jenis_selain_rental_dan_hotel(): void
    {
        // Spec §6.1: pemformatan tanggal berpatokan BENTUK NILAI, bukan jenis
        // penjualan. Tanpa test ini, seseorang bisa menambahkan gerbang per
        // jenis di blade dan seluruh suite tetap hijau — padahal itu membuat
        // satu dokumen memuat dua gaya tanggal sekaligus.
        $baris = [
            ['label' => 'Dokumen', 'date' => '2026-08-15', 'detail' => 'Visa', 'amount' => 500_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 1_000_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('15/08/2026', $html);
        $this->assertStringNotContainsString('2026-08-15', $html);
    }

    /** Rincian rental berperiode — kasus yang tata letak kolom baru ini layani. */
    private const BARIS_RENTAL_KOLOM = [
        ['label' => 'Innova Reborn', 'date' => '2026-08-15', 'date_end' => '2026-08-16', 'detail' => 'Dengan Sopir', 'amount' => 1_700_000],
        ['label' => 'Avanza', 'date' => '2026-08-16', 'date_end' => '2026-08-17', 'detail' => 'sopir', 'amount' => 1_200_000],
    ];

    public function test_pdf_rental_menaruh_rentang_tanggal_di_kolom_kiri(): void
    {
        // Urutannya mengikuti form Rincian Tagihan: periode dulu, baru unitnya.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 2_900_000, [
            'description_lines' => self::BARIS_RENTAL_KOLOM,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_900_000, pax: 4, lines: self::BARIS_RENTAL_KOLOM);

        $this->assertStringContainsString('<td class="k">15/08/2026 – 16/08/2026</td>', $html);
        $this->assertStringContainsString('<td class="k">16/08/2026 – 17/08/2026</td>', $html);
        // Nama unit turun ke kolom kanan — tidak lagi menempati kolom label.
        $this->assertStringNotContainsString('<td class="k">Innova Reborn</td>', $html);
        $this->assertStringContainsString('Innova Reborn', $html);
        $this->assertStringContainsString('Dengan Sopir', $html);
    }

    public function test_pdf_selain_rental_mempertahankan_tata_letak_lama(): void
    {
        // Hotel juga punya tanggal mulai & selesai di form, tapi tata letak
        // PDF-nya TIDAK ikut berubah — hanya rental yang diminta. Pengunci
        // terpenting fitur ini: satu permintaan tidak boleh diam-diam menyeret
        // jenis penjualan lain.
        $baris = [
            ['label' => 'Deluxe Room', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'detail' => 'Twin bed', 'amount' => 3_000_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 3_000_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 3_000_000, pax: 2, lines: $baris);

        $this->assertStringContainsString('<td class="k">Deluxe Room</td>', $html);
        $this->assertStringNotContainsString('<td class="k">15/08/2026 – 17/08/2026</td>', $html);
        // Tanggalnya tetap tercetak; yang tidak berubah hanyalah tempatnya.
        $this->assertStringContainsString('15/08/2026 – 17/08/2026', $html);
    }

    public function test_baris_rental_tanpa_tanggal_tetap_memakai_nama_sebagai_label(): void
    {
        // Kolom kiri tidak boleh pernah kosong. Baris rental tanpa tanggal
        // (mis. biaya parkir) jatuh kembali ke tata letak lama.
        $baris = [
            ['label' => 'Biaya parkir', 'detail' => 'Tol & parkir', 'amount' => 150_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 150_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 150_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('<td class="k">Biaya parkir</td>', $html);
    }

    public function test_kolom_label_hanya_melebar_pada_pdf_rental(): void
    {
        // Rentang tanggal tidak muat di 120px. Kolomnya dilebarkan untuk
        // SELURUH dokumen rental supaya titik dua blok atas tetap sejajar
        // dengan baris tagihan; dokumen jenis lain tidak tersentuh.
        $rental = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 2_900_000, [
            'description_lines' => self::BARIS_RENTAL_KOLOM,
        ]);
        $htmlRental = $this->renderInvoice($rental, unitPrice: 2_900_000, pax: 4, lines: self::BARIS_RENTAL_KOLOM);

        $tour     = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000);
        $htmlTour = $this->renderInvoice($tour, unitPrice: 1_000_000, pax: 4);

        $this->assertStringContainsString('.kv td.k { width: 180px; }', $htmlRental);
        $this->assertStringContainsString('.kv td.k { width: 120px; }', $htmlTour);
    }

    public function test_kolom_label_rental_muat_untuk_rentang_tanggal(): void
    {
        // Assertion atas potongan HTML TIDAK BISA melihat pembungkusan baris:
        // kolom yang terlalu sempit tetap menghasilkan HTML yang sama persis,
        // dan rentang tanggalnya baru terlihat patah dua baris setelah PDF-nya
        // dicetak. Versi pertama fitur ini memakai 160px dan setiap rentang
        // dua tanggal berbeda turun ke baris kedua tanpa satu test pun gagal.
        //
        // Karena itu lebar kolomnya diukur di sini dengan mesin dan font yang
        // sama dengan yang mencetak PDF sungguhan.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 2_900_000, [
            'description_lines' => self::BARIS_RENTAL_KOLOM,
        ]);
        $html = $this->renderInvoice($invoice, unitPrice: 2_900_000, pax: 4, lines: self::BARIS_RENTAL_KOLOM);

        $this->assertMatchesRegularExpression('/\.kv td\.k \{ width: (\d+)px; \}/', $html);
        preg_match('/\.kv td\.k \{ width: (\d+)px; \}/', $html, $cocok);
        $lebarMm = (int) $cocok[1] * 25.4 / 96;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'default_font' => 'dejavusans']);
        $mpdf->SetFont('dejavusans', '', 10);

        // Semua digit dejavusans berlebar sama, jadi rentang mana pun selebar
        // ini — tanggal beda tahun sekalipun.
        $butuhMm = $mpdf->GetStringWidth('15/08/2026 – 16/08/2026');

        $this->assertGreaterThan(
            $butuhMm,
            $lebarMm,
            sprintf(
                'Kolom label %.1fmm tidak muat untuk rentang tanggal %.1fmm — rentangnya akan patah dua baris di PDF customer.',
                $lebarMm,
                $butuhMm
            )
        );
    }

    public function test_satu_invoice_rental_boleh_campur_baris_bertanggal_dan_tidak(): void
    {
        // Kemunduran ke tata letak lama diputuskan PER BARIS, bukan per
        // dokumen: satu baris tanpa tanggal tidak boleh menyeret baris lain
        // yang bertanggal kembali ke tata letak lama.
        $baris = [
            ['label' => 'Innova Reborn', 'date' => '2026-08-15', 'date_end' => '2026-08-16', 'detail' => 'Dengan Sopir', 'amount' => 1_700_000],
            ['label' => 'Biaya parkir', 'detail' => 'Tol & parkir', 'amount' => 150_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 1_850_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 1_850_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('<td class="k">15/08/2026 – 16/08/2026</td>', $html);
        $this->assertStringContainsString('<td class="k">Biaya parkir</td>', $html);
    }

    public function test_baris_additional_dari_pengajuan_biaya_ikut_tata_letak_rental(): void
    {
        // CostRequestController::appendAdditionalCharge() menulis label
        // 'Additional' dengan tanggal berformat 'M d, Y' dan tanpa date_end.
        // Tanggalnya tetap dicetak apa adanya (bukan ISO), tapi tempatnya ikut
        // aturan dokumen — kolom kiri untuk rental.
        $baris = [
            ['label' => 'Additional', 'date' => 'Aug 15, 2026', 'detail' => 'Biaya tambahan disetujui', 'amount' => 500_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 500_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 500_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('<td class="k">Aug 15, 2026</td>', $html);
        $this->assertStringContainsString('Biaya tambahan disetujui', $html);
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

    public function test_total_pax_tidak_dicetak_untuk_komposisi_baris_bernominal(): void
    {
        // Rental tidak mengenal jumlah peserta (§8.7) dan sejak Fase 3 pax tidak
        // ikut menghitung totalnya sama sekali — base-nya 0, seluruh nilai
        // datang dari baris bernominal. Mencetak "Total Pax : 10 pax" ke
        // customer menyatakan angka yang tidak menjelaskan apa pun di dokumen
        // itu. Patokannya aturan jenis (totalComposition), BUKAN tour.pax:
        // nilainya tetap terisi di database untuk semua jenis.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 2_050_000, [
            'description_lines' => self::BARIS_RENTAL,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_050_000, pax: 10, lines: self::BARIS_RENTAL);

        $this->assertStringNotContainsString('Total Pax', $html);
        $this->assertStringNotContainsString('10 pax', $html);
        // Rinciannya tetap utuh — yang hilang hanya baris pax, bukan isi invoice.
        $this->assertStringContainsString('Innova Reborn', $html);
    }

    public function test_total_pax_tetap_dicetak_untuk_jenis_per_unit(): void
    {
        // Pasangan pengunci: pada jenis per_unit pax justru MENJELASKAN totalnya
        // (baris "Price : × N pax"), jadi menghapusnya di sana akan membuang
        // keterangan yang customer butuhkan.
        foreach (array_diff(self::SALES_TYPES, ['rental']) as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 7]), 100_000);

            $html = $this->renderInvoice($invoice, unitPrice: 100_000, pax: 7);

            $this->assertStringContainsString('Total Pax', $html, "Jenis {$type}");
            $this->assertStringContainsString('7 pax', $html, "Jenis {$type}");
        }
    }
}
