<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CashAccount;
use App\Models\FinTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Perbaikan #3 rekonsiliasi Laba Rugi vs Buku Besar (10 Sep 2026): membereskan
 * baris `fin_transactions` yang terlanjur salah di produksi sebelum dua
 * perbaikan kode sebelumnya dipasang.
 *
 * Dua jenis kerusakan, arahnya berlawanan:
 *
 *  - HANTU — baris buku besar yang pembayarannya sudah dihapus. Lahir dari
 *    `$bill->payments()->delete()` yang melewati event model (12 baris) dan dari
 *    cascade database di era sebelum soft delete (2 baris). Total Rp 17.272.000
 *    beban & pendapatan fiktif.
 *
 *  - HILANG — pembayaran hidup yang tidak punya baris buku besar. Lahir saat
 *    disk server penuh 4 Sep 2026 (7 baris, Rp 2.470.000). Uang keluar sungguhan
 *    yang tidak pernah masuk laporan.
 *
 * Invarian yang ditegakkan perintah ini: setiap pembayaran hidup punya tepat
 * satu baris buku besar, dan setiap baris buku besar bersumber AR/AP punya
 * pembayaran hidup. Transaksi `source = 'manual'` tidak pernah disentuh.
 */
class RapikanBukuBesarTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function buatPembayaranBill(float $jumlah = 650_000): BillPayment
    {
        $tour = $this->makeTour('tour');
        $akun = CashAccount::firstOrCreate(['name' => 'Kas Uji'], ['type' => 'cash']);

        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Sewa bus',
            'category'    => 'transport',
            'date'        => '2026-09-01',
            'amount'      => $jumlah,
            'status'      => 'unpaid',
        ]);

        return BillPayment::create([
            'bill_id'         => $bill->id,
            'date'            => '2026-09-04',
            'amount'          => $jumlah,
            'method'          => 'transfer',
            'cash_account_id' => $akun->id,
        ]);
    }

    public function test_menghapus_baris_buku_besar_yang_pembayarannya_sudah_dihapus(): void
    {
        $payment = $this->buatPembayaranBill();

        // Hapus massal lewat query builder — persis jalur lama BillController
        // yang melewati event model, sehingga baris buku besarnya tertinggal.
        BillPayment::where('id', $payment->id)->delete();

        $this->assertSame(1, FinTransaction::where('source', 'bill')->count(), 'Prasyarat: hantunya memang terbentuk.');

        $this->artisan('keuangan:rapikan-buku-besar')->assertSuccessful();

        $this->assertSame(
            0,
            FinTransaction::where('source', 'bill')->count(),
            'Baris buku besar yang pembayarannya sudah dihapus harus ikut dibersihkan — '
            . 'kalau tidak, beban di laporan tetap menghitung uang yang tidak jadi keluar.'
        );
    }

    public function test_membuat_ulang_baris_buku_besar_yang_hilang(): void
    {
        $payment = $this->buatPembayaranBill(2_470_000);

        // Baris buku besarnya lenyap sementara pembayarannya tetap hidup —
        // persis kondisi 4 Sep 2026 saat disk penuh menggagalkan penulisan.
        FinTransaction::where('source', 'bill')->where('source_id', $payment->id)->delete();

        $this->artisan('keuangan:rapikan-buku-besar')->assertSuccessful();

        $baris = FinTransaction::where('source', 'bill')->where('source_id', $payment->id)->first();

        $this->assertNotNull($baris, 'Pembayaran yang masih hidup harus punya baris buku besar.');
        $this->assertSame('out', $baris->direction);
        $this->assertEquals(2_470_000, (float) $baris->amount);
        $this->assertSame('2026-09-04', $baris->date->format('Y-m-d'), 'Tanggalnya harus mengikuti tanggal pembayaran, bukan tanggal perbaikan dijalankan.');
    }

    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $hantu = $this->buatPembayaranBill(100_000);
        BillPayment::where('id', $hantu->id)->delete();
        $idHantu = FinTransaction::where('source_id', $hantu->id)->value('id');

        $hilang = $this->buatPembayaranBill(200_000);
        FinTransaction::where('source', 'bill')->where('source_id', $hilang->id)->delete();

        $this->artisan('keuangan:rapikan-buku-besar', ['--dry-run' => true])->assertSuccessful();

        $this->assertNotNull(
            FinTransaction::find($idHantu),
            'Dry run tidak boleh menghapus apa pun — ini satu-satunya cara aman meninjau sebelum menyentuh produksi.'
        );
        $this->assertSame(
            0,
            FinTransaction::where('source', 'bill')->where('source_id', $hilang->id)->count(),
            'Dry run tidak boleh membuat baris baru.'
        );
    }

    public function test_tidak_menyentuh_baris_sehat_maupun_transaksi_manual(): void
    {
        $sehat   = $this->buatPembayaranBill(300_000);
        $idSehat = FinTransaction::where('source', 'bill')->where('source_id', $sehat->id)->value('id');

        // Transaksi yang diketik akuntan sendiri — tidak punya pembayaran
        // sebagai sumber, jadi rawan disalahartikan sebagai yatim.
        $manual = FinTransaction::create([
            'date'            => '2026-09-01',
            'direction'       => 'out',
            'fin_category_id' => \App\Models\FinCategory::where('name', 'Operasional')->value('id'),
            'cash_account_id' => CashAccount::first()->id,
            'amount'          => 50_000,
            'description'     => 'Beli ATK',
            'source'          => 'manual',
        ]);

        $this->artisan('keuangan:rapikan-buku-besar')->assertSuccessful();

        $this->assertNotNull(FinTransaction::find($idSehat), 'Baris sehat tidak boleh ikut terhapus.');
        $this->assertNotNull(
            FinTransaction::find($manual->id),
            'Transaksi manual tidak punya pembayaran sumber — perintah ini tidak boleh menganggapnya yatim.'
        );
    }

    public function test_idempoten_jalan_kedua_tidak_mengubah_apa_pun(): void
    {
        $hantu = $this->buatPembayaranBill(100_000);
        BillPayment::where('id', $hantu->id)->delete();

        $hilang = $this->buatPembayaranBill(200_000);
        FinTransaction::where('source', 'bill')->where('source_id', $hilang->id)->delete();

        $this->artisan('keuangan:rapikan-buku-besar')->assertSuccessful();
        $setelahJalanPertama = FinTransaction::orderBy('id')->pluck('amount', 'id')->all();

        $this->artisan('keuangan:rapikan-buku-besar')->assertSuccessful();

        $this->assertSame(
            $setelahJalanPertama,
            FinTransaction::orderBy('id')->pluck('amount', 'id')->all(),
            'Aman dijalankan berulang — kalau terputus di tengah jalan, tinggal ulangi tanpa takut merusak.'
        );
    }
}
