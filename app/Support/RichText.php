<?php

namespace App\Support;

class RichText
{
    /**
     * Deskripsi itinerary bisa berupa teks polos (data lama) atau HTML
     * sederhana dari editor rich text. Kembalikan HTML aman untuk PDF:
     * teks polos di-escape + nl2br, HTML dibatasi ke tag format dasar
     * tanpa atribut.
     */
    public static function toPdfHtml(?string $value): string
    {
        if (! $value) {
            return '';
        }

        if (! preg_match('/<[a-z][^>]*>/i', $value)) {
            return nl2br(e($value));
        }

        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $value);
        $html  = strip_tags($value, '<p><br><strong><b><em><i><u><ul><ol><li>');

        return preg_replace('/<(\/?)([a-z0-9]+)[^>]*>/i', '<$1$2>', $html);
    }
}
