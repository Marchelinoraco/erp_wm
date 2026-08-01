<?php

namespace Tests\Feature\Finance;

use App\Models\FinCategory;
use App\Models\FinTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix wave final review (2026-08-01), Temuan #6 (Important): destroyCategory()
 * hanya mengecek relasi transactions() (fin_category_id). Kategori yang HANYA
 * dipakai sebagai contra_fin_category_id lolos guard ini lalu gagal di level
 * database dengan QueryException (FK restrictOnDelete dari Task 2) — user
 * melihat error 500 mentah, bukan pesan 422 yang rapi.
 */
class CategoryDeletionTest extends TestCase
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

    /**
     * Fix-specific: kategori yang hanya dipakai sebagai kategori LAWAN
     * (contra_fin_category_id) harus ditolak dengan 422 rapi, bukan 500.
     */
    public function test_menghapus_kategori_yang_dipakai_sebagai_lawan_dikembalikan_422_bukan_500(): void
    {
        $admin   = $this->makeAdmin();
        $beban   = FinCategory::create(['name' => 'Beban Gaji', 'type' => 'expense']);
        $piutang = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset']);

        FinTransaction::create([
            'date' => '2026-07-30', 'direction' => 'out',
            'fin_category_id' => $beban->id, 'contra_fin_category_id' => $piutang->id,
            'amount' => 1_000_000,
        ]);

        $response = $this->actingAs($admin)->delete(route('finance.categories.destroy', $piutang));

        $response->assertStatus(422);
        $this->assertDatabaseHas('fin_categories', ['id' => $piutang->id]);
    }

    /**
     * Bukti regresi: guard yang sudah ada (kategori dipakai sebagai
     * fin_category_id utama) tetap menghasilkan 422 seperti sebelumnya —
     * penambahan pengecekan contra tidak boleh melonggarkan atau merusak
     * guard lama.
     */
    public function test_menghapus_kategori_yang_dipakai_sebagai_kategori_utama_tetap_422_regresi(): void
    {
        $admin = $this->makeAdmin();
        $beban = FinCategory::create(['name' => 'Operasional', 'type' => 'expense']);

        FinTransaction::create([
            'date' => '2026-07-30', 'direction' => 'out', 'fin_category_id' => $beban->id,
            'cash_account_id' => \App\Models\CashAccount::create(['name' => 'Kas Besar', 'type' => 'cash'])->id,
            'amount' => 500_000,
        ]);

        $response = $this->actingAs($admin)->delete(route('finance.categories.destroy', $beban));

        $response->assertStatus(422);
        $this->assertDatabaseHas('fin_categories', ['id' => $beban->id]);
    }

    /**
     * Bukti regresi: kategori yang TIDAK dipakai sama sekali (bukan sebagai
     * fin_category_id maupun contra_fin_category_id) tetap bisa dihapus
     * seperti sebelumnya — guard baru tidak boleh menolak kasus yang sah.
     */
    public function test_menghapus_kategori_yang_tidak_dipakai_sama_sekali_tetap_berhasil_regresi(): void
    {
        $admin  = $this->makeAdmin();
        $bebas  = FinCategory::create(['name' => 'Kategori Tak Terpakai', 'type' => 'expense']);

        $response = $this->actingAs($admin)->delete(route('finance.categories.destroy', $bebas));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('fin_categories', ['id' => $bebas->id]);
    }
}
