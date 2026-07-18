<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillPayment extends Model
{
    use SoftDeletes;

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
