<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Tour;
use App\Models\TourItem;
use App\Models\User;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\Support\FakeSalesLineRule;
use Tests\Support\FakeSalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Bug production: kartu "Profit Riil" di Pipeline Dashboard menjumlah
 * tour_items.line_sell untuk SEMUA tipe tour, padahal tipe 'tour' sengaja
 * tidak memakai tour_items — profit-nya resminya dari invoice.total_idr
 * (docs/logika-pembuatan-invoice/08-perbedaan-per-tipe.md §8.3/§8.4). Akibatnya
 * tour tipe 'tour' yang sudah lunas tapi tour_items-nya kosong (kondisi wajar
 * untuk tipe ini) terbaca dashboard sebagai "biaya besar, jualan nol".
 */
class DashboardProfitRiilTourTypeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin.dashboard@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    private function makeBill(Tour $tour, float $amount): Bill
    {
        return Bill::create([
            'tour_id'     => $tour->id,
            'description' => 'Biaya uji',
            'category'    => 'other',
            'date'        => now()->toDateString(),
            'amount'      => $amount,
            'status'      => 'unpaid',
        ]);
    }

    public function test_tour_tipe_tour_pakai_invoice_total_bukan_tour_items(): void
    {
        $admin = $this->makeAdmin();

        $tour = $this->makeTour('tour', ['pax' => 1]);
        $this->approveInvoice($this->makeInvoice($tour, 10_000_000));
        $this->makeBill($tour, 3_000_000);

        // Kondisi nyata di production: tour tipe 'tour' tidak punya tour_items
        // sama sekali, karena kolom itu memang tidak dipakai untuk tipe ini.
        $this->assertSame(0, $tour->items()->count());

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('confirmedSell', 10_000_000)
            ->where('actualCost', 3_000_000)
            ->where('realProfit', 7_000_000));
    }

    public function test_tour_tipe_rental_tetap_pakai_tour_items_seperti_semula(): void
    {
        $admin = $this->makeAdmin();

        $tour = $this->makeTour('rental');
        TourItem::create([
            'tour_id'   => $tour->id,
            'unit_sell' => 2_000_000,
            'unit_cost' => 1_200_000,
        ]);
        $this->makeBill($tour, 500_000);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('confirmedSell', 2_000_000)
            ->where('actualCost', 500_000)
            ->where('realProfit', 1_500_000));
    }

    public function test_sumber_confirmed_sell_ditentukan_registry(): void
    {
        // Dashboard dulu mengulang aturan profit dengan tangan. Registry palsu
        // ini menyatakan `rental` menghitung dari tagihan: bila dashboard
        // benar-benar membaca aturan itu, angkanya beralih ke invoice (10jt);
        // bila masih hardcode `type === 'tour'`, ia tetap dari tour_items (2jt).
        $this->app->instance(
            SalesLineRuleRegistry::class,
            new FakeSalesLineRuleRegistry(new FakeSalesLineRule(profitFromRevenue: true))
        );

        $admin = $this->makeAdmin();

        $tour = $this->makeTour('rental', ['pax' => 1]);
        TourItem::create([
            'tour_id'   => $tour->id,
            'unit_sell' => 2_000_000,
            'unit_cost' => 1_200_000,
        ]);
        $this->approveInvoice($this->makeInvoice($tour, 10_000_000));

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('confirmedSell', 10_000_000));
    }
}
