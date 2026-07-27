<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    public function test_admin_bisa_membuat_akun_dengan_nomor_whatsapp(): void
    {
        $admin = $this->makeUser('Admin Satu', 'admin');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name'     => 'Sales Baru',
                'email'    => 'sales.baru@test.local',
                'phone'    => '081234567890',
                'password' => 'password123',
                'role'     => 'sales',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('081234567890', User::where('email', 'sales.baru@test.local')->first()->phone);
    }

    public function test_admin_bisa_mengubah_nomor_whatsapp_akun_lain(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');

        $this->actingAs($admin)
            ->patch(route('users.update', $salesA->id), [
                'name'  => $salesA->name,
                'email' => $salesA->email,
                'phone' => '087654321000',
                'role'  => 'sales',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('087654321000', $salesA->fresh()->phone);
    }

    public function test_nomor_whatsapp_boleh_dikosongkan(): void
    {
        $admin  = $this->makeUser('Admin Satu', 'admin');
        $salesA = $this->makeUser('Sales A');
        $salesA->update(['phone' => '081111111111']);

        $this->actingAs($admin)
            ->patch(route('users.update', $salesA->id), [
                'name'  => $salesA->name,
                'email' => $salesA->email,
                'phone' => '',
                'role'  => 'sales',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($salesA->fresh()->phone);
    }

    public function test_sales_tidak_bisa_mengakses_kelola_akun(): void
    {
        $salesA = $this->makeUser('Sales A');

        // Middleware role: mengarahkan (302) ke homePath() utk request biasa,
        // bukan 403 — lihat EnsureUserHasRole. Efeknya sales tak pernah sampai
        // ke halaman Kelola Akun.
        $this->actingAs($salesA)
            ->get(route('users.index'))
            ->assertRedirect(route('dashboard'));
    }
}
