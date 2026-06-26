<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalCorrection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'year'       => 'integer',
        'amount'     => 'decimal:2',
        'sort_order' => 'integer',
    ];
}
