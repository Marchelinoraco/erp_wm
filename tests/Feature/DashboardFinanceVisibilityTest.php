<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Pipeline Dashboard (`/dashboard`, role:admin,sales) membocorkan angka
 * finansial company-wide (Profit Riil, Diterima Bulan Ini, box "Ringkasan
 * Keuangan") ke role sales. Hanya admin (dan accountant, future-proofing —
 * lihat docs/superpowers/specs/2026-07-29-dashboard-keuangan-restricted-design.md
 * §4) yang boleh melihat keenam field finansial itu.
 */
class DashboardFinanceVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin.financevis@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    public function test_sales_tidak_melihat_data_finansial(): void
    {
        $sales = $this->salesUser();

        $response = $this->actingAs($sales)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('canViewFinance', false)
            ->missing('confirmedSell')
            ->missing('actualCost')
            ->missing('realProfit')
            ->missing('arOutstanding')
            ->missing('apOutstanding')
            ->missing('cashInMonth'));
    }

    public function test_admin_tetap_melihat_data_finansial(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('canViewFinance', true)
            ->has('confirmedSell')
            ->has('actualCost')
            ->has('realProfit')
            ->has('arOutstanding')
            ->has('apOutstanding')
            ->has('cashInMonth'));
    }
}
