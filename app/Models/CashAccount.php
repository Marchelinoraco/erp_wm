<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active'       => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function transactions()
    {
        return $this->hasMany(FinTransaction::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
