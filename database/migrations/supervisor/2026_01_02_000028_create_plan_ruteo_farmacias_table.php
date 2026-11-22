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
        Schema::connection('supervisor')->create('plan_ruteo_farmacias', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ruta', 100)->default('TDB 8 FARMACIAS');
            $table->integer('cliente_id')->nullable();
            $table->string('cod_cliente', 100)->nullable();
            $table->string('cliente', 255);
            $table->string('nit', 50)->nullable()->default(0);
            $table->string('ci', 50)->nullable()->default(0);
            $table->text('direccion')->nullable();
            $table->string('dia', 50);
            $table->double('latitud')->nullable()->default(0);
            $table->double('longitud')->nullable()->default(0);
            $table->string('origen', 30);
            $table->integer('origen_id');
            $table->string('estado', 30)->default('ACTIVO');
            $table->timestamp('creado_el')->nullable()->useCurrent();
            $table->boolean('alta_tramitada')->default(false);
            $table->longText('alta_datos')->nullable();

            $table->index(['ruta', 'estado'], 'idx_ruta_estado');
            $table->index(['origen', 'origen_id'], 'idx_origen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('plan_ruteo_farmacias');
    }
};
