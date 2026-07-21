<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'remind_at'   => 'date',
        'is_done'     => 'boolean',
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return ! $this->is_done && $this->remind_at->isPast();
    }

    public function getIsTodayAttribute(): bool
    {
        return ! $this->is_done && $this->remind_at->isToday();
    }
}
