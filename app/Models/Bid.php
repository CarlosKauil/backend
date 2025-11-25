<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    /**
     * Nombre de la tabla asociada al modelo
     */
    protected $table = 'bids';

    /**
     * Campos que se pueden asignar masivamente
     * Estos son los campos que puedes llenar al crear una puja
     */
    protected $fillable = [
        'auction_id',  // ID de la subasta a la que pertenece
        'user_id',     // ID del usuario que hizo la puja
        'monto',       // Cantidad de dinero ofrecida
        'fecha_puja'   // Fecha y hora exacta de la puja
    ];

    /**
     * Convertir automáticamente estos campos a tipos específicos
     */
    protected $casts = [
        'fecha_puja' => 'datetime', // Convierte a objeto Carbon
        'monto' => 'decimal:2'      // Asegura 2 decimales
    ];

    // ==========================================
    // RELACIONES (Relationships)
    // ==========================================

    /**
     * Relación: Una puja pertenece a UNA subasta
     * Esto te permite hacer: $bid->auction->precio_inicial
     */
    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * Relación: Una puja pertenece a UN usuario
     * Esto te permite hacer: $bid->user->name
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // MÉTODOS ÚTILES (Helper Methods)
    // ==========================================

    /**
     * Verificar si esta puja es la ganadora actual de su subasta
     * 
     * @return bool - true si es la puja más alta, false si no
     */
    public function esGanadora()
    {
        // Obtener la puja más alta de esta subasta
        $pujaMasAlta = $this->auction->bids()->orderBy('monto', 'desc')->first();
        
        // Comparar si esta puja es la más alta
        return $pujaMasAlta && $pujaMasAlta->id === $this->id;
    }

    /**
     * Formatear el monto con símbolo de moneda
     * 
     * @return string - Ejemplo: "$1,500.00"
     */
    public function montoFormateado()
    {
        return '$' . number_format($this->monto, 2);
    }
}
