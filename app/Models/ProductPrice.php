<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'label', 'start_date', 'end_date',
        'cost', 'sell', 'pending_cost', 'status',
        'submitted_by', 'submitted_at', 'is_active', 'notes',
    ];

    protected $casts = [
        'start_date'   => 'date:Y-m-d',
        'end_date'     => 'date:Y-m-d',
        'cost'         => 'decimal:2',
        'sell'         => 'decimal:2',
        'pending_cost' => 'decimal:2',
        'submitted_at' => 'datetime',
        'is_active'    => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
