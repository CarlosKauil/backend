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
        Schema::table('obras', function (Blueprint $table) {
            $table->dropColumn('precio_subasta');
        });
    }

    public function down()
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->decimal('precio_subasta', 10, 2)->nullable();
        });
    }

};
