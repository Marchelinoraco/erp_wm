<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourPackage extends Model
{
    protected $guarded = [];

    public function tours()
    {
        return $this->hasMany(Tour::class, 'package_id');
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->duration_nights > 0) {
            return "{$this->duration_days}D{$this->duration_nights}N";
        }
        return "{$this->duration_days}D";
    }
}
