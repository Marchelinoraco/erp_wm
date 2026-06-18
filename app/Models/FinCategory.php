<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function transactions()
    {
        return $this->hasMany(FinTransaction::class);
    }

    public function scopeIncome($q)  { return $q->where('type', 'income'); }
    public function scopeExpense($q) { return $q->where('type', 'expense'); }
}
