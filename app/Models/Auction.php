<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Librería para manejar fechas fácilmente

class Auction extends Model
{
    /**
     * Nombre de la tabla asociada al modelo
     * Por defecto Laravel buscaría 'auctions' (plural de Auction)
     * Pero es buena práctica especificarlo explícitamente
     */
    protected $table = 'auctions';

    /**
     * Campos que se pueden asignar masivamente
     * Es decir, campos que puedes llenar con: Auction::create([...])
     * Los campos que NO estén aquí, Laravel los bloqueará por seguridad
     */
    protected $fillable = [
        'obra_id',           // ID de la obra que se subasta
        'precio_inicial',    // Precio base de la subasta
        'precio_actual',     // Precio actual (la puja más alta)
        'incremento_minimo', // Cuánto debe aumentar cada puja
        'fecha_inicio',      // Cuándo inicia la subasta
        'fecha_fin',         // Cuándo termina la subasta
        'estado',            // Estado: programada, activa, finalizada, cancelada
        'ganador_id'         // ID del usuario ganador (NULL si aún no termina)
    ];

    /**
     * Convertir automáticamente estos campos a tipos específicos
     * 'datetime' convierte strings de la BD a objetos Carbon (fáciles de manipular)
     */
    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    // ==========================================
    // RELACIONES (Relationships)
    // ==========================================

    /**
     * Relación: Una subasta pertenece a UNA obra
     * Esto te permite hacer: $auction->obra->titulo
     */
    public function obra()
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Relación: Una subasta puede tener MUCHAS pujas
     * Esto te permite hacer: $auction->bids (obtener todas las pujas)
     * Las ordenamos por monto descendente (la más alta primero)
     */
    public function bids()
    {
        return $this->hasMany(Bid::class)->orderBy('monto', 'desc');
    }

    /**
     * Relación: Una subasta tiene UN ganador (usuario)
     * Esto te permite hacer: $auction->ganador->name
     * El ganador es NULL hasta que finalice la subasta
     */
    public function ganador()
    {
        return $this->belongsTo(User::class, 'ganador_id');
    }

    // ==========================================
    // MÉTODOS ÚTILES (Helper Methods)
    // ==========================================

    /**
     * Verificar si la subasta está activa
     * 
     * @return bool - true si está activa, false si no
     * 
     * Una subasta está activa si:
     * 1. Su estado es 'activa'
     * 2. La fecha actual está entre fecha_inicio y fecha_fin
     */
    public function isActiva()
    {
        return $this->estado === 'activa' 
            && Carbon::now()->between($this->fecha_inicio, $this->fecha_fin);
    }

    /**
     * Calcular el tiempo restante en segundos
     * 
     * @return int|null - Segundos restantes o NULL si no está activa
     * 
     * Ejemplo de uso:
     * $segundos = $auction->tiempoRestante();
     * if ($segundos > 0) {
     *     echo "Quedan $segundos segundos";
     * }
     */
    public function tiempoRestante()
    {
        // Si la subasta no está activa, no hay tiempo restante
        if (!$this->isActiva()) {
            return null;
        }
        
        // diffInSeconds calcula la diferencia en segundos
        // El segundo parámetro 'false' permite valores negativos si ya pasó la fecha
        return Carbon::now()->diffInSeconds($this->fecha_fin, false);
    }

    /**
     * Obtener la puja más alta (ganadora actual)
     * 
     * @return Bid|null - La puja más alta o NULL si no hay pujas
     */
    public function pujaMasAlta()
    {
        return $this->bids()->orderBy('monto', 'desc')->first();
    }

    /**
     * Verificar si un usuario ya hizo una puja en esta subasta
     * 
     * @param int $userId - ID del usuario
     * @return bool - true si ya pujó, false si no
     */
    public function usuarioYaPujo($userId)
    {
        return $this->bids()->where('user_id', $userId)->exists();
    }
}
