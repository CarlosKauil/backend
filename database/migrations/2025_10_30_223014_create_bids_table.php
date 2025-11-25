<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración para crear la tabla 'bids' (pujas)
     * Esta tabla guarda todas las pujas que hacen los usuarios en las subastas
     */
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            // ID único de la puja (clave primaria autoincremental)
            $table->id();
            
            // Relación con la tabla 'auctions' (cada puja pertenece a una subasta)
            // Si se elimina la subasta, se eliminan todas sus pujas (cascade)
            $table->foreignId('auction_id')
                  ->constrained('auctions')
                  ->onDelete('cascade');
            
            // Relación con la tabla 'users' (cada puja la hace un usuario)
            // Si se elimina el usuario, se eliminan sus pujas (cascade)
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            // Monto de la puja (cantidad ofrecida por el usuario)
            // Ejemplo: 1500.00
            $table->decimal('monto', 10, 2);
            
            // Fecha y hora exacta en que se realizó la puja
            $table->timestamp('fecha_puja');
            
            // Timestamps automáticos: created_at y updated_at
            $table->timestamps();
            
            // Índice compuesto para optimizar búsquedas
            // Acelera las consultas que buscan pujas por subasta y ordenan por monto
            // Esto es importante cuando hay muchas pujas
            $table->index(['auction_id', 'monto']);
        });
    }

    /**
     * Revertir la migración (eliminar la tabla 'bids')
     * Se ejecuta cuando haces: php artisan migrate:rollback
     */
    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
