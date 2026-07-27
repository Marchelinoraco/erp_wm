<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name, string $role = 'sales'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => strtolower(str_replace(' ', '.', $name)) . '@test.local',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_sales_hanya_melihat_reminder_miliknya_sendiri(): void
    {
        $salesA = $this->makeUser('Sales A');
        $salesB = $this->makeUser('Sales B');
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Punya A', 'remind_at' => today()]);
        Reminder::create(['user_id' => $salesB->id, 'title' => 'Punya B', 'remind_at' => today()]);

        $this->actingAs($salesA)
            ->get(route('reminders.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Reminders/Index')
                ->has('reminders', 1)
                ->where('reminders.0.title', 'Punya A')
                ->where('salesAccounts', []));
    }

    public function test_admin_melihat_semua_reminder_dari_semua_akun_tanpa_filter(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');
        $salesB = $this->makeUser('Sales B');
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Punya A', 'remind_at' => today()]);
        Reminder::create(['user_id' => $salesB->id, 'title' => 'Punya B', 'remind_at' => today()]);

        $this->actingAs($admin)
            ->get(route('reminders.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Reminders/Index')
                ->has('reminders', 2)
                ->where('reminders.0.user.name', fn ($name) => in_array($name, ['Sales A', 'Sales B']))
                ->has('salesAccounts', 3)); // admin + salesA + salesB
    }

    public function test_admin_bisa_filter_reminder_ke_satu_akun_sales(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');
        $salesB = $this->makeUser('Sales B');
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Punya A', 'remind_at' => today()]);
        Reminder::create(['user_id' => $salesB->id, 'title' => 'Punya B', 'remind_at' => today()]);

        $this->actingAs($admin)
            ->get(route('reminders.index', ['user_id' => $salesA->id]))
            ->assertInertia(fn ($page) => $page
                ->has('reminders', 1)
                ->where('reminders.0.title', 'Punya A')
                ->where('filterUserId', $salesA->id));
    }

    public function test_admin_bisa_menandai_selesai_reminder_milik_sales_lain(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');
        $reminder = Reminder::create(['user_id' => $salesA->id, 'title' => 'Punya A', 'remind_at' => today(), 'is_done' => false]);

        $this->actingAs($admin)
            ->patch(route('reminders.done', $reminder->id))
            ->assertRedirect();

        $this->assertTrue($reminder->fresh()->is_done, 'Admin harus bisa menandai selesai reminder milik sales lain.');
    }

    public function test_admin_bisa_menghapus_reminder_milik_sales_lain(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');
        $reminder = Reminder::create(['user_id' => $salesA->id, 'title' => 'Punya A', 'remind_at' => today()]);

        $this->actingAs($admin)
            ->delete(route('reminders.destroy', $reminder->id))
            ->assertRedirect();

        $this->assertNull(Reminder::find($reminder->id), 'Admin harus bisa menghapus reminder milik sales lain.');
    }

    public function test_sales_masih_tidak_bisa_mengubah_reminder_milik_sales_lain(): void
    {
        $salesA = $this->makeUser('Sales A');
        $salesB = $this->makeUser('Sales B');
        $reminder = Reminder::create(['user_id' => $salesB->id, 'title' => 'Punya B', 'remind_at' => today(), 'is_done' => false]);

        $this->actingAs($salesA)
            ->patch(route('reminders.done', $reminder->id))
            ->assertForbidden();

        $this->assertFalse($reminder->fresh()->is_done, 'Sales tidak boleh menandai selesai reminder milik sales lain.');
    }
}
