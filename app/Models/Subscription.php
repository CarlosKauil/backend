<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    // 1. Campos asignables
    protected $fillable = [
        'user_id',
        'plan_id',
        'billing_cycle',
        'status',
        'starts_at',
        'ends_at',
    ];

    // 2. Casts de fechas
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    // 3. Relación con usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 4. Relación con plan
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // 5. Scope de suscripciones activas (no vencidas)
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function ($q) {
                         $q->whereNull('ends_at')
                           ->orWhere('ends_at', '>', now());
                     });
    }
}