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

    public function contraCategory()
    {
        return $this->belongsTo(FinCategory::class, 'contra_fin_category_id');
    }

    /**
     * Jurnal otomatis (double-entry seimbang) dari satu transaksi kas.
     * - Pendapatan (in) : Debit Kas/Bank, Kredit kategori pendapatan
     * - Pengeluaran (out): Debit kategori beban, Kredit Kas/Bank
     * Lawan transaksi adalah akun kas, atau kategori lawan bila transaksinya non-kas.
     *
     * @return array<int, array{account: string, debit: float, credit: float}>
     */
    public function journalLines(): array
    {
        $lawan = $this->cashAccount?->name ?? $this->contraCategory?->name ?? 'Kas';
        $cat   = $this->category?->name ?? '-';
        $amt   = (float) $this->amount;

        return $this->direction === 'in'
            ? [
                ['account' => $lawan, 'debit' => $amt, 'credit' => 0],
                ['account' => $cat,   'debit' => 0,    'credit' => $amt],
            ]
            : [
                ['account' => $cat,   'debit' => $amt, 'credit' => 0],
                ['account' => $lawan, 'debit' => 0,    'credit' => $amt],
            ];
    }

    /**
     * Spek §3.2: tepat satu lawan. Ditegakkan di model, bukan lewat CHECK
     * constraint, supaya perilakunya sama di MySQL dan SQLite dan pesannya
     * terbaca manusia.
     */
    protected static function booted(): void
    {
        static::saving(function (self $trx) {
            $punyaKas   = ! is_null($trx->cash_account_id);
            $punyaLawan = ! is_null($trx->contra_fin_category_id);

            if ($punyaKas === $punyaLawan) {
                throw new \InvalidArgumentException(
                    'Transaksi keuangan harus punya tepat satu lawan: akun kas ATAU kategori lawan.'
                );
            }
        });
    }
}
