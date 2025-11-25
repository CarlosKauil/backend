<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Artist extends Model
{
    //
         //
        protected $fillable = [
        'user_id',
        'alias',
        'fecha_nacimiento',
        'area_id',
        'link',
        'instagram',
        'x_twitter',
        'linkedin',
        'country',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    // Relaciones
    
    /**
     * Obtiene el usuario propietario del perfil del artista.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el área artística asociada al artista.
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Obtiene todas las obras creadas por este artista.
     */
    public function obras()
    {
        return $this->hasMany(Obra::class);
    }

}
