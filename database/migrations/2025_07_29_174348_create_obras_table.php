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
        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('artist_id');
            $table->unsignedBigInteger('area_id');
            $table->string('nombre');
            $table->string('archivo'); // ruta o nombre del archivo
            $table->string('genero_tecnica');
            $table->year('anio_creacion');
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('estatus_id')->default(1); // 1: pendiente
            $table->timestamps();

            $table->foreign('artist_id')->references('id')->on('artists')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('areas');
            $table->foreign('estatus_id')->references('id')->on('estatus_obras');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obras');
    }
};
