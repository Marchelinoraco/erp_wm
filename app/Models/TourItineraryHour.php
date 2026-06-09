<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourItineraryHour extends Model
{
    protected $fillable = ['tour_id', 'day_number', 'start_time', 'end_time', 'activity', 'notes'];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
