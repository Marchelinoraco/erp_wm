<?php

namespace Tests\Feature\Finance;

use App\Models\FinCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix wave final review (2026-08-01), Temuan #1 (Important): destroyCategory()
 * sudah menolak MENGHAPUS kategori is_system, tapi updateCategory() tidak
 * menolak mengganti NAMANYA. Kategori "Piutang Karyawan"/"Gaji Karyawan"
 * dicari oleh KODE lewat nama persis (EmployeeAdvanceController::store(),
 * PayrollProcessor::pay()) — mengganti namanya lewat layar Transaksi biasa
 * (role accountant, bukan hanya admin) membuat kas bon dan gajian gagal 404.
 */
class CategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccountant(): User
    {
        return User::create([
            'name'     => 'Akuntan',
            'email'    => 'akuntan@test.local',
            'password' => bcrypt('password'),
            'role'     => 'accountant',
        ]);
    }

    /**
     * Fix-specific (a): kategori is_system gagal diganti namanya, dengan
     * pesan yang menjelaskan kenapa (dipakai kode lewat nama di tempat lain).
     */
    public function test_kategori_is_system_gagal_diganti_nama(): void
    {
        $kategori = FinCategory::create(['name' => 'Piutang Karyawan', 'type' => 'asset', 'is_system' => true]);

        $response = $this->actingAs($this->makeAccountant())
            ->patch(route('finance.categories.update', $kategori), ['name' => 'Piutang Lain-lain']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('fin_categories', ['id' => $kategori->id, 'name' => 'Piutang Karyawan']);
    }

    /**
     * Fix-specific (b): kategori is_system TETAP bisa toggle is_active — tidak
     * ada alasan menonaktifkannya berbahaya secara berbeda dari kategori biasa.
     */
    public function test_kategori_is_system_tetap_bisa_toggle_is_active(): void
    {
        $kategori = FinCategory::create([
            'name' => 'Gaji Karyawan', 'type' => 'expense', 'is_system' => true, 'is_active' => true,
        ]);

        $response = $this->actingAs($this->makeAccountant())
            ->patch(route('finance.categories.update', $kategori), [
                'name' => 'Gaji Karyawan', 'is_active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fin_categories', ['id' => $kategori->id, 'is_active' => false]);
    }

    /**
     * Fix-specific (c) — regresi: kategori biasa (bukan is_system) tetap bisa
     * diganti namanya seperti sebelumnya, guard baru tidak melonggarkan atau
     * merusak alur yang sah.
     */
    public function test_kategori_biasa_tetap_bisa_diganti_nama_regresi(): void
    {
        $kategori = FinCategory::create(['name' => 'Operasional', 'type' => 'expense', 'is_system' => false]);

        $response = $this->actingAs($this->makeAccountant())
            ->patch(route('finance.categories.update', $kategori), ['name' => 'Operasional Kantor']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fin_categories', ['id' => $kategori->id, 'name' => 'Operasional Kantor']);
    }
}
