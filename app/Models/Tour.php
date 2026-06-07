<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Tour extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function package()
    {
        return $this->belongsTo(TourPackage::class, 'package_id');
    }

    public function items()
    {
        return $this->hasMany(TourItem::class)->orderBy('sort_order');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function itineraryDays()
    {
        return $this->hasMany(TourItineraryDay::class)->orderBy('day_number');
    }

    public function getItineraryPdfUrlAttribute(): ?string
    {
        return $this->itinerary_pdf
            ? Storage::disk('public')->url($this->itinerary_pdf)
            : null;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    // --- PERKIRAAN (snapshot tour_items) ---

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

    public function getMarginAttribute(): float
    {
        return $this->total_sell > 0
            ? round($this->profit / $this->total_sell * 100, 1)
            : 0;
    }

    // --- AKTUAL (M6 — dari tabel bills & invoices) ---

    public function getActualCostAttribute(): float
    {
        return (float) $this->bills->sum('amount');
    }

    public function getActualProfitAttribute(): float
    {
        return $this->total_sell - $this->actual_cost;
    }

    public function getCostVarianceAttribute(): float
    {
        return $this->actual_cost - $this->total_cost; // + = boros
    }

    public function getReceivedAttribute(): float
    {
        return (float) $this->invoices->flatMap->payments->sum('amount');
    }

    public function getReceivableAttribute(): float
    {
        return (float) $this->invoices->sum('total') - $this->received;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tour) {
            if (empty($tour->code)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $tour->code = 'WM-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
