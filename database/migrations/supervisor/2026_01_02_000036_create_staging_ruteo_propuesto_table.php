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
        Schema::connection('supervisor')->create('staging_ruteo_propuesto', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_origen', 255);
            $table->bigInteger('cliente_id');
            $table->string('cliente', 255)->nullable();
            $table->string('ruta_propuesta', 100);
            $table->string('dia_propuesto', 50);
            $table->string('vendedor_propuesto', 255)->nullable();
            $table->string('tipo_cambio', 50);
            $table->string('ruta_anterior', 100)->nullable();
            $table->string('dia_anterior', 50)->nullable();
            $table->string('vendedor_anterior', 255)->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->string('vendedor_norm', 255)->nullable();
            $table->string('dia_norm', 50)->nullable();
            $table->string('estado', 20)->nullable()->default('PENDIENTE');
            $table->timestamp('cargado_en')->nullable()->useCurrent();

            $table->index('cliente_id', 'idx_staging_cliente');
            $table->index('estado', 'idx_staging_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('staging_ruteo_propuesto');
    }
};
