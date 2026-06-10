<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourBooking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'booked_at'   => 'datetime',
        'est_cost'    => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    /** status → label tampilan */
    public const STATUSES = [
        'pending'   => 'Belum di-booking',
        'booked'    => 'Sudah di-booking',
        'cancelled' => 'Batal',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
