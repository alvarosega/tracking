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
        Schema::connection('supervisor')->create('dim_fronteras_rutas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ruta', 100);
            $table->string('canal', 50)->nullable();
            $table->polygon('poligono');
            $table->integer('version')->default(1);
            $table->enum('estado', ['ACTIVO','HISTORICO'])->default('ACTIVO');
            $table->enum('origen', ['AUTOMATICO_PUNTOS','MANUAL_SUPERVISOR'])->default('AUTOMATICO_PUNTOS');
            $table->timestamp('actualizado_en')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('creado_en')->nullable()->useCurrent();

            $table->unique(['ruta', 'estado'], 'uq_ruta_activa');
            $table->index('ruta', 'idx_frontera_ruta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('dim_fronteras_rutas');
    }
};
