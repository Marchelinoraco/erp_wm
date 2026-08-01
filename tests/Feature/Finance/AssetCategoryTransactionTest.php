<?php

namespace Tests\Feature\Finance;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 11: layar Transaksi harus mengenali kategori bertipe `asset`. Dua
 * validasi backend berikut sebelumnya menolak kategori aset sama sekali:
 *
 * 1. storeCategory() hanya mengizinkan type income/expense.
 * 2. validateTransaction() memaksa kategori harus persis income (in) atau
 *    expense (out) -- kategori asset (mis. Piutang Karyawan dari kas bon,
 *    Task 7-10) selalu ditolak di kedua arah.
 *
 * Kategori asset SAH untuk kedua arah: 'out' = kas bon diberikan (piutang
 * bertambah), 'in' = kas bon dilunasi (piutang berkurang). Perbaikan ini
 * TIDAK BOLEH melonggarkan validasi income/expense yang sudah benar --
 * lihat test regresi di bagian bawah file.
 */
class AssetCategoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Keuangan',
            'email'    => 'admin.keuangan@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    // ── storeCategory() menerima type=asset ─────────────────────────────────

    public function test_membuat_kategori_bertipe_asset_berhasil(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('finance.categories.store'), [
                'name' => 'Piutang Karyawan',
                'type' => 'asset',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fin_categories', [
            'name' => 'Piutang Karyawan',
            'type' => 'asset',
        ]);
    }

    // ── validateTransaction() menerima kategori asset di kedua arah ─────────

    public function test_transaksi_out_dengan_kategori_asset_diterima_kas_bon_diberikan(): void
    {
        $admin   = $this->makeAdmin();
        $kas     = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'date'            => '2026-08-01',
                'direction'       => 'out',
                'fin_category_id' => $piutang->id,
                'cash_account_id' => $kas->id,
                'amount'          => 500_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fin_transactions', [
            'fin_category_id' => $piutang->id,
            'direction'       => 'out',
            'amount'          => 500_000,
        ]);
    }

    public function test_transaksi_in_dengan_kategori_asset_diterima_kas_bon_dilunasi(): void
    {
        $admin   = $this->makeAdmin();
        $kas     = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'date'            => '2026-08-01',
                'direction'       => 'in',
                'fin_category_id' => $piutang->id,
                'cash_account_id' => $kas->id,
                'amount'          => 500_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fin_transactions', [
            'fin_category_id' => $piutang->id,
            'direction'       => 'in',
            'amount'          => 500_000,
        ]);
    }

    // ── Regresi: kombinasi income/expense yang salah TETAP ditolak ──────────

    public function test_transaksi_in_dengan_kategori_expense_tetap_ditolak_422(): void
    {
        $admin  = $this->makeAdmin();
        $kas    = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $beban  = FinCategory::create(['name' => 'Operasional', 'type' => 'expense']);

        $response = $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'date'            => '2026-08-01',
            'direction'       => 'in',
            'fin_category_id' => $beban->id,
            'cash_account_id' => $kas->id,
            'amount'          => 500_000,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('fin_transactions', [
            'fin_category_id' => $beban->id,
        ]);
    }

    public function test_transaksi_out_dengan_kategori_income_tetap_ditolak_422(): void
    {
        $admin      = $this->makeAdmin();
        $kas        = CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash']);
        $pendapatan = FinCategory::create(['name' => 'Penjualan Tour', 'type' => 'income']);

        $response = $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'date'            => '2026-08-01',
            'direction'       => 'out',
            'fin_category_id' => $pendapatan->id,
            'cash_account_id' => $kas->id,
            'amount'          => 500_000,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('fin_transactions', [
            'fin_category_id' => $pendapatan->id,
        ]);
    }
}
