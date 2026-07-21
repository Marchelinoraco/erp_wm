<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.brevo.key' => 'test-key', 'services.brevo.base_url' => 'https://api.brevo.com/v3']);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name'     => ucfirst($role),
            'email'    => $role . '@test.local',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_index_lists_contacts_and_uses_correct_offset_for_page_two(): void
    {
        Http::fake([
            '*/contacts/lists*' => Http::response(['lists' => [['id' => 7, 'name' => 'Customer ERP']]]),
            '*/contacts*'       => Http::response(['contacts' => [['email' => 'a@x.com']], 'count' => 5359]),
        ]);

        $this->actingAs($this->makeUser('sales'))
            ->get(route('marketing.contacts.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Contacts/Index')
                ->where('count', 5359)
                ->where('error', null));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'offset=50'));
    }

    public function test_search_by_exact_email_shows_single_result(): void
    {
        Http::fake([
            '*/contacts/lists*'      => Http::response(['lists' => []]),
            '*/contacts/ada%40x.com' => Http::response(['email' => 'ada@x.com']),
        ]);

        $this->actingAs($this->makeUser('sales'))
            ->get(route('marketing.contacts.index', ['email' => 'ada@x.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('count', 1));
    }

    public function test_search_for_missing_email_shows_not_found_not_a_connection_error(): void
    {
        Http::fake([
            '*/contacts/lists*' => Http::response(['lists' => []]),
            '*'                 => Http::response(['message' => 'Contact not found'], 404),
        ]);

        $this->actingAs($this->makeUser('sales'))
            ->get(route('marketing.contacts.index', ['email' => 'nobody@x.com']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('count', 0)
                ->where('error', null)); // 404 bukan error sistem
    }

    public function test_store_creates_contact_with_selected_lists(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $this->actingAs($this->makeUser('sales'))
            ->post(route('marketing.contacts.store'), [
                'email'    => 'baru@x.com',
                'name'     => 'Budi',
                'list_ids' => [7],
            ])->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['email'] === 'baru@x.com'
            && $request['updateEnabled'] === true
            && $request['listIds'] === [7]);
    }

    public function test_page_survives_when_brevo_is_down(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $this->actingAs($this->makeUser('sales'))
            ->get(route('marketing.contacts.index'))
            ->assertOk() // BUKAN 500
            ->assertInertia(fn ($page) => $page->where('contacts', [])->whereNot('error', null));
    }

    public function test_shows_not_configured_message_and_calls_nothing_when_api_key_missing(): void
    {
        Http::fake();
        config(['services.brevo.key' => null]);

        $this->actingAs($this->makeUser('sales'))
            ->get(route('marketing.contacts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('error', fn ($e) => str_contains((string) $e, 'belum dikonfigurasi')));

        Http::assertNothingSent();
    }

    public function test_lists_endpoint_returns_brevo_lists_for_the_picker(): void
    {
        Http::fake(['*' => Http::response(['lists' => [['id' => 7, 'name' => 'Customer ERP']]])]);

        $this->actingAs($this->makeUser('sales'))
            ->getJson(route('marketing.lists'))
            ->assertOk()
            ->assertJson(['lists' => [['id' => 7, 'name' => 'Customer ERP']], 'error' => null]);
    }

    public function test_lists_endpoint_degrades_gracefully_when_brevo_fails(): void
    {
        Http::fake(['*' => Http::response(['message' => 'boom'], 500)]);

        $response = $this->actingAs($this->makeUser('sales'))
            ->getJson(route('marketing.lists'))
            ->assertOk(); // BUKAN 500

        $this->assertSame([], $response->json('lists'));
        $this->assertNotNull($response->json('error'));
    }

    public function test_accountant_cannot_access_marketing_contacts(): void
    {
        Http::fake();

        // Konvensi EnsureUserHasRole: request non-JSON dialihkan ke home path
        // masing-masing role (403 hanya untuk JSON). Yang penting: ditolak.
        $this->actingAs($this->makeUser('accountant'))
            ->get(route('marketing.contacts.index'))
            ->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_guide_cannot_access_marketing_contacts_via_json_either(): void
    {
        Http::fake();

        $this->actingAs($this->makeUser('guide'))
            ->getJson(route('marketing.contacts.index'))
            ->assertForbidden();
    }
}
