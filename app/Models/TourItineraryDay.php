<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourItineraryDay extends Model
{
    protected $fillable = ['tour_id', 'day_number', 'title', 'title_ind', 'description', 'description_ind'];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
