<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute; // 👈 Importación requerida

class Obra extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'artist_id', 'area_id', 'nombre', 'archivo', 'genero_tecnica',
        'anio_creacion', 'descripcion', 'estatus_id', 'precio', 'precio_subastable', 'es_subastable'
    ];
    
    // 👈 Accessor para generar la URL pública
    protected function archivoUrl(): Attribute
    {
        return Attribute::get(
            // La función toma el valor del campo 'archivo' (ej: obras/modelado/file.glb)
            // y lo convierte en una URL pública (ej: http://localhost:8000/storage/obras/modelado/file.glb)
            fn (mixed $value, array $attributes) => $attributes['archivo'] 
                ? asset('storage/' . $attributes['archivo'])
                : null,
        );
    }
    
    /**
     * Los atributos que deben ser anexados al serializar el modelo.
     * Esto asegura que 'archivo_url' esté presente en la respuesta JSON.
     */
    protected $appends = ['archivo_url']; 
    
    protected $casts = [
        'es_subastable' => 'boolean',
    ];


    // Relaciones (Mantenidas)

    public function artist() {
        return $this->belongsTo(Artist::class);
    }

    public function area() {
        return $this->belongsTo(Area::class);
    }

    public function estatus() {
        return $this->belongsTo(EstatusObra::class, 'estatus_id');
    }

    public function mensajesRechazo() {
        return $this->hasMany(MensajeRechazo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}