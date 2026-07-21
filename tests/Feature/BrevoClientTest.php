<?php

namespace Tests\Feature;

use App\Contracts\BrevoGateway;
use App\Services\Brevo\BrevoNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoClientTest extends TestCase
{
    private function gateway(): BrevoGateway
    {
        config(['services.brevo.key' => 'test-key', 'services.brevo.base_url' => 'https://api.brevo.com/v3']);

        return app(BrevoGateway::class);
    }

    public function test_contacts_sends_api_key_header_with_limit_and_offset(): void
    {
        Http::fake(['*' => Http::response(['contacts' => [['email' => 'a@x.com']], 'count' => 5359])]);

        $result = $this->gateway()->contacts(limit: 50, offset: 100);

        $this->assertSame(5359, $result['count']);
        $this->assertCount(1, $result['contacts']);

        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-key')
            && str_contains($request->url(), 'limit=50')
            && str_contains($request->url(), 'offset=100'));
    }

    public function test_find_contact_returns_null_when_brevo_answers_404(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Contact not found'], 404)]);

        // 404 = kontak tidak ada. Harus null, BUKAN exception — supaya UI bisa
        // membedakan "tidak ditemukan" dari "Brevo bermasalah".
        $this->assertNull($this->gateway()->findContact('nobody@x.com'));
    }

    public function test_find_contact_returns_data_when_found(): void
    {
        Http::fake(['*' => Http::response(['email' => 'ada@x.com', 'emailBlacklisted' => false])]);

        $this->assertSame('ada@x.com', $this->gateway()->findContact('ada@x.com')['email']);
    }

    public function test_create_contact_always_sends_update_enabled_so_existing_email_is_not_an_error(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $this->gateway()->createContact('a@x.com', ['FIRSTNAME' => 'Andi'], [7]);

        Http::assertSent(fn ($request) => $request['email'] === 'a@x.com'
            && $request['updateEnabled'] === true
            && $request['listIds'] === [7]
            && $request['attributes'] === ['FIRSTNAME' => 'Andi']);
    }

    public function test_lists_returns_brevo_lists(): void
    {
        Http::fake(['*' => Http::response(['lists' => [['id' => 7, 'name' => 'Customer ERP']], 'count' => 1])]);

        $this->assertSame('Customer ERP', $this->gateway()->lists()[0]['name']);
    }

    public function test_throws_not_configured_and_makes_no_request_when_api_key_is_empty(): void
    {
        Http::fake();
        config(['services.brevo.key' => null]);

        try {
            app(BrevoGateway::class)->contacts();
            $this->fail('Harus melempar BrevoNotConfiguredException saat API key kosong.');
        } catch (BrevoNotConfiguredException) {
            Http::assertNothingSent();
        }
    }
}
