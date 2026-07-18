<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name, string $role): User
    {
        return User::create([
            'name'     => $name,
            'email'    => strtolower(str_replace(' ', '.', $name)) . '@test.local',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_sales_only_sees_own_tours_plus_unowned_legacy_tours(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');
        $salesB = $this->makeUser('Sales B', 'sales');
        $admin  = $this->makeUser('Admin', 'admin');

        $tourA      = Tour::create(['pax' => 1, 'type' => 'tour', 'created_by' => $salesA->id]);
        $tourB      = Tour::create(['pax' => 1, 'type' => 'tour', 'created_by' => $salesB->id]);
        $tourLegacy = Tour::create(['pax' => 1, 'type' => 'tour']); // created_by null

        $visibleToA = Tour::visibleTo($salesA)->pluck('id');
        $this->assertTrue($visibleToA->contains($tourA->id), 'Sales harus melihat tour miliknya sendiri.');
        $this->assertFalse($visibleToA->contains($tourB->id), 'Sales tidak boleh melihat tour milik sales lain.');
        $this->assertTrue($visibleToA->contains($tourLegacy->id), 'Tour lama tanpa pemilik harus tetap tampil ke semua sales.');

        $visibleToAdmin = Tour::visibleTo($admin)->pluck('id');
        $this->assertTrue($visibleToAdmin->contains($tourA->id));
        $this->assertTrue($visibleToAdmin->contains($tourB->id));

        $this->assertTrue($tourA->isAccessibleBy($salesA));
        $this->assertFalse($tourA->isAccessibleBy($salesB));
        $this->assertTrue($tourA->isAccessibleBy($admin));
        $this->assertTrue($tourLegacy->isAccessibleBy($salesB));
    }

    public function test_sales_cannot_open_edit_page_of_other_sales_tour(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');
        $salesB = $this->makeUser('Sales B', 'sales');
        $tourA  = Tour::create(['pax' => 1, 'type' => 'tour', 'status' => 'inquiry', 'created_by' => $salesA->id]);

        $this->actingAs($salesB)->get(route('tours.edit', $tourA))->assertForbidden();
        $this->actingAs($salesA)->get(route('tours.edit', $tourA))->assertOk();
    }

    public function test_sales_cannot_delete_other_sales_tour(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');
        $salesB = $this->makeUser('Sales B', 'sales');
        $tourA  = Tour::create(['pax' => 1, 'type' => 'tour', 'status' => 'inquiry', 'created_by' => $salesA->id]);

        $this->actingAs($salesB)->delete(route('tours.destroy', $tourA))->assertForbidden();
        $this->assertDatabaseHas('tours', ['id' => $tourA->id]);
    }

    public function test_creating_tour_sets_owner_and_creates_followup_reminder(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');

        $this->actingAs($salesA)->post(route('tours.store'), [
            'type'   => 'tour',
            'pax'    => 2,
            'status' => 'inquiry',
        ])->assertRedirect();

        $tour = Tour::firstOrFail();
        $this->assertSame($salesA->id, $tour->created_by);

        $reminder = Reminder::where('tour_id', $tour->id)->first();
        $this->assertNotNull($reminder, 'Membuat inquiry harus otomatis membuat reminder follow-up.');
        $this->assertSame($salesA->id, $reminder->user_id);
        $this->assertSame(now()->addDay()->toDateString(), $reminder->remind_at->toDateString());
    }

    public function test_status_change_chains_reminder_and_final_status_stops_it(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');
        $tour = Tour::create(['pax' => 1, 'type' => 'tour', 'status' => 'inquiry', 'created_by' => $salesA->id]);

        $this->actingAs($salesA)->patch(route('tours.update', $tour), ['status' => 'quotation_sent']);

        $this->assertSame(1, Reminder::where('is_done', false)->count(), 'Reminder lanjutan H+1 harus dibuat saat status berubah.');

        $this->actingAs($salesA)->patch(route('tours.update', $tour), ['status' => 'confirmed']);

        $this->assertSame(0, Reminder::where('is_done', false)->count(), 'Status akhir (confirmed) tidak boleh membuat reminder baru.');
    }

    public function test_dashboard_scopes_tour_count_for_sales_but_not_admin(): void
    {
        $salesA = $this->makeUser('Sales A', 'sales');
        $salesB = $this->makeUser('Sales B', 'sales');
        $admin  = $this->makeUser('Admin', 'admin');

        Tour::create(['pax' => 1, 'type' => 'tour', 'created_by' => $salesA->id]);
        Tour::create(['pax' => 1, 'type' => 'tour', 'created_by' => $salesB->id]);

        $this->actingAs($salesA)->get(route('dashboard'))->assertInertia(
            fn ($page) => $page->where('totalTours', 1)
        );

        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(
            fn ($page) => $page->where('totalTours', 2)
        );
    }
}
