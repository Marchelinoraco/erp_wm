<?php

namespace App\Services\Brevo;

use App\Contracts\BrevoGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Satu-satunya tempat aplikasi berbicara HTTP ke Brevo.
 *
 * Catatan: Brevo v3 memakai header `api-key`, BUKAN `Authorization: Bearer`
 * (Http::withToken() akan gagal autentikasi).
 */
class BrevoClient implements BrevoGateway
{
    public function contacts(int $limit = 50, int $offset = 0): array
    {
        $data = $this->get('/contacts', ['limit' => $limit, 'offset' => $offset]);

        return [
            'contacts' => $data['contacts'] ?? [],
            'count'    => $data['count'] ?? 0,
        ];
    }

    public function findContact(string $email): ?array
    {
        $response = $this->request()->get('/contacts/' . urlencode($email));

        // 404 = kontak memang tidak ada. Bukan kegagalan sistem.
        if ($response->status() === 404) {
            return null;
        }

        return $this->unwrap($response);
    }

    public function createContact(string $email, array $attributes = [], array $listIds = []): array
    {
        // updateEnabled: email yang sudah terdaftar diperbarui & ditambahkan ke
        // list, bukan ditolak sebagai duplicate_parameter.
        $payload = [
            'email'         => $email,
            'updateEnabled' => true,
        ];

        if ($attributes !== []) {
            $payload['attributes'] = $attributes;
        }

        if ($listIds !== []) {
            $payload['listIds'] = array_map('intval', $listIds);
        }

        return $this->unwrap($this->request()->post('/contacts', $payload)) ?? [];
    }

    public function lists(): array
    {
        return $this->get('/contacts/lists', ['limit' => 50])['lists'] ?? [];
    }

    public function emailEvents(string $email, int $limit = 50): array
    {
        return $this->get('/smtp/statistics/events', ['email' => $email, 'limit' => $limit])['events'] ?? [];
    }

    private function get(string $path, array $query = []): array
    {
        return $this->unwrap($this->request()->get($path, $query)) ?? [];
    }

    private function request(): PendingRequest
    {
        $key = config('services.brevo.key');

        if (blank($key)) {
            throw new BrevoNotConfiguredException(
                'Integrasi Brevo belum dikonfigurasi (BREVO_API_KEY kosong).'
            );
        }

        return Http::withHeaders(['api-key' => $key, 'accept' => 'application/json'])
            ->baseUrl(config('services.brevo.base_url'))
            ->timeout(10);
    }

    private function unwrap(Response $response): ?array
    {
        if ($response->failed()) {
            throw new BrevoException(
                'Gagal menghubungi Brevo (HTTP ' . $response->status() . ').'
            );
        }

        try {
            return $response->json();
        } catch (Throwable) {
            throw new BrevoException('Respons Brevo tidak bisa dibaca.');
        }
    }
}
