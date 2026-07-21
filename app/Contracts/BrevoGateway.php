<?php

namespace App\Contracts;

/**
 * Kontrak ke Brevo REST API. Controller bergantung pada interface ini, bukan
 * pada detail HTTP — supaya mudah di-fake saat test dan provider bisa diganti.
 */
interface BrevoGateway
{
    /** @return array{contacts: array<int, array>, count: int} */
    public function contacts(int $limit = 50, int $offset = 0): array;

    /** Kembalikan null bila kontak tidak ada (Brevo 404), bukan exception. */
    public function findContact(string $email): ?array;

    /** Membuat/memperbarui kontak. Email yang sudah ada bukan error. */
    public function createContact(string $email, array $attributes = [], array $listIds = []): array;

    /** @return array<int, array> Daftar list Brevo untuk picker. */
    public function lists(): array;

    /** Event email transaksional (delivered/opened/bounced) untuk satu alamat. */
    public function emailEvents(string $email, int $limit = 50): array;
}
