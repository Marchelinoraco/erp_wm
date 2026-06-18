<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(FinCategory::class, 'fin_category_id');
    }

    public function cashAccount()
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * Jurnal otomatis (double-entry seimbang) dari satu transaksi kas.
     * - Pendapatan (in) : Debit Kas/Bank, Kredit kategori pendapatan
     * - Pengeluaran (out): Debit kategori beban, Kredit Kas/Bank
     *
     * @return array<int, array{account: string, debit: float, credit: float}>
     */
    public function journalLines(): array
    {
        $cash = $this->cashAccount?->name ?? 'Kas';
        $cat  = $this->category?->name ?? '-';
        $amt  = (float) $this->amount;

        return $this->direction === 'in'
            ? [
                ['account' => $cash, 'debit' => $amt, 'credit' => 0],
                ['account' => $cat,  'debit' => 0,    'credit' => $amt],
            ]
            : [
                ['account' => $cat,  'debit' => $amt, 'credit' => 0],
                ['account' => $cash, 'debit' => 0,    'credit' => $amt],
            ];
    }
}
