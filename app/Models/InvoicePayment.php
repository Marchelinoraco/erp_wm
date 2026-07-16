<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date'];

    /**
     * Pembayaran dientri dalam mata uang invoice, kurs SENDIRI per pembayaran
     * (bukan kurs tunggal yang dikunci di invoice) — DP tanggal 1 dan pelunasan
     * tanggal 4 bisa punya kurs berbeda, masing-masing dikonversi ke IDR
     * dengan kursnya sendiri. Kosong = pakai kurs invoice (kompatibel dengan
     * data lama & invoice IDR yang kursnya selalu 1).
     */
    protected static function booted(): void
    {
        static::saving(function (self $payment) {
            $rate = (float) ($payment->exchange_rate ?: ($payment->invoice?->exchange_rate ?: 1));
            $payment->exchange_rate = $rate;
            $payment->amount_idr    = (float) $payment->amount * $rate;
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }
}
