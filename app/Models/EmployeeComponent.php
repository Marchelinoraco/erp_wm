<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeComponent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
