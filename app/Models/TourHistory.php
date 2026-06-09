<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourHistory extends Model
{
    protected $fillable = ['tour_id', 'status_snapshot', 'type', 'description', 'created_by'];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
