<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date', 'due_date' => 'date'];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function getPaidAttribute(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->amount - $this->paid;
    }
}
