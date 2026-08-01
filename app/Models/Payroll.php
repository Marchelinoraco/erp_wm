<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['paid_date' => 'date'];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }
}
