<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerPushToBrevoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-key', 'services.brevo.base_url' => 'https://api.brevo.com/v3']);
    }

    private function sales(): User
    {
        return User::create([
            'name' => 'Sales A', 'email' => 'sales@test.local',
            'password' => bcrypt('password'), 'role' => 'sales',
        ]);
    }

    public function test_pushes_customer_with_firstname_and_selected_list(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $customer = Customer::create(['name' => 'Budi Santoso', 'email' => 'budi@x.com', 'type' => 'direct']);

        $this->actingAs($this->sales())
            ->post(route('marketing.customers.push', $customer), ['list_ids' => [7]])
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['email'] === 'budi@x.com'
            && $request['attributes'] === ['FIRSTNAME' => 'Budi Santoso']
            && $request['listIds'] === [7]
            && $request['updateEnabled'] === true);
    }

    public function test_customer_already_in_brevo_is_success_not_duplicate_error(): void
    {
        // updateEnabled=true membuat Brevo memperbarui, bukan menolak 400.
        Http::fake(['*' => Http::response(['id' => 99])]);

        $customer = Customer::create(['name' => 'Lama', 'email' => 'sudah.ada@x.com', 'type' => 'direct']);

        $this->actingAs($this->sales())
            ->post(route('marketing.customers.push', $customer), ['list_ids' => [7]])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_customer_without_email_is_rejected_and_never_calls_brevo(): void
    {
        Http::fake();

        $customer = Customer::create(['name' => 'Tanpa Email', 'type' => 'direct']); // email null

        $this->actingAs($this->sales())
            ->post(route('marketing.customers.push', $customer), ['list_ids' => [7]])
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_brevo_failure_does_not_crash_and_reports_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@x.com', 'type' => 'direct']);

        $this->actingAs($this->sales())
            ->post(route('marketing.customers.push', $customer), ['list_ids' => [7]])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
