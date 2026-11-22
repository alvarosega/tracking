<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('supervisor')->create('dim_calendario_mensual', function (Blueprint $table) {
            $table->integer('year');
            $table->integer('month');
            $table->string('mes_nombre', 20);
            $table->integer('dias_habiles');

            $table->primary(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('dim_calendario_mensual');
    }
};
