<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull as Middleware;

class ConvertEmptyStringsToNull extends Middleware
{
    /**
     * Field teks quotation yang BOLEH disimpan sebagai string kosong.
     *
     * Tujuannya membedakan dua kondisi yang tampak sama di PDF:
     *  - null  = belum pernah diisi  → PDF pakai teks default perusahaan.
     *  - ''    = sengaja dikosongkan → PDF menyembunyikan bagian itu.
     *
     * Tanpa pengecualian ini, middleware bawaan mengubah '' menjadi null
     * sehingga bagian yang sengaja dihapus tetap muncul (pakai default).
     */
    protected array $keepEmptyString = ['included', 'excluded', 'child_policy', 'terms'];

    protected function transform($key, $value)
    {
        if (in_array($key, $this->keepEmptyString, true)) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
