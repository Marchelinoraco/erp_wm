<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
