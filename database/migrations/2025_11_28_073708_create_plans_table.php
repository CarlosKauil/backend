<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                // Basico, Pro, Galeria
            $table->string('slug')->unique();      // basic, pro, gallery
            $table->string('type')->default('user'); // user | gallery
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->string('currency', 10)->default('MXN');

            // Reglas de negocio
            $table->unsignedInteger('max_works_per_area')->nullable(); // null = sin límite
            $table->unsignedBigInteger('max_file_size_mb')->nullable(); // null = sin límite
            $table->unsignedTinyInteger('commission_percent'); // 35, 30, 25, etc.

            // Para Plan Galería con dos variantes
            $table->boolean('allows_auctions')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
