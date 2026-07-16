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
        'bank_account_ids'   => 'array',
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

        // Simpan pax yang dipakai menghitung total — PDF menampilkan pax invoice,
        // jadi keduanya harus selalu berasal dari angka yang sama.
        $updates = ['total' => $total, 'pax' => max($pax, 1)];
        if (($this->currency ?: 'IDR') === 'IDR') {
            $updates['total_idr'] = $total;
        }

        $this->update($updates);
    }

    /**
     * Nomor keuangan gapless berikutnya (INV-<tahun>-NNNN), reset per tahun.
     * Panggil di dalam transaksi — lockForUpdate mencegah dua approve
     * bersamaan mendapat nomor yang sama.
     */
    public static function nextFinanceNumber(): string
    {
        $prefix = 'INV-' . now()->year . '-';
        $latest = static::where('finance_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('finance_number')
            ->value('finance_number');
        $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor invoice per tipe penjualan & tahun (INV-<tahun>-<kode tipe>-NNNN),
     * urut berdasarkan KAPAN INVOICE ini dibuat — bukan urutan tour-nya.
     * Kode tipe sama persis dengan Tour::resolveTypeCode() (11=Tour Inbound,
     * 12=Outbound, 13=Rental, dst) sehingga nomor tetap jadi referensi tipe
     * penjualan sekilas pandang. Panggil di dalam transaksi — lockForUpdate
     * mencegah dua invoice bersamaan (tipe & tahun sama) dapat nomor sama.
     */
    public static function nextNumber(?Tour $tour): string
    {
        $typeCode = $tour?->resolveTypeCode() ?? '11';
        $prefix   = 'INV-' . now()->year . '-' . $typeCode . '-';
        $latest   = static::where('number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');
        $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $inv) {
            if (! $inv->number) {
                $inv->number = static::nextNumber($inv->tour);
            }
        });
    }
}
