<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 1) Campos asignables
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_id',
    ];

    // 2) Campos ocultos
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // 3) Casts
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // 4) Relación con Artist (ya existente)
    public function artist()
    {
        return $this->hasOne(Artist::class, 'user_id');
    }

    // 5) Relación con ProfileLink (ya existente)
    public function profileLink()
    {
        return $this->hasOne(ProfileLink::class);
    }

    // 6) Relación 1–1 con la suscripción actual
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    // 7) Acceso rápido al plan de la suscripción activa
    public function currentPlan()
    {
        return $this->hasOneThrough(
            Plan::class,
            Subscription::class,
            'user_id', // FK en subscriptions
            'id',      // PK en plans
            'id',      // PK en users
            'plan_id'  // FK en subscriptions
        )->where('subscriptions.status', 'active');
    }

    // 8) Helper para saber si el usuario tiene un plan específico
    public function hasPlan(string $slug): bool
    {
        $plan = $this->currentPlan()->first();

        return $plan?->slug === $slug;
    }
}
