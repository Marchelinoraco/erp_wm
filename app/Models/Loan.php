<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date'           => 'date',
        'original_amount'      => 'decimal:2',
        'monthly_installment'  => 'decimal:2',
        'outstanding_balance'  => 'decimal:2',
        'tenor_months'         => 'integer',
        'is_active'            => 'boolean',
    ];

    public const TYPES = [
        'bank_loan' => 'Hutang Bank',
        'leasing'   => 'Leasing / KPM',
        'other'     => 'Pinjaman Lainnya',
    ];
}
