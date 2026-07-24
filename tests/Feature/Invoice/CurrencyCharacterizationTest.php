<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci kapan total_idr terisi.
 *
 * IDR → terisi langsung saat sinkronisasi. Non-IDR → sengaja dibiarkan kosong
 * sampai disetujui, supaya laporan IDR tidak terdistorsi kurs placeholder
 * sebelum kurs pasti diinput.
 */
class CurrencyCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_idr_mengisi_total_idr_langsung(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $this->assertEquals(1_000_000, $invoice->total);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }

    public function test_non_idr_membiarkan_total_idr_kosong_sebelum_disetujui(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        $this->assertEquals(1_000, $invoice->total);

        // CATATAN: nilainya 0.00, BUKAN null — sudah diverifikasi empiris.
        // Dokumen desain menyebutnya "dibiarkan kosong"; kenyataannya kolom
        // punya default 0. Efek praktisnya sama (laporan IDR tidak terdistorsi
        // kurs placeholder), tapi penulisan test harus mengikuti kenyataan.
        // assertNotNull WAJIB mendahului: assertEquals(0, null) LOLOS di PHPUnit,
        // jadi tanpa penjaga ini regresi ke null pada Fase 2 tidak akan tertangkap
        // padahal justru itu yang ingin dikunci di sini.
        $this->assertNotNull(
            $invoice->total_idr,
            'total_idr harus tetap ada sebagai 0, bukan berubah jadi null'
        );
        $this->assertEquals(
            0,
            $invoice->total_idr,
            'total_idr tetap 0 sampai kurs pasti diinput saat persetujuan'
        );
    }

    public function test_persetujuan_non_idr_mengisi_total_idr_dari_kurs(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        // baseline_total sengaja DIBEDAKAN dari total. Server hanya mensyaratkan
        // baseline_total > 0, dan dengan nilai yang berbeda test dapat membuktikan
        // konversi memakai `total` — bukan `baseline_total` yang kebetulan sama.
        $invoice->update(['baseline_total' => 1]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice), ['exchange_rate' => 16_000])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(16_000, $invoice->exchange_rate);
        $this->assertEquals(16_000_000, $invoice->total_idr, '1.000 USD × 16.000');
    }

    public function test_persetujuan_idr_memakai_kurs_satu(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice = $this->approveInvoice($invoice);

        $this->assertEquals(1, $invoice->exchange_rate);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }

    public function test_proforma_idr_menyetel_kurs_satu_dan_mengisi_total_idr(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 100); // isi awal apa saja, akan ditimpa proforma di bawah

        // Paksa kurs menyimpang dari 1 dulu, supaya test benar-benar
        // membuktikan updateProforma() yang MENYETEL ULANG ke 1 saat currency
        // diisi IDR — bukan cuma kebetulan memakai nilai default kolom (yang
        // juga 1).
        $invoice->update(['exchange_rate' => 5]);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 250_000,
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1, $invoice->exchange_rate, 'IDR selalu kurs 1 — updateProforma() menyetelnya ulang');
        $this->assertEquals(1_000_000, $invoice->total, '250.000 × 4 pax');
        $this->assertEquals(1_000_000, $invoice->total_idr, 'IDR mengisi total_idr langsung saat sinkronisasi');
    }

    /**
     * WART YANG DIKARAKTERISASI, BUKAN DIRESTUI: begitu sebuah invoice pernah
     * berstatus IDR (sehingga total_idr terisi oleh syncProformaTotal()), lalu
     * mata uangnya dipindah ke non-IDR lewat updateProforma(), total_idr TIDAK
     * ikut dikosongkan atau disinkronkan ulang. Ini karena syncProformaTotal()
     * hanya menulis total_idr ketika currency saat ini IDR (lihat Invoice::
     * syncProformaTotal()) — untuk mata uang lain kolom itu sengaja dibiarkan
     * menunggu kurs pasti saat approve(). Efek sampingnya: kolom total_idr
     * memuat NILAI IDR ERA LAMA yang basi, sementara `total` sudah mencerminkan
     * angka USD yang baru. Beberapa laporan finance membaca total_idr — jadi
     * nilai basi ini bisa memberi angka yang salah sampai invoice disetujui
     * (approve() baru menghitung ulang total_idr dari kurs). Test ini MENCATAT
     * kondisi itu apa adanya; ia bukan pernyataan bahwa perilaku ini benar.
     */
    public function test_ganti_mata_uang_dari_idr_ke_usd_meninggalkan_total_idr_lama_yang_basi(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000); // IDR — total_idr langsung terisi

        $this->assertEquals(1_000_000, $invoice->total_idr, 'Prasyarat: total_idr sudah terisi era IDR');

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'USD',
                'unit_price' => 300,
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals('USD', $invoice->currency);
        $this->assertEquals(1_200, $invoice->total, '300 × 4 pax — total sudah mencerminkan mata uang baru');
        $this->assertEquals(
            1_000_000,
            $invoice->total_idr,
            'total_idr TIDAK ikut berubah — nilai IDR era lama basi tertinggal di kolom ini'
        );
    }
}
