<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    protected $guarded = [];
    protected $casts   = ['date' => 'date'];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class);
    }
}
