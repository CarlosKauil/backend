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
        Schema::table('users', function (Blueprint $table) {
            // Asegúrate de SÓLO agregar la columna si no existe
            if (!Schema::hasColumn('users', 'profile_id')) {
                $table->unsignedBigInteger('profile_id')->nullable()->after('id');
            }
            // Luego agrega la foreign key (esto sí es seguro agregar varias veces)
            $table->foreign('profile_id')
                ->references('id')->on('profile_links')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            // Elimina la columna solo si quieres que el rollback elimine profile_id
            // $table->dropColumn('profile_id');
        });
    }


};
