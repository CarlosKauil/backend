<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas'; // fuerza el nombre de la tabla
    protected $fillable = ['nombre'];

    /**
     * Relación con el modelo Artist.
     * Un área puede tener muchos artistas.
     */

    public function artists()
    {
        return $this->hasMany(Artist::class);
    }
}