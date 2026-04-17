<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_id',
        'plan',
        'gallery_requested',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function artist()
    {
        return $this->hasOne(Artist::class, 'user_id');
    }

    public function profileLink()
    {
        return $this->hasOne(ProfileLink::class);
    }

    // ❌ Elimina subscription(), currentPlan() y hasPlan()
    // Cashier ya los maneja internamente
}