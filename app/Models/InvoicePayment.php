<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date'];

    /**
     * Pembayaran dientri dalam mata uang invoice. Simpan ekuivalen IDR
     * (amount × kurs invoice) untuk buku besar/Keuangan yang berbasis IDR.
     */
    protected static function booted(): void
    {
        static::saving(function (self $payment) {
            $rate = (float) ($payment->invoice?->exchange_rate ?: 1);
            $payment->amount_idr = (float) $payment->amount * $rate;
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
