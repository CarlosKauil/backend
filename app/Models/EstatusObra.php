<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstatusObra extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    public function obras() {
        return $this->hasMany(Obra::class, 'estatus_id');
    }
}