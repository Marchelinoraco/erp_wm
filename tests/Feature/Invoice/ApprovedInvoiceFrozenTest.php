<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * JAMINAN INTI (spec §7.4), DIBATASI PADA KETIGA RUTE HTTP YANG DIJAGA
 * (invoices.proforma, invoices.baseline, invoices.approve): lewat ketiga rute
 * itu, invoice yang sudah disetujui tidak akan pernah dihitung ulang, apa pun
 * yang berubah pada rumus atau pada data tour.
 *
 * syncProformaTotal() dipanggil di tiga tempat itu — updateProforma(), lockBaseline(),
 * dan approve() — dan ketiganya didahului ensureNotApproved(). Test ini menutup
 * ketiga jalur itu sekaligus.
 *
 * PENTING — batas jaminan ini: ensureNotApproved() adalah penjaga di CONTROLLER,
 * BUKAN di model. Invoice::syncProformaTotal() sendiri, bila dipanggil langsung
 * pada model (misalnya dari command/backfill), TIDAK diperiksa is_approved sama
 * sekali dan TETAP menghitung ulang total. Lihat
 * test_sync_proforma_total_langsung_TIDAK_dijaga_dan_ini_disengaja_dicatat()
 * di bawah untuk pencatatan sengaja atas kerentanan ini.
 *
 * Test ini WAJIB tetap hijau di setiap fase. Bila ia merah, hentikan pekerjaan:
 * artinya data keuangan yang sudah masuk pembukuan bisa berubah.
 */
class ApprovedInvoiceFrozenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function approvedInvoice(): Invoice
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice = $this->approveInvoice($invoice);

        $this->assertNotNull($invoice->approved_at, 'Prasyarat: invoice harus benar-benar tersetujui');
        $this->assertEquals(5_000_000, $invoice->total);

        return $invoice;
    }

    public function test_perubahan_pax_tour_tidak_menggeser_total_invoice_yang_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        // Ubah pax tour secara drastis SETELAH invoice disetujui.
        $invoice->tour->update(['pax' => 99]);

        // Penting: mengubah pax saja tidak memanggil syncProformaTotal(), jadi
        // memeriksa total di sini tanpa berbuat apa-apa akan lolos meski
        // penjaganya dihapus. Test harus benar-benar MENCOBA menyinkronkan
        // lewat jalur yang dijaga, lalu membuktikan percobaan itu ditolak.
        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(
            5_000_000,
            $invoice->fresh()->total,
            'Total invoice yang sudah masuk Keuangan tidak boleh ikut berubah'
        );
        $this->assertEquals(
            10,
            $invoice->fresh()->pax,
            'pax invoice tetap 10 seperti saat disetujui, bukan 99 dari tour'
        );
    }

    public function test_ketiga_jalur_perhitungan_ulang_ditolak_setelah_disetujui(): void
    {
        $invoice = $this->approvedInvoice();
        $user    = $this->salesUser();

        // Jalur 1 — updateProforma()
        $this->actingAs($user)
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 1,
            ])
            ->assertSessionHasErrors('invoice');

        // Jalur 2 — lockBaseline()
        $this->actingAs($user)
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        // Jalur 3 — approve()
        $this->actingAs($user)
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(5_000_000, $invoice->fresh()->total, 'Total tetap utuh setelah ketiga percobaan');
        $this->assertEquals(500_000, $invoice->fresh()->unit_price, 'unit_price tetap utuh');
    }

    public function test_invoice_yang_disetujui_tidak_bisa_dihapus(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->delete(route('invoices.destroy', $invoice))
            ->assertSessionHasErrors('invoice');

        // Invoice::find() menghormati SoftDeletingScope; $invoice->fresh() TIDAK
        // (ia memakai newQueryWithoutScopes), sehingga fresh() akan tetap
        // mengembalikan baris yang sudah ter-soft-delete dan asersinya jadi mati.
        $this->assertNotNull(
            Invoice::find($invoice->id),
            'Invoice yang sudah masuk Keuangan tidak boleh hilang'
        );
        $this->assertNull(
            $invoice->fresh()->deleted_at,
            'Invoice tidak boleh ter-soft-delete'
        );
    }

    /**
     * CATATAN TAJAM, DISENGAJA: Invoice::syncProformaTotal() TIDAK memiliki
     * pemeriksaan is_approved sama sekali. Penjaganya (ensureNotApproved())
     * hanya ada di InvoiceController, satu tingkat di ATAS model — bukan di
     * dalam method model itu sendiri.
     *
     * Konsekuensinya: memanggil $invoice->syncProformaTotal() LANGSUNG pada
     * model (tanpa lewat rute invoices.proforma/baseline/approve) tetap
     * menghitung ulang total invoice yang sudah disetujui, mengikuti pax tour
     * yang terbaru. Ini dicatat sengaja karena rencana backfill data pada fase
     * berikutnya bisa saja ditulis sesederhana
     * `Invoice::each(fn ($i) => $i->syncProformaTotal())` — baris seperti itu
     * akan diam-diam menimpa ulang total invoice yang sudah masuk Keuangan di
     * produksi, padahal seluruh test HTTP di atas tetap hijau karena mereka
     * hanya menguji jalur yang dijaga controller.
     *
     * Bila test ini suatu saat berubah jadi MERAH, itu artinya seseorang telah
     * menambahkan penjaga is_approved DI DALAM syncProformaTotal() sendiri —
     * itu sebuah PERUBAHAN yang disengaja pada model, bukan sebuah kerusakan.
     */
    public function test_sync_proforma_total_langsung_TIDAK_dijaga_dan_ini_disengaja_dicatat(): void
    {
        $invoice = $this->approvedInvoice();

        // Ubah pax tour secara drastis SETELAH invoice disetujui.
        $invoice->tour->update(['pax' => 99]);

        // Panggil method MODEL secara langsung — tidak lewat rute HTTP mana
        // pun, sehingga ensureNotApproved() di controller sama sekali tidak
        // dilalui/diuji di sini.
        $invoice->syncProformaTotal();

        $this->assertEquals(
            49_500_000,
            $invoice->fresh()->total,
            'syncProformaTotal() yang dipanggil langsung pada model TIDAK dijaga is_approved — '
                . 'total ikut berubah mengikuti pax baru tour (99 × 500.000). Ini kerentanan nyata '
                . 'yang wajib dihindari saat menulis backfill di fase berikutnya, bukan bug pada test ini.'
        );
    }
}
