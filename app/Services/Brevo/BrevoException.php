<?php

namespace App\Services\Brevo;

use RuntimeException;

/**
 * Kegagalan saat berkomunikasi dengan Brevo (down, 401, rate limit, 5xx).
 * Ditangkap controller agar halaman tetap tampil dengan pesan, bukan 500.
 */
class BrevoException extends RuntimeException
{
}
