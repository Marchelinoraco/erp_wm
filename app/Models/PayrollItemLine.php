<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItemLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2'];

    public function item()
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function advance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
}
