<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Bug production (ditemukan 10 Sep 2026 lewat rekonsiliasi Laba Rugi vs Buku
 * Besar): pembayaran tersimpan tetapi baris fin_transactions-nya TIDAK, karena
 * LedgerSync menulis di observer `saved` — SETELAH baris pembayaran commit.
 * Kalau langkah kedua gagal, langkah pertama tetap permanen.
 *
 * Di produksi jalur itu tercapai pada 4 September 2026 saat disk server penuh:
 * 7 pembayaran bill senilai Rp 2.470.000 tersimpan tanpa jejak di buku besar,
 * dan tidak ada satu pun peringatan yang muncul. Uang keluar, laporan tidak tahu.
 *
 * Test ini menempuh cabang kode yang sama lewat kondisi yang bisa direproduksi:
 * kategori sistem yang dicari LedgerSync tidak ditemukan. Yang diuji bukan
 * penyebab kegagalannya, melainkan syarat yang tidak boleh dilanggar — pembayaran
 * dan pencatatannya harus jadi bersama atau batal bersama.
 */
class PembayaranAtomikTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_pembayaran_bill_dibatalkan_kalau_buku_besar_gagal_ditulis(): void
    {
        $accountant  = $this->accountantUser();
        $tour        = $this->makeTour('tour');
        $cashAccount = CashAccount::create(['name' => 'Kas Uji', 'type' => 'cash']);

        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Sewa bus',
            'category'    => 'transport',
            'date'        => '2026-09-01',
            'amount'      => 650_000,
            'status'      => 'unpaid',
        ]);

        // Fondasi buku besar hilang → LedgerSync tidak bisa menulis apa pun.
        FinCategory::where('name', 'Biaya Supplier')->delete();

        $this->actingAs($accountant)->post(route('bill-payments.store', $bill), [
            'date'            => '2026-09-04',
            'amount'          => 650_000,
            'method'          => 'transfer',
            'cash_account_id' => $cashAccount->id,
        ]);

        $this->assertSame(
            0,
            BillPayment::count(),
            'Pembayaran tidak boleh tersimpan kalau baris buku besarnya gagal dibuat — '
            . 'itulah yang membuat Rp 2.470.000 hilang dari pembukuan pada 4 Sep 2026.'
        );

        $this->assertSame(
            0,
            FinTransaction::count(),
            'Tidak boleh ada baris buku besar yatim ketika pembayarannya dibatalkan.'
        );
    }

    public function test_pembayaran_invoice_dibatalkan_kalau_buku_besar_gagal_ditulis(): void
    {
        $accountant  = $this->accountantUser();
        $tour        = $this->makeTour('tour');
        $cashAccount = CashAccount::create(['name' => 'Kas Uji', 'type' => 'cash']);

        $invoice = Invoice::create([
            'tour_id' => $tour->id,
            'number'  => 'INV-2026-09-0001',
            'date'    => '2026-09-01',
            'total'   => 1_000_000,
        ]);

        FinCategory::where('name', 'Penjualan Tour')->delete();

        $this->actingAs($accountant)->post(route('invoice-payments.store', $invoice), [
            'date'            => '2026-09-04',
            'amount'          => 1_000_000,
            'method'          => 'transfer',
            'cash_account_id' => $cashAccount->id,
        ]);

        $this->assertSame(
            0,
            InvoicePayment::count(),
            'Sisi piutang punya cacat yang sama seperti sisi hutang — uang masuk '
            . 'tidak boleh tercatat tanpa baris buku besarnya.'
        );

        $this->assertSame(0, FinTransaction::count());
    }
}
