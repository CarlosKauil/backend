<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileIdForeignToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Asegúrate de que el campo profile_id es unsignedBigInteger y puede ser null.
            // Si ya existe y fue creado correctamente, esta línea puede omitirse.
            // $table->unsignedBigInteger('profile_id')->nullable()->change();

            $table->foreign('profile_id')
                ->references('id')->on('profile_links')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
        });
    }
}
