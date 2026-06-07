<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date', 'due_date' => 'date'];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function getPaidAttribute(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->total - $this->paid;
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $inv) {
            if (! $inv->number) {
                $year = now()->year;
                $seq  = static::whereYear('created_at', $year)->count() + 1;
                $inv->number = sprintf('INV-%d-%04d', $year, $seq);
            }
        });
    }
}
