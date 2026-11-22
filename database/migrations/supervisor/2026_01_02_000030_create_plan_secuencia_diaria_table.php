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
        Schema::connection('supervisor')->create('plan_secuencia_diaria', function (Blueprint $table) {
            $table->bigInteger('id')->nullable();
            $table->string('ruta', 255)->nullable();
            $table->string('dia', 255)->nullable();
            $table->bigInteger('secuencia')->nullable();
            $table->bigInteger('cliente_id')->nullable();
            $table->text('cliente')->nullable();
            $table->string('cliente_norm', 255)->nullable();
            $table->text('direccion')->nullable();
            $table->double('latitud')->nullable();
            $table->double('longitud')->nullable();
            $table->string('hora_historica_visita', 255)->nullable();
            $table->string('tiempo_promedio_pdv', 255)->nullable();
            $table->bigInteger('visitas_historicas_count')->nullable();
            $table->double('venta_promedio_bs')->nullable();
            $table->string('estado_secuencia', 255)->nullable();
            $table->string('ultima_modificacion', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('supervisor')->dropIfExists('plan_secuencia_diaria');
    }
};
