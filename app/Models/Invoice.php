<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];
    protected $casts   = [
        'date'              => 'date',
        'due_date'          => 'date',
        'approved_at'       => 'datetime',
        'description_lines' => 'array',
        'exchange_rate'     => 'decimal:6',
        'unit_price'        => 'decimal:2',
        'total_idr'         => 'decimal:2',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Hanya invoice yang sudah disetujui sales yang masuk ke Keuangan. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    // ── Status alur sales ──────────────────────────────────────────────────────

    public function getIsApprovedAttribute(): bool
    {
        return ! is_null($this->approved_at);
    }

    /**
     * Tahap alur sales (diturunkan, tanpa kolom terpisah):
     *  - approved : sudah disetujui & masuk Keuangan (terkunci)
     *  - detail   : patokan terkunci, sales merinci item (Tahap 2)
     *  - baseline : masih menyusun patokan (Tahap 1)
     */
    public function getStageAttribute(): string
    {
        if ($this->is_approved) {
            return 'approved';
        }

        return $this->baseline_total > 0 ? 'detail' : 'baseline';
    }

    // ── Nilai turunan dari invoice_items ────────────────────────────────────────

    public function getTotalCostAttribute(): float
    {
        return (float) $this->items->sum('line_cost');
    }

    public function getTotalSellAttribute(): float
    {
        return (float) $this->items->sum('line_sell');
    }

    public function getProfitAttribute(): float
    {
        return $this->total_sell - $this->total_cost;
    }

    public function getPaidAttribute(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->total - $this->paid;
    }

    /**
     * Hitung ulang total proforma (mata uang invoice). total = unit_price × pax.
     * total_idr hanya di-set untuk IDR (kurs 1); untuk mata uang lain nilai IDR
     * ditetapkan saat disetujui (approve) agar laporan IDR tak terdistorsi kurs
     * placeholder sebelum kurs pasti diinput.
     */
    public function syncProformaTotal(): void
    {
        $pax   = (int) ($this->tour?->pax ?? $this->pax ?? 1);
        $total = (float) $this->unit_price * max($pax, 1);

        $updates = ['total' => $total];
        if (($this->currency ?: 'IDR') === 'IDR') {
            $updates['total_idr'] = $total;
        }

        $this->update($updates);
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $inv) {
            if (! $inv->number) {
                $year = now()->year;
                $seq  = static::whereYear('created_at', $year)->count() + 1;
                $inv->number = sprintf('INV-%d-%04d', $year, $seq);
            }
        });
    }
}
