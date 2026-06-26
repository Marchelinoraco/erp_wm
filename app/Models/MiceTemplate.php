<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiceTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
    ];
}
