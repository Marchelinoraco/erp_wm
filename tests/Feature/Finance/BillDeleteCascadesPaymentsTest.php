<?php

namespace Tests\Feature\Finance;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CashAccount;
use App\Models\FinTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Bug production: hapus Bill hanya soft-delete Bill-nya, payments-nya tetap
 * aktif — BillPayment::sum('amount') di FinanceController lalu menghitung
 * pembayaran untuk bill yang sudah tidak ada, membuat Hutang (AP) jadi minus.
 */
class BillDeleteCascadesPaymentsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function makeAccountant(): User
    {
        return User::create([
            'name'     => 'Akuntan Uji',
            'email'    => 'akuntan@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }

    public function test_hapus_bill_ikut_hapus_payments_supaya_hutang_ap_tidak_salah(): void
    {
        $accountant  = $this->makeAccountant();
        $tour        = $this->makeTour('tour');
        $cashAccount = CashAccount::create(['name' => 'Kas Uji', 'type' => 'cash']);

        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Sewa bus',
            'category'    => 'transport',
            'date'        => now()->toDateString(),
            'amount'      => 26_000_000,
            'status'      => 'unpaid',
        ]);

        $payment = BillPayment::create([
            'bill_id'         => $bill->id,
            'date'            => now()->toDateString(),
            'amount'          => 52_000_000,
            'method'          => 'transfer',
            'cash_account_id' => $cashAccount->id,
        ]);

        $this->actingAs($accountant)
            ->delete(route('bills.destroy', $bill))
            ->assertRedirect();

        $this->assertSoftDeleted($bill);
        $this->assertSoftDeleted($payment);

        // Bill terhapus tidak lagi tercatat sebagai hutang, dan pembayarannya
        // pun tidak lagi ikut dihitung — bukan menyeret Hutang (AP) jadi minus.
        $this->assertSame(0.0, (float) Bill::sum('amount'));
        $this->assertSame(0.0, (float) BillPayment::sum('amount'));
    }

    /**
     * Celah yang lolos dari test di atas: ia memeriksa payments-nya ter-soft-delete,
     * tapi tidak pernah memeriksa fin_transactions. Padahal `$bill->payments()->delete()`
     * adalah penghapusan massal lewat query builder — Laravel tidak menyalakan event
     * `deleted` per model, jadi BillPaymentObserver tidak jalan dan LedgerSync::remove()
     * tidak pernah dipanggil. Baris buku besarnya tertinggal jadi hantu.
     *
     * Di produksi per 10 Sep 2026 ada 12 baris seperti ini senilai Rp 16.272.000 yang
     * menggelembungkan beban di Buku Besar untuk tagihan yang sudah dibatalkan.
     */
    public function test_hapus_bill_ikut_menghapus_baris_buku_besar_pembayarannya(): void
    {
        $accountant  = $this->makeAccountant();
        $tour        = $this->makeTour('tour');
        $cashAccount = CashAccount::create(['name' => 'Kas Uji', 'type' => 'cash']);

        $bill = Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Sewa bus',
            'category'    => 'transport',
            'date'        => '2026-08-01',
            'amount'      => 16_272_000,
            'status'      => 'unpaid',
        ]);

        BillPayment::create([
            'bill_id'         => $bill->id,
            'date'            => '2026-08-07',
            'amount'          => 16_272_000,
            'method'          => 'transfer',
            'cash_account_id' => $cashAccount->id,
        ]);

        // Pastikan barisnya memang terbentuk dulu — kalau tidak, test ini akan
        // hijau karena ketiadaan, bukan karena penghapusan berhasil.
        $this->assertSame(1, FinTransaction::where('source', 'bill')->count());

        $this->actingAs($accountant)
            ->delete(route('bills.destroy', $bill))
            ->assertRedirect();

        $this->assertSame(
            0,
            FinTransaction::where('source', 'bill')->count(),
            'Baris buku besar harus ikut hilang ketika tagihannya dihapus — '
            . 'kalau tidak, beban di Buku Besar tetap menghitung uang yang tidak jadi keluar.'
        );
    }
}
