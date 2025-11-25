<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensajeRechazo extends Model
{
    use HasFactory;

    protected $table = 'mensajes_rechazo';

    protected $fillable = [
        'obra_id',
        'receptor_id',
        'emisor_id',
        'mensaje'
    ];

    // Relación con la obra
    public function obra() {
        return $this->belongsTo(Obra::class);
    }

    // Relación con el usuario que envía el mensaje (admin)
    public function emisor() {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    // Relación con el usuario que recibe el mensaje (artista)
    public function receptor() {
        return $this->belongsTo(User::class, 'receptor_id');
    }
        public function mensajesRechazo()
    {
        return $this->hasMany(MensajeRechazo::class, 'obra_id');
    }
}
