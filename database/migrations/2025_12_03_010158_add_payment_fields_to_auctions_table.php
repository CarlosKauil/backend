<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('auctions', function (Blueprint $table) {
            // Agregamos estado de pago. Por defecto es 'pendiente'.
            // Otros valores posibles: 'pagado', 'fallido'.
            $table->string('pago_status')->default('pendiente')->after('ganador_id');
            
            // Fecha en que se realizó el pago
            $table->timestamp('fecha_pago')->nullable()->after('pago_status');
            
            // ID de transacción (útil si integras Stripe/PayPal después)
            $table->string('transaccion_id')->nullable()->after('fecha_pago');
        });
    }
    /**
     * Reverse the migrations.
     */
   public function down()
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['pago_status', 'fecha_pago', 'transaccion_id']);
        });
    }
};
