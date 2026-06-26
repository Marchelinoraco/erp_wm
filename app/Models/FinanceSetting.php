<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';
    protected $guarded    = [];
    protected $casts      = ['value' => 'decimal:2'];

    public static function get(string $key, float $default = 0.0): float
    {
        return (float) (static::where('key', $key)->value('value') ?? $default);
    }
}
