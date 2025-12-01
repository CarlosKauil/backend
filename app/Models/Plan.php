<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    // 1. Campos que se pueden asignar en masa
    protected $fillable = [
        'name',
        'slug',
        'type',                 // user | gallery
        'price_monthly',
        'currency',
        'max_works_per_area',
        'max_file_size_mb',
        'commission_percent',
        'allows_auctions',
        'is_active',
    ];

    // 2. Casts para tipos nativos
    protected $casts = [
        'price_monthly'      => 'decimal:2',
        'max_works_per_area' => 'integer',
        'max_file_size_mb'   => 'integer',
        'commission_percent' => 'integer',
        'allows_auctions'    => 'boolean',
        'is_active'          => 'boolean',
    ];

    // 3. Relación con suscripciones
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // 4. Scope para solo planes activos
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}