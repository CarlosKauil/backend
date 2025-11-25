<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de roles (¡Debe ir primero!)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // Ej: user, artist
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // 2. Tabla principal de usuarios con campos personalizados
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->string('profile_picture', 255)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
        });

        // 3. Tabla de tokens para recuperación de contraseña
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 4. Tabla de sesiones (si usas driver 'database')
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 5. Tabla de tokens de usuario (JWT, refresh, API tokens)
        Schema::create('user_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token', 512);
            $table->enum('type', ['jwt', 'refresh', 'api'])->default('jwt');
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
