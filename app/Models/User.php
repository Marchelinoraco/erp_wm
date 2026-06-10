<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'supplier_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isSales(): bool       { return $this->role === 'sales'; }
    public function isAccountant(): bool  { return $this->role === 'accountant'; }
    public function isOperation(): bool   { return $this->role === 'operation'; }
    public function isField(): bool       { return in_array($this->role, ['guide', 'driver', 'tour_leader']); }
    public function isTravelAgent(): bool { return $this->role === 'travel_agent'; }

    public function homePath(): string
    {
        return match (true) {
            $this->isAdmin(), $this->isSales() => route('dashboard', absolute: false),
            $this->isAccountant()              => route('finance.index', absolute: false),
            $this->isOperation()               => route('bookings.index', absolute: false),
            $this->isTravelAgent()             => route('agent.products.index', absolute: false),
            default                            => route('my-jobs', absolute: false),
        };
    }
}
