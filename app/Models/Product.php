<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cost'             => 'decimal:2',
        'sell'             => 'decimal:2',
        'pending_cost'     => 'decimal:2',
        'is_active'        => 'boolean',
        'price_updated_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
