<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeAdvance extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function finTransaction()
    {
        return $this->belongsTo(FinTransaction::class);
    }

    /**
     * Spek D10: sisa DIHITUNG, bukan disimpan. amount dikurangi total
     * payroll_item_lines berjenis kas_bon yang merujuk kas bon ini.
     *
     * `PayrollItemLine` belum ada sampai Task 5 membuat model & tabelnya.
     * Guard class_exists() di sini (pola sama dengan EmployeeController::index())
     * supaya sisa() tidak fatal error sebelum Task 5 lahir — begitu Task 5
     * membuat kelasnya, baris di bawah otomatis mulai menghitung potongan
     * sungguhan tanpa perlu menyentuh method ini lagi.
     */
    public function sisa(): float
    {
        $dipotong = class_exists(PayrollItemLine::class)
            ? PayrollItemLine::where('employee_advance_id', $this->id)->sum('amount')
            : 0;

        return round((float) $this->amount - (float) $dipotong, 2);
    }

    /**
     * Spek §5: kas bon yang sudah pernah dipotong tidak bisa dihapus.
     */
    public function sudahDipotong(): bool
    {
        return class_exists(PayrollItemLine::class)
            && PayrollItemLine::where('employee_advance_id', $this->id)->exists();
    }
}
