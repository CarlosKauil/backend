<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración para crear la tabla 'auctions' (subastas)
     * Esta tabla almacena las subastas de las obras de arte
     */
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            // ID único de la subasta (clave primaria autoincremental)
            $table->id();
            
            // Relación con la tabla 'obras' (cada subasta pertenece a una obra)
            // Si se elimina la obra, se elimina automáticamente la subasta (cascade)
            $table->foreignId('obra_id')
                  ->constrained('obras')
                  ->onDelete('cascade');
            
            // Precio inicial de la subasta (con 2 decimales)
            // Ejemplo: 1000.00
            $table->decimal('precio_inicial', 10, 2);
            
            // Precio actual de la subasta (la puja más alta hasta el momento)
            // Puede ser NULL al inicio si no hay pujas
            $table->decimal('precio_actual', 10, 2)->nullable();
            
            // Monto mínimo que debe aumentar cada puja
            // Por defecto es 100.00 (puedes cambiarlo después)
            $table->decimal('incremento_minimo', 10, 2)->default(100.00);
            
            // Fecha y hora de inicio de la subasta
            $table->timestamp('fecha_inicio');
            
            // Fecha y hora de finalización de la subasta
            $table->timestamp('fecha_fin');
            
            // Estado de la subasta (solo puede ser uno de estos 4 valores):
            // - 'programada': Creada pero aún no ha iniciado
            // - 'activa': En curso, aceptando pujas
            // - 'finalizada': Terminó (por tiempo o manualmente)
            // - 'cancelada': Cancelada por el administrador
            $table->enum('estado', ['programada', 'activa', 'finalizada', 'cancelada'])
                  ->default('programada');
            
            // Usuario ganador de la subasta (quien hizo la puja más alta)
            // Es NULL mientras la subasta está activa
            // Si se elimina el usuario, este campo se pone en NULL (set null)
            $table->foreignId('ganador_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            // Timestamps automáticos: created_at y updated_at
            $table->timestamps();
        });
    }

    /**
     * Revertir la migración (eliminar la tabla 'auctions')
     * Se ejecuta cuando haces: php artisan migrate:rollback
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
