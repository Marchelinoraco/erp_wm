<?php

namespace App\Services\Brevo;

/**
 * BREVO_API_KEY belum diisi. Dibedakan dari BrevoException supaya UI bisa
 * bilang "belum dikonfigurasi" alih-alih menampilkan 401 yang membingungkan.
 */
class BrevoNotConfiguredException extends BrevoException
{
}
