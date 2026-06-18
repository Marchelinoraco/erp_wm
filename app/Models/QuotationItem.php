<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'tour_id', 'product_id', 'label', 'qty', 'nights', 'pax_mode',
        'unit_sell', 'notes', 'status', 'sort_order',
    ];

    protected $casts = [
        'unit_sell' => 'decimal:2',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getLineSellAttribute(): float
    {
        return (float) $this->qty * $this->nights * $this->unit_sell;
    }
}
