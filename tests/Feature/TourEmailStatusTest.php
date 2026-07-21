<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TourEmailStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-key', 'services.brevo.base_url' => 'https://api.brevo.com/v3']);
    }

    private function sales(string $email = 'sales@test.local'): User
    {
        return User::create([
            'name' => 'Sales', 'email' => $email,
            'password' => bcrypt('password'), 'role' => 'sales',
        ]);
    }

    private function tourFor(User $owner, ?string $customerEmail = 'budi@x.com'): Tour
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => $customerEmail, 'type' => 'direct']);

        return Tour::create([
            'pax' => 1, 'type' => 'tour', 'status' => 'inquiry',
            'created_by' => $owner->id, 'customer_id' => $customer->id,
        ]);
    }

    public function test_returns_summary_of_delivery_and_opens(): void
    {
        Http::fake(['*' => Http::response(['events' => [
            ['event' => 'delivered', 'date' => '2026-07-20T10:00:00.000Z'],
            ['event' => 'opened',    'date' => '2026-07-20T11:00:00.000Z'],
            ['event' => 'opened',    'date' => '2026-07-20T12:00:00.000Z'],
        ]])]);

        $sales = $this->sales();

        $this->actingAs($sales)
            ->getJson(route('tours.email-status', $this->tourFor($sales)))
            ->assertOk()
            ->assertJson([
                'configured' => true,
                'email'      => 'budi@x.com',
                'summary'    => ['delivered' => 1, 'opened' => 2],
                'error'      => null,
            ]);
    }

    public function test_no_activity_is_not_an_error(): void
    {
        Http::fake(['*' => Http::response(['events' => []])]);

        $sales = $this->sales();

        $this->actingAs($sales)
            ->getJson(route('tours.email-status', $this->tourFor($sales)))
            ->assertOk()
            ->assertJson(['summary' => [], 'error' => null]);
    }

    public function test_brevo_down_reports_error_without_crashing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $sales = $this->sales();

        $response = $this->actingAs($sales)
            ->getJson(route('tours.email-status', $this->tourFor($sales)))
            ->assertOk(); // BUKAN 500

        $this->assertNotNull($response->json('error'));
    }

    public function test_missing_api_key_reports_not_configured_and_calls_nothing(): void
    {
        Http::fake();
        config(['services.brevo.key' => null]);

        $sales = $this->sales();

        $this->actingAs($sales)
            ->getJson(route('tours.email-status', $this->tourFor($sales)))
            ->assertOk()
            ->assertJson(['configured' => false]);

        Http::assertNothingSent();
    }

    public function test_tour_without_customer_email_calls_nothing(): void
    {
        Http::fake();

        $sales = $this->sales();

        $this->actingAs($sales)
            ->getJson(route('tours.email-status', $this->tourFor($sales, null)))
            ->assertOk()
            ->assertJson(['email' => null, 'summary' => []]);

        Http::assertNothingSent();
    }

    public function test_sales_cannot_read_email_status_of_another_sales_tour(): void
    {
        Http::fake();

        $owner = $this->sales('owner@test.local');
        $other = $this->sales('other@test.local');

        $this->actingAs($other)
            ->getJson(route('tours.email-status', $this->tourFor($owner)))
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
